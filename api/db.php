<?php
/**
 * InventoX - Database Connection
 * Configuração de conexão com MySQL usando PDO
 */

// Suprimir warnings durante carregamento de env
error_reporting(E_ALL);
ini_set('display_errors', 0); // Não mostrar erros no output
ini_set('log_errors', 1);

require_once __DIR__ . '/load_env.php';

class Database {
    private static $instance = null;
    private $connection;

    private function __construct() {
        try {
            // Carregar variáveis de ambiente
            $host = getenv('DB_HOST') ?: ($_ENV['DB_HOST'] ?? 'mysql');
            $dbname = getenv('DB_NAME') ?: ($_ENV['DB_NAME'] ?? 'inventox');
            $username = getenv('DB_USER') ?: ($_ENV['DB_USER'] ?? 'inventox_user');
            $password = getenv('DB_PASS') ?: ($_ENV['DB_PASS'] ?? 'change_me');
            $port = getenv('DB_PORT') ?: ($_ENV['DB_PORT'] ?? '3306');

            $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";
            
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
            ];

            $this->connection = new PDO($dsn, $username, $password, $options);
        } catch (PDOException $e) {
            error_log("Database connection error: " . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Erro de conexão com a base de dados'
            ]);
            exit;
        }
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection() {
        return $this->connection;
    }

    // Prevenir clonagem
    private function __clone() {}
    
    // Prevenir unserialize
    public function __wakeup() {
        throw new Exception("Cannot unserialize singleton");
    }
}

/**
 * Função helper para obter a conexão
 */
function getDB() {
    return Database::getInstance()->getConnection();
}

/**
 * Função helper para enviar resposta JSON
 */
function sendJsonResponse($data, $statusCode = 200) {
    // Limpar qualquer output anterior
    if (ob_get_level() > 0) {
        ob_clean();
    }
    
    // CRÍTICO: Não usar header_remove() se houver sessão ativa
    // header_remove() remove TODOS os headers, incluindo Set-Cookie de sessão
    // O PHP envia o cookie Set-Cookie automaticamente quando há uma sessão ativa
    // Não podemos remover isso antes que o PHP o envie
    
    // Se não houver sessão ativa, podemos remover headers
    // Mas se houver sessão, não remover para preservar cookies de sessão
    $hasActiveSession = (session_status() === PHP_SESSION_ACTIVE);
    
    if (!$hasActiveSession) {
        // Sem sessão ativa, podemos limpar headers normalmente
        header_remove();
    }
    // Se houver sessão ativa, NÃO remover headers - o PHP enviará Set-Cookie automaticamente
    
    // Definir headers necessários (isso sobrescreve headers anteriores de mesmo tipo)
    header('Content-Type: application/json; charset=utf-8');
    http_response_code($statusCode);
    
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

/**
 * Função helper para sanitizar entrada
 */
function sanitizeInput($input) {
    if (is_array($input)) {
        return array_map('sanitizeInput', $input);
    }
    return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
}

/**
 * Função helper para validar autenticação
 */
function requireAuth() {
    // Verificar se a sessão já está ativa
    $sessionAlreadyActive = (session_status() === PHP_SESSION_ACTIVE);
    
    // Configurar cookies de sessão ANTES de session_start()
    // IMPORTANTE: ini_set só pode ser chamado ANTES de session_start()
    if (!$sessionAlreadyActive) {
        ini_set('session.cookie_httponly', '1');
        ini_set('session.cookie_samesite', 'Lax');
        ini_set('session.use_strict_mode', '1');
        ini_set('session.cookie_path', '/');
        ini_set('session.cookie_domain', '');
        
        // Configurar diretório de sessões (se não estiver configurado)
        $sessionPath = ini_get('session.save_path');
        if (empty($sessionPath)) {
            // Tentar usar diretório padrão ou criar um
            $defaultPath = '/var/lib/php/sessions';
            if (is_dir($defaultPath) || @mkdir($defaultPath, 1733, true)) {
                ini_set('session.save_path', $defaultPath);
            }
        }
        
        // Iniciar sessão
        session_start();
    }
    
    // Debug: verificar cookies recebidos
    $cookieName = session_name();
    $hasSessionCookie = isset($_COOKIE[$cookieName]);
    error_log("requireAuth - Session ID from cookie: " . ($hasSessionCookie ? $_COOKIE[$cookieName] : 'NOT SET') . ", Session ID active: " . session_id());
    
    // Verificar se a sessão está realmente ativa e tem dados
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['username'])) {
        // Log detalhado para debug
        error_log("Auth failed - Session status: " . session_status() . 
                  ", Session ID: " . session_id() . 
                  ", Cookie name: " . session_name() . 
                  ", Has cookie: " . ($hasSessionCookie ? 'YES' : 'NO') . 
                  ", User ID: " . ($_SESSION['user_id'] ?? 'not set') . 
                  ", Session data: " . print_r($_SESSION, true));
        sendJsonResponse([
            'success' => false,
            'message' => 'Autenticação necessária'
        ], 401);
    }
}

/**
 * Função helper para verificar permissões de admin
 */
function requireAdmin() {
    requireAuth();
    if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
        sendJsonResponse([
            'success' => false,
            'message' => 'Acesso restrito a administradores'
        ], 403);
    }
}

