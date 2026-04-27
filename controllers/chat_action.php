<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/GeminiService.php';
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

// 1. Validar Economia de Tokens / Plano
$stmtU = $db->prepare("SELECT plano, token_saldo FROM usuarios WHERE id = ?");
$stmtU->execute([$uid]);
$user_data = $stmtU->fetch();

if ($user_data['plano'] !== 'premium') {
    if ($user_data['token_saldo'] <= 0) {
        echo json_encode(['ok' => false, 'msg' => 'Você não possui tokens suficientes. Ative o Modo Guerra ou compre créditos.', 'paywall' => true]);
        exit;
    }
}

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

// Debitar Token se for free
if ($user_data['plano'] !== 'premium') {
    $db->prepare("UPDATE usuarios SET token_saldo = token_saldo - 1 WHERE id = ?")->execute([$uid]);
    
    // Registrar transação de débito
    $db->prepare("INSERT INTO token_transacoes (usuario_id, tipo, quantidade, descricao) VALUES (?, 'consumo', -1, 'Consulta ao Mentor IA')")
       ->execute([$uid]);
}

echo json_encode(['ok'=>true, 'resposta'=>$resposta, 'tokens_restantes' => ($user_data['plano'] === 'premium' ? '∞' : $user_data['token_saldo'] - 1)]);
} catch (\Exception $e) {
    echo json_encode(['ok'=>false, 'msg'=>'Erro ao processar: '.$e->getMessage()]);
}
