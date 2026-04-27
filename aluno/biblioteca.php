<?php
require_once __DIR__ . '/../config/config.php';
exigirLogin('../login.php');

$db = getDB();
$usuario_id = $_SESSION['usuario_id'];

// Processar Seleção de Concurso
if (isset($_GET['selecionar'])) {
    $biblioteca_id = (int)$_GET['selecionar'];
    
    // Verificar se existe
    $edital = $db->prepare("SELECT * FROM lib_editais WHERE id = ?");
    $edital->execute([$biblioteca_id]);
    $dados = $edital->fetch();

    if ($dados) {
        try {
            // 1. Criar um registro na tabela 'editais' do usuário baseado na biblioteca
            // Isso permite que o usuário tenha sua própria cópia para personalizar se necessário
            $ins = $db->prepare("
                INSERT INTO editais (usuario_id, nome_concurso, banca, status, conteudo_texto) 
                VALUES (?, ?, ?, ?, ?)
            ");
            $ins->execute([
                $usuario_id, 
                $dados['nome_concurso'], 
                $dados['banca'], 
                'ativo',
                "Edital importado da biblioteca: " . $dados['nome_concurso']
            ]);
            $novoEditalId = $db->lastInsertId();

            // 2. Importar disciplinas
            $discQ = $db->prepare("SELECT * FROM lib_disciplinas WHERE lib_edital_id = ?");
            $discQ->execute([$biblioteca_id]);
            $disciplinas = $discQ->fetchAll();

            $insDisc = $db->prepare("INSERT INTO disciplinas (edital_id, nome, peso) VALUES (?, ?, ?)");
            foreach ($disciplinas as $d) {
                $insDisc->execute([$novoEditalId, $d['nome'], $d['peso']]);
            }

            // 3. Vincular no perfil
            $stmt = $db->prepare("UPDATE perfis_usuario SET edital_ativo_id = ? WHERE usuario_id = ?");
            $stmt->execute([$novoEditalId, $usuario_id]);

            // Registrar Evento
            $stmtEv = $db->prepare("INSERT INTO eventos_usuario (usuario_id, evento, metadata) VALUES (?, ?, ?)");
            $stmtEv->execute([$usuario_id, 'selecionou_concurso', json_encode(['id' => $biblioteca_id, 'nome' => $dados['nome_concurso']])]);

            flashMsg('success', 'Alvo selecionado! Agora vamos configurar seu diagnóstico de partida.');
            redirect('diagnostico.php');
        } catch (Exception $e) {
            flashMsg('danger', 'Erro ao importar concurso: ' . $e->getMessage());
        }
    }
}

// Filtros
$search = sanitize($_GET['q'] ?? '');
$cat = sanitize($_GET['cat'] ?? '');

$query = "SELECT * FROM lib_editais WHERE status != 'encerrado'";
$params = [];

if ($search) {
    $query .= " AND (nome_concurso LIKE ? OR orgao LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$query .= " ORDER BY criado_em DESC";
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
      <h2><i class="bi bi-bullseye text-neon" style="font-size:1.4rem; margin-right:0.5rem; filter: drop-shadow(0 0 5px var(--neon-green-glow));"></i> Escolha seu Próximo Alvo</h2>
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

        <?php foreach($editais as $e): 
            $imgUrl = !empty($e['imagem']) ? APP_URL . '/' . $e['imagem'] : 'https://placehold.co/800x200/0c1424/22c55e?text=' . urlencode($e['orgao']);
        ?>
        <div class="card-glass" style="padding:0; overflow:hidden;">
            <div style="height:140px; position:relative; overflow:hidden;">
                <img src="<?= $imgUrl ?>" style="width:100%; height:100%; object-fit:cover; opacity:0.8;">
                <div style="position:absolute; inset:0; background:linear-gradient(to top, var(--dark-bg), transparent);"></div>
                <div style="position:absolute; top:1rem; left:1rem;">
                    <span class="badge-hc <?= $e['status'] == 'aberto' ? 'badge-neon' : 'badge-blue' ?>">
                        <?= strtoupper($e['status']) ?>
                    </span>
                </div>
            </div>
            <div class="card-body" style="padding:1.25rem;">
                <h4 class="lh-sm mb-xs" style="font-size:1.1rem;"><?= sanitize($e['nome_concurso']) ?></h4>
                <div class="text-muted" style="font-size:0.8rem; margin-bottom:1.5rem;">
                    <div class="d-flex jc-between mb-xs">
                        <span><i class="bi bi-bank"></i> Órgão: <?= sanitize($e['orgao']) ?></span>
                        <span class="text-white fw-700"><?= sanitize($e['abrangencia'] ?? 'Nacional') ?></span>
                    </div>
                    <div class="d-flex jc-between mb-xs">
                        <span><i class="bi bi-briefcase"></i> Banca: <?= sanitize($e['banca']) ?></span>
                        <span class="text-neon fw-700"><?= $e['numero_vagas'] ?? 'A definir' ?> vagas</span>
                    </div>
                    <?php if($e['data_prova']): ?>
                        <span style="display:block;"><i class="bi bi-calendar-event"></i> Prova: <?= date('d/m/Y', strtotime($e['data_prova'])) ?></span>
                    <?php endif; ?>
                </div>

                <div class="d-flex jc-between ai-center">
                    <div class="text-neon fw-700" style="font-size:0.75rem;">
                        <i class="bi bi-lightning-charge-fill"></i> IA PRONTA
                    </div>
                    <a href="?selecionar=<?= $e['id'] ?>" class="btn-hc btn-primary-hc btn-sm">Selecionar</a>
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
            <?php if ($_SESSION['plano'] === 'premium'): ?>
                <a href="upload_edital.php" class="btn-hc btn-ai">Subir Edital PDF</a>
            <?php else: ?>
                <a href="meu_plano.php" class="btn-hc btn-ai">Upgrade Modo Guerra</a>
            <?php endif; ?>
        </div>
    </div>

  </main>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
