<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/DashboardData.php';
exigirLogin('../login.php');

$db = getDB();
$usuario_id = $_SESSION['usuario_id'];
$dash = new DashboardData($db, $usuario_id);
$m = $dash->getMetrics();
$radar = $dash->getRadar();

$pageTitle = "Central de Evolução";
include __DIR__ . '/../includes/header_aluno.php';
?>

<div class="dashboard-layout">
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="main-content">
        <?php
        // Verificar se o usuário já tem um plano ativo
        $stmtPlan = $db->prepare("SELECT id FROM planos_estudo WHERE usuario_id = ? AND ativo = 1 LIMIT 1");
        $stmtPlan->execute([$usuario_id]);
        $hasPlan = (bool)$stmtPlan->fetch();

        if (!$hasPlan): 
        ?>
            <!-- TELA DE ATIVAÇÃO (ONBOARDING) -->
            <div class="onboarding-container animate__animated animate__fadeIn" style="max-width: 900px; margin: 4rem auto; padding: 0 1rem;">
                <!-- HEADER DE ATIVAÇÃO -->
                <div class="text-center mb-xl">
                    <div class="badge-hc badge-neon mb-md" style="padding: 0.5rem 1.5rem; font-size: 0.8rem; letter-spacing: 2px;">MODO ESTRATÉGICO ATIVADO</div>
                    <h1 class="fw-900 text-white mb-md" style="font-size: clamp(2.5rem, 5vw, 4rem); line-height: 1; letter-spacing: -2px;">
                        Sua aprovação começa <span class="text-neon">aqui.</span>
                    </h1>
                    <p class="text-secondary" style="font-size: 1.25rem; max-width: 650px; margin: 0 auto; line-height: 1.6;">
                        O HackConcursos não é um banco de questões. É o sistema que diz exatamente <strong>o que</strong> e <strong>quando</strong> estudar para vencer a banca.
                    </p>
                </div>

                <!-- BLOCO CENTRAL DE AÇÃO -->
                <div class="card-glass p-0 mb-xl border-neon" style="overflow: hidden; background: linear-gradient(135deg, rgba(34,197,94,0.05), transparent);">
                    <div class="row g-0">
                        <div class="col-md-7 p-5">
                            <h3 class="fw-800 text-white mb-md">Crie sua estratégia em segundos</h3>
                            <ul class="list-unstyled d-grid gap-md mb-xl">
                                <li class="d-flex ai-center gap-md">
                                    <div class="text-neon" style="font-size: 1.2rem;"><i class="fas fa-check-circle"></i></div>
                                    <div class="text-secondary">Análise automática do edital por peso e incidência.</div>
                                </li>
                                <li class="d-flex ai-center gap-md">
                                    <div class="text-neon" style="font-size: 1.2rem;"><i class="fas fa-check-circle"></i></div>
                                    <div class="text-secondary">Plano dinâmico que se ajusta à sua rotina real.</div>
                                </li>
                                <li class="d-flex ai-center gap-md">
                                    <div class="text-neon" style="font-size: 1.2rem;"><i class="fas fa-check-circle"></i></div>
                                    <div class="text-secondary">Missões diárias focadas no que realmente cai.</div>
                                </li>
                            </ul>
                            <button class="btn-hc btn-neon btn-xl px-xl py-3 fw-900 shadow-neon w-100" style="font-size: 1.3rem;" onclick="location.href='biblioteca.php'">
                                CONFIGURAR MEU ALVO <i class="fas fa-arrow-right ml-sm"></i>
                            </button>
                        </div>
                        <div class="col-md-5 d-none d-md-flex ai-center jc-center p-5" style="background: rgba(255,255,255,0.02); border-left: 1px solid rgba(255,255,255,0.05);">
                            <div class="text-center">
                                <div class="text-neon mb-md" style="font-size: 4rem;"><i class="fas fa-chess-knight"></i></div>
                                <div class="fw-800 text-white small text-uppercase letter-spacing-1">Estratégia > Esforço</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- PASSOS E OBJETIVOS INICIAIS -->
                <div class="row g-4">
                    <div class="col-md-4">
                        <div class="card-glass p-4 border-glass text-center h-100">
                            <div class="mb-sm text-secondary"><i class="fas fa-bullseye" style="font-size: 1.5rem;"></i></div>
                            <h6 class="fw-800 text-white mb-xs">Objetivo 01</h6>
                            <p class="text-muted small">Escolher seu concurso na biblioteca</p>
                            <div class="badge-hc badge-ghost">PENDENTE</div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card-glass p-4 border-glass text-center h-100">
                            <div class="mb-sm text-secondary"><i class="fas fa-clock" style="font-size: 1.5rem;"></i></div>
                            <h6 class="fw-800 text-white mb-xs">Objetivo 02</h6>
                            <p class="text-muted small">Definir seus horários de estudo</p>
                            <div class="badge-hc badge-ghost">AGUARDANDO</div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card-glass p-4 border-glass text-center h-100">
                            <div class="mb-sm text-secondary"><i class="fas fa-bolt" style="font-size: 1.5rem;"></i></div>
                            <h6 class="fw-800 text-white mb-xs">Objetivo 03</h6>
                            <p class="text-muted small">Iniciar sua primeira missão</p>
                            <div class="badge-hc badge-ghost">AGUARDANDO</div>
                        </div>
                    </div>
                </div>
            </div>

        <?php else: ?>
            <!-- TOPO (HUD FIXO) -->
            <header class="hud-container mb-md" id="hud-stats">
                <div class="card-glass p-3 d-flex jc-between ai-center">
                    <div class="d-flex gap-lg">
                        <div class="hud-item">
                            <span class="form-label mb-0" style="font-size: 0.65rem;">Domínio</span>
                            <div class="fw-800 text-neon"><?= $m['nivel_label'] ?></div>
                        </div>
                        <div class="hud-item">
                            <span class="form-label mb-0" style="font-size: 0.65rem;">Sequência</span>
                            <div class="fw-800 text-warning">🔥 <?= $m['streak'] ?> Dias</div>
                        </div>
                        <div class="hud-item">
                            <span class="form-label mb-0" style="font-size: 0.65rem;">Energia</span>
                            <div class="fw-800 text-blue">⚡ <?= $m['tokens'] ?> Cargas</div>
                        </div>
                        <div class="hud-item">
                            <span class="form-label mb-0" style="font-size: 0.65rem;">Eficiência</span>
                            <div class="fw-800" style="color: <?= $m['risco']['color'] ?>"><?= 100 - (int)$m['cobertura'] ?>%</div>
                        </div>
                    </div>
                    <button class="btn-hc btn-ai btn-sm" id="btn-activate-ia" onclick="location.href='insights.php'">
                        ✨ OTIMIZAR SISTEMA
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

                    <!-- LINHA 2: FOCO DO DIA -->
                    <section class="mission-center mb-lg" id="current-mission">
                        <?php if ($m['missao']): ?>
                            <div class="card-glass border-neon" style="border-width: 2px;">
                                <div class="card-body d-flex jc-between ai-center">
                                    <div class="flex-1">
                                        <div class="badge-hc badge-neon mb-xs">FOCO DO DIA</div>
                                        <h2 class="fw-800 mb-xs"><?= $m['missao']['titulo'] ?></h2>
                                        <p class="text-secondary mb-md">
                                            <i class="fas fa-info-circle"></i> 
                                            Análise: <strong>Tópico Prioritário</strong>. Seu domínio atual é de <strong><?= $m['missao']['dominio'] ?? 0 ?>%</strong>.
                                        </p>
                                        <div class="d-flex gap-lg">
                                            <div class="hud-item">
                                                <span class="form-label mb-0">Meta de Acerto</span>
                                                <div class="fw-700 text-blue">85%</div>
                                            </div>
                                            <div class="hud-item">
                                                <span class="form-label mb-0">Evolução</span>
                                                <div class="fw-700 text-warning">+150 XP</div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <button class="btn-hc btn-neon btn-xl" onclick="location.href='simulados.php?missao=<?= $m['missao']['id'] ?>'">
                                            INICIAR SPRINT <i class="fas fa-bolt ml-sm"></i>
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

                    <!-- LINHA 3: MAPA DE GARGALOS -->
                    <section class="problem-radar">
                        <h3 class="fw-800 mb-md"><i class="fas fa-satellite-dish text-danger"></i> Mapa de Gargalos</h3>
                        <div class="d-grid gap-md">
                            <?php foreach ($radar as $p): ?>
                                <div class="card-glass p-3 d-flex jc-between ai-center border-glass" style="background: rgba(239,68,68,0.03);">
                                    <div class="d-flex ai-center gap-md">
                                        <div class="text-danger" style="font-size: 1.5rem;"><i class="fas fa-microchip"></i></div>
                                        <div>
                                            <div class="fw-700"><?= $p['msg'] ?></div>
                                            <div class="text-muted" style="font-size: 0.8rem;"><?= $p['detalhe'] ?></div>
                                        </div>
                                    </div>
                                    <div class="d-flex gap-sm">
                                        <button class="btn-hc btn-ghost btn-sm" onclick="location.href='material.php'">Manual (Grátis)</button>
                                        <button class="btn-hc btn-ai btn-sm" onclick="location.href='scanner_erros.php'">✨ Otimizar com IA</button>
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
                        <p class="text-secondary" style="font-size: 0.85rem;"><?= $m['desafio']['titulo'] ?></p>
                        <div class="mt-md">
                            <div class="d-flex jc-between mb-xs">
                                <span style="font-size: 0.75rem;">Progresso</span>
                                <span style="font-size: 0.75rem;"><?= $m['desafio']['progresso'] ?>/<?= $m['desafio']['meta'] ?></span>
                            </div>
                            <div class="progress-hc">
                                <div class="progress-bar-fill" style="width: <?= $m['desafio']['percentual'] ?>%;"></div>
                            </div>
                        </div>
                    </div>

                    <div class="card-glass p-4 border-neon" id="ia-scanner-card" style="background: linear-gradient(180deg, rgba(168,85,247,0.05), transparent);">
                        <h5 class="fw-800 mb-xs text-purple">✨ Scanner de Padrões</h5>
                        <p class="text-secondary mb-md" style="font-size: 0.85rem;">
                            "Identificamos um padrão de erro recorrente. A IA pode aplicar um 'patch' no seu aprendizado."
                        </p>
                        <button class="btn-hc btn-ai w-100" onclick="location.href='scanner_erros.php'">
                            ATIVAR SCANNER (5⚡)
                        </button>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </main>
</div>

<?php include __DIR__ . '/../includes/footer_aluno.php'; ?>
