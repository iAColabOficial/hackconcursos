<?php
require_once __DIR__ . '/../config/config.php';
exigirLogin('../login.php');

$db = getDB();
$usuario_id = $_SESSION['usuario_id'];

// Processar Seleção de Concurso
if (isset($_GET['selecionar'])) {
    $biblioteca_id = (int)$_GET['selecionar'];
    
    // Verificar se existe
    $edital = $db->prepare("SELECT * FROM biblioteca_editais WHERE id = ?");
    $edital->execute([$biblioteca_id]);
    $dados = $edital->fetch();

    if ($dados) {
        try {
            // Atualizar ou Criar Perfil do Usuário com este edital
            $stmt = $db->prepare("
                INSERT INTO perfis_usuario (usuario_id, biblioteca_edital_id) 
                VALUES (?, ?) 
                ON DUPLICATE KEY UPDATE biblioteca_edital_id = ?
            ");
            $stmt->execute([$usuario_id, $biblioteca_id, $biblioteca_id]);

            // Registrar Evento
            $stmtEv = $db->prepare("INSERT INTO eventos_usuario (usuario_id, evento, metadata) VALUES (?, ?, ?)");
            $stmtEv->execute([$usuario_id, 'selecionou_concurso', json_encode(['id' => $biblioteca_id, 'nome' => $dados['nome_concurso']])]);

            flashMsg('success', 'Concurso selecionado com sucesso! Vamos iniciar seu diagnóstico.');
            redirect('diagnostico.php');
        } catch (Exception $e) {
            flashMsg('danger', 'Erro ao selecionar concurso: ' . $e->getMessage());
        }
    }
}

// Filtros
$search = sanitize($_GET['q'] ?? '');
$cat = sanitize($_GET['cat'] ?? '');

$query = "SELECT * FROM biblioteca_editais WHERE status != 'encerrado'";
$params = [];

if ($search) {
    $query .= " AND (nome_concurso LIKE ? OR orgao LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($cat) {
    $query .= " AND categoria = ?";
    $params[] = $cat;
}

$query .= " ORDER BY popularidade DESC, criado_em DESC";
$stmt = $db->prepare($query);
$stmt->execute($params);
$editais = $stmt->fetchAll();

$page_title = 'Escolher Concurso - HackConcursos';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="dashboard-layout">
  <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>
  <main class="main-content">

    <div class="page-header">
      <div class="page-breadcrumb">
        <a href="dashboard.php">Dashboard</a>
        <span class="sep">/</span>
        <span>Biblioteca de Concursos</span>
      </div>
      <h2>🎯 Escolha seu Próximo Alvo</h2>
      <p>Selecione um dos concursos abaixo para gerar sua estratégia personalizada.</p>
    </div>

    <!-- Barra de Busca e Filtros -->
    <div class="card-glass mb-lg">
        <div class="card-body">
            <form method="GET" class="d-flex gap-md ai-center">
                <div class="input-group-hc flex-1">
                    <span class="input-icon"><i class="bi bi-search"></i></span>
                    <input type="text" name="q" class="form-control-hc" placeholder="Buscar por concurso ou órgão..." value="<?= $search ?>">
                </div>
                <select name="cat" class="form-control-hc" style="width:200px;" onchange="this.form.submit()">
                    <option value="">Todas Categorias</option>
                    <option value="Nacional" <?= $cat === 'Nacional' ? 'selected' : '' ?>>Nacional</option>
                    <option value="Estadual" <?= $cat === 'Estadual' ? 'selected' : '' ?>>Estadual</option>
                    <option value="Municipal" <?= $cat === 'Municipal' ? 'selected' : '' ?>>Municipal</option>
                </select>
                <button type="submit" class="btn-hc btn-primary-hc">Filtrar</button>
            </form>
        </div>
    </div>

    <!-- Grid de Cards -->
    <div class="grid-3">
        <?php if (empty($editais)): ?>
            <div style="grid-column: span 3; text-align:center; padding:5rem 2rem;">
                <i class="bi bi-search" style="font-size:3rem; color:var(--text-muted); opacity:0.3;"></i>
                <h4 class="mt-md">Nenhum concurso encontrado</h4>
                <p class="text-muted">Tente mudar os termos da busca ou filtros.</p>
                <a href="biblioteca.php" class="btn-hc btn-ghost mt-md">Limpar Filtros</a>
            </div>
        <?php endif; ?>

        <?php foreach($editais as $e): ?>
        <div class="card-glass">
            <div style="height:120px; background:linear-gradient(135deg, rgba(59,130,246,0.1), rgba(168,85,247,0.1)); border-radius:var(--radius-lg) var(--radius-lg) 0 0; display:flex; align-items:center; justify-content:center;">
                <div style="font-size:3.5rem; opacity:0.8;">🏛️</div>
            </div>
            <div class="card-body">
                <span class="badge-hc badge-blue mb-sm"><?= $e['categoria'] ?></span>
                <h4 class="lh-sm mb-xs"><?= sanitize($e['nome_concurso']) ?></h4>
                <div class="text-muted" style="font-size:0.85rem; margin-bottom:1.5rem;">
                    <i class="bi bi-bank"></i> <?= sanitize($e['orgao']) ?> &nbsp; 
                    <i class="bi bi-briefcase"></i> <?= sanitize($e['banca']) ?> &nbsp;
                    <i class="bi bi-calendar-event"></i> <?= $e['ano'] ?>
                </div>

                <div class="d-flex jc-between ai-center">
                    <div class="text-neon fw-700" style="font-size:0.8rem;">
                        <i class="bi bi-lightning-fill"></i> Estratégia Pronta
                    </div>
                    <a href="?selecionar=<?= $e['id'] ?>" class="btn-hc btn-neon btn-sm">Selecionar Alvo</a>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Banner Premium Upgrade -->
    <div class="card-glass mt-lg" style="border: 1px solid var(--accent-purple2); background: linear-gradient(90deg, rgba(168,85,247,0.05), transparent);">
        <div class="card-body d-flex ai-center jc-between">
            <div>
                <h4 class="text-purple"><i class="bi bi-stars"></i> Não encontrou seu concurso?</h4>
                <p class="text-muted" style="max-width:500px;">Seja <strong>Premium</strong> e suba qualquer edital em PDF para nossa IA processar exclusivamente para você.</p>
            </div>
            <a href="../planos.php" class="btn-hc btn-ai">Upgrade Modo Guerra</a>
        </div>
    </div>

  </main>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
