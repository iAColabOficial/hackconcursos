<?php
require_once __DIR__ . '/../config/config.php';
exigirLogin('../public/login.php');

$db  = getDB();
$uid = (int)$_SESSION['usuario_id'];

// Buscar edital e cargo selecionado do usuário para saber o que simular
$cargoQ = $db->prepare("
    SELECT c.id, c.nome AS cargo_nome, e.nome_concurso 
    FROM cargos c
    JOIN editais e ON e.id = c.edital_id
    WHERE e.usuario_id = ? AND c.selecionado = 1
    LIMIT 1
");
$cargoQ->execute([$uid]);
$cargoAtivo = $cargoQ->fetch();

// Buscar histórico de simulados do usuário
$simuladosQ = $db->prepare("
    SELECT s.*, c.nome AS cargo_nome
    FROM simulados s
    JOIN cargos c ON c.id = s.cargo_id
    WHERE s.usuario_id = ? 
    ORDER BY s.criado_em DESC
");
$simuladosQ->execute([$uid]);
$historicoSimulados = $simuladosQ->fetchAll();

// Buscar disciplinas do cargo para o modal de novo simulado
$disciplinas = [];
if ($cargoAtivo) {
    $discQ = $db->prepare("SELECT id, nome FROM disciplinas WHERE cargo_id = ?");
    $discQ->execute([$cargoAtivo['id']]);
    $disciplinas = $discQ->fetchAll();
}

$is_free = ($_SESSION['plano'] ?? 'free') === 'free';
$totalRealizados = count($historicoSimulados);
$limitReached = $is_free && $totalRealizados >= 5;

$page_title = 'Simulados';
include __DIR__ . '/../includes/header_aluno.php';
?>

<div class="dashboard-layout" style="position:relative;z-index:1;">
  <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>
  <main class="main-content">

    <div class="page-header d-flex jc-between ai-center">
      <div>
        <div class="page-breadcrumb"><a href="dashboard.php">Dashboard</a><span class="sep">›</span> Simulados</div>
        <h2><i class="bi bi-journal-check text-blue"></i> Meus Simulados</h2>
        <p>Pratique com questões reais e geradas por inteligência tática.</p>
      </div>
      <?php if ($cargoAtivo): ?>
        <?php if ($limitReached): ?>
          <div class="d-flex flex-column ai-end">
            <button class="btn-hc btn-ghost btn-lg" style="opacity:0.6; cursor:not-allowed;" title="Limite de 5 simulados atingido no plano gratuito.">
              Cota Esgotada (5/5)
            </button>
            <a href="meu_plano.php" class="text-neon small mt-xs fw-700" style="text-decoration:none;"><i class="bi bi-rocket-takeoff"></i> Fazer Upgrade para Ilimitado</a>
          </div>
        <?php else: ?>
          <button class="btn-hc btn-primary-hc btn-lg" onclick="openModal('modal-novo-simulado')">
            Novo Simulado <?= $is_free ? "($totalRealizados/5)" : "" ?>
          </button>
        <?php endif; ?>
      <?php endif; ?>
    </div>

    <?php if (!$cargoAtivo): ?>
    <div class="card-glass" style="text-align:center;padding:3rem;">
      <div style="font-size:3rem;margin-bottom:1rem;"><i class="bi bi-file-earmark-text text-muted"></i></div>
      <h3>Nenhum edital selecionado</h3>
      <p style="color:var(--text-secondary);margin:0.75rem 0 1.5rem;">Você precisa configurar um edital e cargo antes de gerar simulados.</p>
      <a href="upload_edital.php" class="btn-hc btn-primary-hc">Enviar Edital</a>
    </div>
    <?php else: ?>

    <div class="grid-4 mb-md">
      <!-- KPIs Rápidos -->
      <?php
      $totalRealizados = count($historicoSimulados);
      $mediaAcertos = 0;
      if ($totalRealizados > 0) {
          $somaPcts = array_sum(array_map(function($s){ return $s['total_questoes'] > 0 ? ($s['acertos']/$s['total_questoes'])*100 : 0; }, $historicoSimulados));
          $mediaAcertos = round($somaPcts / $totalRealizados, 1);
      }
      ?>
      <div class="kpi-card">
        <div class="kpi-icon blue"><i class="bi bi-clipboard-check"></i></div>
        <div>
          <div class="kpi-label">Realizados</div>
          <div class="kpi-value"><?= $totalRealizados ?></div>
        </div>
      </div>
      <div class="kpi-card">
        <div class="kpi-icon neon"><i class="bi bi-target"></i></div>
        <div>
          <div class="kpi-label">Média de Acertos</div>
          <div class="kpi-value text-neon"><?= $mediaAcertos ?>%</div>
        </div>
      </div>
    </div>

    <div class="card-glass">
      <div class="card-header-hc">
        <h5><i class="bi bi-clock-history text-muted"></i> Histórico de Simulados</h5>
      </div>
      <div class="card-body" style="padding:0;">
        <?php if (empty($historicoSimulados)): ?>
          <div style="text-align:center;padding:3rem;color:var(--text-muted);">
            <p>Você ainda não realizou nenhum simulado.</p>
          </div>
        <?php else: ?>
          <table class="table-hc">
            <thead>
              <tr>
                <th>Data</th>
                <th>Título / Cargo</th>
                <th>Questões</th>
                <th>Desempenho</th>
                <th>Tempo</th>
                <th>Ação</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($historicoSimulados as $sim): 
                $pct = $sim['total_questoes'] > 0 ? round(($sim['acertos'] / $sim['total_questoes']) * 100) : 0;
                $cor = $pct >= 70 ? 'text-neon' : ($pct >= 50 ? 'text-warning' : 'text-danger');
              ?>
              <tr>
                <td><?= date('d/m/Y', strtotime($sim['criado_em'])) ?></td>
                <td>
                  <div class="fw-700"><?= sanitize($sim['titulo']) ?></div>
                  <div style="font-size:0.75rem;color:var(--text-muted);"><?= sanitize($sim['cargo_nome']) ?></div>
                </td>
                <td><?= $sim['total_questoes'] ?></td>
                <td>
                  <span class="<?= $cor ?> fw-700"><?= $sim['acertos'] ?> acertos</span>
                  <div style="font-size:0.7rem;color:var(--text-muted);">Aproveitamento: <?= $pct ?>%</div>
                </td>
                <td><?= $sim['tempo_minutos'] ?> min</td>
                <td>
                  <div class="d-flex ai-center gap-sm">
                    <a href="simulado_view.php?id=<?= $sim['id'] ?>" class="btn-hc btn-ghost btn-sm">Ver Detalhes</a>
                    <?php if (!$is_free): ?>
                    <button class="btn-hc btn-sm" 
                            style="background:rgba(239,68,68,0.1); color:var(--danger); border:1px solid rgba(239,68,68,0.2);"
                            onclick="excluirSimulado(<?= $sim['id'] ?>)"
                            title="Excluir Simulado">
                      <i class="bi bi-trash"></i>
                    </button>
                    <?php endif; ?>
                  </div>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
      </div>
    </div>

    <?php endif; ?>

  </main>
</div>

<!-- MODAL NOVO SIMULADO -->
<div class="modal-overlay" id="modal-novo-simulado" style="display:none;">
  <div class="modal-box">
    <div class="modal-header">
      <h5 style="margin:0;"><i class="bi bi-play-circle-fill text-neon"></i> Iniciar Novo Simulado</h5>
      <button class="modal-close" onclick="closeModal('modal-novo-simulado')">&times;</button>
    </div>
    <form id="form-novo-simulado">
      <div class="modal-body">
        <p style="color:var(--text-secondary);font-size:0.88rem;margin-bottom:1.5rem;">
          Configure seu simulado tático. As questões serão geradas com base no seu cargo atual: <strong><?= sanitize($cargoAtivo['cargo_nome'] ?? '') ?></strong>.
        </p>
        
        <div class="form-group mb-md">
          <label class="form-label fw-700">Título do Simulado</label>
          <input type="text" name="titulo" class="form-control-hc" placeholder="Ex: Simulado Semanal FGV" required>
        </div>

        <div class="row-hc mb-md" style="display:flex; gap:1rem;">
          <div class="form-group" style="flex:1;">
            <label class="form-label fw-700">Banca Examinadora</label>
            <select name="banca" class="form-control-hc">
                <option value="FGV">FGV</option>
                <option value="CESPE">CESPE / Cebraspe</option>
                <option value="FCC">FCC</option>
                <option value="VUNESP">VUNESP</option>
                <option value="OUTRA">Outra (Genérica)</option>
            </select>
          </div>
          <div class="form-group" style="flex:1;">
            <label class="form-label fw-700">Dificuldade</label>
            <select name="dificuldade" class="form-control-hc">
                <option value="facil">Básico</option>
                <option value="media" selected>Intermediário</option>
                <option value="dificil">Avançado (Elite)</option>
            </select>
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">Disciplinas (Deixe vazio para todas)</label>
          <div style="max-height:150px;overflow-y:auto;border:1px solid var(--border-glass);padding:0.75rem;border-radius:var(--radius-md);background:rgba(255,255,255,0.02);">
            <?php foreach ($disciplinas as $d): ?>
            <label style="display:flex;align-items:center;gap:0.6rem;margin-bottom:0.5rem;cursor:pointer;font-size:0.85rem;">
              <input type="checkbox" name="disciplinas[]" value="<?= $d['id'] ?>" checked>
              <?= sanitize($d['nome']) ?>
            </label>
            <?php endforeach; ?>
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">Quantidade de Questões</label>
          <select name="quantidade" class="form-control-hc">
            <option value="5">5 questões (Express)</option>
            <option value="10" selected>10 questões (Padrão)</option>
            <option value="20">20 questões (Intenso)</option>
            <option value="50">50 questões (Simulado Real)</option>
          </select>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn-hc btn-ghost" onclick="closeModal('modal-novo-simulado')">Cancelar</button>
        <button type="submit" class="btn-hc btn-primary-hc">Gerar Simulado <i class="bi bi-lightning-charge-fill"></i></button>
      </div>
    </form>
  </div>
</div>

<script>
// Função para excluir simulado
async function excluirSimulado(id) {
    if (!confirm('Deseja realmente apagar este simulado? Esta ação removerá também o seu histórico de acertos para esta prova e não pode ser desfeita.')) return;

    try {
        const response = await fetch('../controllers/simulado_delete.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id })
        });
        
        const text = await response.text();
        let res;
        try {
            res = JSON.parse(text);
        } catch (e) {
            alert('Erro Crítico no Servidor: ' + text.substring(0, 200) + '...');
            return;
        }

        if (res.ok) {
            location.reload();
        } else {
            alert('Erro ao excluir: ' + res.msg);
        }
    } catch (err) {
        alert('Erro ao processar exclusão.');
    }
}

document.getElementById('form-novo-simulado').addEventListener('submit', async function(e) {
    e.preventDefault();
    const btn = this.querySelector('button[type="submit"]');
    const originalText = btn.innerHTML;
    
    btn.disabled = true;
    btn.innerHTML = '<span class="loader-spinner" style="width:18px;height:18px;border-width:2px;display:inline-block;"></span> Gerando...';
    
    const formData = new FormData(this);
    const data = {
        titulo: formData.get('titulo'),
        quantidade: formData.get('quantidade'),
        banca: formData.get('banca'),
        dificuldade: formData.get('dificuldade'),
        disciplinas: formData.getAll('disciplinas[]')
    };

    try {
        const response = await fetch('<?= APP_URL ?>/controllers/simulado_action.php?action=gerar', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });
        
        const responseText = await response.text();
        let res;
        try {
            res = JSON.parse(responseText);
        } catch (e) {
            alert('Erro de Geração (Resposta Inválida): ' + responseText.substring(0, 200) + '...');
            btn.disabled = false;
            btn.innerHTML = originalText;
            return;
        }
        
        if (res.ok) {
            window.location.href = 'simulado_view.php?id=' + res.simulado_id;
        } else {
            alert('Erro: ' + res.msg);
            btn.disabled = false;
            btn.innerHTML = originalText;
        }
    } catch (err) {
        alert('Erro ao conectar com o servidor.');
        btn.disabled = false;
        btn.innerHTML = originalText;
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
