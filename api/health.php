<?php
// Health check endpoint for Railway
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

try {
    // Verificar se o PHP está funcionando
    $status = [
        'status' => 'healthy',
        'timestamp' => date('Y-m-d H:i:s'),
        'php_version' => PHP_VERSION,
        'services' => []
    ];
    
    // Verificar conexão com base de dados (se configurada)
    if (isset($_ENV['DATABASE_URL']) || (isset($_ENV['DB_HOST']) && isset($_ENV['DB_NAME']))) {
        try {
            require_once 'db.php';
            $db = getDBConnection();
            $stmt = $db->query("SELECT 1");
            $status['services']['database'] = 'connected';
        } catch (Exception $e) {
            $status['services']['database'] = 'disconnected';
            $status['database_error'] = $e->getMessage();
        }
    } else {
        $status['services']['database'] = 'not_configured';
    }
    
    // Verificar se as pastas necessárias existem
    $status['services']['uploads'] = is_dir('../uploads') && is_writable('../uploads') ? 'ready' : 'not_ready';
    
    http_response_code(200);
    echo json_encode($status, JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'unhealthy',
        'error' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_PRETTY_PRINT);
}
?>
