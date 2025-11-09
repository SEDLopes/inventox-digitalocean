<?php
/**
 * Test Token Authentication
 * Endpoint para testar se o token está sendo recebido e validado
 * 
 * ⚠️ ATENÇÃO: Este endpoint é apenas para debug/teste
 * Em produção, requer autenticação admin
 */

require_once __DIR__ . '/protect_debug_endpoints.php';
require_once __DIR__ . '/db.php';

header('Content-Type: application/json; charset=utf-8');

// Verificar token no header
$authToken = null;
$headers = getallheaders();
if (isset($headers['Authorization'])) {
    $authHeader = $headers['Authorization'];
    if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
        $authToken = $matches[1];
    }
}

$response = [
    'token_received' => !empty($authToken),
    'token_value' => $authToken ? substr($authToken, 0, 20) . '...' : null,
    'headers_received' => $headers,
    'session_status' => session_status() === PHP_SESSION_ACTIVE ? 'ACTIVE' : 'INACTIVE',
    'session_id' => session_id(),
    'has_session_cookie' => isset($_COOKIE[session_name()]),
    'session_data' => $_SESSION,
    'is_authenticated' => isset($_SESSION['user_id'])
];

// Tentar validar token se fornecido
if ($authToken) {
    $sessionPath = session_save_path();
    if (empty($sessionPath)) {
        $sessionPath = sys_get_temp_dir();
    }
    
    $tokenFound = false;
    $sessionFiles = glob($sessionPath . '/sess_*');
    if ($sessionFiles && is_array($sessionFiles)) {
        foreach ($sessionFiles as $sessionFile) {
            if (!is_file($sessionFile) || !is_readable($sessionFile)) {
                continue;
            }
            
            $sessionData = @file_get_contents($sessionFile);
            if ($sessionData !== false && strpos($sessionData, $authToken) !== false) {
                $tokenFound = true;
                $response['token_found_in_session'] = true;
                $response['session_file'] = basename($sessionFile);
                break;
            }
        }
    }
    
    if (!$tokenFound) {
        $response['token_found_in_session'] = false;
        $response['session_path'] = $sessionPath;
        $response['session_files_count'] = is_array($sessionFiles) ? count($sessionFiles) : 0;
    }
}

echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

