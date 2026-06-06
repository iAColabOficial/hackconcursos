<?php
/**
 * HackConcursos — TokenManager
 * Gerencia todo o consumo, crédito e consulta de tokens de IA.
 * Centralizar aqui evita débitos duplicados, inconsistências e facilita auditorias.
 */

class TokenManager
{
    /**
     * Verifica se o usuário tem saldo suficiente OU é premium (ilimitado).
     *
     * @param int $usuarioId
     * @param int $custo       Custo em tokens da operação
     * @return array           ['pode' => bool, 'saldo' => int, 'plano' => string, 'msg' => string]
     */
    public static function verificar(int $usuarioId, int $custo = 1): array
    {
        $db   = getDB();
        $stmt = $db->prepare("SELECT plano, token_saldo FROM usuarios WHERE id = ? AND ativo = 1");
        $stmt->execute([$usuarioId]);
        $user = $stmt->fetch();

        if (!$user) {
            return ['pode' => false, 'saldo' => 0, 'plano' => 'free', 'msg' => 'Usuário não encontrado.'];
        }

        // Premium tem acesso ilimitado
        if ($user['plano'] === 'premium') {
            return ['pode' => true, 'saldo' => PHP_INT_MAX, 'plano' => 'premium', 'msg' => ''];
        }

        $saldo = (int)$user['token_saldo'];
        if ($saldo < $custo) {
            return [
                'pode'  => false,
                'saldo' => $saldo,
                'plano' => $user['plano'],
                'msg'   => "Você não tem tokens suficientes para esta operação. Necessário: {$custo}, Disponível: {$saldo}."
            ];
        }

        return ['pode' => true, 'saldo' => $saldo, 'plano' => $user['plano'], 'msg' => ''];
    }

    /**
     * Consome tokens do usuário (somente se não for premium).
     * Registra automaticamente em token_transacoes.
     *
     * @param int    $usuarioId
     * @param int    $custo      Quantidade a debitar
     * @param string $servico    Nome do serviço (ex: 'Mentor IA', 'Simulado IA')
     * @return array             ['ok' => bool, 'saldo_restante' => int, 'msg' => string]
     */
    public static function consumir(int $usuarioId, int $custo, string $servico): array
    {
        $check = self::verificar($usuarioId, $custo);

        if (!$check['pode']) {
            return ['ok' => false, 'saldo_restante' => $check['saldo'], 'msg' => $check['msg'], 'paywall' => true];
        }

        // Premium não debita
        if ($check['plano'] === 'premium') {
            return ['ok' => true, 'saldo_restante' => PHP_INT_MAX, 'msg' => ''];
        }

        $db = getDB();

        // Débito atômico com proteção contra race condition
        $stmt = $db->prepare("
            UPDATE usuarios 
            SET token_saldo = token_saldo - ?
            WHERE id = ? AND token_saldo >= ?
        ");
        $stmt->execute([$custo, $usuarioId, $custo]);

        if ($stmt->rowCount() === 0) {
            return ['ok' => false, 'saldo_restante' => 0, 'msg' => 'Saldo insuficiente.', 'paywall' => true];
        }

        // Registrar transação de débito
        $db->prepare("
            INSERT INTO token_transacoes (usuario_id, tipo, quantidade, servico, descricao)
            VALUES (?, 'consumo', ?, ?, ?)
        ")->execute([$usuarioId, -$custo, $servico, "Uso de: {$servico}"]);

        // FIX C4: usar prepared statement (eliminado SQL Injection por interpolação direta)
        $stSaldo = $db->prepare("SELECT token_saldo FROM usuarios WHERE id = ?");
        $stSaldo->execute([$usuarioId]);
        $saldoNovo = (int)$stSaldo->fetchColumn();

        return ['ok' => true, 'saldo_restante' => (int)$saldoNovo, 'msg' => ''];
    }

    /**
     * Adiciona tokens ao usuário (compra, bônus, etc).
     * Registra em token_transacoes.
     *
     * @param int    $usuarioId
     * @param int    $quantidade
     * @param string $tipo        'compra' | 'bonus'
     * @param string $descricao
     * @return bool
     */
    public static function creditar(int $usuarioId, int $quantidade, string $tipo = 'compra', string $descricao = ''): bool
    {
        if ($quantidade <= 0) return false;

        $db = getDB();
        $db->prepare("UPDATE usuarios SET token_saldo = token_saldo + ? WHERE id = ?")->execute([$quantidade, $usuarioId]);
        $db->prepare("
            INSERT INTO token_transacoes (usuario_id, tipo, quantidade, descricao)
            VALUES (?, ?, ?, ?)
        ")->execute([$usuarioId, $tipo, $quantidade, $descricao ?: ucfirst($tipo) . " de {$quantidade} tokens"]);

        return true;
    }

    /**
     * Retorna o saldo atual do usuário.
     * Para premium, retorna uma string '∞'.
     *
     * @param int $usuarioId
     * @return string|int
     */
    public static function saldo(int $usuarioId)
    {
        $db   = getDB();
        $stmt = $db->prepare("SELECT plano, token_saldo FROM usuarios WHERE id = ?");
        $stmt->execute([$usuarioId]);
        $user = $stmt->fetch();

        if (!$user) return 0;
        if ($user['plano'] === 'premium') return '∞';
        return (int)$user['token_saldo'];
    }

    /**
     * Consome tokens e lança exceção em caso de falha.
     * Use este método dentro de blocos try/catch nos controllers.
     *
     * @throws \RuntimeException
     */
    public static function consumirOuFalhar(int $usuarioId, int $custo, string $servico): void
    {
        $result = self::consumir($usuarioId, $custo, $servico);
        if (!$result['ok']) {
            throw new \RuntimeException($result['msg'], isset($result['paywall']) ? 402 : 500);
        }
    }
}
