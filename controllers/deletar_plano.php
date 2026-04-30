<?php
require_once __DIR__ . '/../config/config.php';
exigirLogin('../login.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirmar_delete'])) {
    $db = getDB();
    $usuario_id = $_SESSION['usuario_id'];

    try {
        $db->beginTransaction();

        // 1. Buscar o plano ativo
        $stmtP = $db->prepare("SELECT id FROM planos_estudo WHERE usuario_id = ? AND ativo = 1");
        $stmtP->execute([$usuario_id]);
        $plano = $stmtP->fetch();

        if ($plano) {
            $plano_id = $plano['id'];

            // 2. Deletar todas as tarefas vinculadas
            $db->prepare("DELETE FROM tarefas_estudo WHERE plano_id = ?")->execute([$plano_id]);

            // 3. Desativar o plano (ou deletar)
            $db->prepare("UPDATE planos_estudo SET ativo = 0 WHERE id = ?")->execute([$plano_id]);
        }

        // 4. Limpar o edital selecionado no perfil
        $db->prepare("UPDATE perfis_usuario SET biblioteca_edital_id = NULL WHERE usuario_id = ?")->execute([$usuario_id]);

        $db->commit();
        flashMsg('success', 'Plano deletado com sucesso. Seu sistema foi resetado.');

    } catch (Exception $e) {
        $db->rollBack();
        flashMsg('danger', 'Erro ao deletar plano: ' . $e->getMessage());
    }

    redirect('../aluno/plano_estudos.php');
} else {
    redirect('../aluno/plano_estudos.php');
}
