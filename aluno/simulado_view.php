<?php
require_once __DIR__ . '/../config/config.php';
exigirLogin('../public/login.php');

$db  = getDB();
$uid = (int)$_SESSION['usuario_id'];
require_once __DIR__ . '/../classes/GatilhoEngine.php';
$simuladoId = (int)($_GET['id'] ?? 0);

if (!$simuladoId) {
    flashMsg('danger', 'Simulado não encontrado.');
    redirect('simulados.php');
}

// Buscar cabeçalho do simulado
$simQ = $db->prepare("
    SELECT s.*, c.nome AS cargo_nome, e.nome_concurso 
    FROM simulados s
    JOIN cargos c ON c.id = s.cargo_id
    JOIN editais e ON e.id = c.edital_id
    WHERE s.id = ? AND s.usuario_id = ?
");
$simQ->execute([$simuladoId, $uid]);
$simulado = $simQ->fetch();

if (!$simulado) {
    flashMsg('danger', 'Acesso negado ou simulado inexistente.');
    redirect('simulados.php');
}

// Buscar questões
$questQ = $db->prepare("
    SELECT q.*, d.nome AS disciplina_nome, r.resposta AS resp_usuario, r.correta, r.posicao_questao
    FROM questoes q
    LEFT JOIN disciplinas d ON d.id = q.disciplina_id
    LEFT JOIN respostas_usuario r ON r.questao_id = q.id AND r.usuario_id = ?
    WHERE q.simulado_id = ?
    ORDER BY q.id ASC
");
$questQ->execute([$uid, $simuladoId]);
$questoes = $questQ->fetchAll();

// Calcular Fatigue Index
$respostasValidas = array_filter($questoes, fn($q) => $q['posicao_questao'] !== null);
usort($respostasValidas, fn($a, $b) => $a['posicao_questao'] <=> $b['posicao_questao']);
$totalResp = count($respostasValidas);

$fatigueIndex = null;
$fatigueAlert = false;
$primeirasPct = 0;
$ultimasPct = 0;
$primeirasTotal = 0;
$ultimasTotal = 0;

if ($totalResp >= 10) { 
    $numG = min(10, (int)ceil($totalResp / 2));
    $primeiras = array_slice($respostasValidas, 0, $numG);
    $ultimas = array_slice($respostasValidas, -$numG);

    $primeirasTotal = count($primeiras);
    $ultimasTotal = count($ultimas);
    
    $primeirasAcertos = array_reduce($primeiras, fn($c, $q) => $c + ($q['correta'] ? 1 : 0), 0);
    $ultimasAcertos = array_reduce($ultimas, fn($c, $q) => $c + ($q['correta'] ? 1 : 0), 0);

    $primeirasPct = round(($primeirasAcertos / max(1,$primeirasTotal)) * 100);
    $ultimasPct = round(($ultimasAcertos / max(1,$ultimasTotal)) * 100);

    $fatigueIndex = $primeirasPct - $ultimasPct;
    if ($fatigueIndex > 20) {
        $fatigueAlert = true;
    }
}

$finalizado = (bool)$simulado['concluido'];

$ofertaGatilho = null;
if ($finalizado) {
    $engineOfertas = new GatilhoEngine($db, $uid);
    $ofertaGatilho = $engineOfertas->verificarGatilhoSimulado($simuladoId);
}

$page_title = $finalizado ? 'Resultado do Simulado' : 'Simulado em Andamento';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="dashboard-layout" style="position:relative;z-index:1;">
  <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>
  <main class="main-content">

    <div class="page-header d-flex jc-between ai-center">
      <div>
        <div class="page-breadcrumb">
            <a href="dashboard.php">Dashboard</a><span class="sep">›</span> 
            <a href="simulados.php">Simulados</a><span class="sep">›</span> 
            <?= $finalizado ? 'Resultado' : 'Execução' ?>
        </div>
        <h2><?= sanitize($simulado['titulo']) ?></h2>
        <p><?= sanitize($simulado['nome_concurso']) ?> · <?= sanitize($simulado['cargo_nome']) ?></p>
      </div>
      
      <?php if (!$finalizado): ?>
      <div class="d-flex ai-center gap-md">
        <div class="card-glass" style="padding:0.5rem 1rem;border-color:var(--accent-blue);">
          <i class="bi bi-stopwatch text-blue"></i> <span id="timer" class="font-mono fw-700">00:00:00</span>
        </div>
        <button class="btn-hc btn-neon" onclick="finalizarSimulado()">
          <i class="bi bi-check-all"></i> Finalizar Agora
        </button>
      </div>
      <?php else: ?>
      <a href="simulados.php" class="btn-hc btn-ghost">
        <i class="bi bi-arrow-left"></i> Voltar ao Histórico
      </a>
      <?php endif; ?>
    </div>

    <?php if ($finalizado): ?>
    <!-- VISÃO DE RESULTADOS -->
    <div class="grid-3 mb-md">
        <div class="kpi-card" style="border-color:var(--neon-green);">
            <div class="kpi-icon neon"><i class="bi bi-check-circle"></i></div>
            <div>
                <div class="kpi-label">Acertos</div>
                <div class="kpi-value text-neon"><?= $simulado['acertos'] ?></div>
            </div>
        </div>
        <div class="kpi-card" style="border-color:var(--danger);">
            <div class="kpi-icon"><i class="bi bi-x-circle text-danger"></i></div>
            <div>
                <div class="kpi-label">Erros</div>
                <div class="kpi-value text-danger"><?= $simulado['erros'] ?></div>
            </div>
        </div>
        <?php 
        $pct = $simulado['total_questoes'] > 0 ? round(($simulado['acertos'] / $simulado['total_questoes']) * 100, 1) : 0;
        ?>
        <div class="kpi-card">
            <div class="kpi-icon blue"><i class="bi bi-graph-up"></i></div>
            <div>
                <div class="kpi-label">Aproveitamento</div>
                <div class="kpi-value text-blue"><?= $pct ?>%</div>
            </div>
        </div>
    </div>

    <?php if ($fatigueIndex !== null): ?>
    <div class="card-glass mb-4" style="background:linear-gradient(90deg, rgba(168,85,247,0.05), rgba(0,0,0,0)); border:1px solid rgba(168,85,247,0.2); padding:1.25rem;">
        <div class="d-flex ai-center gap-md">
            <div style="font-size:2rem;"><?= $fatigueAlert ? '🚨' : '🔋' ?></div>
            <div style="flex:1;">
                <div style="font-weight:800; color:var(--accent-purple); font-size:0.9rem; text-transform:uppercase;">Índice de Fadiga (Fatigue Index)</div>
                <div style="font-size:1rem; margin-top:0.25rem;">
                    Você acertou <strong><?= $primeirasPct ?>%</strong> nas primeiras <?= $primeirasTotal ?> questões e <strong><?= $ultimasPct ?>%</strong> nas últimas <?= $ultimasTotal ?>.
                    <?php if ($fatigueAlert): ?>
                        <br><span class="text-danger fw-700">Queda brusca de desempenho! Você está perdendo foco por cansaço.</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($ofertaGatilho): ?>
    <div class="card-glass mb-4 animate__animated animate__pulse animate__infinite" style="background:linear-gradient(90deg, rgba(245,158,11,0.1), rgba(0,0,0,0)); border:2px solid var(--warning); padding:1.5rem;">
        <div class="d-flex ai-center gap-md">
            <div style="font-size:2.5rem;">🎁</div>
            <div style="flex:1;">
                <div class="badge-hc badge-warning mb-xs">Oferta Especial Desbloqueada</div>
                <h4 class="fw-800 text-warning mb-xs"><?= sanitize($ofertaGatilho['titulo']) ?></h4>
                <div style="font-size:1rem; color:var(--text-secondary);">
                    <?= sanitize($ofertaGatilho['descricao']) ?>
                </div>
            </div>
            <div class="text-right">
                <div class="text-muted" style="text-decoration:line-through; font-size:0.9rem;">De R$ <?= number_format($ofertaGatilho['preco'], 2, ',', '.') ?></div>
                <div class="text-warning fw-900 mb-2" style="font-size:1.5rem;">Por R$ <?= number_format($ofertaGatilho['preco'] - $ofertaGatilho['valor_desconto'], 2, ',', '.') ?></div>
                <a href="<?= sanitize($ofertaGatilho['link_stripe'] ?: 'checkout.php?oferta='.$ofertaGatilho['id']) ?>" class="btn-hc" style="background:var(--warning); color:#000; font-weight:900;">
                    RESGATAR AGORA
                </a>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Relatório por Disciplina (Profissional) -->
    <div class="card-glass mb-lg" style="padding:1.5rem;">
        <h5 class="fw-800 mb-3"><i class="bi bi-bar-chart-fill text-purple"></i> Análise de Desempenho por Disciplina</h5>
        
        <?php
        $statsDisc = [];
        foreach ($questoes as $q) {
            $dNome = $q['disciplina_nome'];
            if (!isset($statsDisc[$dNome])) $statsDisc[$dNome] = ['total' => 0, 'acertos' => 0];
            $statsDisc[$dNome]['total']++;
            if ($q['resp_usuario'] === $q['gabarito']) $statsDisc[$dNome]['acertos']++;
        }

        // Identificar a pior matéria para o Gatilho de Conversão
        $piorMateria = null;
        $menorPercentual = 101;
        foreach ($statsDisc as $nome => $s) {
            $p = round(($s['acertos'] / $s['total']) * 100);
            if ($p < $menorPercentual) {
                $menorPercentual = $p;
                $piorMateria = $nome;
            }
        }

        if ($menorPercentual < 60): ?>
        <!-- GATILHO DE CONVERSÃO: BOOSTER -->
        <div class="card-glass mb-4" style="background:linear-gradient(90deg, rgba(239,68,68,0.05), rgba(0,0,0,0)); border:1px solid rgba(239,68,68,0.2); padding:1.25rem;">
            <div class="d-flex ai-center gap-md">
                <div style="font-size:2rem;">🚨</div>
                <div style="flex:1;">
                    <div style="font-weight:800; color:#ef4444; font-size:0.9rem; text-transform:uppercase;">Alerta de Fraqueza Detectado</div>
                    <div style="font-size:1rem; margin-top:0.25rem;">
                        Seu desempenho em <strong><?= $piorMateria ?></strong> (<?= $menorPercentual ?>%) está abaixo da linha de corte.
                    </div>
                </div>
                <a href="loja.php?tag=acelerador" class="btn-hc" style="background:#ef4444; color:#fff; font-weight:900;">
                    DESBLOQUEAR REFORÇO IA
                </a>
            </div>
        </div>
        <?php endif; ?>

        <div class="grid-2" style="gap:1rem;">
            <?php
            $statsDisc = [];
            foreach ($questoes as $q) {
                $dNome = $q['disciplina_nome'];
                if (!isset($statsDisc[$dNome])) $statsDisc[$dNome] = ['total' => 0, 'acertos' => 0];
                $statsDisc[$dNome]['total']++;
                if ($q['resp_usuario'] === $q['gabarito']) $statsDisc[$dNome]['acertos']++;
            }

            foreach ($statsDisc as $nome => $s): 
                $pDisc = round(($s['acertos'] / $s['total']) * 100);
                $color = $pDisc >= 70 ? 'var(--neon-green)' : ($pDisc >= 50 ? 'var(--warning)' : 'var(--danger)');
            ?>
            <div class="p-3" style="background:rgba(255,255,255,0.02); border:1px solid var(--border-glass); border-radius:var(--radius-md);">
                <div class="d-flex jc-between ai-center mb-2">
                    <span class="fw-700" style="font-size:0.9rem;"><?= sanitize($nome) ?></span>
                    <span class="fw-800" style="color:<?= $color ?>;"><?= $pDisc ?>%</span>
                </div>
                <div class="progress-hc" style="height:6px;">
                    <div class="progress-bar-fill" style="width:<?= $pDisc ?>%; background:<?= $color ?>;"></div>
                </div>
                <div class="mt-2 text-muted" style="font-size:0.75rem;">
                    <?= $s['acertos'] ?> acertos de <?= $s['total'] ?> questões
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Lista de Questões com Gabarito e Explicação -->
    <div style="display:flex;flex-direction:column;gap:1.5rem;">
        <?php foreach ($questoes as $idx => $q): 
            $correta = $q['resp_usuario'] === $q['gabarito'];
            $classeStatus = $correta ? 'status-correta' : 'status-errada';
        ?>
        <div class="card-glass q-box <?= $classeStatus ?>">
            <div class="card-header-hc d-flex jc-between">
                <div>
                   <span class="badge-hc badge-blue">Questão <?= $idx + 1 ?></span>
                   <span class="badge-hc badge-muted"><?= sanitize($q['disciplina_nome']) ?></span>
                </div>
                <?php if ($correta): ?>
                    <span class="text-neon fw-700"><i class="bi bi-check-circle-fill"></i> Você acertou</span>
                <?php else: ?>
                    <span class="text-danger fw-700"><i class="bi bi-x-circle-fill"></i> Você errou</span>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <div class="enunciado mb-md" style="font-size:1.1rem;font-weight:500;">
                    <?= nl2br(sanitize($q['enunciado'])) ?>
                </div>

                <div class="alternativas-list">
                    <?php foreach(['A','B','C','D','E'] as $alt): 
                        $key = 'alternativa_' . strtolower($alt);
                        if (empty($q[$key])) continue;
                        
                        $isGabarito = ($alt === $q['gabarito']);
                        $isSua      = ($alt === $q['resp_usuario']);
                        
                        $style = "";
                        if ($isGabarito) $style = "border-color:var(--neon-green); background:rgba(34,197,94,0.1);";
                        elseif ($isSua && !$isGabarito) $style = "border-color:var(--danger); background:rgba(239,68,68,0.1);";
                    ?>
                    <div class="alt-item" style="padding:1rem; border:1px solid var(--border-glass); border-radius:var(--radius-md); margin-bottom:0.5rem; <?= $style ?>">
                        <strong><?= $alt ?>)</strong> <?= sanitize($q[$key]) ?>
                        <?php if($isGabarito): ?> <span class="badge-hc badge-neon float-end" style="float:right;">Gabarito</span> <?php endif; ?>
                        <?php if($isSua && !$isGabarito): ?> <span class="badge-hc badge-danger float-end" style="float:right;">Sua Resposta</span> <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>

                <?php if ($q['explicacao']): ?>
                <div class="mt-md" style="padding:1rem; background:rgba(255,255,255,0.03); border-radius:var(--radius-md); border-left:4px solid var(--accent-purple);">
                    <div class="fw-700 text-purple mb-xs">🧠 Explicação do Hack</div>
                    <div style="font-size:0.9rem;color:var(--text-secondary);"><?= nl2br(sanitize($q['explicacao'])) ?></div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <?php else: ?>
    <!-- VISÃO DE EXECUÇÃO -->
    <div id="simulado-container" style="max-width:900px;margin:0 auto;">
        
        <!-- Cartão Resposta / Navegador (Modo Guerra) -->
        <div class="card-glass mb-md" style="padding:1rem;">
            <div class="d-flex jc-between ai-center mb-3">
                <span style="font-size:0.85rem;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.05em;">Navegação da Prova</span>
                <span id="progress-text" class="badge-hc badge-blue" style="font-family:var(--font-mono);"><?= count($questoes) ?> questões</span>
            </div>
            <div id="questoes-nav" style="display:flex; flex-wrap:wrap; gap:0.5rem;">
                <?php foreach ($questoes as $idx => $q): ?>
                    <button class="nav-q-btn btn-hc btn-ghost btn-sm" 
                            id="nav-btn-<?= $idx ?>" 
                            onclick="showQuestion(<?= $idx ?>)"
                            style="width:36px; height:36px; padding:0; justify-content:center; border-color:var(--border-glass);">
                        <?= $idx + 1 ?>
                    </button>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Progress bar discreta -->
        <div class="mb-lg">
            <div class="progress-hc" style="height:4px; background:rgba(255,255,255,0.05);">
                <div id="progress-bar" class="progress-bar-fill progress-blue" style="width:0%; transition: width 0.4s ease;"></div>
            </div>
        </div>

        <div id="questions-wrapper">
            <?php foreach ($questoes as $idx => $q): ?>
            <div class="card-glass q-card" id="q-card-<?= $idx ?>" style="display: <?= $idx === 0 ? 'block' : 'none' ?>;">
                <div class="card-header-hc">
                    <span class="badge-hc badge-blue">QUESTÃO <?= $idx + 1 ?></span>
                    <span class="badge-hc badge-muted"><?= sanitize($q['disciplina_nome']) ?></span>
                </div>
                <div class="card-body">
                    <div class="enunciado mb-lg" style="font-size:1.15rem;font-weight:500;line-height:1.5;">
                        <?= nl2br(sanitize($q['enunciado'])) ?>
                    </div>

                    <div class="alternativas-exec">
                        <?php foreach(['A','B','C','D','E'] as $alt): 
                            $key = 'alternativa_' . strtolower($alt);
                            if (empty($q[$key])) continue;
                        ?>
                        <div class="alt-btn" onclick="responderQuestao(<?= $q['id'] ?>, '<?= $alt ?>', <?= $idx ?>)" id="alt-<?= $q['id'] ?>-<?= $alt ?>">
                            <span class="alt-letter"><?= $alt ?></span>
                            <span class="alt-text"><?= sanitize($q[$key]) ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="card-footer d-flex jc-between" style="padding:1rem 1.5rem; border-top:1px solid var(--border-glass);">
                    <button class="btn-hc btn-ghost" onclick="prevQuestion(<?= $idx ?>)" <?= $idx === 0 ? 'disabled' : '' ?>>
                        <i class="bi bi-chevron-left"></i> Anterior
                    </button>
                    <div>
                        <?php if ($idx < count($questoes) - 1): ?>
                        <button class="btn-hc btn-primary-hc" onclick="nextQuestion(<?= $idx ?>)">
                            Próxima <i class="bi bi-chevron-right"></i>
                        </button>
                        <?php else: ?>
                        <button class="btn-hc btn-neon" onclick="finalizarSimulado()">
                            Finalizar Simulado <i class="bi bi-check-all"></i>
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <style>
        .alternativas-exec { display:flex; flex-direction:column; gap:0.85rem; }
        .alt-btn {
            padding:1.25rem 1.5rem;
            background:rgba(255,255,255,0.02);
            border:1px solid var(--border-glass);
            border-radius:var(--radius-md);
            cursor:pointer;
            transition:all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            display:flex;
            align-items:center;
            gap:1.25rem;
        }
        .alt-btn:hover { background:rgba(59,130,246,0.06); border-color:rgba(59,130,246,0.3); transform: translateX(5px); }
        .alt-btn.selected { background:rgba(59,130,246,0.12); border-color:var(--accent-blue); box-shadow:0 0 20px rgba(59,130,246,0.15); }
        .alt-letter {
            width:34px; height:34px;
            display:flex; align-items:center; justify-content:center;
            background:rgba(255,255,255,0.05);
            border:1px solid var(--border-glass);
            border-radius:50%;
            font-weight:800;
            color:var(--text-secondary);
            flex-shrink:0;
        }
        .alt-btn.selected .alt-letter { background:var(--accent-blue); color:#fff; border-color:var(--accent-blue); }
        
        .nav-q-btn.answered { background:var(--accent-blue) !important; color:#fff !important; border-color:var(--accent-blue) !important; box-shadow:0 0 10px rgba(59,130,246,0.3); }
        .nav-q-btn.active-q { border:2px solid var(--neon-green) !important; color:var(--neon-green) !important; }

        .status-correta { border-left:6px solid var(--neon-green) !important; }
        .status-errada { border-left:6px solid var(--danger) !important; }
    </style>

    <script>
    let startTime = Date.now();
    let timerInterval;
    let totalQuestions = <?= count($questoes) ?>;
    let answeredQuestions = new Set();

    function updateTimer() {
        const diff = Math.floor((Date.now() - startTime) / 1000);
        const h = Math.floor(diff / 3600).toString().padStart(2, '0');
        const m = Math.floor((diff % 3600) / 60).toString().padStart(2, '0');
        const s = (diff % 60).toString().padStart(2, '0');
        document.getElementById('timer').textContent = `${h}:${m}:${s}`;
    }

    timerInterval = setInterval(updateTimer, 1000);

    function showQuestion(idx) {
        document.querySelectorAll('.q-card').forEach(c => c.style.display = 'none');
        document.querySelectorAll('.nav-q-btn').forEach(b => b.classList.remove('active-q'));
        
        document.getElementById('q-card-' + idx).style.display = 'block';
        document.getElementById('nav-btn-' + idx).classList.add('active-q');
    }

    // Inicializar o primeiro botão como ativo
    document.addEventListener('DOMContentLoaded', () => {
        if(totalQuestions > 0) document.getElementById('nav-btn-0').classList.add('active-q');
    });

    function nextQuestion(idx) {
        if (idx < totalQuestions - 1) showQuestion(idx + 1);
    }

    function prevQuestion(idx) {
        if (idx > 0) showQuestion(idx - 1);
    }

    async function responderQuestao(qId, resp, idx) {
        // UI feedback
        document.querySelectorAll('#q-card-' + idx + ' .alt-btn').forEach(b => b.classList.remove('selected'));
        document.getElementById('alt-' + qId + '-' + resp).classList.add('selected');

        answeredQuestions.add(qId);
        document.getElementById('nav-btn-' + idx).classList.add('answered');
        updateProgress();

        try {
            await fetch('<?= APP_URL ?>/controllers/simulado_action.php?action=responder', {
                method: 'POST',
                headers: { 
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': window.csrfToken || ''
                },
                body: JSON.stringify({
                    simulado_id: <?= $simuladoId ?>,
                    questao_id: qId,
                    resposta: resp
                })
            });
        } catch (e) {}
    }

    function updateProgress() {
        const count = answeredQuestions.size;
        const pct = (count / totalQuestions) * 100;
        document.getElementById('progress-bar').style.width = pct + '%';
        document.getElementById('progress-text').textContent = `${count} de ${totalQuestions}`;
    }

    async function finalizarSimulado() {
        if (!confirm('Deseja finalizar o simulado agora?')) return;
        
        clearInterval(timerInterval);
        const tempoSegundos = Math.floor((Date.now() - startTime) / 1000);
        const tempoMinutos  = Math.ceil(tempoSegundos / 60);

        try {
            const r = await fetch('<?= APP_URL ?>/controllers/simulado_action.php?action=finalizar', {
                method: 'POST',
                headers: { 
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': window.csrfToken || ''
                },
                body: JSON.stringify({
                    simulado_id: <?= $simuladoId ?>,
                    tempo_minutos: tempoMinutos
                })
            });
            const res = await r.json();
            if (res.ok) {
                window.location.reload(); // Vai recarregar como finalizado
            }
        } catch (e) {
            alert('Erro ao finalizar. Tente novamente.');
        }
    }
    </script>
    <?php endif; ?>

  </main>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
