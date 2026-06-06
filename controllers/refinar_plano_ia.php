<?php
/**
 * Controller: Refinar Plano com IA
 * FIX C7: Substituído shell_exec (Python) por chamada direta ao GeminiService.
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/GeminiService.php';
require_once __DIR__ . '/../classes/TokenManager.php';
exigirLogin('../login.php');

$db         = getDB();
$usuario_id = (int)$_SESSION['usuario_id']; // FIX M6: cast explícito para int

// 1. Verificar Tokens/Plano via TokenManager
$check = TokenManager::verificar($usuario_id, COST_IA_REROUTE);
if (!$check['pode']) {
    flashMsg('error', 'O Refinamento Avançado com IA requer tokens. ' . $check['msg']);
    redirect('../aluno/meu_plano.php');
}

// 2. Buscar Dados para a IA
$perfilQ = $db->prepare("SELECT biblioteca_edital_id FROM perfis_usuario WHERE usuario_id = ?");
$perfilQ->execute([$usuario_id]);
$perfil = $perfilQ->fetch();

// FIX A5/M5: Verificar que o perfil existe antes de usar
if (!$perfil || !$perfil['biblioteca_edital_id']) {
    flashMsg('error', 'Configure seu concurso antes de usar o Refinamento IA.');
    redirect('../aluno/meus_concursos.php');
}

$edital = $db->prepare("SELECT banca FROM biblioteca_editais WHERE id = ?");
$edital->execute([$perfil['biblioteca_edital_id']]);
$banca = $edital->fetchColumn() ?: 'Geral';

// Buscar Performance do Usuário (para a IA analisar erros)
$perfQ = $db->prepare("
    SELECT d.nome as disciplina, SUM(r.correta) as acertos, COUNT(r.id) - SUM(r.correta) as erros
    FROM respostas_usuario r
    JOIN questoes q ON r.questao_id = q.id
    JOIN disciplinas d ON q.disciplina_id = d.id
    WHERE r.usuario_id = ?
    GROUP BY d.id
    ORDER BY erros DESC
    LIMIT 10
");
$perfQ->execute([$usuario_id]);
$performance = $perfQ->fetchAll(PDO::FETCH_ASSOC);

// Buscar disciplinas com mais erros para o contexto
$disciplinasMaisErradas = implode(', ', array_column(array_filter($performance, fn($p) => $p['erros'] > 0), 'disciplina'));
if (empty($disciplinasMaisErradas)) $disciplinasMaisErradas = 'nenhuma registrada ainda';

try {
    // 3. FIX C7: Chamar GeminiService diretamente (elimina shell_exec + dependência Python)
    $gemini = new GeminiService();

    $prompt = "Você é um estrategista de concursos públicos especialista na banca {$banca}. 
Analise a performance do candidato abaixo e forneça um plano de refinamento de estudos em JSON.

DESEMPENHO DO CANDIDATO:
" . json_encode($performance, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "

Responda SOMENTE com JSON no formato:
{
  \"intervencao_sugerida\": \"texto curto com a principal ação a tomar\",
  \"disciplinas_prioridade\": [\"lista das 3 disciplinas mais críticas\"],
  \"dicas_banca\": \"padrão da banca {$banca} que o candidato deve dominar\",
  \"meta_semanal\": \"meta concreta para esta semana\"
}";

    $ai_resultado = $gemini->gerarTextoSimples($prompt);

    // Tentar parsear como JSON, com fallback
    $ai_boost = json_decode($ai_resultado, true);
    if (!$ai_boost || !isset($ai_boost['intervencao_sugerida'])) {
        // Fallback inteligente baseado nos dados reais
        $ai_boost = [
            'intervencao_sugerida' => "Foque nos erros em {$disciplinasMaisErradas} nos próximos 7 dias.",
            'disciplinas_prioridade' => array_column(array_slice($performance, 0, 3), 'disciplina'),
            'dicas_banca' => "A banca {$banca} prioriza questões de interpretação. Leia cada enunciado duas vezes.",
            'meta_semanal' => 'Acertar 70% das questões das disciplinas com mais erros.'
        ];
    }

    // 4. Registrar Evento de Otimização
    $stmtEv = $db->prepare("INSERT INTO eventos_usuario (usuario_id, evento, metadata) VALUES (?, 'ia_refinement', ?)");
    $stmtEv->execute([$usuario_id, json_encode($ai_boost)]);

    // 5. Debitar Token (só depois de ter resultado — FIX A3)
    TokenManager::consumirOuFalhar($usuario_id, COST_IA_REROUTE, 'Refinamento de Plano IA');

    $msgIntervencao = $ai_boost['intervencao_sugerida'];
    flashMsg('success', '✨ IA ativada! Sua estratégia foi otimizada para a banca ' . htmlspecialchars($banca) . '. ' . htmlspecialchars($msgIntervencao));
    redirect('../aluno/plano_estudos.php');

} catch (Exception $e) {
    error_log('refinar_plano_ia: ' . $e->getMessage());
    flashMsg('error', 'Erro no motor de IA. Tente novamente em alguns instantes.');
    redirect('../aluno/plano_estudos.php');
}
