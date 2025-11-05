<?php
/**
 * InventoX - Test Session API
 * Endpoint temporário para testar configuração de sessão
 */

require_once __DIR__ . '/db.php';

// Configurar cookies ANTES de session_start()
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Lax');
ini_set('session.use_strict_mode', '1');
ini_set('session.cookie_path', '/');
ini_set('session.cookie_domain', '');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$cookieName = session_name();
$sessionId = session_id();

$response = [
    'success' => true,
    'debug' => [
        'session_status' => session_status(),
        'session_status_text' => session_status() === PHP_SESSION_ACTIVE ? 'ACTIVE' : 'INACTIVE',
        'session_id' => $sessionId,
        'session_name' => $cookieName,
        'cookie_received' => isset($_COOKIE[$cookieName]),
        'cookie_value' => $_COOKIE[$cookieName] ?? 'NOT SET',
        'all_cookies' => $_COOKIE,
        'session_data' => $_SESSION,
        'headers_sent' => headers_sent(),
        'session_save_path' => session_save_path(),
        'php_version' => PHP_VERSION
    ]
];

// Se tiver dados na sessão, mostrar
if (isset($_SESSION['user_id'])) {
    $response['authenticated'] = true;
    $response['user'] = [
        'id' => $_SESSION['user_id'],
        'username' => $_SESSION['username'] ?? null,
        'role' => $_SESSION['role'] ?? null
    ];
} else {
    $response['authenticated'] = false;
}

sendJsonResponse($response);

