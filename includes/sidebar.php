<?php
/**
 * HackConcursos - Sidebar do Dashboard
 * Incluída em todas as páginas da área do aluno e admin
 */
$current_page = basename($_SERVER['PHP_SELF']);
$is_admin = ($_SESSION['perfil'] ?? '') === 'admin';
$base = APP_URL . ($is_admin ? '/admin' : '/aluno');

function sidebarItem(string $href, string $icon, string $label, string $current, ?string $badge = null): string {
    $active = (basename($href) === $current) ? ' active' : '';
    $b = $badge ? "<span class='badge'>{$badge}</span>" : '';
    return "<a href='{$href}' class='sidebar-item{$active}'><span class='icon'><i class='bi bi-{$icon}'></i></span>{$label}{$b}</a>";
}
?>
<aside class="sidebar-hc" id="sidebar">
  <div class="sidebar-brand" style="padding-bottom:1.25rem;">
  <a href="<?= APP_URL ?>/aluno/dashboard.php">
    <img src="<?= APP_URL ?>/assets/img/logo.png" alt="HackConcursos" style="width:140px;height:auto;display:block;">
  </a>
</div>
  <nav class="sidebar-menu">

<?php if (!$is_admin): ?>
    <div class="sidebar-section-label">Início</div>
    <?= sidebarItem($base . '/dashboard.php',     'grid-fill',       'Dashboard',         $current_page) ?>
    <?= sidebarItem($base . '/meus_concursos.php', 'trophy-fill',     'Meus Concursos',    $current_page) ?>
    <?= sidebarItem($base . '/biblioteca.php',     'search',          'Escolher Concurso', $current_page) ?>
    <?= sidebarItem($base . '/plano_estudos.php', 'calendar3',       'Plano de Estudos',  $current_page) ?>

    <div class="sidebar-section-label">Aprender</div>
    <?= sidebarItem($base . '/simulados.php',     'patch-question',  'Simulados',         $current_page) ?>
    <?= sidebarItem($base . '/chat_edital.php',   'stars',           'Tira-Dúvidas IA',  $current_page) ?>
    <?= sidebarItem($base . '/diagnostico.php',   'clipboard-data',  'Diagnóstico',       $current_page) ?>

    <div class="sidebar-section-label">Loja</div>
    <?= sidebarItem($base . '/loja.php',          'bag-heart',       'Materiais',         $current_page) ?>

    <div class="sidebar-section-label">Conta</div>
    <?= sidebarItem($base . '/perfil.php',        'person-circle',   'Meu Perfil',        $current_page) ?>
    <?= sidebarItem($base . '/meu_plano.php',     'credit-card',     'Meu Plano',         $current_page) ?>

<?php else: ?>
    <div class="sidebar-section-label">Administração</div>
    <?= sidebarItem($base . '/index.php',    'speedometer2',  'Painel Admin',        $current_page) ?>
    <?= sidebarItem($base . '/usuarios.php', 'people-fill',   'Usuários',            $current_page) ?>
    <?= sidebarItem($base . '/biblioteca.php', 'journal-bookmark-fill', 'Biblioteca IA', $current_page) ?>
    <?= sidebarItem($base . '/editais.php',  'file-text-fill','Editais Enviados',    $current_page) ?>
    <?= sidebarItem($base . '/produtos.php', 'shop',          'Produtos da Loja',    $current_page) ?>
    <?= sidebarItem($base . '/relatorios.php','graph-up',     'Relatórios',          $current_page) ?>
    <?= sidebarItem($base . '/crawler_control.php', 'cpu-fill', 'Crawler PCI',       $current_page) ?>

    <div class="sidebar-section-label">Conta</div>
<?php endif; ?>

    <a href="<?= APP_URL ?>/logout.php" class="sidebar-item" style="color:var(--danger)">
      <span class="icon"><i class="bi bi-box-arrow-left"></i></span>Sair
    </a>
  </nav>

  <div style="padding:1rem 1.25rem;border-top:1px solid var(--border-glass);">
    <?php
    $p = $_SESSION['plano'] ?? 'free';
    $tokens = $_SESSION['token_saldo'] ?? 0;
    
    $plano_label = ['free'=>'Plano Gratuito','premium'=>'Modo Guerra ⚡'];
    $plano_class = ['free'=>'badge-blue','premium'=>'badge-neon'];
    ?>
    <div style="font-size:0.75rem;color:var(--text-muted);margin-bottom:0.35rem;">Plano atual</div>
    <span class="badge-hc <?= $plano_class[$p] ?>"><?= $plano_label[$p] ?></span>
    
    <?php if ($p !== 'premium'): ?>
    <div class="mt-xs" style="font-size:0.75rem; color:var(--accent-purple); margin-top:0.5rem;">
        <i class="bi bi-coin"></i> <?= $tokens ?> Tokens IA
    </div>
    <a href="<?= APP_URL ?>/planos.php" class="btn-hc btn-neon btn-sm mt-xs w-100" style="margin-top:0.6rem;">
       🚀 Upgrade
    </a>
    <?php endif; ?>

    <!-- Toggle de Tema -->
    <div style="margin-top:0.85rem;padding-top:0.85rem;border-top:1px solid var(--border-glass);">
      <button id="theme-toggle" onclick="toggleTheme()"
        style="width:100%;display:flex;align-items:center;justify-content:space-between;background:rgba(255,255,255,0.04);border:1px solid var(--border-glass);border-radius:var(--radius-md);padding:0.55rem 0.85rem;cursor:pointer;font-family:var(--font-main);font-size:0.8rem;font-weight:600;color:var(--text-secondary);transition:all 0.25s;">
        <span id="theme-label" style="display:flex;align-items:center;gap:0.5rem;">
          <i class="bi bi-moon-stars-fill" id="theme-icon"></i>
          <span id="theme-text">Tema Escuro</span>
        </span>
        <div id="theme-pill" style="width:32px;height:18px;border-radius:9px;background:var(--border-glass);position:relative;transition:background 0.3s;">
          <div id="theme-knob" style="width:12px;height:12px;border-radius:50%;background:var(--text-muted);position:absolute;top:3px;left:3px;transition:all 0.3s;"></div>
        </div>
      </button>
    </div>
  </div>
</aside>

<script>
(function() {
  // Aplicar tema salvo ao carregar (evita flash)
  const saved = localStorage.getItem('hc_theme') || 'dark';
  if (saved === 'light') {
    document.documentElement.setAttribute('data-theme', 'light');
  }
})();

function toggleTheme() {
  const current = document.documentElement.getAttribute('data-theme');
  const next    = current === 'light' ? 'dark' : 'light';

  document.documentElement.setAttribute('data-theme', next);
  localStorage.setItem('hc_theme', next);
  atualizarBotaoTema(next);
}

function atualizarBotaoTema(tema) {
  const icon  = document.getElementById('theme-icon');
  const text  = document.getElementById('theme-text');
  const pill  = document.getElementById('theme-pill');
  const knob  = document.getElementById('theme-knob');

  if (tema === 'light') {
    icon.className  = 'bi bi-sun-fill';
    icon.style.color= '#D97706';
    text.textContent= 'Tema Claro';
    pill.style.background = '#22C55E';
    knob.style.left = '17px';
    knob.style.background = '#fff';
  } else {
    icon.className  = 'bi bi-moon-stars-fill';
    icon.style.color= '';
    text.textContent= 'Tema Escuro';
    pill.style.background = 'var(--border-glass)';
    knob.style.left = '3px';
    knob.style.background = 'var(--text-muted)';
  }
}

// Inicializar visual do botão conforme tema salvo
document.addEventListener('DOMContentLoaded', function() {
  const saved = localStorage.getItem('hc_theme') || 'dark';
  document.documentElement.setAttribute('data-theme', saved);
  atualizarBotaoTema(saved);
});
</script>
