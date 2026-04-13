<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/GeminiService.php';
exigirLogin('../login.php');
header('Content-Type: application/json');

$uid  = (int)$_SESSION['usuario_id'];
$body = json_decode(file_get_contents('php://input'), true);
$pergunta = trim($body['pergunta'] ?? '');
$editalId = (int)($body['edital_id'] ?? 0);

if (empty($pergunta) || !$editalId) {
    echo json_encode(['ok'=>false,'msg'=>'Dados inválidos.']); exit;
}

$db = getDB();

// Buscar resumo do status do aluno via StudyPlanner
require_once __DIR__ . '/../classes/StudyPlanner.php';
$planner = new StudyPlanner();
$contextoAluno = $planner->getResumoStatus($uid);

// Verificar edital
$edital = null;
if ($editalId > 0) {
    $eq = $db->prepare("SELECT id, conteudo_texto, nome_concurso FROM editais WHERE id=? AND usuario_id=?");
    $eq->execute([$editalId, $uid]);
    $edital = $eq->fetch();
}

// Buscar histórico recente (últimas 6 mensagens)
$historico = [];
if ($editalId > 0) {
    $hq = $db->prepare("SELECT papel, mensagem FROM mensagens_chat WHERE usuario_id=? AND edital_id=? ORDER BY criado_em DESC LIMIT 6");
    $hq->execute([$uid, $editalId]);
    $historico = array_reverse($hq->fetchAll());

    // Salvar pergunta
    $db->prepare("INSERT INTO mensagens_chat (usuario_id, edital_id, papel, mensagem) VALUES (?,?,?,?)")
       ->execute([$uid, $editalId, 'user', $pergunta]);
}

try {
    $gemini   = new GeminiService();
    // Passar o contexto do edital (se houver) e o contexto do aluno (sempre)
    $resposta = $gemini->chatEdital($pergunta, $edital['conteudo_texto'] ?? 'Nenhum edital selecionado no momento.', $historico, $contextoAluno);

    // Salvar resposta
    if ($editalId > 0) {
        $db->prepare("INSERT INTO mensagens_chat (usuario_id, edital_id, papel, mensagem) VALUES (?,?,?,?)")
           ->execute([$uid, $editalId, 'model', $resposta]);
    }

    echo json_encode(['ok'=>true, 'resposta'=>$resposta]);
} catch (\Exception $e) {
    echo json_encode(['ok'=>false, 'msg'=>'Erro ao processar: '.$e->getMessage()]);
}
