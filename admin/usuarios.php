<?php
require_once __DIR__ . '/../config/config.php';
exigirAdmin('../login.php');

$db = getDB();

// Filtros
$search = sanitize($_GET['q'] ?? '');
$plano  = sanitize($_GET['plano'] ?? '');

// Pagination settings (FIX B6)
$page = (int)($_GET['page'] ?? 1);
if ($page < 1) $page = 1;
$limit = 20;

$countSql = "SELECT COUNT(*) FROM usuarios WHERE perfil = 'aluno'";
$countParams = [];

if (!empty($search)) {
    $countSql .= " AND (nome LIKE ? OR email LIKE ?)";
    $countParams[] = "%$search%";
    $countParams[] = "%$search%";
}

if (!empty($plano)) {
    $countSql .= " AND plano = ?";
    $countParams[] = $plano;
}

$totalStmt = $db->prepare($countSql);
$totalStmt->execute($countParams);
$totalUsers = (int)$totalStmt->fetchColumn();
$totalPages = ceil($totalUsers / $limit);
if ($totalPages < 1) $totalPages = 1;
if ($page > $totalPages) $page = $totalPages;
$offset = ($page - 1) * $limit;

// FIX M10: Selecionando apenas colunas necessárias, sem carregar hashes de senha
$sql = "SELECT id, nome, email, plano, token_saldo, ativo, criado_em, atualizado_em FROM usuarios WHERE perfil = 'aluno'";
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

$sql .= " ORDER BY criado_em DESC LIMIT $limit OFFSET $offset";
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
                        <th>Tokens</th>
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
                            <td class="fw-700 text-purple"><i class="bi bi-lightning-charge"></i> <?= $u['token_saldo'] ?></td>
                            <td>
                                <?php if($u['ativo']): ?>
                                    <span class="badge-hc badge-neon" style="padding:2px 8px; font-size:0.65rem;">ATIVO</span>
                                <?php else: ?>
                                    <span class="badge-hc badge-danger" style="padding:2px 8px; font-size:0.65rem;">BLOQUEADO</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-right">
                                <button class="btn-hc btn-ghost btn-sm" onclick="abrirModal(<?= (int)$u['id'] ?>, '<?= sanitize($u['nome']) ?>', '<?= sanitize($u['plano']) ?>', <?= (int)$u['token_saldo'] ?>, <?= (int)$u['ativo'] ?>)">
                                    <i class="bi bi-pencil"></i> Gerir
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Paginação (FIX B6) -->
    <?php if ($totalPages > 1): ?>
    <div class="d-flex jc-between ai-center mt-md" style="margin-top:1.5rem; display:flex; justify-content:space-between; align-items:center;">
        <span class="small text-muted">Mostrando <?= count($lista) ?> de <?= $totalUsers ?> usuários</span>
        <div class="d-flex gap-xs" style="display:flex; gap:0.5rem;">
            <?php if ($page > 1): ?>
                <a href="?page=<?= $page - 1 ?>&q=<?= urlencode($search) ?>&plano=<?= urlencode($plano) ?>" class="btn-hc btn-ghost btn-sm">&lt; Anterior</a>
            <?php endif; ?>
            
            <span class="btn-hc btn-primary-hc btn-sm" style="pointer-events:none; padding: 0.4rem 0.8rem; background: var(--border-glass); border-radius: 4px;"><?= $page ?> / <?= $totalPages ?></span>
            
            <?php if ($page < $totalPages): ?>
                <a href="?page=<?= $page + 1 ?>&q=<?= urlencode($search) ?>&plano=<?= urlencode($plano) ?>" class="btn-hc btn-ghost btn-sm">Próxima &gt;</a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Modal Edição -->
    <div id="modal-user-edit" class="modal-overlay" style="display:none; align-items:center; justify-content:center; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.8); z-index:9999;">
        <div class="modal-box card-glass" style="width:100%; max-width:500px; padding:2rem;">
            <div class="d-flex jc-between ai-center mb-md">
                <h4 class="fw-800 m-0">Gerir Aluno</h4>
                <button class="btn-hc btn-ghost btn-sm" onclick="closeModal('modal-user-edit')" style="padding:0.2rem 0.5rem;"><i class="bi bi-x-lg"></i></button>
            </div>
            
            <input type="hidden" id="edit-uid" value="">
            
            <div class="form-group">
                <label class="form-label">Nome do Aluno</label>
                <input type="text" id="edit-nome" class="form-control-hc" readonly style="opacity:0.7">
            </div>

            <div class="form-group">
                <label class="form-label">Plano</label>
                <select id="edit-plano" class="form-control-hc">
                    <option value="free">Free</option>
                    <option value="basico">Básico</option>
                    <option value="premium">Premium</option>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label">Tokens Atuais: <span id="edit-tokens-display" class="fw-700 text-neon">0</span></label>
                <input type="number" id="edit-add-tokens" class="form-control-hc" placeholder="Adicionar (ex: 50)" value="0" min="0">
                <small class="text-muted">Apenas para adicionar. Deixe 0 para não alterar.</small>
            </div>

            <div class="d-flex flex-column gap-sm mt-lg">
                <button class="btn-hc btn-primary-hc w-100" onclick="salvarAlteracoes()">💾 Salvar Alterações</button>
                <button class="btn-hc btn-ghost w-100 text-danger" id="btn-toggle-bloqueio" onclick="toggleBloqueio()">🚫 Bloquear Usuário</button>
                <button class="btn-hc btn-ghost w-100 text-warning" onclick="redefinirSenha()">🔑 Gerar Nova Senha</button>
            </div>
        </div>
    </div>

    <script>
    function abrirModal(uid, nome, plano, tokens, ativo) {
        document.getElementById('edit-uid').value = uid;
        document.getElementById('edit-nome').value = nome;
        document.getElementById('edit-plano').value = plano;
        document.getElementById('edit-tokens-display').textContent = tokens;
        document.getElementById('edit-add-tokens').value = 0;
        
        const btnBloq = document.getElementById('btn-toggle-bloqueio');
        if (ativo == 1) {
            btnBloq.innerHTML = '🚫 Bloquear Usuário';
            btnBloq.className = 'btn-hc btn-ghost w-100 text-danger';
        } else {
            btnBloq.innerHTML = '✅ Desbloquear Usuário';
            btnBloq.className = 'btn-hc btn-ghost w-100 text-neon';
        }

        document.getElementById('modal-user-edit').style.display = 'flex';
    }

    function closeModal(id) {
        document.getElementById(id).style.display = 'none';
    }

    async function reqApi(action, data) {
        const payload = { action, ...data };
        const res = await fetch('user_action.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(payload)
        });
        return await res.json();
    }

    async function salvarAlteracoes() {
        const uid = document.getElementById('edit-uid').value;
        const plano = document.getElementById('edit-plano').value;
        const addTokens = parseInt(document.getElementById('edit-add-tokens').value) || 0;

        try {
            let res = await reqApi('alterar_plano', { usuario_id: uid, plano: plano });
            if (!res.ok) throw new Error(res.msg);

            if (addTokens > 0) {
                res = await reqApi('adicionar_tokens', { usuario_id: uid, quantidade: addTokens });
                if (!res.ok) throw new Error(res.msg);
            }
            
            alert('Alterações salvas com sucesso!');
            window.location.reload();
        } catch (e) {
            alert('Erro: ' + e.message);
        }
    }

    async function toggleBloqueio() {
        const uid = document.getElementById('edit-uid').value;
        if (!confirm('Tem certeza que deseja alterar o status deste usuário?')) return;
        try {
            const res = await reqApi('bloquear', { usuario_id: uid });
            if (res.ok) window.location.reload();
            else alert(res.msg);
        } catch(e) { alert('Erro na comunicação'); }
    }

    async function redefinirSenha() {
        const uid = document.getElementById('edit-uid').value;
        if (!confirm('Isso irá invalidar a senha atual. Continuar?')) return;
        try {
            const res = await reqApi('redefinir_senha', { usuario_id: uid });
            if (res.ok) {
                prompt('Senha redefinida! Copie a nova senha abaixo e envie ao aluno:', res.nova_senha);
            } else {
                alert(res.msg);
            }
        } catch(e) { alert('Erro na comunicação'); }
    }
    </script>

  </main>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
