<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/GeminiService.php';
require_once __DIR__ . '/../classes/TokenManager.php';
exigirLogin('../login.php');
header('Content-Type: application/json');

$uid  = (int)$_SESSION['usuario_id'];
$body = json_decode(file_get_contents('php://input'), true);
$pergunta = trim($body['pergunta'] ?? '');
$editalId = (int)($body['edital_id'] ?? 0);

if (empty($pergunta)) {
    echo json_encode(['ok'=>false,'msg'=>'Dados inválidos.']); exit;
}

$db = getDB();

// 1. Verificar saldo via TokenManager centralizado
$check = TokenManager::verificar($uid, COST_IA_TIPS);
if (!$check['pode']) {
    echo json_encode(['ok' => false, 'msg' => $check['msg'], 'paywall' => true]);
    exit;
}
$user_data = ['plano' => $check['plano']]; // compatibilidade com código abaixo

// Buscar resumo do status do aluno via StudyPlanner
require_once __DIR__ . '/../classes/StudyPlanner.php';
$planner = new StudyPlanner();
$contextoAluno = $planner->getResumoStatus($uid);

// Verificar edital
$edital = null;
if ($editalId > 0) {
    // Tentar buscar na biblioteca global primeiro
    $eq = $db->prepare("SELECT id, nome_concurso, banca FROM biblioteca_editais WHERE id=?");
    $eq->execute([$editalId]);
    $edital = $eq->fetch();
    
    if ($edital) {
        // Buscar disciplinas como contexto
        $disc = $db->prepare("SELECT nome FROM biblioteca_disciplinas WHERE biblioteca_edital_id = ?");
        $disc->execute([$editalId]);
        $disciplinas = $disc->fetchAll(PDO::FETCH_COLUMN);
        $edital['conteudo_texto'] = "Matérias deste edital: " . implode(', ', $disciplinas);
    } else {
        // Tentar buscar no upload antigo
        $eq = $db->prepare("SELECT id, conteudo_texto, nome_concurso, banca FROM editais WHERE id=? AND usuario_id=?");
        $eq->execute([$editalId, $uid]);
        $edital = $eq->fetch();
    }
}

// Buscar histórico recente (últimas 6 mensagens)
$historico = [];
$hSql = $editalId > 0 
    ? "SELECT papel, mensagem FROM mensagens_chat WHERE usuario_id=? AND edital_id=? ORDER BY criado_em DESC LIMIT 6"
    : "SELECT papel, mensagem FROM mensagens_chat WHERE usuario_id=? AND edital_id IS NULL ORDER BY criado_em DESC LIMIT 6";

$hq = $db->prepare($hSql);
$hParams = $editalId > 0 ? [$uid, $editalId] : [$uid];
$hq->execute($hParams);
$historico = array_reverse($hq->fetchAll());

// Salvar pergunta
$insSql = "INSERT INTO mensagens_chat (usuario_id, edital_id, papel, mensagem) VALUES (?,?,?,?)";
$insSt = $db->prepare($insSql);
$insSt->execute([$uid, $editalId ?: null, 'user', $pergunta]);

try {
    $gemini   = new GeminiService();
    // Passar o contexto do edital, o resumo do aluno e agora explicitamente NOME e BANCA
    $resposta = $gemini->chatEdital(
        $pergunta, 
        $edital['conteudo_texto'] ?? 'Nenhum edital selecionado no momento.', 
        $historico, 
        $contextoAluno,
        $edital['nome_concurso'] ?? '',
        $edital['banca'] ?? ''
    );

// Salvar resposta
$db->prepare("INSERT INTO mensagens_chat (usuario_id, edital_id, papel, mensagem) VALUES (?,?,?,?)")
   ->execute([$uid, $editalId ?: null, 'model', $resposta]);

// Debitar Token e registrar transação
$resultado = TokenManager::consumir($uid, COST_IA_TIPS, 'Consulta ao Mentor IA');

echo json_encode(['ok'=>true, 'resposta'=>$resposta, 'tokens_restantes' => $resultado['saldo_restante']]);
} catch (\Exception $e) {
    echo json_encode(['ok'=>false, 'msg'=>'Erro ao processar: '.$e->getMessage()]);
}
