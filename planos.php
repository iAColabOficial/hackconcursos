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
    <h2 style="font-size:3rem; margin-bottom:1rem;">Escolha seu <span class="text-neon">Nível de Acesso</span></h2>
    <p style="color:var(--text-secondary); margin-bottom:4rem; font-size:1.15rem;">Escolha o nível de processamento ideal para hackear sua aprovação.</p>

    <div class="grid-3" style="align-items:stretch;">
      
      <!-- PLANO 1: DISCOVERY (ACESSO) -->
      <div class="card-glass" style="padding:3rem 2rem; display:flex; flex-direction:column;">
         <h3 style="margin-bottom:0.5rem;">Modo Discovery</h3>
         <div style="font-size:0.9rem; color:var(--text-muted); margin-bottom:1.5rem;">Início do mapeamento</div>
         
         <div class="fw-800" style="font-size:2.5rem; margin-bottom:2rem;">Gratuito</div>
         
         <div style="flex:1; text-align:left; margin-bottom:2rem;">
            <ul style="list-style:none; padding:0; display:flex; flex-direction:column; gap:0.85rem; font-size:0.95rem;">
               <li><i class="bi bi-check-circle text-neon"></i> 1 Edital (Alvo)</li>
               <li><i class="bi bi-check-circle text-neon"></i> Mapa de Evolução</li>
               <li><i class="bi bi-check-circle text-neon"></i> 5 Cargas de Energia (Bônus)</li>
               <li><i class="bi bi-check-circle text-neon"></i> Insights Instantâneos</li>
               <li style="opacity:0.4;"><i class="bi bi-x-circle"></i> Scanner de Padrões</li>
               <li style="opacity:0.4;"><i class="bi bi-x-circle"></i> Evolução Automática</li>
            </ul>
         </div>
         <a href="cadastro.php?plano=free" class="btn-hc btn-ghost w-100">Começar Grátis</a>
      </div>

      <!-- PLANO 2: TURBO (PERFORMANCE) -->
      <div class="card-glass" style="padding:3rem 2rem; display:flex; flex-direction:column; border-color:var(--neon-green); transform:scale(1.05); z-index:2; box-shadow: 0 0 30px rgba(34,197,94,0.15);">
         <div style="position:absolute; top:-12px; left:50%; transform:translateX(-50%);" class="badge-hc badge-neon">MAIS ESCOLHIDO</div>
         <h3 style="margin-bottom:0.5rem;">Modo Turbo</h3>
         <div style="font-size:0.9rem; color:var(--text-muted); margin-bottom:1.5rem;">Evolução em alta velocidade</div>
         
         <div class="fw-800" style="font-size:2.5rem; margin-bottom:2rem;">R$ 49,90<span style="font-size:1rem; color:var(--text-muted);">/mês</span></div>
         
         <div style="flex:1; text-align:left; margin-bottom:2rem;">
            <ul style="list-style:none; padding:0; display:flex; flex-direction:column; gap:0.85rem; font-size:0.95rem;">
               <li><i class="bi bi-check-circle text-neon"></i> 3 Editais (Alvos)</li>
               <li><i class="bi bi-check-circle text-neon"></i> 250 Cargas/mês</li>
               <li><i class="bi bi-check-circle text-neon"></i> Scanner de Padrões IA</li>
               <li><i class="bi bi-check-circle text-neon"></i> Simulados Inteligentes</li>
               <li><i class="bi bi-check-circle text-neon"></i> Projeção de Sucesso</li>
               <li><i class="bi bi-check-circle text-neon"></i> Evolução Automática</li>
            </ul>
         </div>
         <a href="cadastro.php?plano=premium" class="btn-hc btn-neon w-100">Ativar Turbo ⚡</a>
      </div>

      <!-- PLANO 3: MASTERMIND (DOMÍNIO) -->
      <div class="card-glass" style="padding:3rem 2rem; display:flex; flex-direction:column; border-color:var(--accent-purple);">
         <h3 style="margin-bottom:0.5rem;">Modo Mastermind</h3>
         <div style="font-size:0.9rem; color:var(--text-muted); margin-bottom:1.5rem;">Controle total do sistema</div>
         
         <div class="fw-800" style="font-size:2.5rem; margin-bottom:2rem;">R$ 39,90<span style="font-size:1rem; color:var(--text-muted);">/mês*</span></div>
         <div style="font-size:0.75rem; color:var(--text-muted); margin-top:-1.5rem; margin-bottom:2rem;">*cobrado anualmente</div>
         
         <div style="flex:1; text-align:left; margin-bottom:2rem;">
            <ul style="list-style:none; padding:0; display:flex; flex-direction:column; gap:0.85rem; font-size:0.95rem;">
               <li><i class="bi bi-check-circle text-neon"></i> Alvos Ilimitados</li>
               <li><i class="bi bi-check-circle text-neon"></i> 600 Cargas/mês</li>
               <li><i class="bi bi-check-circle text-neon"></i> Recalibragem Inteligente</li>
               <li><i class="bi bi-check-circle text-neon"></i> Relatórios de Domínio</li>
               <li><i class="bi bi-check-circle text-neon"></i> Suporte Prioritário</li>
            </ul>
         </div>
         <a href="cadastro.php?plano=anual" class="btn-hc btn-ai w-100">Garantir Domínio</a>
      </div>

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
