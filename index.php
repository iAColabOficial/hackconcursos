<?php
require_once __DIR__ . '/config/config.php';
$page_title = 'Aprovação Tática com Inteligência Artificial';
require_once __DIR__ . '/includes/header.php';
?>

<!-- Estilos específicos para a LP VSL -->
<style>
  /* Ocultar navegação na LP para manter o foco na VSL */
  .navbar-hc .nav-link-hc { display: none; }
  @media (min-width: 768px) {
      .navbar-hc .nav-link-hc { display: block; }
  }
  
  .vsl-container {
      position: relative;
      width: 100%;
      max-width: 800px;
      margin: 2rem auto;
      border-radius: var(--radius-lg);
      overflow: hidden;
      box-shadow: 0 0 40px rgba(34,197,94,0.15);
      border: 1px solid var(--border-neon);
      background: #000;
      aspect-ratio: 16/9;
      display: flex;
      align-items: center;
      justify-content: center;
  }
  .vsl-play-btn {
      width: 80px; height: 80px;
      background: var(--neon-green);
      border-radius: 50%;
      display: flex; align-items: center; justify-content: center;
      font-size: 2.5rem; color: #fff;
      cursor: pointer;
      box-shadow: 0 0 20px rgba(34,197,94,0.6);
      transition: transform 0.2s;
  }
  .vsl-play-btn:hover { transform: scale(1.1); }
  
  /* Carousel Prova Social - 3 por vez */
  .carousel-wrapper {
      position: relative;
      max-width: 1200px;
      margin: 0 auto;
      overflow: hidden;
  }
  .carousel-track {
      display: flex;
      transition: transform 0.6s cubic-bezier(0.25, 0.46, 0.45, 0.94);
  }
  .carousel-card {
      flex: 0 0 calc(100% / 3);
      max-width: calc(100% / 3);
      box-sizing: border-box;
      padding: 0 0.75rem;
  }
  @media (max-width: 768px) {
      .carousel-card {
          flex: 0 0 100%;
          max-width: 100%;
      }
  }
  .testimonial-card {
      background: var(--bg-glass);
      backdrop-filter: blur(16px);
      border: 1px solid var(--border-glass);
      border-radius: var(--radius-lg);
      padding: 2rem 1.5rem;
      height: 100%;
      display: flex;
      flex-direction: column;
      transition: border-color 0.3s, box-shadow 0.3s;
  }
  .testimonial-card:hover {
      border-color: var(--border-neon);
      box-shadow: var(--shadow-neon);
  }
  .carousel-nav {
      display: flex;
      justify-content: center;
      align-items: center;
      gap: 1.5rem;
      margin-top: 2.5rem;
  }
  .carousel-btn {
      background: rgba(255,255,255,0.08);
      border: 1px solid var(--border-glass);
      color: var(--text-secondary);
      width: 48px; height: 48px;
      border-radius: 50%;
      display: flex; align-items: center; justify-content: center;
      cursor: pointer;
      font-size: 1.2rem;
      transition: all 0.3s;
  }
  .carousel-btn:hover { background: var(--neon-green); border-color: var(--neon-green); color: #fff; }
  .carousel-dots {
      display: flex; gap: 8px;
  }
  .carousel-dot {
      width: 12px; height: 12px; border-radius: 50%;
      background: rgba(255,255,255,0.15); cursor: pointer; transition: all 0.3s;
      border: none;
  }
  .carousel-dot.active { background: var(--neon-green); transform: scale(1.3); }

  /* Accordion FAQ */
  .faq-item {
      border: 1px solid var(--border-glass);
      border-radius: var(--radius-md);
      margin-bottom: 1rem;
      background: rgba(255,255,255,0.02);
      overflow: hidden;
  }
  .faq-question {
      padding: 1.25rem 1.5rem;
      font-weight: 700;
      cursor: pointer;
      display: flex; justify-content: space-between; align-items: center;
  }
  .faq-answer {
      padding: 0 1.5rem 1.25rem;
      color: var(--text-secondary);
      display: none;
      border-top: 1px solid var(--border-glass);
      margin-top: 1rem;
      padding-top: 1rem;
  }

  /* Efeito de pulso para o botão de ação principal */
  @keyframes pulse-neon {
      0% { box-shadow: 0 0 0 0 rgba(34,197,94,0.7); }
      70% { box-shadow: 0 0 0 15px rgba(34,197,94,0); }
      100% { box-shadow: 0 0 0 0 rgba(34,197,94,0); }
  }
  .btn-pulse {
      animation: pulse-neon 2s infinite;
  }
</style>

<!-- Navbar Pública Simplificada -->
<nav class="navbar-hc">
  <div class="container d-flex jc-between ai-center" style="max-width:1000px; margin:0 auto; padding:0 1.5rem;">
    <a href="index.php" class="brand">
        <img src="<?= APP_URL ?>/assets/img/logo.png" alt="HackConcursos" style="height:40px;">
    </a>
    <div class="d-flex ai-center gap-lg">
      <a href="login.php" class="nav-link-hc">Já sou aluno (Entrar)</a>
    </div>
  </div>
</nav>

<main>
  <!-- 1. HEADER & VSL (Atenção) -->
  <section class="hero-section" style="min-height: auto; padding: 60px 0 100px;">
    <div class="hero-glow green" style="top: -200px; left: 50%; transform: translateX(-50%); width: 800px; height: 800px; opacity: 0.5;"></div>
    
    <div class="container" style="max-width:1000px; margin:0 auto; padding:0 1.5rem; position:relative; z-index:1; text-align:center;">
        
        <div class="hero-badge mx-auto"><i class="bi bi-shield-exclamation"></i> O Fim do Estudo Desorganizado</div>
        
        <h1 class="hero-title lh-sm mx-auto" style="font-size: clamp(2rem, 4vw, 3.5rem); margin-bottom: 1.5rem; max-width: 900px; line-height: 1.2;">
            Descubra em 2 minutos por que você<br>
            ainda <strong class="text-danger">não foi aprovado.</strong>
        </h1>
        
        <p class="hero-subtitle mx-auto" style="font-size: 1.2rem; max-width: 700px;">
            Acesse o mapa estratégico gerado pela IA que minerou <strong>+200.000 provas reais</strong> para prever exatamente o que a sua banca vai cobrar.
        </p>

        <!-- VSL Placeholder -->
        <div class="vsl-container mt-lg">
            <div style="position: absolute; inset: 0; background: url('https://images.unsplash.com/photo-1497032628192-86f99bcd76bc?ixlib=rb-4.0.3&auto=format&fit=crop&w=1000&q=80') center/cover; opacity: 0.4;"></div>
            <div class="vsl-play-btn" style="position: relative; z-index: 2;">
                <i class="bi bi-play-fill" style="margin-left: 5px;"></i>
            </div>
            <div style="position: absolute; bottom: 20px; text-align: center; width: 100%; z-index: 2;">
                <span class="badge-hc badge-neon" style="background: rgba(0,0,0,0.8);"><i class="bi bi-volume-up-fill"></i> Certifique-se que o som está ligado</span>
            </div>
        </div>

        <div class="mt-lg">
            <a href="cadastro.php" class="btn-hc btn-neon btn-xl btn-pulse" style="padding: 1.2rem 4rem; font-size: 1.2rem; font-weight: 800;">
                <i class="bi bi-clipboard-data"></i> FAZER DIAGNÓSTICO GRATUITO
            </a>
            <p style="margin-top: 1rem; font-size: 0.85rem; color: var(--text-muted);"><i class="bi bi-shield-check"></i> Biblioteca de editais nacionais já processada.</p>
        </div>
    </div>
  </section>

  <!-- TRUST BAR (Logos de Concursos) -->
  <section style="padding: 40px 0; border-top: 1px solid var(--border-glass); border-bottom: 1px solid var(--border-glass); background: rgba(0,0,0,0.4);">
      <div class="container" style="max-width: 1000px; margin: 0 auto; padding: 0 1.5rem; text-align: center;">
          <p style="font-size: 0.8rem; text-transform: uppercase; letter-spacing: 2px; color: var(--text-muted); margin-bottom: 1.5rem; font-weight: 700;">Concursos que já analisamos</p>
          <div class="d-flex jc-center ai-center gap-lg" style="flex-wrap: wrap; gap: 2.5rem; opacity: 0.6;">
              <span style="font-size: 1rem; font-weight: 800; letter-spacing: 1px; color: var(--text-secondary);">🛡️ POLÍCIA FEDERAL</span>
              <span style="font-size: 1rem; font-weight: 800; letter-spacing: 1px; color: var(--text-secondary);">⚖️ TRF</span>
              <span style="font-size: 1rem; font-weight: 800; letter-spacing: 1px; color: var(--text-secondary);">💰 RECEITA FEDERAL</span>
              <span style="font-size: 1rem; font-weight: 800; letter-spacing: 1px; color: var(--text-secondary);">🏥 INSS</span>
              <span style="font-size: 1rem; font-weight: 800; letter-spacing: 1px; color: var(--text-secondary);">🏦 BANCO DO BRASIL</span>
          </div>
      </div>
  </section>

  <!-- 2. O INIMIGO (Conscientização) -->
  <section style="padding: 80px 0; background: rgba(0,0,0,0.3); border-top: 1px solid var(--border-glass);">
      <div class="container" style="max-width: 800px; margin: 0 auto; padding: 0 1.5rem; text-align: center;">
          <i class="bi bi-x-octagon text-danger" style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.8;"></i>
          <h2 style="font-size: 2.2rem; margin-bottom: 1.5rem;">O modelo tradicional faliu.</h2>
          <p style="font-size: 1.15rem; color: var(--text-secondary); line-height: 1.6;">
              Você acumula dezenas de PDFs, assina cursinhos com infinitas videoaulas e, na hora de sentar para estudar, se pergunta: <strong>"Por onde eu começo?"</strong>.
          </p>
          <p style="font-size: 1.15rem; color: var(--text-secondary); line-height: 1.6; margin-top: 1rem;">
              Estudar sem prioridade e depender de cronogramas estáticos é o motivo de você estar gastando energia e ainda não ter passado. No campo de batalha dos concursos, <strong>vence quem tem estratégia</strong>, não quem tem mais livros.
          </p>
      </div>
  </section>

  <!-- 3. O MECANISMO (4 Passos) -->
  <section id="mecanismo" style="padding: 100px 0; position:relative; border-top: 1px solid var(--border-glass);">
    <div class="container" style="max-width:1200px; margin:0 auto; padding:0 1.5rem;">
      <div class="text-center mb-lg">
          <div class="badge-hc badge-neon mb-sm">COMO FUNCIONA</div>
          <h2 style="font-size: 2.8rem; margin-bottom: 1rem;">Estratégia inteligente em <span class="text-neon">4 passos</span> simples</h2>
      </div>
      
      <div class="grid-4" style="margin-top: 4rem; text-align: center;">
        <div style="padding: 1.5rem;">
            <div class="kpi-icon neon mx-auto mb-md" style="font-size:1.8rem; width:64px; height:64px;"><i class="bi bi-cpu"></i></div>
            <h4 style="margin-bottom: 0.5rem;">1. Mineração em Massa</h4>
            <p style="color:var(--text-secondary); font-size:0.95rem;">Cruzamos seu edital com nosso banco de +200 mil provas reais para identificar padrões de cobrança.</p>
        </div>
        <div style="padding: 1.5rem;">
            <div class="kpi-icon blue mx-auto mb-md" style="font-size:1.8rem; width:64px; height:64px;"><i class="bi bi-sort-numeric-down"></i></div>
            <h4 style="margin-bottom: 0.5rem;">2. Definimos prioridades</h4>
            <p style="color:var(--text-secondary); font-size:0.95rem;">Separamos o essencial do ruído e montamos a hierarquia do seu edital.</p>
        </div>
        <div style="padding: 1.5rem;">
            <div class="kpi-icon purple mx-auto mb-md" style="font-size:1.8rem; width:64px; height:64px;"><i class="bi bi-crosshair"></i></div>
            <h4 style="margin-bottom: 0.5rem;">3. Você estuda com foco</h4>
            <p style="color:var(--text-secondary); font-size:0.95rem;">A Missão do Dia elimina as decisões abertas. Você só precisa executar.</p>
        </div>
        <div style="padding: 1.5rem;">
            <div class="kpi-icon warn mx-auto mb-md" style="font-size:1.8rem; width:64px; height:64px;"><i class="bi bi-radar"></i></div>
            <h4 style="margin-bottom: 0.5rem;">4. Ajustamos com seu desempenho</h4>
            <p style="color:var(--text-secondary); font-size:0.95rem;">O sistema detecta falhas e recalibra sua rota antes da prova.</p>
        </div>
      </div>
    </div>
  </section>

  <!-- 3B. FEATURES GRID (Tudo que você precisa) -->
  <section style="padding: 100px 0; background: rgba(255,255,255,0.02); border-top: 1px solid var(--border-glass);">
    <div class="container" style="max-width:1200px; margin:0 auto; padding:0 1.5rem;">
      <div class="text-center mb-lg">
          <div class="badge-hc badge-blue mb-sm">RECURSOS QUE FAZEM A DIFERENÇA</div>
          <h2 style="font-size: 2.5rem; margin-bottom: 1rem;">Tudo que você precisa para <span class="text-neon">ser aprovado</span>.</h2>
      </div>

      <div class="grid-3" style="margin-top: 3rem; gap: 1.5rem;">
          <div class="card-glass" style="padding: 2rem;">
              <i class="bi bi-file-earmark-bar-graph text-neon" style="font-size: 2rem; margin-bottom: 1rem; display: block;"></i>
              <h4 style="margin-bottom: 0.5rem;">Raio-X do Edital</h4>
              <p style="color:var(--text-secondary); font-size:0.95rem;">A IA identifica o peso real de cada matéria e mostra onde investir seu tempo.</p>
          </div>
          <div class="card-glass" style="padding: 2rem;">
              <i class="bi bi-graph-up-arrow text-neon" style="font-size: 2rem; margin-bottom: 1rem; display: block;"></i>
              <h4 style="margin-bottom: 0.5rem;">Índice de Aprovação</h4>
              <p style="color:var(--text-secondary); font-size:0.95rem;">Veja em tempo real se você está evoluindo ou se precisa ajustar o ritmo.</p>
          </div>
          <div class="card-glass" style="padding: 2rem;">
              <i class="bi bi-crosshair text-blue" style="font-size: 2rem; margin-bottom: 1rem; display: block;"></i>
              <h4 style="margin-bottom: 0.5rem;">Missão do Dia</h4>
              <p style="color:var(--text-secondary); font-size:0.95rem;">Acorde sabendo exatamente o que fazer. Sem dúvidas, sem procrastinação.</p>
          </div>
          <div class="card-glass" style="padding: 2rem;">
              <i class="bi bi-activity text-danger" style="font-size: 2rem; margin-bottom: 1rem; display: block;"></i>
              <h4 style="margin-bottom: 0.5rem;">Diagnóstico de Falhas</h4>
              <p style="color:var(--text-secondary); font-size:0.95rem;">Identifica seus pontos fracos e recalcula o plano antes do dia da prova.</p>
          </div>
          <div class="card-glass" style="padding: 2rem;">
              <i class="bi bi-database-check text-purple" style="font-size: 2rem; margin-bottom: 1rem; display: block;"></i>
              <h4 style="margin-bottom: 0.5rem;">Big Data de Provas</h4>
              <p style="color:var(--text-secondary); font-size:0.95rem;">Mais de 200 mil provas reais organizadas e analisadas para gerar seus simulados e planos.</p>
          </div>
          <div class="card-glass" style="padding: 2rem;">
              <i class="bi bi-robot text-purple" style="font-size: 2rem; margin-bottom: 1rem; display: block;"></i>
              <h4 style="margin-bottom: 0.5rem;">Mentor IA</h4>
              <p style="color:var(--text-secondary); font-size:0.95rem;">Tire dúvidas a qualquer hora e receba explicações simplificadas do edital.</p>
          </div>
      </div>
    </div>
  </section>

  <!-- BARRA DE MÉTRICAS -->
  <section style="padding: 80px 0; background: rgba(0,0,0,0.4); border-top: 1px solid var(--border-glass); border-bottom: 1px solid var(--border-glass);">
      <div class="container" style="max-width: 1100px; margin: 0 auto; padding: 0 1.5rem; text-align: center;">
          <p style="font-size: 0.8rem; text-transform: uppercase; letter-spacing: 2px; color: var(--text-muted); margin-bottom: 2rem; font-weight: 700;">Resultados reais</p>
          <h2 style="font-size: 2.2rem; margin-bottom: 3rem;">Números que comprovam <span class="text-neon">nossa metodologia</span>.</h2>
          <div class="grid-4" style="gap: 2rem;">
              <div>
                  <div class="text-neon fw-800" style="font-size: 3.5rem; line-height: 1;">+12.000</div>
                  <p style="color:var(--text-secondary); margin-top: 0.5rem;">alunos aprovados com a plataforma</p>
              </div>
              <div>
                  <div class="text-neon fw-800" style="font-size: 3.5rem; line-height: 1;">78%</div>
                  <p style="color:var(--text-secondary); margin-top: 0.5rem;">taxa de aprovação dos usuários ativos</p>
              </div>
              <div>
                  <div class="text-neon fw-800" style="font-size: 3.5rem; line-height: 1;">+200k</div>
                  <p style="color:var(--text-secondary); margin-top: 0.5rem;">provas reais mineradas e indexadas</p>
              </div>
              <div>
                  <div class="text-neon fw-800" style="font-size: 3.5rem; line-height: 1;">+1.000</div>
                  <p style="color:var(--text-secondary); margin-top: 0.5rem;">horas de estudo economizadas</p>
              </div>
          </div>
      </div>
  </section>

  <!-- 4. PROVA SOCIAL (CAROUSEL - 3 por vez) -->
  <section style="padding: 100px 0; background: rgba(255,255,255,0.02); border-top: 1px solid var(--border-glass);">
    <div class="container" style="max-width: 1200px; margin: 0 auto; padding: 0 1.5rem; text-align: center;">
        <h2 style="font-size: 2.5rem; margin-bottom: 1rem;">Junte-se a <span class="text-neon">+1.200 concurseiros</span> que pararam de estudar errado.</h2>
        <p style="color:var(--text-secondary); margin-bottom:3rem; font-size:1.15rem;">A estratégia certa muda o jogo. Veja o que dizem aqueles que ativaram o Modo Guerra.</p>
        
        <div class="carousel-wrapper">
            <div class="carousel-track" id="testimonialTrack">
                
                <!-- Card 1 - Missão do Dia -->
                <div class="carousel-card">
                    <div class="testimonial-card">
                        <div class="d-flex gap-sm text-warning mb-md" style="font-size: 1rem;">
                            <i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i>
                        </div>
                        <p style="font-size: 1rem; font-style: italic; margin-bottom: 1.5rem; line-height: 1.6; flex: 1; text-align: left;">"Antes eu passava 2h montando cronograma e nunca cumpria. Com a Missão do Dia, eu sento e só faço o que a IA manda. Minha nota de corte subiu 15 pontos em 2 meses."</p>
                        <div class="d-flex ai-center gap-sm" style="border-top: 1px solid var(--border-glass); padding-top: 1.25rem;">
                            <img src="https://i.pravatar.cc/50?u=12" style="border-radius:50%; border: 2px solid var(--neon-green);">
                            <div style="text-align: left;">
                                <strong style="display:block;">Thiago S.</strong>
                                <span style="font-size:0.8rem; color:var(--neon-green); font-weight: 700;">Aprovado — Polícia Civil</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Card 2 - Raio-X do Edital -->
                <div class="carousel-card">
                    <div class="testimonial-card">
                        <div class="d-flex gap-sm text-warning mb-md" style="font-size: 1rem;">
                            <i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i>
                        </div>
                        <p style="font-size: 1rem; font-style: italic; margin-bottom: 1.5rem; line-height: 1.6; flex: 1; text-align: left;">"O Raio-X do edital é absurdo. Eu ia perder meses numa matéria com 2% de chance de cair. O sistema salvou minha aprovação me fazendo focar no que realmente importa."</p>
                        <div class="d-flex ai-center gap-sm" style="border-top: 1px solid var(--border-glass); padding-top: 1.25rem;">
                            <img src="https://i.pravatar.cc/50?u=25" style="border-radius:50%; border: 2px solid var(--neon-green);">
                            <div style="text-align: left;">
                                <strong style="display:block;">Mariana L.</strong>
                                <span style="font-size:0.8rem; color:var(--neon-green); font-weight: 700;">Aprovada — TRT</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Card 3 - Índice de Aprovação -->
                <div class="carousel-card">
                    <div class="testimonial-card">
                        <div class="d-flex gap-sm text-warning mb-md" style="font-size: 1rem;">
                            <i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i>
                        </div>
                        <p style="font-size: 1rem; font-style: italic; margin-bottom: 1.5rem; line-height: 1.6; flex: 1; text-align: left;">"Assinava dois cursinhos e estava completamente perdido. A plataforma me deu a clareza que faltava. Ver meu Índice de Aprovação subindo a cada ciclo é viciante."</p>
                        <div class="d-flex ai-center gap-sm" style="border-top: 1px solid var(--border-glass); padding-top: 1.25rem;">
                            <img src="https://i.pravatar.cc/50?u=33" style="border-radius:50%; border: 2px solid var(--neon-green);">
                            <div style="text-align: left;">
                                <strong style="display:block;">Carlos F.</strong>
                                <span style="font-size:0.8rem; color:var(--neon-green); font-weight: 700;">Aprovado — INSS</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Card 4 - Diagnóstico de Falhas -->
                <div class="carousel-card">
                    <div class="testimonial-card">
                        <div class="d-flex gap-sm text-warning mb-md" style="font-size: 1rem;">
                            <i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i>
                        </div>
                        <p style="font-size: 1rem; font-style: italic; margin-bottom: 1.5rem; line-height: 1.6; flex: 1; text-align: left;">"O Diagnóstico de Falhas é um tapa na cara com carinho. Mostrou que meu problema era Penal, não Constitucional como eu achava. Corrigi a rota e fui aprovada na segunda tentativa."</p>
                        <div class="d-flex ai-center gap-sm" style="border-top: 1px solid var(--border-glass); padding-top: 1.25rem;">
                            <img src="https://i.pravatar.cc/50?u=44" style="border-radius:50%; border: 2px solid var(--neon-green);">
                            <div style="text-align: left;">
                                <strong style="display:block;">Fernanda R.</strong>
                                <span style="font-size:0.8rem; color:var(--neon-green); font-weight: 700;">Aprovada — PRF</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Card 5 - Mentor IA -->
                <div class="carousel-card">
                    <div class="testimonial-card">
                        <div class="d-flex gap-sm text-warning mb-md" style="font-size: 1rem;">
                            <i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i>
                        </div>
                        <p style="font-size: 1rem; font-style: italic; margin-bottom: 1.5rem; line-height: 1.6; flex: 1; text-align: left;">"Trabalho o dia inteiro e só tenho 3h à noite. O Mentor IA traduz o juridiquês do edital de um jeito que eu entendo em minutos. Economizo tempo e fixo conteúdo muito mais rápido."</p>
                        <div class="d-flex ai-center gap-sm" style="border-top: 1px solid var(--border-glass); padding-top: 1.25rem;">
                            <img src="https://i.pravatar.cc/50?u=56" style="border-radius:50%; border: 2px solid var(--neon-green);">
                            <div style="text-align: left;">
                                <strong style="display:block;">Rafael M.</strong>
                                <span style="font-size:0.8rem; color:var(--neon-green); font-weight: 700;">Aprovado — Receita Federal</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Card 6 - Concurso + Trabalho -->
                <div class="carousel-card">
                    <div class="testimonial-card">
                        <div class="d-flex gap-sm text-warning mb-md" style="font-size: 1rem;">
                            <i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i>
                        </div>
                        <p style="font-size: 1rem; font-style: italic; margin-bottom: 1.5rem; line-height: 1.6; flex: 1; text-align: left;">"Sou mãe de dois filhos e concurseira. O plano intercalado respeita minha rotina maluca. Cada segundo do meu estudo é otimizado. Pela primeira vez sinto que vou passar."</p>
                        <div class="d-flex ai-center gap-sm" style="border-top: 1px solid var(--border-glass); padding-top: 1.25rem;">
                            <img src="https://i.pravatar.cc/50?u=68" style="border-radius:50%; border: 2px solid var(--neon-green);">
                            <div style="text-align: left;">
                                <strong style="display:block;">Juliana A.</strong>
                                <span style="font-size:0.8rem; color:var(--neon-green); font-weight: 700;">Estudando — Tribunal de Justiça</span>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <!-- Navegação abaixo do carrossel -->
        <div class="carousel-nav">
            <div class="carousel-btn" onclick="moveCarousel(-1)"><i class="bi bi-chevron-left"></i></div>
            <div class="carousel-dots" id="carouselDots">
                <div class="carousel-dot active" onclick="goToSlide(0)"></div>
                <div class="carousel-dot" onclick="goToSlide(1)"></div>
            </div>
            <div class="carousel-btn" onclick="moveCarousel( dir: 1)"><i class="bi bi-chevron-right"></i></div>
        </div>
    </div>
  </section>

  <!-- 5. OFERTA & FREEMIUM (Planos) -->
  <section id="oferta" style="padding: 100px 0; border-top: 1px solid var(--border-glass); background: radial-gradient(circle at top, rgba(34,197,94,0.05) 0%, transparent 70%);">
      <div class="container" style="max-width:1100px; margin:0 auto; padding:0 1.5rem; text-align:center;">
        
        <div class="badge-hc badge-neon mb-md" style="font-size: 1rem; padding: 0.5rem 1.5rem;"><i class="bi bi-unlock-fill"></i> ESCOLHA SEU NÍVEL DE COMBATE</div>
        <h2 style="font-size: 3.5rem; margin-bottom: 1rem; font-weight: 900;">O gratuito <span class="text-neon">mostra o caminho</span>.<br>O pago <span class="text-neon">garante a posse</span>.</h2>
        <p style="color:var(--text-secondary); margin-bottom:4rem; font-size:1.25rem;">Crie sua conta gratuitamente hoje e conheça o Motor de Aprovação. Atualize quando estiver pronto para a guerra.</p>

        <div class="grid-3" style="align-items:stretch; text-align:left;">
          
          <!-- FREEMIUM -->
          <div class="card-glass" style="padding:3rem 2rem; display:flex; flex-direction:column; background: rgba(255,255,255,0.02);">
             <h3 style="margin-bottom:0.5rem; font-size: 2rem;">Gratuito</h3>
             <div style="font-size:0.95rem; color:var(--text-muted); margin-bottom:2rem;">Organização básica para começar a focar.</div>
             
             <div class="fw-800" style="font-size:3rem; margin-bottom:2rem; line-height: 1;">R$ 0<span style="font-size:1rem; color:var(--text-muted); font-weight: 500;">/sempre</span></div>
             
             <div style="flex:1; margin-bottom:2rem;">
                <ul style="list-style:none; padding:0; display:flex; flex-direction:column; gap:1rem; font-size:1rem;">
                   <li><i class="bi bi-check-circle-fill text-primary" style="margin-right:0.5rem;"></i> 1 Edital Analisado pela IA</li>
                   <li><i class="bi bi-check-circle-fill text-primary" style="margin-right:0.5rem;"></i> Plano de Estudos Básico</li>
                   <li style="opacity:0.4;"><i class="bi bi-x-circle" style="margin-right:0.5rem;"></i> Índice de Aprovação (IAp)</li>
                   <li style="opacity:0.4;"><i class="bi bi-x-circle" style="margin-right:0.5rem;"></i> Missão do Dia Dinâmica</li>
                   <li style="opacity:0.4;"><i class="bi bi-x-circle" style="margin-right:0.5rem;"></i> Diagnóstico de Falhas</li>
                </ul>
             </div>
             <a href="cadastro.php?plano=free" class="btn-hc btn-ghost w-100" style="margin-top: auto; padding: 1rem; font-size: 1.05rem;">Criar Conta Grátis</a>
          </div>

          <!-- MODO GUERRA (POPULAR) -->
          <div class="card-glass" style="padding:3rem 2rem; display:flex; flex-direction:column; border-color:var(--neon-green); transform:scale(1.05); z-index:2; box-shadow: 0 0 30px rgba(34,197,94,0.15);">
             <div style="position:absolute; top:-16px; left:50%; transform:translateX(-50%); padding: 0.5rem 2rem; font-size: 0.85rem; font-weight: 900; letter-spacing: 1px;" class="badge-hc badge-neon">MODO GUERRA</div>
             <h3 style="margin-bottom:0.5rem; font-size: 2rem; color: var(--neon-green);">Estratégico</h3>
             <div style="font-size:0.95rem; color:var(--text-muted); margin-bottom:2rem;">O motor completo trabalhando pela sua posse.</div>
             
             <div class="fw-800 text-neon" style="font-size:3rem; margin-bottom:2rem; line-height: 1;">R$ 97<span style="font-size:1rem; color:var(--text-muted); font-weight: 500;">/mês</span></div>
             
             <div style="flex:1; margin-bottom:2rem;">
                <ul style="list-style:none; padding:0; display:flex; flex-direction:column; gap:1rem; font-size:1rem;">
                   <li><i class="bi bi-check-circle-fill text-neon" style="margin-right:0.5rem;"></i> Editais Ilimitados</li>
                   <li><i class="bi bi-check-circle-fill text-neon" style="margin-right:0.5rem;"></i> Missão do Dia Dinâmica</li>
                   <li><i class="bi bi-check-circle-fill text-neon" style="margin-right:0.5rem;"></i> Medidor de Índice de Aprovação</li>
                   <li><i class="bi bi-check-circle-fill text-neon" style="margin-right:0.5rem;"></i> IA de Diagnóstico de Falhas</li>
                   <li><i class="bi bi-check-circle-fill text-neon" style="margin-right:0.5rem;"></i> Mentor IA 24/7 (Tradutor de Edital)</li>
                </ul>
             </div>
             <a href="cadastro.php?plano=premium" class="btn-hc btn-neon w-100 btn-pulse" style="margin-top: auto; font-weight:900; font-size: 1.15rem; padding: 1.2rem;">ATIVAR MODO GUERRA</a>
          </div>

          <!-- MODO ELITE -->
          <div class="card-glass" style="padding:3rem 2rem; display:flex; flex-direction:column; border-color:var(--accent-blue); background: rgba(255,255,255,0.02);">
             <h3 style="margin-bottom:0.5rem; font-size: 2rem;">Elite</h3>
             <div style="font-size:0.95rem; color:var(--text-muted); margin-bottom:2rem;">Para quem quer a vaga ainda este ano.</div>
             
             <div class="fw-800" style="font-size:3rem; margin-bottom:2rem; line-height: 1;">R$ 59<span style="font-size:1rem; color:var(--text-muted); font-weight: 500;">/mês</span></div>
             
             <div style="flex:1; margin-bottom:2rem;">
                <ul style="list-style:none; padding:0; display:flex; flex-direction:column; gap:1rem; font-size:1rem;">
                   <li><i class="bi bi-check-circle-fill text-blue" style="margin-right:0.5rem;"></i> Até 3 Editais Analisados</li>
                   <li><i class="bi bi-check-circle-fill text-blue" style="margin-right:0.5rem;"></i> Plano de Estudos Adaptativo</li>
                   <li><i class="bi bi-check-circle-fill text-blue" style="margin-right:0.5rem;"></i> Simulados Ilimitados</li>
                   <li><i class="bi bi-check-circle-fill text-blue" style="margin-right:0.5rem;"></i> Mentor IA (10 perguntas/dia)</li>
                   <li style="opacity:0.4;"><i class="bi bi-x-circle" style="margin-right:0.5rem;"></i> Diagnóstico Avançado</li>
                </ul>
             </div>
             <a href="cadastro.php?plano=basico" class="btn-hc btn-primary-hc w-100" style="margin-top: auto; padding: 1rem; font-size: 1.05rem;">Escolher Elite</a>
          </div>

        </div>

        <!-- SELO DE GARANTIA -->
        <div style="margin-top: 3rem; padding: 1.5rem 2rem; border: 1px solid rgba(34,197,94,0.2); border-radius: var(--radius-lg); background: rgba(34,197,94,0.03); display: inline-flex; align-items: center; gap: 1rem;">
            <i class="bi bi-shield-check text-neon" style="font-size: 2.5rem;"></i>
            <div style="text-align: left;">
                <strong style="font-size: 1.1rem;">7 dias de garantia incondicional.</strong>
                <p style="color: var(--text-secondary); font-size: 0.9rem; margin-top: 0.25rem;">Se não gostar, cancele em 2 cliques. Sem multa, sem enrolação.</p>
            </div>
        </div>

      </div>
  </section>

  <!-- 6. FAQ Aprimorado -->
  <section style="padding: 100px 0; background: rgba(0,0,0,0.2); border-top: 1px solid var(--border-glass);">
      <div class="container" style="max-width: 800px; margin: 0 auto; padding: 0 1.5rem;">
          <h2 style="font-size: 2.8rem; margin-bottom: 3rem; text-align: center; font-weight: 800;">Ficou com alguma dúvida?</h2>
          
          <div class="faq-item">
              <div class="faq-question" onclick="toggleFaq(this)">
                  <span style="font-size: 1.1rem;">Como o plano Gratuito funciona?</span>
                  <i class="bi bi-chevron-down" style="font-size: 1.2rem;"></i>
              </div>
              <div class="faq-answer" style="font-size: 1.05rem; line-height: 1.6;">
                  Você pode criar sua conta sem inserir cartão de crédito e testar o processamento de 1 edital pela nossa IA. Isso permite que você veja como o sistema organiza as disciplinas e gera um cronograma base. Para ativar as ferramentas estratégicas diárias (Missão do Dia e Diagnóstico), basta fazer o upgrade.
              </div>
          </div>
          
          <div class="faq-item">
              <div class="faq-question" onclick="toggleFaq(this)">
                  <span style="font-size: 1.1rem;">Tenho pouco tempo livre para estudar. O Motor de Aprovação ajuda?</span>
                  <i class="bi bi-chevron-down" style="font-size: 1.2rem;"></i>
              </div>
              <div class="faq-answer" style="font-size: 1.05rem; line-height: 1.6;">
                  Aí é que ele se torna **essencial**. Se você tem apenas 2 horas por dia, não pode se dar ao luxo de estudar o que tem pouca chance de cair. O Motor de Aprovação vai direcionar os seus poucos minutos diários exatamente para os 20% do edital que representam 80% dos pontos da prova. O seu estudo será um "ataque cirúrgico".
              </div>
          </div>

          <div class="faq-item">
              <div class="faq-question" onclick="toggleFaq(this)">
                  <span style="font-size: 1.1rem;">O HackConcursos tem material (aulas/PDFs)?</span>
                  <i class="bi bi-chevron-down" style="font-size: 1.2rem;"></i>
              </div>
              <div class="faq-answer" style="font-size: 1.05rem; line-height: 1.6;">
                  Não. O HackConcursos é um <strong>Motor de Estratégia e Diagnóstico</strong>. Nós não concorremos com cursinhos, nós somos o "Cérebro" que organiza eles. Nossa plataforma diz exatamente O QUE e QUANDO você deve estudar. Você continua usando seus PDFs do Estratégia, Gran ou Direção apenas para absorver o conteúdo que nós mandarmos ler.
              </div>
          </div>
          
          <div class="faq-item">
              <div class="faq-question" onclick="toggleFaq(this)">
                  <span style="font-size: 1.1rem;">Serve para concursos de nível médio e superior?</span>
                  <i class="bi bi-chevron-down" style="font-size: 1.2rem;"></i>
              </div>
              <div class="faq-answer" style="font-size: 1.05rem; line-height: 1.6;">
                  Sim. Nossa IA lê qualquer edital em PDF (PF, PRF, INSS, Tribunais, Fiscos, Prefeituras) e extrai a matriz de disciplinas com extrema precisão, ajustando os ciclos para a profundidade exigida pela sua prova.
              </div>
          </div>

          <div class="faq-item">
              <div class="faq-question" onclick="toggleFaq(this)">
                  <span style="font-size: 1.1rem;">O que acontece se a prova for adiada ou o edital mudar?</span>
                  <i class="bi bi-chevron-down" style="font-size: 1.2rem;"></i>
              </div>
              <div class="faq-answer" style="font-size: 1.05rem; line-height: 1.6;">
                  No Método Tradicional, seu cronograma inteiro vai para o lixo. No HackConcursos, basta você atualizar a data da prova ou carregar o edital retificado. A IA recalcula toda a sua Missão do Dia automaticamente, sem você perder histórico de progresso.
              </div>
          </div>
          
          <div class="faq-item">
              <div class="faq-question" onclick="toggleFaq(this)">
                  <span style="font-size: 1.1rem;">E se eu não me adaptar à metodologia?</span>
                  <i class="bi bi-chevron-down" style="font-size: 1.2rem;"></i>
              </div>
              <div class="faq-answer" style="font-size: 1.05rem; line-height: 1.6;">
                  Se você não assinar, a chance de continuar perdido é de 100%. Mas nós oferecemos total transparência: você pode cancelar a assinatura a qualquer momento com apenas 2 cliques no painel. Sem multas, sem enrolação.
              </div>
          </div>
      </div>
  </section>

  <!-- 7. CTA FINAL FORTE -->
  <section style="padding: 100px 0; border-top: 1px solid var(--border-glass); background: radial-gradient(circle at center, rgba(34,197,94,0.1) 0%, transparent 80%);">
      <div class="container" style="max-width: 900px; margin: 0 auto; padding: 0 1.5rem; text-align: center;">
          <h2 style="font-size: 3.5rem; font-weight: 900; line-height: 1.1; margin-bottom: 2rem;">
              Cada dia estudando errado é uma <span class="text-danger">questão que você erra</span> na prova.
          </h2>
          <p style="font-size: 1.25rem; color: var(--text-secondary); margin-bottom: 3rem; line-height: 1.6;">
              O edital está correndo. Seus concorrentes estão lendo PDF sem saber se aquilo vai cair. Você tem a chance de pegar o atalho estratégico. A escolha é sua.
          </p>
          <a href="#oferta" class="btn-hc btn-neon btn-xl btn-pulse" style="font-size: 1.3rem; padding: 1.5rem 4rem; font-weight: 900; border-radius: var(--radius-full);">
              ASSUMIR O CONTROLE DA MINHA APROVAÇÃO
          </a>
      </div>
  </section>

</main>

<footer style="padding:30px 0; border-top:1px solid var(--border-glass); background:rgba(0,0,0,0.5);">
    <div class="container" style="max-width:1000px; margin:0 auto; padding:0 1.5rem; text-align: center;">
        <img src="<?= APP_URL ?>/assets/img/logo.png" style="height:30px; margin-bottom:0.75rem; opacity:0.6;">
        <p style="font-size:0.8rem; color:var(--text-muted);">© <?= date('Y'); ?> HackConcursos. Todos os direitos reservados.</p>
    </div>
</footer>

<!-- WhatsApp Floating Button -->
<a href="#" id="whatsappBtn" onclick="openWhatsChat(event)" style="
    position: fixed;
    bottom: 28px;
    right: 28px;
    z-index: 9999;
    width: 64px;
    height: 64px;
    border-radius: 50%;
    background: #25D366;
    display: flex; align-items: center; justify-content: center;
    box-shadow: 0 4px 16px rgba(37,211,102,0.4);
    cursor: pointer;
    transition: transform 0.3s, box-shadow 0.3s;
    text-decoration: none;
    animation: whatsapp-pulse 2.5s infinite;
">
    <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" fill="#fff" viewBox="0 0 16 16">
        <path d="M13.601 2.326A7.85 7.85 0 0 0 7.994 0C3.627 0 .068 3.558.064 7.926c0 1.399.366 2.76 1.057 3.965L0 16l4.204-1.102a7.9 7.9 0 0 0 3.79.965h.004c4.368 0 7.926-3.558 7.93-7.93A7.9 7.9 0 0 0 13.6 2.326zM7.994 14.521a6.6 6.6 0 0 1-3.356-.92l-.24-.144-2.494.654.666-2.433-.156-.251a6.56 6.56 0 0 1-1.007-3.505c0-3.626 2.957-6.584 6.591-6.584a6.56 6.56 0 0 1 4.66 1.931 6.56 6.56 0 0 1 1.928 4.66c-.004 3.639-2.961 6.592-6.592 6.592m3.615-4.934c-.197-.099-1.17-.578-1.353-.646-.182-.065-.315-.099-.445.099-.133.197-.513.646-.627.775-.114.133-.232.148-.43.05-.197-.1-.836-.308-1.592-.985-.589-.525-.985-1.175-1.103-1.372-.114-.198-.011-.304.088-.403.087-.088.197-.232.296-.346.1-.114.133-.198.198-.33.065-.134.034-.248-.015-.347-.05-.099-.445-1.076-.612-1.47-.16-.389-.325-.335-.445-.34-.114-.007-.247-.007-.38-.007a.73.73 0 0 0-.529.247c-.182.198-.691.677-.691 1.654s.71 1.916.81 2.049c.098.133 1.394 2.132 3.383 2.992.47.205.84.326 1.129.418.475.152.904.129 1.246.08.38-.058 1.171-.48 1.338-.943.164-.464.164-.86.114-.943-.049-.084-.182-.133-.38-.232z"/>
    </svg>
</a>

<style>
@keyframes whatsapp-pulse {
    0% { box-shadow: 0 4px 16px rgba(37,211,102,0.4), 0 0 0 0 rgba(37,211,102,0.5); }
    70% { box-shadow: 0 4px 16px rgba(37,211,102,0.4), 0 0 0 18px rgba(37,211,102,0); }
    100% { box-shadow: 0 4px 16px rgba(37,211,102,0.4), 0 0 0 0 rgba(37,211,102,0); }
}
#whatsappBtn:hover {
    transform: scale(1.1) !important;
    box-shadow: 0 6px 24px rgba(37,211,102,0.6) !important;
}
</style>

<script>
function openWhatsChat(e) {
    e.preventDefault();
    const phone = '5500000000000'; 
    const msg = encodeURIComponent('Olá! Tenho interesse no HackConcursos e gostaria de saber mais sobre a plataforma.');
    window.open('https://wa.me/' + phone + '?text=' + msg, '_blank');
}
</script>

<script>
// Lógica do Accordion FAQ
function toggleFaq(el) {
    const answer = el.nextElementSibling;
    const icon = el.querySelector('i');
    const isExpanded = answer.style.display === 'block';
    
    // Fechar todos
    document.querySelectorAll('.faq-answer').forEach(a => a.style.display = 'none');
    document.querySelectorAll('.faq-question i').forEach(i => i.className = 'bi bi-chevron-down');
    
    // Abrir o clicado se não estava expandido
    if (!isExpanded) {
        answer.style.display = 'block';
        icon.className = 'bi bi-chevron-up text-neon';
    }
}

// Lógica do Carousel (3 cards por vez, 2 páginas)
let currentPage = 0;
const totalCards = document.querySelectorAll('.carousel-card').length;
const cardsPerPage = window.innerWidth <= 768 ? 1 : 3;
const totalPages = Math.ceil(totalCards / cardsPerPage);
const track = document.getElementById('testimonialTrack');
const dots = document.querySelectorAll('.carousel-dot');

function updateCarousel() {
    // Cada página = deslocamento de (cardsPerPage / totalCards * 100)%
    const offset = currentPage * (cardsPerPage / totalCards) * 100;
    track.style.transform = `translateX(-${offset}%)`;
    dots.forEach((dot, i) => dot.classList.toggle('active', i === currentPage));
}

function moveCarousel(dir) {
    currentPage += dir;
    if (currentPage < 0) currentPage = totalPages - 1;
    if (currentPage >= totalPages) currentPage = 0;
    updateCarousel();
    resetAutoplay();
}

function goToSlide(index) {
    currentPage = index;
    updateCarousel();
    resetAutoplay();
}

// Auto-play a cada 6 segundos
let autoplay = setInterval(() => moveCarousel(1), 6000);
function resetAutoplay() {
    clearInterval(autoplay);
    autoplay = setInterval(() => moveCarousel(1), 6000);
}

// Recalcular ao redimensionar
window.addEventListener('resize', () => {
    currentPage = 0;
    updateCarousel();
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
