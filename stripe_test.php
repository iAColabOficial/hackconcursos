<?php
require_once __DIR__ . '/config/config.php';

echo "<h3>Teste de Configuração Stripe</h3>";
echo "Public Key: " . substr(STRIPE_PUBLIC_KEY, 0, 10) . "..." . "<br>";
echo "Secret Key: " . substr(STRIPE_SECRET_KEY, 0, 10) . "..." . "<br>";

if (empty(STRIPE_SECRET_KEY)) {
    echo "<b style='color:red'>ERRO: Secret Key não configurada no .env</b>";
} else {
    echo "<b style='color:green'>Sucesso: Chaves carregadas.</b>";
}
?>
