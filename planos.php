<?php
require_once __DIR__ . '/config/config.php';
$page_title = 'Nossos Planos';
require_once __DIR__ . '/includes/header.php';
?>

<!-- Navbar Pública -->
<nav class="navbar-hc">
  <div class="container d-flex jc-between ai-center" style="max-width:1200px; margin:0 auto; padding:0 1.5rem;">
    <a href="index.php" class="brand">
        <img src="<?= APP_URL ?>/assets/img/logo.png" alt="HackConcursos" style="height:40px;">
    </a>
    <div class="d-flex ai-center gap-lg">
      <a href="index.php" class="nav-link-hc">Início</a>
      <a href="login.php" class="nav-link-hc">Entrar</a>
      <a href="cadastro.php" class="btn-hc btn-primary-hc btn-sm">Criar Conta</a>
    </div>
  </div>
</nav>

<main style="padding:80px 0;">
  <div class="container" style="max-width:1100px; margin:0 auto; padding:0 1.5rem; text-align:center;">
    <h2 style="font-size:3rem; margin-bottom:1rem;">Escolha seu <span class="text-neon">Nível de Combate</span></h2>
    <p style="color:var(--text-secondary); margin-bottom:4rem; font-size:1.15rem;">Planos pensados para quem não tem tempo a perder e busca a posse o quanto antes.</p>

    <div class="grid-3" style="align-items:stretch;">
      
      <!-- PLANO 1 -->
      <div class="card-glass" style="padding:3rem 2rem; display:flex; flex-direction:column;">
         <h3 style="margin-bottom:0.5rem;">Modo Tático</h3>
         <div style="font-size:0.9rem; color:var(--text-muted); margin-bottom:1.5rem;">Para quem está começando</div>
         
         <div class="fw-800" style="font-size:2.5rem; margin-bottom:2rem;">R$ 29<span style="font-size:1rem; color:var(--text-muted);">/mês</span></div>
         
         <div style="flex:1; text-align:left; margin-bottom:2rem;">
            <ul style="list-style:none; padding:0; display:flex; flex-direction:column; gap:0.85rem; font-size:0.95rem;">
               <li><i class="bi bi-check-circle text-neon"></i> 1 Edital Analisado</li>
               <li><i class="bi bi-check-circle text-neon"></i> Plano de Estudos Semanal</li>
               <li><i class="bi bi-check-circle text-neon"></i> 5 Simulados/mês</li>
               <li style="opacity:0.4;"><i class="bi bi-x-circle"></i> Mentor IA 24/7</li>
               <li style="opacity:0.4;"><i class="bi bi-x-circle"></i> Diagnóstico Avançado</li>
            </ul>
         </div>
         <a href="cadastro.php?plano=free" class="btn-hc btn-ghost w-100">Assinar Agora</a>
      </div>

      <!-- PLANO 2 (POPULAR) -->
      <div class="card-glass" style="padding:3rem 2rem; display:flex; flex-direction:column; border-color:var(--accent-blue); transform:scale(1.05); z-index:2; box-shadow: var(--shadow-blue);">
         <div style="position:absolute; top:-12px; left:50%; transform:translateX(-50%);" class="badge-hc badge-blue">MAIS POPULAR</div>
         <h3 style="margin-bottom:0.5rem;">Modo Elite</h3>
         <div style="font-size:0.9rem; color:var(--text-muted); margin-bottom:1.5rem;">Para concurseiros dedicados</div>
         
         <div class="fw-800" style="font-size:2.5rem; margin-bottom:2rem;">R$ 59<span style="font-size:1rem; color:var(--text-muted);">/mês</span></div>
         
         <div style="flex:1; text-align:left; margin-bottom:2rem;">
            <ul style="list-style:none; padding:0; display:flex; flex-direction:column; gap:0.85rem; font-size:0.95rem;">
               <li><i class="bi bi-check-circle text-neon"></i> 3 Editais Analisados</li>
               <li><i class="bi bi-check-circle text-neon"></i> Plano de Estudos Adaptativo</li>
               <li><i class="bi bi-check-circle text-neon"></i> Simulados Ilimitados</li>
               <li><i class="bi bi-check-circle text-neon"></i> Mentor IA (10 perguntas/dia)</li>
               <li style="opacity:0.4;"><i class="bi bi-x-circle"></i> Diagnóstico de Falhas</li>
            </ul>
         </div>
         <a href="cadastro.php?plano=basico" class="btn-hc btn-primary-hc w-100">Assinar Agora</a>
      </div>

      <!-- PLANO 3 -->
      <div class="card-glass" style="padding:3rem 2rem; display:flex; flex-direction:column; border-color:var(--accent-purple);">
         <h3 style="margin-bottom:0.5rem;">Modo Guerra</h3>
         <div style="font-size:0.9rem; color:var(--text-muted); margin-bottom:1.5rem;">Para quem quer a vaga custo o que custar</div>
         
         <div class="fw-800" style="font-size:2.5rem; margin-bottom:2rem;">R$ 97<span style="font-size:1rem; color:var(--text-muted);">/mês</span></div>
         
         <div style="flex:1; text-align:left; margin-bottom:2rem;">
            <ul style="list-style:none; padding:0; display:flex; flex-direction:column; gap:0.85rem; font-size:0.95rem;">
               <li><i class="bi bi-check-circle text-neon"></i> Editais Ilimitados</li>
               <li><i class="bi bi-check-circle text-neon"></i> I.A. de Diagnóstico de Falhas</li>
               <li><i class="bi bi-check-circle text-neon"></i> Mentor IA Ilimitado</li>
               <li><i class="bi bi-check-circle text-neon"></i> Suporte em 15 minutos</li>
               <li><i class="bi bi-check-circle text-neon"></i> Acesso à Loja VIP</li>
            </ul>
         </div>
         <a href="cadastro.php?plano=premium" class="btn-hc btn-ai w-100">Assinar Agora ⚡</a>
      </div>

    </div>

    <div class="mt-lg pt-lg" style="color:var(--text-muted); font-size:0.85rem;">
        <p><i class="bi bi-shield-lock"></i> Pagamento 100% seguro via Stripe ou PIX.</p>
        <p>Cancele a qualquer momento, sem taxas ocultas.</p>
    </div>
  </div>
</main>

<footer style="padding:60px 0; border-top:1px solid var(--border-glass);">
    <div class="container d-flex jc-between ai-center" style="max-width:1200px; margin:0 auto; padding:0 1.5rem;">
        <div>
            <img src="<?= APP_URL ?>/assets/img/logo.png" style="height:35px; margin-bottom:1rem; opacity:0.8;">
            <p style="font-size:0.8rem; color:var(--text-muted);">© 2026 HackConcursos. Todos os direitos reservados.</p>
        </div>
        <div class="d-flex gap-lg">
            <a href="index.php" class="text-muted" style="font-size:0.85rem;">Início</a>
            <a href="login.php" class="text-muted" style="font-size:0.85rem;">Login</a>
            <a href="cadastro.php" class="text-muted" style="font-size:0.85rem;">Cadastro</a>
        </div>
    </div>
</footer>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
