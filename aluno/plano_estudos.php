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
                    <h5 class="fw-800 mb-md"><i class="fas fa-cog text-neon"></i> Configuração Tática</h5>
                    <form method="POST">
                        <input type="hidden" name="configurar" value="1">
                        
                        <div class="form-group">
                            <label class="form-label">Horas por Dia</label>
                            <input type="number" name="horas_dia" class="form-control-hc" step="0.5" value="<?= $perfil['horas_dia'] ?? 2 ?>" min="0.5" max="15">
                        </div>

                        <div class="form-group">
                            <label class="form-label">Dias da Semana</label>
                            <div class="d-grid grid-4 gap-xs">
                                <?php 
                                $dias_ativos = explode(',', $perfil['dias_semana'] ?? '1,2,3,4,5');
                                $dias_nomes = [1=>'S', 2=>'T', 3=>'Q', 4=>'Q', 5=>'S', 6=>'S', 7=>'D'];
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

                        <div class="mt-md p-3 border-glass rounded bg-dark" style="font-size: 0.8rem;">
                            <div class="text-muted text-uppercase fw-700 mb-xs">Concurso Ativo</div>
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

                        <button type="submit" class="btn-hc btn-neon w-100 mt-md">SALVAR E RECALCULAR</button>
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
            </div>

            <!-- COLUNA DE MISSÕES (DIREITA) -->
            <div class="col-md-8">
                <section class="card-glass p-4">
                    <div class="d-flex jc-between ai-center mb-md">
                        <h3 class="fw-800">🎯 Suas Missões</h3>
                        <div class="text-muted" style="font-size: 0.8rem;"><?= count($missoes) ?> missões planejadas</div>
                    </div>

                    <?php if (empty($missoes)): ?>
                        <div class="text-center p-5">
                            <i class="fas fa-calendar-times text-muted mb-md" style="font-size: 3rem;"></i>
                            <h4>Nenhuma missão gerada.</h4>
                            <p class="text-secondary">Selecione um edital na biblioteca e clique em "Salvar e Recalcular".</p>
                            <button class="btn-hc btn-primary-hc mt-md" onclick="location.href='biblioteca.php'">Ir para Biblioteca</button>
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

<script>
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

<?php include __DIR__ . '/../includes/footer_aluno.php'; ?>
