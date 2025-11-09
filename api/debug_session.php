<?php
/**
 * InventoX - Debug Session API
 * Endpoint para diagnosticar problemas de sessão
 * 
 * ⚠️ ATENÇÃO: Este endpoint é apenas para debug/teste
 * Em produção, requer autenticação admin
 */

require_once __DIR__ . '/protect_debug_endpoints.php';

// Headers para debug
header('Content-Type: application/json; charset=utf-8');

// Detectar HTTPS
$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || 
           (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ||
           (!empty($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] === 'on');

// Configurar cookies ANTES de session_start()
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Lax');
ini_set('session.use_strict_mode', '1');
ini_set('session.cookie_path', '/');
ini_set('session.cookie_domain', '');
ini_set('session.cookie_secure', $isHttps ? '1' : '0');

// Iniciar sessão se ainda não iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$cookieName = session_name();
$sessionId = session_id();

$response = [
    'success' => true,
    'server_info' => [
        'php_version' => PHP_VERSION,
        'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'unknown',
        'document_root' => $_SERVER['DOCUMENT_ROOT'] ?? 'unknown',
        'script_filename' => __FILE__,
    ],
    'request_info' => [
        'method' => $_SERVER['REQUEST_METHOD'] ?? 'unknown',
        'uri' => $_SERVER['REQUEST_URI'] ?? 'unknown',
        'host' => $_SERVER['HTTP_HOST'] ?? 'unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
        'remote_addr' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
    ],
    'https_detection' => [
        'is_https' => $isHttps,
        'server_https' => $_SERVER['HTTPS'] ?? 'not set',
        'x_forwarded_proto' => $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? 'not set',
        'x_forwarded_ssl' => $_SERVER['HTTP_X_FORWARDED_SSL'] ?? 'not set',
    ],
    'session_config' => [
        'session_status' => session_status(),
        'session_status_text' => session_status() === PHP_SESSION_ACTIVE ? 'ACTIVE' : 'INACTIVE',
        'session_id' => $sessionId,
        'session_name' => $cookieName,
        'session_save_path' => session_save_path(),
        'cookie_params' => session_get_cookie_params(),
    ],
    'cookies' => [
        'has_session_cookie' => isset($_COOKIE[$cookieName]),
        'cookie_value' => $_COOKIE[$cookieName] ?? 'NOT SET',
        'all_cookies' => $_COOKIE,
    ],
    'session_data' => $_SESSION ?? [],
    'authenticated' => isset($_SESSION['user_id']),
    'user_info' => isset($_SESSION['user_id']) ? [
        'id' => $_SESSION['user_id'],
        'username' => $_SESSION['username'] ?? null,
        'role' => $_SESSION['role'] ?? null
    ] : null,
];

echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

