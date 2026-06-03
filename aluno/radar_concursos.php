<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/ConcursosAPI.php';
exigirLogin('../login.php');

$api = new ConcursosAPI();
$estados = $api->getEstados();

// Pega o estado filtrado (padrão 'br')
$estado_selecionado = isset($_GET['uf']) ? strtolower($_GET['uf']) : 'br';
if (!array_key_exists($estado_selecionado, $estados)) {
    $estado_selecionado = 'br';
}

$concursos = $api->getConcursos($estado_selecionado);

$pageTitle = "Radar de Concursos";
include __DIR__ . '/../includes/header_aluno.php';
?>

<div class="dashboard-layout">
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="main-content">
        <header class="d-flex jc-between ai-end mb-lg">
            <div>
                <h1 class="fw-900 mb-xs" style="font-size: 1.8rem;"><i class="fas fa-satellite-dish text-neon"></i> Radar de Concursos</h1>
                <p class="text-secondary">Encontre oportunidades de aprovação no seu estado ou no Brasil inteiro.</p>
            </div>
            <div>
                <form action="radar_concursos.php" method="GET" class="d-flex gap-sm ai-center">
                    <label for="uf" class="fw-700 text-muted" style="font-size: 0.8rem;">FILTRAR POR ESTADO:</label>
                    <select name="uf" id="uf" class="form-control-hc" onchange="this.form.submit()" style="min-width: 150px; background: rgba(0,0,0,0.5); color: #fff; border: 1px solid rgba(255,255,255,0.1); padding: 0.5rem; border-radius: 8px;">
                        <?php foreach ($estados as $sigla => $nome): ?>
                            <option value="<?= $sigla ?>" <?= $sigla == $estado_selecionado ? 'selected' : '' ?>>
                                <?= htmlspecialchars($nome) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </form>
            </div>
        </header>

        <section class="mb-lg">
            <?php if (empty($concursos)): ?>
                <div class="card-glass p-5 text-center">
                    <i class="fas fa-exclamation-triangle text-warning mb-md" style="font-size: 3rem;"></i>
                    <h3 class="fw-800">Nenhum concurso encontrado ou API indisponível</h3>
                    <p class="text-secondary">Verifique se a API Python do Radar de Concursos está rodando na porta 5000.</p>
                </div>
            <?php else: ?>
                <div class="row">
                    <?php foreach ($concursos as $c): ?>
                        <div class="col-md-4 mb-md">
                            <div class="card-glass h-100 d-flex flex-column border-neon" style="background: linear-gradient(135deg, rgba(34,197,94,0.02), transparent);">
                                <div class="card-body flex-grow-1">
                                    <div class="d-flex jc-between ai-start mb-sm">
                                        <?php
                                            $status_class = 'badge-muted';
                                            $status_text = strtoupper($c['status'] ?? 'DESCONHECIDO');
                                            if ($c['status'] == 'open') {
                                                $status_class = 'badge-neon';
                                                $status_text = 'ABERTO';
                                            } elseif ($c['status'] == 'expected') {
                                                $status_class = 'badge-warning';
                                                $status_text = 'PREVISTO';
                                            } elseif ($c['status'] == 'closed') {
                                                $status_class = 'badge-danger';
                                                $status_text = 'ENCERRADO';
                                            }
                                        ?>
                                        <span class="badge-hc <?= $status_class ?>"><?= htmlspecialchars($status_text) ?></span>
                                        <div class="text-warning fw-800" style="font-size: 0.8rem;">
                                            <i class="fas fa-users"></i> <?= htmlspecialchars($c['workPlacesAvailable'] ?? 'Várias') ?> Vagas
                                        </div>
                                    </div>
                                    <h5 class="fw-800 mb-sm" style="font-size: 1.1rem; line-height: 1.3;"><?= htmlspecialchars($c['organization'] ?? 'Concurso') ?></h5>
                                </div>
                                <div class="card-footer-hc border-top-glass pt-md mt-auto d-flex flex-column gap-sm">
                                    <a href="<?= htmlspecialchars($c['link'] ?? '#') ?>" target="_blank" class="btn-hc btn-ghost btn-sm w-100">VER MATÉRIA COMPLETA <i class="fas fa-external-link-alt"></i></a>
                                    <a href="sugerir_edital.php?nome=<?= urlencode($c['organization'] ?? '') ?>" class="btn-hc btn-neon btn-sm w-100">FOCAR NESTE EDITAL <i class="fas fa-crosshairs"></i></a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    </main>
</div>

<?php include __DIR__ . '/../includes/footer_aluno.php'; ?>
