<?php
require_once __DIR__ . '/../config/config.php';
exigirAdmin('../login.php');

$db = getDB();

// Ações (Simples CRUD mock para UI)
if (isset($_POST['action'])) {
    if ($_POST['action'] === 'salvar') {
        $nome = sanitize($_POST['nome']);
        $preco = (float)$_POST['preco'];
        $tag = sanitize($_POST['tag']);
        $desc = sanitize($_POST['descricao']);
        $img  = sanitize($_POST['imagem']);

        $ins = $db->prepare("INSERT INTO produtos (nome, preco, disciplina_tag, descricao, imagem) VALUES (?,?,?,?,?)");
        $ins->execute([$nome, $preco, $tag, $desc, $img]);
        flashMsg('success', 'Produto cadastrado com sucesso!');
        redirect('produtos.php');
    }
}

$produtos = $db->query("SELECT * FROM produtos ORDER BY criado_em DESC")->fetchAll();

$page_title = 'Gestão de Produtos';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="dashboard-layout" style="position:relative;z-index:1;">
  <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>
  <main class="content main-content">

    <div class="page-header d-flex jc-between ai-center">
      <div>
        <div class="page-breadcrumb"><a href="index.php">Painel Admin</a><span class="sep">›</span> Produtos</div>
        <h2>🛍️ Produtos da Loja</h2>
        <p>Gerencie os materiais, cursos e e-books disponíveis para venda.</p>
      </div>
      <button class="btn-hc btn-primary-hc" onclick="openModal('modal-novo-produto')">
        <i class="bi bi-plus-lg"></i> Novo Produto
      </button>
    </div>

    <div class="grid-3">
        <?php foreach($produtos as $p): ?>
        <div class="card-glass" style="overflow:hidden;">
            <div style="height:140px; background:rgba(255,255,255,0.05); display:flex; align-items:center; justify-content:center;">
                <?php if($p['imagem']): ?>
                    <img src="<?= $p['imagem'] ?>" style="width:100%; height:100%; object-fit:cover;">
                <?php else: ?>
                    <i class="bi bi-image text-muted" style="font-size:3rem;"></i>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <div class="d-flex jc-between mb-xs">
                    <span class="badge-hc badge-blue" style="font-size:0.6rem;"><?= strtoupper($p['disciplina_tag'] ?: 'GERAL') ?></span>
                    <strong class="text-neon">R$ <?= number_format($p['preco'], 2, ',', '.') ?></strong>
                </div>
                <h5 class="mb-xs"><?= sanitize($p['nome']) ?></h5>
                <p style="font-size:0.75rem; color:var(--text-secondary); height:40px; overflow:hidden;"><?= sanitize($p['descricao']) ?></p>
                <div class="d-flex jc-end gap-xs mt-md pt-md" style="border-top:1px solid var(--border-glass);">
                    <button class="btn-hc btn-ghost btn-sm">Editar</button>
                    <button class="btn-hc btn-ghost btn-sm text-danger">Excluir</button>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

  </main>
</div>

<!-- MODAL NOVO PRODUTO -->
<div class="modal-overlay" id="modal-novo-produto" style="display:none;">
    <div class="modal-box">
        <div class="modal-header">
            <h5 style="margin:0;">📦 Cadastrar Novo Produto</h5>
            <button class="modal-close" onclick="closeModal('modal-novo-produto')">&times;</button>
        </div>
        <form action="" method="POST">
            <input type="hidden" name="action" value="salvar">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Nome do Produto</label>
                    <input type="text" name="nome" class="form-control-hc" required placeholder="Ex: Hack de Matemática">
                </div>
                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label">Preço (R$)</label>
                        <input type="number" step="0.01" name="preco" class="form-control-hc" required placeholder="49.90">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Tag / Categoria</label>
                        <input type="text" name="tag" class="form-control-hc" placeholder="Ex: direito">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">URL da Imagem</label>
                    <input type="text" name="imagem" class="form-control-hc" placeholder="https://...">
                </div>
                <div class="form-group">
                    <label class="form-label">Descrição Curta</label>
                    <textarea name="descricao" class="form-control-hc" rows="3"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-hc btn-ghost" onclick="closeModal('modal-novo-produto')">Cancelar</button>
                <button type="submit" class="btn-hc btn-primary-hc">Salvar Produto</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
