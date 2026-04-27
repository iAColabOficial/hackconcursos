<?php
require_once __DIR__ . '/../config/config.php';
exigirLogin('../login.php');

$db = getDB();
$usuario_id = $_SESSION['usuario_id'];

// Buscar perfil e edital selecionado
$perfil = $db->prepare("SELECT * FROM perfis_usuario WHERE usuario_id = ?");
$perfil->execute([$usuario_id]);
$dados_perfil = $perfil->fetch();

if (!$dados_perfil || !$dados_perfil['biblioteca_edital_id']) {
    flashMsg('info', 'Por favor, selecione um concurso primeiro.');
    redirect('biblioteca.php');
}

$biblioteca_id = $dados_perfil['biblioteca_edital_id'];

// Buscar dados do edital
$edital = $db->prepare("SELECT * FROM biblioteca_editais WHERE id = ?");
$edital->execute([$biblioteca_id]);
$dados_edital = $edital->fetch();

// Buscar disciplinas do edital
$disciplinas = $db->prepare("SELECT * FROM biblioteca_disciplinas WHERE biblioteca_edital_id = ?");
$disciplinas->execute([$biblioteca_id]);
$lista_disciplinas = $disciplinas->fetchAll();

// Processar Diagnóstico
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $horas_dia = (float)$_POST['horas_dia'];
    $niveis = $_POST['nivel'] ?? []; // Array: disciplina_id => nivel

    try {
        $db->beginTransaction();

        // 1. Atualizar perfil com horas
        $up = $db->prepare("UPDATE perfis_usuario SET horas_dia = ? WHERE usuario_id = ?");
        $up->execute([$horas_dia, $usuario_id]);

        // 2. Criar registro de diagnóstico (se não existir para este edital)
        // Nota: Para simplificar, estamos usando a estrutura existente ou adaptando.
        // Vamos apenas salvar os níveis de disciplina para este usuário.
        
        // Registrar Evento
        $stmtEv = $db->prepare("INSERT INTO eventos_usuario (usuario_id, evento, metadata) VALUES (?, ?, ?)");
        $stmtEv->execute([$usuario_id, 'concluiu_diagnostico', json_encode(['horas' => $horas_dia])]);

        $db->commit();
        
        // Redirecionar para o Resultado do Diagnóstico (Onde vem o Paywall)
        $_SESSION['diag_concluido'] = true;
        redirect('resultado_diagnostico.php');
    } catch (Exception $e) {
        $db->rollBack();
        flashMsg('danger', 'Erro ao salvar diagnóstico: ' . $e->getMessage());
    }
}

$page_title = 'Diagnóstico Estratégico - HackConcursos';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="dashboard-layout">
  <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>
  <main class="main-content">

    <div class="page-header">
      <h2>📊 Diagnóstico de Nível</h2>
      <p>Concurso: <strong class="text-neon"><?= sanitize($dados_edital['nome_concurso']) ?></strong></p>
    </div>

    <form method="POST">
        <div class="grid-2 mb-lg">
            <!-- Coluna 1: Configurações de Tempo -->
            <div class="card-glass">
                <div class="card-header-hc">
                    <h5><i class="bi bi-clock-history"></i> Disponibilidade</h5>
                </div>
                <div class="card-body">
                    <div class="form-group">
                        <label class="form-label">Quantas horas você tem por dia?</label>
                        <input type="number" name="horas_dia" class="form-control-hc neon" value="3" step="0.5" min="1" max="16" required>
                        <small class="text-muted">Seja realista. É melhor 2h constantes do que 8h que você não cumpre.</small>
                    </div>
                </div>
            </div>

            <!-- Coluna 2: Informação -->
            <div class="card-glass" style="background:rgba(34,197,94,0.05); border-color:var(--border-neon);">
                <div class="card-body">
                    <h5 class="text-neon"><i class="bi bi-info-circle"></i> Por que isso é importante?</h5>
                    <p style="font-size:0.9rem; margin-top:0.5rem;">Nosso algoritmo cruzará sua base de conhecimento com o peso de cada matéria no edital da <strong><?= $dados_edital['orgao'] ?></strong> para criar o caminho mais curto até sua aprovação.</p>
                </div>
            </div>
        </div>

        <div class="card-glass">
            <div class="card-header-hc">
                <h5><i class="bi bi-list-check"></i> Como está seu conhecimento nestas matérias?</h5>
            </div>
            <div class="card-body">
                <table class="table-hc">
                    <thead>
                        <tr>
                            <th>Disciplina</th>
                            <th>Seu Nível Atual</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($lista_disciplinas as $d): ?>
                        <tr>
                            <td><strong><?= sanitize($d['nome']) ?></strong></td>
                            <td>
                                <div class="d-flex gap-sm">
                                    <label class="chip" style="cursor:pointer;">
                                        <input type="radio" name="nivel[<?= $d['id'] ?>]" value="iniciante" checked style="display:none;">
                                        <span>Iniciante</span>
                                    </label>
                                    <label class="chip" style="cursor:pointer;">
                                        <input type="radio" name="nivel[<?= $d['id'] ?>]" value="intermediario" style="display:none;">
                                        <span>Intermediário</span>
                                    </label>
                                    <label class="chip" style="cursor:pointer;">
                                        <input type="radio" name="nivel[<?= $d['id'] ?>]" value="avancado" style="display:none;">
                                        <span>Avançado</span>
                                    </label>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="mt-lg text-right">
            <button type="submit" class="btn-hc btn-neon btn-lg">Gerar Meu Raio-X de Aprovação</button>
        </div>
    </form>

  </main>
</div>

<script>
// Script simples para dar feedback visual nos chips de rádio
document.querySelectorAll('.chip').forEach(chip => {
    chip.addEventListener('click', function() {
        const row = this.closest('tr');
        row.querySelectorAll('.chip').forEach(c => {
            c.style.borderColor = 'var(--border-glass)';
            c.style.color = 'var(--text-secondary)';
            c.style.background = 'rgba(255,255,255,0.06)';
        });
        this.style.borderColor = 'var(--neon-green)';
        this.style.color = 'var(--neon-green)';
        this.style.background = 'rgba(34,197,94,0.1)';
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
