<?php
/**
 * HackConcursos - Meus Concursos
 * Listagem e gestão de múltiplos planos de estudo
 */
require_once __DIR__ . '/../config/config.php';
exigirLogin('../login.php');

$db = getDB();
$uid = (int)$_SESSION['usuario_id'];

// Ação de Excluir
if (isset($_GET['delete'])) {
    $delId = (int)$_GET['delete'];
    $st = $db->prepare("DELETE FROM editais WHERE id = ? AND usuario_id = ?");
    $st->execute([$delId, $uid]);
    flashMsg('success', 'Concurso removido com sucesso.');
    header('Location: meus_concursos.php');
    exit;
}

// Buscar concursos
$concursosQ = $db->prepare("
    SELECT e.*, 
           (SELECT COUNT(*) FROM tarefas_estudo t JOIN planos_estudo p ON p.id = t.plano_id WHERE p.cargo_id = (SELECT id FROM cargos WHERE edital_id = e.id LIMIT 1)) as total_tarefas,
           (SELECT SUM(concluida) FROM tarefas_estudo t JOIN planos_estudo p ON p.id = t.plano_id WHERE p.cargo_id = (SELECT id FROM cargos WHERE edital_id = e.id LIMIT 1)) as concluidas
    FROM editais e 
    WHERE e.usuario_id = ? AND e.ativo = 1 
    ORDER BY e.criado_em DESC
");
$concursosQ->execute([$uid]);
$concursos = $concursosQ->fetchAll();

$page_title = 'Meus Concursos';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="dashboard-layout">
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="main-content">
        <div class="d-flex ai-center jc-between mb-lg">
            <div>
                <h2 class="fw-900 mb-1">Meus Concursos</h2>
                <p class="text-secondary">Gerencie suas jornadas de estudo e metas.</p>
            </div>
            <a href="upload_edital.php" class="btn-hc btn-primary-hc">
                <i class="bi bi-plus-lg"></i> Novo Concurso
            </a>
        </div>

        <?php if (empty($concursos)): ?>
            <div class="card-glass p-5 text-center">
                <div style="margin-bottom:1.5rem;">
                    <i class="bi bi-bullseye text-neon" style="font-size:4rem; filter: drop-shadow(0 0 10px var(--neon-green-glow));"></i>
                </div>
                <h4>Você ainda não tem concursos cadastrados.</h4>
                <p class="text-secondary mb-4">Comece agora e crie seu primeiro plano de estudos tático.</p>
                <a href="upload_edital.php" class="btn-hc btn-primary-hc btn-lg">Criar Meu Primeiro Plano</a>
            </div>
        <?php else: ?>
            <div class="grid-2">
                <?php foreach ($concursos as $c): 
                    $total = (int)$c['total_tarefas'];
                    $feitas = (int)$c['concluidas'];
                    $perc = $total > 0 ? round(($feitas / $total) * 100, 1) : 0;
                    $dataProva = $c['data_prova'] ? date('d/m/Y', strtotime($c['data_prova'])) : 'A definir';
                ?>
                <div class="card-glass animate__animated animate__fadeInUp">
                    <div class="card-body p-4">
                        <div class="d-flex jc-between ai-start mb-4">
                            <div>
                                <h4 class="fw-800 mb-1"><?= sanitize($c['nome_concurso']) ?></h4>
                                <span class="badge-hc <?= $c['data_prova'] ? 'badge-blue' : 'badge-neon' ?>">
                                    <i class="bi bi-calendar-event me-1"></i> <?= $dataProva ?>
                                </span>
                            </div>
                            <div class="dropdown">
                                <button class="btn-hc btn-ghost btn-sm" onclick="confirmDelete(<?= $c['id'] ?>)">
                                    <i class="bi bi-trash text-danger"></i>
                                </button>
                            </div>
                        </div>

                        <div class="mb-4">
                            <div class="d-flex jc-between ai-center mb-2">
                                <span class="text-muted small">Progresso do Edital</span>
                                <span class="text-neon fw-700"><?= $perc ?>%</span>
                            </div>
                            <div class="progress-hc" style="height:10px;">
                                <div class="progress-bar-fill" style="width:<?= $perc ?>%"></div>
                            </div>
                        </div>

                        <div class="d-flex gap-md">
                            <a href="dashboard.php?edital_id=<?= $c['id'] ?>" class="btn-hc btn-primary-hc btn-sm flex-1">
                                <i class="bi bi-speedometer2"></i> Ver Dashboard
                            </a>
                            <a href="plano_estudos.php?edital_id=<?= $c['id'] ?>" class="btn-hc btn-ghost btn-sm flex-1">
                                <i class="bi bi-calendar3"></i> Cronograma
                            </a>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>
</div>

<script>
function confirmDelete(id) {
    if (confirm('Tem certeza que deseja excluir este concurso? Todo o progresso e o plano de estudos serão perdidos permanentemente.')) {
        location.href = '?delete=' + id;
    }
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
