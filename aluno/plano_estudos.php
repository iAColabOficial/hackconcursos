<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/StudyPlanner.php';
exigirLogin('../public/login.php');

$db  = getDB();
$uid = (int)$_SESSION['usuario_id'];

// Reorganizar atrasadas se solicitado
if (isset($_GET['reorganizar'])) {
    $planoAtivo = $db->prepare("SELECT id FROM planos_estudo WHERE usuario_id=? AND ativo=1 LIMIT 1");
    $planoAtivo->execute([$uid]);
    $pid = $planoAtivo->fetchColumn();
    if ($pid) {
        $planner = new StudyPlanner();
        $count   = $planner->reorganizarAtrasadas((int)$pid);
        flashMsg('success', "$count tarefa(s) reagendada(s) com sucesso!");
    }
    redirect(APP_URL.'/aluno/plano_estudos.php');
}

// Buscar plano ativo
$planoQ = $db->prepare("
    SELECT p.*, c.nome AS cargo_nome, e.nome_concurso, e.data_prova, e.banca
    FROM planos_estudo p
    JOIN cargos c ON c.id = p.cargo_id
    JOIN editais e ON e.id = c.edital_id
    WHERE p.usuario_id = ? AND p.ativo = 1
    ORDER BY p.criado_em DESC LIMIT 1
");
$planoQ->execute([$uid]);
$plano = $planoQ->fetch();

// Buscar todos os planos do usuário para o seletor
$todosPlanosQ = $db->prepare("
    SELECT p.id, e.nome_concurso, c.nome AS cargo_nome, p.ativo
    FROM planos_estudo p
    JOIN cargos c ON c.id = p.cargo_id
    JOIN editais e ON e.id = c.edital_id
    WHERE p.usuario_id = ?
    ORDER BY p.criado_em DESC
");
$todosPlanosQ->execute([$uid]);
$todosPlanos = $todosPlanosQ->fetchAll();

if (!$plano) {
    flashMsg('warning','Você ainda não possui um plano de estudos. Envie um edital para começar!');
    redirect(APP_URL.'/aluno/upload_edital.php');
}

$planoId  = (int)$plano['id'];
$semana   = $_GET['semana'] ?? 0; // offset de semanas (0=atual, 1=próxima, -1=anterior)
$semana   = (int)$semana;

// Calcular intervalo da semana
$monday  = new DateTime();
$monday->modify('monday this week')->modify("+{$semana} weeks");
$sunday  = (clone $monday)->modify('+6 days');

$inicioSem = $monday->format('Y-m-d');
$fimSem    = $sunday->format('Y-m-d');

// Buscar tarefas da semana
$tarefasQ = $db->prepare("
    SELECT t.*, d.nome AS disciplina_nome,
           CASE WHEN t.data_prevista < CURDATE() AND t.concluida=0 THEN 1 ELSE 0 END AS eh_atrasada
    FROM tarefas_estudo t
    JOIN disciplinas d ON d.id = t.disciplina_id
    WHERE t.plano_id = ? AND t.data_prevista BETWEEN ? AND ?
    ORDER BY t.data_prevista ASC, t.concluida ASC, t.id ASC
");
$tarefasQ->execute([$planoId, $inicioSem, $fimSem]);
$tarefasSemana = $tarefasQ->fetchAll();

// Agrupar por dia
$porDia = [];
for ($i=0; $i<7; $i++) {
    $d = (clone $monday)->modify("+$i days");
    $porDia[$d->format('Y-m-d')] = ['data'=>$d,'tarefas'=>[]];
}
foreach ($tarefasSemana as $t) {
    if (isset($porDia[$t['data_prevista']])) {
        $porDia[$t['data_prevista']]['tarefas'][] = $t;
    }
}

// Stats gerais
$statsQ = $db->prepare("
    SELECT COUNT(*) AS total, SUM(concluida) AS concluidas,
           SUM(CASE WHEN data_prevista < CURDATE() AND concluida=0 THEN 1 ELSE 0 END) AS atrasadas,
           SUM(CASE WHEN data_prevista >= CURDATE() AND concluida=0 THEN 1 ELSE 0 END) AS pendentes
    FROM tarefas_estudo WHERE plano_id=?
");
$statsQ->execute([$planoId]);
$stats = $statsQ->fetch();

// Progresso por disciplina
$discProgQ = $db->prepare("
    SELECT d.nome,
           COUNT(t.id) AS total,
           SUM(t.concluida) AS feitas,
           SUM(t.duracao_minutos) AS minutos_total,
           SUM(CASE WHEN t.concluida=1 THEN t.duracao_minutos ELSE 0 END) AS minutos_feitos
    FROM tarefas_estudo t
    JOIN disciplinas d ON d.id = t.disciplina_id
    WHERE t.plano_id=? AND t.tipo='estudo'
    GROUP BY d.id, d.nome
    ORDER BY feitas DESC
");
$discProgQ->execute([$planoId]);
$discProg = $discProgQ->fetchAll();

$page_title = 'Plano de Estudos';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="dashboard-layout" style="position:relative;z-index:1;">
  <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>
  <main class="main-content">

    <!-- HEADER -->
    <div class="d-flex jc-between ai-center mb-md" style="flex-wrap:wrap;gap:1rem;">
      <div class="page-header" style="margin:0;">
        <div class="page-breadcrumb">
          <a href="dashboard.php">Dashboard</a><span class="sep">›</span> Plano de Estudos
        </div>
        <div class="d-flex ai-center gap-md">
            <h2 style="margin:0;">📅 Plano de Estudos</h2>
            <?php if (count($todosPlanos) > 1): ?>
            <select class="form-control-hc btn-ghost btn-sm" style="width:auto; height:32px; padding:0 12px; border-color:var(--border-glass); cursor:pointer; background:rgba(255,255,255,0.05);" onchange="trocarPlano(this.value)">
                <?php foreach($todosPlanos as $tp): ?>
                <option value="<?= $tp['id'] ?>" <?= $tp['ativo'] ? 'selected' : '' ?>>
                    <?= sanitize($tp['nome_concurso']) ?>
                </option>
                <?php endforeach; ?>
            </select>
            <?php endif; ?>
        </div>
        <p style="margin:0;"><?= sanitize($plano['nome_concurso']) ?> · <?= sanitize($plano['cargo_nome']) ?></p>
      </div>
      <div class="d-flex gap-md">
        <?php if (($stats['atrasadas'] ?? 0) > 0): ?>
        <a href="?reorganizar=1" class="btn-hc btn-danger-hc btn-sm">
          Reagendar <?= $stats['atrasadas'] ?> atrasada(s)
        </a>
        <?php endif; ?>
        <a href="upload_edital.php" class="btn-hc btn-primary-hc">Novo Plano</a>
      </div>
    </div>

    <!-- STATS -->
    <div class="grid-4 mb-md">
      <?php
      $total     = (int)($stats['total'] ?? 0);
      $concluidas= (int)($stats['concluidas'] ?? 0);
      $atrasadas = (int)($stats['atrasadas'] ?? 0);
      $pendentes = (int)($stats['pendentes'] ?? 0);
      $pct = $total > 0 ? round(($concluidas/$total)*100,1) : 0;
      ?>
      <div class="kpi-card">
        <div class="kpi-icon neon"><i class="bi bi-check-circle-fill" style="color:var(--neon-green)"></i></div>
        <div><div class="kpi-label">Concluídas</div><div class="kpi-value text-neon"><?= $concluidas ?></div><div class="kpi-sub"><?= $pct ?>% do total</div></div>
      </div>
      <div class="kpi-card">
        <div class="kpi-icon blue"><i class="bi bi-list-task" style="color:var(--accent-blue)"></i></div>
        <div><div class="kpi-label">Pendentes</div><div class="kpi-value text-blue"><?= $pendentes ?></div><div class="kpi-sub">tarefas restantes</div></div>
      </div>
      <div class="kpi-card">
        <div class="kpi-icon warn"><i class="bi bi-exclamation-triangle-fill" style="color:var(--warning)"></i></div>
        <div><div class="kpi-label">Atrasadas</div><div class="kpi-value" style="color:var(--danger)"><?= $atrasadas ?></div><div class="kpi-sub"><?= $atrasadas>0?'Precisa reagendar':'Tudo em dia! ✅' ?></div></div>
      </div>
      <div class="kpi-card">
        <div class="kpi-icon purple"><i class="bi bi-bar-chart-fill" style="color:var(--accent-purple)"></i></div>
        <div><div class="kpi-label">Progresso Geral</div><div class="kpi-value text-purple"><?= $pct ?>%</div><div class="kpi-sub"><?= $total ?> tarefas no total</div></div>
      </div>
    </div>

    <!-- CONTEÚDO PRINCIPAL -->
    <div class="grid-2" style="grid-template-columns:1fr 300px;gap:1.5rem;align-items:start;">

      <!-- CALENDÁRIO SEMANAL -->
      <div class="card-glass">
        <div class="card-header-hc">
          <div class="d-flex ai-center gap-md flex-1">
            <button onclick="mudarSemana(-1)" class="btn-hc btn-ghost btn-sm"><i class="bi bi-chevron-left"></i></button>
            <h5 style="margin:0;flex:1;text-align:center;">
              <?= $monday->format('d/m') ?> – <?= $sunday->format('d/m/Y') ?>
            </h5>
            <button onclick="mudarSemana(1)" class="btn-hc btn-ghost btn-sm"><i class="bi bi-chevron-right"></i></button>
          </div>
          <?php if ($semana !== 0): ?>
          <a href="?" style="font-size:0.78rem;color:var(--text-muted);margin-left:0.5rem;">Hoje</a>
          <?php endif; ?>
        </div>
        <div class="card-body" style="padding:1rem;">
          <?php
          $diasNomes = ['Segunda','Terça','Quarta','Quinta','Sexta','Sábado','Domingo'];
          $hoje2 = date('Y-m-d');
          $di = 0;
          foreach ($porDia as $dataStr => $dia):
            $isHoje = $dataStr === $hoje2;
            $dtObj  = $dia['data'];
          ?>
          <div style="margin-bottom:0.75rem;<?= $isHoje?'border-left:3px solid var(--neon-green);padding-left:0.75rem;':'' ?>">
            <div style="display:flex;align-items:center;gap:0.75rem;margin-bottom:0.4rem;">
              <div style="font-size:0.78rem;font-weight:700;text-transform:uppercase;letter-spacing:0.07em;color:<?= $isHoje?'var(--neon-green)':'var(--text-muted)' ?>;">
                <?= $diasNomes[$di] ?> <?= $dtObj->format('d/m') ?>
                <?php if ($isHoje): ?> <span class="badge-hc badge-neon" style="font-size:0.6rem;padding:2px 6px;">HOJE</span><?php endif; ?>
              </div>
              <div style="flex:1;height:1px;background:var(--border-glass);"></div>
              <span style="font-size:0.72rem;color:var(--text-muted);"><?= count($dia['tarefas']) ?> tarefa(s)</span>
            </div>

            <?php if (empty($dia['tarefas'])): ?>
              <div style="padding:0.5rem 0.5rem;font-size:0.8rem;color:var(--text-muted);">Nenhuma tarefa – dia livre 🌿</div>
            <?php else: ?>
              <?php foreach ($dia['tarefas'] as $t):
                $done    = (bool)$t['concluida'];
                $atrsda  = (bool)$t['eh_atrasada'];
                $tipoIco = ['estudo'=>'book','revisao_24h'=>'arrow-clockwise','revisao_7d'=>'arrow-clockwise','revisao_30d'=>'arrow-clockwise','simulado'=>'patch-question'];
                $tipoClr = ['estudo'=>'blue','revisao_24h'=>'neon','revisao_7d'=>'neon','revisao_30d'=>'neon','simulado'=>'purple'];
                $ic  = $tipoIco[$t['tipo']] ?? 'book';
                $clr = $tipoClr[$t['tipo']] ?? 'blue';
                $dur = $t['duracao_minutos'] >= 60 ? floor($t['duracao_minutos']/60).'h'.($t['duracao_minutos']%60>0?($t['duracao_minutos']%60).'m':'') : $t['duracao_minutos'].'m';
              ?>
              <div class="task-item <?= $done?'done':'' ?> <?= $atrsda?'atrasada':'' ?>"
                   onclick="toggleTarefa(<?= $t['id'] ?>, this)"
                   style="margin-bottom:0.35rem;padding:0.65rem 0.85rem;">
                <div class="task-check">
                  <?php if($done): ?><i class="bi bi-check-lg"></i><?php endif; ?>
                </div>
                <div class="kpi-icon <?= $clr ?>" style="width:32px;height:32px;font-size:0.85rem;flex-shrink:0;">
                  <i class="bi bi-<?= $ic ?>"></i>
                </div>
                <div style="flex:1;">
                  <div class="task-titulo fw-700" style="font-size:0.85rem;"><?= sanitize($t['disciplina_nome']) ?></div>
                  <div style="font-size:0.72rem;color:var(--text-muted);"><?= ucfirst(str_replace('_',' ',$t['tipo'])) ?></div>
                </div>
                <div style="display:flex;align-items:center;gap:0.5rem;">
                  <?php if ($atrsda): ?><span class="badge-hc badge-danger" style="font-size:0.6rem;">Atrasada</span><?php endif; ?>
                  <span class="font-mono" style="font-size:0.78rem;color:var(--text-muted);"><?= $dur ?></span>
                </div>
              </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
          <?php $di++; endforeach; ?>
        </div>
      </div>

      <!-- PAINEL DIREITO: progresso por disciplina -->
      <div>
        <div class="card-glass">
          <div class="card-header-hc"><h5><i class="bi bi-graph-up text-neon"></i> Por Disciplina</h5></div>
          <div class="card-body">
            <?php if (empty($discProg)): ?>
              <p style="color:var(--text-muted);font-size:0.85rem;">Nenhum progresso registrado.</p>
            <?php else: ?>
            <?php foreach ($discProg as $dp):
              $ptotal = (int)$dp['total'];
              $pfeitas= (int)$dp['feitas'];
              $ppct   = $ptotal > 0 ? round(($pfeitas/$ptotal)*100) : 0;
              $minFmt = $dp['minutos_feitos'] >= 60 ? floor($dp['minutos_feitos']/60).'h' : $dp['minutos_feitos'].'min';
            ?>
            <div style="margin-bottom:1.1rem;">
              <div class="d-flex jc-between ai-center" style="margin-bottom:0.3rem;">
                <span style="font-size:0.83rem;font-weight:600;"><?= sanitize($dp['nome']) ?></span>
                <span class="font-mono <?= $ppct>=70?'text-neon':($ppct>=40?'text-blue':'text-danger') ?>" style="font-size:0.8rem;font-weight:700;"><?= $ppct ?>%</span>
              </div>
              <div class="progress-hc"><div class="progress-bar-fill <?= $ppct>=70?'':($ppct>=40?'progress-blue':'') ?>" style="width:<?= $ppct ?>%"></div></div>
              <div style="font-size:0.7rem;color:var(--text-muted);margin-top:0.2rem;"><?= $pfeitas ?>/<?= $ptotal ?> sessões · <?= $minFmt ?> estudado</div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </div>

        <?php if ($plano['data_prova']): ?>
        <div class="card-glass mt-sm" style="margin-top:1rem;background:linear-gradient(135deg,rgba(59,130,246,0.08),rgba(168,85,247,0.06));border-color:rgba(59,130,246,0.2);">
          <div class="card-body" style="text-align:center;">
            <?php
            $diff = (new DateTime($plano['data_prova']))->diff(new DateTime());
            $dias = max(0, $diff->days);
            ?>
            <div style="font-size:2rem;font-weight:900;color:var(--accent-blue)"><?= $dias ?></div>
            <div style="font-size:0.75rem;color:var(--text-muted);">dias para a prova</div>
            <div style="font-size:0.82rem;font-weight:600;margin-top:0.25rem;"><?= date('d/m/Y', strtotime($plano['data_prova'])) ?></div>
          </div>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </main>
</div>

<script>
function mudarSemana(delta) {
  const url = new URL(window.location.href);
  const atual = parseInt(url.searchParams.get('semana') || '0');
  url.searchParams.set('semana', atual + delta);
  window.location.href = url.toString();
}
async function toggleTarefa(id, el) {
  const done = el.classList.contains('done');
  try {
    const r = await fetch('<?= APP_URL ?>/controllers/tarefa_action.php', {
      method:'POST', headers:{'Content-Type':'application/json'},
      body: JSON.stringify({id, concluida: !done})
    });
    const data = await r.json();
    if(data.ok) {
      el.classList.toggle('done');
      const check = el.querySelector('.task-check');
      check.innerHTML = !done ? '<i class="bi bi-check-lg"></i>' : '';
    }
  } catch(e) {}
}

async function trocarPlano(id) {
    try {
        const r = await fetch('<?= APP_URL ?>/controllers/plano_action.php', {
            method:'POST',
            headers:{'Content-Type':'application/json'},
            body: JSON.stringify({id})
        });
        const data = await r.json();
        if(data.ok) {
            window.location.href = 'plano_estudos.php';
        } else {
            alert('Erro ao trocar plano: ' + data.msg);
        }
    } catch(e) {
        alert('Erro de conexão ao trocar plano.');
    }
}
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
