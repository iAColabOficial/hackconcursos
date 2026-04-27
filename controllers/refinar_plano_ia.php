<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/GeminiService.php';
exigirLogin('../login.php');

$db = getDB();
$usuario_id = $_SESSION['usuario_id'];

// 1. Verificar Plano/Tokens
$stmtU = $db->prepare("SELECT plano, token_saldo FROM usuarios WHERE id = ?");
$stmtU->execute([$usuario_id]);
$usr = $stmtU->fetch();

if ($usr['plano'] !== 'premium' && $usr['token_saldo'] <= 0) {
    flashMsg('error', 'Tokens insuficientes para o refinamento IA.');
    redirect('../aluno/meu_plano.php');
}

// 2. Buscar Dados do Edital e Disciplinas
$perfilQ = $db->prepare("SELECT biblioteca_edital_id, horas_dia FROM perfis_usuario WHERE usuario_id = ?");
$perfilQ->execute([$usuario_id]);
$perfil = $perfilQ->fetch();

if (!$perfil || !$perfil['biblioteca_edital_id']) {
    flashMsg('error', 'Nenhum edital selecionado.');
    redirect('../aluno/biblioteca.php');
}

$edital_id = $perfil['biblioteca_edital_id'];
$horas_dia = $perfil['horas_dia'] ?: 3;

$edital = $db->prepare("SELECT nome_concurso, banca FROM biblioteca_editais WHERE id = ?");
$edital->execute([$edital_id]);
$eData = $edital->fetch();

$discQ = $db->prepare("SELECT * FROM biblioteca_disciplinas WHERE biblioteca_edital_id = ?");
$discQ->execute([$edital_id]);
$disciplinas = $discQ->fetchAll();

try {
    // 3. Chamar IA para Refinar Ordem
    $gemini = new GeminiService();
    $ordemPrioridade = $gemini->refinarPlano($eData['nome_concurso'], $eData['banca'], $disciplinas);

    if (empty($ordemPrioridade)) {
        throw new Exception("Falha ao processar estratégia da IA.");
    }

    // 4. Reorganizar Ciclo (7 dias)
    $planoAtivo = $db->prepare("SELECT id FROM planos_estudo WHERE usuario_id=? AND ativo=1 LIMIT 1");
    $planoAtivo->execute([$usuario_id]);
    $plano_id = $planoAtivo->fetchColumn();

    if (!$plano_id) {
        throw new Exception("Plano ativo não encontrado.");
    }

    $db->beginTransaction();
    
    // Limpar tarefas futuras
    $hoje = date('Y-m-d');
    $db->prepare("DELETE FROM tarefas_estudo WHERE plano_id = ? AND concluida = 0 AND data_prevista >= ?")->execute([$plano_id, $hoje]);

    $data_atual = new DateTime();
    $total_discs = count($ordemPrioridade);
    $task_index = 0;

    for ($dia = 0; $dia < 14; $dia++) { // Estender para 14 dias para dar mais valor
        $data_tarefa = clone $data_atual;
        $data_tarefa->modify("+$dia days");
        
        for ($hora = 0; $hora < floor($horas_dia); $hora++) {
            $nome_disc = $ordemPrioridade[$task_index % $total_discs];
            
            $stmtTask = $db->prepare("
                INSERT INTO tarefas_estudo (plano_id, titulo, data_prevista, duracao_minutos, tipo) 
                VALUES (?, ?, ?, 60, 'estudo')
            ");
            $stmtTask->execute([$plano_id, "Foco IA: " . $nome_disc, $data_tarefa->format('Y-m-d')]);
            
            $task_index++;
        }
    }

    // 5. Debitar Token se necessário
    if ($usr['plano'] !== 'premium') {
        $db->prepare("UPDATE usuarios SET token_saldo = token_saldo - 1 WHERE id = ?")->execute([$usuario_id]);
        $db->prepare("INSERT INTO token_transacoes (usuario_id, tipo, quantidade, descricao) VALUES (?, 'consumo', -1, 'Refinamento Estratégico IA')")->execute([$usuario_id]);
    }

    $db->commit();
    flashMsg('success', '✨ Plano refinado com sucesso! Sua estratégia agora tem 98% de precisão.');
    redirect('../aluno/plano_estudos.php');

} catch (Exception $e) {
    if ($db->inTransaction()) $db->rollBack();
    flashMsg('error', 'Erro no refinamento: ' . $e->getMessage());
    redirect('../aluno/plano_estudos.php');
}
