<?php
require_once __DIR__ . '/../config/config.php';

// Receber o corpo da requisição
$payload = file_get_contents('php://input');
$event = json_decode($payload, true);

if (!$event) {
    http_response_code(400);
    exit;
}

// Log simples para debug (remover em produção)
file_put_contents('stripe_log.txt', date('Y-m-d H:i:s') . " - Evento: " . $event['type'] . "\n", FILE_APPEND);

$db = getDB();

switch ($event['type']) {
    case 'checkout.session.completed':
        $session = $event['data']['object'];
        $usuario_id = $session['metadata']['usuario_id'] ?? null;
        $tipo = $session['metadata']['tipo'] ?? 'assinatura';
        $stripe_id = $session['customer'] ?? '';

        if ($usuario_id) {
            if ($tipo === 'assinatura') {
                // Atualizar Plano do Usuário
                $stmt = $db->prepare("UPDATE usuarios SET plano = 'premium', status_assinatura = 'ativa', stripe_id = ? WHERE id = ?");
                $stmt->execute([$stripe_id, $usuario_id]);
            } 
            elseif ($tipo === 'tokens') {
                // Adicionar 50 Tokens
                $db->prepare("UPDATE usuarios SET token_saldo = token_saldo + 50 WHERE id = ?")->execute([$usuario_id]);
                
                // Registrar Transação
                $stmtTrans = $db->prepare("INSERT INTO token_transacoes (usuario_id, tipo, quantidade, descricao) VALUES (?, 'compra', 50, 'Compra de Pacote (Stripe)')");
                $stmtTrans->execute([$usuario_id]);
            }

            // Registrar Evento Geral
            $stmtEv = $db->prepare("INSERT INTO eventos_usuario (usuario_id, evento, metadata) VALUES (?, 'pagamento_confirmado', ?)");
            $stmtEv->execute([$usuario_id, $payload]);
        }
        break;

    case 'customer.subscription.deleted':
        // Assinatura Cancelada
        $subscription = $event['data']['object'];
        $stripe_id = $subscription['customer'];
        $db->prepare("UPDATE usuarios SET plano = 'free', status_assinatura = 'cancelada' WHERE stripe_id = ?")->execute([$stripe_id]);
        break;
}

http_response_code(200);
