<?php
/**
 * InventoX - Logout API
 * Endpoint para logout de utilizadores
 */

require_once __DIR__ . '/db.php';

// Iniciar sessão com configurações de cookie
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax');
    ini_set('session.use_strict_mode', '1');
    session_start();
}

// Destruir sessão
if (isset($_SESSION)) {
    // Limpar variáveis de sessão
    $_SESSION = array();
    
    // Destruir cookie de sessão se existir
    if (isset($_COOKIE[session_name()])) {
        setcookie(session_name(), '', time() - 3600, '/');
    }
    
    // Destruir sessão
    session_destroy();
}

sendJsonResponse([
    'success' => true,
    'message' => 'Logout realizado com sucesso'
]);

