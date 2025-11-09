<?php
/**
 * Test Authentication
 * Endpoint para testar se autenticação está funcionando
 * 
 * ⚠️ ATENÇÃO: Este endpoint é apenas para debug/teste
 * Em produção, requer autenticação admin
 */

require_once __DIR__ . '/protect_debug_endpoints.php';
require_once __DIR__ . '/db.php';

header('Content-Type: application/json; charset=utf-8');

// Detectar HTTPS
$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || 
           (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ||
           (!empty($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] === 'on');

// Configurar cookies menos restritivos para teste
ini_set('session.cookie_httponly', '0'); // Permitir acesso via JavaScript para teste
ini_set('session.use_strict_mode', '1');
ini_set('session.cookie_path', '/');
ini_set('session.cookie_domain', '');
ini_set('session.cookie_secure', $isHttps ? '1' : '0');

// SameSite só está disponível no PHP 7.3+
if (PHP_VERSION_ID >= 70300) {
    try {
        ini_set('session.cookie_samesite', 'None');
    } catch (Exception $e) {
        // Ignorar erro se não suportar
    }
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$response = [
    'php_version' => PHP_VERSION,
    'php_version_id' => PHP_VERSION_ID,
    'samesite_support' => PHP_VERSION_ID >= 70300,
    'session_info' => [
        'session_id' => session_id(),
        'session_name' => session_name(),
        'session_status' => session_status() === PHP_SESSION_ACTIVE ? 'ACTIVE' : 'INACTIVE',
        'cookie_params' => session_get_cookie_params()
    ],
    'cookies_received' => $_COOKIE,
    'has_session_cookie' => isset($_COOKIE[session_name()]),
    'session_data' => $_SESSION,
    'is_authenticated' => isset($_SESSION['user_id']),
    'https_detected' => $isHttps,
    'server_info' => [
        'HTTP_X_FORWARDED_PROTO' => $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? 'not set',
        'HTTP_X_FORWARDED_SSL' => $_SERVER['HTTP_X_FORWARDED_SSL'] ?? 'not set',
        'HTTPS' => $_SERVER['HTTPS'] ?? 'not set',
        'REQUEST_URI' => $_SERVER['REQUEST_URI'] ?? 'not set'
    ]
];

if (isset($_SESSION['user_id'])) {
    $response['user'] = [
        'id' => $_SESSION['user_id'],
        'username' => $_SESSION['username'],
        'role' => $_SESSION['role']
    ];
}

echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

