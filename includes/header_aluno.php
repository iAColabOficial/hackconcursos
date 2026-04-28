<?php
require_once __DIR__ . '/../config/config.php';
iniciarSessao();
$flash = getFlash();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <script>
        (function(){
            const t = localStorage.getItem('hc_theme');
            if (t === 'light') document.documentElement.setAttribute('data-theme','light');
            window.HC_APP_URL = '<?= APP_URL ?>';
        })();
    </script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?? 'Área do Aluno' ?> | HackConcursos</title>
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <!-- Bootstrap 5 CDN -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Design System HackConcursos -->
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/style.css?v=<?= time() ?>">
    <!-- Driver.js for Onboarding -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/driver.js@1.0.1/dist/driver.css"/>
    <style>
        :root {
            --driver-color-main: var(--neon-green);
        }
        .driver-popover {
            background-color: var(--card-bg) !important;
            border: 1px solid var(--neon-green) !important;
            color: var(--text-main) !important;
            box-shadow: 0 0 20px rgba(34, 197, 94, 0.2) !important;
        }
        .driver-popover-title { color: var(--neon-green) !important; font-weight: 800 !important; }
        .driver-popover-description { color: var(--text-secondary) !important; }
        .driver-popover-arrow-side-left { border-right-color: var(--neon-green) !important; }
        .driver-popover-arrow-side-right { border-left-color: var(--neon-green) !important; }
        .driver-popover-arrow-side-top { border-bottom-color: var(--neon-green) !important; }
        .driver-popover-arrow-side-bottom { border-top-color: var(--neon-green) !important; }
        .driver-popover-next-btn, .driver-popover-prev-btn, .driver-popover-close-btn {
            background: var(--neon-green) !important;
            color: #000 !important;
            text-shadow: none !important;
            border: none !important;
        }
    </style>
</head>
<?php
// Verificar se deve exibir onboarding
$db = getDB();
$showOnboarding = false;
if (isset($_SESSION['usuario_id'])) {
    $stO = $db->prepare("SELECT onboarding_visto FROM perfis_usuario WHERE usuario_id = ?");
    $stO->execute([$_SESSION['usuario_id']]);
    $visto = $stO->fetchColumn();
    if ($visto === 0 || $visto === '0') {
        $showOnboarding = true;
    }
}
?>
<body data-onboarding="<?= $showOnboarding ? 'true' : 'false' ?>">
<?php if ($flash): ?>
<div id="flash-msg" class="alert-hc alert-<?= $flash['tipo'] ?>" style="position:fixed;top:1rem;right:1rem;z-index:9999;max-width:380px;">
  <i class="bi bi-<?= $flash['tipo'] === 'success' ? 'check-circle' : ($flash['tipo'] === 'danger' ? 'x-circle' : 'info-circle') ?>"></i>
  <?= sanitize($flash['msg']) ?>
</div>
<script>
  setTimeout(() => {
    const el = document.getElementById('flash-msg');
    if(el) { el.style.opacity='0'; el.style.transition='opacity 0.4s'; setTimeout(()=>el.remove(),400); }
  }, 4000);
</script>
<?php endif; ?>

<!-- Scripts Globais -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/driver.js@1.0.1/dist/driver.js.iife.js"></script>
<script src="<?= APP_URL ?>/assets/js/onboarding.js?v=<?= time() ?>"></script>
