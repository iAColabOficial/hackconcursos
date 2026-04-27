<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/EngineAdaptacao.php';
exigirLogin('../login.php');

$db = getDB();
$usuario_id = $_SESSION['usuario_id'];

// 1. Buscar Perfil e Edital Selecionado
$stmt = $db->prepare("SELECT biblioteca_edital_id, horas_dia FROM perfis_usuario WHERE usuario_id = ?");
$stmt->execute([$usuario_id]);
$perfil = $stmt->fetch();

if (!$perfil || !$perfil['biblioteca_edital_id']) {
    flashMsg('error', 'Por favor, selecione um edital na biblioteca primeiro.');
    redirect('../aluno/biblioteca.php');
}

$targetId = $perfil['biblioteca_edital_id'];
$targetType = 'biblioteca';

try {
    // Instanciar o Motor de Adaptação (Camada 2 - PHP)
    // Ele chamará internamente a Camada 1 (Python - Base Estratégica)
    $engine = new EngineAdaptacao($db);
    $resultado = $engine->gerarPlanoMissions($usuario_id, $targetId, $targetType);

    if (isset($resultado['error'])) {
        throw new Exception($resultado['error']);
    }

    flashMsg('success', '🚀 Plano estratégico gerado com ' . $resultado['total_missoes'] . ' missões personalizadas!');
    redirect('../aluno/dashboard.php');

} catch (Exception $e) {
    flashMsg('error', 'Erro ao gerar motor estratégico: ' . $e->getMessage());
    redirect('../aluno/biblioteca.php');
}
