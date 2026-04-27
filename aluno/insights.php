<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/InsightsData.php';
exigirLogin('../login.php');

$db = getDB();
$usuario_id = $_SESSION['usuario_id'];
$ins = new InsightsData($db, $usuario_id);

$mapa = $ins->getMapaCalor();
$proj = $ins->getProjecao();
$padroes = $ins->getPadroesBanca("FGV"); // Mockando banca

$pageTitle = "Insights de Inteligência";
include __DIR__ . '/../includes/header_aluno.php';
?>

<div class="dashboard-layout">
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="main-content">
        <header class="page-header">
            <h2 class="fw-800">🧠 Insights de Inteligência</h2>
            <p>Estratégia pura baseada no seu comportamento e na banca.</p>
        </header>

        <div class="row">
            <div class="col-md-8">
                <!-- 1. MAPA DE CALOR -->
                <section class="card-glass mb-lg">
                    <div class="card-header-hc">
                        <h5>🔥 Mapa de Calor por Disciplina</h5>
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-md">
                            <?php foreach ($mapa as $m): 
                                $color = $m['taxa'] > 75 ? 'text-neon' : ($m['taxa'] > 50 ? 'text-warning' : 'text-danger');
                                $bg = $m['taxa'] > 75 ? 'rgba(34,197,94,0.1)' : ($m['taxa'] > 50 ? 'rgba(245,158,11,0.1)' : 'rgba(239,68,68,0.1)');
                            ?>
                                <div class="p-3 border-glass rounded d-flex jc-between ai-center" style="background: <?= $bg ?>;">
                                    <div class="fw-700"><?= $m['nome'] ?></div>
                                    <div class="d-flex ai-center gap-lg">
                                        <div class="text-muted" style="font-size: 0.8rem;"><?= $m['questoes'] ?> questões</div>
                                        <div class="fw-800 <?= $color ?>" style="font-size: 1.2rem; min-width: 60px; text-align: right;"><?= round($m['taxa']) ?>%</div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </section>

                <!-- 2. PADRÕES DE BANCA -->
                <section class="card-glass mb-lg border-neon">
                    <div class="card-header-hc">
                        <h5 class="text-neon">🎯 Padrões da Banca & Você</h5>
                    </div>
                    <div class="card-body">
                        <?php foreach ($padroes as $p): ?>
                            <div class="mb-md pb-md border-bottom-glass last-child-no-border">
                                <div class="d-flex gap-md">
                                    <div class="text-purple"><i class="fas fa-microchip"></i></div>
                                    <div style="font-size: 0.95rem;"><?= $p['msg'] ?></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        <button class="btn-hc btn-ai btn-sm w-100">SOLICITAR ANÁLISE DE BANCA COMPLETA</button>
                    </div>
                </section>
            </div>

            <div class="col-md-4">
                <!-- 3. PROJEÇÃO DE APROVAÇÃO -->
                <section class="card-glass p-4 mb-md text-center" style="background: linear-gradient(135deg, #0f172a, #1e293b);">
                    <h6 class="text-muted fw-700 text-uppercase mb-md">Projeção de Aprovação</h6>
                    <div class="d-flex jc-between ai-center mb-md">
                        <div class="text-left">
                            <div class="text-muted" style="font-size: 0.7rem;">Cenário Atual</div>
                            <div class="fw-800" style="font-size: 1.8rem;"><?= $proj['atual'] ?>%</div>
                        </div>
                        <div style="font-size: 1.5rem; opacity: 0.2;"><i class="fas fa-chevron-right"></i></div>
                        <div class="text-right">
                            <div class="text-muted" style="font-size: 0.7rem;">Meta Ideal</div>
                            <div class="fw-800 text-neon" style="font-size: 1.8rem;"><?= $proj['ideal'] ?>%</div>
                        </div>
                    </div>
                    <div class="badge-hc badge-blue w-100 mb-md">Tendência: <?= $proj['tendencia'] ?></div>
                    <p class="text-secondary" style="font-size: 0.8rem;">Faltam aprox. <strong><?= $proj['dias_para_meta'] ?> dias</strong> mantendo o ritmo atual para atingir a zona de classificação.</p>
                </section>

                <!-- 4. RECOMENDAÇÕES -->
                <section class="card-glass p-4 mb-md">
                    <h6 class="fw-800 mb-md">🛡️ Plano de Ataque</h6>
                    <ul class="list-unstyled d-grid gap-sm">
                        <li class="d-flex gap-sm ai-center" style="font-size: 0.85rem;">
                            <i class="fas fa-arrow-up text-neon"></i> Focar em <strong>Licitações</strong> (Baixo acerto)
                        </li>
                        <li class="d-flex gap-sm ai-center" style="font-size: 0.85rem;">
                            <i class="fas fa-pause text-warning"></i> Reduzir tempo em <strong>Constitucional</strong> (Domínio total)
                        </li>
                        <li class="d-flex gap-sm ai-center" style="font-size: 0.85rem;">
                            <i class="fas fa-bolt text-blue"></i> Iniciar revisão de <strong>Informática</strong>
                        </li>
                    </ul>
                    <button class="btn-hc btn-primary-hc w-100 mt-lg">
                        GERAR PLANO OTIMIZADO COM IA
                    </button>
                </section>
            </div>
        </div>
    </main>
</div>

<?php include __DIR__ . '/../includes/footer_aluno.php'; ?>
