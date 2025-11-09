<?php
/**
 * Script de Diagnóstico do Sistema
 * Verifica configuração de sessões, cookies e autenticação
 * 
 * ⚠️ ATENÇÃO: Este endpoint é apenas para debug/teste
 * Em produção, requer autenticação admin
 */

require_once __DIR__ . '/protect_debug_endpoints.php';

// Não usar require db.php para evitar interferir com sessão
header('Content-Type: application/json; charset=utf-8');

$diagnostics = [];

// 1. Verificar configuração PHP
$diagnostics['php_version'] = PHP_VERSION;
$diagnostics['session_status'] = session_status();
$diagnostics['session_status_text'] = [
    PHP_SESSION_DISABLED => 'DISABLED',
    PHP_SESSION_NONE => 'NONE',
    PHP_SESSION_ACTIVE => 'ACTIVE'
][session_status()];

// 2. Verificar diretório de sessões
$sessionPath = session_save_path();
$diagnostics['session_save_path'] = $sessionPath;
$diagnostics['session_path_exists'] = !empty($sessionPath) && is_dir($sessionPath);
$diagnostics['session_path_writable'] = !empty($sessionPath) && is_writable($sessionPath);

// 3. Verificar configuração de cookies
$diagnostics['cookie_params'] = session_get_cookie_params();

// 4. Verificar HTTPS
$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || 
           (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ||
           (!empty($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] === 'on');
$diagnostics['is_https'] = $isHttps;
$diagnostics['server_https'] = $_SERVER['HTTPS'] ?? 'not set';
$diagnostics['http_x_forwarded_proto'] = $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? 'not set';
$diagnostics['http_x_forwarded_ssl'] = $_SERVER['HTTP_X_FORWARDED_SSL'] ?? 'not set';

// 5. Verificar headers recebidos
$diagnostics['all_headers'] = getallheaders();

// 6. Verificar cookies recebidos
$diagnostics['cookies_received'] = $_COOKIE;

// 7. Iniciar sessão de teste
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax');
    ini_set('session.use_strict_mode', '1');
    ini_set('session.cookie_path', '/');
    ini_set('session.cookie_domain', '');
    ini_set('session.cookie_secure', $isHttps ? '1' : '0');
    
    session_start();
}

$diagnostics['session_id'] = session_id();
$diagnostics['session_name'] = session_name();
$diagnostics['session_data'] = $_SESSION;

// 8. Verificar conexão com banco de dados
try {
    require_once __DIR__ . '/config.php';
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
    $diagnostics['database_connection'] = 'OK';
    
    // Verificar se tabela users existe
    $stmt = $pdo->query("SHOW TABLES LIKE 'users'");
    $diagnostics['users_table_exists'] = $stmt->rowCount() > 0;
    
    if ($diagnostics['users_table_exists']) {
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM users");
        $diagnostics['users_count'] = $stmt->fetch()['count'];
    }
} catch (Exception $e) {
    $diagnostics['database_connection'] = 'ERROR: ' . $e->getMessage();
}

// 9. Verificar permissões de arquivo
$diagnostics['api_directory_writable'] = is_writable(__DIR__);

echo json_encode($diagnostics, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

