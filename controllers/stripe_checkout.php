<?php
require_once __DIR__ . '/../config/config.php';
exigirLogin('../login.php');

$tipo = $_GET['tipo'] ?? 'assinatura'; // 'assinatura' ou 'tokens'
$usuario_id = $_SESSION['usuario_id'];
$email = $_SESSION['email'];

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
    die('Erro cURL: ' . curl_error($ch));
}
curl_close($ch);

$session = json_decode($result, true);

if (isset($session['url'])) {
    header("Location: " . $session['url']);
} else {
    echo "<h3>Erro ao criar sessão de checkout</h3>";
    echo "<pre>";
    print_r($session);
    echo "</pre>";
}
