<?php
require_once __DIR__ . '/../config/config.php';
exigirLogin('../public/login.php');

$db  = getDB();
$uid = (int)$_SESSION['usuario_id'];

// Buscar edital ativo do usuário
$editQ = $db->prepare("SELECT * FROM editais WHERE usuario_id=? AND ativo=1 AND status_processamento='concluido' ORDER BY criado_em DESC LIMIT 1");
$editQ->execute([$uid]);
$edital = $editQ->fetch();

// Buscar histórico de chat
$historico = [];
if ($edital) {
    $hq = $db->prepare("SELECT papel, mensagem, criado_em FROM mensagens_chat WHERE usuario_id=? AND edital_id=? ORDER BY criado_em ASC LIMIT 50");
    $hq->execute([$uid, $edital['id']]);
    $historico = $hq->fetchAll();
}

$page_title = 'Tira-Dúvidas';
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
    <div class="card-glass" style="text-align:center;padding:3rem;">
      <div style="font-size:3rem;margin-bottom:1rem;">📄</div>
      <h3>Nenhum edital analisado</h3>
      <p style="color:var(--text-secondary);margin:0.75rem 0 1.5rem;">Você precisa enviar e processar um edital antes de usar o Tira-Dúvidas.</p>
      <a href="upload_edital.php" class="btn-hc btn-primary-hc"><i class="bi bi-cloud-upload"></i> Enviar Edital</a>
    </div>
    <?php else: ?>

    <div class="grid-2" style="grid-template-columns:1fr 280px;gap:1.5rem;align-items:start;">

      <!-- CHAT -->
      <div class="card-glass" style="display:flex;flex-direction:column;height:600px;">
        <!-- Header do chat -->
        <div class="card-header-hc">
          <div style="width:36px;height:36px;border-radius:50%;background:linear-gradient(135deg,var(--accent-purple),var(--accent-blue));display:flex;align-items:center;justify-content:center;font-size:1rem;">🤖</div>
          <div>
            <div class="fw-700" style="font-size:0.9rem;">Mentor Hack IA</div>
            <div style="font-size:0.72rem;color:var(--neon-green);">● Online · Mentor Educacional</div>
          </div>
          <button class="btn-hc btn-ghost btn-sm" style="margin-left:auto;" onclick="limparChat()">
            Limpar
          </button>
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
          $sugestoes = ['Qual a data da prova?','Quais documentos preciso levar?','Qual o critério de desempate?','Preciso de nível superior?'];
          foreach ($sugestoes as $s):
          ?>
          <button onclick="enviarSugestao('<?= htmlspecialchars($s, ENT_QUOTES) ?>')" class="btn-hc btn-ghost" style="font-size:0.75rem;padding:0.3rem 0.65rem;border-radius:var(--radius-full);">
            <?= $s ?>
          </button>
          <?php endforeach; ?>
        </div>

        <!-- Input -->
        <div class="chat-input-area">
          <textarea id="chat-input" class="form-control-hc" rows="1" placeholder="Tire sua dúvida sobre matérias ou o edital..." onkeydown="if(event.key==='Enter' && !event.shiftKey){event.preventDefault();enviar();}"></textarea>
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
              <div class="fw-700 text-blue" style="font-size:0.9rem;"><?= sanitize($edital['nome_concurso']) ?></div>
            </div>
            <?php if ($edital['banca']): ?>
            <div><div class="form-label">Banca</div><div style="font-size:0.88rem;"><?= sanitize($edital['banca']) ?></div></div>
            <?php endif; ?>
            <?php if ($edital['data_prova']): ?>
            <div><div class="form-label">Data da Prova</div>
              <div class="fw-700 text-neon"><?= date('d/m/Y', strtotime($edital['data_prova'])) ?></div>
            </div>
            <?php endif; ?>
            <a href="upload_edital.php" class="btn-hc btn-ghost btn-sm w-100">Trocar Edital</a>
          </div>
        </div>

        <!-- Perguntas sugeridas por categoria -->
        <div class="card-glass">
          <div class="card-header-hc"><h5>💡 Pergunte sobre</h5></div>
          <div class="card-body" style="display:flex;flex-direction:column;gap:0.4rem;">
            <?php
            $cats = [
              ['📅','Datas e prazos', 'Quais são as datas importantes do concurso?'],
              ['📋','Requisitos','Quais são os requisitos para o cargo?'],
              ['📦','Documentação','Quais documentos são exigidos na inscrição?'],
              ['🏆','Pontuação','Como é calculada a nota final?'],
              ['♿','Vagas especiais','Há vagas para PcD ou negros?'],
              ['🖊️','Prova','Quantas questões tem a prova?'],
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
    <?php endif; ?>
  </main>
</div>

<script>
const editalId = <?= $edital ? $edital['id'] : 'null' ?>;
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
  if (!pergunta || !editalId) return;

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
    } else {
      addMsg('⚠️ ' + data.msg, 'ai');
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
