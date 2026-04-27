<?php
require_once __DIR__ . '/config/config.php';
iniciarSessao();

if (usuarioLogado()) {
    redirect(APP_URL . '/aluno/dashboard.php');
}

$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $senha = trim($_POST['senha'] ?? '');

    if (empty($email) || empty($senha)) {
        $erro = 'Preencha e-mail e senha.';
    } else {
        try {
            $db = getDB();
            $st = $db->prepare("SELECT * FROM usuarios WHERE email = ? AND ativo = 1 LIMIT 1");
            $st->execute([$email]);
            $user = $st->fetch();

            if ($user && password_verify($senha, $user['senha'])) {
                session_regenerate_id(true);
                $_SESSION['usuario_id']   = $user['id'];
                $_SESSION['usuario_nome'] = $user['nome'];
                $_SESSION['perfil']       = $user['perfil'];
                $_SESSION['plano']        = $user['plano'];

                // Atualizar perfil se não existir
                $pst = $db->prepare("INSERT IGNORE INTO perfis_usuario (usuario_id) VALUES (?)");
                $pst->execute([$user['id']]);

                $dest = $user['perfil'] === 'admin'
                    ? APP_URL . '/admin/index.php'
                    : APP_URL . '/aluno/dashboard.php';
                redirect($dest);
            } else {
                $erro = 'E-mail ou senha incorretos.';
            }
        } catch (PDOException $e) {
            $erro = 'Erro de sistema. Tente novamente.';
        }
    }
}

$page_title = 'Login';
$page_desc  = 'Acesse sua conta HackConcursos e continue sua jornada rumo à aprovação.';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login | HackConcursos</title>
  <meta name="description" content="<?= $page_desc ?>">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/style.css?v=<?= time() ?>">
  <style>
    .login-page { min-height:100vh; display:flex; }

    /* Lado esquerdo - visual */
    .login-visual {
      flex: 1;
      background: var(--bg-card);
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      padding: 3rem;
      position: relative;
      overflow: hidden;
    }
    .login-visual::before {
      content:'';
      position:absolute; inset:0;
      background: radial-gradient(ellipse at 60% 40%, rgba(34,197,94,0.12), transparent 70%),
                  radial-gradient(ellipse at 20% 80%, rgba(255,255,255,0.03), transparent 60%);
    }
    .visual-content { position:relative;z-index:1; max-width:440px; }
    .stat-pill {
      display:inline-flex; align-items:center; gap:0.6rem;
      background:var(--bg-glass); border:1px solid var(--border-glass);
      border-radius:var(--radius-full); padding:0.5rem 1.1rem;
      font-size:0.82rem; font-weight:600; color:var(--text-secondary);
      backdrop-filter:blur(12px);
    }
    .stat-pill strong { color:var(--neon-green); }

    /* Lado direito - form */
    .login-form-side {
      width: 480px;
      min-width: 380px;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      padding: 3rem 2.5rem;
    }
    .login-box { width:100%; max-width:400px; }
    .login-logo {
      font-size:1.75rem; font-weight:900; letter-spacing:-0.03em;
      margin-bottom:2rem; text-align:center;
    }
    .login-logo span { color:var(--neon-green); }
    .divider-text {
      display:flex; align-items:center; gap:0.75rem;
      font-size:0.78rem; color:var(--text-muted); margin:1.25rem 0;
    }
    .divider-text::before, .divider-text::after {
      content:''; flex:1; height:1px; background:var(--border-glass);
    }

    @media (max-width:768px) {
      .login-visual { display:none; }
      .login-form-side { width:100%; padding:2rem 1.5rem; }
    }
  </style>
</head>
<body>
<div class="login-page">

  <!-- Botão Voltar -->
  <a href="index.php" style="position: absolute; top: 2rem; left: 2rem; color: var(--text-secondary); display: flex; align-items: center; gap: 0.5rem; z-index: 10; text-decoration: none; font-size: 0.9rem; font-weight: 600; transition: color 0.3s;" onmouseover="this.style.color='var(--neon-green)'" onmouseout="this.style.color='var(--text-secondary)'">
    <i class="bi bi-arrow-left"></i> Voltar para o Início
  </a>

  <!-- VISUAL ESQUERDO -->
  <div class="login-visual">
    <div class="visual-content">


      <h1 style="font-size:2.5rem;font-weight:900;line-height:1.15;margin-bottom:1.25rem;">
        Sua aprovação <br><strong style="color:var(--neon-green);">começa aqui.</strong>
      </h1>
      <p style="color:var(--text-secondary);font-size:1rem;margin-bottom:2rem;">
        Transforme qualquer edital em um plano de estudos inteligente. Foque no que importa.
      </p>

      <!-- Stats -->
      <div style="display:flex;flex-direction:column;gap:0.75rem;margin-bottom:2rem;">
        <div class="stat-pill"><i class="bi bi-file-earmark-pdf-fill" style="color:var(--accent-blue)"></i> <strong>Análise automática</strong> de edital</div>
        <div class="stat-pill"><i class="bi bi-calendar-check-fill" style="color:var(--neon-green)"></i> Plano de estudos <strong>personalizado</strong></div>
        <div class="stat-pill"><i class="bi bi-patch-question-fill" style="color:var(--accent-purple)"></i> <strong>Simulados personalizados</strong> por disciplina</div>
        <div class="stat-pill"><i class="bi bi-fire" style="color:var(--warning)"></i> Gamificação e <strong>ranking em tempo real</strong></div>
      </div>

      <!-- Fake social proof -->
      <div style="display:flex;align-items:center;gap:1rem;">
        <div style="display:flex;">
          <?php foreach(['A','B','C','D','E'] as $l): ?>
          <div style="width:34px;height:34px;border-radius:50%;background:linear-gradient(135deg,var(--accent-blue),var(--accent-purple));display:flex;align-items:center;justify-content:center;font-size:0.8rem;font-weight:700;border:2px solid var(--bg-card);margin-left:-8px;color:#fff;"><?=$l?></div>
          <?php endforeach; ?>
        </div>
        <div style="font-size:0.82rem;color:var(--text-secondary);">
          <strong style="color:var(--text-primary);">+2.400 concurseiros</strong> já usam o HackConcursos
        </div>
      </div>
    </div>
  </div>

  <!-- FORMULÁRIO DIREITO -->
  <div class="login-form-side">
    <div class="login-box">
      <img src="<?= APP_URL ?>/assets/img/logo.png" alt="HackConcursos" style="width:180px;height:auto;margin-bottom:2rem;">
      <h2 style="font-size:1.5rem;font-weight:800;margin-bottom:0.35rem;">Bem-vindo de volta!</h2>
      <p style="color:var(--text-secondary);font-size:0.9rem;margin-bottom:2rem;">Entre na sua conta para continuar estudando.</p>

      <?php if ($erro): ?>
      <div class="alert-hc alert-danger" style="margin-bottom:1.25rem;">
        <i class="bi bi-x-circle"></i> <?= sanitize($erro) ?>
      </div>
      <?php endif; ?>

      <form method="POST" action="" id="form-login" novalidate>
        <div class="form-group">
          <label class="form-label" for="email">E-mail</label>
          <div class="input-group-hc">
            <i class="bi bi-envelope input-icon"></i>
            <input type="email" id="email" name="email" class="form-control-hc"
                   placeholder="seu@email.com"
                   value="<?= sanitize($_POST['email'] ?? '') ?>"
                   required autocomplete="email">
          </div>
        </div>

        <div class="form-group">
          <div class="d-flex jc-between ai-center" style="margin-bottom:0.45rem;">
            <label class="form-label" for="senha" style="margin:0;">Senha</label>
            <a href="recuperar_senha.php" style="font-size:0.8rem;color:var(--accent-blue);">Esqueci a senha</a>
          </div>
          <div class="input-group-hc">
            <i class="bi bi-lock input-icon"></i>
            <input type="password" id="senha" name="senha" class="form-control-hc"
                   placeholder="••••••••" required autocomplete="current-password"
                   style="padding-right:3rem;">
            <button type="button" onclick="toggleSenha()" style="position:absolute;right:0.9rem;top:50%;transform:translateY(-50%);background:none;border:none;color:var(--text-muted);cursor:pointer;font-size:1rem;" id="toggleEye">
              <i class="bi bi-eye" id="eyeIcon"></i>
            </button>
          </div>
        </div>

        <button type="submit" class="btn-hc btn-primary-hc btn-lg w-100" id="btn-login">
          Entrar
        </button>
      </form>

      <div class="divider-text">ou</div>

      <a href="cadastro.php" class="btn-hc btn-ghost btn-lg w-100" style="text-align:center;">
        Criar conta
      </a>

      <p style="text-align:center;font-size:0.75rem;color:var(--text-muted);margin-top:2rem;">
        Ao entrar você concorda com nossos <a href="#">Termos de Uso</a> e <a href="#">Política de Privacidade</a>.
      </p>
    </div>
  </div>
</div>

<script>
function toggleSenha() {
  const inp = document.getElementById('senha');
  const ic  = document.getElementById('eyeIcon');
  if (inp.type === 'password') {
    inp.type = 'text';
    ic.className = 'bi bi-eye-slash';
  } else {
    inp.type = 'password';
    ic.className = 'bi bi-eye';
  }
}
document.getElementById('form-login').addEventListener('submit', function() {
  const btn = document.getElementById('btn-login');
  btn.innerHTML = '<span class="loader-spinner" style="width:18px;height:18px;border-width:2px;display:inline-block;"></span> Entrando...';
  btn.disabled = true;
});
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
