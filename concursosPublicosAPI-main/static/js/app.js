// ─── STATE ───────────────────────────────────────────────────────────
const S = {
  user: null, // {id, nome, email}
  isLoginMode: true, // true=login, false=cadastro
  dificuldade: 'Média',
  planoId: null,
  cronograma: [],
  semanaAtual: 0,
  simId: null,
  questoes: [],
  respostas: {},
};

// ─── AUTH & VIEWS ─────────────────────────────────────────────────────
function goView(v) {
  document.querySelectorAll('.view').forEach(el => el.classList.remove('on'));
  document.querySelectorAll('.tab').forEach(el => el.classList.remove('on','on-g'));
  const el = document.getElementById(v + '-view');
  if (el) el.classList.add('on');
  document.getElementById('crono-view').style.display = 'none';
  document.getElementById('sim-exec-view').style.display = 'none';
  document.getElementById('res-view').style.display = 'none';
  
  if (v !== 'auth') {
    const tab = document.getElementById('tab-' + v);
    if (tab) tab.classList.add(v === 'sim' ? 'on-g' : 'on');
    if (v === 'hist') loadHist();
  }
}

async function checkAuth() {
  try {
    const res = await fetch('/api/auth/me');
    const data = await res.json();
    if (res.ok && data.id) {
      S.user = data;
      showUserLogged();
    } else {
      goView('auth');
    }
  } catch {
    goView('auth');
  }
}

function showUserLogged() {
  document.getElementById('user-name-display').textContent = S.user.nome;
  document.getElementById('auth-controls').style.display = 'flex';
  document.getElementById('main-nav').style.display = 'flex';
  goView('plano');
}

function toggleAuthMode() {
  S.isLoginMode = !S.isLoginMode;
  document.getElementById('auth-title').textContent = S.isLoginMode ? 'Entrar' : 'Cadastre-se';
  document.getElementById('auth-sub').textContent = S.isLoginMode ? 'Acesse seus planos e simulados' : 'Crie sua conta para começar';
  document.getElementById('auth-nome-fg').style.display = S.isLoginMode ? 'none' : 'block';
  document.getElementById('btn-auth-submit').textContent = S.isLoginMode ? 'Entrar' : 'Criar conta';
  document.getElementById('auth-switch-text').textContent = S.isLoginMode ? 'Não tem conta?' : 'Já tem uma conta?';
  document.getElementById('auth-switch-btn').textContent = S.isLoginMode ? 'Cadastre-se' : 'Entrar';
}

async function submitAuth() {
  const email = document.getElementById('auth-email').value.trim();
  const senha = document.getElementById('auth-senha').value;
  const nome = document.getElementById('auth-nome').value.trim();

  if (!email || !senha || (!S.isLoginMode && !nome)) {
    toast('⚠️ Preencha todos os campos.'); return;
  }

  const endpoint = S.isLoginMode ? '/api/auth/login' : '/api/auth/cadastro';
  const body = S.isLoginMode ? { email, senha } : { nome, email, senha };

  loading(true, S.isLoginMode ? 'Autenticando...' : 'Criando conta...', 'Aguarde');
  try {
    const res = await fetch(endpoint, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(body)
    });
    const data = await res.json();
    if (!res.ok) {
      toast('❌ ' + (data.error || 'Erro na autenticação'));
      return;
    }
    S.user = data;
    showUserLogged();
  } catch {
    toast('❌ Erro de conexão.');
  } finally {
    loading(false);
  }
}

async function fazerLogout() {
  await fetch('/api/auth/logout', { method: 'POST' });
  S.user = null;
  document.getElementById('auth-controls').style.display = 'none';
  document.getElementById('main-nav').style.display = 'none';
  
  // Limpa campos
  document.getElementById('auth-email').value = '';
  document.getElementById('auth-senha').value = '';
  document.getElementById('auth-nome').value = '';
  
  if (!S.isLoginMode) toggleAuthMode(); // Volta pra tela de login
  
  goView('auth');
}

// ─── TOAST & LOADING ──────────────────────────────────────────────────
function toast(msg) {
  const t = document.getElementById('toast');
  t.textContent = msg; t.classList.add('on');
  setTimeout(() => t.classList.remove('on'), 4000);
}

function loading(show, title='Processando...', sub='Aguarde') {
  document.getElementById('loading').classList.toggle('on', show);
  document.getElementById('ld-t').textContent = title;
  document.getElementById('ld-s').textContent = sub;
}

// ─── DIFICULDADE ──────────────────────────────────────────────────────
function setDiff(d) {
  S.dificuldade = d;
  ['f','m','d'].forEach(x => {
    const b = document.getElementById('df-'+x);
    b.className = 'df';
  });
  const map = { 'Fácil': ['f','sf'], 'Média': ['m','sm'], 'Difícil': ['d','sd'] };
  const [key, cls] = map[d];
  document.getElementById('df-'+key).classList.add(cls);
}
setDiff('Média');

// ═══════════════════════════════════════════════════════════════════════
//  PLANO DE ESTUDOS
// ═══════════════════════════════════════════════════════════════════════
let matCount = 0;
function addMateria(nome='', peso=1, topicos='') {
  matCount++;
  const id = 'mat-' + matCount;
  const div = document.createElement('div');
  div.className = 'mat-card'; div.id = id;
  div.innerHTML = `
    <div class="mat-hdr">
      <input class="inp" type="text" placeholder="Nome da matéria (ex: Direito Constitucional)"
             id="${id}-nome" value="${nome}">
      <div class="peso-row">
        <button class="pw${peso===1?' s1':''}" onclick="setPeso('${id}',1)" title="Peso 1 - Baixo">1</button>
        <button class="pw${peso===2?' s2':''}" onclick="setPeso('${id}',2)" title="Peso 2 - Médio">2</button>
        <button class="pw${peso===3?' s3':''}" onclick="setPeso('${id}',3)" title="Peso 3 - Alto">3</button>
      </div>
      <button class="del" onclick="document.getElementById('${id}').remove()">✕</button>
    </div>
    <div class="fg">
      <label>Tópicos <span style="color:var(--t3);font-weight:400;text-transform:none">(um por linha)</span></label>
      <textarea class="inp topicos-inp" id="${id}-top" placeholder="Princípios Constitucionais\nDireitos Fundamentais\nOrganização do Estado">${topicos}</textarea>
      <div class="hint">Peso 1 = revisão leve · Peso 2 = moderado · Peso 3 = foco máximo (aparece mais vezes)</div>
    </div>
  `;
  div.querySelector(`[onclick="setPeso('${id}',${peso})"]`).click = null;
  document.getElementById('materias-container').appendChild(div);
  const btns = div.querySelectorAll('.pw');
  btns.forEach(b => b.classList.remove('s1','s2','s3'));
  div.querySelector(`[onclick="setPeso('${id}',${peso})"]`)?.classList.add(`s${peso}`);
}

function setPeso(id, p) {
  const card = document.getElementById(id);
  card.querySelectorAll('.pw').forEach(b => b.classList.remove('s1','s2','s3'));
  card.querySelectorAll('.pw')[p-1]?.classList.add(`s${p}`);
  card.dataset.peso = p;
}

addMateria('Língua Portuguesa', 2, 'Interpretação de Texto\nCrase\nConcordância Verbal\nRegência');
addMateria('Direito Administrativo', 3, 'Princípios da Adm. Pública\nAtos Administrativos\nLicitações');

async function gerarPlano() {
  const nomeConc  = document.getElementById('nome-conc').value.trim();
  const dtInicio  = document.getElementById('dt-inicio').value;
  const dtProva   = document.getElementById('dt-prova').value;
  const horas     = parseFloat(document.getElementById('hrs').value);

  if (!dtInicio) { toast('⚠️ Informe a data de início dos estudos.'); return; }
  if (!dtProva)  { toast('⚠️ Informe a data da prova.'); return; }

  const materias = [];
  document.querySelectorAll('.mat-card').forEach(card => {
    const id   = card.id;
    const nome = document.getElementById(id+'-nome')?.value.trim();
    const top  = document.getElementById(id+'-top')?.value.trim();
    const peso = parseInt(card.dataset.peso || card.querySelectorAll('.pw.s1,.pw.s2,.pw.s3')[0]?.textContent || 1);
    if (nome && top) {
      const topicos = top.split('\n').map(t => t.trim()).filter(Boolean);
      if (topicos.length) materias.push({ nome, peso, topicos });
    }
  });

  if (!materias.length) { toast('⚠️ Adicione pelo menos uma matéria com tópicos.'); return; }

  loading(true, 'Gerando cronograma...', 'Algoritmo Python puro · Sem IA');
  try {
    const res = await fetch('/api/plano/gerar', {
      method: 'POST', headers: {'Content-Type':'application/json'},
      body: JSON.stringify({ nome_concurso: nomeConc || 'Concurso', data_inicio: dtInicio,
                             data_prova: dtProva, horas_diarias: horas, materias })
    });
    if (res.status === 401) { fazerLogout(); return; }
    const data = await res.json();
    if (data.error) { toast('❌ ' + data.error); return; }
    S.planoId = data.plano_id;
    S.cronograma = data.cronograma;
    renderCrono(data);
  } catch { toast('❌ Erro de conexão.'); }
  finally { loading(false); }
}

function renderCrono(data) {
  const r = data.resumo;
  document.getElementById('plano-view').classList.remove('on');
  document.getElementById('crono-view').style.display = 'block';
  window.scrollTo({top:0,behavior:'smooth'});

  document.getElementById('crono-titulo').textContent = data.nome_concurso;
  document.getElementById('crono-sub').textContent =
    `${formatDate(data.data_inicio)} → ${formatDate(data.data_prova)} · ${r.horas_por_materia ? Object.keys(r.horas_por_materia).length : 0} matérias`;

  const sg = document.getElementById('res-stats');
  sg.innerHTML = [
    {v: r.total_dias, l: 'Dias Totais', c: 'var(--neon)'},
    {v: r.dias_estudo, l: 'Dias de Estudo', c: 'var(--cyan)'},
    {v: r.dias_revisao, l: 'Dias de Revisão', c: 'var(--amber)'},
    {v: r.total_horas + 'h', l: 'Horas Totais', c: 'var(--green)'},
  ].map(s => `<div class="glass sc"><div class="sv" style="color:${s.c}">${s.v}</div><div class="sl">${s.l}</div></div>`).join('');

  const hpm = r.horas_por_materia || {};
  const maxH = Math.max(...Object.values(hpm), 1);
  document.getElementById('bars-content').innerHTML = Object.entries(hpm).map(([mat,h]) =>
    `<div class="bar-row">
      <div class="bl">${mat}</div>
      <div class="bk"><div class="bf" style="width:${Math.round((h/maxH)*100)}%"></div></div>
      <div class="bv">${h}h</div>
    </div>`
  ).join('');

  const semanas = agruparSemanas(data.cronograma);
  S.semanaAtual = 0;
  const wf = document.getElementById('wf');
  wf.innerHTML = semanas.map((s,i) =>
    `<button class="wf-btn${i===0?' on':''}" onclick="showSemana(${i})">${i===0?'Semana 1':
      i===semanas.length-1&&semanas.length>1?'Revisão Final':'Semana '+(i+1)}</button>`
  ).join('');
  window._semanas = semanas;
  showSemana(0);
}

function showSemana(idx) {
  S.semanaAtual = idx;
  document.querySelectorAll('.wf-btn').forEach((b,i) => b.classList.toggle('on', i===idx));
  const dias = window._semanas[idx] || [];
  document.getElementById('dias-list').innerHTML = dias.map(dia => {
    const tipo = dia.tipo;
    const cls  = tipo==='revisao_final'?'dia-r':tipo==='ciclo_revisao'?'dia-c':'dia-e';
    const bcls = tipo==='revisao_final'?'tb-r':tipo==='ciclo_revisao'?'tb-c':'tb-e';
    const blbl = tipo==='revisao_final'?'REVISÃO FINAL':tipo==='ciclo_revisao'?'REVISÃO CICLO':'ESTUDO';
    const sessHtml = dia.sessoes.map(s =>
      `<span class="stag"><strong>${s.materia}</strong> · ${s.topico}</span>`
    ).join('');
    return `<div class="dia ${cls}">
      <div class="dia-top">
        <span class="dia-dt">${dia.dia_semana}, ${formatDate(dia.data)} · ${dia.horas}h</span>
        <span class="tbadge ${bcls}">${blbl}</span>
      </div>
      <div class="sess">${sessHtml}</div>
    </div>`;
  }).join('');
}

function agruparSemanas(crono) {
  const semanas = [];
  let sem = [];
  crono.forEach((dia, i) => {
    sem.push(dia);
    if (sem.length === 7 || i === crono.length - 1) {
      semanas.push(sem); sem = [];
    }
  });
  return semanas;
}

function voltarPlano() {
  document.getElementById('crono-view').style.display = 'none';
  goView('plano');
}

function goSimFromPlano() {
  goView('sim');
  toast('💡 O simulado será vinculado ao seu plano!');
}

// ═══════════════════════════════════════════════════════════════════════
//  SIMULADO
// ═══════════════════════════════════════════════════════════════════════
async function gerarSimulado() {
  const mat = document.getElementById('s-mat').value.trim();
  const top = document.getElementById('s-top').value.trim();
  const banca = document.getElementById('s-banca').value;
  const qty   = parseInt(document.getElementById('qty').value);

  if (!mat) { toast('⚠️ Preencha a matéria.'); return; }
  if (!top) { toast('⚠️ Preencha o tópico.'); return; }

  const subs = ['Analisando estilo da banca...','Elaborando enunciados...','Criando alternativas...','Verificando gabarito...','Finalizando questões...'];
  let si = 0;
  loading(true, 'Gerando simulado com IA...', subs[0]);
  const intv = setInterval(() => {
    si = (si+1)%subs.length;
    document.getElementById('ld-s').textContent = subs[si];
  }, 2200);

  try {
    const res = await fetch('/api/simulado/gerar', {
      method:'POST', headers:{'Content-Type':'application/json'},
      body: JSON.stringify({ materia:mat, topico:top, banca, dificuldade:S.dificuldade,
                             quantidade:qty, plano_id:S.planoId })
    });
    if (res.status === 401) { clearInterval(intv); fazerLogout(); return; }
    const data = await res.json();
    if (data.error) { toast('❌ ' + data.error); return; }
    S.simId = data.simulado_id;
    S.questoes = data.questoes;
    S.respostas = {};
    renderSim(mat, top, banca);
  } catch { toast('❌ Erro de conexão.'); }
  finally { clearInterval(intv); loading(false); }
}

function renderSim(mat, top, banca) {
  document.getElementById('sim-view').classList.remove('on');
  document.getElementById('sim-exec-view').style.display = 'block';
  window.scrollTo({top:0,behavior:'smooth'});

  document.getElementById('sim-meta').innerHTML =
    `<span class="tag tag-n">🏛️ ${banca}</span>
     <span class="tag">${mat}</span>
     <span class="tag">${top}</span>
     <span class="tag">${S.dificuldade}</span>
     <span class="tag">${S.questoes.length} questões</span>`;

  const ql = document.getElementById('q-list');
  ql.innerHTML = '';
  S.questoes.forEach((q, idx) => {
    const lets = Object.keys(q.alternativas);
    const altsH = lets.map(l =>
      `<button class="ab" id="ab-${q.id}-${l}" onclick="marcar(${q.id},'${l}',this)">
         <span class="al">${l}</span><span>${q.alternativas[l]}</span>
       </button>`
    ).join('');
    ql.innerHTML += `<div class="glass qcard">
      <div class="qnum">Questão ${idx+1} de ${S.questoes.length}</div>
      <div class="qtxt">${q.enunciado}</div>
      <div class="alts">${altsH}</div>
    </div>`;
  });
  atualizarProg();
}

function marcar(qid, letra, btn) {
  const q = S.questoes.find(x => x.id === qid);
  if (!q) return;
  Object.keys(q.alternativas).forEach(l => {
    document.getElementById(`ab-${qid}-${l}`)?.classList.remove('on');
  });
  btn.classList.add('on');
  S.respostas[qid] = letra;
  atualizarProg();
}

function atualizarProg() {
  const t = S.questoes.length, r = Object.keys(S.respostas).length;
  document.getElementById('prog-f').style.width = (t>0?Math.round(r/t*100):0)+'%';
  document.getElementById('ans-count').textContent = `${r} de ${t} respondidas`;
}

async function finalizarSim() {
  const t = S.questoes.length, r = Object.keys(S.respostas).length;
  if (r < t && !confirm(`Ainda faltam ${t-r} respostas. Finalizar mesmo assim?`)) return;
  loading(true, 'Corrigindo simulado...', 'Calculando nota...');
  try {
    const res = await fetch('/api/simulado/corrigir', {
      method:'POST', headers:{'Content-Type':'application/json'},
      body: JSON.stringify({ simulado_id: S.simId, respostas: S.respostas })
    });
    if (res.status === 401) { fazerLogout(); return; }
    const data = await res.json();
    if (data.error) { toast('❌ '+data.error); return; }
    renderResult(data);
  } catch { toast('❌ Erro ao corrigir.'); }
  finally { loading(false); }
}

function renderResult(data) {
  const { nota_final, acertos, total, gabarito } = data;
  document.getElementById('sim-exec-view').style.display = 'none';
  document.getElementById('res-view').style.display = 'block';
  window.scrollTo({top:0,behavior:'smooth'});

  const c = document.getElementById('sc-circle');
  const circ = 339.3;
  const color = nota_final>=70?'var(--green)':nota_final>=50?'var(--amber)':'var(--red)';
  c.style.stroke = color;
  setTimeout(() => { c.style.strokeDashoffset = circ - (nota_final/100)*circ; }, 100);

  const el = document.getElementById('r-pct');
  let cur = 0; const step = nota_final/40;
  const t = setInterval(() => {
    cur = Math.min(cur+step, nota_final);
    el.textContent = Math.round(cur)+'%';
    if (cur>=nota_final) clearInterval(t);
  }, 30);

  const msg = nota_final>=70?['🏆 Excelente!','Continue assim, você está no caminho!']:
               nota_final>=50?['💪 Bom esforço!','Revise os erros e tente novamente.']:
                              ['📚 Estude mais!','Releia as justificativas e pratique.'];
  document.getElementById('r-title').textContent = msg[0];
  document.getElementById('r-sub').textContent   = msg[1];
  document.getElementById('r-ac').textContent    = acertos;
  document.getElementById('r-er').textContent    = total - acertos;
  document.getElementById('r-tot').textContent   = total;

  const gl = document.getElementById('gab-list');
  gl.innerHTML = gabarito.map((q, i) => {
    const lets = Object.keys(q.alternativas);
    const altsH = lets.map(l => {
      let cls = '';
      if (l===q.resposta_correta) cls='c';
      else if (l===q.marcada && !q.acertou) cls='w';
      const ico = l===q.resposta_correta?' ✅':(l===q.marcada&&!q.acertou?' ❌':'');
      return `<div class="galt ${cls}"><span class="al" style="min-width:22px;width:22px;height:22px;font-size:.76rem">${l}</span>${q.alternativas[l]}${ico}</div>`;
    }).join('');
    const marcInfo = q.marcada
      ? (q.acertou?`<span style="color:var(--green)">Você marcou: ${q.marcada} ✅</span>`
                  :`<span style="color:var(--red)">Você marcou: ${q.marcada} ❌ · Correta: ${q.resposta_correta}</span>`)
      : `<span style="color:var(--t3)">Não respondida · Correta: ${q.resposta_correta}</span>`;
    return `<div class="glass gi ${q.acertou?'gi-ok':'gi-err'}">
      <div class="gi-top">
        <div><div class="gn">QUESTÃO ${i+1}</div><div style="font-size:.8rem;margin-top:3px">${marcInfo}</div></div>
        <span class="rb ${q.acertou?'rb-ok':'rb-err'}">${q.acertou?'ACERTO':'ERRO'}</span>
      </div>
      <div class="gq">${q.enunciado}</div>
      <div class="ga">${altsH}</div>
      <div class="just"><strong>💡 Justificativa:</strong> ${q.justificativa}</div>
    </div>`;
  }).join('');
}

function voltarSim() {
  S.simId=null; S.questoes=[]; S.respostas={};
  document.getElementById('sim-exec-view').style.display='none';
  document.getElementById('res-view').style.display='none';
  goView('sim');
}

// ═══════════════════════════════════════════════════════════════════════
//  HISTÓRICO
// ═══════════════════════════════════════════════════════════════════════
async function loadHist() {
  const el = document.getElementById('hist-content');
  el.innerHTML = '<div class="empty"><div class="ei">⏳</div><p>Carregando...</p></div>';
  try {
    const res = await fetch('/api/simulados/historico');
    if (res.status === 401) { fazerLogout(); return; }
    const rows = await res.json();
    if (!rows.length) {
      el.innerHTML = '<div class="empty"><div class="ei">📭</div><p>Nenhum simulado ainda.</p></div>'; return;
    }
    el.innerHTML = `<table class="htb">
      <thead><tr><th>Matéria</th><th>Tópico</th><th>Banca</th><th>Dific.</th><th>Qtd</th><th>Nota</th><th>Data</th></tr></thead>
      <tbody>${rows.map(r => {
        let nb = '—';
        if (r.nota_final!=null) {
          const c = r.nota_final>=70?'nb-a':r.nota_final>=50?'nb-m':'nb-b';
          nb = `<span class="nb ${c}">${r.nota_final}%</span>`;
        }
        const dt = r.criado_em?r.criado_em.split(' ')[0].split('-').reverse().join('/'):'—';
        return `<tr><td>${r.materia}</td><td>${r.topico}</td>
          <td><span class="tag tag-n" style="font-size:.72rem">${r.banca}</span></td>
          <td>${r.dificuldade}</td><td>${r.quantidade}</td><td>${nb}</td><td style="color:var(--t3);font-size:.8rem">${dt}</td></tr>`;
      }).join('')}</tbody></table>`;
  } catch {
    el.innerHTML = '<div class="empty"><div class="ei">❌</div><p>Erro ao carregar.</p></div>';
  }
}

// ─── UTILS ────────────────────────────────────────────────────────────
function formatDate(iso) {
  if (!iso) return '';
  const [y,m,d] = iso.split('-');
  return `${d}/${m}/${y}`;
}

// ─── INIT ─────────────────────────────────────────────────────────────
const today = new Date().toISOString().split('T')[0];
document.getElementById('dt-inicio').value = today;

// Checa sessão ao carregar
checkAuth();
