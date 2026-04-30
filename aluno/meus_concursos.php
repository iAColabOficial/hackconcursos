<?php
/**
 * HackConcursos - Meus Concursos
 * Listagem e gestão de múltiplos planos de estudo
 */
require_once __DIR__ . '/../config/config.php';
exigirLogin('../login.php');

$db = getDB();
$uid = (int)$_SESSION['usuario_id'];

// Limites do Plano
$limite = ($_SESSION['plano'] === 'premium') ? LIMIT_TARGETS_TURBO : (($_SESSION['plano'] === 'anual') ? LIMIT_TARGETS_MASTERMIND : LIMIT_TARGETS_ACESSO);
$atual  = getContagemAlvos($uid);
$bloqueado = ($atual >= $limite);

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

// Buscar sugestões pendentes
$sugestoesQ = $db->prepare("SELECT * FROM biblioteca_editais WHERE usuario_id = ? AND situacao_adm = 'pendente'");
$sugestoesQ->execute([$uid]);
$sugestoes = $sugestoesQ->fetchAll();

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
            <?php if ($bloqueado): ?>
                <button class="btn-hc btn-ghost btn-sm" onclick="alert('Limite de <?= $limite ?> alvo atingido. Remova o atual ou faça upgrade para adicionar outro.')">
                    <i class="bi bi-lock-fill"></i> Novo Concurso
                </button>
            <?php else: ?>
                <a href="upload_edital.php" class="btn-hc btn-primary-hc">
                    <i class="bi bi-plus-lg"></i> Novo Concurso
                </a>
            <?php endif; ?>
        </div>

        <?php if (empty($concursos)): ?>
            <div class="card-glass p-5 text-center">
                <div style="margin-bottom:1.5rem;">
                    <i class="bi bi-bullseye text-neon" style="font-size:4rem; filter: drop-shadow(0 0 10px var(--neon-green-glow));"></i>
                </div>
                <h4>Você ainda não tem concursos cadastrados.</h4>
                <p class="text-secondary mb-4">Seu progresso aparecerá aqui assim que seu edital for aprovado ou selecionado.</p>
                <?php if (!$bloqueado): ?>
                    <a href="biblioteca.php" class="btn-hc btn-primary-hc btn-lg">Escolher Meu Primeiro Concurso</a>
                <?php else: ?>
                    <div class="alert-hc alert-info d-inline-flex ai-center gap-sm">
                        <i class="bi bi-hourglass-split"></i> Aguardando aprovação da sua sugestão para liberar o plano.
                    </div>
                <?php endif; ?>
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

        <!-- SEÇÃO DE SUGESTÕES PENDENTES -->
        <?php if (!empty($sugestoes)): ?>
            <div class="mt-xl">
                <div class="d-flex ai-center gap-sm mb-md">
                    <h3 class="fw-800 mb-0">Sugestões em Análise</h3>
                    <span class="badge-hc badge-muted"><?= count($sugestoes) ?></span>
                </div>
                <div class="grid-2">
                    <?php foreach ($sugestoes as $s): ?>
                        <div class="card-glass" style="border-style: dashed; border-color: var(--border-glass); opacity: 0.8;">
                            <div class="card-body p-4 d-flex ai-center jc-between">
                                <div>
                                    <h5 class="fw-700 mb-1"><?= sanitize($s['nome_concurso']) ?></h5>
                                    <div class="text-muted small">
                                        <i class="bi bi-bank"></i> <?= sanitize($s['orgao'] ?? '---') ?> | 
                                        <i class="bi bi-clock-history"></i> Enviado em: <?= date('d/m/Y', strtotime($s['criado_em'])) ?>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <span class="badge-hc badge-warning">
                                        <i class="bi bi-hourglass-split me-1"></i> EM ANÁLISE
                                    </span>
                                    <div class="text-muted mt-2" style="font-size: 0.65rem;">Liberação em até 24h</div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
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
