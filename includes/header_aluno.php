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
</head>
<body>
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
