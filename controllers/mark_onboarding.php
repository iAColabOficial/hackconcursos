<?php
require_once __DIR__ . '/../config/config.php';
iniciarSessao();

if (usuarioLogado() && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $db = getDB();
    $uid = $_SESSION['usuario_id'];
    
    $upd = $db->prepare("UPDATE perfis_usuario SET onboarding_visto = 1 WHERE usuario_id = ?");
    $upd->execute([$uid]);
    
    echo json_encode(['status' => 'success']);
} else {
    http_response_code(403);
    echo json_encode(['error' => 'Acesso negado']);
}
