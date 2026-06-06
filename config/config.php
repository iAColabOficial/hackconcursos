<?php
// ============================================
// HackConcursos - Configurações Globais (Produção Hostinger)
// ============================================

// Carregador robusto de .env (Máxima compatibilidade)
$envPath = __DIR__ . '/../.env';
if (file_exists($envPath)) {
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if (!$line || $line[0] === '#' || strpos($line, '=') === false) continue;
        list($name, $value) = explode('=', $line, 2);
        $_ENV[trim($name)] = trim($value);
        $_SERVER[trim($name)] = trim($value);
    }
}

// Helper para ler variáveis do .env ou $_SERVER
function env(string $key, $default = null) {
    return $_ENV[$key] ?? $_SERVER[$key] ?? $default;
}

// Configurações Base
define('APP_NAME',    'HackConcursos');
define('APP_VERSION', '1.0.0');
define('APP_URL',     env('APP_URL', 'http://localhost/Hackconcurso'));

// Banco de Dados (Puxando do .env — sem fallback para evitar credenciais padrão em produção)
define('DB_HOST',    env('DB_HOST', 'localhost'));
define('DB_NAME',    env('DB_NAME', 'estudoconcursos'));
define('DB_USER',    env('DB_USER', 'root'));      // Manter fallback só em dev local
define('DB_PASS',    env('DB_PASS', ''));           // FIX C6: sem fallback de senha em produção
define('DB_CHARSET', 'utf8mb4');

// Google Gemini API (Configuração Atualizada 2.5 Flash)
define('GEMINI_API_KEY', env('GEMINI_API_KEY', 'SUA_CHAVE_AQUI'));
define('GEMINI_MODEL',   'gemini-flash-latest');
define('GEMINI_BASE_URL','https://generativelanguage.googleapis.com/v1beta/models/');

// Configurações do Stripe
define('STRIPE_PUBLIC_KEY',    env('STRIPE_PUBLIC_KEY'));
define('STRIPE_SECRET_KEY',    env('STRIPE_SECRET_KEY'));
define('STRIPE_WEBHOOK_SECRET', env('STRIPE_WEBHOOK_SECRET', '')); // FIX C1
define('PRICE_MODO_TURBO',     env('STRIPE_PRICE_MODO_TURBO',  'price_turbo_id'));
define('PRICE_MASTERMIND',     env('STRIPE_PRICE_MASTERMIND', 'price_mastermind_id'));
// FIX A12: Aliases necessários para stripe_checkout.php
define('PRICE_MODO_GUERRA',    env('STRIPE_PRICE_MODO_TURBO',  'price_turbo_id'));
define('PRICE_50_TOKENS',      env('STRIPE_PRICE_50_TOKENS',   'price_tokens_id'));

// --- DEFINIÇÕES DE PLANOS E ENERGIA (GAMESYSTEM) ---
define('PLANO_ACESSO',    'free');      // Modo Discovery
define('PLANO_TURBO',     'premium');   // Modo Turbo (Mensal)
define('PLANO_MASTERMIND', 'anual');     // Modo Mastermind (Anual)

// Custos de Energia (Tokens)
define('COST_IA_TIPS',      1);  // Consultor IA (Dicas)
define('COST_IA_SCANNER',   5);  // Scanner de Padrões (Falhas)
define('COST_IA_REROUTE',  20);  // Ajuste de Rota Inteligente
define('COST_IA_SIMULADO', 30);  // Gerar Simulado IA

// Limites de Alvos (Editais)
define('LIMIT_TARGETS_ACESSO',  1);
define('LIMIT_TARGETS_TURBO',   3);
define('LIMIT_TARGETS_MASTERMIND', 999);

// Cargas de Energia Iniciais/Mensais
define('RECHARGE_ACESSO',     5);   // Carga única não renovável
define('RECHARGE_TURBO',    250);   // Renovação mensal
define('RECHARGE_MASTERMIND', 600); // Renovação mensal

// Direas das pastas (Ajustadas para Hospedagem)
define('BASE_PATH',      dirname(__DIR__));
define('UPLOAD_DIR',     BASE_PATH . '/uploads/');
define('UPLOAD_EDITAIS', UPLOAD_DIR . 'editais/');
define('UPLOAD_MAX_MB',  20);

// Segurança
define('BCRYPT_COST',    12);
define('SESSION_NAME',   'hc_session');
define('SESSION_LIFE',   86400 * 7);

// FIX C5: Controle de exibição de erros baseado no ambiente
$appEnv = env('APP_ENV', 'development');
if ($appEnv === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
    ini_set('log_errors', 1);
}
date_default_timezone_set('America/Sao_Paulo');

// === CONEXÃO PDO ===
function getDB(): PDO {
    static $pdo = null;
    
    // Se já temos a conexão, vamos pingar para ver se ela ainda está viva
    if ($pdo !== null) {
        try {
            $pdo->query("SELECT 1");
        } catch (PDOException $e) {
            $pdo = null; // A conexão morreu, forçamos o reset
        }
    }

    if ($pdo === null) {
        try {
            $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', DB_HOST, DB_NAME, DB_CHARSET);
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            // FIX A11: Não expor detalhes técnicos ao usuário em produção
            error_log('Falha na conexão com o banco de dados: ' . $e->getMessage());
            if (env('APP_ENV', 'development') === 'development') {
                echo '<h1>Erro de Conexão (DEV)</h1><pre>' . htmlspecialchars($e->getMessage()) . '</pre>';
            } else {
                echo '<h1>Serviço temporàriamente indisponível</h1><p>Tente novamente em instantes. Se o problema persistir, contate o suporte.</p>';
            }
            die();
        }
    }
    return $pdo;
}

// === SESSÃO SEGURA ===
function iniciarSessao(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_name(SESSION_NAME);
        session_set_cookie_params([
            'lifetime' => SESSION_LIFE,
            'path'     => '/',
            'secure'   => env('APP_ENV', 'production') === 'production', // true em HTTPS produção (M14)
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
        session_start();
    }
}

// === HELPERS ===
function usuarioLogado(): bool {
    iniciarSessao();
    return isset($_SESSION['usuario_id']);
}

function exigirLogin(string $redirecionar = '../login.php'): void {
    iniciarSessao();
    if (!isset($_SESSION['usuario_id'])) {
        header("Location: $redirecionar");
        exit;
    }
    
    // Atualizar dados da sessão em tempo real (Plano e Tokens)
    $db = getDB();
    $stmt = $db->prepare("SELECT plano, token_saldo FROM usuarios WHERE id = ?");
    $stmt->execute([$_SESSION['usuario_id']]);
    $u = $stmt->fetch();
    if ($u) {
        $_SESSION['plano'] = $u['plano'];
        $_SESSION['token_saldo'] = $u['token_saldo'];
    }
}

function exigirAdmin(string $redirecionar = '../login.php'): void {
    iniciarSessao();
    if (!isset($_SESSION['usuario_id']) || ($_SESSION['perfil'] ?? '') !== 'admin') {
        header("Location: $redirecionar");
        exit;
    }
}

function sanitize(?string $input): string {
    return htmlspecialchars(trim($input ?? ''), ENT_QUOTES, 'UTF-8');
}

function formatarData(string $data): string {
    return date('d/m/Y', strtotime($data));
}

function formatarMoeda(float $valor): string {
    return 'R$ ' . number_format($valor, 2, ',', '.');
}

function gerarToken(int $length = 32): string {
    return bin2hex(random_bytes($length));
}

function getCSRFToken(): string {
    iniciarSessao();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verificarCSRFToken(?string $token): bool {
    iniciarSessao();
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

function redirect(string $url): void {
    header("Location: $url");
    exit;
}

/**
 * Conta o total de alvos ativos e sugestões pendentes do usuário
 */
function getContagemAlvos(int $usuario_id): int {
    $db = getDB();
    
    // 1. Contar editais ativos
    $st1 = $db->prepare("SELECT COUNT(*) FROM editais WHERE usuario_id = ? AND ativo = 1");
    $st1->execute([$usuario_id]);
    $ativos = (int)$st1->fetchColumn();
    
    // 2. Contar sugestões na fila de análise
    $st2 = $db->prepare("SELECT COUNT(*) FROM biblioteca_editais WHERE usuario_id = ? AND situacao_adm = 'pendente'");
    $st2->execute([$usuario_id]);
    $pendentes = (int)$st2->fetchColumn();
    
    return $ativos + $pendentes;
}

function flashMsg(string $tipo, string $msg): void {
    iniciarSessao();
    $_SESSION['flash'] = ['tipo' => $tipo, 'msg' => $msg];
}

function getFlash(): ?array {
    iniciarSessao();
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}
