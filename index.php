<?php
require_once __DIR__ . '/config/config.php';
$page_title = 'Aprovação Tática com Inteligência Artificial';
require_once __DIR__ . '/includes/header.php';
?>

<!-- Navbar Pública -->
<nav class="navbar-hc">
  <div class="container d-flex jc-between ai-center" style="max-width:1200px; margin:0 auto; padding:0 1.5rem;">
    <a href="index.php" class="brand">
        <img src="<?= APP_URL ?>/assets/img/logo.png" alt="HackConcursos" style="height:40px;">
    </a>
    <div class="d-flex ai-center gap-lg">
      <a href="planos.php" class="nav-link-hc">Planos</a>
      <a href="login.php" class="nav-link-hc">Entrar</a>
      <a href="cadastro.php" class="btn-hc btn-primary-hc btn-sm">Criar Conta</a>
    </div>
  </div>
</nav>

<main>
  <!-- HERO SECTION -->
  <section class="hero-section">
    <div class="hero-glow green"></div>
    <div class="hero-glow blue"></div>
    
    <div class="container" style="max-width:1200px; margin:0 auto; padding:0 1.5rem; position:relative; z-index:1;">
      <div class="grid-2 ai-center">
        <div>
          <div class="hero-badge"><i class="bi bi-stars"></i> O Futuro dos Concursos Chegou</div>
          <h1 class="hero-title lh-sm">Transforme qualquer edital em um <strong>plano de estudos inteligente.</strong></h1>
          <p class="hero-subtitle">Abandone os métodos tradicionais. Nossa IA analisa o edital em segundos e cria uma estratégia tática focada no que realmente cai na prova.</p>
          <div class="d-flex gap-md">
            <a href="cadastro.php" class="btn-hc btn-neon btn-lg">Começar Agora</a>
            <a href="#como-funciona" class="btn-hc btn-ghost btn-lg">Ver como funciona</a>
          </div>
          <div class="mt-lg d-flex ai-center gap-md" style="opacity:0.7;">
            <div style="display:flex; -webkit-mask-image: linear-gradient(to right, black 80%, transparent); mask-image: linear-gradient(to right, black 80%, transparent);">
                <img src="https://i.pravatar.cc/40?u=1" style="border-radius:50%; border:2px solid var(--bg-dark); margin-right:-10px;">
                <img src="https://i.pravatar.cc/40?u=2" style="border-radius:50%; border:2px solid var(--bg-dark); margin-right:-10px;">
                <img src="https://i.pravatar.cc/40?u=3" style="border-radius:50%; border:2px solid var(--bg-dark); margin-right:-10px;">
                <img src="https://i.pravatar.cc/40?u=4" style="border-radius:50%; border:2px solid var(--bg-dark);">
            </div>
            <span style="font-size:0.85rem;">+1.200 concurseiros em <strong>Modo Guerra</strong></span>
          </div>
        </div>
        
        <div style="position:relative;">
           <div class="card-glass" style="padding:1rem; transform: rotate(2deg); border-color:var(--accent-blue); box-shadow: var(--shadow-blue);">
              <div class="card-header-hc" style="border:none; padding-bottom:0.5rem;">
                <div class="badge-hc badge-blue">EDITAL ANALISADO</div>
              </div>
              <div style="padding:1rem;">
                <h4 style="margin-bottom:1rem;">Polícia Federal (Escrivão)</h4>
                <div style="display:flex; flex-direction:column; gap:0.5rem;">
                    <div class="progress-hc"><div class="progress-bar-fill progress-blue" style="width:75%;"></div></div>
                    <div class="progress-hc"><div class="progress-bar-fill progress-purple" style="width:40%;"></div></div>
                    <div class="progress-hc"><div class="progress-bar-fill" style="width:90%;"></div></div>
                </div>
              </div>
           </div>
           <!-- Elemento Flutuante -->
           <div class="card-glass" style="position:absolute; bottom:-2rem; left:-2rem; padding:1.5rem; width:220px; border-color:var(--neon-green); transform: rotate(-3deg); box-shadow: var(--shadow-neon);">
              <div class="text-neon fw-800" style="font-size:1.5rem;">88%</div>
              <div style="font-size:0.8rem; color:var(--text-secondary);">Taxa de acerto sugerida pelo mentor IA</div>
           </div>
        </div>
      </div>
    </div>
  </section>

  <!-- FEATURES SECTION -->
  <section id="como-funciona" style="padding:100px 0; position:relative; background:rgba(255,255,255,0.02);">
    <div class="container" style="max-width:1200px; margin:0 auto; padding:0 1.5rem; text-align:center;">
      <h2 style="margin-bottom:1rem;">O Metodologia <span class="text-neon">Hack</span></h2>
      <p style="color:var(--text-secondary); max-width:700px; margin:0 auto 4rem;">Combinamos inteligência artificial de ponta com as melhores técnicas de estudo para criar um caminho rápido até a posse.</p>
      
      <div class="grid-3">
        <div class="card-glass" style="padding:2.5rem 2rem;">
            <div class="kpi-icon blue mb-md" style="width:64px; height:64px; font-size:2rem; margin:0 auto 1.5rem;"><i class="bi bi-file-earmark-pdf"></i></div>
            <h3>Análise Instantânea</h3>
            <p style="color:var(--text-secondary); font-size:0.95rem;">Envie o PDF do edital e nossa IA extrai automaticamente pesos, matérias e tópicos complexos.</p>
        </div>
        <div class="card-glass" style="padding:2.5rem 2rem; border-color:var(--accent-purple);">
            <div class="kpi-icon purple mb-md" style="width:64px; height:64px; font-size:2rem; margin:0 auto 1.5rem;"><i class="bi bi-calendar3"></i></div>
            <h3>Ciclos Dinâmicos</h3>
            <p style="color:var(--text-secondary); font-size:0.95rem;">Cronograma que se ajusta à sua rotina real. Revisões de 24h, 7d e 30d geradas automaticamente.</p>
        </div>
        <div class="card-glass" style="padding:2.5rem 2rem; border-color:var(--neon-green);">
            <div class="kpi-icon neon mb-md" style="width:64px; height:64px; font-size:2rem; margin:0 auto 1.5rem;"><i class="bi bi-robot"></i></div>
            <h3>Mentor 24/7</h3>
            <p style="color:var(--text-secondary); font-size:0.95rem;">Dúvida sobre o edital? Pergunte ao nosso Mentor IA e receba respostas baseadas no documento oficial.</p>
        </div>
      </div>
    </div>
  </section>

  <!-- CTA FINAl -->
  <section style="padding:100px 0; position:relative; overflow:hidden;">
    <!-- Background Glow -->
    <div style="position:absolute; top:50%; left:50%; transform:translate(-50%, -50%); width:600px; height:600px; background:radial-gradient(circle, rgba(34,197,94,0.1), transparent 70%); z-index:0;"></div>
    
    <div class="container" style="max-width:900px; margin:0 auto; padding:0 1.5rem; position:relative; z-index:1;">
        <div class="card-glass" style="padding:5rem 3rem; text-align:center; border-color:rgba(34,197,94,0.2); background:rgba(15,23,42,0.6); backdrop-filter:blur(20px);">
            <h2 style="font-size:3.5rem; line-height:1.1; margin-bottom:1.5rem; font-weight:900;">
                Pronto para entrar em <br>
                <span class="text-neon" style="text-shadow: 0 0 20px rgba(34,197,94,0.4);">Modo Guerra</span>?
            </h2>
            <p style="color:var(--text-primary); font-size:1.25rem; margin:0 auto 3rem; max-width:600px; opacity:0.9; line-height:1.6;">
                Junte-se a centenas de alunos que estão estudando de forma tática e abandonaram a procrastinação de vez.
            </p>
            <div class="d-flex jc-center">
                <a href="cadastro.php" class="btn-hc btn-neon btn-xl" style="padding:1.25rem 3.5rem; font-size:1.1rem; box-shadow: 0 0 30px rgba(34,197,94,0.3);">
                    COMEÇAR AGORA
                </a>
            </div>
        </div>
    </div>
  </section>
</main>

<footer style="padding:60px 0; border-top:1px solid var(--border-glass); background:rgba(0,0,0,0.2);">
    <div class="container d-flex jc-between ai-center" style="max-width:1200px; margin:0 auto; padding:0 1.5rem;">
        <div>
            <img src="<?= APP_URL ?>/assets/img/logo.png" style="height:35px; margin-bottom:1rem; opacity:0.8;">
            <p style="font-size:0.8rem; color:var(--text-muted);">© 2026 HackConcursos. Todos os direitos reservados.</p>
        </div>
        <div class="d-flex gap-lg">
            <a href="#" class="text-muted" style="font-size:0.85rem;">Termos de Uso</a>
            <a href="#" class="text-muted" style="font-size:0.85rem;">Privacidade</a>
            <a href="mailto:contato@hackconcursos.com.br" class="text-muted" style="font-size:0.85rem;">Suporte</a>
        </div>
    </div>
</footer>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
