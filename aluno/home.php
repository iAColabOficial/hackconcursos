<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/DashboardData.php';
exigirLogin('../login.php');

$db = getDB();
$usuario_id = $_SESSION['usuario_id'];
$dash = new DashboardData($db, $usuario_id);
$m = $dash->getMetrics();
$conquistas = $dash->getConquistas();
$pontosFracos = $dash->getPontosFracos();
$is_premium = ($m['plano'] === 'premium');

$pageTitle = "Visão Geral";
include __DIR__ . '/../includes/header_aluno.php';
?>

<div class="dashboard-layout">
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="main-content">
        <!-- HUD SUPERIOR -->
        <header class="d-flex jc-between ai-end mb-lg">
            <div>
                <h1 class="fw-900 mb-xs" style="font-size: 1.8rem;">Rumo à Aprovação!</h1>
                <p class="text-secondary">Sua jornada épica começou. Cada minuto conta.</p>
            </div>
            <div class="d-flex gap-lg">
                <div class="text-right">
                    <div class="kpi-label" style="font-size: 0.6rem;">Probabilidade de Aprovação</div>
                    <div class="d-flex ai-center gap-sm">
                        <div class="fw-900 text-neon" style="font-size: 1.8rem;"><?= $m['probabilidade'] ?>%</div>
                        <i class="fas fa-chart-line text-neon"></i>
                    </div>
                </div>
                <div class="text-right">
                    <div class="kpi-label" style="font-size: 0.6rem;">Risco Atual</div>
                    <div class="fw-900" style="font-size: 1.2rem; color: <?= $m['risco']['color'] ?>;"><?= strtoupper($m['risco']['label']) ?></div>
                    <div class="text-muted" style="font-size: 0.6rem;">Foco nos pontos fracos!</div>
                </div>
            </div>
        </header>

        <!-- BARRA DE JORNADA -->
        <section class="card-glass p-4 mb-lg">
            <div class="d-flex jc-between mb-sm">
                <span class="fw-700" style="font-size: 0.8rem;">PROGRESSO DO EDITAL</span>
                <span class="badge-hc badge-neon"><?= $m['cobertura'] ?>%</span>
            </div>
            <div class="journey-container">
                <div class="journey-bar">
                    <div class="journey-progress" style="width: <?= $m['cobertura'] ?>%;"></div>
                    <div class="journey-node active">
                        <span class="node-label">Iniciante<br>0%</span>
                    </div>
                    <div class="journey-node <?= $m['cobertura'] >= 25 ? 'active' : '' ?>">
                        <span class="node-label">Em Construção<br>25%</span>
                    </div>
                    <div class="journey-node <?= $m['cobertura'] >= 50 ? 'active' : '' ?>">
                        <span class="node-label">Competitivo<br>50%</span>
                    </div>
                    <div class="journey-node <?= $m['cobertura'] >= 75 ? 'active' : '' ?>">
                        <span class="node-label">Avançado<br>75%</span>
                    </div>
                    <div class="journey-node <?= $m['cobertura'] >= 100 ? 'active' : '' ?>">
                        <span class="node-label">Elite<br>100%</span>
                    </div>
                </div>
            </div>
        </section>

        <div class="row">
            <!-- COLUNA CENTRAL -->
            <div class="col-md-8">
                <!-- MISSÃO ATIVA -->
                <section class="card-glass mb-lg border-neon" style="background: linear-gradient(135deg, rgba(34,197,94,0.05), transparent);">
                    <div class="card-body">
                        <div class="d-flex jc-between ai-start mb-md">
                            <div>
                                <div class="text-warning fw-800 mb-xs" style="font-size: 0.7rem;"><i class="fas fa-bullseye"></i> MISSÃO ATIVA</div>
                                <h2 class="fw-900"><?= $m['missao']['titulo'] ?? 'Nenhuma missão ativa' ?></h2>
                                <div class="badge-hc badge-danger">ALTA INCIDÊNCIA</div>
                            </div>
                            <div class="circle-progress">
                                <svg width="100" height="100">
                                    <circle class="bg" cx="50" cy="50" r="40"></circle>
                                    <circle class="bar" cx="50" cy="50" r="40" style="stroke-dasharray: 251.2; stroke-dashoffset: <?= 251.2 - (251.2 * 0.6) ?>;"></circle>
                                </svg>
                                <div class="circle-value">
                                    <span class="fw-900" style="font-size: 1.2rem;">60%</span>
                                    <span class="text-muted" style="font-size: 0.5rem;">CONCLUÍDO</span>
                                </div>
                            </div>
                        </div>

                        <div class="row mb-md">
                            <div class="col-md-7">
                                <h6 class="fw-800 mb-sm" style="font-size: 0.7rem; color: var(--text-muted);">OBJETIVO</h6>
                                <ul class="list-unstyled" style="font-size: 0.85rem;">
                                    <li class="mb-xs"><i class="fas fa-check-circle text-neon"></i> Estudar teoria: Principais tópicos</li>
                                    <li class="mb-xs"><i class="fas fa-check-circle text-neon"></i> Resolver 10 questões da banca</li>
                                    <li><i class="far fa-circle text-muted"></i> Meta de acerto: 75% ou mais</li>
                                </ul>
                            </div>
                            <div class="col-md-5 text-right">
                                <img src="<?= APP_URL ?>/assets/img/warrior_avatar.png" style="width: 120px; filter: drop-shadow(0 0 10px var(--neon-green));" alt="Mestre">
                            </div>
                        </div>

                        <div class="d-flex jc-between ai-center pt-md border-top-glass">
                            <div class="d-flex gap-lg">
                                <div>
                                    <div class="text-muted" style="font-size: 0.6rem;">RECOMPENSAS</div>
                                    <div class="d-flex gap-sm ai-center" style="font-size: 0.8rem;">
                                        <span class="text-warning"><i class="fas fa-bolt"></i> 150 XP</span>
                                        <span class="text-blue"><i class="fas fa-battery-three-quarters"></i> 2 Energia</span>
                                    </div>
                                </div>
                            </div>
                            <button class="btn-hc btn-neon btn-lg" onclick="location.href='dashboard.php'">CONTINUAR MISSÃO <i class="fas fa-arrow-right"></i></button>
                        </div>
                    </div>
                </section>

                <!-- ALERTAS / SCANNER IA -->
                <div class="card-glass p-3 mb-lg border-glass d-flex jc-between ai-center <?= !$is_premium ? 'premium-lock' : '' ?>" style="background: rgba(239,68,68,0.1);">
                    <?php if (!$is_premium): ?><div class="lock-overlay"><button class="btn-hc btn-ai btn-sm">CONHECER SCANNER</button></div><?php endif; ?>
                    <div class="d-flex ai-center gap-md">
                        <div class="text-danger" style="font-size: 1.5rem;"><i class="fas fa-exclamation-triangle"></i></div>
                        <div style="font-size: 0.85rem;">
                            <strong>ATENÇÃO!</strong> Você cometeu este erro 3 vezes.<br>
                            <span class="text-secondary">Quer descobrir o padrão e parar de perder pontos?</span>
                        </div>
                    </div>
                    <button class="btn-hc btn-ai btn-sm">USAR SCANNER IA</button>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <section class="card-glass h-100">
                            <div class="card-header-hc">
                                <h6 class="fw-800 mb-0"><i class="fas fa-skull text-danger"></i> PONTOS FRACOS</h6>
                            </div>
                            <div class="card-body">
                                <?php foreach ($pontosFracos as $pf): ?>
                                    <div class="d-flex jc-between ai-center mb-sm">
                                        <div style="font-size: 0.8rem;">
                                            <div class="fw-700"><?= $pf['nome'] ?></div>
                                            <div class="text-muted"><?= round($pf['taxa']) ?>% de acerto</div>
                                        </div>
                                        <span class="badge-hc <?= $pf['taxa'] < 50 ? 'badge-danger' : 'badge-warning' ?>"><?= $pf['taxa'] < 50 ? 'CRÍTICO' : 'ALTO' ?></span>
                                    </div>
                                <?php endforeach; ?>
                                <button class="btn-hc btn-ghost btn-sm w-100 mt-md">VER TODOS</button>
                            </div>
                        </section>
                    </div>
                    <div class="col-md-6">
                        <section class="card-glass h-100">
                            <div class="card-header-hc">
                                <h6 class="fw-800 mb-0"><i class="fas fa-trophy text-warning"></i> CONQUISTAS RECENTES</h6>
                            </div>
                            <div class="card-body">
                                <?php foreach ($conquistas as $c): ?>
                                    <div class="d-flex jc-between ai-center mb-sm">
                                        <div class="d-flex ai-center gap-sm">
                                            <span style="font-size: 1.2rem;"><?= $c['icone'] ?></span>
                                            <div style="font-size: 0.8rem;">
                                                <div class="fw-700"><?= $c['titulo'] ?></div>
                                                <div class="text-muted" style="font-size: 0.7rem;"><?= $c['descricao'] ?></div>
                                            </div>
                                        </div>
                                        <span class="text-warning" style="font-size: 0.7rem; font-weight: 700;">+100 XP</span>
                                    </div>
                                <?php endforeach; ?>
                                <button class="btn-hc btn-ghost btn-sm w-100 mt-md">VER TODAS</button>
                            </div>
                        </section>
                    </div>
                </div>
            </div>

            <!-- COLUNA LATERAL -->
            <div class="col-md-4">
                <!-- PRÓXIMA MISSÃO -->
                <section class="card-glass mb-md">
                    <div class="card-header-hc">
                        <h6 class="fw-800 mb-0">PRÓXIMA MISSÃO</h6>
                    </div>
                    <div class="card-body">
                        <?php if ($m['proxima_missao']): ?>
                            <div class="d-flex ai-center gap-md mb-md">
                                <div class="kpi-icon blue"><i class="fas fa-shield-alt"></i></div>
                                <div>
                                    <div class="fw-800" style="font-size: 0.9rem;"><?= $m['proxima_missao']['titulo'] ?></div>
                                    <div class="badge-hc badge-muted" style="font-size: 0.6rem;">MÉDIA INCIDÊNCIA</div>
                                </div>
                            </div>
                            <div class="text-muted mb-md" style="font-size: 0.75rem;">
                                Tópico: Direito Constitucional<br>
                                <i class="far fa-clock"></i> 50 min estimados
                            </div>
                            <button class="btn-hc btn-ghost btn-sm w-100">VER PLANO COMPLETO</button>
                        <?php else: ?>
                            <p class="text-muted text-center py-4">Sem missões agendadas.</p>
                        <?php endif; ?>
                    </div>
                </section>

                <!-- DESEMPENHO RECENTE -->
                <section class="card-glass mb-md">
                    <div class="card-header-hc d-flex jc-between ai-center">
                        <h6 class="fw-800 mb-0">DESEMPENHO RECENTE</h6>
                        <span class="text-neon fw-900">+14%</span>
                    </div>
                    <div class="card-body">
                        <div class="mb-md" style="height: 60px; background: rgba(34,197,94,0.05); border-radius: 8px; position: relative; overflow: hidden;">
                             <!-- Mock de gráfico -->
                             <svg viewBox="0 0 100 20" style="width: 100%; height: 100%;">
                                <path d="M0,15 Q25,5 50,12 T100,2" fill="none" stroke="var(--neon-green)" stroke-width="2" />
                             </svg>
                        </div>
                        <div class="row text-center">
                            <div class="col-4">
                                <div class="text-neon fw-800">68%</div>
                                <div class="text-muted" style="font-size: 0.6rem;">Acertos</div>
                            </div>
                            <div class="col-4">
                                <div class="fw-800">142</div>
                                <div class="text-muted" style="font-size: 0.6rem;">Questões</div>
                            </div>
                            <div class="col-4">
                                <div class="fw-800">8h 30m</div>
                                <div class="text-muted" style="font-size: 0.6rem;">Tempo</div>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- SEQUÊNCIA -->
                <section class="card-glass p-4 mb-md">
                    <div class="text-center">
                        <div class="text-warning fw-800 mb-xs" style="font-size: 0.7rem;"><i class="fas fa-fire"></i> SEQUÊNCIA (STREAK)</div>
                        <div class="fw-900" style="font-size: 2.5rem; line-height: 1;"><?= $m['streak'] ?> DIAS</div>
                        <p class="text-secondary mt-sm" style="font-size: 0.8rem;">Muito bem! Não quebre agora!</p>
                    </div>
                    <div class="d-flex jc-between mt-md px-2">
                        <?php 
                        $dias = ['S','T','Q','Q','S','S','D'];
                        foreach($dias as $i => $d): ?>
                            <div class="text-center">
                                <div class="text-muted mb-xs" style="font-size: 0.6rem;"><?= $d ?></div>
                                <div style="font-size: 1.2rem; color: <?= ($i < $m['streak'] % 7) ? 'var(--warning)' : 'rgba(255,255,255,0.1)' ?>;">
                                    <i class="fas fa-fire"></i>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="mt-md pt-md border-top-glass text-center" style="font-size: 0.7rem;">
                        <span class="text-muted">PRÓXIMA RECOMPENSA: 7 DIAS</span><br>
                        <strong class="text-warning">BÔNUS DE 50 XP</strong>
                    </div>
                </section>

                <!-- IA PREMIUM LOCKS -->
                <div class="card-glass p-3 mb-md premium-lock">
                    <div class="lock-badge">PRO</div>
                    <div class="d-flex ai-center gap-md">
                        <div class="text-purple"><i class="fas fa-brain"></i></div>
                        <div class="fw-700" style="font-size: 0.8rem;">Scanner de Padrões IA</div>
                    </div>
                </div>
                <div class="card-glass p-3 mb-md premium-lock">
                    <div class="lock-badge">PRO</div>
                    <div class="d-flex ai-center gap-md">
                        <div class="text-purple"><i class="fas fa-route"></i></div>
                        <div class="fw-700" style="font-size: 0.8rem;">Rota Inteligente (Refazer Plano)</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- DICA DO MENTOR -->
        <footer class="card-glass p-3 mt-lg d-flex ai-center gap-md" style="background: rgba(34,197,94,0.03);">
            <div class="mentor-avatar">
                <img src="<?= APP_URL ?>/assets/img/mentor_avatar.png" style="width: 40px; border-radius: 50%; border: 2px solid var(--neon-green);" alt="Mentor">
            </div>
            <div style="font-size: 0.8rem;">
                <span class="fw-800 text-neon">DICA DO MENTOR:</span>
                <span class="text-secondary">A constância é o que separa quem tenta de quem conquista. Foque 1% melhor a cada dia.</span>
            </div>
        </footer>
    </main>
</div>

<?php include __DIR__ . '/../includes/footer_aluno.php'; ?>
