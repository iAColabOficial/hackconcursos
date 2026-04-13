<?php
require_once __DIR__ . '/../config/config.php';
exigirLogin('../public/login.php');

$db  = getDB();
$uid = (int)$_SESSION['usuario_id'];

// Carregar dados completos do usuário e perfil
$userQ = $db->prepare("
    SELECT u.*, p.horas_dia, p.dias_semana, p.objetivo, p.nivel_geral, p.sequencia_dias, p.total_horas
    FROM usuarios u
    LEFT JOIN perfis_usuario p ON p.usuario_id = u.id
    WHERE u.id = ?
");
$userQ->execute([$uid]);
$usr = $userQ->fetch();

// Buscar conquistas
$conqQ = $db->prepare("SELECT * FROM conquistas WHERE usuario_id = ? ORDER BY conquistada_em DESC");
$conqQ->execute([$uid]);
$conquistas = $conqQ->fetchAll();

// Mocks de conquistas se não houver
if (empty($conquistas)) {
   $mocks = [
       ['primeiro_edital', 'Primeiro Passo', 'Enviou o primeiro edital para o Hack.', '📜'],
       ['streak_3', 'Sem Parar', 'Estudou por 3 dias seguidos.', '🔥'],
       ['simulado_100', 'Mestre Supremo', 'Gabaritou um simulado tático.', '🎓']
   ];
   $ins = $db->prepare("INSERT INTO conquistas (usuario_id, tipo, titulo, descricao, icone) VALUES (?,?,?,?,?)");
   foreach ($mocks as $m) $ins->execute(array_merge([$uid], $m));
   
   $conqQ->execute([$uid]);
   $conquistas = $conqQ->fetchAll();
}

$page_title = 'Meu Perfil';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="dashboard-layout" style="position:relative;z-index:1;">
  <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>
  <main class="main-content">

    <div class="page-header">
      <div class="page-breadcrumb"><a href="dashboard.php">Dashboard</a><span class="sep">›</span> Perfil</div>
      <h2>⚙️ Configurações de Perfil</h2>
      <p>Gerencie seus dados pessoais, preferências de estudo e veja suas conquistas.</p>
    </div>

    <div class="grid-2" style="grid-template-columns: 320px 1fr; gap: 2rem; align-items: start;">
        <!-- Coluna Esquerda: Avatar e Stats -->
        <div style="display:flex; flex-direction:column; gap:1.5rem;">
            <div class="card-glass" style="text-align:center; padding:2rem;">
                <div style="position:relative; width:120px; height:120px; margin:0 auto 1.5rem;">
                    <div style="width:100%; height:100%; border-radius:50%; background:linear-gradient(135deg, var(--accent-blue), var(--accent-purple)); display:flex; align-items:center; justify-content:center; font-size:3rem; font-weight:800; color:#fff; border:4px solid var(--border-glass);">
                        <?= strtoupper(substr($usr['nome'], 0, 1)) ?>
                    </div>
                    <button class="btn-hc btn-primary-hc btn-sm" style="position:absolute; bottom:0; right:0; padding:0.3rem; border-radius:50%; width:32px; height:32px;">
                        Editar
                    </button>
                </div>
                <h3 style="margin-bottom:0.25rem;"><?= sanitize($usr['nome']) ?></h3>
                <div class="badge-hc badge-purple mb-md">PLANO <?= strtoupper($usr['plano']) ?></div>
                
                <div style="background:rgba(255,255,255,0.03); padding:1rem; border-radius:var(--radius-md); text-align:left;">
                    <div class="d-flex jc-between mb-xs">
                        <span style="font-size:0.8rem; color:var(--text-muted);">E-mail:</span>
                        <span style="font-size:0.85rem; font-weight:600;"><?= sanitize($usr['email']) ?></span>
                    </div>
                    <div class="d-flex jc-between">
                        <span style="font-size:0.8rem; color:var(--text-muted);">Membro desde:</span>
                        <span style="font-size:0.85rem; font-weight:600;"><?= date('d/m/Y', strtotime($usr['criado_em'])) ?></span>
                    </div>
                </div>
            </div>

            <!-- Stats Rápidos -->
            <div class="card-glass" style="padding:1.5rem;">
                <h5 class="mb-md">Desempenho</h5>
                <div style="display:flex; flex-direction:column; gap:0.8rem;">
                    <div class="d-flex jc-between ai-center">
                        <span style="font-size:0.85rem; color:var(--text-secondary);">Total Horas</span>
                        <span class="fw-800 text-neon"><?= number_format($usr['total_horas'], 1) ?>h</span>
                    </div>
                    <div class="d-flex jc-between ai-center">
                        <span style="font-size:0.85rem; color:var(--text-secondary);">Sequência</span>
                        <span class="fw-800 text-warning"> <?= $usr['sequencia_dias'] ?> Dias</span>
                    </div>
                    <div class="d-flex jc-between ai-center">
                        <span style="font-size:0.85rem; color:var(--text-secondary);">Nível</span>
                        <span class="badge-hc badge-blue"><?= ucfirst($usr['nivel_geral']) ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Coluna Direita: Forms e Conquistas -->
        <div style="display:flex; flex-direction:column; gap:2rem;">
            <!-- Tabs Mock -->
            <div class="card-glass">
                <div class="card-header-hc" style="display:flex; gap:1.5rem; border-bottom:1px solid var(--border-glass);">
                    <button class="tab-btn active" onclick="switchTab('dados')">Meus Dados</button>
                    <button class="tab-btn" onclick="switchTab('conquistas')">Minhas Conquistas</button>
                    <button class="tab-btn" onclick="switchTab('seguranca')">Segurança</button>
                </div>
                
                <!-- TAB: DADOS -->
                <div class="card-body tab-content" id="tab-dados">
                    <form action="" method="POST">
                        <div class="grid-2">
                            <div class="form-group">
                                <label class="form-label">Nome Completo</label>
                                <input type="text" class="form-control-hc" value="<?= sanitize($usr['nome']) ?>" name="nome">
                            </div>
                            <div class="form-group">
                                <label class="form-label">E-mail</label>
                                <input type="email" class="form-control-hc" value="<?= sanitize($usr['email']) ?>" readonly style="opacity:0.6; cursor:not-allowed;">
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Objetivo de Estudo (Ex: Concurso PF)</label>
                            <input type="text" class="form-control-hc" value="<?= sanitize($usr['objetivo']) ?>" name="objetivo" placeholder="Para qual órgão ou cargo você foca?">
                        </div>
                        <div class="d-flex jc-end mt-sm">
                            <button type="button" class="btn-hc btn-primary-hc" onclick="alert('Dados atualizados (demonstração)')">Salvar Alterações</button>
                        </div>
                    </form>
                </div>

                <!-- TAB: CONQUISTAS -->
                <div class="card-body tab-content" id="tab-conquistas" style="display:none;">
                    <div class="grid-2">
                        <?php foreach ($conquistas as $c): ?>
                        <div style="background:rgba(255,255,255,0.03); border:1px solid var(--border-glass); border-radius:var(--radius-md); padding:1rem; display:flex; gap:1rem; align-items:center;">
                            <div style="font-size:2rem; width:50px; height:50px; display:flex; align-items:center; justify-content:center; background:rgba(0,0,0,0.2); border-radius:50%;">
                                <?= $c['icone'] ?>
                            </div>
                            <div style="flex:1;">
                                <div class="fw-700" style="font-size:0.9rem;"><?= sanitize($c['titulo']) ?></div>
                                <div style="font-size:0.75rem; color:var(--text-secondary);"><?= sanitize($c['descricao']) ?></div>
                                <div style="font-size:0.65rem; color:var(--text-muted); margin-top:0.25rem;">Conquistado em <?= date('d/m/Y', strtotime($c['conquistada_em'])) ?></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- TAB: SEGURANÇA -->
                <div class="card-body tab-content" id="tab-seguranca" style="display:none;">
                    <form action="" method="POST">
                        <div class="form-group">
                            <label class="form-label">Senha Atual</label>
                            <input type="password" class="form-control-hc" placeholder="••••••••">
                        </div>
                        <div class="grid-2">
                            <div class="form-group">
                                <label class="form-label">Nova Senha</label>
                                <input type="password" class="form-control-hc" placeholder="••••••••">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Confirmar Nova Senha</label>
                                <input type="password" class="form-control-hc" placeholder="••••••••">
                            </div>
                        </div>
                        <div class="d-flex jc-end mt-sm">
                            <button type="button" class="btn-hc btn-danger-hc" onclick="alert('Funcionalidade em desenvolvimento')">Alterar Senha</button>
                        </div>
                    </form>
                </div>

            </div>
        </div>
    </div>

  </main>
</div>

<style>
.tab-btn {
    background:none; border:none; color:var(--text-muted); padding:1.25rem 0.5rem; font-weight:700; font-size:0.85rem; text-transform:uppercase; cursor:pointer; position:relative; transition:all 0.2s;
}
.tab-btn.active { color:var(--accent-blue); }
.tab-btn.active::after {
    content:''; position:absolute; bottom:-1px; left:0; right:0; height:2px; background:var(--accent-blue);
}
.tab-btn:hover { color:var(--text-primary); }
</style>

<script>
function switchTab(tab) {
    document.querySelectorAll('.tab-content').forEach(el => el.style.display = 'none');
    document.querySelectorAll('.tab-btn').forEach(el => el.classList.remove('active'));
    
    document.getElementById('tab-' + tab).style.display = 'block';
    event.target.classList.add('active');
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
