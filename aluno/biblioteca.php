<?php
require_once __DIR__ . '/../config/config.php';
exigirLogin('../login.php');

$db = getDB();
$usuario_id = $_SESSION['usuario_id'];

// Processar Seleção de Concurso - PASSO 2: Importação Real
if (isset($_POST['finalizar_selecao'])) {
    $limite = ($_SESSION['plano'] === 'premium') ? LIMIT_TARGETS_TURBO : (($_SESSION['plano'] === 'anual') ? LIMIT_TARGETS_MASTERMIND : LIMIT_TARGETS_ACESSO);
    $atual  = getContagemAlvos($usuario_id);

    if ($atual >= $limite) {
        flashMsg('danger', "Você atingiu o limite de alvos do seu plano ($limite/$limite). Remova um concurso ou faça upgrade.");
        redirect('meus_concursos.php');
    }

    $biblioteca_id = (int)$_POST['biblioteca_id'];
    $cargo_id_bib  = (int)$_POST['cargo_id'];
    
    $edital = $db->prepare("SELECT * FROM biblioteca_editais WHERE id = ?");
    $edital->execute([$biblioteca_id]);
    $dados = $edital->fetch();

    if ($dados) {
        try {
            $db->beginTransaction();

            // 1. Criar edital do usuário
            $ins = $db->prepare("
                INSERT INTO editais (usuario_id, nome_concurso, banca, orgao, data_prova, arquivo_pdf, status_processamento, conteudo_texto) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $ins->execute([
                $usuario_id, $dados['nome_concurso'], $dados['banca'], $dados['orgao'],
                $dados['data_prova'], $dados['edital_pdf'] ?? 'biblioteca', 'concluido',
                "Importado da biblioteca: " . $dados['nome_concurso']
            ]);
            $novoEditalId = $db->lastInsertId();

            // 2. Criar Cargo
            $cargoBib = $db->prepare("SELECT nome FROM biblioteca_cargos WHERE id = ?");
            $cargoBib->execute([$cargo_id_bib]);
            $nomeCargo = $cargoBib->fetchColumn() ?: 'Geral';

            $insCargo = $db->prepare("INSERT INTO cargos (edital_id, nome, selecionado) VALUES (?, ?, ?)");
            $insCargo->execute([$novoEditalId, $nomeCargo, 1]);
            $novoCargoId = $db->lastInsertId();

            // 3. Importar disciplinas E TÓPICOS
            $discQ = $db->prepare("SELECT * FROM biblioteca_disciplinas WHERE biblioteca_cargo_id = ?");
            $discQ->execute([$cargo_id_bib]);
            $disciplinas = $discQ->fetchAll();

            $insDisc = $db->prepare("INSERT INTO disciplinas (cargo_id, nome, peso) VALUES (?, ?, ?)");
            $insTop  = $db->prepare("INSERT INTO topicos_edital (disciplina_id, nome, cobrado_frequentemente) VALUES (?, ?, ?)");

            foreach ($disciplinas as $d) {
                $insDisc->execute([$novoCargoId, $d['nome'], $d['peso']]);
                $novaDiscId = $db->lastInsertId();

                // Buscar tópicos da biblioteca para esta disciplina
                $topQ = $db->prepare("SELECT * FROM biblioteca_topicos WHERE biblioteca_disciplina_id = ?");
                $topQ->execute([$d['id']]);
                $topicos = $topQ->fetchAll();

                foreach ($topicos as $t) {
                    $incidencia = ($t['incidencia'] === 'alta' ? 1 : 0);
                    $insTop->execute([$novaDiscId, $t['nome'], $incidencia]);
                }
            }

            // 4. Vincular no perfil
            $db->prepare("UPDATE perfis_usuario SET biblioteca_edital_id = ? WHERE usuario_id = ?")
               ->execute([$biblioteca_id, $usuario_id]);

            $db->commit();

            flashMsg('success', 'Alvo selecionado! Agora vamos configurar seu diagnóstico de partida.');
            redirect('diagnostico.php?cargo=' . $novoCargoId);
        } catch (Exception $e) {
            $db->rollBack();
            flashMsg('danger', 'Erro ao importar: ' . $e->getMessage());
        }
    }
}

// PASSO 1.5: Seleção de Cargo (Interface)
$selecionado_id = (int)($_GET['selecionar'] ?? 0);
$cargos_disponiveis = [];
if ($selecionado_id) {
    $cq = $db->prepare("SELECT * FROM biblioteca_cargos WHERE biblioteca_edital_id = ?");
    $cq->execute([$selecionado_id]);
    $cargos_disponiveis = $cq->fetchAll();
}

// Verificar limite global para exibição de alerta
$limite_global = ($_SESSION['plano'] === 'premium') ? LIMIT_TARGETS_TURBO : (($_SESSION['plano'] === 'anual') ? LIMIT_TARGETS_MASTERMIND : LIMIT_TARGETS_ACESSO);
$atual_global  = getContagemAlvos($usuario_id);
$bloqueado_global = ($atual_global >= $limite_global);

// Filtros
$search = sanitize($_GET['q'] ?? '');
$cat = sanitize($_GET['cat'] ?? '');

$query = "SELECT * FROM biblioteca_editais WHERE situacao_adm = 'aprovado' AND status != 'encerrado'";
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
      <div style="font-size:0.7rem; color:var(--text-muted); opacity:0.5;">
          DEBUG: Plano: <?= $_SESSION['plano'] ?> | Alvos: <?= $atual_global ?>/<?= $limite_global ?> | Bloqueado: <?= $bloqueado_global ? 'SIM' : 'NÃO' ?>
      </div>
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

    <!-- OVERLAY DE SELEÇÃO DE CARGO -->
    <?php if ($selecionado_id && !empty($cargos_disponiveis)): ?>
    <div style="position:fixed; inset:0; background:rgba(0,0,0,0.85); backdrop-filter:blur(10px); z-index:9999; display:flex; align-items:center; justify-content:center; padding:2rem;">
        <div class="card-glass animate__animated animate__zoomIn" style="max-width:500px; width:100%; border:1px solid var(--accent-blue);">
            <div class="card-header-hc d-flex jc-between ai-center">
                <h4 style="margin:0;"><i class="bi bi-person-badge text-blue"></i> Selecione seu Cargo</h4>
                <a href="biblioteca.php" class="text-muted"><i class="bi bi-x-lg"></i></a>
            </div>
            <div class="card-body">
                <p class="text-muted mb-lg" style="font-size:0.9rem;">As matérias e o nível de profundidade do edital mudam completamente dependendo do cargo escolhido.</p>
                
                <form method="POST">
                    <input type="hidden" name="biblioteca_id" value="<?= $selecionado_id ?>">
                    <div style="display:flex; flex-direction:column; gap:0.75rem;">
                        <?php foreach($cargos_disponiveis as $idx => $cargo): ?>
                        <label style="cursor:pointer;">
                            <input type="radio" name="cargo_id" value="<?= $cargo['id'] ?>" style="display:none;" <?= $idx===0?'checked':'' ?> class="cargo-radio">
                            <div class="cargo-opt card-glass" style="padding:1rem; border:1px solid var(--border-glass); transition:all 0.3s; background:rgba(255,255,255,0.03);">
                                <div class="d-flex ai-center jc-between">
                                    <span class="fw-700"><?= sanitize($cargo['nome']) ?></span>
                                    <i class="bi bi-check-circle-fill check-ico" style="color:var(--accent-blue); opacity:0;"></i>
                                </div>
                            </div>
                        </label>
                        <?php endforeach; ?>
                    </div>
                    <button type="submit" name="finalizar_selecao" class="btn-hc btn-primary-hc w-100 mt-lg py-3 shadow-neon">
                        GERAR MINHA ESTRATÉGIA <i class="bi bi-rocket-takeoff"></i>
                    </button>
                </form>
            </div>
        </div>
    </div>
    <style>
        .cargo-radio:checked + .cargo-opt { border-color: var(--accent-blue) !important; background: rgba(59,130,246,0.1) !important; }
        .cargo-radio:checked + .cargo-opt .check-ico { opacity: 1 !important; }
    </style>
    <?php elseif($selecionado_id && !isset($_GET['auto'])): ?>
        <script>window.location.href = '?selecionar=<?= $selecionado_id ?>&auto=1';</script>
    <?php elseif($selecionado_id && isset($_GET['auto'])): ?>
        <div class="card-glass text-center p-xl">
            <i class="bi bi-exclamation-triangle text-warning" style="font-size:3rem;"></i>
            <h4 class="mt-md">Nenhum cargo cadastrado</h4>
            <p class="text-muted">Este edital não possui cargos detalhados na biblioteca. Entre em contato com o suporte ou tente outro concurso.</p>
            <a href="biblioteca.php" class="btn-hc btn-primary-hc mt-md">Voltar para Biblioteca</a>
        </div>
    <?php endif; ?>

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
                    <?php if ($bloqueado_global): ?>
                        <button class="btn-hc btn-ghost btn-sm" onclick="alert('Você atingiu o limite de <?= $limite_global ?> alvos ativos. Remova um concurso para adicionar outro.')">Bloqueado</button>
                    <?php else: ?>
                        <a href="?selecionar=<?= $e['id'] ?>" class="btn-hc btn-primary-hc btn-sm">Selecionar</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Banner Colaboração (Crowdsourcing) -->
    <div class="card-glass mt-lg" style="border: 1px solid var(--accent-blue); background: linear-gradient(90deg, rgba(59,130,246,0.05), transparent);">
        <div class="card-body d-flex ai-center jc-between">
            <div>
                <h4 class="text-blue"><i class="bi bi-cloud-upload"></i> Não encontrou seu concurso?</h4>
                <p class="text-muted" style="max-width:550px;">Ajude a nossa base de dados! Suba o edital que você deseja. Nossa equipe irá processar e liberar para você em até 24h.</p>
            </div>
            <div class="d-flex gap-sm">
                <?php if ($_SESSION['plano'] === 'premium'): ?>
                    <a href="upload_edital.php" class="btn-hc btn-primary-hc">Subir PDF Direto (Premium)</a>
                <?php endif; ?>
                <a href="sugerir_edital.php" class="btn-hc btn-ghost">Sugerir Novo Concurso</a>
            </div>
        </div>
    </div>

  </main>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
