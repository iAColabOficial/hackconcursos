<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/EngineAdaptacao.php';
exigirLogin('../login.php');

$db = getDB();
$usuario_id = $_SESSION['usuario_id'];

// 1. Buscar Perfil Atual
$stmt = $db->prepare("SELECT * FROM perfis_usuario WHERE usuario_id = ?");
$stmt->execute([$usuario_id]);
$perfil = $stmt->fetch();

$edital_id = $perfil['biblioteca_edital_id'] ?? 0;

// 2. Se o formulário de configuração for enviado
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['configurar'])) {
    $horas_dia = (float)$_POST['horas_dia'];
    $dias_semana = implode(',', $_POST['dias_semana'] ?? []);
    $nivel_geral = $_POST['nivel_geral'];

    $up = $db->prepare("UPDATE perfis_usuario SET horas_dia = ?, dias_semana = ?, nivel_geral = ? WHERE usuario_id = ?");
    $up->execute([$horas_dia, $dias_semana, $nivel_geral, $usuario_id]);

    // Regerar missões automaticamente se houver edital
    if ($edital_id) {
        $engine = new EngineAdaptacao($db);
        $engine->gerarPlanoMissions($usuario_id, $edital_id, 'biblioteca');
        flashMsg('success', 'Configurações salvas e missões recalculadas!');
    } else {
        flashMsg('success', 'Configurações salvas! Selecione um edital na biblioteca para gerar missões.');
    }
    redirect('plano_estudos.php');
}

// 3. Buscar Missões Geradas
$missoes = [];
$plano_id = 0;
if ($perfil) {
    $stmtM = $db->prepare("
        SELECT t.*, d.nome as disciplina_nome 
        FROM tarefas_estudo t 
        JOIN disciplinas d ON t.disciplina_id = d.id
        JOIN planos_estudo p ON t.plano_id = p.id
        WHERE p.usuario_id = ? AND p.ativo = 1
        ORDER BY t.data_prevista ASC, t.id ASC
    ");
    $stmtM->execute([$usuario_id]);
    $missoes = $stmtM->fetchAll(PDO::FETCH_ASSOC);
}

$pageTitle = "Plano de Estudo Dinâmico";
include __DIR__ . '/../includes/header_aluno.php';
?>

<div class="dashboard-layout">
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="main-content">
        <header class="page-header">
            <h2 class="fw-800">📅 Plano de Estudo Dinâmico</h2>
            <p>Configuração tática e execução diária.</p>
        </header>

        <div class="row">
            <!-- COLUNA DE CONFIGURAÇÃO (ESQUERDA) -->
            <div class="col-md-4">
                <section class="card-glass p-4 mb-lg">
                    <div id="config-summary" style="<?= isset($_GET['edit']) ? 'display:none;' : '' ?>">
                        <div class="d-flex jc-between ai-center mb-md">
                            <h5 class="fw-800 mb-0"><i class="fas fa-sliders-h text-neon"></i> Suas Configurações</h5>
                            <button class="btn-hc btn-ghost btn-sm" onclick="toggleConfigForm(true)">EDITAR</button>
                        </div>
                        
                        <div class="grid-2 gap-sm mb-md">
                            <div class="card-glass p-2 text-center">
                                <div class="text-muted small">Carga Diária</div>
                                <div class="fw-800"><?= $perfil['horas_dia'] ?? 2 ?>h</div>
                            </div>
                            <div class="card-glass p-2 text-center">
                                <div class="text-muted small">Seu Nível</div>
                                <div class="fw-800 text-uppercase" style="font-size:0.7rem;"><?= $perfil['nivel_geral'] ?? 'Iniciante' ?></div>
                            </div>
                        </div>

                        <div class="mb-md">
                            <div class="text-muted small mb-xs">Dias Ativos</div>
                            <div class="d-flex gap-xs flex-wrap">
                                <?php 
                                $dias_ativos = explode(',', $perfil['dias_semana'] ?? '1,2,3,4,5');
                                $dias_nomes = [1=>'S', 2=>'T', 3=>'Q', 4=>'Q', 5=>'S', 6=>'S', 7=>'D'];
                                foreach ($dias_nomes as $val => $nome): 
                                    if(in_array($val, $dias_ativos)):
                                ?>
                                    <span class="badge-hc badge-neon" style="font-size:0.6rem;"><?= $nome ?></span>
                                <?php endif; endforeach; ?>
                            </div>
                        </div>

                        <div class="p-3 border-glass rounded bg-dark mb-md" style="font-size: 0.8rem;">
                            <div class="text-muted text-uppercase fw-700 mb-xs">Alvo Selecionado</div>
                            <div class="fw-800 text-blue">
                                <?php 
                                if ($edital_id) {
                                    $stmtE = $db->prepare("SELECT nome_concurso FROM biblioteca_editais WHERE id = ?");
                                    $stmtE->execute([$edital_id]);
                                    echo $stmtE->fetchColumn();
                                } else {
                                    echo "Nenhum selecionado";
                                }
                                ?>
                            </div>
                        </div>
                        
                        <?php if ($edital_id && !empty($missoes)): ?>
                        <button type="submit" name="configurar" class="btn-hc btn-neon w-100" style="display:none;">SALVAR</button>
                        <?php endif; ?>
                    </div>

                    <form method="POST" id="config-form" style="<?= isset($_GET['edit']) ? '' : 'display:none;' ?>">
                        <input type="hidden" name="configurar" value="1">
                        <div class="d-flex jc-between ai-center mb-md">
                            <h5 class="fw-800 mb-0"><i class="fas fa-cog text-neon"></i> Ajuste Tático</h5>
                            <button type="button" class="btn-hc btn-ghost btn-sm" onclick="toggleConfigForm(false)">CANCELAR</button>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Horas por Dia</label>
                            <input type="number" name="horas_dia" class="form-control-hc" step="0.5" value="<?= $perfil['horas_dia'] ?? 2 ?>" min="0.5" max="15">
                        </div>

                        <div class="form-group">
                            <label class="form-label">Dias da Semana</label>
                            <div class="d-grid grid-4 gap-xs">
                                <?php 
                                foreach ($dias_nomes as $val => $nome): 
                                ?>
                                    <label class="chip p-2 text-center" style="cursor:pointer; <?= in_array($val, $dias_ativos) ? 'border-color:var(--neon-green); color:var(--neon-green);' : '' ?>">
                                        <input type="checkbox" name="dias_semana[]" value="<?= $val ?>" <?= in_array($val, $dias_ativos) ? 'checked' : '' ?> style="display:none;">
                                        <?= $nome ?>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Seu Nível</label>
                            <select name="nivel_geral" class="form-control-hc">
                                <option value="iniciante" <?= ($perfil['nivel_geral'] ?? '') == 'iniciante' ? 'selected' : '' ?>>Iniciante</option>
                                <option value="intermediario" <?= ($perfil['nivel_geral'] ?? '') == 'intermediario' ? 'selected' : '' ?>>Intermediário</option>
                                <option value="avancado" <?= ($perfil['nivel_geral'] ?? '') == 'avancado' ? 'selected' : '' ?>>Avançado</option>
                            </select>
                        </div>

                        <button type="submit" class="btn-hc btn-neon w-100 mt-md">SALVAR E ATUALIZAR PLANO</button>
                    </form>
                </section>

                <div class="card-glass p-4 border-neon">
                    <h5 class="fw-800 mb-sm text-purple">✨ Upgrade IA</h5>
                    <p class="text-secondary mb-md" style="font-size: 0.85rem;">
                        Recalcule sua rota usando análise de banca FGV/CESPE e seus pontos fracos.
                    </p>
                    <button class="btn-hc btn-ai w-100" onclick="location.href='../controllers/refinar_plano_ia.php'">
                        OTIMIZAÇÃO AVANÇADA (1⚡)
                    </button>
                </div>

                <div class="mt-lg">
                    <button type="button" class="btn-hc btn-ghost w-100 text-danger" style="border-color: rgba(239,68,68,0.3);" onclick="abrirModalDelete()">
                        <i class="bi bi-trash3"></i> DELETAR PLANO ATUAL
                    </button>
                </div>
            </div>

            <!-- COLUNA DE MISSÕES (DIREITA) -->
            <div class="col-md-8">
                <section class="card-glass p-4">
                    <div class="d-flex jc-between ai-center mb-md">
                        <h3 class="fw-800">🎯 Suas Missões</h3>
                        <div class="text-muted" style="font-size: 0.8rem;"><?= count($missoes) ?> missões planejadas</div>
                    </div>

                    <?php if (empty($missoes)): ?>
                        <div class="text-center p-5 animate__animated animate__fadeIn">
                            <div style="font-size: 4rem; margin-bottom: 1.5rem;">🎯</div>
                            <h4 class="fw-900">Nenhum plano ativo detectado.</h4>
                            <p class="text-secondary mx-auto" style="max-width: 400px;">
                                Para gerar seu caminho de evolução, você precisa selecionar um concurso na nossa biblioteca.
                            </p>
                            <button class="btn-hc btn-neon mt-lg px-xl py-3 shadow-neon" onclick="location.href='biblioteca.php'">
                                <i class="bi bi-search"></i> ESCOLHER CONCURSO AGORA
                            </button>
                        </div>
                    <?php else: ?>
                        <div class="mission-timeline">
                            <?php 
                            $currentDate = '';
                            foreach ($missoes as $m): 
                                if ($currentDate != $m['data_prevista']):
                                    $currentDate = $m['data_prevista'];
                                    $hoje = date('Y-m-d');
                                    $dataLabel = ($currentDate == $hoje) ? 'HOJE' : date('d/m', strtotime($currentDate));
                            ?>
                                <div class="date-divider mt-md mb-sm d-flex ai-center gap-md">
                                    <span class="badge-hc <?= $currentDate == $hoje ? 'badge-neon' : 'badge-muted' ?>"><?= $dataLabel ?></span>
                                    <div class="flex-1 border-bottom-glass"></div>
                                </div>
                            <?php endif; ?>

                            <div class="task-item <?= $m['concluida'] ? 'done' : '' ?> d-flex jc-between ai-center mb-sm">
                                <div class="d-flex ai-center gap-md">
                                    <div class="task-check"><i class="fas fa-check"></i></div>
                                    <div>
                                        <div class="task-titulo fw-700"><?= $m['titulo'] ?></div>
                                        <div class="d-flex gap-sm ai-center mt-xs">
                                            <span class="chip" style="font-size: 0.65rem;"><?= $m['disciplina_nome'] ?></span>
                                            <span class="text-muted" style="font-size: 0.7rem;"><i class="far fa-clock"></i> <?= $m['duracao_minutos'] ?> min</span>
                                        </div>
                                    </div>
                                </div>
                                <div>
                                    <?php if (!$m['concluida']): ?>
                                        <button class="btn-hc btn-ghost btn-sm" onclick="location.href='simulados.php?missao=<?= $m['id'] ?>'">EXECUTAR</button>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </section>
            </div>
        </div>
    </main>
</div>

<?php include __DIR__ . '/../includes/footer_aluno.php'; ?>

<!-- MODAL DE DELEÇÃO -->
<div id="modal-delete-plano" class="modal-hc-overlay" style="display:none;">
    <div class="modal-hc-content" style="max-width: 500px; border: 1px solid rgba(239,68,68,0.5); box-shadow: 0 0 30px rgba(239,68,68,0.15);">
        <div class="text-center mb-md">
            <div style="font-size: 3.5rem; color: #ef4444; margin-bottom: 1rem;">
                <i class="bi bi-exclamation-triangle-fill"></i>
            </div>
            <h3 class="fw-900 text-danger">RESETAR JORNADA?</h3>
            <p class="text-secondary mt-sm">
                Ao deletar seu plano, você estará **apagando todo o seu histórico de sprints**, metas e o mapeamento do seu edital atual.
            </p>
        </div>

        <div class="card-glass p-3 mb-md" style="background: rgba(239,68,68,0.05); border-color: rgba(239,68,68,0.2);">
            <ul class="list-unstyled mb-0" style="font-size: 0.85rem; color: var(--text-secondary);">
                <li class="mb-xs"><i class="bi bi-x-circle-fill text-danger"></i> Perda do progresso acumulado</li>
                <li class="mb-xs"><i class="bi bi-x-circle-fill text-danger"></i> Histórico de desempenho excluído</li>
                <li><i class="bi bi-x-circle-fill text-danger"></i> Necessidade de reconfiguração total</li>
            </ul>
        </div>

        <p class="text-center fw-700 mb-lg" style="font-size: 0.9rem;">
            Esta ação é irreversível. Deseja continuar?
        </p>

        <div class="d-flex gap-md">
            <button class="btn-hc btn-ghost flex-1" onclick="fecharModalDelete()">CANCELAR</button>
            <form action="../controllers/deletar_plano.php" method="POST" class="flex-1">
                <input type="hidden" name="confirmar_delete" value="1">
                <button type="submit" class="btn-hc btn-neon w-100" style="background: #ef4444; color: white;">SIM, DELETAR TUDO</button>
            </form>
        </div>
    </div>
</div>

<style>
.modal-hc-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.85);
    backdrop-filter: blur(8px);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 10000;
}
.modal-hc-content {
    background: #0d0d0d;
    padding: 2.5rem;
    border-radius: 24px;
    width: 90%;
    animation: modalSlideUp 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}
@keyframes modalSlideUp {
    from { transform: translateY(20px); opacity: 0; }
    to { transform: translateY(0); opacity: 1; }
}
</style>

<script>
// Funções do Modal
function abrirModalDelete() {
    document.getElementById('modal-delete-plano').style.display = 'flex';
}
function fecharModalDelete() {
    document.getElementById('modal-delete-plano').style.display = 'none';
}

function toggleConfigForm(show) {
    document.getElementById('config-summary').style.display = show ? 'none' : 'block';
    document.getElementById('config-form').style.display = show ? 'block' : 'none';
}

// Toggle visual dos chips de dias da semana
document.querySelectorAll('input[name="dias_semana[]"]').forEach(chk => {
    chk.addEventListener('change', function() {
        const chip = this.parentElement;
        if (this.checked) {
            chip.style.borderColor = 'var(--neon-green)';
            chip.style.color = 'var(--neon-green)';
        } else {
            chip.style.borderColor = 'var(--border-glass)';
            chip.style.color = 'var(--text-secondary)';
        }
    });
});
</script>
