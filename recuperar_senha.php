<?php
require_once __DIR__ . '/config/config.php';

$token = sanitize($_GET['token'] ?? '');
$msg = '';
$tipoMsg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['email'])) {
        // Solicitação de recuperação
        $email = sanitize($_POST['email']);
        $db = getDB();
        $q = $db->prepare("SELECT id FROM usuarios WHERE email = ?");
        $q->execute([$email]);
        if ($q->fetch()) {
            // Gerar token mockado para demonstração
            $tokenMock = bin2hex(random_bytes(16));
            $db->prepare("UPDATE usuarios SET token_recuperacao = ?, token_expira = DATE_ADD(NOW(), INTERVAL 1 HOUR) WHERE email = ?")
               ->execute([$tokenMock, $email]);
            
            $msg = "Se o e-mail estiver cadastrado, você receberá um link de recuperação em instantes. (Link demonstrativo: recuperar_senha.php?token=$tokenMock)";
            $tipoMsg = 'success';
        } else {
            $msg = "E-mail não encontrado.";
            $tipoMsg = 'danger';
        }
    } elseif (isset($_POST['nova_senha']) && !empty($token)) {
        // Redefinição de senha
        $nova = $_POST['nova_senha'];
        $conf = $_POST['confirmar_senha'];

        if ($nova !== $conf) {
            $msg = "As senhas não conferem.";
            $tipoMsg = 'danger';
        } else {
            $db = getDB();
            $hash = password_hash($nova, PASSWORD_DEFAULT);
            $upd = $db->prepare("UPDATE usuarios SET senha = ?, token_recuperacao = NULL, token_expira = NULL WHERE token_recuperacao = ?");
            $upd->execute([$hash, $token]);
            
            if ($upd->rowCount() > 0) {
                flashMsg('success', 'Senha alterada com sucesso! Faça login.');
                redirect('login.php');
            } else {
                $msg = "Token inválido ou expirado.";
                $tipoMsg = 'danger';
            }
        }
    }
}

$page_title = 'Recuperar Senha';
require_once __DIR__ . '/includes/header.php';
?>

<div class="hero-section" style="display:flex; align-items:center; justify-content:center; padding:2rem;">
    <div class="hero-glow green" style="top:20%; left:20%;"></div>
    <div class="hero-glow blue" style="bottom:10%; right:10%;"></div>

    <div class="card-glass" style="width:100%; max-width:420px; padding:2.5rem; position:relative; z-index:1;">
        <div class="text-center mb-lg">
            <a href="index.php">
                <img src="<?= APP_URL ?>/assets/img/logo.png" alt="Logo" style="height:45px; margin-bottom:1.5rem;">
            </a>
            <h2><?= empty($token) ? 'Recuperar Senha' : 'Nova Senha' ?></h2>
            <p style="color:var(--text-secondary); font-size:0.9rem; margin-top:0.5rem;">
                <?= empty($token) ? 'Informe seu e-mail para receber as instruções.' : 'Crie uma nova senha forte para sua conta.' ?>
            </p>
        </div>

        <?php if ($msg): ?>
            <div class="alert-hc alert-<?= $tipoMsg ?> mb-md" style="font-size:0.8rem;">
                <?= $msg ?>
            </div>
        <?php endif; ?>

        <?php if (empty($token)): ?>
            <form action="" method="POST">
                <div class="form-group mb-lg">
                    <label class="form-label">E-mail Cadastrado</label>
                    <div class="input-group-hc">
                        <i class="bi bi-envelope input-icon"></i>
                        <input type="email" name="email" class="form-control-hc" placeholder="seu@email.com" required>
                    </div>
                </div>
                <button type="submit" class="btn-hc btn-primary-hc w-100 btn-lg mb-md">Enviar Instruções</button>
            </form>
        <?php else: ?>
            <form action="" method="POST">
                <div class="form-group">
                    <label class="form-label">Nova Senha</label>
                    <input type="password" name="nova_senha" class="form-control-hc" placeholder="••••••••" required>
                </div>
                <div class="form-group mb-lg">
                    <label class="form-label">Confirmar Nova Senha</label>
                    <input type="password" name="confirmar_senha" class="form-control-hc" placeholder="••••••••" required>
                </div>
                <button type="submit" class="btn-hc btn-neon w-100 btn-lg mb-md">Redefinir Senha</button>
            </form>
        <?php endif; ?>

        <div class="text-center" style="font-size:0.85rem; color:var(--text-muted);">
            Lembrou a senha? <a href="login.php" class="text-blue fw-700">Fazer Login</a>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
