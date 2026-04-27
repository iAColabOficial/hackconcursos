<?php
require_once __DIR__ . '/../config/config.php';
exigirLogin('../login.php');

// Verificar se é admin
if (($_SESSION['perfil'] ?? '') !== 'admin') {
    flashMsg('danger', 'Acesso negado.');
    redirect('../aluno/dashboard.php');
}

$db = getDB();

// Métricas Rápidas
$totalUsers = $db->query("SELECT COUNT(*) FROM usuarios WHERE perfil = 'aluno'")->fetchColumn();
$totalEditais = $db->query("SELECT COUNT(*) FROM editais")->fetchColumn();
$totalSimulados = $db->query("SELECT COUNT(*) FROM simulados")->fetchColumn();
$totalVendas = $db->query("SELECT SUM(total) FROM pedidos WHERE status = 'pago'")->fetchColumn() ?? 0;

// Gráfico de novos usuários (últimos 7 dias) - DADOS REAIS
$statsQ = $db->query("
    SELECT DATE(criado_em) as dia, COUNT(*) as total 
    FROM usuarios 
    WHERE perfil = 'aluno' AND criado_em >= DATE_SUB(CURDATE(), INTERVAL 6 DAY) 
    GROUP BY dia 
    ORDER BY dia ASC
");
$realStats = $statsQ->fetchAll(PDO::FETCH_KEY_PAIR);

// Preparar labels e dados preenchendo lacunas com 0
$labels = [];
$dataUsers = [];
$maxVal = 5; // Valor mínimo para escala do gráfico
for($i=6; $i>=0; $i--) {
    $d = date('Y-m-d', strtotime("-$i days"));
    $labels[] = date('d/m', strtotime($d));
    $val = (int)($realStats[$d] ?? 0);
    $dataUsers[] = $val;
    if($val > $maxVal) $maxVal = $val;
}

// Feed de Atividades Reais (UNION de múltiplos eventos)
$feedQ = $db->query("
    (SELECT u.nome as usr, 'se cadastrou na plataforma' as act, u.criado_em as data, 'person-plus' as icon FROM usuarios u WHERE u.perfil = 'aluno')
    UNION ALL
    (SELECT u.nome as usr, CONCAT('enviou o edital ', e.nome_concurso) as act, e.criado_em as data, 'file-pdf' as icon FROM editais e JOIN usuarios u ON u.id = e.usuario_id)
    UNION ALL
    (SELECT u.nome as usr, CONCAT('finalizou o simulado ', s.titulo) as act, s.criado_em as data, 'check-circle' as icon FROM simulados s JOIN usuarios u ON u.id = s.usuario_id WHERE s.concluido = 1)
    ORDER BY data DESC LIMIT 10
");
$logs = $feedQ->fetchAll();

// Função auxiliar para tempo relativo (há X min)
function timeAgo($date) {
    if(!$date) return 'n/a';
    $time = time() - strtotime($date);
    if($time < 60) return 'agora';
    if($time < 3600) return 'há ' . round($time/60) . ' min';
    if($time < 86400) return 'há ' . round($time/3600) . ' h';
    return date('d/m/Y', strtotime($date));
}

$page_title = 'Painel Administrativo';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="dashboard-layout" style="position:relative;z-index:1;">
  <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>
  <main class="main-content">

    <div class="page-header">
      <h2>🛡️ Painel de Controle</h2>
      <p>Gestão estratégica da plataforma HackConcursos.</p>
    </div>

    <!-- KPI CARDS ADMIN -->
    <div class="grid-4 mb-lg">
      <div class="kpi-card">
        <div class="kpi-icon blue"><i class="bi bi-people"></i></div>
        <div>
          <div class="kpi-label">Total Alunos</div>
          <div class="kpi-value"><?= $totalUsers ?></div>
        </div>
      </div>
      <div class="kpi-card">
        <div class="kpi-icon neon"><i class="bi bi-file-earmark-pdf"></i></div>
        <div>
          <div class="kpi-label">Editais Analisados</div>
          <div class="kpi-value text-neon"><?= $totalEditais ?></div>
        </div>
      </div>
      <div class="kpi-card">
        <div class="kpi-icon purple"><i class="bi bi-controller"></i></div>
        <div>
          <div class="kpi-label">Simulados Realizados</div>
          <div class="kpi-value text-purple"><?= $totalSimulados ?></div>
        </div>
      </div>
      <div class="kpi-card">
        <div class="kpi-icon warn"><i class="bi bi-currency-dollar"></i></div>
        <div>
          <div class="kpi-label">Receita Total</div>
          <div class="kpi-value text-warning">R$ <?= number_format($totalVendas, 2, ',', '.') ?></div>
        </div>
      </div>
    </div>

    <div class="grid-2 mb-lg">
        <!-- Gráfico de Crescimento -->
        <div class="card-glass">
            <div class="card-header-hc">
                <h5><i class="bi bi-graph-up"></i> Crescimento de Usuários</h5>
            </div>
            <div class="card-body">
                <div style="height:300px; display:flex; align-items:flex-end; gap:0.5rem; justify-content:space-between; padding-top:2rem;">
                    <?php foreach($dataUsers as $idx => $val): 
                        $pctHeight = ($val / $maxVal) * 100;
                    ?>
                    <div style="flex:1; display:flex; flex-direction:column; align-items:center;">
                        <div style="width:100%; max-width:40px; background:linear-gradient(to top, var(--accent-blue), var(--accent-purple)); border-radius:4px 4px 0 0; height:<?= max(5, $pctHeight) ?>%; transition:height 1s; position:relative;" data-tooltip="<?= $val ?> novos">
                        </div>
                        <span style="font-size:0.7rem; color:var(--text-muted); margin-top:0.5rem;"><?= $labels[$idx] ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Atividades Recentes -->
        <div class="card-glass">
            <div class="card-header-hc">
                <h5><i class="bi bi-lightning-charge"></i> Atividade em Tempo Real</h5>
            </div>
            <div class="card-body" style="max-height:300px; overflow-y:auto;">
                <div class="sidebar-menu" style="padding:0;">
                    <?php if (empty($logs)): ?>
                        <div style="padding:2rem; text-align:center; color:var(--text-muted);">Sem atividades recentes.</div>
                    <?php endif; ?>
                    <?php foreach($logs as $log): ?>
                    <div style="display:flex; gap:1rem; padding:0.85rem; border-bottom:1px solid var(--border-glass); align-items:center;">
                        <div style="width:40px; height:40px; border-radius:50%; background:rgba(255,255,255,0.05); display:flex; align-items:center; justify-content:center; color:var(--accent-blue);">
                            <i class="bi bi-<?= $log['icon'] ?>"></i>
                        </div>
                        <div style="flex:1;">
                            <div style="font-size:0.85rem;"><strong class="text-primary"><?= sanitize($log['usr']) ?></strong> <?= sanitize($log['act']) ?></div>
                            <div style="font-size:0.7rem; color:var(--text-muted);"><?= timeAgo($log['data']) ?></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="card-glass">
        <div class="card-header-hc">
            <h5><i class="bi bi-people"></i> Últimos Usuários Cadastrados</h5>
            <a href="usuarios.php" class="btn-hc btn-ghost btn-sm" style="margin-left:auto;">Ver Todos</a>
        </div>
        <div class="card-body" style="padding:0;">
            <?php
            $usersQ = $db->query("SELECT id, nome, email, plano, criado_em FROM usuarios WHERE perfil = 'aluno' ORDER BY criado_em DESC LIMIT 5");
            $users = $usersQ->fetchAll();
            ?>
            <table class="table-hc">
                <thead>
                    <tr>
                        <th>Aluno</th>
                        <th>Plano</th>
                        <th>Cadastro</th>
                        <th>Status</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($users as $u): ?>
                    <tr>
                        <td>
                            <div class="fw-700"><?= sanitize($u['nome']) ?></div>
                            <div style="font-size:0.75rem; color:var(--text-muted);"><?= sanitize($u['email']) ?></div>
                        </td>
                        <td><span class="badge-hc <?= $u['plano'] === 'premium' ? 'badge-purple' : 'badge-blue' ?>"><?= strtoupper($u['plano']) ?></span></td>
                        <td><?= date('d/m/Y', strtotime($u['criado_em'])) ?></td>
                        <td><span class="text-neon"><i class="bi bi-dot" style="font-size:1.5rem;"></i> Ativo</span></td>
                        <td>
                            <button class="btn-hc btn-ghost btn-sm" onclick="alert('Editar usuário')">Editar</button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

  </main>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
