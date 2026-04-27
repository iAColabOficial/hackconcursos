<?php
require_once __DIR__ . '/../config/config.php';
exigirLogin('../login.php');

$db = getDB();
$usuario_id = $_SESSION['usuario_id'];

// Buscar perfil
$perfilQ = $db->prepare("SELECT * FROM perfis_usuario WHERE usuario_id = ?");
$perfilQ->execute([$usuario_id]);
$dados_perfil = $perfilQ->fetch();

$biblioteca_id = $dados_perfil['biblioteca_edital_id'] ?? 0;
$dados_edital = null;
$lista_disciplinas = [];

if ($biblioteca_id) {
    // Buscar dados do edital
    $editalQ = $db->prepare("SELECT * FROM biblioteca_editais WHERE id = ?");
    $editalQ->execute([$biblioteca_id]);
    $dados_edital = $editalQ->fetch();

    // Buscar disciplinas do edital
    $discQ = $db->prepare("SELECT * FROM biblioteca_disciplinas WHERE biblioteca_edital_id = ?");
    $discQ->execute([$biblioteca_id]);
    $lista_disciplinas = $discQ->fetchAll();
}

// Processar Personalização
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $horas_dia = (float)$_POST['horas_dia'];
    $nivel_geral = $_POST['nivel_geral'] ?? 'iniciante';
    $niveis = $_POST['nivel'] ?? []; // Array: disciplina_id => nivel

    try {
        $db->beginTransaction();

        // 1. Atualizar ou Criar perfil
        if ($dados_perfil) {
            $up = $db->prepare("UPDATE perfis_usuario SET horas_dia = ? WHERE usuario_id = ?");
            $up->execute([$horas_dia, $usuario_id]);
        } else {
            $ins = $db->prepare("INSERT INTO perfis_usuario (usuario_id, horas_dia) VALUES (?, ?)");
            $ins->execute([$usuario_id, $horas_dia]);
        }

        // Registrar Evento de Perfil
        $stmtEv = $db->prepare("INSERT INTO eventos_usuario (usuario_id, evento, metadata) VALUES (?, ?, ?)");
        $stmtEv->execute([$usuario_id, 'personalizou_estudos', json_encode([
            'horas' => $horas_dia,
            'nivel_geral' => $nivel_geral,
            'edital_ativo' => $biblioteca_id > 0
        ])]);

        $db->commit();
        
        $_SESSION['diag_concluido'] = true;
        $_SESSION['perfil_estudo'] = $nivel_geral; // Para exibição no resultado
        redirect('resultado_diagnostico.php');
    } catch (Exception $e) {
        $db->rollBack();
        flashMsg('danger', 'Erro ao salvar perfil: ' . $e->getMessage());
    }
}

$page_title = 'Personalizar Meus Estudos - HackConcursos';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="dashboard-layout">
  <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>
  <main class="main-content">

    <div class="page-header d-flex ai-center jc-between">
      <div>
        <h2 style="font-weight:900;"><i class="bi bi-person-gear text-neon"></i> Personalizar Meus Estudos</h2>
        <p style="color:var(--text-secondary);">Defina seu perfil para que a IA otimize seu caminho até a aprovação.</p>
      </div>
      <?php if ($dados_edital): ?>
        <div class="text-right">
            <span class="badge-hc badge-blue">EDITAL ATIVO</span>
            <div style="font-size:0.8rem; font-weight:700; margin-top:0.3rem;"><?= sanitize($dados_edital['nome_concurso']) ?></div>
        </div>
      <?php endif; ?>
    </div>

    <form method="POST" class="animate__animated animate__fadeIn">
        <div class="grid-2 mb-lg">
            <!-- Coluna 1: Disponibilidade -->
            <div class="card-glass">
                <div class="card-header-hc">
                    <h5><i class="bi bi-clock-history text-neon"></i> Sua Rotina</h5>
                </div>
                <div class="card-body">
                    <div class="form-group mb-md">
                        <label class="form-label">Quantas horas reais você dedicará por dia?</label>
                        <div class="d-flex ai-center gap-md">
                            <input type="range" name="horas_dia" class="form-range" min="1" max="12" step="0.5" value="<?= $dados_perfil['horas_dia'] ?? 3 ?>" oninput="this.nextElementSibling.value = this.value + 'h'">
                            <output style="font-weight:900; color:var(--neon-green); font-size:1.2rem; min-width:50px;"><?= $dados_perfil['horas_dia'] ?? 3 ?>h</output>
                        </div>
                        <small class="text-muted">A constância vence a intensidade. Seja honesto consigo mesmo.</small>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Qual seu nível de experiência em concursos?</label>
                        <select name="nivel_geral" class="form-control-hc">
                            <option value="iniciante">Estou começando agora (Iniciante)</option>
                            <option value="intermediario">Já estudo há algum tempo (Intermediário)</option>
                            <option value="avancado">Já bati na trave em provas (Avançado/Ninja)</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Coluna 2: Estilo de Estudo -->
            <div class="card-glass" style="background:rgba(34,197,94,0.03); border-color:var(--border-glass);">
                <div class="card-header-hc">
                    <h5><i class="bi bi-lightning-charge text-warning"></i> Seu Estilo</h5>
                </div>
                <div class="card-body">
                    <label class="form-label">Como você prefere consumir conteúdo?</label>
                    <div class="grid-2 gap-sm">
                        <label class="chip w-100" style="padding:1rem; text-align:center;">
                            <input type="radio" name="estilo" value="video" checked style="display:none;">
                            <i class="bi bi-play-circle d-block mb-xs" style="font-size:1.2rem;"></i>
                            <span>Videoaulas</span>
                        </label>
                        <label class="chip w-100" style="padding:1rem; text-align:center;">
                            <input type="radio" name="estilo" value="pdf" style="display:none;">
                            <i class="bi bi-file-earmark-text d-block mb-xs" style="font-size:1.2rem;"></i>
                            <span>Leitura (PDF)</span>
                        </label>
                    </div>
                    <p class="mt-md" style="font-size:0.8rem; color:var(--text-muted);">
                        Isso ajuda a IA a sugerir os melhores materiais e tempos de descanso.
                    </p>
                </div>
            </div>
        </div>

        <?php if ($dados_edital && !empty($lista_disciplinas)): ?>
        <div class="card-glass">
            <div class="card-header-hc">
                <h5><i class="bi bi-list-check text-neon"></i> Diagnóstico por Matéria</h5>
            </div>
            <div class="card-body">
                <p class="mb-md" style="font-size:0.9rem; color:var(--text-secondary);">Para o concurso <strong><?= sanitize($dados_edital['nome_concurso']) ?></strong>, informe seu nível em cada disciplina:</p>
                <div style="max-height: 400px; overflow-y: auto; padding-right: 10px;">
                    <table class="table-hc">
                        <thead>
                            <tr>
                                <th>Disciplina</th>
                                <th>Seu Nível</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($lista_disciplinas as $d): ?>
                            <tr>
                                <td style="font-weight:700;"><?= sanitize($d['nome']) ?></td>
                                <td>
                                    <div class="d-flex gap-sm">
                                        <label class="chip chip-sm">
                                            <input type="radio" name="nivel[<?= $d['id'] ?>]" value="iniciante" checked style="display:none;">
                                            <span>Básico</span>
                                        </label>
                                        <label class="chip chip-sm">
                                            <input type="radio" name="nivel[<?= $d['id'] ?>]" value="intermediario" style="display:none;">
                                            <span>Médio</span>
                                        </label>
                                        <label class="chip chip-sm">
                                            <input type="radio" name="nivel[<?= $d['id'] ?>]" value="avancado" style="display:none;">
                                            <span>Top</span>
                                        </label>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php else: ?>
        <div class="card-glass" style="border-style:dashed; text-align:center; padding:2rem;">
            <div style="font-size:2rem; margin-bottom:1rem; opacity:0.5;"><i class="bi bi-search"></i></div>
            <h6>Próximo Passo: Escolher um Concurso</h6>
            <p style="font-size:0.85rem; color:var(--text-muted);">Após salvar seu perfil, você poderá escolher um edital para fazer o diagnóstico específico das matérias.</p>
        </div>
        <?php endif; ?>

        <div class="mt-lg d-flex jc-between ai-center">
            <div class="text-muted" style="font-size:0.85rem;">
                <i class="bi bi-shield-check text-neon"></i> Seus dados estão seguros e serão usados apenas pela IA.
            </div>
            <button type="submit" class="btn-hc btn-neon btn-lg shadow-neon" style="min-width:250px;">
                SALVAR E CONTINUAR <i class="bi bi-arrow-right-short"></i>
            </button>
        </div>
    </form>

  </main>
</div>

<script>
document.querySelectorAll('.chip').forEach(chip => {
    const radio = chip.querySelector('input[type="radio"]');
    
    const updateStyle = () => {
        const group = chip.closest('.d-flex') || chip.closest('.grid-2');
        group.querySelectorAll('.chip').forEach(c => {
            c.style.borderColor = 'var(--border-glass)';
            c.style.color = 'var(--text-secondary)';
            c.style.background = 'rgba(255,255,255,0.06)';
        });
        chip.style.borderColor = 'var(--neon-green)';
        chip.style.color = 'var(--neon-green)';
        chip.style.background = 'rgba(34,197,94,0.1)';
    };

    if (radio.checked) updateStyle();

    chip.addEventListener('click', function() {
        radio.checked = true;
        updateStyle();
    });
});
</script>

<style>
.form-range {
    flex: 1;
    height: 6px;
    background: rgba(255,255,255,0.1);
    border-radius: 3px;
    outline: none;
    -webkit-appearance: none;
}
.form-range::-webkit-slider-thumb {
    -webkit-appearance: none;
    width: 20px;
    height: 20px;
    background: var(--neon-green);
    border-radius: 50%;
    cursor: pointer;
    box-shadow: 0 0 10px var(--neon-green-glow);
}
.chip-sm { padding: 0.3rem 0.8rem !important; font-size: 0.75rem !important; }
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
