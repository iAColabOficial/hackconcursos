<?php
require_once __DIR__ . '/../config/config.php';
exigirLogin('../login.php');

$tipo = $_GET['tipo'] ?? 'assinatura'; // 'assinatura' ou 'tokens'
// FIX M7: Whitelist explícita de tipo para evitar injeção
if (!in_array($tipo, ['assinatura', 'tokens'], true)) {
    die('Tipo de checkout inválido.');
}

$usuario_id = (int)$_SESSION['usuario_id'];
$email = filter_var($_SESSION['email'], FILTER_VALIDATE_EMAIL);

if (!$email) {
    die('E-mail do usuário inválido.');
}

// Configurações baseadas no tipo
if ($tipo === 'tokens') {
    $price_id = PRICE_50_TOKENS;
    $mode = 'payment'; // Pagamento único
    $success_url = APP_URL . '/aluno/meu_plano.php?success=tokens&session_id={CHECKOUT_SESSION_ID}';
} else {
    $price_id = PRICE_MODO_GUERRA;
    $mode = 'subscription'; // Recorrente
    $success_url = APP_URL . '/aluno/meu_plano.php?success=assinatura&session_id={CHECKOUT_SESSION_ID}';
}

$cancel_url = APP_URL . '/planos.php?cancel=1';

// Verificar se o Price ID está configurado
if ($price_id === 'price_xxxxxx' || empty($price_id)) {
    die("Erro: ID de Preço do Stripe não configurado no .env. Por favor, crie o produto no Stripe Dashboard e atualize o .env.");
}

// Criar Sessão de Checkout via cURL (Sem SDK)
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'https://api.stripe.com/v1/checkout/sessions');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_USERPWD, STRIPE_SECRET_KEY . ':');

// FIX A5: Ativação obrigatória de SSL para evitar man-in-the-middle
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);

$post_data = [
    'success_url' => $success_url,
    'cancel_url' => $cancel_url,
    'mode' => $mode,
    'customer_email' => $email,
    'line_items[0][price]' => $price_id,
    'line_items[0][quantity]' => 1,
    'metadata[usuario_id]' => $usuario_id,
    'metadata[tipo]' => $tipo
];

curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post_data));

$result = curl_exec($ch);
if (curl_errno($ch)) {
    error_log('Stripe checkout cURL error: ' . curl_error($ch));
    die('Ocorreu um erro de comunicação com o gateway de pagamento. Tente novamente.');
}
curl_close($ch);

$session = json_decode($result, true);

if (isset($session['url'])) {
    header("Location: " . $session['url']);
} else {
    // FIX A6: Não expor payloads do gateway ao usuário (escrever em logs)
    error_log('Stripe session creation failed: ' . json_encode($session));
    die('Erro ao processar pagamento. Contate o suporte técnico.');
}

