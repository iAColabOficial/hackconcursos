<?php
require_once __DIR__ . '/../config/config.php';
exigirLogin('../login.php');

$db = getDB();
$usuario_id = $_SESSION['usuario_id'];

// Buscar perfil
$perfilQ = $db->prepare("SELECT * FROM perfis_usuario WHERE usuario_id = ?");
$perfilQ->execute([$usuario_id]);
$dados_perfil = $perfilQ->fetch();

$biblioteca_id = $dados_perfil['biblioteca_edital_id'] ?? 0;
$dados_edital = null;

if ($biblioteca_id) {
    $editalQ = $db->prepare("SELECT * FROM biblioteca_editais WHERE id = ?");
    $editalQ->execute([$biblioteca_id]);
    $dados_edital = $editalQ->fetch();
}

// Heurística de Diagnóstico
$perfil_estudo = $_SESSION['perfil_estudo'] ?? 'iniciante';
$score_atual = $perfil_estudo === 'avancado' ? 65 : ($perfil_estudo === 'intermediario' ? 45 : 25);
$meses_estimados = $perfil_estudo === 'avancado' ? 4 : ($perfil_estudo === 'intermediario' ? 8 : 12);

$page_title = 'Resultado da Personalização - HackConcursos';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="dashboard-layout">
  <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>
  <main class="main-content">

    <?php if (!$dados_edital): ?>
    <!-- RESULTADO PERFIL GERAL -->
    <div class="animate__animated animate__zoomIn text-center" style="max-width:800px; margin: 4rem auto;">
        <div style="font-size:5rem; margin-bottom:1.5rem;">🎯</div>
        <h2 style="font-weight:900; font-size:2.5rem; margin-bottom:1rem;">Perfil <span class="text-neon">Hakeado</span> com Sucesso!</h2>
        <p style="font-size:1.2rem; color:var(--text-secondary); margin-bottom:3rem;">
            Seu perfil de estudo <strong><?= ucfirst($perfil_estudo) ?></strong> foi registrado. 
            A IA já sabe que você terá <strong><?= $dados_perfil['horas_dia'] ?? 3 ?>h</strong> por dia para buscar sua vaga.
        </p>

        <div class="card-glass" style="padding:3rem; border: 1px solid var(--border-neon); background: rgba(34,197,94,0.02);">
            <h4 style="font-weight:800; margin-bottom:1.5rem;">PRÓXIMO PASSO: ESCOLHER SEU ALVO</h4>
            <p style="color:var(--text-muted); margin-bottom:2rem;">Para que possamos gerar seu cronograma tático, precisamos saber para qual concurso você está estudando.</p>
            <a href="biblioteca.php" class="btn-hc btn-neon btn-lg shadow-neon" style="padding: 1.2rem 4rem;">
                ESCOLHER MEU CONCURSO <i class="bi bi-arrow-right"></i>
            </a>
        </div>
    </div>

    <?php else: ?>
    <!-- RESULTADO RAIO-X ESPECÍFICO -->
    <div class="page-header text-center animate__animated animate__fadeIn">
      <h2>🔍 Seu Raio-X Estratégico</h2>
      <p>Análise concluída para: <strong class="text-neon"><?= sanitize($dados_edital['nome_concurso']) ?></strong></p>
    </div>

    <div class="grid-3 mb-lg animate__animated animate__fadeInUp">
        <!-- Card: Índice de Aprovação -->
        <div class="card-glass text-center" style="padding:2rem;">
            <div class="kpi-label">Seu IAp Atual</div>
            <div class="kpi-value <?= $score_atual < 50 ? 'text-danger' : 'text-warning' ?>" style="font-size:4rem;"><?= $score_atual ?>%</div>
            <p class="text-muted" style="font-size:0.8rem;">Chance estimada de posse hoje</p>
            <div class="progress-hc mt-sm">
                <div class="progress-bar-fill" style="width: <?= $score_atual ?>%; background: <?= $score_atual < 50 ? 'var(--danger)' : 'var(--warning)' ?>;"></div>
            </div>
        </div>

        <!-- Card: Tempo Estimado -->
        <div class="card-glass text-center" style="padding:2rem;">
            <div class="kpi-label">Tempo para a Posse</div>
            <div class="kpi-value text-blue" style="font-size:4rem;"><?= $meses_estimados ?></div>
            <p class="text-muted" style="font-size:0.8rem;">Meses de estudo tático necessários</p>
            <div style="font-size:2rem; margin-top:1rem;">🗓️</div>
        </div>

        <!-- Card: Nível de Competição -->
        <div class="card-glass text-center" style="padding:2rem;">
            <div class="kpi-label">Status de Competição</div>
            <div class="kpi-value text-warning" style="font-size:2.5rem; margin-top:1rem;">
                <?= $score_atual < 40 ? 'ALERTA' : 'EVOLUINDO' ?>
            </div>
            <p class="text-muted" style="font-size:0.8rem; margin-top:0.5rem;">Sua estratégia atual precisa de ajustes para bater a concorrência.</p>
            <div style="font-size:2rem; margin-top:1rem;">⚠️</div>
        </div>
    </div>

    <!-- O PAYWALL ESTRATÉGICO -->
    <div class="card-glass" style="border: 2px solid var(--neon-green); background: linear-gradient(135deg, rgba(34,197,94,0.05), rgba(0,0,0,0.2));">
        <div class="card-body text-center" style="padding:4rem 2rem;">
            <h2 class="mb-md">Você quer encurtar este caminho?</h2>
            <p class="text-secondary mx-auto" style="max-width:700px; font-size:1.1rem; margin-bottom:3rem;">
                O diagnóstico gratuito revelou que você está estudando de forma desequilibrada. No <strong>Modo Guerra</strong>, nossa IA assume o controle da sua rotina e garante que você estude apenas o que vai cair, aumentando sua assertividade para <strong>98%</strong>.
            </p>

            <div class="grid-2 mb-lg" style="text-align:left; max-width:800px; margin-left:auto; margin-right:auto;">
                <div class="d-flex ai-center gap-md">
                    <i class="bi bi-check-all text-neon" style="font-size:2rem;"></i>
                    <div>
                        <strong style="display:block;">Missão do Dia Dinâmica</strong>
                        <span class="text-muted" style="font-size:0.85rem;">Saiba exatamente o que estudar a cada minuto.</span>
                    </div>
                </div>
                <div class="d-flex ai-center gap-md">
                    <i class="bi bi-check-all text-neon" style="font-size:2rem;"></i>
                    <div>
                        <strong style="display:block;">Ajuste Tático IA</strong>
                        <span class="text-muted" style="font-size:0.85rem;">Recalculamos seu plano sempre que você errar.</span>
                    </div>
                </div>
                <div class="d-flex ai-center gap-md">
                    <i class="bi bi-check-all text-neon" style="font-size:2rem;"></i>
                    <div>
                        <strong style="display:block;">Mentor IA 24/7</strong>
                        <span class="text-muted" style="font-size:0.85rem;">Tire dúvidas ilimitadas sobre qualquer matéria.</span>
                    </div>
                </div>
                <div class="d-flex ai-center gap-md">
                    <i class="bi bi-check-all text-neon" style="font-size:2rem;"></i>
                    <div>
                        <strong style="display:block;">Simulados com IA</strong>
                        <span class="text-muted" style="font-size:0.85rem;">Questões inéditas baseadas no seu ponto fraco.</span>
                    </div>
                </div>
            </div>

            <div class="mt-lg">
                <a href="../planos.php" class="btn-hc btn-neon btn-xl btn-pulse" style="padding: 1.5rem 5rem; font-size: 1.3rem;">
                    ATIVAR MODO GUERRA AGORA
                </a>
                <p class="mt-md text-muted" style="font-size:0.8rem;">Pague apenas R$ 97/mês. Cancele quando quiser.</p>
            </div>
        </div>
    </div>

    <div class="text-center mt-lg">
        <a href="../controllers/gerar_plano.php" class="text-muted" style="font-size:0.9rem;">Continuar com o plano gratuito limitado</a>
    </div>
    <?php endif; ?>

  </main>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
