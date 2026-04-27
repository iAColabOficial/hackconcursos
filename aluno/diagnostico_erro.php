<?php
require_once __DIR__ . '/../config/config.php';
exigirLogin('../login.php');

$db = getDB();
$tarefaId = (int)($_GET['id'] ?? 0);

// Buscar dados da missão para contexto
$st = $db->prepare("
    SELECT t.*, d.nome as disciplina_nome, tp.nome as topico_nome, e.banca
    FROM tarefas_estudo t
    JOIN disciplinas d ON d.id = t.disciplina_id
    JOIN planos_estudo p ON p.id = t.plano_id
    JOIN cargos c ON c.id = p.cargo_id
    JOIN editais e ON e.id = c.edital_id
    LEFT JOIN topicos_edital tp ON tp.id = t.topico_id
    WHERE t.id = ? AND p.usuario_id = ?
");
$st->execute([$tarefaId, $_SESSION['usuario_id']]);
$missao = $st->fetch();

if (!$missao) {
    flashMsg('danger', 'Missão não encontrada.');
    redirect('dashboard.php');
}

$page_title = 'Diagnóstico Estratégico de Erro';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="dashboard-layout">
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>
    <main class="main-content">
        
        <div class="page-header">
            <div class="page-breadcrumb">
                <a href="dashboard.php">Dashboard</a>
                <span class="sep">/</span>
                <span>Análise de Padrão</span>
            </div>
            <h2><i class="bi bi-cpu text-neon"></i> Scanner de Padrões de Erro</h2>
            <p>A IA analisará seus erros para detectar falhas de memorização, interpretação ou táticas da banca.</p>
        </div>

        <div class="grid-2" style="grid-template-columns: 1fr 350px; gap: 2rem;">
            
            <div class="card-glass">
                <div class="card-body">
                    <div class="mb-lg p-3" style="background:rgba(255,255,255,0.02); border-radius:var(--radius-md); border-left:4px solid var(--accent-blue);">
                        <h5 class="mb-xs"><?= sanitize($missao['topico_nome'] ?: $missao['disciplina_nome']) ?></h5>
                        <p class="text-muted small">Banca Alvo: <strong><?= sanitize($missao['banca']) ?></strong></p>
                    </div>

                    <form id="formDiagnostico">
                        <input type="hidden" id="feedbackTaskId" value="<?= $tarefaId ?>">
                        
                        <div class="mb-md">
                            <label class="form-label fw-700 text-white">Escolha a Abordagem da IA:</label>
                            <div class="d-flex gap-md">
                                <label class="flex-1">
                                    <input type="radio" name="variacao" value="tecnica" checked style="display:none;" class="var-radio">
                                    <div class="var-opt card-glass p-3 text-center" style="cursor:pointer; border:1px solid var(--border-glass);">
                                        <div class="fw-700 small">TÉCNICA</div>
                                        <div class="text-muted" style="font-size:0.6rem;">Auditoria de Dados</div>
                                    </div>
                                </label>
                                <label class="flex-1">
                                    <input type="radio" name="variacao" value="emocional" style="display:none;" class="var-radio">
                                    <div class="var-opt card-glass p-3 text-center" style="cursor:pointer; border:1px solid var(--border-glass);">
                                        <div class="fw-700 small">PROVOCATIVA</div>
                                        <div class="text-muted" style="font-size:0.6rem;">Treinador de Elite</div>
                                    </div>
                                </label>
                            </div>
                        </div>

                        <label class="form-label fw-700 text-white">O que aconteceu nessa missão?</label>
                        <p class="text-muted small mb-sm">Cole as questões que errou ou descreva o que foi difícil.</p>
                        
                        <textarea id="textoErro" class="form-control-hc" rows="8" placeholder="Ex: Confundi os prazos de anulação..." style="background:rgba(0,0,0,0.3);"></textarea>

                        <div class="mt-lg d-flex jc-between ai-center">
                            <button type="submit" class="btn-hc btn-ai w-100 py-3 shadow-neon">
                                INICIAR SCANNER ESTRATÉGICO <i class="bi bi-magic"></i>
                            </button>
                        </div>
                    </form>

                    <style>
                        .var-radio:checked + .var-opt { border-color: var(--neon-green) !important; background: rgba(34,197,94,0.1) !important; }
                    </style>
                </div>
            </div>

            <div style="display:flex; flex-direction:column; gap:1.5rem;">
                <div class="card-glass" style="border-color:var(--accent-purple);">
                    <div class="card-body">
                        <h6 class="text-purple fw-900 mb-sm">POR QUE USAR?</h6>
                        <ul class="text-muted small" style="padding-left:1rem; margin:0;">
                            <li class="mb-xs">Pare de errar pelo mesmo motivo.</li>
                            <li class="mb-xs">Diferencie conceitos "irmãos".</li>
                            <li>Aprenda a ler as entrelinhas da banca <strong><?= sanitize($missao['banca']) ?></strong>.</li>
                        </ul>
                    </div>
                </div>

                <div id="loadingIa" style="display:none; text-align:center; padding:2rem;">
                    <div class="loader-spinner mb-md" style="width:40px; height:40px; border-top-color:var(--neon-green);"></div>
                    <p class="text-neon fw-700 animate__animated animate__pulse animate__infinite">IA ANALISANDO PADRÕES...</p>
                </div>

                <div id="resultadoIa" class="animate__animated animate__fadeIn" style="display:none;">
                    <!-- Resultado via JS -->
                </div>
            </div>

        </div>

    </main>
</div>

<script>
document.getElementById('formDiagnostico').onsubmit = async function(e) {
    e.preventDefault();
    const texto = document.getElementById('textoErro').value;
    if(!texto) return alert("Por favor, descreva seus erros para análise.");

    document.getElementById('loadingIa').style.display = 'block';
    document.getElementById('resultadoIa').style.display = 'none';

    try {
        const variacao = document.querySelector('input[name="variacao"]:checked').value;
        const r = await fetch('<?= APP_URL ?>/controllers/ai_diagnostico_erro.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                tarefa_id: <?= $tarefaId ?>,
                texto_erro: texto,
                variacao: variacao
            })
        });
        const res = await r.json();
        
        document.getElementById('loadingIa').style.display = 'none';
        
        if(res.ok) {
            exibirResultado(res.analise);
        } else {
            alert(res.msg);
        }
    } catch(err) {
        alert("Erro ao processar diagnóstico.");
        document.getElementById('loadingIa').style.display = 'none';
    }
};

function exibirResultado(analise) {
    const resDiv = document.getElementById('resultadoIa');
    resDiv.style.display = 'block';
    resDiv.innerHTML = `
        <div class="card-glass" style="border:1px solid var(--neon-green); background:rgba(34,197,94,0.05);">
            <div class="card-body">
                <h5 class="text-neon mb-md"><i class="bi bi-shield-check"></i> Resultado do Scanner</h5>
                <div class="text-white small lh-lg">
                    ${analise}
                </div>
                <button onclick="location.href='dashboard.php'" class="btn-hc btn-ghost btn-sm w-100 mt-lg">VOLTAR AO PLANO</button>
            </div>
        </div>
    `;
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
