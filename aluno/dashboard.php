<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/DashboardData.php';
exigirLogin('../login.php');

$db = getDB();
$usuario_id = $_SESSION['usuario_id'];
$dash = new DashboardData($db, $usuario_id);
$m = $dash->getMetrics();
$radar = $dash->getRadar();

$pageTitle = "Dashboard de Guerra";
include __DIR__ . '/../includes/header_aluno.php';
?>

<div class="dashboard-layout">
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="main-content">
        <!-- TOPO (HUD FIXO) -->
        <header class="hud-container mb-md" id="hud-stats">
            <div class="card-glass p-3 d-flex jc-between ai-center">
                <div class="d-flex gap-lg">
                    <div class="hud-item">
                        <span class="form-label mb-0" style="font-size: 0.65rem;">Nível</span>
                        <div class="fw-800 text-neon"><?= $m['nivel_label'] ?></div>
                    </div>
                    <div class="hud-item">
                        <span class="form-label mb-0" style="font-size: 0.65rem;">Streak</span>
                        <div class="fw-800 text-warning">🔥 <?= $m['streak'] ?> Dias</div>
                    </div>
                    <div class="hud-item">
                        <span class="form-label mb-0" style="font-size: 0.65rem;">Energia</span>
                        <div class="fw-800 text-blue">⚡ <?= $m['tokens'] ?> Tokens</div>
                    </div>
                    <div class="hud-item">
                        <span class="form-label mb-0" style="font-size: 0.65rem;">Risco</span>
                        <div class="fw-800" style="color: <?= $m['risco']['color'] ?>"><?= $m['risco']['label'] ?></div>
                    </div>
                </div>
                <button class="btn-hc btn-ai btn-sm" id="btn-activate-ia" onclick="location.href='insights.php'">
                    ✨ ATIVAR IA (SKILL)
                </button>
            </div>
        </header>

        <div class="row">
            <div class="col-md-9">
                <!-- LINHA 1: VISÃO ESTRATÉGICA -->
                <div class="grid-4 mb-lg">
                    <div class="kpi-card">
                        <div class="kpi-icon blue"><i class="fas fa-book-open"></i></div>
                        <div>
                            <div class="kpi-label">Cobertura</div>
                            <div class="kpi-value"><?= $m['cobertura'] ?>%</div>
                        </div>
                    </div>
                    <div class="kpi-card">
                        <div class="kpi-icon neon"><i class="fas fa-bullseye"></i></div>
                        <div>
                            <div class="kpi-label">Acerto Geral</div>
                            <div class="kpi-value"><?= $m['taxa_acerto'] ?>%</div>
                        </div>
                    </div>
                    <div class="kpi-card">
                        <div class="kpi-icon warn"><i class="fas fa-skull"></i></div>
                        <div>
                            <div class="kpi-label">Pior Matéria</div>
                            <div class="kpi-value" style="font-size: 1.1rem;"><?= $m['fraca']['nome'] ?></div>
                            <div class="kpi-sub text-danger"><?= round($m['fraca']['taxa']) ?>%</div>
                        </div>
                    </div>
                    <div class="kpi-card">
                        <div class="kpi-icon purple"><i class="fas fa-crown"></i></div>
                        <div>
                            <div class="kpi-label">Melhor Matéria</div>
                            <div class="kpi-value" style="font-size: 1.1rem;"><?= $m['forte']['nome'] ?></div>
                            <div class="kpi-sub text-neon"><?= round($m['forte']['taxa']) ?>%</div>
                        </div>
                    </div>
                </div>

                <!-- LINHA 2: MISSÃO ATUAL -->
                <section class="mission-center mb-lg" id="current-mission">
                    <?php if ($m['missao']): ?>
                        <div class="card-glass border-neon" style="border-width: 2px;">
                            <div class="card-body d-flex jc-between ai-center">
                                <div class="flex-1">
                                    <div class="badge-hc badge-neon mb-xs">MISSÃO ATIVA</div>
                                    <h2 class="fw-800 mb-xs"><?= $m['missao']['titulo'] ?></h2>
                                    <p class="text-secondary mb-md">
                                        <i class="fas fa-info-circle"></i> 
                                        Motivo: <strong>Alta incidência</strong> e sua última taxa foi de <strong>55%</strong>.
                                    </p>
                                    <div class="d-flex gap-lg">
                                        <div class="hud-item">
                                            <span class="form-label mb-0">Meta</span>
                                            <div class="fw-700 text-blue">85% Acerto</div>
                                        </div>
                                        <div class="hud-item">
                                            <span class="form-label mb-0">Recompensa</span>
                                            <div class="fw-700 text-warning">+150 XP</div>
                                        </div>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <button class="btn-hc btn-neon btn-xl" onclick="location.href='simulados.php?missao=<?= $m['missao']['id'] ?>'">
                                        INICIAR MISSÃO <i class="fas fa-rocket ml-sm"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="card-glass p-5 text-center">
                            <h3>Nenhuma missão para hoje.</h3>
                            <p class="text-secondary">Você completou todos os objetivos do ciclo!</p>
                            <button class="btn-hc btn-primary-hc mt-md" onclick="location.href='plano_estudos.php'">Gerar Novo Ciclo</button>
                        </div>
                    <?php endif; ?>
                </section>

                <!-- LINHA 3: RADAR DE PROBLEMAS -->
                <section class="problem-radar">
                    <h3 class="fw-800 mb-md"><i class="fas fa-satellite-dish text-danger"></i> Radar de Problemas</h3>
                    <div class="d-grid gap-md">
                        <?php foreach ($radar as $p): ?>
                            <div class="card-glass p-3 d-flex jc-between ai-center border-glass" style="background: rgba(239,68,68,0.03);">
                                <div class="d-flex ai-center gap-md">
                                    <div class="text-danger" style="font-size: 1.5rem;"><i class="fas fa-exclamation-triangle"></i></div>
                                    <div>
                                        <div class="fw-700"><?= $p['msg'] ?></div>
                                        <div class="text-muted" style="font-size: 0.8rem;"><?= $p['detalhe'] ?></div>
                                    </div>
                                </div>
                                <div class="d-flex gap-sm">
                                    <button class="btn-hc btn-ghost btn-sm" onclick="location.href='material.php'">Manual (Grátis)</button>
                                    <button class="btn-hc btn-ai btn-sm" onclick="location.href='scanner_erros.php'">✨ Resolver com IA</button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        
                        <?php if (empty($radar)): ?>
                            <div class="text-muted p-4 text-center">Nenhum problema crítico detectado. Você está no caminho certo.</div>
                        <?php endif; ?>
                    </div>
                </section>
            </div>

            <!-- LATERAL DIREITA -->
            <div class="col-md-3">
                <div class="card-glass p-4 mb-md text-center">
                    <div class="fw-900 text-warning" style="font-size: 3rem; line-height: 1;"><?= $m['streak'] ?></div>
                    <div class="text-muted fw-700 text-uppercase" style="font-size: 0.7rem;">Dias de Fogo</div>
                    <div class="progress-hc mt-md" style="height: 4px;">
                        <div class="progress-bar-fill" style="width: <?= ($m['streak'] / 30) * 100 ?>%;"></div>
                    </div>
                    <div class="mt-sm text-secondary" style="font-size: 0.8rem;">Próxima Conquista em 5 dias</div>
                </div>

                <div class="card-glass p-4 mb-md">
                    <h5 class="fw-800 mb-sm"><i class="fas fa-trophy text-warning"></i> Desafio Semanal</h5>
                    <p class="text-secondary" style="font-size: 0.85rem;">Acerte 200 questões de Direito Administrativo até Domingo.</p>
                    <div class="mt-md">
                        <div class="d-flex jc-between mb-xs">
                            <span style="font-size: 0.75rem;">Progresso</span>
                            <span style="font-size: 0.75rem;">45/200</span>
                        </div>
                        <div class="progress-hc">
                            <div class="progress-bar-fill" style="width: 22%;"></div>
                        </div>
                    </div>
                </div>

                <div class="card-glass p-4 border-neon" id="ia-scanner-card" style="background: linear-gradient(180deg, rgba(168,85,247,0.05), transparent);">
                    <h5 class="fw-800 mb-xs text-purple">✨ Scanner IA</h5>
                    <p class="text-secondary mb-md" style="font-size: 0.85rem;">
                        "Você está repetindo o mesmo erro em crase. A IA pode identificar o padrão mental."
                    </p>
                    <button class="btn-hc btn-ai w-100" onclick="location.href='scanner_erros.php'">
                        USAR SCANNER (1⚡)
                    </button>
                </div>
            </div>
        </div>
    </main>
</div>

<?php include __DIR__ . '/../includes/footer_aluno.php'; ?>
