<?php
require_once 'config/config.php';
$db = getDB();
echo "--- TABELA: tarefas_estudo ---\n";
$res = $db->query("DESCRIBE tarefas_estudo")->fetchAll(PDO::FETCH_ASSOC);
foreach($res as $r) echo "{$r['Field']} ({$r['Type']})\n";

echo "\n--- TABELA: usuarios ---\n";
$res = $db->query("DESCRIBE usuarios")->fetchAll(PDO::FETCH_ASSOC);
foreach($res as $r) echo "{$r['Field']} ({$r['Type']})\n";

echo "\n--- TABELA: perfis_usuario ---\n";
$res = $db->query("DESCRIBE perfis_usuario")->fetchAll(PDO::FETCH_ASSOC);
foreach($res as $r) echo "{$r['Field']} ({$r['Type']})\n";
?>
