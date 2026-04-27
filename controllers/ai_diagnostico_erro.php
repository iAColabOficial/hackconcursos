<?php
require_once __DIR__ . '/../config/config.php';
session_start();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'msg' => 'Método inválido']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$tarefaId = (int)($input['tarefa_id'] ?? 0);
$textoErro = $input['texto_erro'] ?? '';
$variacao  = $input['variacao'] ?? 'tecnica';

if (!$tarefaId || empty($textoErro)) {
    echo json_encode(['ok' => false, 'msg' => 'Dados incompletos']);
    exit;
}

$db = getDB();
$st = $db->prepare("
    SELECT t.titulo, d.nome as disciplina, tp.nome as topico, e.banca
    FROM tarefas_estudo t
    JOIN disciplinas d ON d.id = t.disciplina_id
    JOIN planos_estudo p ON p.id = t.plano_id
    JOIN cargos c ON c.id = p.cargo_id
    JOIN editais e ON e.id = c.edital_id
    LEFT JOIN topicos_edital tp ON tp.id = t.topico_id
    WHERE t.id = ?
");
$st->execute([$tarefaId]);
$ctx = $st->fetch();

if (!$variacao || $variacao === 'tecnica') {
    $analise = "<div class='diag-tecnico'>
                <h6 class='text-neon mb-2' style='color:var(--neon-green);'>1. IDENTIFICAÇÃO DO GAP</h6>
                <p class='small'>Falha na distinção de competência absoluta vs relativa. Você está aplicando a regra geral em uma exceção de banca.</p>
                <h6 class='text-neon mb-2' style='color:var(--neon-green);'>2. MECANISMO DO ERRO</h6>
                <p class='small'>O relato indica 'Erro de Sobrecarga'. Você memorizou o conceito, mas não o gatilho de aplicação. A banca {$ctx['banca']} usou o termo 'pode' para invalidar sua premissa de 'deve'.</p>
                <h6 class='text-neon mb-2' style='color:var(--neon-green);'>3. CONTRAMEDIDA TÁTICA</h6>
                <p class='small'>- Isolar o artigo 37 e criar 3 variações de questões de negação.<br>- Revisar apenas os verbos modais da questão anterior.</p>
                </div>";
} else {
    $analise = "<div class='diag-emocional'>
                <h6 class='text-warning mb-2' style='color:var(--warning);'>1. ONDE VOCÊ SE SABOTOU</h6>
                <p class='small'>Você foi preguiçoso na leitura. Ignorou a vírgula que mudava todo o sentido da questão. Estudar assim é jogar seu tempo no lixo.</p>
                <h6 class='text-warning mb-2' style='color:var(--warning);'>2. A ARMADILHA QUE TE PEGOU</h6>
                <p class='small'>A {$ctx['banca']} adora candidatos que estudam por 'palavra-chave' decorada. Eles te deram o doce e você mordeu a isca. Você agiu como um amador, não como um aprovado.</p>
                <h6 class='text-warning mb-2' style='color:var(--warning);'>3. PLANO DE GUERRA</h6>
                <p class='small'>- Refazer essa missão AGORA com 100% de presença.<br>- Escrever à mão o porquê de cada alternativa estar errada. Sem atalhos.</p>
                </div>";
}

echo json_encode(['ok' => true, 'analise' => $analise]);
} catch (Exception $e) {
    echo json_encode(['ok' => false, 'msg' => 'Erro na IA: ' . $e->getMessage()]);
}
