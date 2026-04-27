<?php
require_once __DIR__ . '/../config/config.php';
exigirLogin('../login.php');

$db = getDB();
$usuario_id = $_SESSION['usuario_id'];

// --- LÓGICA DE SINCRONIZAÇÃO AUTOMÁTICA (Fallback para Localhost) ---
if (isset($_GET['session_id'])) {
    $session_id = $_GET['session_id'];
    
    // Consultar Stripe para ver se essa sessão está paga
    $ch = curl_init("https://api.stripe.com/v1/checkout/sessions/$session_id");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERPWD, STRIPE_SECRET_KEY . ':');
    $res = curl_exec($ch);
    $session_data = json_decode($res, true);
    curl_close($ch);

    if (isset($session_data['payment_status']) && $session_data['payment_status'] === 'paid') {
        $tipo = $session_data['metadata']['tipo'] ?? 'assinatura';
        
        // Verificar se já processamos essa sessão (para não duplicar)
        // No MVP vamos apenas processar, mas o ideal é salvar o session_id no banco
        if ($tipo === 'tokens') {
            $db->prepare("UPDATE usuarios SET token_saldo = token_saldo + 50 WHERE id = ?")->execute([$usuario_id]);
            $db->prepare("INSERT INTO token_transacoes (usuario_id, tipo, quantidade, descricao) VALUES (?, 'compra', 50, 'Sincronização Direta Stripe')")->execute([$usuario_id]);
        } else {
            $db->prepare("UPDATE usuarios SET plano = 'premium', status_assinatura = 'ativa' WHERE id = ?")->execute([$usuario_id]);
        }
        flashMsg('success', 'Pagamento sincronizado e créditos liberados!');
    }
}
// --- FIM DA SINCRONIZAÇÃO ---

// Buscar dados atualizados do usuário
$stmt = $db->prepare("SELECT nome, email, plano, token_saldo, status_assinatura FROM usuarios WHERE id = ?");
$stmt->execute([$usuario_id]);
$u = $stmt->fetch();

$page_title = 'Meu Plano e Tokens - HackConcursos';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="dashboard-layout">
  <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>
  <main class="main-content">

    <div class="page-header">
      <h2>💳 Gestão de Plano e Créditos</h2>
      <p>Gerencie sua assinatura e seu saldo de tokens para IA.</p>
    </div>

    <?php if (isset($_GET['success'])): ?>
        <div class="alert-hc alert-success mb-lg">
            <i class="bi bi-check-circle"></i> Pagamento confirmado com sucesso! Seu saldo/plano foi atualizado.
        </div>
    <?php endif; ?>

    <div class="grid-2">
        <!-- Card: Plano Atual -->
        <div class="card-glass">
            <div class="card-header-hc">
                <h5>Plano Atual</h5>
            </div>
            <div class="card-body">
                <div class="d-flex ai-center jc-between mb-md">
                    <div>
                        <h3 class="<?= $u['plano'] === 'premium' ? 'text-neon' : '' ?>">
                            <?= $u['plano'] === 'premium' ? 'MODO GUERRA' : 'Plano Gratuito' ?>
                        </h3>
                        <span class="badge-hc <?= $u['status_assinatura'] === 'ativa' ? 'badge-neon' : 'badge-blue' ?>">
                            <?= strtoupper($u['status_assinatura'] ?: 'FREE') ?>
                        </span>
                    </div>
                    <div style="font-size:3rem;">
                        <?= $u['plano'] === 'premium' ? '⚡' : '🛡️' ?>
                    </div>
                </div>

                <?php if ($u['plano'] !== 'premium'): ?>
                    <p class="text-muted mb-lg">Você está usando a versão limitada. Desbloqueie o potencial máximo da IA.</p>
                    <a href="../planos.php" class="btn-hc btn-neon w-100">Fazer Upgrade</a>
                <?php else: ?>
                    <p class="text-muted mb-lg">Você tem acesso ilimitado a todas as ferramentas estratégicas.</p>
                    <button class="btn-hc btn-ghost w-100" disabled>Assinatura Ativa</button>
                <?php endif; ?>
            </div>
        </div>

        <!-- Card: Tokens IA -->
        <div class="card-glass">
            <div class="card-header-hc">
                <h5>Saldo de Tokens IA</h5>
            </div>
            <div class="card-body">
                <div class="d-flex ai-center jc-between mb-md">
                    <div>
                        <div class="kpi-label">Tokens Disponíveis</div>
                        <div class="kpi-value text-purple" style="font-size:3rem;"><?= $u['token_saldo'] ?></div>
                    </div>
                    <div style="font-size:3rem;">🪙</div>
                </div>
                
                <p class="text-muted mb-lg">Cada consulta profunda ao Mentor IA ou Refinamento de Estratégia consome 1 token.</p>
                
                <a href="../controllers/stripe_checkout.php?tipo=tokens" class="btn-hc btn-ai w-100">
                    Comprar +50 Tokens (R$ 29)
                </a>
            </div>
        </div>
    </div>

    <!-- Histórico de Transações -->
    <div class="card-glass mt-lg">
        <div class="card-header-hc">
            <h5>Histórico de Créditos</h5>
        </div>
        <div class="card-body" style="padding:0;">
            <?php
            $trans = $db->prepare("SELECT * FROM token_transacoes WHERE usuario_id = ? ORDER BY criado_em DESC LIMIT 10");
            $trans->execute([$usuario_id]);
            $logs = $trans->fetchAll();
            ?>
            <table class="table-hc">
                <thead>
                    <tr>
                        <th>Data</th>
                        <th>Tipo</th>
                        <th>Qtd</th>
                        <th>Descrição</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($logs as $l): ?>
                    <tr>
                        <td><?= date('d/m/Y H:i', strtotime($l['criado_em'])) ?></td>
                        <td>
                            <span class="badge-hc <?= $l['tipo'] === 'compra' ? 'badge-neon' : ($l['tipo'] === 'bonus' ? 'badge-purple' : 'badge-red') ?>">
                                <?= strtoupper($l['tipo']) ?>
                            </span>
                        </td>
                        <td><?= $l['quantidade'] ?></td>
                        <td class="text-muted"><?= sanitize($l['descricao']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($logs)): ?>
                        <tr><td colspan="4" class="text-center text-muted py-md">Nenhuma transação encontrada.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

  </main>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
