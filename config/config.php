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

// Banco de Dados (Puxando do .env)
define('DB_HOST',    env('DB_HOST', 'localhost'));
define('DB_NAME',    env('DB_NAME', 'estudoconcursos'));
define('DB_USER',    env('DB_USER', 'root'));
define('DB_PASS',    env('DB_PASS', '1234'));
define('DB_CHARSET', 'utf8mb4');

// Google Gemini API (Configuração Atualizada 2.5 Flash)
define('GEMINI_API_KEY', env('GEMINI_API_KEY', 'SUA_CHAVE_AQUI'));
define('GEMINI_MODEL',   'gemini-2.5-flash');
define('GEMINI_BASE_URL','https://generativelanguage.googleapis.com/v1/models/');

// Direas das pastas (Ajustadas para Hospedagem)
define('BASE_PATH',      dirname(__DIR__));
define('UPLOAD_DIR',     BASE_PATH . '/uploads/');
define('UPLOAD_EDITAIS', UPLOAD_DIR . 'editais/');
define('UPLOAD_MAX_MB',  20);

// Segurança
define('BCRYPT_COST',    12);
define('SESSION_NAME',   'hc_session');
define('SESSION_LIFE',   86400 * 7);

// DEBUG TOTAL (Para ver o erro real na tela)
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
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
            // Em vez de erro 500 genérico, vamos ver o que o MySQL diz:
            echo "<h1>Erro de Conexão com o Banco de Dados</h1>";
            echo "<p>Verifique o arquivo <b>.env</b> na raiz.</p>";
            echo "<pre>" . $e->getMessage() . "</pre>";
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
            'secure'   => false, // true em HTTPS produção
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
    if (!usuarioLogado()) {
        header("Location: $redirecionar");
        exit;
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

function redirect(string $url): void {
    header("Location: $url");
    exit;
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
