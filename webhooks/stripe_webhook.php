<?php
/**
 * Webhook Stripe — HackConcursos
 * FIX C1: Validação HMAC-SHA256 obrigatória antes de processar qualquer evento.
 */
require_once __DIR__ . '/../config/config.php';

$payload    = file_get_contents('php://input');
$sigHeader  = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

// ─────────────────────────────────────────────────────────────
// C1: VERIFICAÇÃO DE ASSINATURA (impede eventos forjados)
// ─────────────────────────────────────────────────────────────
function verificarAssinaturaStripe(string $payload, string $sigHeader, string $secret): bool {
    // Formato do header: t=timestamp,v1=assinatura
    $parts = [];
    foreach (explode(',', $sigHeader) as $part) {
        [$k, $v] = explode('=', $part, 2) + ['', ''];
        $parts[$k] = $v;
    }

    $timestamp = $parts['t'] ?? '';
    $v1        = $parts['v1'] ?? '';

    if (empty($timestamp) || empty($v1)) return false;

    // Rejeitar eventos com mais de 5 minutos (proteção replay)
    if (abs(time() - (int)$timestamp) > 300) return false;

    $assinadoPayload = $timestamp . '.' . $payload;
    $esperado        = hash_hmac('sha256', $assinadoPayload, $secret);

    return hash_equals($esperado, $v1);
}

$webhookSecret = defined('STRIPE_WEBHOOK_SECRET') ? STRIPE_WEBHOOK_SECRET : '';

if (empty($webhookSecret)) {
    // Se a constante não estiver configurada, bloquear em produção
    error_log('STRIPE_WEBHOOK_SECRET não configurado.');
    http_response_code(500);
    exit;
}

if (!verificarAssinaturaStripe($payload, $sigHeader, $webhookSecret)) {
    error_log('Webhook Stripe: assinatura inválida ou evento rejeitado.');
    http_response_code(400);
    exit;
}

$event = json_decode($payload, true);

if (!$event || !isset($event['type'])) {
    http_response_code(400);
    exit;
}

$db = getDB();

switch ($event['type']) {
    case 'checkout.session.completed':
        $session    = $event['data']['object'];
        $usuario_id = (int)($session['metadata']['usuario_id'] ?? 0);
        $tipo       = $session['metadata']['tipo'] ?? 'assinatura';
        $stripe_id  = $session['customer'] ?? '';
        $session_id = $session['id'] ?? ''; // ID único da sessão Stripe

        if (!$usuario_id) break;

        // ─────────────────────────────────────────────────────
        // IDEMPOTÊNCIA: Verificar se este evento já foi processado
        // ─────────────────────────────────────────────────────
        $chkDup = $db->prepare("SELECT COUNT(*) FROM token_transacoes WHERE stripe_session_id = ? AND usuario_id = ?");
        $chkDup->execute([$session_id, $usuario_id]);
        if ($chkDup->fetchColumn() > 0) {
            // Evento já processado — responder 200 para Stripe parar de reenviar
            error_log('Webhook Stripe: evento duplicado ignorado. session_id=' . $session_id);
            http_response_code(200);
            exit;
        }

        if ($tipo === 'assinatura') {
            $stmt = $db->prepare("UPDATE usuarios SET plano = 'premium', status_assinatura = 'ativa', stripe_id = ? WHERE id = ?");
            $stmt->execute([$stripe_id, $usuario_id]);

            // Registrar evento com session_id para idempotência
            $db->prepare("INSERT INTO token_transacoes (usuario_id, tipo, quantidade, descricao, stripe_session_id) VALUES (?, 'assinatura', 0, 'Ativação Premium (Stripe)', ?)")
               ->execute([$usuario_id, $session_id]);

            // Registrar pedido para faturamento do dashboard (FIX M12)
            $db->prepare("INSERT INTO pedidos (usuario_id, total, status) VALUES (?, 49.90, 'pago')")
               ->execute([$usuario_id]);

        } elseif ($tipo === 'tokens') {
            $db->prepare("UPDATE usuarios SET token_saldo = token_saldo + 50 WHERE id = ?")
               ->execute([$usuario_id]);

            $db->prepare("INSERT INTO token_transacoes (usuario_id, tipo, quantidade, descricao, stripe_session_id) VALUES (?, 'compra', 50, 'Compra de Pacote (Stripe)', ?)")
               ->execute([$usuario_id, $session_id]);

            // Registrar pedido para faturamento do dashboard (FIX M12)
            $db->prepare("INSERT INTO pedidos (usuario_id, total, status) VALUES (?, 29.00, 'pago')")
               ->execute([$usuario_id]);
        }

        // Registrar evento geral (sem payload completo — privacidade)
        $db->prepare("INSERT INTO eventos_usuario (usuario_id, evento, metadata) VALUES (?, 'pagamento_confirmado', ?)")
           ->execute([$usuario_id, json_encode(['tipo' => $tipo, 'session_id' => $session_id])]);
        break;

    case 'customer.subscription.deleted':
        $subscription = $event['data']['object'];
        $stripe_id    = $subscription['customer'];
        $db->prepare("UPDATE usuarios SET plano = 'free', status_assinatura = 'cancelada' WHERE stripe_id = ?")
           ->execute([$stripe_id]);
        break;
}

http_response_code(200);
