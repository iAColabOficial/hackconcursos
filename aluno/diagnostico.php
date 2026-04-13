<?php
require_once __DIR__ . '/../config/config.php';
exigirLogin('../public/login.php');

$db  = getDB();
$uid = (int)$_SESSION['usuario_id'];

// Verificar se já tem edital e cargo
$check = $db->prepare("
    SELECT c.id, e.status_processamento 
    FROM cargos c 
    JOIN editais e ON e.id = c.edital_id 
    WHERE e.usuario_id = ? AND c.selecionado = 1 
    LIMIT 1
");
$check->execute([$uid]);
$ativo = $check->fetch();

if (!$ativo || $ativo['status_processamento'] !== 'concluido') {
    // Se não tem nada ativo ou o edital ainda está processando, manda pro upload
    redirect('upload_edital.php');
}

// Se já tem, redireciona para a etapa 3 do wizard (que é o diagnóstico de fato)
// Mas para ser um arquivo independente que o usuário pode acessar para "ajustar" o plano...
// Vamos carregar os dados atuais e permitir editar.

$diagQ = $db->prepare("SELECT * FROM diagnosticos WHERE usuario_id = ? AND cargo_id = ? LIMIT 1");
$diagQ->execute([$uid, $ativo['id']]);
$diag = $diagQ->fetch();

if (!$diag) {
    // Se selecionou cargo mas não fez diagnóstico, manda pro wizard na etapa 3
    redirect('upload_edital.php');
}

// Redireciona para o wizard mas com um flag de "editar" se for o caso
// Ou simplesmente levamos ele para a página de upload_edital.php?step=3
redirect('upload_edital.php?step=3');
