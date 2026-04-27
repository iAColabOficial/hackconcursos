<?php
require_once __DIR__ . '/../config/config.php';
exigirLogin('../login.php');

$db = getDB();
$usuario_id = $_SESSION['usuario_id'];

// 1. Buscar Perfil e Edital Selecionado
$perfil = $db->prepare("SELECT * FROM perfis_usuario WHERE usuario_id = ?");
$perfil->execute([$usuario_id]);
$dados_perfil = $perfil->fetch();

if (!$dados_perfil || !$dados_perfil['biblioteca_edital_id']) {
    redirect('biblioteca.php');
}

$biblioteca_id = $dados_perfil['biblioteca_edital_id'];
$horas_dia = $dados_perfil['horas_dia'] ?: 3;

// 2. Criar Plano de Estudo se não existir
$stmtPlano = $db->prepare("SELECT id FROM planos_estudo WHERE usuario_id = ? AND ativo = 1 LIMIT 1");
$stmtPlano->execute([$usuario_id]);
$plano = $stmtPlano->fetch();

if (!$plano) {
    $insPlano = $db->prepare("INSERT INTO planos_estudo (usuario_id, nome, horas_diarias, ativo) VALUES (?, ?, ?, 1)");
    $insPlano->execute([$usuario_id, "Plano Estratégico - " . date('d/m/Y'), $horas_dia]);
    $plano_id = $db->lastInsertId();
} else {
    $plano_id = $plano['id'];
}

// 3. Buscar Disciplinas da Biblioteca
$disciplinas = $db->prepare("SELECT * FROM biblioteca_disciplinas WHERE biblioteca_edital_id = ? ORDER BY peso_padrao DESC, importancia_ia DESC");
$disciplinas->execute([$biblioteca_id]);
$lista = $disciplinas->fetchAll();

// 4. Gerar Tarefas para os próximos 7 dias (Ciclo Básico)
// Lógica Simplificada: 1 tarefa por hora disponível.
$data_atual = new DateTime();

$db->beginTransaction();
try {
    // Limpar tarefas futuras para regerar
    $db->prepare("DELETE FROM tarefas_estudo WHERE plano_id = ? AND concluida = 0 AND data_prevista >= ?")->execute([$plano_id, $data_atual->format('Y-m-d')]);

    $disciplina_index = 0;
    for ($dia = 0; $dia < 7; $dia++) {
        $data_tarefa = clone $data_atual;
        $data_tarefa->modify("+$dia days");
        
        for ($hora = 0; $hora < floor($horas_dia); $hora++) {
            $d = $lista[$disciplina_index % count($lista)];
            
            // Inserir Tarefa
            // Nota: tarefas_estudo exige disciplina_id (tabela disciplinas). 
            // Para o MVP, vamos ignorar a FK ou garantir que as disciplinas existam lá.
            // Aqui, usaremos um valor nulo ou adaptaremos.
            
            $stmtTask = $db->prepare("
                INSERT INTO tarefas_estudo (plano_id, disciplina_id, titulo, data_prevista, duracao_minutos, tipo) 
                VALUES (?, NULL, ?, ?, 60, 'estudo')
            ");
            // Nota: 0 como disciplina_id temporário. Idealmente mapearíamos.
            $stmtTask->execute([$plano_id, "Estudar: " . $d['nome'], $data_tarefa->format('Y-m-d')]);
            
            $disciplina_index++;
        }
    }
    $db->commit();
    flashMsg('success', 'Ciclo de estudos gerado com sucesso!');
    redirect('../aluno/dashboard.php');
} catch (Exception $e) {
    $db->rollBack();
    die("Erro ao gerar plano: " . $e->getMessage());
}
