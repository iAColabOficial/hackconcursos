<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/ScannerErros.php';
exigirLogin('../login.php');

$db = getDB();
$usuario_id = $_SESSION['usuario_id'];
$scanner = new ScannerErros($db, $usuario_id);
$resumo = $scanner->getResumo();
$padroes = $scanner->getPadroes();

$pageTitle = "Scanner de Erros";
include __DIR__ . '/../includes/header_aluno.php';
?>

<div class="dashboard-layout">
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="main-content">
        <header class="page-header">
            <div class="page-breadcrumb">
                <a href="dashboard.php">Dashboard</a> <span class="sep">/</span> <span>Scanner de Erros</span>
            </div>
            <h2 class="fw-800">🔍 Scanner de Erros</h2>
            <p>Identificando falhas invisíveis no seu processo de estudo.</p>
        </header>

        <!-- TOPO: RESUMO GERAL -->
        <div class="grid-3 mb-lg">
            <div class="card-glass p-4">
                <div class="kpi-label">Total de Erros</div>
                <div class="kpi-value text-danger"><?= $resumo['total_erros'] ?></div>
                <div class="kpi-sub">Questões falhas detectadas</div>
            </div>
            <div class="card-glass p-4">
                <div class="kpi-label">Taxa de Acerto</div>
                <div class="kpi-value"><?= $resumo['taxa_acerto'] ?>%</div>
                <div class="progress-hc mt-xs" style="height: 4px;">
                    <div class="progress-bar-fill" style="width: <?= $resumo['taxa_acerto'] ?>%;"></div>
                </div>
            </div>
            <div class="card-glass p-4 border-neon">
                <div class="kpi-label">Principal Padrão</div>
                <div class="fw-800 mt-xs" style="font-size: 1.1rem;"><?= $resumo['padrao_principal'] ?></div>
                <div class="badge-hc badge-danger mt-sm">DETECÇÃO CRÍTICA</div>
            </div>
        </div>

        <!-- LISTA DE PADRÕES -->
        <section class="error-patterns">
            <div class="d-flex jc-between ai-center mb-md">
                <h3 class="fw-800">Padrões de Erro Detectados</h3>
                <button class="btn-hc btn-ai" onclick="alert('IA analisando banco de dados...')">
                    <i class="fas fa-sync-alt mr-sm"></i> ATUALIZAR SCANNER
                </button>
            </div>

            <div class="d-grid gap-md">
                <?php foreach ($padroes as $p): ?>
                    <div class="card-glass overflow-hidden">
                        <div class="card-body d-flex jc-between ai-center">
                            <div class="d-flex gap-lg ai-center">
                                <div class="text-center" style="min-width: 80px;">
                                    <div class="fw-800 text-danger" style="font-size: 1.5rem;"><?= $p['frequencia'] ?>%</div>
                                    <div class="text-muted" style="font-size: 0.7rem; text-transform: uppercase;">Freq.</div>
                                </div>
                                <div style="border-left: 1px solid var(--border-glass); padding-left: 1.5rem;">
                                    <h4 class="fw-700 mb-xs"><?= $p['nome'] ?></h4>
                                    <div class="d-flex gap-sm mb-xs">
                                        <span class="chip"><?= $p['disciplina'] ?></span>
                                        <span class="badge-hc badge-<?= $p['impacto'] == 'Crítico' ? 'danger' : 'warning' ?>"><?= $p['impacto'] ?> Impacto</span>
                                    </div>
                                    <p class="text-secondary" style="font-size: 0.9rem;"><?= $p['descricao'] ?></p>
                                </div>
                            </div>
                            <button class="btn-hc btn-ghost btn-sm" onclick="this.parentElement.nextElementSibling.classList.toggle('d-none')">
                                VER DIAGNÓSTICO
                            </button>
                        </div>
                        
                        <!-- Diagnóstico Oculto -->
                        <div class="card-body bg-dark d-none border-top-glass">
                            <div class="row">
                                <div class="col-md-4">
                                    <h6 class="text-muted fw-700 text-uppercase mb-sm" style="font-size: 0.7rem;">Mecanismo do Erro</h6>
                                    <p style="font-size: 0.85rem;">O usuário associa o termo "Licitação" apenas a "Pregão", esquecendo modalidades de dispensa.</p>
                                </div>
                                <div class="col-md-4">
                                    <h6 class="text-muted fw-700 text-uppercase mb-sm" style="font-size: 0.7rem;">Contramedida</h6>
                                    <p style="font-size: 0.85rem;">Flashcards específicos sobre o Artigo 75 da Nova Lei de Licitações.</p>
                                </div>
                                <div class="col-md-4">
                                    <button class="btn-hc btn-ai w-100" style="margin-top: 1rem;">
                                        GERAR ANÁLISE PROFUNDA (IA)
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    </main>
</div>

<?php include __DIR__ . '/../includes/footer_aluno.php'; ?>
