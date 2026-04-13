<?php
// SCRIPT DE EMERGÊNCIA - RESET SENHA HOSTINGER
require_once __DIR__ . '/config/config.php';

echo "<h2>🔧 Reset de Senha - Hostinger Production</h2>";

try {
    // getDB() vai usar os dados do seu .env da Hostinger
    $db = getDB();
    
    $novaSenha = password_hash('Admin@123', PASSWORD_BCRYPT);
    $emailAdmin = 'admin@hackconcursos.com.br';

    $st = $db->prepare("UPDATE usuarios SET senha = ? WHERE email = ?");
    $st->execute([$novaSenha, $emailAdmin]);

    if ($st->rowCount() > 0) {
        echo "<p style='color:green;'>✅ SUCESSO! A senha do admin na Hostinger foi alterada para: <b>Admin@123</b></p>";
        echo "<p>Agora você já pode fazer login no seu site real.</p>";
    } else {
        echo "<p style='color:orange;'>⚠️ O usuário não foi encontrado ou a senha já era essa.</p>";
    }

} catch (Exception $e) {
    echo "<p style='color:red;'>❌ ERRO de conexão ou banco: " . $e->getMessage() . "</p>";
}

echo "<hr><p><b>IMPORTANTE:</b> Delete este arquivo (reset_hostinger.php) via FTP ou Gerenciador de Arquivos após usar por segurança.</p>";
