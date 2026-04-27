<?php
require_once __DIR__ . '/../config/config.php';
session_start();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'msg' => 'Método inválido']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$tarefaId = (int)($input['id'] ?? 0);
$questoesTotal = (int)($input['questoes_total'] ?? 0);
$questoesAcerto = (int)($input['questoes_acerto'] ?? 0);
$tempoGasto = (int)($input['tempo_gasto'] ?? 0);

if (!$tarefaId) {
    echo json_encode(['ok' => false, 'msg' => 'ID da tarefa obrigatório']);
    exit;
}

try {
    $db = getDB();
    
    // 1. Atualizar a tarefa com os dados de performance
    $stmt = $db->prepare("
        UPDATE tarefas_estudo 
        SET concluida = 1, 
            concluida_em = NOW(),
            questoes_total = ?, 
            questoes_acerto = ?, 
            tempo_gasto_minutos = ?
        WHERE id = ? AND plano_id IN (SELECT id FROM planos_estudo WHERE usuario_id = ?)
    ");
    
    $stmt->execute([
        $questoesTotal, 
        $questoesAcerto, 
        $tempoGasto, 
        $tarefaId, 
        $_SESSION['usuario_id']
    ]);

    // 2. Atualizar o total de horas no perfil do usuário
    $horasIncremento = $tempoGasto / 60;
    $stmtUpd = $db->prepare("UPDATE perfis_usuario SET total_horas = total_horas + ? WHERE usuario_id = ?");
    $stmtUpd->execute([$horasIncremento, $_SESSION['usuario_id']]);

    // 3. PROCESSAMENTO REATIVO (Lógica Pura)
    require_once __DIR__ . '/../classes/StudyPlanner.php';
    $planner = new StudyPlanner();
    $resultado = $planner->processarDesempenhoMissao($tarefaId);

    echo json_encode([
        'ok' => true, 
        'msg' => $resultado['msg'],
        'frequencia' => $resultado['frequencia'] ?? 0,
        'sugerir_ia' => $resultado['sugerir_ia'],
        'status_missao' => $resultado['status']
    ]);
} catch (Exception $e) {
    echo json_encode(['ok' => false, 'msg' => 'Erro: ' . $e->getMessage()]);
}
