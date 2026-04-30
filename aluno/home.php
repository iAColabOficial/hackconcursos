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
        <header class="d-flex jc-between ai-end mb-lg" id="hud-stats">
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
                    <div class="kpi-label" style="font-size: 0.6rem;">Taxa de Acerto Global</div>
                    <div class="fw-900" style="color: <?= $m['taxa_acerto'] > 70 ? 'var(--neon-green)' : 'var(--warning)' ?>; font-size: 1.2rem;"><?= $m['taxa_acerto'] ?>%</div>
                    <div class="text-muted" style="font-size: 0.6rem;">Média de desempenho</div>
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
                <!-- FOCO DO DIA -->
                <section class="card-glass mb-lg border-neon" id="current-mission" style="background: linear-gradient(135deg, rgba(34,197,94,0.05), transparent);">
                    <div class="card-body">
                        <div class="d-flex jc-between ai-start mb-md">
                            <div>
                                <div class="text-warning fw-800 mb-xs" style="font-size: 0.7rem;"><i class="fas fa-bullseye"></i> FOCO DO DIA</div>
                                <h2 class="fw-900"><?= $m['missao']['titulo'] ?? 'Nenhum foco definido' ?></h2>
                                <div class="badge-hc badge-danger">PRIORIDADE ALTA</div>
                            </div>
                            <div class="circle-progress">
                                <?php 
                                $progresso_missao = (isset($m['missao']) && $m['missao']['concluida']) ? 100 : 0; 
                                ?>
                                <svg width="100" height="100">
                                    <circle class="bg" cx="50" cy="50" r="40"></circle>
                                    <circle class="bar" cx="50" cy="50" r="40" style="stroke-dasharray: 251.2; stroke-dashoffset: <?= 251.2 - (251.2 * ($progresso_missao/100)) ?>;"></circle>
                                </svg>
                                <div class="circle-value">
                                    <span class="fw-900" style="font-size: 1.2rem;"><?= $progresso_missao ?>%</span>
                                    <span class="text-muted" style="font-size: 0.5rem;">STATUS</span>
                                </div>
                            </div>
                        </div>

                        <div class="row mb-md">
                            <div class="col-md-7">
                                <h6 class="fw-800 mb-sm" style="font-size: 0.7rem; color: var(--text-muted);">OBJETIVO ESTRATÉGICO</h6>
                                <ul class="list-unstyled" style="font-size: 0.85rem;">
                                    <?php if ($m['missao']): ?>
                                        <li class="mb-xs"><i class="fas fa-check-circle text-neon"></i> Dominar: <?= $m['missao']['disciplina_nome'] ?></li>
                                        <li class="mb-xs"><i class="fas fa-check-circle text-neon"></i> Concluir trilha de <?= $m['missao']['duracao_minutos'] ?> min</li>
                                        <li><i class="far fa-circle text-muted"></i> Meta de acerto: <?= $m['taxa_acerto'] > 0 ? max(75, $m['taxa_acerto'] + 5) : 75 ?>%</li>
                                    <?php else: ?>
                                        <li class="text-muted">Nenhuma missão ativa. Vá para o plano para gerar uma.</li>
                                    <?php endif; ?>
                                </ul>
                            </div>
                            <div class="col-md-5 text-right">
                                <div class="mestre-avatar-container">
                                    <div class="mestre-circle">
                                        <i class="fas fa-brain"></i>
                                    </div>
                                    <div class="mestre-label">SISTEMA MESTRE</div>
                                </div>
                            </div>
                        </div>

                        <div class="d-flex jc-between ai-center pt-md border-top-glass">
                            <div class="d-flex gap-lg">
                                <div>
                                    <div class="text-muted" style="font-size: 0.6rem;">EVOLUÇÃO</div>
                                    <div class="d-flex gap-sm ai-center" style="font-size: 0.8rem;">
                                        <span class="text-warning"><i class="fas fa-bolt"></i> 150 XP</span>
                                        <span class="text-blue"><i class="fas fa-battery-three-quarters"></i> 2 Cargas</span>
                                    </div>
                                </div>
                            </div>
                            <button class="btn-hc btn-neon btn-lg" onclick="location.href='dashboard.php'">CONTINUAR SPRINT <i class="fas fa-arrow-right"></i></button>
                        </div>
                    </div>
                </section>

                <!-- ALERTAS / SCANNER IA (DINÂMICO) -->
                <?php 
                // Só mostrar se houver erros reais ou se for premium com dados
                $tem_erros = count($dash->getRadar()) > 0;
                if ($tem_erros): 
                ?>
                <div class="card-glass p-3 mb-lg border-glass d-flex jc-between ai-center <?= !$is_premium ? 'premium-lock' : '' ?>" style="background: rgba(239,68,68,0.1);">
                    <?php if (!$is_premium): ?><div class="lock-overlay"><button class="btn-hc btn-ai btn-sm">DESBLOQUEAR SCANNER</button></div><?php endif; ?>
                    <div class="d-flex ai-center gap-md">
                        <div class="text-danger" style="font-size: 1.5rem;"><i class="fas fa-microchip"></i></div>
                        <div style="font-size: 0.85rem;">
                            <strong>ALERTA DE PADRÃO!</strong> Detectamos uma inconsistência em seus estudos.<br>
                            <span class="text-secondary">Quer aplicar um 'patch' corretivo agora?</span>
                        </div>
                    </div>
                    <button class="btn-hc btn-ai btn-sm" id="btn-activate-ia">ATIVAR SCANNER</button>
                </div>
                <?php endif; ?>

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
                                <?php if (empty($conquistas)): ?>
                                    <div class="text-center py-4">
                                        <div style="font-size: 2rem; opacity: 0.2; margin-bottom: 1rem;"><i class="fas fa-medal"></i></div>
                                        <p class="text-muted small">Nenhuma medalha ainda.<br>Conclua missões para ganhar!</p>
                                    </div>
                                <?php else: ?>
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
                                <?php endif; ?>
                                <button class="btn-hc btn-ghost btn-sm w-100 mt-md">VER TODAS</button>
                            </div>
                        </section>
                    </div>
                </div>
            </div>

            <!-- COLUNA LATERAL -->
            <div class="col-md-4">
                <!-- PRÓXIMA ETAPA -->
                <section class="card-glass mb-md">
                    <div class="card-header-hc">
                        <h6 class="fw-800 mb-0">PRÓXIMO FOCO</h6>
                    </div>
                    <div class="card-body">
                        <?php if ($m['proxima_missao']): ?>
                            <div class="d-flex ai-center gap-md mb-md">
                                <div class="kpi-icon blue"><i class="fas fa-shield-alt"></i></div>
                                <div>
                                    <div class="fw-800" style="font-size: 0.9rem;"><?= $m['proxima_missao']['titulo'] ?></div>
                                    <div class="badge-hc badge-muted" style="font-size: 0.6rem;">PRIORIDADE MÉDIA</div>
                                </div>
                            </div>
                            <div class="text-muted mb-md" style="font-size: 0.75rem;">
                                Tópico: Direito Constitucional<br>
                                <i class="far fa-clock"></i> 50 min estimados
                            </div>
                            <button class="btn-hc btn-ghost btn-sm w-100">VER CAMINHO COMPLETO</button>
                        <?php else: ?>
                            <p class="text-muted text-center py-4">Sem focos agendados.</p>
                        <?php endif; ?>
                    </div>
                </section>

                <!-- DESEMPENHO RECENTE -->
                <section class="card-glass mb-md">
                    <div class="card-header-hc d-flex jc-between ai-center">
                        <h6 class="fw-800 mb-0">DESEMPENHO REAL</h6>
                        <span class="text-neon fw-900">+<?= $m['melhoria'] ?>%</span>
                    </div>
                    <div class="card-body">
                        <div class="mb-md" style="height: 60px; background: rgba(34,197,94,0.05); border-radius: 8px; position: relative; overflow: hidden;">
                             <!-- Gráfico de Evolução Dinâmico -->
                             <svg viewBox="0 0 100 20" style="width: 100%; height: 100%; preserveAspectRatio: none;">
                                <path d="M0,<?= 20 - ($m['taxa_acerto']*0.15) ?> Q25,<?= 20 - ($m['taxa_acerto']*0.18) ?> 50,<?= 20 - ($m['taxa_acerto']*0.2) ?> T100,<?= 20 - ($m['taxa_acerto']*0.22) ?>" fill="none" stroke="var(--neon-green)" stroke-width="2" />
                             </svg>
                        </div>
                        <div class="row text-center">
                            <div class="col-4">
                                <div class="text-neon fw-800"><?= $m['taxa_acerto'] ?>%</div>
                                <div class="text-muted" style="font-size: 0.6rem;">Acertos</div>
                            </div>
                            <div class="col-4">
                                <div class="fw-800"><?= $m['total_questoes'] ?></div>
                                <div class="text-muted" style="font-size: 0.6rem;">Questões</div>
                            </div>
                            <div class="col-4">
                                <div class="fw-800"><?= $m['tempo_formatado'] ?></div>
                                <div class="text-muted" style="font-size: 0.6rem;">Estudo</div>
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
                    <div class="lock-badge">TURBO</div>
                    <div class="d-flex ai-center gap-md">
                        <div class="text-purple"><i class="fas fa-brain"></i></div>
                        <div class="fw-700" style="font-size: 0.8rem;">Scanner de Padrões IA</div>
                    </div>
                </div>
                <div class="card-glass p-3 mb-md premium-lock">
                    <div class="lock-badge">TURBO</div>
                    <div class="d-flex ai-center gap-md">
                        <div class="text-purple"><i class="fas fa-route"></i></div>
                        <div class="fw-700" style="font-size: 0.8rem;">Recalibragem Inteligente</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- DICA DO MENTOR -->
        <footer class="card-glass p-3 mt-lg d-flex ai-center gap-md" style="background: rgba(34,197,94,0.03);">
            <div class="mentor-avatar">
                <div style="width: 40px; height: 40px; border-radius: 50%; border: 2px solid var(--neon-green); background: var(--card-bg); display: flex; align-items: center; justify-content: center; font-size: 1.2rem;">
                    🧠
                </div>
            </div>
            <div style="font-size: 0.8rem;">
                <span class="fw-800 text-neon">DICA DO MENTOR:</span>
                <span class="text-secondary">A constância é o que separa quem tenta de quem conquista. Foque 1% melhor a cada dia.</span>
            </div>
        </footer>
    </main>

    <!-- MODAL DE BOAS-VINDAS / CHAMADA PARA ESTUDO -->
    <?php if (!$m['missao']): ?>
    <div id="modal-welcome-hc" class="modal-hc-overlay" style="display: flex;">
        <div class="modal-hc-content welcome-modal-card">
            <div class="welcome-image-container">
                <img src="<?= APP_URL ?>/assets/img/welcome_mastermind.png" alt="Inicie sua Jornada" class="welcome-img">
                <div class="welcome-overlay-text">
                    <h2 class="fw-900">SUA ESTRATÉGIA ESTÁ VAZIA</h2>
                    <p>O tempo é o único recurso que você não pode recuperar.</p>
                </div>
            </div>
            <div class="welcome-body text-center p-lg">
                <h4 class="fw-800 mb-md">Pronto para virar o jogo?</h4>
                <p class="text-secondary mb-xl">
                    Nossa IA já mapeou os editais mais quentes. <br>
                    Você está a <strong>um clique</strong> de transformar sua rotina em uma máquina de aprovação.
                </p>
                <div class="d-flex flex-column gap-md">
                    <button class="btn-hc btn-neon btn-xl shadow-neon" onclick="location.href='biblioteca.php'">
                        INICIAR JORNADA AGORA <i class="bi bi-lightning-charge-fill"></i>
                    </button>
                    <button class="btn-hc btn-ghost btn-sm" onclick="closeWelcomeModal()">Explorar o painel primeiro</button>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/footer_aluno.php'; ?>

<style>
.mestre-avatar-container {
    display: inline-flex;
    flex-direction: column;
    align-items: center;
    gap: 10px;
    padding: 1rem;
}
.mestre-circle {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--neon-green), #000);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2rem;
    color: white;
    box-shadow: 0 0 20px var(--neon-green-glow);
    border: 2px solid rgba(255,255,255,0.1);
    animation: pulse-mestre 2s infinite ease-in-out;
}
.mestre-label {
    font-size: 0.6rem;
    font-weight: 900;
    color: var(--neon-green);
    letter-spacing: 2px;
    text-shadow: 0 0 5px var(--neon-green-glow);
}
@keyframes pulse-mestre {
    0% { transform: scale(1); box-shadow: 0 0 15px var(--neon-green-glow); }
    50% { transform: scale(1.05); box-shadow: 0 0 30px var(--neon-green-glow); }
    100% { transform: scale(1); box-shadow: 0 0 15px var(--neon-green-glow); }
}

/* Modal de Boas Vindas Estilizado */
.modal-hc-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.7);
    backdrop-filter: blur(15px);
    -webkit-backdrop-filter: blur(15px);
    z-index: 10000;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 2rem;
    animation: fadeInModal 0.4s ease;
}

@keyframes fadeInModal {
    from { opacity: 0; }
    to { opacity: 1; }
}

.modal-hc-content {
    background: var(--bg-card);
    border-radius: var(--radius-xl);
    box-shadow: 0 0 50px rgba(34,197,94,0.2);
    animation: slideUpModal 0.5s cubic-bezier(0.16, 1, 0.3, 1);
}

@keyframes slideUpModal {
    from { transform: translateY(30px); opacity: 0; }
    to { transform: translateY(0); opacity: 1; }
}

.welcome-modal-card {
    max-width: 600px !important;
    padding: 0 !important;
    overflow: hidden;
    border: 1px solid var(--border-neon);
}
.welcome-image-container {
    position: relative;
    height: 250px;
}
.welcome-img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    filter: brightness(0.6);
}
.welcome-overlay-text {
    position: absolute;
    bottom: 20px;
    left: 20px;
    right: 20px;
}
.welcome-overlay-text h2 {
    font-size: 1.5rem;
    color: var(--neon-green);
    margin-bottom: 5px;
}
.welcome-overlay-text p {
    font-size: 0.8rem;
    color: rgba(255,255,255,0.8);
}
</style>

<script>
function closeWelcomeModal() {
    document.getElementById('modal-welcome-hc').style.display = 'none';
}
</script>
