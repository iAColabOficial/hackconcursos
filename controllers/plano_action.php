<?php
require_once __DIR__ . '/../config/config.php';
exigirLogin();
header('Content-Type: application/json');

$uid = (int)$_SESSION['usuario_id'];
$data = json_decode(file_get_contents('php://input'), true);
$id = (int)($data['id'] ?? 0);

if (!$id) {
    echo json_encode(['ok' => false, 'msg' => 'ID do plano não informado.']); exit;
}

try {
    $db = getDB();
    
    // Verificar se o plano pertence ao usuário
    $st = $db->prepare("SELECT id FROM planos_estudo WHERE id = ? AND usuario_id = ?");
    $st->execute([$id, $uid]);
    if (!$st->fetch()) {
        echo json_encode(['ok' => false, 'msg' => 'Plano não encontrado ou sem permissão.']); exit;
    }

    // Trocar plano ativo: Desativa todos do usuário e ativa o selecionado
    $db->beginTransaction();
    $db->prepare("UPDATE planos_estudo SET ativo = 0 WHERE usuario_id = ?")->execute([$uid]);
    $db->prepare("UPDATE planos_estudo SET ativo = 1 WHERE id = ?")->execute([$id]);
    $db->commit();

    echo json_encode(['ok' => true]);
} catch (Exception $e) {
    if (isset($db)) $db->rollBack();
    echo json_encode(['ok' => false, 'msg' => 'Erro ao trocar plano: ' . $e->getMessage()]);
}
