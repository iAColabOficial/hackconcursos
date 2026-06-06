<?php
require_once __DIR__ . '/../config/config.php';
exigirAdmin();
header('Content-Type: application/json');

$db   = getDB();
$body = json_decode(file_get_contents('php://input'), true);

$action = $body['action'] ?? '';
$uid    = (int)($body['usuario_id'] ?? 0);

if (!$uid) {
    echo json_encode(['ok' => false, 'msg' => 'ID inválido']);
    exit;
}

// Verifica que o usuário existe e é aluno
$check = $db->prepare("SELECT id, ativo, plano, token_saldo FROM usuarios WHERE id = ? AND perfil = 'aluno'");
$check->execute([$uid]);
$userRow = $check->fetch();

if (!$userRow) {
    echo json_encode(['ok' => false, 'msg' => 'Usuário não encontrado']);
    exit;
}

switch ($action) {

    // ------------------------------------------------------------------
    // Alterar plano do usuário
    // ------------------------------------------------------------------
    case 'alterar_plano':
        $planosValidos = ['free', 'basico', 'premium'];
        $plano = $body['plano'] ?? '';

        if (!in_array($plano, $planosValidos, true)) {
            echo json_encode(['ok' => false, 'msg' => 'Plano inválido. Use: free, basico ou premium']);
            exit;
        }

        $statusAssinatura = ($plano === 'premium') ? 'ativa_admin' : 'inactive';

        $stmt = $db->prepare(
            "UPDATE usuarios SET plano = ?, status_assinatura = ? WHERE id = ?"
        );
        $stmt->execute([$plano, $statusAssinatura, $uid]);

        echo json_encode([
            'ok'    => true,
            'msg'   => 'Plano alterado para ' . strtoupper($plano) . ' com sucesso.',
            'plano' => $plano,
        ]);
        break;

    // ------------------------------------------------------------------
    // Adicionar tokens manualmente
    // ------------------------------------------------------------------
    case 'adicionar_tokens':
        $quantidade = (int)($body['quantidade'] ?? 0);

        if ($quantidade <= 0 || $quantidade > 9999) {
            echo json_encode(['ok' => false, 'msg' => 'Quantidade inválida. Informe entre 1 e 9999.']);
            exit;
        }

        // Incrementa saldo
        $stmt = $db->prepare(
            "UPDATE usuarios SET token_saldo = token_saldo + ? WHERE id = ?"
        );
        $stmt->execute([$quantidade, $uid]);

        // Registra a transação
        $trans = $db->prepare(
            "INSERT INTO token_transacoes (usuario_id, tipo, quantidade, descricao)
             VALUES (?, 'bonus', ?, 'Crédito Manual (Admin)')"
        );
        $trans->execute([$uid, $quantidade]);

        // Lê novo saldo
        $saldoStmt = $db->prepare("SELECT token_saldo FROM usuarios WHERE id = ?");
        $saldoStmt->execute([$uid]);
        $novoSaldo = (int)$saldoStmt->fetchColumn();

        echo json_encode([
            'ok'         => true,
            'msg'        => $quantidade . ' tokens adicionados com sucesso.',
            'novo_saldo' => $novoSaldo,
        ]);
        break;

    // ------------------------------------------------------------------
    // Toggle bloqueio do usuário
    // ------------------------------------------------------------------
    case 'bloquear':
        $novoAtivo = $userRow['ativo'] ? 0 : 1;

        $stmt = $db->prepare("UPDATE usuarios SET ativo = ? WHERE id = ?");
        $stmt->execute([$novoAtivo, $uid]);

        $statusTexto = $novoAtivo ? 'ativado' : 'bloqueado';

        echo json_encode([
            'ok'         => true,
            'msg'        => 'Usuário ' . $statusTexto . ' com sucesso.',
            'novo_status' => $novoAtivo,
        ]);
        break;

    // ------------------------------------------------------------------
    // Redefinir senha do usuário
    // ------------------------------------------------------------------
    case 'redefinir_senha':
        // Gera senha aleatória de 10 chars (letras + números)
        $chars  = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $length = 10;
        $senha  = '';
        for ($i = 0; $i < $length; $i++) {
            $senha .= $chars[random_int(0, strlen($chars) - 1)];
        }

        $hash = password_hash($senha, PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]);

        $stmt = $db->prepare("UPDATE usuarios SET senha = ? WHERE id = ?");
        $stmt->execute([$hash, $uid]);

        echo json_encode([
            'ok'         => true,
            'msg'        => 'Senha redefinida com sucesso. Copie a senha abaixo e entregue ao usuário.',
            'senha'      => $senha,
            'nova_senha' => $senha,
        ]);
        break;

    // ------------------------------------------------------------------
    default:
        echo json_encode(['ok' => false, 'msg' => 'Ação inválida']);
        break;
}
