<?php
require_once __DIR__ . '/../config/config.php';
exigirLogin('../public/login.php');

if (($_SESSION['perfil'] ?? '') !== 'admin') {
    flashMsg('danger', 'Acesso negado.');
    redirect('../aluno/dashboard.php');
}

$db = getDB();

$sql = "
    SELECT e.*, u.nome AS usuario_nome, u.email AS usuario_email
    FROM editais e
    JOIN usuarios u ON u.id = e.usuario_id
    ORDER BY e.criado_em DESC
";
$editais = $db->query($sql)->fetchAll();

$page_title = 'Gestão de Editais';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="dashboard-layout" style="position:relative;z-index:1;">
  <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>
  <main class="main-content">

    <div class="page-header">
      <div class="page-breadcrumb"><a href="index.php">Painel Admin</a><span class="sep">›</span> Editais</div>
      <h2>📄 Editais Enviados</h2>
      <p>Acompanhe todos os editais processados pela inteligência artificial da plataforma.</p>
    </div>

    <div class="card-glass">
        <div class="card-body" style="padding:0;">
            <table class="table-hc">
                <thead>
                    <tr>
                        <th>Concurso / Órgão</th>
                        <th>Usuário</th>
                        <th>Banca</th>
                        <th>Processamento</th>
                        <th>Data Envio</th>
                        <th class="text-right">Ação</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($editais)): ?>
                    <tr><td colspan="6" class="text-center" style="padding:3rem;">Nenhum edital enviado ainda.</td></tr>
                    <?php else: ?>
                        <?php foreach($editais as $e): 
                            $statusClass = [
                                'pendente' => 'badge-muted',
                                'processando' => 'badge-blue',
                                'concluido' => 'badge-neon',
                                'erro' => 'badge-danger'
                            ][$e['status_processamento']] ?? 'badge-muted';
                        ?>
                        <tr>
                            <td>
                                <div class="fw-700"><?= sanitize($e['nome_concurso']) ?></div>
                                <div style="font-size:0.75rem; color:var(--text-muted);"><?= sanitize($e['orgao']) ?></div>
                            </td>
                            <td>
                                <div style="font-size:0.85rem;"><?= sanitize($e['usuario_nome']) ?></div>
                                <div style="font-size:0.7rem; color:var(--text-muted);"><?= sanitize($e['usuario_email']) ?></div>
                            </td>
                            <td><?= sanitize($e['banca']) ?></td>
                            <td>
                                <span class="badge-hc <?= $statusClass ?>">
                                    <?= strtoupper($e['status_processamento']) ?>
                                </span>
                            </td>
                            <td><?= date('d/m/Y H:i', strtotime($e['criado_em'])) ?></td>
                            <td class="text-right">
                                <a href="<?= APP_URL ?>/uploads/<?= $e['arquivo_pdf'] ?>" target="_blank" class="btn-hc btn-ghost btn-sm" title="Download PDF">
                                    Baixar
                                </a>
                                <button class="btn-hc btn-ghost btn-sm text-danger" title="Excluir">Excluir</button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

  </main>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
