<?php
// Health check endpoint - Forçar processamento PHP
// Adicionar header antes de qualquer output
if (!headers_sent()) {
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *');
}

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
            $db = getDB();
            $stmt = $db->query("SELECT 1");
            $status['services']['database'] = 'connected';
            
            // Verificar se tabelas principais existem
            try {
                $stmt = $db->query("SELECT COUNT(*) as count FROM users LIMIT 1");
                $status['services']['database_tables'] = 'ok';
            } catch (Exception $e) {
                $status['services']['database_tables'] = 'error';
            }
        } catch (Exception $e) {
            $status['services']['database'] = 'disconnected';
            $status['database_error'] = $e->getMessage();
        }
    } else {
        $status['services']['database'] = 'not_configured';
    }
    
    // Verificar se as pastas necessárias existem
    $uploadsPath = __DIR__ . '/../uploads';
    $status['services']['uploads'] = is_dir($uploadsPath) && is_writable($uploadsPath) ? 'ready' : 'not_ready';
    
    // Verificar sessões
    $sessionPath = session_save_path();
    if (empty($sessionPath)) {
        $sessionPath = sys_get_temp_dir();
    }
    $status['services']['sessions'] = is_dir($sessionPath) && is_writable($sessionPath) ? 'ready' : 'not_ready';
    
    // Informações do sistema (apenas em desenvolvimento)
    $isProduction = (
        !empty($_SERVER['HTTPS']) || 
        (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
    );
    
    if (!$isProduction) {
        $status['system'] = [
            'session_path' => $sessionPath,
            'uploads_path' => $uploadsPath,
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time'),
            'upload_max_filesize' => ini_get('upload_max_filesize')
        ];
    }
    
    http_response_code(200);
    echo json_encode($status, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'unhealthy',
        'error' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}
?>