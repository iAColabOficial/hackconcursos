<?php
// Includes e configurações globais
$config_path = __DIR__ . '/../../config/config.php';
if (!file_exists($config_path)) {
    $config_path = __DIR__ . '/../config/config.php';
}
require_once $config_path;
iniciarSessao();

// Flash message
$flash = getFlash();

// Usuário logado info
$usuario_nome = $_SESSION['usuario_nome'] ?? 'Usuário';
$usuario_perfil = $_SESSION['perfil'] ?? 'aluno';
$usuario_plano = $_SESSION['plano'] ?? 'free';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <!-- Aplicar tema antes do render para evitar flash -->
  <script>
    (function(){
      const t = localStorage.getItem('hc_theme');
      if (t === 'light') document.documentElement.setAttribute('data-theme','light');
    })();
  </script>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= isset($page_title) ? sanitize($page_title) . ' | ' : '' ?>HackConcursos</title>
  <meta name="description" content="<?= isset($page_desc) ? sanitize($page_desc) : 'Plataforma de estudos inteligente para concursos públicos. IA que transforma editais em planos de estudo personalizados.' ?>">
  <!-- Google Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <!-- Bootstrap 5 CDN -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <!-- Bootstrap Icons -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <!-- Design System HackConcursos -->
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/style.css">
  <?php if (isset($extra_css)) echo $extra_css; ?>
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
