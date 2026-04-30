<?php
// Controller AJAX para excluir simulado
require_once __DIR__ . '/../config/config.php';
exigirLogin();
header('Content-Type: application/json');

$body = json_decode(file_get_contents('php://input'), true);
$id   = (int)($body['id'] ?? 0);
$uid  = (int)$_SESSION['usuario_id'];

if (!$id) { echo json_encode(['ok'=>false,'msg'=>'ID inválido']); exit; }

try {
    $db = getDB();
    
    // 1. Verificar se o simulado pertence ao usuário
    $st = $db->prepare("SELECT id FROM simulados WHERE id = ? AND usuario_id = ?");
    $st->execute([$id, $uid]);
    if (!$st->fetch()) {
        echo json_encode(['ok'=>false, 'msg'=>'Simulado não encontrado ou sem permissão.']);
        exit;
    }

    // BLOQUEIO PARA PLANO GRATUITO
    if (($_SESSION['plano'] ?? 'free') === 'free') {
        echo json_encode(['ok'=>false, 'msg'=>'No plano gratuito, os simulados não podem ser deletados para garantir o histórico de evolução.']);
        exit;
    }

    // Iniciar transação para deletar tudo relacionado
    $db->beginTransaction();
    $db->exec("SET FOREIGN_KEY_CHECKS = 0");

    // 1. Apagar respostas dos usuários vinculadas
    $db->prepare("DELETE FROM respostas_usuario WHERE simulado_id = ?")->execute([$id]);

    // 2. Apagar as questões geradas para este simulado
    $db->prepare("DELETE FROM questoes WHERE simulado_id = ?")->execute([$id]);

    // 3. Apagar o cabeçalho do simulado
    $db->prepare("DELETE FROM simulados WHERE id = ?")->execute([$id]);

    $db->exec("SET FOREIGN_KEY_CHECKS = 1");
    $db->commit();
    echo json_encode(['ok'=>true]);

} catch (PDOException $e) {
    if (isset($db)) {
        $db->exec("SET FOREIGN_KEY_CHECKS = 1");
        $db->rollBack();
    }
    echo json_encode(['ok'=>false,'msg'=>'Erro Banco (MySQL): ' . $e->getMessage()]);
}
