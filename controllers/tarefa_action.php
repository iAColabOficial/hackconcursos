<?php
// Controller AJAX para marcar tarefa como concluída
require_once __DIR__ . '/../config/config.php';
exigirLogin();
header('Content-Type: application/json');

$body = json_decode(file_get_contents('php://input'), true);
$id       = (int)($body['id'] ?? 0);
$concluida = (bool)($body['concluida'] ?? false);
$uid      = (int)$_SESSION['usuario_id'];

if (!$id) { echo json_encode(['ok'=>false,'msg'=>'ID inválido']); exit; }

try {
    $db = getDB();
    // Verificar que a tarefa pertence ao usuário
    $st = $db->prepare("SELECT t.id FROM tarefas_estudo t
                         JOIN planos_estudo p ON p.id = t.plano_id
                         WHERE t.id = ? AND p.usuario_id = ?");
    $st->execute([$id, $uid]);
    if (!$st->fetch()) { echo json_encode(['ok'=>false,'msg'=>'Não autorizado']); exit; }

    $now = $concluida ? date('Y-m-d H:i:s') : null;
    $db->prepare("UPDATE tarefas_estudo SET concluida = ?, concluida_em = ? WHERE id = ?")
       ->execute([(int)$concluida, $now, $id]);

    // Atualizar horas estudadas no perfil (estimativa: duração / 60)
    $durQ = $db->prepare("SELECT duracao_minutos FROM tarefas_estudo WHERE id = ?");
    $durQ->execute([$id]);
    $dur = (float)($durQ->fetchColumn() ?? 0);
    $horas = $dur / 60;

    if ($concluida) {
        $db->prepare("UPDATE perfis_usuario SET total_horas = total_horas + ? WHERE usuario_id = ?")
           ->execute([$horas, $uid]);
    } else {
        // Estornar horas ao desmarcar
        $db->prepare("UPDATE perfis_usuario SET total_horas = GREATEST(0, total_horas - ?) WHERE usuario_id = ?")
           ->execute([$horas, $uid]);
    }

    echo json_encode(['ok'=>true]);
} catch (PDOException $e) {
    echo json_encode(['ok'=>false,'msg'=>'Erro interno']);
}
