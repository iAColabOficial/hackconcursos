<?php
require_once __DIR__ . '/config/config.php';
iniciarSessao();
if (usuarioLogado()) redirect(APP_URL . '/aluno/dashboard.php');

$erro = '';
$sucesso = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome  = trim($_POST['nome'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $senha = trim($_POST['senha'] ?? '');
    $conf  = trim($_POST['confirmar_senha'] ?? '');

    if (empty($nome) || empty($email) || empty($senha)) {
        $erro = 'Preencha todos os campos.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erro = 'E-mail inválido.';
    } elseif (strlen($senha) < 8) {
        $erro = 'A senha deve ter no mínimo 8 caracteres.';
    } elseif ($senha !== $conf) {
        $erro = 'As senhas não coincidem.';
    } else {
        try {
            $db = getDB();
            $chk = $db->prepare("SELECT id FROM usuarios WHERE email = ?");
            $chk->execute([$email]);
            if ($chk->fetch()) {
                $erro = 'Este e-mail já está cadastrado.';
            } else {
                $hash = password_hash($senha, PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]);
                $ins = $db->prepare("INSERT INTO usuarios (nome, email, senha) VALUES (?,?,?)");
                $ins->execute([$nome, $email, $hash]);
                $uid = $db->lastInsertId();
                $db->prepare("INSERT INTO perfis_usuario (usuario_id) VALUES (?)")->execute([$uid]);
                
                // Auto-login
                iniciarSessao();
                $_SESSION['usuario_id'] = $uid;
                $_SESSION['nome']       = $nome;
                $_SESSION['email']      = $email;
                $_SESSION['perfil']     = 'aluno';
                $_SESSION['plano']      = 'free';
                
                redirect(APP_URL . '/aluno/biblioteca.php');
            }
        } catch (PDOException $e) {
            $erro = 'Erro ao criar conta. Tente novamente.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Criar Conta | HackConcursos</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/style.css?v=<?= time() ?>">
  <style>
    .cadastro-page { min-height:100vh; display:flex; align-items:center; justify-content:center; padding:2rem 1rem; }
    .cadastro-box  { width:100%; max-width:480px; }
    .plan-option {
      border:1px solid var(--border-glass); border-radius:var(--radius-md);
      padding:0.85rem 1.1rem; cursor:pointer; transition:all var(--transition);
      display:flex; align-items:center; gap:0.75rem; margin-bottom:0.5rem;
    }
    .plan-option:hover, .plan-option.selected { border-color:var(--neon-green); background:rgba(34,197,94,0.05); }
    .plan-option input[type=radio] { accent-color:var(--neon-green); }
    .strength-bar { height:4px; border-radius:4px; margin-top:6px; transition: width 0.3s; }
  </style>
</head>
<body>
<div class="cadastro-page">
  <!-- Botão Voltar -->
  <a href="index_new.php" style="position: absolute; top: 2rem; left: 2rem; color: var(--text-secondary); display: flex; align-items: center; gap: 0.5rem; z-index: 10; text-decoration: none; font-size: 0.9rem; font-weight: 600; transition: color 0.3s;" onmouseover="this.style.color='var(--neon-green)'" onmouseout="this.style.color='var(--text-secondary)'">
    <i class="bi bi-arrow-left"></i> Voltar para o Início
  </a>

  <div class="cadastro-box">
    <div style="text-align:center;margin-bottom:2rem;">
      <a href="<?= APP_URL ?>">
        <img src="<?= APP_URL ?>/assets/img/logo.png" alt="HackConcursos" style="width:160px;height:auto;margin-bottom:1rem;">
      </a>
      <h2 style="font-size:1.4rem;font-weight:800;margin-top:1rem;margin-bottom:0.35rem;">Crie sua conta</h2>
      <p style="color:var(--text-secondary);font-size:0.88rem;">Comece hoje. Aprovação amanhã.</p>
    </div>

    <?php if ($sucesso): ?>
    <div class="alert-hc alert-success" style="text-align:center;padding:1.5rem;">
      <div style="font-size:2rem;margin-bottom:0.5rem;">🎉</div>
      <strong>Conta criada com sucesso!</strong><br>
      <span style="font-size:0.88rem;color:var(--text-secondary);">Faça login e comece sua jornada.</span>
      <div style="margin-top:1.25rem;">
        <a href="login.php" class="btn-hc btn-neon btn-lg">Entrar agora</a>
      </div>
    </div>
    <?php else: ?>

    <?php if ($erro): ?>
    <div class="alert-hc alert-danger mb-sm"><i class="bi bi-x-circle"></i> <?= sanitize($erro) ?></div>
    <?php endif; ?>

    <form method="POST" action="" id="form-cad" novalidate>
      <div class="card-glass" style="padding:2rem;">

        <div class="form-group">
          <label class="form-label" for="nome">Nome completo</label>
          <div class="input-group-hc">
            <i class="bi bi-person input-icon"></i>
            <input type="text" id="nome" name="nome" class="form-control-hc neon"
                   placeholder="Seu nome" value="<?= sanitize($_POST['nome'] ?? '') ?>" required>
          </div>
        </div>

        <div class="form-group">
          <label class="form-label" for="email">E-mail</label>
          <div class="input-group-hc">
            <i class="bi bi-envelope input-icon"></i>
            <input type="email" id="email" name="email" class="form-control-hc neon"
                   placeholder="seu@email.com" value="<?= sanitize($_POST['email'] ?? '') ?>" required>
          </div>
        </div>

        <div class="form-group">
          <label class="form-label" for="senha">Senha</label>
          <div class="input-group-hc">
            <i class="bi bi-lock input-icon"></i>
            <input type="password" id="senha" name="senha" class="form-control-hc neon"
                   placeholder="Mínimo 8 caracteres" required id="senhaInp" oninput="checkStrength(this.value)">
          </div>
          <div id="strengthWrap" style="display:none;margin-top:6px;">
            <div style="height:4px;border-radius:4px;background:rgba(255,255,255,0.06);">
              <div id="strengthBar" class="strength-bar" style="width:0;"></div>
            </div>
            <div id="strengthLabel" style="font-size:0.72rem;margin-top:4px;"></div>
          </div>
        </div>

        <div class="form-group">
          <label class="form-label" for="confirmar_senha">Confirmar Senha</label>
          <div class="input-group-hc">
            <i class="bi bi-lock-fill input-icon"></i>
            <input type="password" id="confirmar_senha" name="confirmar_senha" class="form-control-hc neon" placeholder="Repita a senha" required>
          </div>
        </div>

        <button type="submit" class="btn-hc btn-neon btn-lg w-100" id="btn-cad" style="margin-top:0.5rem;">
          Criar Conta
        </button>
      </div>
    </form>

    <p style="text-align:center;margin-top:1.25rem;font-size:0.85rem;color:var(--text-secondary);">
      Já tem uma conta? <a href="login.php" style="color:var(--neon-green);font-weight:700;">Fazer login</a>
    </p>
    <?php endif; ?>
  </div>
</div>

<script>
function checkStrength(v) {
  const wrap = document.getElementById('strengthWrap');
  const bar  = document.getElementById('strengthBar');
  const lbl  = document.getElementById('strengthLabel');
  if (!v) { wrap.style.display='none'; return; }
  wrap.style.display='block';
  let score = 0;
  if (v.length >= 8) score++;
  if (/[A-Z]/.test(v)) score++;
  if (/[0-9]/.test(v)) score++;
  if (/[^A-Za-z0-9]/.test(v)) score++;
  const levels = [
    {w:'25%',  bg:'#EF4444', t:'Muito fraca'},
    {w:'50%',  bg:'#F59E0B', t:'Fraca'},
    {w:'75%',  bg:'#3B82F6', t:'Boa'},
    {w:'100%', bg:'#22C55E', t:'Forte 💪'},
  ];
  const l = levels[score-1] || levels[0];
  bar.style.width  = l.w;
  bar.style.background = l.bg;
  lbl.style.color  = l.bg;
  lbl.textContent  = l.t;
}
document.getElementById('form-cad').addEventListener('submit', function(){
  const btn = document.getElementById('btn-cad');
  btn.innerHTML = 'Criando...';
  btn.disabled = true;
});
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
