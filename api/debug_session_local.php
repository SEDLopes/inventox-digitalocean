<?php
/**
 * Debug Session - Local
 * Endpoint para debug de sessão em ambiente local
 */

header('Content-Type: application/json; charset=utf-8');

// Permitir CORS em desenvolvimento
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Credentials: true');

// Detectar HTTPS
$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || 
           (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ||
           (!empty($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] === 'on');

// Configurar cookies ANTES de session_start()
ini_set('session.cookie_httponly', '1');
ini_set('session.use_strict_mode', '1');
ini_set('session.cookie_path', '/');
ini_set('session.cookie_domain', '');
ini_set('session.cookie_secure', $isHttps ? '1' : '0');

if (PHP_VERSION_ID >= 70300) {
    try {
        ini_set('session.cookie_samesite', $isHttps ? 'None' : 'Lax');
    } catch (Exception $e) {
        // Ignorar
    }
}

// Configurar session.save_path
$possiblePaths = [
    '/var/lib/php/sessions',
    sys_get_temp_dir() . '/php_sessions',
    __DIR__ . '/../sessions',
    '/tmp/php_sessions'
];

$sessionPath = ini_get('session.save_path');
if (empty($sessionPath) || !is_dir($sessionPath) || !is_writable($sessionPath)) {
    foreach ($possiblePaths as $path) {
        if (is_dir($path) && is_writable($path)) {
            ini_set('session.save_path', $path);
            break;
        } elseif (@mkdir($path, 0755, true)) {
            ini_set('session.save_path', $path);
            break;
        }
    }
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$response = [
    'session_id' => session_id(),
    'session_name' => session_name(),
    'session_status' => session_status(),
    'session_save_path' => ini_get('session.save_path'),
    'session_cookie_path' => ini_get('session.cookie_path'),
    'session_cookie_domain' => ini_get('session.cookie_domain'),
    'session_cookie_httponly' => ini_get('session.cookie_httponly'),
    'session_cookie_secure' => ini_get('session.cookie_secure'),
    'session_cookie_samesite' => ini_get('session.cookie_samesite'),
    'cookies_received' => $_COOKIE,
    'session_data' => $_SESSION ?? [],
    'server' => [
        'HTTPS' => $_SERVER['HTTPS'] ?? 'off',
        'HTTP_X_FORWARDED_PROTO' => $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? null,
        'HTTP_X_FORWARDED_SSL' => $_SERVER['HTTP_X_FORWARDED_SSL'] ?? null,
        'REQUEST_URI' => $_SERVER['REQUEST_URI'] ?? null,
    ]
];

echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

