<?php
require_once __DIR__ . '/../config/config.php';
exigirLogin('../public/login.php');

$db  = getDB();
$uid = (int)$_SESSION['usuario_id'];

// Buscar filtro por disciplina se houver
$tag = sanitize($_GET['tag'] ?? '');

$sql = "SELECT * FROM produtos WHERE ativo = 1";
$params = [];
if (!empty($tag)) {
    $sql .= " AND (disciplina_tag = ? OR nome LIKE ?)";
    $params[] = $tag;
    $params[] = "%$tag%";
}
$sql .= " ORDER BY criado_em DESC";

$pq = $db->prepare($sql);
$pq->execute($params);
$produtos = $pq->fetchAll();

// Se não houver produtos, vamos inserir o Arsenal de Elite (Mocks Modo Guerra)
if (empty($produtos) && empty($tag)) {
    $mocks = [
        ['Modo Sprint 48h (IA)', 'A IA identifica o que você NÃO precisa estudar e compacta seu edital para as próximas 48h.', 'acelerador', 47.00, 'https://placehold.co/600x400/0c1424/22c55e?text=MODO+SPRINT'],
        ['Escudo Anti-Banca (Dossiê)', 'Análise profunda dos padrões de pegadinhas da banca do seu concurso.', 'estrategia', 39.90, 'https://placehold.co/600x400/0c1424/3b82f6?text=ANTI-BANCA'],
        ['Célula de Energia (Pack 50)', 'Recarregue sua Stamina Estratégica para mais 50 consultas profundas ao Mentor IA.', 'energia', 25.00, 'https://placehold.co/600x400/0c1424/fbbf24?text=ENERGIA+IA'],
        ['Mapeamento Preditivo', 'O que tem 92% de chance de estar na sua prova baseado em dados históricos.', 'acelerador', 89.00, 'https://placehold.co/600x400/0c1424/a855f7?text=PREDITIVO']
    ];
    $ins = $db->prepare("INSERT INTO produtos (nome, descricao, disciplina_tag, preco, imagem) VALUES (?,?,?,?,?)");
    foreach ($mocks as $m) $ins->execute($m);
    
    // Recarregar
    $pq->execute();
    $produtos = $pq->fetchAll();
}

$page_title = 'Materiais';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="dashboard-layout" style="position:relative;z-index:1;">
  <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>
  <main class="main-content">

    <div class="page-header d-flex jc-between ai-center">
      <div>
        <div class="page-breadcrumb"><a href="dashboard.php">Dashboard</a><span class="sep">›</span> Arsenal</div>
        <h2><i class="bi bi-rocket-takeoff text-neon" style="margin-right:0.5rem;"></i> Arsenal de Elite</h2>
        <p>Atalhos estratégicos para reduzir seu tempo até a aprovação.</p>
      </div>
      <div class="d-flex gap-md">
         <div class="input-group-hc" style="width:250px;">
            <i class="bi bi-search input-icon"></i>
            <form action="" method="GET">
                <input type="text" name="tag" class="form-control-hc" placeholder="Filtrar por matéria..." value="<?= $tag ?>">
            </form>
         </div>
      </div>
    </div>

    <!-- Filtros Rápidos -->
    <div style="display:flex; gap:0.6rem; margin-bottom:2rem; overflow-x:auto; padding-bottom:0.5rem;" class="hide-scrollbar">
        <a href="loja.php" class="chip <?= empty($tag) ? 'active' : '' ?>">Todos</a>
        <a href="?tag=acelerador" class="chip <?= $tag=='acelerador'?'active':'' ?>"><i class="bi bi-rocket-takeoff"></i> Aceleradores</a>
        <a href="?tag=energia" class="chip <?= $tag=='energia'?'active':'' ?>"><i class="bi bi-lightning-charge"></i> Energia</a>
        <a href="?tag=estrategia" class="chip <?= $tag=='estrategia'?'active':'' ?>"><i class="bi bi-shield-lock"></i> Estratégia</a>
    </div>

    <div class="grid-3">
        <?php foreach ($produtos as $p): ?>
        <div class="card-glass feature-card" style="padding:0; overflow:hidden; display:flex; flex-direction:column;">
            <div style="height:180px; width:100%; overflow:hidden; position:relative;">
                <img src="<?= $p['imagem'] ?>" alt="<?= sanitize($p['nome']) ?>" style="width:100%; height:100%; object-fit:cover;">
                <div style="position:absolute; top:1rem; right:1rem;">
                    <?php if ($p['disciplina_tag'] === 'acelerador'): ?>
                        <span class="badge-hc" style="background:var(--neon-green); color:#000; font-weight:900;">ACELERADOR</span>
                    <?php else: ?>
                        <span class="badge-hc badge-purple">ESTRATÉGICO</span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card-body" style="flex:1; display:flex; flex-direction:column; padding:1.25rem;">
                <div class="d-flex jc-between ai-center mb-xs">
                    <span style="font-size:0.75rem; color:var(--text-muted); font-weight:700; text-transform:uppercase;"><?= sanitize($p['disciplina_tag']) ?></span>
                    <div style="display:flex; gap:2px; color:var(--warning); font-size:0.8rem;">
                        <i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i>
                    </div>
                </div>
                <h4 style="font-size:1.15rem; margin-bottom:0.6rem;"><?= sanitize($p['nome']) ?></h4>
                <p style="font-size:0.85rem; color:var(--text-secondary); margin-bottom:1.5rem; flex:1;">
                    <?= sanitize($p['descricao']) ?>
                </p>
                <div class="d-flex jc-between ai-center mt-auto" style="border-top:1px solid var(--border-glass); padding-top:1rem;">
                    <div>
                        <div style="font-size:0.75rem; color:var(--text-muted);">Por apenas</div>
                        <div class="fw-800 text-neon" style="font-size:1.4rem;">R$ <?= number_format($p['preco'], 2, ',', '.') ?></div>
                    </div>
                    <button class="btn-hc btn-primary-hc btn-sm" onclick="alert('Funcionalidade de compra em breve!')">
                        Comprar
                    </button>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Banner Cupom -->
    <div class="card-glass mt-lg" style="background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); border: 1px solid rgba(34,197,94,0.4); padding: 3rem; position: relative; overflow: hidden; border-radius: 20px; box-shadow: 0 20px 50px rgba(0,0,0,0.3);">
        <!-- Efeito de brilho sutil no fundo -->
        <div style="position:absolute; top:-50%; left:-20%; width:300px; height:300px; background:radial-gradient(circle, rgba(34,197,94,0.15), transparent 70%); z-index:0;"></div>
        
        <div style="position:relative; z-index:1;">
            <div class="badge-hc" style="background:rgba(34,197,94,0.2); color:#22c55e; border:1px solid #22c55e; margin-bottom:1rem; padding:4px 12px; font-weight:700; font-size:0.7rem; letter-spacing:1px;">PROMOÇÃO DE LANÇAMENTO</div>
            <h3 style="margin-bottom:0.75rem; font-size:2rem; color:#ffffff; font-weight:900; letter-spacing:-0.02em;">Cupom de 20% OFF em qualquer material</h3>
            <p style="color:rgba(255,255,255,0.8); max-width:550px; font-size:1.1rem; line-height:1.5;">
                Use o código <strong style="color:#22c55e; font-weight:900;">HACKER20</strong> no checkout e acelere sua preparação agora mesmo.
            </p>
        </div>
        
        <!-- Ícone decorativo posicionado e dimensionado melhor -->
        <div style="position:absolute; right:3rem; top:50%; transform:translateY(-50%); color:rgba(255,255,255,0.05); font-size:10rem; pointer-events:none;">
            <i class="bi bi-ticket-perforated"></i>
        </div>
    </div>

  </main>
</div>

<style>
.hide-scrollbar::-webkit-scrollbar { display: none; }
.hide-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
.chip.active { background: rgba(34,197,94,0.15); border-color: var(--neon-green); color: var(--neon-green); }
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
