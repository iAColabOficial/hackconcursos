<?php
require_once __DIR__ . '/../config/config.php';
exigirLogin('../public/login.php');

if (($_SESSION['perfil'] ?? '') !== 'admin') {
    flashMsg('danger', 'Acesso negado.');
    redirect('../aluno/dashboard.php');
}

$db = getDB();

// Filtros
$search = sanitize($_GET['q'] ?? '');
$plano  = sanitize($_GET['plano'] ?? '');

$sql = "SELECT * FROM usuarios WHERE perfil = 'aluno'";
$params = [];

if (!empty($search)) {
    $sql .= " AND (nome LIKE ? OR email LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($plano)) {
    $sql .= " AND plano = ?";
    $params[] = $plano;
}

$sql .= " ORDER BY criado_em DESC";
$users = $db->prepare($sql);
$users->execute($params);
$lista = $users->fetchAll();

$page_title = 'Gestão de Usuários';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="dashboard-layout" style="position:relative;z-index:1;">
  <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>
  <main class="main-content">

    <div class="page-header d-flex jc-between ai-center">
      <div>
        <div class="page-breadcrumb"><a href="index.php">Painel Admin</a><span class="sep">›</span> Usuários</div>
        <h2>👥 Gestão de Alunos</h2>
        <p>Visualize, edite e gerencie o acesso dos estudantes da plataforma.</p>
      </div>
      <button class="btn-hc btn-primary-hc" onclick="alert('Funcionalidade de convite em breve')">
        <i class="bi bi-person-plus"></i> Novo Usuário
      </button>
    </div>

    <!-- Barra de Filtros -->
    <div class="card-glass mb-lg" style="padding:1rem;">
        <form action="" method="GET" class="grid-3" style="grid-template-columns: 1fr 200px 150px; gap:1rem; align-items:end;">
            <div class="form-group mb-0">
                <label class="form-label">Buscar por nome ou e-mail</label>
                <div class="input-group-hc">
                    <i class="bi bi-search input-icon"></i>
                    <input type="text" name="q" class="form-control-hc" placeholder="Ex: João Silva..." value="<?= $search ?>">
                </div>
            </div>
            <div class="form-group mb-0">
                <label class="form-label">Filtrar Plano</label>
                <select name="plano" class="form-control-hc">
                    <option value="">Todos os Planos</option>
                    <option value="free" <?= $plano === 'free' ? 'selected' : '' ?>>Free</option>
                    <option value="basico" <?= $plano === 'basico' ? 'selected' : '' ?>>Básico</option>
                    <option value="premium" <?= $plano === 'premium' ? 'selected' : '' ?>>Premium</option>
                </select>
            </div>
            <button type="submit" class="btn-hc btn-ghost w-100">Filtrar</button>
        </form>
    </div>

    <div class="card-glass">
        <div class="card-body" style="padding:0;">
            <table class="table-hc">
                <thead>
                    <tr>
                        <th>Nome / E-mail</th>
                        <th>Plano</th>
                        <th>Data Cadastro</th>
                        <th>Último Acesso</th>
                        <th>Status</th>
                        <th class="text-right">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($lista)): ?>
                    <tr>
                        <td colspan="6" class="text-center" style="padding:4rem; color:var(--text-muted);">
                            Nenhum usuário encontrado com os filtros aplicados.
                        </td>
                    </tr>
                    <?php else: ?>
                        <?php foreach($lista as $u): ?>
                        <tr>
                            <td>
                                <div class="fw-700"><?= sanitize($u['nome']) ?></div>
                                <div style="font-size:0.75rem; color:var(--text-muted);"><?= sanitize($u['email']) ?></div>
                            </td>
                            <td>
                                <span class="badge-hc <?= $u['plano'] === 'premium' ? 'badge-purple' : 'badge-blue' ?>">
                                    <?= strtoupper($u['plano']) ?>
                                </span>
                            </td>
                            <td><?= date('d/m/Y', strtotime($u['criado_em'])) ?></td>
                            <td><?= $u['atualizado_em'] ? date('d/m/Y H:i', strtotime($u['atualizado_em'])) : '-' ?></td>
                            <td>
                                <?php if($u['ativo']): ?>
                                    <span class="badge-hc badge-neon" style="padding:2px 8px; font-size:0.65rem;">ATIVO</span>
                                <?php else: ?>
                                    <span class="badge-hc badge-danger" style="padding:2px 8px; font-size:0.65rem;">BLOQUEADO</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-right">
                                <div class="d-flex gap-xs jc-end">
                                    <button class="btn-hc btn-ghost btn-sm" title="Editar">Editar</button>
                                    <button class="btn-hc btn-ghost btn-sm" title="Ver Progresso">Ver</button>
                                    <button class="btn-hc btn-ghost btn-sm text-danger" title="Bloquear">Bloquear</button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

  </main>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
