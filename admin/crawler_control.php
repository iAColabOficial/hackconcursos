<?php
require_once __DIR__ . '/../config/config.php';
exigirAdmin('../login.php');

$db = getDB();

// Ação de disparar o crawler via AJAX/Post
if (isset($_POST['action']) && $_POST['action'] === 'run_crawler') {
    header('Content-Type: application/json');
    $term = $_POST['term'] ?? '';
    $mode = $_POST['mode'] ?? 'map';
    $max_pages = max(1, min(999, (int)($_POST['max_pages'] ?? 20)));

    $pythonPath = 'python';
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        $wherePython = shell_exec('where python');
        if ($wherePython) {
            $lines = explode("\n", trim($wherePython));
            $pythonPath = trim($lines[0]);
        }
    }

    $scriptPath = BASE_PATH . DIRECTORY_SEPARATOR . 'python' . DIRECTORY_SEPARATOR . 'crawler_pci.py';
    $cmd = "\"$pythonPath\" \"" . $scriptPath . "\" " . escapeshellarg($term) . " --pages=$max_pages";
    if ($mode === 'download') $cmd .= " --download";
    
    // Usar pasta uploads/ que já tem permissão de escrita no XAMPP
    $jobFile = BASE_PATH . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'crawler_job.json';
    $job = [
        'term'      => $term,
        'mode'      => $mode,
        'max_pages' => $max_pages,
        'criado_em' => date('Y-m-d H:i:s')
    ];
    
    $written = file_put_contents($jobFile, json_encode($job, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    if ($written !== false) {
        echo json_encode(['ok' => true, 'msg' => 'Ordem enviada!']);
    } else {
        echo json_encode(['ok' => false, 'msg' => 'Erro ao criar job. Pasta: ' . dirname($jobFile)]);
    }
    exit;
}

// Ação de Pausar (Matar processo)
if (isset($_POST['action']) && $_POST['action'] === 'pause_crawler') {
    header('Content-Type: application/json');
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        shell_exec("taskkill /F /IM python.exe /T");
    }
    echo json_encode(['ok' => true, 'msg' => 'Robô interrompido com sucesso.']);
    exit;
}

// Ação de Monitoramento (Get Status)
if (isset($_GET['action']) && $_GET['action'] === 'get_live_status') {
    header('Content-Type: application/json');
    $activeFile = BASE_PATH . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'crawler_active.json';
    $active = null;
    if (file_exists($activeFile)) {
        $data = json_decode(file_get_contents($activeFile), true);
        if ($data) {
            $stmt = $db->prepare("SELECT * FROM banco_provas WHERE id = ?");
            $stmt->execute([$data['id']]);
            $active = $stmt->fetch(PDO::FETCH_ASSOC);
        }
    }
    
    $concluidos = $db->query("SELECT * FROM banco_provas WHERE arquivo_local IS NOT NULL ORDER BY criado_em DESC LIMIT 2")->fetchAll(PDO::FETCH_ASSOC);
    $pendentes = $db->query("SELECT * FROM banco_provas WHERE arquivo_local IS NULL ORDER BY id ASC LIMIT 2")->fetchAll(PDO::FETCH_ASSOC);
    
    // Verificar se o python está rodando
    $taskList = shell_exec('tasklist');
    $isRunning = (strpos($taskList, 'python.exe') !== false);
    
    // Tentar ler o master_state para ver o progresso
    $masterState = @json_decode(@file_get_contents(BASE_PATH . '/uploads/master_state.json'), true);
    
    echo json_encode([
        'active' => $active,
        'done'   => $concluidos,
        'next'   => $pendentes,
        'is_running' => $isRunning,
        'is_master' => ($isRunning && !$active), // Se roda e não tem prova ativa no JSON, assume-se Master (ou troca de página)
        'master_page' => $masterState['next_page'] ?? 1
    ]);
    exit;
}

// Estatísticas
$total = $db->query("SELECT COUNT(*) FROM banco_provas")->fetchColumn();
$por_ano = $db->query("SELECT ano, COUNT(*) as qtd FROM banco_provas GROUP BY ano ORDER BY ano DESC")->fetchAll();
$por_banca = $db->query("SELECT banca, COUNT(*) as qtd FROM banco_provas GROUP BY banca ORDER BY qtd DESC LIMIT 10")->fetchAll();

// Filtro de exibição da tabela via GET
$q_banca = trim($_GET['q_banca'] ?? '');
$q_ano   = trim($_GET['q_ano']   ?? '');
$q_cargo = trim($_GET['q_cargo'] ?? '');

$where = []; $params = [];
if ($q_banca) { $where[] = 'banca LIKE ?'; $params[] = "%$q_banca%"; }
if ($q_ano)   { $where[] = 'ano = ?';      $params[] = (int)$q_ano; }
if ($q_cargo) { $where[] = 'cargo LIKE ?'; $params[] = "%$q_cargo%"; }

$whereSQL = $where ? ('WHERE ' . implode(' AND ', $where)) : '';
$stmtR = $db->prepare("SELECT * FROM banco_provas $whereSQL ORDER BY (arquivo_local IS NOT NULL) DESC, criado_em DESC LIMIT 50");
$stmtR->execute($params);
$ultimas = $stmtR->fetchAll();

$page_title = 'Torre de Controle - Big Data';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="dashboard-layout">
  <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>
  
  <main class="main-content">
    <div class="page-header">
      <div class="page-breadcrumb">Admin <span class="sep">›</span> Crawler PCI</div>
      <h2 class="text-neon"><i class="bi bi-cpu"></i> Torre de Controle: Big Data</h2>
      <p>Gerencie o mapeamento e download da base global de concursos.</p>
    </div>

    <!-- MONITOR DE OPERAÇÃO (AO VIVO) -->
    <div class="card-glass mb-md border-neon" style="background: rgba(0,255,100,0.03);">
        <div class="card-header-hc d-flex jc-between ai-center">
            <h5><i class="bi bi-activity"></i> Monitor de Operação em Tempo Real</h5>
            <div class="d-flex gap-sm">
                <button onclick="controlCrawler('run_crawler')" class="btn-hc btn-neon btn-sm"><i class="bi bi-play-fill"></i> ATIVAR</button>
                <button onclick="controlCrawler('pause_crawler')" class="btn-hc btn-ghost btn-sm text-danger"><i class="bi bi-pause-fill"></i> PAUSAR</button>
            </div>
        </div>
        <div class="card-body">
            <div id="live_monitor_list" class="d-flex flex-column gap-sm">
                <!-- Injetado via JS -->
                <div class="text-center p-md text-muted">Aguardando atividade do robô...</div>
            </div>
        </div>
    </div>

    <!-- KPIs -->
    <div class="grid-3 mb-md">
        <div class="kpi-card">
            <div class="kpi-icon neon"><i class="bi bi-database-fill-check"></i></div>
            <div>
                <div class="kpi-label">Total de Provas</div>
                <div class="kpi-value text-neon"><?= number_format($total, 0, ',', '.') ?></div>
                <div class="kpi-sub">Indexadas no banco</div>
            </div>
        </div>
        <div class="kpi-card">
            <div class="kpi-icon blue"><i class="bi bi-calendar-event"></i></div>
            <div>
                <div class="kpi-label">Último Ano</div>
                <div class="kpi-value text-blue"><?= $por_ano[0]['ano'] ?? '---' ?></div>
                <div class="kpi-sub"><?= $por_ano[0]['qtd'] ?? 0 ?> provas encontradas</div>
            </div>
        </div>
        <div class="kpi-card">
            <div class="kpi-icon purple"><i class="bi bi-lightning-charge"></i></div>
            <div>
                <div class="kpi-label">IA Status</div>
                <div class="kpi-value text-purple">Ativa</div>
                <div class="kpi-sub">Pronta para análise tática</div>
            </div>
        </div>
    </div>

    <div class="grid-2" style="grid-template-columns: 2fr 1fr; gap: 1.5rem;">
        
        <!-- FILTROS AVANÇADOS -->
        <div class="card-glass">
            <div class="card-header-hc"><h5><i class="bi bi-filter-square"></i> Filtros de Mineração Estratégica</h5></div>
            <div class="card-body">
                <div class="grid-4 mb-md">
                    <div>
                        <label class="form-label-hc">Cargo / Vaga</label>
                        <input type="text" id="filter_vaga" class="form-control-hc" placeholder="Ex: Professor">
                    </div>
                    <div>
                        <label class="form-label-hc">Estado / Órgão</label>
                        <input type="text" id="filter_estado" class="form-control-hc" placeholder="Ex: Santa Catarina">
                    </div>
                    <div>
                        <label class="form-label-hc">Banca</label>
                        <input type="text" id="filter_banca" class="form-control-hc" placeholder="Ex: FGV, Acafe">
                    </div>
                    <div>
                        <label class="form-label-hc">Ano</label>
                        <input type="number" id="filter_ano" class="form-control-hc" placeholder="Ex: 2024" value="2024">
                    </div>
                    <div>
                        <label class="form-label-hc">Máx. Páginas <small class="text-muted">(1 pág ≈ 50 provas)</small></label>
                        <input type="number" id="filter_pages" class="form-control-hc" value="20" min="1" max="999">
                    </div>
                </div>
                
                <div class="d-flex gap-md ai-end">
                    <button onclick="triggerCrawler('map')" class="btn-hc btn-primary-hc flex-1">
                        <i class="bi bi-search"></i> 1. Mapear Links
                    </button>
                    <button onclick="triggerCrawler('download')" class="btn-hc btn-outline-neon flex-1">
                        <i class="bi bi-cloud-download"></i> 2. Baixar PDFs
                    </button>
                    <button onclick="prioritizeFilter()" class="btn-hc btn-neon flex-1" style="box-shadow: 0 0 15px var(--neon-green);">
                        <i class="bi bi-lightning-charge-fill"></i> Priorizar Filtros
                    </button>
                </div>
                
                <div id="crawler_status" class="mt-md" style="display:none;">
                    <div class="d-flex ai-center gap-md text-neon">
                        <div class="spinner-border spinner-border-sm" role="status"></div>
                        <span id="status_text">Processando dados no servidor...</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- ESTATÍSTICAS POR BANCA -->
        <div class="card-glass">
            <div class="card-header-hc"><h5><i class="bi bi-pie-chart"></i> Top Bancas</h5></div>
            <div class="card-body">
                <table class="table-hc">
                    <thead>
                        <tr><th>Banca</th><th class="text-right">Qtd</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach($por_banca as $b): ?>
                        <tr>
                            <td class="fw-700"><?= $b['banca'] ?></td>
                            <td class="text-right text-blue"><?= $b['qtd'] ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ÚLTIMAS DESCOBERTAS -->
        <div class="card-glass" style="grid-column: span 2;">
            <div class="card-header-hc"><h5><i class="bi bi-list-stars"></i> Últimas Provas Indexadas</h5></div>
            <div class="card-body p-0">
                <table class="table-hc">
                    <thead>
                        <tr>
                            <th>Ano</th>
                            <th>Banca</th>
                            <th>Órgão / Cargo</th>
                            <th>Status</th>
                            <th>Ação</th>
                            <th>Data</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($ultimas as $u): ?>
                        <tr>
                            <td><span class="badge-hc badge-blue"><?= $u['ano'] ?></span></td>
                            <td class="fw-800 text-neon"><?= $u['banca'] ?></td>
                            <td><?= $u['orgao'] ?> <br><small class="text-muted"><?= $u['cargo'] ?></small></td>
                            <td>
                                <?php if($u['arquivo_local']): ?>
                                    <span class="text-neon" title="Arquivo no servidor"><i class="bi bi-check-circle-fill"></i> Baixado</span>
                                <?php else: ?>
                                    <span class="text-muted"><i class="bi bi-clock"></i> Pendente</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if($u['arquivo_local']): ?>
                                    <a href="<?= APP_URL ?>/<?= $u['arquivo_local'] ?>" target="_blank" class="btn-hc btn-ghost btn-sm">Abrir Local</a>
                                <?php else: ?>
                                    <a href="<?= $u['url_pdf'] ?>" target="_blank" class="text-blue btn-sm">Ver Original</a>
                                <?php endif; ?>
                            </td>
                            <td class="font-mono" style="font-size:0.75rem;"><?= date('d/m/H:i', strtotime($u['criado_em'])) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
  </main>
</div>

<script>
// Pré-popular filtros a partir da URL
const urlP = new URLSearchParams(window.location.search);
if (urlP.get('q_banca')) document.getElementById('filter_banca').value = urlP.get('q_banca');
if (urlP.get('q_ano'))   document.getElementById('filter_ano').value   = urlP.get('q_ano');
if (urlP.get('q_cargo')) document.getElementById('filter_vaga').value  = urlP.get('q_cargo');

async function controlCrawler(action) {
    const formData = new FormData();
    formData.append('action', action);
    if (action === 'run_crawler') {
        formData.append('term', document.getElementById('filter_vaga').value || '');
        formData.append('mode', 'download');
    }
    const r = await fetch('crawler_control.php', { method: 'POST', body: formData });
    const d = await r.json();
    alert(d.msg);
}

async function updateLiveMonitor() {
    try {
        const r = await fetch('crawler_control.php?action=get_live_status');
        const d = await r.json();
        const list = document.getElementById('live_monitor_list');
        list.innerHTML = '';

        // Estilos de linha
        const rowStyle = "display:grid; grid-template-columns: 80px 1fr 120px; align-items:center; padding: 0.5rem 1rem; border-radius: 8px; font-size:0.9rem;";

        // 2 CONCLUÍDOS
        d.done.reverse().forEach(p => {
            list.innerHTML += `<div style="${rowStyle} opacity:0.5; background: rgba(255,255,255,0.02);">
                <span class="text-muted">${p.ano}</span>
                <span class="text-truncate">${p.cargo}</span>
                <span class="text-right text-neon"><i class="bi bi-check-all"></i> Concluído</span>
            </div>`;
        });

        // 1 ATIVO (O MEIO)
        if (d.active) {
            list.innerHTML += `<div style="${rowStyle} border: 1px solid var(--neon-green); background: rgba(34,197,94,0.1); box-shadow: 0 0 15px rgba(34,197,94,0.2);">
                <b class="text-neon">${d.active.ano}</b>
                <b class="text-neon text-truncate">${d.active.cargo}</b>
                <span class="text-right"><span class="spinner-grow spinner-grow-sm text-neon"></span> BAIXANDO</span>
            </div>`;
        } else if (d.is_running) {
            const label = d.is_master ? `<i class="bi bi-minecart-loaded"></i> MASTER JOB` : `Robô ocioso`;
            list.innerHTML += `<div class="text-center p-sm text-neon" style="font-size:0.8rem;">${label} (Aguardando próxima página...)</div>`;
        } else {
            list.innerHTML += `<div class="text-center p-sm text-danger" style="font-size:0.8rem;">Robô Desativado</div>`;
        }

        // 2 PENDENTES
        d.next.forEach(p => {
            list.innerHTML += `<div style="${rowStyle} opacity:0.5;">
                <span class="text-muted">${p.ano}</span>
                <span class="text-truncate">${p.cargo}</span>
                <span class="text-right text-muted"><i class="bi bi-hourglass"></i> Na Fila</span>
            </div>`;
        });

    } catch(e) {}
}

// Iniciar Polling do Monitor
setInterval(updateLiveMonitor, 3000);
updateLiveMonitor();

async function prioritizeFilter() {
    const vaga = document.getElementById('filter_vaga').value.trim();
    const ano = document.getElementById('filter_ano').value.trim();
    const term = `${vaga} ${ano}`.trim() || 'Educação';
    
    if(!confirm(`Deseja interromper o Master Job para baixar agora: "${term}"?`)) return;
    
    const formData = new FormData();
    formData.append('action', 'run_crawler');
    formData.append('term', term);
    formData.append('mode', 'download');
    formData.append('max_pages', document.getElementById('filter_pages').value);

    const r = await fetch('crawler_control.php', { method: 'POST', body: formData });
    const d = await r.json();
    alert(d.msg);
}

async function triggerCrawler(mode = 'map') {
    const vaga   = document.getElementById('filter_vaga').value.trim();
    const estado = document.getElementById('filter_estado').value.trim();
    const banca  = document.getElementById('filter_banca').value.trim();
    const ano    = document.getElementById('filter_ano').value.trim();
    const pages  = document.getElementById('filter_pages').value || 20;

    const terms = [vaga, estado, banca, ano].filter(x => x !== '');
    const fullTerm = terms.join(' ');

    if (!fullTerm) { alert('Preencha pelo menos um campo de filtro.'); return; }

    const actionLabel = mode === 'download' ? 'BAIXAR os arquivos reais' : 'MAPEAR os links';
    if (!confirm('Deseja ' + actionLabel + ' para: "' + fullTerm + '"?')) return;

    document.getElementById('crawler_status').style.display = 'block';
    document.getElementById('status_text').textContent = 'Enviando ordem ao Watcher...';

    try {
        const formData = new FormData();
        formData.append('action', 'run_crawler');
        formData.append('term', fullTerm);
        formData.append('mode', mode);
        formData.append('max_pages', pages);

        const r = await fetch('crawler_control.php', { 
            method: 'POST', 
            body: formData,
            // Aumentar timeout para requisições de download
            signal: AbortSignal.timeout(30000) 
        });
        const data = await r.json();

        if (data.ok) {
            document.getElementById('status_text').textContent = '✅ Ordem enviada! Redirecionando para os resultados em 3s...';
            
            // Montar URL de filtro para ver os resultados após o crawl
            const params = new URLSearchParams();
            if (banca)  params.set('q_banca', banca);
            if (ano)    params.set('q_ano', ano);
            if (vaga)   params.set('q_cargo', vaga);

            // Redirecionar após 3 segundos (tempo para o watcher iniciar)
            setTimeout(() => {
                window.location.href = 'crawler_control.php?' + params.toString();
            }, 3000);
        } else {
            alert('Erro: ' + data.msg);
            document.getElementById('crawler_status').style.display = 'none';
        }
    } catch(e) {
        alert('Erro de comunicação: ' + e);
        document.getElementById('crawler_status').style.display = 'none';
    }
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
