<?php
require_once __DIR__ . '/../config/config.php';
exigirLogin('../login.php');

$db = getDB();
$uid = $_SESSION['usuario_id'];
$erro = '';
$sucesso = false;

// Sistema de Trava de Planos (Ativos + Pendentes)
$limite = ($_SESSION['plano'] === 'premium') ? LIMIT_TARGETS_TURBO : (($_SESSION['plano'] === 'anual') ? LIMIT_TARGETS_MASTERMIND : LIMIT_TARGETS_ACESSO);
$atual  = getContagemAlvos($uid);
$bloqueado = ($atual >= $limite);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$bloqueado) {
    $nome_concurso = sanitize($_POST['nome_concurso']);
    $orgao         = sanitize($_POST['orgao']);
    $banca         = sanitize($_POST['banca']);
    $data_prova    = $_POST['data_prova'] ?: null;
    $link_edital   = sanitize($_POST['link_edital']);

    if (empty($nome_concurso) || empty($orgao)) {
        $erro = "Preencha ao menos o nome do concurso e o órgão.";
    } else {
        try {
            $ins = $db->prepare("
                INSERT INTO biblioteca_editais (nome_concurso, orgao, banca, data_prova, edital_pdf, situacao_adm, usuario_id) 
                VALUES (?, ?, ?, ?, ?, 'pendente', ?)
            ");
            $ins->execute([$nome_concurso, $orgao, $banca, $data_prova, $link_edital, $uid]);
            $sucesso = true;
        } catch (PDOException $e) {
            $erro = "Erro ao enviar sugestão: " . $e->getMessage();
        }
    }
}

$page_title = 'Sugerir Novo Edital - HackConcursos';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="dashboard-layout">
    <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>
    <main class="main-content">
        <div class="page-header">
            <div class="page-breadcrumb">
                <a href="biblioteca.php">Biblioteca</a>
                <span class="sep">/</span>
                <span>Sugerir Edital</span>
            </div>
            <h2><i class="bi bi-cloud-arrow-up text-neon"></i> Colaborar com a Base</h2>
            <p>Não encontrou seu concurso? Envie os dados e nossa equipe irá catalogá-lo para você.</p>
            <div style="font-size:0.7rem; color:var(--text-muted); opacity:0.5;">
                DEBUG: Plano: <?= $_SESSION['plano'] ?> | Alvos: <?= $atual ?>/<?= $limite ?> | Bloqueado: <?= $bloqueado ? 'SIM' : 'NÃO' ?>
            </div>
        </div>

        <div class="row">
            <div class="col-md-7">
                <?php if ($sucesso): ?>
                    <div class="card-glass p-5 text-center border-neon">
                        <div style="font-size: 3rem; margin-bottom: 1rem;">🚀</div>
                        <h3 class="text-neon">Sugestão Enviada!</h3>
                        <p class="text-secondary mb-lg">
                            Obrigado por colaborar! Nossa equipe irá revisar os dados e autorizar a extração por IA.<br>
                            Você poderá acompanhar o status em <strong>Meus Concursos</strong>.
                        </p>
                        <a href="meus_concursos.php" class="btn-hc btn-primary-hc">Ver Meus Concursos</a>
                    </div>
                <?php elseif ($bloqueado): ?>
                    <div class="card-glass p-5 text-center border-danger shadow-danger">
                        <div style="font-size: 3rem; margin-bottom: 1rem;">🔒</div>
                        <h3 class="text-danger">Limite Atingido!</h3>
                        <p class="text-secondary mb-lg">
                            Seu plano atual permite apenas <strong><?= $limite ?> alvo</strong> ativo (incluindo sugestões em análise).<br>
                            Para sugerir este concurso, você precisa remover seu alvo atual ou fazer upgrade.
                        </p>
                        <div class="d-flex gap-md jc-center">
                            <a href="meus_concursos.php" class="btn-hc btn-ghost">Gerenciar Meus Concursos</a>
                            <a href="meu_plano.php" class="btn-hc btn-ai">Upgrade Modo Guerra</a>
                        </div>
                    </div>
                <?php else: ?>
                    <form method="POST" class="card-glass p-lg">
                        <?php if ($erro): ?>
                            <div class="alert-hc alert-danger mb-md"><?= $erro ?></div>
                        <?php endif; ?>

                        <div class="row">
                            <div class="col-md-12 mb-md">
                                <label class="form-label">Nome do Concurso (Ex: Auditor Fiscal SEFAZ-SP)</label>
                                <input type="text" name="nome_concurso" class="form-control-hc" placeholder="Digite o nome completo..." required>
                            </div>
                            <div class="col-md-6 mb-md">
                                <label class="form-label">Órgão</label>
                                <input type="text" name="orgao" class="form-control-hc" placeholder="Ex: SEFAZ, PF, TJ-RJ..." required>
                            </div>
                            <div class="col-md-6 mb-md">
                                <label class="form-label">Banca Examinadora</label>
                                <input type="text" name="banca" class="form-control-hc" placeholder="Ex: FGV, FCC, Cebraspe...">
                            </div>
                            <div class="col-md-6 mb-md">
                                <label class="form-label">Data da Prova (Se houver)</label>
                                <input type="date" name="data_prova" class="form-control-hc">
                            </div>
                            <div class="col-md-6 mb-md">
                                <label class="form-label">Link do Edital ou PDF</label>
                                <input type="text" name="link_edital" class="form-control-hc" placeholder="Cole o link oficial ou PDF...">
                            </div>
                        </div>

                        <div class="mt-lg">
                            <button type="submit" class="btn-hc btn-primary-hc w-100 py-3 shadow-neon">
                                ENVIAR PARA ANÁLISE <i class="bi bi-send-fill ms-2"></i>
                            </button>
                        </div>
                    </form>
                <?php endif; ?>
            </div>

            <div class="col-md-5">
                <div class="card-glass p-lg" style="background: rgba(34,197,94,0.02);">
                    <h5 class="fw-800 mb-md">Como funciona o processo?</h5>
                    <ul class="list-unstyled text-secondary" style="font-size: 0.9rem; line-height: 1.8;">
                        <li class="mb-sm"><i class="bi bi-1-circle text-neon me-2"></i> Você envia os dados básicos do concurso.</li>
                        <li class="mb-sm"><i class="bi bi-2-circle text-neon me-2"></i> Nossa equipe valida se o edital é real e atualizado.</li>
                        <li class="mb-sm"><i class="bi bi-3-circle text-neon me-2"></i> Ativamos a extração tática da IA (Nódulos de Conhecimento).</li>
                        <li class="mb-sm"><i class="bi bi-4-circle text-neon me-2"></i> O edital fica disponível para você iniciar seus estudos.</li>
                    </ul>
                    <div class="mt-md p-3 border-glass rounded-md" style="background: rgba(0,0,0,0.2);">
                        <small class="text-muted"><i class="bi bi-info-circle"></i> Tempo médio de aprovação: <strong>12h a 24h úteis</strong>.</small>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
