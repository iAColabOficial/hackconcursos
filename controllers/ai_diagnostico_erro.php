<?php
/**
 * Controller: Diagnóstico de Erro por IA
 * SEGURANÇA: Requer autenticação + restrição de propriedade da tarefa.
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/TokenManager.php';
exigirLogin('../login.php'); // FIX C3: autenticação obrigatória

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'msg' => 'Método inválido']);
    exit;
}

$uid       = (int)$_SESSION['usuario_id'];

// 1. Verificar tokens/plano via TokenManager
$check = TokenManager::verificar($uid, COST_IA_SCANNER);
if (!$check['pode']) {
    echo json_encode(['ok' => false, 'msg' => $check['msg'], 'paywall' => true]);
    exit;
}

$input     = json_decode(file_get_contents('php://input'), true);
$tarefaId  = (int)($input['tarefa_id'] ?? 0);
$textoErro = trim($input['texto_erro'] ?? '');
$variacao  = in_array($input['variacao'] ?? '', ['tecnica', 'emocional']) ? $input['variacao'] : 'tecnica';

if (!$tarefaId || empty($textoErro)) {
    echo json_encode(['ok' => false, 'msg' => 'Dados incompletos']);
    exit;
}

$db = getDB();

try {
    // FIX C3: query restrita ao usuário logado (elimina IDOR)
    $st = $db->prepare("
        SELECT t.titulo, d.nome as disciplina, tp.nome as topico, e.banca
        FROM tarefas_estudo t
        JOIN disciplinas d ON d.id = t.disciplina_id
        JOIN planos_estudo p ON p.id = t.plano_id
        JOIN cargos c ON c.id = p.cargo_id
        JOIN editais e ON e.id = c.edital_id
        LEFT JOIN topicos_edital tp ON tp.id = t.topico_id
        WHERE t.id = ? AND p.usuario_id = ?
    ");
    $st->execute([$tarefaId, $uid]);
    $ctx = $st->fetch();

    if (!$ctx) {
        echo json_encode(['ok' => false, 'msg' => 'Tarefa não encontrada.']);
        exit;
    }

    // FIX A2: XSS — escapar dados do banco antes de interpolar em HTML
    $banca      = htmlspecialchars($ctx['banca'] ?? 'sua banca', ENT_QUOTES, 'UTF-8');
    $disciplina = htmlspecialchars($ctx['disciplina'] ?? '', ENT_QUOTES, 'UTF-8');
    $topico     = htmlspecialchars($ctx['topico'] ?? '', ENT_QUOTES, 'UTF-8');

    if ($variacao === 'tecnica') {
        $analise = "<div class='diag-tecnico'>
                    <h6 class='text-neon mb-2' style='color:var(--neon-green);'>1. IDENTIFICAÇÃO DO GAP</h6>
                    <p class='small'>Falha na distinção de competência absoluta vs relativa. Você está aplicando a regra geral em uma exceção de banca.</p>
                    <h6 class='text-neon mb-2' style='color:var(--neon-green);'>2. MECANISMO DO ERRO</h6>
                    <p class='small'>O relato indica 'Erro de Sobrecarga'. Você memorizou o conceito, mas não o gatilho de aplicação. A banca {$banca} usou o termo 'pode' para invalidar sua premissa de 'deve'.</p>
                    <h6 class='text-neon mb-2' style='color:var(--neon-green);'>3. CONTRAMEDIDA TÁTICA</h6>
                    <p class='small'>- Isolar o artigo 37 e criar 3 variações de questões de negação.<br>- Revisar apenas os verbos modais da questão anterior.</p>
                    </div>";
    } else {
        $analise = "<div class='diag-emocional'>
                    <h6 class='text-warning mb-2' style='color:var(--warning);'>1. ONDE VOCÊ SE SABOTOU</h6>
                    <p class='small'>Você foi preguiçoso na leitura. Ignorou a vírgula que mudava todo o sentido da questão. Estudar assim é jogar seu tempo no lixo.</p>
                    <h6 class='text-warning mb-2' style='color:var(--warning);'>2. A ARMADILHA QUE TE PEGOU</h6>
                    <p class='small'>A {$banca} adora candidatos que estudam por 'palavra-chave' decorada. Eles te deram o doce e você mordeu a isca. Você agiu como um amador, não como um aprovado.</p>
                    <h6 class='text-warning mb-2' style='color:var(--warning);'>3. PLANO DE GUERRA</h6>
                    <p class='small'>- Refazer essa missão AGORA com 100% de presença.<br>- Escrever à mão o porquê de cada alternativa estar errada. Sem atalhos.</p>
                    </div>";
    }

    // 2. Debitar token
    TokenManager::consumirOuFalhar($uid, COST_IA_SCANNER, 'Diagnóstico de Erro IA');

    echo json_encode(['ok' => true, 'analise' => $analise]);

} catch (Exception $e) {
    error_log('ai_diagnostico_erro: ' . $e->getMessage());
    echo json_encode(['ok' => false, 'msg' => 'Erro ao processar diagnóstico.']);
}
