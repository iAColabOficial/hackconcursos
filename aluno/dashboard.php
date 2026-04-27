<?php
require_once __DIR__ . '/../config/config.php';
exigirLogin('../login.php');

$db = getDB();
$uid = (int)$_SESSION['usuario_id'];

// ---- Dados do usuário ----
$usrQ = $db->prepare("SELECT u.nome, u.plano, u.token_saldo, p.sequencia_dias, p.total_horas, p.horas_dia
                       FROM usuarios u
                       LEFT JOIN perfis_usuario p ON p.usuario_id = u.id
                       WHERE u.id = ?");
$usrQ->execute([$uid]);
$usr = $usrQ->fetch() ?: ['nome'=>$_SESSION['usuario_nome']??'Aluno','plano'=>'free','token_saldo'=>0,'sequencia_dias'=>0,'total_horas'=>0,'horas_dia'=>2];

// ---- Todos os Editais do Usuário ----
$todosEditaisQ = $db->prepare("SELECT e.id, e.nome_concurso, e.data_prova FROM editais e WHERE e.usuario_id = ? AND e.ativo = 1 ORDER BY e.criado_em DESC");
$todosEditaisQ->execute([$uid]);
$todosEditais = $todosEditaisQ->fetchAll();

// ---- Edital Selecionado (ID via GET ou o mais recente) ----
$editalId = isset($_GET['edital_id']) ? (int)$_GET['edital_id'] : ($todosEditais[0]['id'] ?? 0);

$editQ = $db->prepare("SELECT e.*, c.id as cargo_id, c.nome as cargo_nome FROM editais e
                        LEFT JOIN cargos c ON c.edital_id = e.id AND c.selecionado = 1
                        WHERE e.id = ? AND e.usuario_id = ? AND e.ativo = 1");
$editQ->execute([$editalId, $uid]);
$edital = $editQ->fetch();

// ---- Estatísticas de progresso do edital selecionado ----
$totalTarefas = 0; $concluidas = 0;
if ($edital) {
    $tQ = $db->prepare("SELECT COUNT(*) as total, SUM(concluida) as feitas FROM tarefas_estudo t
                         JOIN planos_estudo p ON p.id = t.plano_id 
                         WHERE p.usuario_id = ? AND p.cargo_id = ?");
    $tQ->execute([$uid, $edital['cargo_id']]);
    $stats = $tQ->fetch();
    $totalTarefas = (int)($stats['total'] ?? 0);
    $concluidas   = (int)($stats['feitas'] ?? 0);
}
$cobertura = $totalTarefas > 0 ? round(($concluidas / $totalTarefas) * 100, 1) : 0;

// ---- Tarefas de hoje do edital selecionado ----
$hoje = date('Y-m-d');
$tarefasHoje = [];
if ($edital) {
    $hojeTQ = $db->prepare("
        SELECT t.*, d.nome AS disciplina_nome, tp.nome AS topico_nome
        FROM tarefas_estudo t
        JOIN planos_estudo pl ON pl.id = t.plano_id
        JOIN disciplinas d ON d.id = t.disciplina_id
        LEFT JOIN topicos_edital tp ON tp.id = t.topico_id
        WHERE pl.usuario_id = ? AND pl.cargo_id = ? AND t.data_prevista = ?
        ORDER BY t.concluida ASC, t.id ASC LIMIT 8
    ");
    $hojeTQ->execute([$uid, $edital['cargo_id'], $hoje]);
    $tarefasHoje = $hojeTQ->fetchAll();
}

// ---- Progresso por disciplina para o resumo inferior ----
$progDisc = [];
if ($edital) {
    $pdQ = $db->prepare("
        SELECT d.nome, 
               (SUM(t.concluida) * 100.0 / COUNT(t.id)) as percentual_concluido
        FROM disciplinas d
        JOIN tarefas_estudo t ON t.disciplina_id = d.id
        JOIN planos_estudo pl ON pl.id = t.plano_id
        WHERE pl.usuario_id = ? AND pl.cargo_id = ?
        GROUP BY d.id
    ");
    $pdQ->execute([$uid, $edital['cargo_id']]);
    $progDisc = $pdQ->fetchAll();
}

// ---- Ranking (Top 5 usuários por horas totais) ----
$rankQ = $db->query("SELECT u.nome, u.avatar, COALESCE(p.total_horas,0) AS horas
                      FROM usuarios u LEFT JOIN perfis_usuario p ON p.usuario_id = u.id
                      WHERE u.perfil='aluno' ORDER BY horas DESC LIMIT 5");
$ranking = $rankQ->fetchAll();

// ---- Próxima prova ----
$diasProva = null;
if ($edital && $edital['data_prova']) {
    $diff = (new DateTime($edital['data_prova']))->diff(new DateTime());
    $diasProva = max(0, $diff->days);
}

// ---- Tempo estudado ----
$hTotal = (float)($usr['total_horas'] ?? 0);
$horas  = floor($hTotal);
$mins   = round(($hTotal - $horas) * 60);

// ---- Verificar se há tarefas atrasadas para o gatilho de IA ----
$temAtrasadas = false;
if ($edital) {
    $atrasQ = $db->prepare("SELECT COUNT(*) FROM tarefas_estudo t JOIN planos_estudo p ON p.id = t.plano_id WHERE p.usuario_id = ? AND p.cargo_id = ? AND t.atrasada = 1 AND t.concluida = 0");
    $atrasQ->execute([$uid, $edital['cargo_id']]);
    $temAtrasadas = $atrasQ->fetchColumn() > 0;
}

// ---- Análise de Risco Estratégico ----
require_once __DIR__ . '/../classes/StudyPlanner.php';
$planner = new StudyPlanner();
$risco = $planner->getAnaliseRisco($uid);

$page_title = 'Dashboard';
$page_desc  = 'Seu painel de estudos personalizado';
require_once __DIR__ . '/../includes/header.php';
?>
<style>
    .blur-text { filter: blur(8px); user-select: none; pointer-events: none; }
    .locked-card { position: relative; overflow: hidden; }
    .locked-overlay {
        position: absolute; inset: 0; background: rgba(0,0,0,0.4);
        backdrop-filter: blur(4px); display: flex; flex-direction: column;
        align-items: center; justify-content: center; z-index: 10;
        transition: all 0.3s; cursor: pointer; border-radius: inherit;
    }
    .locked-overlay:hover { background: rgba(0,0,0,0.6); }
    .btn-upgrade-sm {
        background: var(--neon-green); color: #000; font-size: 0.7rem;
        font-weight: 800; padding: 0.4rem 0.8rem; border-radius: 100px;
        text-decoration: none; margin-top: 0.5rem; text-transform: uppercase;
    }
    .alert-ia {
        background: linear-gradient(90deg, rgba(239, 68, 68, 0.15), rgba(245, 158, 11, 0.15));
        border: 1px solid rgba(239, 68, 68, 0.3); border-radius: var(--radius-md);
        padding: 1rem 1.5rem; display: flex; align-items: center; gap: 1rem;
        margin-bottom: 1.5rem; animation: pulse-border 2s infinite;
    }
    @keyframes pulse-border {
        0% { border-color: rgba(239, 68, 68, 0.3); }
        50% { border-color: rgba(239, 68, 68, 0.8); }
        100% { border-color: rgba(239, 68, 68, 0.3); }
    }

    /* Estilos de Risco e Energia */
    .risk-banner {
        background: rgba(0,0,0,0.4); border: 1px solid <?= $risco['cor'] ?>;
        border-radius: var(--radius-lg); padding: 1.5rem; margin-bottom: 2rem;
        display: flex; align-items: center; gap: 2rem; position: relative;
        overflow: hidden; box-shadow: 0 0 20px <?= $risco['cor'] ?>20;
    }
    .risk-banner::before {
        content: ""; position: absolute; top: 0; left: 0; width: 4px; height: 100%;
        background: <?= $risco['cor'] ?>;
    }
    .risk-indicator {
        width: 60px; height: 60px; border-radius: 50%; border: 4px solid <?= $risco['cor'] ?>;
        display: flex; align-items: center; justify-content: center; font-size: 1.5rem;
        color: <?= $risco['cor'] ?>; flex-shrink: 0;
        animation: pulse-risk 2s infinite;
    }
    @keyframes pulse-risk {
        0% { transform: scale(1); opacity: 1; }
        50% { transform: scale(1.1); opacity: 0.7; }
        100% { transform: scale(1); opacity: 1; }
    }
    .energy-badge {
        background: linear-gradient(135deg, #fbbf24, #d97706);
        color: #000; font-weight: 900; padding: 0.3rem 0.8rem;
        border-radius: 100px; font-size: 0.75rem; text-transform: uppercase;
        display: flex; align-items: center; gap: 0.4rem; box-shadow: 0 0 15px rgba(251, 191, 36, 0.4);
    }

    /* --- MODO GUERRA EXCLUSIVO --- */
    .modo-guerra-active {
        background: radial-gradient(circle at 50% 0%, rgba(34, 197, 94, 0.05) 0%, transparent 70%);
    }
    .modo-guerra-active .kpi-card {
        border-color: rgba(34, 197, 94, 0.2);
        box-shadow: 0 10px 30px rgba(0,0,0,0.5), inset 0 0 15px rgba(34,197,94,0.02);
    }
    .modo-guerra-active .kpi-value {
        text-shadow: 0 0 15px var(--neon-green-glow);
        letter-spacing: -1px;
    }
    .modo-guerra-active .sidebar-hc {
        border-right: 1px solid rgba(34, 197, 94, 0.15);
    }
    .modo-guerra-active .card-glass {
        border-color: rgba(255,255,255,0.1);
        background: rgba(10, 10, 10, 0.8);
    }
    .modo-guerra-active .badge-neon {
        animation: neon-pulse 1.5s infinite alternate;
    }
    @keyframes neon-pulse {
        from { box-shadow: 0 0 5px var(--neon-green); }
        to { box-shadow: 0 0 15px var(--neon-green); }
    }
    
    /* Overlay de scanner sutil */
    .modo-guerra-active::after {
        content: "";
        position: fixed; top: 0; left: 0; width: 100%; height: 100%;
        background: linear-gradient(rgba(18, 16, 16, 0) 50%, rgba(0, 0, 0, 0.1) 50%), 
                    linear-gradient(90deg, rgba(255, 0, 0, 0.02), rgba(0, 255, 0, 0.01), rgba(0, 0, 255, 0.02));
        background-size: 100% 4px, 3px 100%;
        pointer-events: none; z-index: 9999; opacity: 0.1;
    }
</style>

<!-- ====== LAYOUT ====== -->
<div class="dashboard-layout <?= $usr['plano'] === 'premium' ? 'modo-guerra-active' : '' ?>" style="position:relative;z-index:1;">

  <!-- SIDEBAR -->
  <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

  <!-- MAIN -->
  <main class="main-content">

    <!-- TOP BAR -->
    <div class="d-flex ai-center jc-between mb-md" style="flex-wrap:wrap;gap:1rem;">
      <div>
        <div style="font-size:1.65rem;font-weight:900;letter-spacing:-0.02em; display:flex; align-items:center; gap:0.75rem;">
          Bora evoluir, <?= explode(' ', sanitize($usr['nome']))[0] ?>! <i class="bi bi-rocket-takeoff text-neon" style="font-size:1.4rem; filter: drop-shadow(0 0 5px var(--neon-green-glow));"></i>
          <span class="badge-hc <?= $usr['plano'] === 'premium' ? 'badge-neon' : 'badge-blue' ?>" style="font-size:0.7rem; padding:0.2rem 0.6rem;">
            <?= $usr['plano'] === 'premium' ? 'MODO GUERRA' : 'PLANO FREE' ?>
          </span>
          <?php if ($usr['plano'] !== 'premium'): ?>
            <a href="meu_plano.php" class="energy-badge" style="text-decoration:none;">
              <i class="bi bi-lightning-charge-fill"></i> <?= $usr['token_saldo'] ?> ENERGIA
            </a>
          <?php endif; ?>
        </div>
        <div style="color:var(--text-secondary);font-size:0.9rem;margin-top:0.2rem;">
          Seu sucesso é construído todos os dias. Foco total no seu objetivo!
        </div>
      </div>
      <div class="d-flex ai-center gap-md">
        <!-- Seletor de Planos -->
        <?php if (count($todosEditais) > 1): ?>
          <select class="form-control-hc btn-sm" onchange="location.href='?edital_id=' + this.value" style="width: auto; height: 38px; background: rgba(255,255,255,0.05); color: var(--text-primary); border: 1px solid var(--border-glass);">
            <?php foreach ($todosEditais as $te): ?>
              <option value="<?= $te['id'] ?>" <?= $editalId == $te['id'] ? 'selected' : '' ?>>
                <i class="bi bi-bullseye text-neon"></i> <?= sanitize($te['nome_concurso']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        <?php endif; ?>
        
        <a href="upload_edital.php" class="btn-hc btn-primary-hc shadow-neon">
          <i class="bi bi-plus-lg"></i> Novo Edital
        </a>
      </div>
    </div>

    <!-- RISK BANNER (SISTEMA DE PRESSÃO) -->
    <div class="risk-banner" style="<?= empty($risco['dias_restantes']) ? 'border-style: dashed; opacity: 0.8;' : '' ?>">
        <div class="risk-indicator">
            <i class="bi <?= empty($risco['dias_restantes']) ? 'bi-plus-circle' : 'bi-exclamation-triangle-fill' ?>"></i>
        </div>
        <div style="flex:1;">
            <div style="font-size:0.75rem; font-weight:800; color:var(--text-muted); text-transform:uppercase; letter-spacing:1px; margin-bottom:0.25rem;">
                Status de Prontidão Estratégica
            </div>
            <div style="font-size:1.1rem; font-weight:800; color:<?= $risco['cor'] ?>; margin-bottom:0.25rem;">
                <?= $risco['msg'] ?>
            </div>
            <?php if (!empty($risco['dias_restantes'])): ?>
            <div style="font-size:0.85rem; color:var(--text-secondary);">
                Dias até a prova: <strong class="text-white"><?= $risco['dias_restantes'] ?></strong> | 
                Capacidade necessária: <strong class="text-white"><?= $risco['horas_necessarias_dia'] ?>h/dia</strong>
            </div>
            <?php else: ?>
            <div style="font-size:0.85rem; color:var(--text-secondary);">
                Ative seu edital para que a IA calcule sua probabilidade de aprovação.
            </div>
            <?php endif; ?>
        </div>
        <div style="text-align:right;">
            <?php if (empty($risco['dias_restantes'])): ?>
                <a href="biblioteca.php" class="btn-hc btn-sm" style="background:var(--neon-green); color:#000; font-weight:900;">
                    ESCOLHER CONCURSO
                </a>
            <?php else: ?>
                <a href="chat_edital.php?acao=reorganizar" class="btn-hc btn-sm" style="background:<?= $risco['cor'] ?>; color:#000; font-weight:900;">
                    CORRIGIR ROTA AGORA
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- KPI CARDS -->
    <div class="grid-4 mb-md">
      <!-- Cobertura -->
      <div class="kpi-card">
        <div class="kpi-icon blue"><i class="bi bi-book-fill" style="color:var(--accent-blue)"></i></div>
        <div>
          <div class="kpi-label">Cobertura do Edital</div>
          <div class="kpi-value text-blue"><?= $cobertura ?>%</div>
          <div class="kpi-sub"><?= $concluidas ?> de <?= $totalTarefas ?> tópicos concluídos</div>
        </div>
      </div>
      <!-- Horas -->
      <div class="kpi-card">
        <div class="kpi-icon neon"><i class="bi bi-clock-fill" style="color:var(--neon-green)"></i></div>
        <div>
          <div class="kpi-label">Horas Estudadas</div>
          <div class="kpi-value text-neon"><?= $horas ?>h <?= str_pad($mins,2,'0',STR_PAD_LEFT) ?>m</div>
          <div class="kpi-sub"><i class="bi bi-arrow-up-circle-fill text-neon"></i> +<?= number_format($usr['horas_dia'] ?? 2, 1) ?>h esta semana</div>
        </div>
      </div>
      <!-- Sequência -->
      <div class="kpi-card">
        <div class="kpi-icon warn"><i class="bi bi-fire" style="color:var(--warning)"></i></div>
        <div>
          <div class="kpi-label">Sequência (dias)</div>
          <div class="kpi-value" style="color:var(--warning)"><?= (int)($usr['sequencia_dias'] ?? 0) ?> dias</div>
          <div class="kpi-sub">Mantenha o ritmo! 🔥</div>
        </div>
      </div>
      <!-- Índice de Aprovação IA -->
      <div class="kpi-card locked-card">
        <div class="kpi-icon purple"><i class="bi bi-cpu-fill" style="color:var(--accent-purple)"></i></div>
        <div>
          <div class="kpi-label">Índice de Aprovação IA</div>
          <?php if ($usr['plano'] === 'premium' || $usr['plano'] === 'basico'): ?>
            <div class="kpi-value text-purple"><?= min(98, $cobertura + 12) ?>%</div>
            <div class="kpi-sub">Probabilidade baseada no seu ritmo</div>
          <?php else: ?>
            <div class="kpi-value text-purple blur-text">85.4%</div>
            <div class="locked-overlay" onclick="location.href='meu_plano.php'">
               <i class="bi bi-lock-fill mb-sm"></i>
               <span style="font-size:0.7rem; font-weight:700;">BLOQUEADO</span>
               <a href="meu_plano.php" class="btn-upgrade-sm">Calcular com IA</a>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <?php if ($temAtrasadas): ?>
      <div class="alert-ia">
        <div style="font-size:1.5rem;">⚠️</div>
        <div style="flex:1;">
          <div style="font-weight:800; font-size:0.95rem; color: #fca5a5;">Sua meta está em risco!</div>
          <div style="font-size:0.85rem; color: var(--text-secondary);">Identificamos tarefas atrasadas que podem comprometer sua aprovação.</div>
        </div>
        <a href="chat_edital.php?acao=reorganizar" class="btn-hc btn-neon btn-sm" style="background: #ef4444; color: white;">
          <i class="bi bi-magic"></i> RECALCULAR ROTA
        </a>
      </div>
    <?php endif; ?>

    <!-- CONTEÚDO PRINCIPAL: plano + progresso | painel direito -->
    <div class="grid-2" style="grid-template-columns: 1fr 320px; gap: 1.5rem; align-items:start;">

      <!-- COLUNA ESQUERDA -->
      <div>

        <!-- HOJE - PLANO DE ESTUDOS -->
        <div class="card-glass mb-md">
          <div class="card-header-hc" style="border-color:var(--border-glass);">
            <div class="d-flex ai-center gap-md flex-1">
              <span class="badge-hc badge-neon" style="font-size:0.7rem;">HOJE</span>
              <h5>Seu Plano de Estudos</h5>
            </div>
            <a href="plano_estudos.php" style="font-size:0.8rem;color:var(--neon-green);font-weight:600;white-space:nowrap;">
              Ver Plano Completo →
            </a>
          </div>
          <div class="card-body" style="padding:1rem 1.5rem;">
            <?php if (empty($tarefasHoje)): ?>
              <div class="text-center" style="padding:3rem 1rem;">
                <?php if ($edital): ?>
                  <div class="animate__animated animate__zoomIn">
                    <div style="margin-bottom:1rem;"><i class="bi bi-trophy text-neon" style="font-size:3rem;"></i></div>
                    <h5 class="fw-900 mb-2">Tudo em dia para hoje!</h5>
                    <p class="text-secondary mx-auto" style="max-width:300px; font-size:0.9rem;">
                      Você concluiu suas metas ou não há tarefas para este plano hoje no <strong><?= sanitize($edital['nome_concurso']) ?></strong>.
                    </p>
                    <div class="mt-4 p-3 card-glass" style="background: rgba(34,197,94,0.05); border-color: rgba(34,197,94,0.2);">
                        <div class="d-flex jc-between mb-2">
                           <span class="text-muted small">Progresso do Concurso</span>
                           <span class="text-neon fw-700"><?= $cobertura ?>%</span>
                        </div>
                        <div class="progress-hc" style="height: 8px;">
                            <div class="progress-bar-fill" style="width: <?= $cobertura ?>%"></div>
                        </div>
                    </div>
                    <div class="mt-4">
                        <a href="plano_estudos.php?edital_id=<?= $editalId ?>" class="btn-hc btn-ghost btn-sm">Ver Cronograma Completo</a>
                    </div>
                  </div>
                <?php else: ?>
                  <div style="margin-bottom:0.75rem;"><i class="bi bi-journal-bookmark-fill text-blue" style="font-size:2.5rem;"></i></div>
                  <p>Nenhuma tarefa encontrada. <a href="upload_edital.php" class="text-neon fw-700">Comece cadastrando um edital agora!</a></p>
                <?php endif; ?>
              </div>
            <?php else: ?>
              <div style="display:flex;flex-direction:column;gap:0.5rem;">
                <?php foreach ($tarefasHoje as $i => $t):
                  $done = (bool)$t['concluida'];
                  $tipoIcons = ['estudo'=>'book','revisao_24h'=>'arrow-repeat','revisao_7d'=>'arrow-repeat','revisao_30d'=>'arrow-repeat','simulado'=>'patch-question'];
                  $tipoColors = ['estudo'=>'blue','revisao_24h'=>'neon','revisao_7d'=>'neon','revisao_30d'=>'neon','simulado'=>'purple'];
                  $ic   = $tipoIcons[$t['tipo']] ?? 'book';
                  $clr  = $tipoColors[$t['tipo']] ?? 'blue';
                  $dur  = $t['duracao_minutos'] >= 60 ? floor($t['duracao_minutos']/60).'h '.($t['duracao_minutos']%60 > 0 ? ($t['duracao_minutos']%60).'m' : '') : $t['duracao_minutos'].'m';
                ?>
                <div class="task-item <?= $done?'done':'' ?> <?= $t['atrasada']?'atrasada':'' ?>"
                     onclick="toggleTarefa(<?= $t['id'] ?>, this)"
                     style="display:flex;align-items:center;gap:1rem;">
                  <div class="task-check">
                    <?php if($done): ?><i class="bi bi-check-lg"></i><?php endif; ?>
                  </div>
                  <div class="kpi-icon <?= $clr ?>" style="width:40px;height:40px;font-size:1rem;flex-shrink:0;">
                    <i class="bi bi-<?= $ic ?>"></i>
                  </div>
                  <div style="flex:1;">
                    <div class="task-titulo fw-700" style="font-size:0.92rem;"><?= sanitize($t['disciplina_nome']) ?></div>
                    <div style="font-size:0.78rem;color:var(--text-muted);">
                      <?= $t['topico_nome'] ? sanitize($t['topico_nome']) : ucfirst(str_replace('_',' ',$t['tipo'])) ?>
                    </div>
                  </div>
                  <div style="font-size:0.85rem;color:var(--text-secondary);white-space:nowrap;font-family:var(--font-mono);">
                    <?= $dur ?>
                  </div>
                  <?php if ($i === 0 && !$done): ?>
                  <a href="plano_estudos.php" class="btn-hc btn-neon btn-sm" onclick="event.stopPropagation()">
                    <i class="bi bi-play-fill"></i> ESTUDAR AGORA
                  </a>
                  <?php endif; ?>
                </div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>
        </div>

        <!-- PROGRESSO DO EDITAL -->
        <div class="card-glass">
          <div class="card-header-hc">
            <h5><i class="bi bi-graph-up text-neon"></i> Progresso do Edital</h5>
            <a href="plano_estudos.php" style="font-size:0.8rem;color:var(--neon-green);font-weight:600;margin-left:auto;">Ver Detalhes →</a>
          </div>
          <div class="card-body">
            <div style="display:flex;gap:2rem;align-items:center;">
              <!-- Circular Progress -->
              <div style="flex-shrink:0;text-align:center;">
                <div style="position:relative;width:120px;height:120px;">
                  <canvas id="circularChart" width="120" height="120"></canvas>
                  <div style="position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center;">
                    <span style="font-size:1.4rem;font-weight:900;color:var(--neon-green);"><?= $cobertura ?>%</span>
                    <span style="font-size:0.65rem;color:var(--text-muted);">concluído</span>
                  </div>
                </div>
              </div>
              <!-- Barras por disciplina -->
              <div style="flex:1;">
                <?php if (empty($progDisc)): ?>
                  <p style="color:var(--text-muted);font-size:0.85rem;">Nenhum progresso registrado ainda.</p>
                <?php else: ?>
                  <?php foreach ($progDisc as $pd): ?>
                  <div style="margin-bottom:0.9rem;">
                    <div class="d-flex jc-between ai-center mb-xs" style="margin-bottom:0.3rem;">
                      <span style="font-size:0.85rem;font-weight:600;"><?= sanitize($pd['nome']) ?></span>
                      <span class="text-neon fw-700" style="font-size:0.85rem;font-family:var(--font-mono);"><?= round($pd['percentual_concluido']) ?>%</span>
                    </div>
                    <div class="progress-hc">
                      <div class="progress-bar-fill" style="width:<?= min(100, round($pd['percentual_concluido'])) ?>%"></div>
                    </div>
                  </div>
                  <?php endforeach; ?>
                <?php endif; ?>
              </div>
            </div>
          </div>
        </div>

      </div><!-- /coluna esq -->

      <!-- COLUNA DIREITA -->
      <div style="display:flex;flex-direction:column;gap:1.25rem;">

        <!-- SEQUÊNCIA SEMANAL -->
        <div class="card-glass">
          <div class="card-header-hc">
            <h5><i class="bi bi-lightning-charge-fill text-warning"></i> Sequência</h5>
            <a href="#" style="font-size:0.78rem;color:var(--neon-green);margin-left:auto;">Ver Ranking →</a>
          </div>
          <div class="card-body" style="text-align:center;">
            <div style="font-size:2.75rem;font-weight:900;color:var(--neon-green);line-height:1;">
              <?= (int)($usr['sequencia_dias'] ?? 0) ?>
            </div>
            <div style="font-size:0.8rem;color:var(--text-muted);margin-bottom:1rem;">dias de sequência</div>
            <!-- Dias da semana -->
            <?php
              $dias = ['D','S','T','Q','Q','S','S'];
              $diaSemana = (int)date('w'); // 0=dom
            ?>
            <div style="display:flex;justify-content:center;gap:0.4rem;">
              <?php for ($d=0; $d<7; $d++):
                $ativo = $d <= $diaSemana;
                $hoje2 = $d === $diaSemana;
              ?>
              <div style="display:flex;flex-direction:column;align-items:center;gap:0.3rem;">
                <span style="font-size:0.65rem;color:var(--text-muted);"><?= $dias[$d] ?></span>
                <div style="width:28px;height:28px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:0.75rem;
                  <?= $ativo ? 'background:var(--neon-green);color:#020617;' : 'background:rgba(255,255,255,0.06);color:var(--text-muted);' ?>
                  <?= $hoje2 ? 'box-shadow:0 0 10px rgba(34,197,94,0.6);' : '' ?>
                ">
                  <?= $ativo ? '✓' : '' ?>
                </div>
              </div>
              <?php endfor; ?>
            </div>
            <?php if (($usr['sequencia_dias'] ?? 0) > 0): ?>
            <div style="margin-top:1rem;padding:0.6rem;background:rgba(34,197,94,0.08);border:1px solid rgba(34,197,94,0.15);border-radius:var(--radius-md);font-size:0.8rem;color:var(--neon-green);">
              💪 Você está incrível! Continue assim!
            </div>
            <?php endif; ?>
          </div>
        </div>

        <!-- RADAR DE COMPETITIVIDADE -->
        <div class="card-glass" style="background: linear-gradient(180deg, rgba(34,197,94,0.05) 0%, transparent 100%);">
          <div class="card-header-hc">
            <h5><i class="bi bi-radar text-neon"></i> Radar de Competitividade</h5>
            <span class="badge-hc badge-neon" style="margin-left:auto; font-size:0.6rem;">IA ANALYTICS</span>
          </div>
          <div class="card-body" style="padding:1.25rem;">
            
            <?php
            // Mock de Competitividade (Baseado no progresso real + fator IA)
            $dominioAtual = $cobertura > 0 ? min(95, $cobertura + 15) : 45; // Simulação de domínio
            $notaCorte = 82;
            $statusCor = $dominioAtual >= $notaCorte ? 'var(--neon-green)' : ($dominioAtual >= 65 ? 'var(--warning)' : 'var(--danger)');
            ?>

            <div class="text-center mb-4">
                <div style="font-size:0.7rem; color:var(--text-muted); text-transform:uppercase; letter-spacing:1px; margin-bottom:0.35rem;">Sua Temperatura de Aprovação</div>
                <div style="font-size:2.5rem; font-weight:900; color:<?= $statusCor ?>; line-height:1; filter: drop-shadow(0 0 8px <?= $statusCor ?>44);">
                    <?= $dominioAtual ?>%
                </div>
            </div>

            <!-- Barra de Radar -->
            <div style="position:relative; padding-top:1.5rem; margin-bottom:1.5rem;">
                <!-- Marcador de Nota de Corte -->
                <div style="position:absolute; left:<?= $notaCorte ?>%; top:0; transform:translateX(-50%); text-align:center; z-index:2;">
                    <div style="font-size:0.6rem; font-weight:800; color:var(--text-muted); margin-bottom:4px;">CORTE</div>
                    <div style="width:2px; height:35px; background:rgba(255,255,255,0.2); margin:0 auto; border-left:1px dashed rgba(255,255,255,0.4);"></div>
                </div>

                <div class="progress-hc" style="height:12px; background:rgba(255,255,255,0.05);">
                    <div class="progress-bar-fill" style="width:<?= $dominioAtual ?>%; background:<?= $statusCor ?>; box-shadow:0 0 15px <?= $statusCor ?>66;"></div>
                </div>
                
                <div class="d-flex jc-between mt-2" style="font-size:0.65rem; color:var(--text-muted); font-weight:700;">
                    <span>0%</span>
                    <span>50%</span>
                    <span>100%</span>
                </div>
            </div>

            <!-- Insight Tático -->
            <div style="background:rgba(255,255,255,0.03); border:1px solid var(--border-glass); border-radius:var(--radius-md); padding:0.85rem; display:flex; gap:0.75rem; ai:center;">
                <div style="font-size:1.2rem; color:<?= $statusCor ?>;">
                    <i class="bi <?= $dominioAtual >= $notaCorte ? 'bi-check-all' : 'bi-info-circle-fill' ?>"></i>
                </div>
                <div style="font-size:0.8rem; line-height:1.4;">
                    <?php if ($dominioAtual < $notaCorte): ?>
                        Você está a <strong class="text-white"><?= $notaCorte - $dominioAtual ?>%</strong> da nota de corte projetada. Melhore seu desempenho em <strong>Direito Administrativo</strong>.
                    <?php else: ?>
                        Você está dentro da <strong>Zona de Vagas</strong>. Mantenha a consistência para garantir a nomeação.
                    <?php endif; ?>
                </div>
            </div>

          </div>
        </div>

        <!-- DESAFIO SEMANAL -->
        <div class="card-glass" style="background:linear-gradient(135deg,rgba(245,158,11,0.08),rgba(239,68,68,0.06));border-color:rgba(245,158,11,0.2);">
          <div class="card-body" style="text-align:center;">
            <div style="margin-bottom:0.5rem;"><i class="bi bi-trophy text-neon" style="font-size:2.5rem;"></i></div>
            <div style="font-size:0.7rem;font-weight:700;text-transform:uppercase;letter-spacing:0.1em;color:var(--warning);margin-bottom:0.4rem;">Desafio Semanal</div>
            <div style="font-weight:800;font-size:1rem;margin-bottom:0.25rem;">Estude 15 horas</div>
            <div style="color:var(--warning);font-size:0.85rem;font-weight:700;margin-bottom:1rem;">e ganhe 500 XP!</div>
            <?php $progDesaf = min(100, round(($hTotal / 15) * 100)); ?>
            <div class="progress-hc" style="height:6px;margin-bottom:0.5rem;">
              <div class="progress-bar-fill" style="width:<?= $progDesaf ?>%;background:linear-gradient(90deg,#b45309,var(--warning));"></div>
            </div>
            <div style="font-size:0.78rem;color:var(--text-muted);"><?= number_format($hTotal,1,'.',',') ?>h / 15h</div>
          </div>
        </div>

      </div><!-- /col direita -->
    </div><!-- /grid -->

  </main>
</div>

<script>
// ---- Gráficos Circulares (Chart.js) ----
(function(){
  // 1. Donut de Progresso (Inferior)
  const ctxDonut = document.getElementById('donutProgresso');
  if(ctxDonut) {
      new Chart(ctxDonut, {
          type: 'doughnut',
          data: {
              datasets: [{
                  data: [<?= $cobertura ?>, <?= 100 - $cobertura ?>],
                  backgroundColor: ['#22C55E', 'rgba(255,255,255,0.05)'],
                  borderWidth: 0,
                  hoverOffset: 0
              }]
          },
          options: {
              cutout: '80%',
              plugins: { legend: {display:false}, tooltip: {enabled:false} },
              animation: { duration: 1500, easing: 'easeOutQuart' }
          }
      });
  }

  // 2. Círculo de Sequência (Sidebar)
  const ctxSeq = document.getElementById('circleSequence');
  if(ctxSeq) {
      const seq = <?= (int)$usr['sequencia_dias'] ?>;
      const total = 20; // Meta de recorde
      new Chart(ctxSeq, {
          type: 'doughnut',
          data: {
              datasets: [{
                  data: [seq, Math.max(0, total - seq)],
                  backgroundColor: ['#22C55E', 'rgba(255,255,255,0.03)'],
                  borderWidth: 0
              }]
          },
          options: {
              cutout: '85%',
              plugins: { legend: {display:false}, tooltip: {enabled:false} },
              rotation: -90,
              circumference: 360
          }
      });
  }
})();

// ---- Toggle tarefa (AJAX) ----
async function toggleTarefa(id, el) {
  const done = el.classList.contains('done');
  try {
    const r = await fetch('<?= APP_URL ?>/controllers/tarefa_action.php', {
      method:'POST',
      headers:{'Content-Type':'application/json'},
      body: JSON.stringify({id, concluida: !done})
    });
    const data = await r.json();
    if(data.ok) location.reload(); // Recarregar para atualizar a timeline e horários
  } catch(e) { console.warn(e); }
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
