<?php
/**
 * HackConcursos - Processamento de Cadastro Manual de Edital
 */
require_once __DIR__ . '/../config/config.php';
exigirLogin('../login.php');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . APP_URL . '/aluno/upload_edital.php');
    exit;
}

$uid = (int)$_SESSION['usuario_id'];
$nomeConcurso = trim($_POST['nome_concurso'] ?? '');
$dataProva    = !empty($_POST['data_prova']) ? $_POST['data_prova'] : null;
$disciplinasInput = $_POST['disciplinas'] ?? [];

if (empty($nomeConcurso) || empty($disciplinasInput)) {
    flashMsg('danger', 'Preencha o nome do concurso e pelo menos uma matéria.');
    header('Location: ' . APP_URL . '/aluno/upload_edital.php');
    exit;
}

try {
    $db = getDB();
    $db->beginTransaction();

    // 1. Criar Edital
    $stEdit = $db->prepare("INSERT INTO editais (usuario_id, nome_concurso, arquivo_pdf, data_prova, status_processamento) VALUES (?, ?, ?, ?, ?)");
    $stEdit->execute([$uid, $nomeConcurso, 'manual', $dataProva, 'concluido']);
    $editalId = (int)$db->lastInsertId();

    // 2. Criar Cargo Único (Geral)
    $stCargo = $db->prepare("INSERT INTO cargos (edital_id, nome, selecionado) VALUES (?, ?, ?)");
    $stCargo->execute([$editalId, 'Plano de Estudos', 1]);
    $cargoId = (int)$db->lastInsertId();

    // 3. Processar Disciplinas e Tópicos
    foreach ($disciplinasInput as $disc) {
        $nomeMateria = trim($disc['nome'] ?? '');
        $topicosBrutos = $disc['topicos'] ?? '';

        if (empty($nomeMateria)) continue;

        // Inserir Disciplina
        $stDisc = $db->prepare("INSERT INTO disciplinas (cargo_id, nome, peso) VALUES (?, ?, ?)");
        $stDisc->execute([$cargoId, $nomeMateria, 1.0]);
        $discId = (int)$db->lastInsertId();

        // Quebrar tópicos por linha
        $linhas = explode("\n", str_replace("\r", "", $topicosBrutos));
        $stTop = $db->prepare("INSERT INTO topicos_edital (disciplina_id, nome) VALUES (?, ?)");

        foreach ($linhas as $linha) {
            $topico = trim($linha);
            if (!empty($topico)) {
                $stTop->execute([$discId, $topico]);
            }
        }
    }

    $db->commit();

    // Redirecionar para o Diagnóstico
    header('Location: ' . APP_URL . "/aluno/upload_edital.php?step=diagnostico&edital={$editalId}&cargo={$cargoId}");
    exit;

} catch (\Exception $e) {
    if (isset($db)) $db->rollBack();
    flashMsg('danger', 'Erro ao salvar: ' . $e->getMessage());
    header('Location: ' . APP_URL . '/aluno/upload_edital.php');
    exit;
}
