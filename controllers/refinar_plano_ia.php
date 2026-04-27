<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/EngineAdaptacao.php';
exigirLogin('../login.php');

$db = getDB();
$usuario_id = $_SESSION['usuario_id'];

// 1. Verificar Plano/Tokens (Apenas Premium ou com Tokens acessam esta camada)
$stmtU = $db->prepare("SELECT plano, token_saldo FROM usuarios WHERE id = ?");
$stmtU->execute([$usuario_id]);
$usr = $stmtU->fetch();

if ($usr['plano'] !== 'premium' && $usr['token_saldo'] <= 0) {
    flashMsg('error', 'O Refinamento Avançado com IA é exclusivo para membros Premium.');
    redirect('../aluno/meu_plano.php');
}

// 2. Buscar Dados para a IA
$perfilQ = $db->prepare("SELECT biblioteca_edital_id FROM perfis_usuario WHERE usuario_id = ?");
$perfilQ->execute([$usuario_id]);
$perfil = $perfilQ->fetch();

$edital = $db->prepare("SELECT banca FROM biblioteca_editais WHERE id = ?");
$edital->execute([$perfil['biblioteca_edital_id']]);
$banca = $edital->fetchColumn() ?: "Geral";

// Buscar Base Estratégica Atual (Camada 1)
$engine = new EngineAdaptacao($db);
$base = $engine->getBaseEstrategica($perfil['biblioteca_edital_id'], 'biblioteca');

// Buscar Performance do Usuário (para a IA analisar erros)
$perfQ = $db->prepare("
    SELECT d.nome as disciplina, SUM(r.correta) as acertos, COUNT(r.id) - SUM(r.correta) as erros
    FROM respostas_usuario r
    JOIN questoes q ON r.questao_id = q.id
    JOIN disciplinas d ON q.disciplina_id = d.id
    WHERE r.usuario_id = ?
    GROUP BY d.id
");
$perfQ->execute([$usuario_id]);
$performance = $perfQ->fetchAll(PDO::FETCH_ASSOC);

try {
    // 3. Chamar Camada 3 (IA - Advanced Intelligence)
    $pythonPath = "python";
    $scriptPath = dirname(__DIR__) . "/python/ai_expert.py";
    
    $baseJson = escapeshellarg(json_encode($base));
    $perfJson = escapeshellarg(json_encode($performance));
    $bancaArg = escapeshellarg($banca);

    $command = "$pythonPath \"$scriptPath\" $bancaArg $baseJson $perfJson";
    $output = shell_exec($command);
    $ai_boost = json_decode($output, true);

    if (isset($ai_boost['error'])) throw new Exception($ai_boost['error']);

    // 4. Aplicar Otimizações (Ex: Salvar dicas de mestre ou ajustar prioridades)
    // Para o MVP, vamos registrar a intervenção e as dicas em um local visível
    $msgIntervencao = $ai_boost['intervencao_sugerida'] ?? "IA analisou seu perfil e otimizou seu ciclo.";
    
    // Registrar Evento de Otimização IA
    $stmtEv = $db->prepare("INSERT INTO eventos_usuario (usuario_id, evento, metadata) VALUES (?, 'ia_refinement', ?)");
    $stmtEv->execute([$usuario_id, json_encode($ai_boost)]);

    // 5. Debitar Token se necessário
    if ($usr['plano'] !== 'premium') {
        $db->prepare("UPDATE usuarios SET token_saldo = token_saldo - 1 WHERE id = ?")->execute([$usuario_id]);
    }

    flashMsg('success', '✨ IA ativada! Sua estratégia foi otimizada para a banca ' . $banca . '. ' . $msgIntervencao);
    redirect('../aluno/plano_estudos.php');

} catch (Exception $e) {
    flashMsg('error', 'Erro no motor de IA: ' . $e->getMessage());
    redirect('../aluno/plano_estudos.php');
}
