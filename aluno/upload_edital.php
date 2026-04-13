<?php
require_once __DIR__ . '/../config/config.php';
exigirLogin('../public/login.php');

$db  = getDB();
$uid = (int)$_SESSION['usuario_id'];

// ---- Detectar etapa do wizard ----
$step    = $_GET['step'] ?? 'upload';   // upload | cargo | diagnostico | concluido
$editalId= (int)($_GET['edital'] ?? 0);
$cargoId = (int)($_GET['cargo']  ?? 0);

// Buscar edital se já existe
$edital = null;
if ($editalId) {
    $q = $db->prepare("SELECT * FROM editais WHERE id = ? AND usuario_id = ?");
    $q->execute([$editalId, $uid]);
    $edital = $q->fetch();
    if (!$edital) { flashMsg('danger','Edital não encontrado.'); redirect(APP_URL.'/aluno/upload_edital.php'); }
}

// Buscar cargos do edital
$cargos = [];
if ($edital) {
    $cq = $db->prepare("SELECT * FROM cargos WHERE edital_id = ? ORDER BY id");
    $cq->execute([$editalId]);
    $cargos = $cq->fetchAll();
}

// Buscar disciplinas do cargo selecionado
$disciplinas = [];
if ($cargoId) {
    $dq = $db->prepare("SELECT * FROM disciplinas WHERE cargo_id = ? ORDER BY peso DESC");
    $dq->execute([$cargoId]);
    $disciplinas = $dq->fetchAll();
}

// ---- Processar seleção de cargo ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $step === 'cargo') {
    $cargoSel = (int)($_POST['cargo_id'] ?? 0);
    if ($cargoSel) {
        $db->prepare("UPDATE cargos SET selecionado=0 WHERE edital_id=?")->execute([$editalId]);
        $db->prepare("UPDATE cargos SET selecionado=1 WHERE id=? AND edital_id=?")->execute([$cargoSel, $editalId]);
        redirect(APP_URL.'/aluno/upload_edital.php?step=diagnostico&edital='.$editalId.'&cargo='.$cargoSel);
    }
}

// ---- Processar diagnóstico e gerar plano ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $step === 'diagnostico') {
    require_once __DIR__ . '/../classes/StudyPlanner.php';
    $horas     = (float)($_POST['horas_dia'] ?? 2);
    $diasEst   = $_POST['dias_estudo'] ?? ['1','2','3','4','5'];
    $diaFolga  = (int)($_POST['dia_folga'] ?? 0);
    $niveis    = $_POST['nivel'] ?? [];
    $dificuldades= $_POST['dificuldade'] ?? [];

    // 1. Salvar diagnóstico
    $db->prepare("INSERT INTO diagnosticos (usuario_id, cargo_id, horas_disponivel, dias_estudo, dia_folga) VALUES (?,?,?,?,?)")
       ->execute([$uid, $cargoId, $horas, implode(',', $diasEst), $diaFolga]);
    $diagId = (int)$db->lastInsertId();

    // 2. Salvar nível por disciplina
    $stDD = $db->prepare("INSERT INTO diagnostico_disciplinas (diagnostico_id, disciplina_id, nivel, dificuldade) VALUES (?,?,?,?)");
    foreach ($niveis as $discId => $nivel) {
        $stDD->execute([$diagId, (int)$discId, $nivel, (int)($dificuldades[$discId] ?? 5)]);
    }

    // 3. Atualizar perfil
    $db->prepare("UPDATE perfis_usuario SET horas_dia=?, dias_semana=? WHERE usuario_id=?")
       ->execute([$horas, implode(',', $diasEst), $uid]);

    // 4. Gerar plano
    try {
        $planner = new StudyPlanner();
        $planoId = $planner->gerarPlano($uid, $cargoId, $diagId);
        flashMsg('success', 'Seu plano de estudos foi gerado com sucesso! 🎯');
        redirect(APP_URL.'/aluno/plano_estudos.php?plano='.$planoId);
    } catch (\Exception $e) {
        flashMsg('danger', 'Erro ao gerar plano: ' . $e->getMessage());
        redirect(APP_URL.'/aluno/upload_edital.php?step=diagnostico&edital='.$editalId.'&cargo='.$cargoId);
    }
}

$page_title = 'Meu Edital';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="dashboard-layout" style="position:relative;z-index:1;">
  <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>
  <main class="main-content">

    <!-- PAGE HEADER -->
    <div class="page-header">
      <div class="page-breadcrumb">
        <a href="dashboard.php">Dashboard</a><span class="sep">›</span> Meu Edital
      </div>
      <h2>📄 Análise de Edital</h2>
      <p>Envie seu edital e o sistema irá gerar seu plano de estudos personalizado em minutos.</p>
    </div>

    <!-- WIZARD STEPS -->
    <div class="card-glass mb-md" style="padding:1.25rem 1.5rem;">
      <div style="display:flex;gap:0;align-items:center;">
        <?php
        $steps = [
          'upload'      => ['num'=>1,'label'=>'Upload do Edital'],
          'diagnostico' => ['num'=>2,'label'=>'Diagnóstico'],
          'concluido'   => ['num'=>3,'label'=>'Plano Gerado'],
        ];
        $stepKeys = array_keys($steps);
        $currentIdx = array_search($step, $stepKeys);
        foreach ($steps as $sk => $sv):
          $idx    = array_search($sk, $stepKeys);
          $done   = $idx < $currentIdx;
          $active = $sk === $step;
          $color  = $done ? 'var(--neon-green)' : ($active ? 'var(--accent-blue)' : 'var(--text-muted)');
        ?>
        <div style="display:flex;align-items:center;flex:1;<?= $idx === 0 ? '' : '' ?>">
          <?php if ($idx > 0): ?>
          <div style="flex:1;height:2px;background:<?= $done ? 'var(--neon-green)' : 'var(--border-glass)' ?>;transition:background 0.4s;"></div>
          <?php endif; ?>
          <div style="display:flex;flex-direction:column;align-items:center;gap:0.3rem;">
            <div style="width:36px;height:36px;border-radius:50%;background:<?= $active ? 'var(--accent-blue)' : ($done ? 'var(--neon-green)' : 'rgba(255,255,255,0.06)') ?>;display:flex;align-items:center;justify-content:center;font-size:0.85rem;font-weight:700;color:<?= ($active||$done)?'#fff':'var(--text-muted)' ?>;transition:all 0.4s;">
              <?= $done ? '✓' : $sv['num'] ?>
            </div>
            <span style="font-size:0.72rem;font-weight:600;color:<?= $color ?>;white-space:nowrap;"><?= $sv['label'] ?></span>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- ======================== STEP: CADASTRO MANUAL ======================== -->
    <?php if ($step === 'upload'): ?>
    <div class="card-glass shadow-lg">
      <div class="card-header-hc py-3">
        <h5 class="mb-0"><i class="bi bi-pencil-square text-blue"></i> Configurar Plano de Estudos</h5>
      </div>
      <div class="card-body p-4">
        <form id="form-manual" method="POST" action="<?= APP_URL ?>/controllers/manual_action.php">
          
          <!-- Nome do Concurso -->
          <div class="row-hc mb-md animate__animated animate__fadeIn">
            <div class="col-md-8">
              <label class="form-label fw-700" style="color: var(--accent-blue);">1. Qual o nome do seu concurso?</label>
              <input type="text" name="nome_concurso" class="form-control-hc py-3" placeholder="Ex: INSS 2024, PF, OAB..." required>
            </div>
            <div class="col-md-4">
              <label class="form-label fw-700" style="color: var(--accent-blue);">Previsão da Prova</label>
              <input type="date" name="data_prova" class="form-control-hc py-3">
              <small class="text-muted" style="font-size:0.7rem;">(Opcional, deixe vazio se não houver edital)</small>
            </div>
          </div>

          <!-- Container de Disciplinas -->
          <label class="form-label fw-700 mb-3" style="font-size: 1rem; color: var(--accent-purple);">2. Quais matérias você vai estudar?</label>
          <div id="disciplinas-container">
            <!-- Primeira Matéria (Sempre visível) -->
            <div class="disciplina-item card-glass mb-4 animate__animated animate__fadeInUp" style="padding:1.5rem; background: rgba(255,255,255,0.02); border: 1px solid var(--border-glass);">
              <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem;">
                <h6 class="mb-0 fw-700"><i class="bi bi-book text-blue"></i> Matéria #1</h6>
              </div>
              
              <div class="mb-4">
                <label class="form-label text-muted" style="font-size:0.75rem; text-transform:uppercase; letter-spacing:1px;">Nome da Disciplina</label>
                <input type="text" name="disciplinas[0][nome]" class="form-control-hc" placeholder="Ex: Língua Portuguesa" required>
              </div>
              
              <div>
                <label class="form-label text-muted" style="font-size:0.75rem; text-transform:uppercase; letter-spacing:1px;">Tópicos / Assuntos (Um por linha)</label>
                <textarea name="disciplinas[0][topicos]" class="form-control-hc" rows="5" placeholder="Crase&#10;Interpretação de Textos&#10;Concordância Nominal..." required></textarea>
              </div>
            </div>
          </div>

          <!-- Botão Adicionar -->
          <div class="mb-5">
            <button type="button" onclick="addDisciplina()" class="btn-hc btn-ghost w-100 py-3" style="border: 2px dashed var(--border-glass);">
              <i class="bi bi-plus-circle me-2"></i> Adicionar Próxima Matéria
            </button>
          </div>

          <!-- Finalizar -->
          <div class="mt-5">
            <button type="submit" class="btn-hc btn-primary-hc w-100 py-4 shadow-neon" style="font-size:1.2rem; border-radius: var(--radius-lg);">
              Gerar Meu Plano Tático <i class="bi bi-rocket-takeoff ms-2"></i>
            </button>
            <p class="text-center text-muted mt-3" style="font-size:0.85rem;">Após clicar, você definirá suas horas disponíveis e nível em cada matéria.</p>
          </div>
        </form>
      </div>
    </div>

    <!-- Scripts do Formulário Dinâmico -->
    <script>
    let discCount = 1;
    function addDisciplina() {
      const container = document.getElementById('disciplinas-container');
      const div = document.createElement('div');
      div.className = 'disciplina-item card-glass mb-4 animate__animated animate__fadeInUp';
      div.style = 'padding:1.5rem; background: rgba(255,255,255,0.02); border: 1px solid var(--border-glass); position:relative;';
      
      div.innerHTML = `
        <button type="button" onclick="this.parentElement.remove()" class="btn-hc btn-sm" style="position:absolute; top:15px; right:15px; color:var(--text-muted); padding:0; width:30px; height:30px; display:flex; align-items:center; justify-content:center; border-radius:50%; background:rgba(255,255,255,0.05); border:none;"><i class="bi bi-x-lg"></i></button>
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem;">
          <h6 class="mb-0 fw-700"><i class="bi bi-book text-blue"></i> Matéria #${discCount + 1}</h6>
        </div>
        <div class="mb-4">
          <label class="form-label text-muted" style="font-size:0.75rem; text-transform:uppercase; letter-spacing:1px;">Nome da Disciplina</label>
          <input type="text" name="disciplinas[${discCount}][nome]" class="form-control-hc" placeholder="Ex: Direito Constitucional" required>
        </div>
        <div>
          <label class="form-label text-muted" style="font-size:0.75rem; text-transform:uppercase; letter-spacing:1px;">Tópicos / Assuntos (Um por linha)</label>
          <textarea name="disciplinas[${discCount}][topicos]" class="form-control-hc" rows="5" placeholder="Direitos Fundamentais&#10;Organização do Estado..." required></textarea>
        </div>
      `;
      container.appendChild(div);
      discCount++;
      div.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
    </script>
    
    <!-- ======================== STEP: DIAGNÓSTICO ======================== -->
    <?php elseif ($step === 'diagnostico' && $edital && $cargoId): ?>
    <form method="POST" action="?step=diagnostico&edital=<?= $editalId ?>&cargo=<?= $cargoId ?>">
    <div class="grid-2" style="grid-template-columns:1fr 320px;gap:1.5rem;align-items:start;">

      <!-- Disciplinas -->
      <div class="card-glass">
        <div class="card-header-hc"><h5><i class="bi bi-clipboard-data text-purple"></i> Seu nível em cada disciplina</h5></div>
        <div class="card-body">
          <?php if (empty($disciplinas)): ?>
            <p style="color:var(--text-muted);">Nenhuma disciplina encontrada para este cargo.</p>
          <?php else: ?>
          <p style="color:var(--text-secondary);font-size:0.88rem;margin-bottom:1.25rem;">
            Informe honestamente seu nível. Isso garante um plano mais eficiente.
          </p>
          <div style="display:flex;flex-direction:column;gap:0.85rem;">
            <?php foreach ($disciplinas as $disc): ?>
            <div style="background:rgba(255,255,255,0.02);border:1px solid var(--border-glass);border-radius:var(--radius-md);padding:1rem 1.25rem;">
              <div style="display:flex;align-items:center;gap:0.75rem;margin-bottom:0.85rem;">
                <span class="badge-hc badge-blue" style="font-size:0.68rem;"><?= number_format((float)$disc['peso'],1) ?>x</span>
                <span class="fw-700" style="font-size:0.92rem;"><?= sanitize($disc['nome']) ?></span>
              </div>
              <div style="display:flex;gap:0.5rem;margin-bottom:0.75rem;">
                <?php foreach(['iniciante'=>['🟢','Iniciante'],'intermediario'=>['🟡','Intermediário'],'avancado'=>['🔵','Avançado']] as $val=>[$ico,$lbl]): ?>
                <label style="flex:1;cursor:pointer;">
                  <input type="radio" name="nivel[<?= $disc['id'] ?>]" value="<?= $val ?>" style="display:none;" <?= $val==='intermediario'?'checked':'' ?>>
                  <div class="nivel-opt" data-val="<?= $val ?>" onclick="selecionarNivel(<?= $disc['id'] ?>, this, '<?= $val ?>')" style="border:1px solid var(--border-glass);border-radius:var(--radius-md);padding:0.45rem 0.5rem;text-align:center;font-size:0.75rem;font-weight:600;cursor:pointer;transition:all 0.2s;<?= $val==='intermediario'?'border-color:var(--accent-blue);background:rgba(59,130,246,0.1);color:var(--accent-blue);':'' ?>">
                    <?= $ico ?> <?= $lbl ?>
                  </div>
                </label>
                <?php endforeach; ?>
              </div>
              <div style="display:flex;align-items:center;gap:0.75rem;">
                <span style="font-size:0.75rem;color:var(--text-muted);white-space:nowrap;">Dificuldade:</span>
                <input type="range" name="dificuldade[<?= $disc['id'] ?>]" min="1" max="10" value="5"
                       style="flex:1;accent-color:var(--accent-blue);"
                       oninput="document.getElementById('dif-<?= $disc['id'] ?>').textContent=this.value">
                <span id="dif-<?= $disc['id'] ?>" class="font-mono text-blue fw-700" style="font-size:0.85rem;min-width:20px;text-align:right;">5</span>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- Disponibilidade-->
      <div style="display:flex;flex-direction:column;gap:1.25rem;">
        <div class="card-glass">
          <div class="card-header-hc"><h5><i class="bi bi-clock text-neon"></i> Disponibilidade</h5></div>
          <div class="card-body">
            <div class="form-group">
              <label class="form-label">Horas de estudo por dia</label>
              <div style="display:flex;align-items:center;gap:1rem;">
                <input type="range" name="horas_dia" id="horas-range" min="0.5" max="12" step="0.5" value="2"
                       style="flex:1;accent-color:var(--neon-green);"
                       oninput="document.getElementById('horas-val').textContent=this.value+'h'">
                <span id="horas-val" class="font-mono text-neon fw-700" style="font-size:1.1rem;min-width:36px;">2h</span>
              </div>
            </div>
            <div class="form-group">
              <label class="form-label">Dias que estuda</label>
              <div style="display:flex;gap:0.4rem;flex-wrap:wrap;">
                <?php
                $diasNomes = [1=>'Seg',2=>'Ter',3=>'Qua',4=>'Qui',5=>'Sex',6=>'Sáb',7=>'Dom'];
                foreach ($diasNomes as $num => $nome):
                  $checked = $num <= 5;
                ?>
                <label style="cursor:pointer;">
                  <input type="checkbox" name="dias_estudo[]" value="<?= $num ?>" <?= $checked?'checked':'' ?> style="display:none;" class="dia-check">
                  <div class="dia-btn" onclick="toggleDia(this)" style="width:42px;height:42px;border-radius:var(--radius-md);display:flex;align-items:center;justify-content:center;font-size:0.75rem;font-weight:700;border:1px solid var(--border-glass);cursor:pointer;transition:all 0.2s;<?= $checked?'background:rgba(34,197,94,0.15);border-color:var(--neon-green);color:var(--neon-green);':'' ?>">
                    <?= $nome ?>
                  </div>
                </label>
                <?php endforeach; ?>
              </div>
            </div>
          </div>
        </div>

        <div class="card-glass">
          <div class="card-body" style="text-align:center;">
            <div style="font-size:1.5rem;margin-bottom:0.5rem;">🎯</div>
            <div class="fw-700">Tudo pronto?</div>
            <p style="font-size:0.82rem;color:var(--text-secondary);margin:0.5rem 0 1rem;">
              Após confirmar, seu plano de estudos será gerado automaticamente com revisões programadas.
            </p>
            <button type="submit" class="btn-hc btn-neon btn-lg w-100">
              Gerar Meu Plano
            </button>
            <a href="upload_edital.php?step=cargo&edital=<?= $editalId ?>" class="btn-hc btn-ghost btn-sm w-100 mt-xs" style="margin-top:0.5rem;">← Voltar</a>
          </div>
        </div>
      </div>
    </div>
    </form>
    <?php endif; ?>

  </main>
</div>

<script>
// Upload via AJAX
function uploadEdital() {
  const input = document.getElementById('pdf_file');
  if (!input.files.length) return;

  const form = new FormData();
  form.append('pdf_file', input.files[0]);

  document.getElementById('upload-progress').style.display = 'block';
  document.getElementById('btn-upload').disabled = true;
  document.getElementById('btn-upload').innerHTML = '<span class="loader-spinner" style="width:16px;height:16px;border-width:2px;display:inline-block;margin-right:8px;"></span> Processando...';

  const steps = [
    {pct:15, msg:'Lendo o arquivo PDF...'},
    {pct:40, msg:'Extraindo conteúdo do edital...'},
    {pct:70, msg:'Analisando disciplinas e tópicos...'},
    {pct:90, msg:'Finalizando análise...'},
  ];
  let si = 0;
  const interval = setInterval(() => {
    if (si < steps.length) {
      document.getElementById('progress-fill').style.width = steps[si].pct + '%';
      document.getElementById('upload-pct').textContent    = steps[si].pct + '%';
      document.getElementById('upload-status').textContent = steps[si].msg;
      si++;
    }
  }, 1800);

  fetch('<?= APP_URL ?>/controllers/edital_action.php', { method:'POST', body:form })
    .then(r => r.json())
    .then(data => {
      clearInterval(interval);
      document.getElementById('progress-fill').style.width = '100%';
      document.getElementById('upload-pct').textContent    = '100%';
      if (data.ok) {
        document.getElementById('upload-status').textContent = '✅ Análise concluída! Redirecionando...';
        setTimeout(() => window.location.href = data.redirect, 1200);
      } else {
        document.getElementById('upload-status').textContent = '❌ ' + data.msg;
        document.getElementById('upload-status').style.color = 'var(--danger)';
        document.getElementById('btn-upload').disabled = false;
        document.getElementById('btn-upload').innerHTML = '<i class="bi bi-cpu"></i> Tentar Novamente';
      }
    })
    .catch(() => {
      clearInterval(interval);
      document.getElementById('upload-status').textContent = '❌ Erro de conexão. Tente novamente.';
      document.getElementById('upload-status').style.color = 'var(--danger)';
      document.getElementById('btn-upload').disabled = false;
    });
}

// Preview do arquivo selecionado
document.getElementById('pdf_file')?.addEventListener('change', function() {
  if (this.files.length) {
    const f = this.files[0];
    document.getElementById('file-name').textContent = f.name;
    document.getElementById('file-size').textContent = (f.size/1048576).toFixed(2) + ' MB';
    document.getElementById('file-preview').style.display = 'flex';
    document.getElementById('btn-upload').disabled = false;
    document.getElementById('upload-zone').style.borderColor = 'var(--neon-green)';
  }
});
function removerArquivo() {
  document.getElementById('pdf_file').value = '';
  document.getElementById('file-preview').style.display = 'none';
  document.getElementById('btn-upload').disabled = true;
  document.getElementById('upload-zone').style.borderColor = '';
}

// Seleção de nível de disciplina
function selecionarNivel(discId, el, val) {
  el.closest('.card-body') || el.closest('.card-glass');
  // Reset todos os botões da mesma disciplina
  el.closest('div').parentElement.querySelectorAll('.nivel-opt').forEach(opt => {
    opt.style.borderColor = 'var(--border-glass)';
    opt.style.background  = '';
    opt.style.color       = '';
  });
  // Highlight selecionado
  el.style.borderColor = 'var(--accent-blue)';
  el.style.background  = 'rgba(59,130,246,0.1)';
  el.style.color       = 'var(--accent-blue)';
  // Marcar radio
  el.closest('label').querySelector('input[type=radio]').checked = true;
}

// Toggle dias de estudo
function toggleDia(el) {
  const label = el.closest('label');
  const check = label.querySelector('input');
  check.checked = !check.checked;
  if (check.checked) {
    el.style.background    = 'rgba(34,197,94,0.15)';
    el.style.borderColor   = 'var(--neon-green)';
    el.style.color         = 'var(--neon-green)';
  } else {
    el.style.background    = '';
    el.style.borderColor   = 'var(--border-glass)';
    el.style.color         = '';
  }
}
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
