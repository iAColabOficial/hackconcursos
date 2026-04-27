<?php
require_once __DIR__ . '/../config/config.php';
exigirLogin('../login.php');

// Verificar se é admin
if (($_SESSION['perfil'] ?? '') !== 'admin') {
    flashMsg('danger', 'Acesso negado.');
    redirect('../aluno/dashboard.php');
}

$db = getDB();

// Processar Ações (Adicionar/Editar/Excluir)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $nome        = sanitize($_POST['nome_concurso']);
        $orgao       = sanitize($_POST['orgao']);
        $banca       = sanitize($_POST['banca']);
        $data_prova  = sanitize($_POST['data_prova']);
        $abrangencia = sanitize($_POST['abrangencia']);
        $numero_vagas= sanitize($_POST['numero_vagas']);
        $status      = $_POST['status'];
        
        $imagem_path = '';
        $pdf_path    = '';

        // Upload da Imagem (800x200)
        if (!empty($_FILES['imagem']['name'])) {
            $ext = pathinfo($_FILES['imagem']['name'], PATHINFO_EXTENSION);
            $new_name = 'card_' . time() . '.' . $ext;
            if (move_uploaded_file($_FILES['imagem']['tmp_name'], __DIR__ . '/../assets/img/' . $new_name)) {
                $imagem_path = 'assets/img/' . $new_name;
            }
        }

        // Upload do Edital PDF
        if (!empty($_FILES['edital_pdf']['name'])) {
            $new_name = 'edital_' . time() . '.pdf';
            if (move_uploaded_file($_FILES['edital_pdf']['tmp_name'], __DIR__ . '/../assets/editais/' . $new_name)) {
                $pdf_path = 'assets/editais/' . $new_name;
            }
        }

        try {
            $stmt = $db->prepare("INSERT INTO lib_editais (nome_concurso, orgao, banca, data_prova, abrangencia, numero_vagas, status, imagem, edital_pdf) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$nome, $orgao, $banca, $data_prova, $abrangencia, $numero_vagas, $status, $imagem_path, $pdf_path]);
            flashMsg('success', 'Concurso adicionado com sucesso!');
        } catch (Exception $e) {
            flashMsg('danger', 'Erro ao adicionar: ' . $e->getMessage());
        }
        redirect('biblioteca.php');
    }

    if ($action === 'delete') {
        $id = (int)$_POST['id'];
        $db->prepare("DELETE FROM lib_editais WHERE id = ?")->execute([$id]);
        flashMsg('success', 'Concurso removido.');
        redirect('biblioteca.php');
    }
}

// Listar concursos
$editais = $db->query("SELECT * FROM lib_editais ORDER BY criado_em DESC")->fetchAll();

$page_title = 'Biblioteca IA - HackConcursos';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="dashboard-layout">
  <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>
  <main class="main-content">

    <div class="page-header" style="display:flex; justify-content:space-between; align-items:center;">
      <div>
        <h2>📚 Biblioteca IA</h2>
        <p>Gerencie os concursos curados e processados pela inteligência estratégica.</p>
      </div>
      <button class="btn-hc btn-neon" onclick="document.getElementById('modal-add').style.display='flex'">
        <i class="bi bi-plus-lg"></i> Novo Concurso
      </button>
    </div>

    <div class="card-glass">
        <div class="card-body" style="padding:0;">
            <table class="table-hc">
                <thead>
                    <tr>
                        <th>Concurso / Órgão</th>
                        <th>Banca / Ano</th>
                        <th>Categoria</th>
                        <th>Status</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($editais)): ?>
                        <tr>
                            <td colspan="5" style="text-align:center; padding:3rem; color:var(--text-muted);">
                                <i class="bi bi-archive" style="font-size:2rem; display:block; margin-bottom:1rem;"></i>
                                Nenhuma concurso na biblioteca ainda.
                            </td>
                        </tr>
                    <?php endif; ?>
                    <?php foreach($editais as $e): ?>
                    <tr>
                        <td>
                            <div class="d-flex ai-center gap-sm">
                                <?php if($e['imagem']): ?>
                                    <img src="<?= APP_URL ?>/<?= $e['imagem'] ?>" style="width:60px; height:24px; object-fit:cover; border-radius:4px; border:1px solid var(--border-glass);">
                                <?php endif; ?>
                                <div>
                                    <div class="fw-700"><?= sanitize($e['nome_concurso']) ?></div>
                                    <div style="font-size:0.75rem; color:var(--text-muted);"><?= sanitize($e['orgao']) ?></div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <div><?= sanitize($e['banca']) ?></div>
                            <div style="font-size:0.75rem; color:var(--text-muted);"><?= $e['data_prova'] ? date('d/m/Y', strtotime($e['data_prova'])) : 'A definir' ?></div>
                        </td>
                        <td><span class="badge-hc badge-blue"><?= $e['abrangencia'] ?: 'Nacional' ?></span></td>
                        <td>
                            <?php 
                            $status_class = ['aberto'=>'badge-neon', 'previsto'=>'badge-purple', 'encerrado'=>'badge-red'];
                            $st = $e['status'];
                            ?>
                            <span class="badge-hc <?= $status_class[$st] ?? 'badge-blue' ?>"><?= strtoupper($st) ?></span>
                        </td>
                        <td>
                            <div style="display:flex; gap:0.5rem;">
                                <button class="btn-hc btn-ghost btn-sm btn-ia-analyze" 
                                        data-id="<?= $e['id'] ?>" 
                                        title="Analisar com IA">
                                    <i class="bi bi-robot"></i>
                                </button>
                                <button class="btn-hc btn-ghost btn-sm btn-view-disciplinas" 
                                        data-id="<?= $e['id'] ?>" 
                                        data-nome="<?= sanitize($e['nome_concurso']) ?>"
                                        title="Ver Disciplinas">
                                    <i class="bi bi-list-ul"></i>
                                </button>
                                <form method="POST" onsubmit="return confirm('Excluir este concurso?')">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= $e['id'] ?>">
                                    <button type="submit" class="btn-hc btn-ghost btn-sm text-danger">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

  </main>
</div>

<!-- Modal Adicionar -->
<div id="modal-add" class="modal-overlay" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.8); z-index:9999; align-items:center; justify-content:center;">
    <div class="card-glass" style="width:100%; max-width:600px;">
        <div class="card-header-hc" style="display:flex; justify-content:space-between;">
            <h5>Adicionar Concurso de Elite</h5>
            <button class="btn-hc btn-ghost btn-sm" onclick="document.getElementById('modal-add').style.display='none'">&times;</button>
        </div>
        <div class="card-body">
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="create">
                
                <div class="form-group mb-md">
                    <label>Nome do Concurso</label>
                    <input type="text" name="nome_concurso" class="form-control-hc" placeholder="Ex: Polícia Federal - Agente" required>
                </div>

                <div class="grid-2 mb-md">
                    <div class="form-group">
                        <label>Órgão</label>
                        <input type="text" name="orgao" class="form-control-hc" placeholder="Ex: PF" required>
                    </div>
                    <div class="form-group">
                        <label>Banca</label>
                        <input type="text" name="banca" class="form-control-hc" placeholder="Ex: Cebraspe" required>
                    </div>
                </div>

                <div class="grid-2 mb-md">
                    <div class="form-group">
                        <label>Data da Prova</label>
                        <input type="date" name="data_prova" class="form-control-hc">
                    </div>
                    <div class="form-group">
                        <label>Abrangência</label>
                        <input type="text" name="abrangencia" class="form-control-hc" placeholder="Ex: Nacional ou SP, RJ, MG">
                    </div>
                </div>

                <div class="grid-2 mb-md">
                    <div class="form-group">
                        <label>Nº de Vagas</label>
                        <input type="text" name="numero_vagas" class="form-control-hc" placeholder="Ex: 1.500 + CR">
                    </div>
                    <div class="form-group">
                        <label>Status</label>
                        <select name="status" class="form-control-hc">
                            <option value="aberto">Aberto</option>
                            <option value="previsto">Previsto</option>
                            <option value="encerrado">Encerrado</option>
                        </select>
                    </div>
                </div>

                <div class="form-group mb-md">
                    <label>Imagem do Card (800x200px)</label>
                    <input type="file" name="imagem" class="form-control-hc" accept="image/*">
                </div>

                <div class="form-group mb-lg">
                    <label>Edital (PDF)</label>
                    <input type="file" name="edital_pdf" class="form-control-hc" accept=".pdf">
                </div>

                <button type="submit" class="btn-hc btn-neon w-100">Salvar Concurso</button>
            </form>
        </div>
    </div>
</div>

<!-- Modal Ver Disciplinas -->
<div id="modal-view" class="modal-overlay" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.8); z-index:9999; align-items:center; justify-content:center;">
    <div class="card-glass" style="width:100%; max-width:600px;">
        <div class="card-header-hc" style="display:flex; justify-content:space-between;">
            <h5 id="view-title">Disciplinas do Concurso</h5>
            <button class="btn-hc btn-ghost btn-sm" onclick="document.getElementById('modal-view').style.display='none'">&times;</button>
        </div>
        <div class="card-body" id="view-body">
            <!-- Carregado via AJAX -->
            <div class="text-center py-lg"><div class="loader-spinner"></div></div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

<script>
document.querySelectorAll('.btn-ia-analyze').forEach(btn => {
    btn.addEventListener('click', function() {
        const id = this.getAttribute('data-id');
        const icon = this.querySelector('i');
        
        if (confirm('Deseja processar este edital com IA agora?')) {
            this.disabled = true;
            icon.className = 'bi bi-arrow-repeat spin';
            
            fetch('../controllers/biblioteca_ia.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'id=' + id
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    alert('Análise concluída!');
                    location.reload();
                } else {
                    alert('Erro: ' + data.error);
                }
            })
            .catch(err => {
                console.error(err);
                alert('Erro de conexão.');
            })
            .finally(() => {
                this.disabled = false;
                icon.className = 'bi bi-robot';
            });
        }
    });
});

document.querySelectorAll('.btn-view-disciplinas').forEach(btn => {
    btn.addEventListener('click', function() {
        const id = this.getAttribute('data-id');
        const nome = this.getAttribute('data-nome');
        document.getElementById('view-title').textContent = 'Disciplinas: ' + nome;
        document.getElementById('modal-view').style.display = 'flex';
        document.getElementById('view-body').innerHTML = '<div class="text-center py-lg"><div class="loader-spinner"></div></div>';
        
        fetch('../controllers/biblioteca_ia.php?get_disciplinas=1&id=' + id)
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                let html = '<table class="table-hc"><thead><tr><th>Matéria</th><th>Peso</th><th>Importância IA</th></tr></thead><tbody>';
                data.disciplinas.forEach(d => {
                    html += `<tr><td>${d.nome}</td><td>${d.peso_padrao}</td><td><span class="badge-hc badge-purple">${d.importancia_ia}/10</span></td></tr>`;
                });
                html += '</tbody></table>';
                document.getElementById('view-body').innerHTML = html;
            } else {
                document.getElementById('view-body').innerHTML = '<p class="text-danger">Erro ao carregar: ' + data.error + '</p>';
            }
        });
    });
});
</script>

<style>
.spin { animation: fa-spin 2s infinite linear; }
@keyframes fa-spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
</style>
