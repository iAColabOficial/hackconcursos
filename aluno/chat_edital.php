<?php
require_once __DIR__ . '/../config/config.php';
exigirLogin('../public/login.php');

$db  = getDB();
$uid = (int)$_SESSION['usuario_id'];

// 1. Dados do Usuário (Plano e Tokens)
$usrQ = $db->prepare("SELECT plano, token_saldo FROM usuarios WHERE id = ?");
$usrQ->execute([$uid]);
$usr = $usrQ->fetch();

// 2. Buscar edital selecionado no perfil
$perfilQ = $db->prepare("SELECT biblioteca_edital_id FROM perfis_usuario WHERE usuario_id = ?");
$perfilQ->execute([$uid]);
$perfil = $perfilQ->fetch();

$edital = null;
if ($perfil && $perfil['biblioteca_edital_id']) {
    $editQ = $db->prepare("SELECT * FROM biblioteca_editais WHERE id = ?");
    $editQ->execute([$perfil['biblioteca_edital_id']]);
    $edital = $editQ->fetch();
} else {
    // Fallback para upload antigo se não houver da biblioteca
    $editQ = $db->prepare("SELECT * FROM editais WHERE usuario_id=? AND ativo=1 ORDER BY criado_em DESC LIMIT 1");
    $editQ->execute([$uid]);
    $edital = $editQ->fetch();
}

// 3. Buscar histórico de chat
$historico = [];
if ($edital) {
    $edital_id = $perfil['biblioteca_edital_id'] ?? $edital['id'];
    $hq = $db->prepare("SELECT papel, mensagem, criado_em FROM mensagens_chat WHERE usuario_id=? AND (edital_id=? OR edital_id IS NULL) ORDER BY criado_em ASC LIMIT 50");
    $hq->execute([$uid, $edital_id]);
    $historico = $hq->fetchAll();
}

$page_title = 'Mentor IA - HackConcursos';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="dashboard-layout" style="position:relative;z-index:1;">
  <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>
  <main class="main-content">

    <div class="page-header">
      <div class="page-breadcrumb"><a href="dashboard.php">Dashboard</a><span class="sep">›</span> Tira-Dúvidas</div>
      <h2>💬 Mentor Hack IA</h2>
      <p>Seu mentor pessoal para dúvidas de matérias, estratégia de estudos e edital.</p>
    </div>

    <?php if (!$edital): ?>
    <div class="alert alert-info-hc mb-4">
      <i class="bi bi-info-circle"></i> Você está no modo <strong>Mentor Geral</strong>. Envie um edital em <a href="upload_edital.php" class="text-white fw-700">Meus Concursos</a> para receber orientações específicas sobre um concurso.
    </div>
    <?php endif; ?>

    <div class="grid-2" style="grid-template-columns:1fr 280px;gap:1.5rem;align-items:start;">

      <!-- CHAT -->
      <div class="card-glass" style="display:flex;flex-direction:column;height:600px;">
        <!-- Header do chat -->
        <div class="card-header-hc" style="border-bottom: 1px solid var(--border-glass);">
          <div style="width:40px;height:40px;border-radius:50%;background:linear-gradient(135deg,var(--neon-green),var(--accent-blue));display:flex;align-items:center;justify-content:center;font-size:1.2rem;box-shadow:0 0 15px rgba(34,197,94,0.3);">🤖</div>
          <div style="margin-left:0.5rem;">
            <div class="fw-800" style="font-size:1rem; letter-spacing:-0.01em;">Mentor Estratégico IA</div>
            <?php if ($usr['plano'] === 'premium'): ?>
                <div style="font-size:0.75rem;color:var(--neon-green); font-weight:700;">● MODO GUERRA ATIVO</div>
            <?php else: ?>
                <div style="font-size:0.75rem;color:var(--accent-purple); font-weight:700;">● Mentor IA (Consome Tokens)</div>
            <?php endif; ?>
          </div>
          
          <div style="margin-left:auto; display:flex; gap:0.75rem; align-items:center;">
             <div class="badge-hc badge-purple" id="token-badge">
                <i class="bi bi-coin"></i> <span id="token-count"><?= $usr['plano'] === 'premium' ? '∞' : $usr['token_saldo'] ?></span> Tokens
             </div>
             <button class="btn-hc btn-ghost btn-sm" onclick="limparChat()" title="Limpar conversa">
                <i class="bi bi-trash"></i>
             </button>
          </div>
        </div>

        <!-- Mensagens -->
        <div class="chat-messages" id="chat-messages">
          <!-- Mensagem inicial do bot -->
          <div class="msg-bubble msg-ai">
            <div class="ai-icon">🤖 Mentor Hack</div>
            Olá! Sou seu <strong>Mentor Hack IA</strong>. 🚀<br>
            Estou aqui para te ajudar a dominar as matérias, criar estratégias de estudo imbatíveis ou tirar dúvidas específicas sobre o seu concurso. O que vamos aprender hoje?
          </div>

          <!-- Histórico -->
          <?php foreach ($historico as $msg): ?>
          <div class="msg-bubble <?= $msg['papel'] === 'user' ? 'msg-user' : 'msg-ai' ?>">
            <?php if($msg['papel'] === 'model'): ?><div class="ai-icon">🤖 Assistente</div><?php endif; ?>
            <?= nl2br(sanitize($msg['mensagem'])) ?>
          </div>
          <?php endforeach; ?>
        </div>

        <!-- Sugestões rápidas -->
        <div id="sugestoes" style="padding:0.5rem 1rem;border-top:1px solid var(--border-glass);display:flex;gap:0.5rem;flex-wrap:wrap;">
          <?php
          $sugestoes = [
            'Como posso memorizar melhor?',
            'O que é a técnica Pomodoro?',
            'Estou desmotivado, o que fazer?',
            'Qual o meu progresso hoje?',
            'Me faça 3 questões sobre o edital'
          ];
          foreach ($sugestoes as $s):
          ?>
          <button onclick="enviarSugestao('<?= htmlspecialchars($s, ENT_QUOTES) ?>')" class="btn-hc btn-ghost" style="font-size:0.75rem;padding:0.3rem 0.65rem;border-radius:var(--radius-full);">
            <?= $s ?>
          </button>
          <?php endforeach; ?>
        </div>

        <!-- Input -->
        <div class="chat-input-area">
          <textarea id="chat-input" class="form-control-hc" rows="1" placeholder="Dívidas de matérias, estratégia ou edital..." onkeydown="if(event.key==='Enter' && !event.shiftKey){event.preventDefault();enviar();}"></textarea>
          <button class="btn-hc btn-ai" onclick="enviar()" id="btn-enviar">
            Enviar
          </button>
        </div>
      </div>

      <!-- PAINEL LATERAL -->
      <div style="display:flex;flex-direction:column;gap:1rem;">
        <!-- Info do edital -->
        <div class="card-glass">
          <div class="card-header-hc"><h5><i class="bi bi-file-text text-blue"></i> Seu Edital</h5></div>
          <div class="card-body" style="display:flex;flex-direction:column;gap:0.65rem;">
            <div>
              <div class="form-label">Concurso</div>
              <div class="fw-700 text-blue" style="font-size:0.9rem;"><?= sanitize($edital['nome_concurso'] ?? 'Mentor Geral') ?></div>
            </div>
            <?php if ($edital && !empty($edital['data_prova'])): ?>
            <div><div class="form-label">Data da Prova</div>
              <div class="fw-700 text-neon"><?= date('d/m/Y', strtotime($edital['data_prova'])) ?></div>
            </div>
            <?php endif; ?>
          </div>
        </div>

        <!-- Sugestões de Estratégia -->
        <div class="card-glass">
          <div class="card-header-hc"><h5>🚀 Estratégia Hack</h5></div>
          <div class="card-body" style="display:flex;flex-direction:column;gap:0.4rem;">
            <?php
            $cats = [
              ['🧠','Técnicas de Estudo', 'Quais as melhores técnicas para estudar exatas?'],
              ['⚖️','Prioridades IA','Com base no meu progresso, o que devo priorizar?'],
              ['⏱️','Gestão de Tempo','Como organizar meu tempo se só tenho 2 horas?'],
              ['📊','Análise de Edital','Explique os critérios de desempate deste edital.'],
            ];
            foreach ($cats as [$ico,$cat,$q]):
            ?>
            <button onclick="enviarSugestao('<?= htmlspecialchars($q,ENT_QUOTES) ?>')"
                    style="display:flex;align-items:center;gap:0.65rem;background:transparent;border:1px solid var(--border-glass);border-radius:var(--radius-md);padding:0.55rem 0.75rem;cursor:pointer;transition:all 0.2s;text-align:left;color:var(--text-secondary);font-size:0.82rem;font-family:var(--font-main);"
                    onmouseover="this.style.borderColor='var(--accent-purple)';this.style.color='var(--accent-purple)'"
                    onmouseout="this.style.borderColor='var(--border-glass)';this.style.color='var(--text-secondary)'">
              <span><?= $ico ?></span><?= $cat ?>
            </button>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
    </div>
  </main>
</div>

<script>
const editalId = <?= $edital ? (int)$edital['id'] : 0 ?>;
const msgs = document.getElementById('chat-messages');

function scrollBottom() {
  if(msgs) msgs.scrollTop = msgs.scrollHeight;
}
scrollBottom();

function addMsg(texto, tipo) {
  const div = document.createElement('div');
  div.className = 'msg-bubble ' + (tipo === 'user' ? 'msg-user' : 'msg-ai');
  if (tipo === 'ai') {
    const lbl = document.createElement('div');
    lbl.className = 'ai-icon';
    lbl.textContent = '🤖 Assistente';
    div.appendChild(lbl);
  }
  div.innerHTML += texto.replace(/\n/g,'<br>');
  msgs.appendChild(div);
  scrollBottom();
  return div;
}

function addTyping() {
  const div = document.createElement('div');
  div.className = 'msg-bubble msg-ai';
  div.id = 'typing';
  div.innerHTML = '<div class="ai-icon">🤖 Assistente</div><span style="display:flex;gap:4px;align-items:center;"><span class="dot"></span><span class="dot"></span><span class="dot"></span></span>';
  msgs.appendChild(div);
  scrollBottom();
}
function removeTyping() {
  const el = document.getElementById('typing');
  if(el) el.remove();
}

async function enviar() {
  const inp = document.getElementById('chat-input');
  const pergunta = inp.value.trim();
  if (!pergunta) return;

  addMsg(pergunta, 'user');
  inp.value = '';
  document.getElementById('sugestoes').style.display = 'none';

  const btn = document.getElementById('btn-enviar');
  btn.disabled = true;
  addTyping();

  try {
    const r = await fetch('<?= APP_URL ?>/controllers/chat_action.php', {
      method:'POST',
      headers:{'Content-Type':'application/json'},
      body: JSON.stringify({pergunta, edital_id: editalId})
    });
    const data = await r.json();
    removeTyping();
    if (data.ok) {
      addMsg(data.resposta, 'ai');
      // Atualizar contador de tokens
      if (document.getElementById('token-count')) {
          document.getElementById('token-count').textContent = data.tokens_restantes;
      }
    } else {
      if (data.paywall) {
          addMsg('🚨 <strong>Seus tokens acabaram!</strong><br>Para continuar recebendo orientações estratégicas de 98% de precisão, você precisa de mais créditos.', 'ai');
          setTimeout(() => {
              if(confirm('Seus tokens acabaram. Deseja comprar mais agora?')) {
                  location.href = 'meu_plano.php';
              }
          }, 1500);
      } else {
          addMsg('⚠️ ' + data.msg, 'ai');
      }
    }
  } catch(e) {
    removeTyping();
    addMsg('❌ Erro de conexão. Tente novamente.', 'ai');
  }
  btn.disabled = false;
}

function enviarSugestao(txt) {
  document.getElementById('chat-input').value = txt;
  enviar();
}

function limparChat() {
  if (!confirm('Limpar todo o histórico de conversa?')) return;
  fetch('<?= APP_URL ?>/controllers/chat_action.php', {
    method:'POST',
    headers:{'Content-Type':'application/json'},
    body: JSON.stringify({limpar:true, edital_id: editalId})
  });
  msgs.querySelectorAll('.msg-bubble:not(:first-child)').forEach(el=>el.remove());
}

// Estilo dos pontos de digitação
const style = document.createElement('style');
style.textContent = `.dot{width:7px;height:7px;border-radius:50%;background:var(--text-muted);animation:blink 1.2s infinite both;}.dot:nth-child(2){animation-delay:.2s}.dot:nth-child(3){animation-delay:.4s}@keyframes blink{0%,80%,100%{opacity:.2}40%{opacity:1}}`;
document.head.appendChild(style);
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
