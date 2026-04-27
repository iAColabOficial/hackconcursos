<?php
require_once 'config/config.php';
$db = getDB();
echo "--- ÚLTIMOS 10 REGISTROS EM QUESTOES ---\n";
try {
    $res = $db->query("SELECT id, simulado_id, disciplina_id, LEFT(enunciado, 30) as resumo FROM questoes ORDER BY id DESC LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
    if(empty($res)) {
        echo "Tabela de questões está VAZIA!\n";
    } else {
        foreach($res as $r) {
            echo "ID: {$r['id']} | Simulado: {$r['simulado_id']} | Disciplina: {$r['disciplina_id']} | Snippet: {$r['resumo']}\n";
        }
    }
} catch(Exception $e) { echo "Erro: " . $e->getMessage() . "\n"; }

echo "\n--- ÚLTIMOS 3 SIMULADOS ---\n";
try {
    $res = $db->query("SELECT id, titulo, total_questoes FROM simulados ORDER BY id DESC LIMIT 3")->fetchAll(PDO::FETCH_ASSOC);
    foreach($res as $r) {
        echo "ID: {$r['id']} | Título: {$r['titulo']} | Contagem no Banco: {$r['total_questoes']}\n";
    }
} catch(Exception $e) { echo "Erro: " . $e->getMessage() . "\n"; }
?>
