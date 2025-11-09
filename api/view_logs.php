<?php
/**
 * View Server Logs
 * Endpoint para visualizar logs do servidor (apenas para debug)
 * 
 * ⚠️ ATENÇÃO: Este endpoint requer autenticação admin
 */

require_once __DIR__ . '/db.php';

// Verificar autenticação
requireAuth();

// Verificar se é admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    sendJsonResponse([
        'success' => false,
        'message' => 'Acesso negado. Apenas administradores podem ver logs.'
    ], 403);
}

header('Content-Type: application/json; charset=utf-8');

// Tentar ler logs do Apache/PHP
$logFiles = [
    '/var/log/apache2/error.log',
    '/var/log/httpd/error_log',
    '/var/log/php_errors.log',
    '/var/log/php-fpm/error.log',
    sys_get_temp_dir() . '/php_errors.log'
];

$logs = [];
$foundLogs = false;

foreach ($logFiles as $logFile) {
    if (file_exists($logFile) && is_readable($logFile)) {
        $foundLogs = true;
        $fileContent = file_get_contents($logFile);
        $lines = explode("\n", $fileContent);
        
        // Pegar últimas 100 linhas
        $recentLines = array_slice($lines, -100);
        
        $logs[basename($logFile)] = [
            'path' => $logFile,
            'size' => filesize($logFile),
            'last_100_lines' => $recentLines
        ];
    }
}

// Se não encontrou logs do sistema, tentar error_log do PHP
if (!$foundLogs) {
    // Tentar ler do error_log do PHP
    $phpErrorLog = ini_get('error_log');
    if ($phpErrorLog && file_exists($phpErrorLog) && is_readable($phpErrorLog)) {
        $fileContent = file_get_contents($phpErrorLog);
        $lines = explode("\n", $fileContent);
        $recentLines = array_slice($lines, -100);
        
        $logs['php_error_log'] = [
            'path' => $phpErrorLog,
            'size' => filesize($phpErrorLog),
            'last_100_lines' => $recentLines
        ];
    }
}

// Adicionar informações do sistema
$response = [
    'success' => true,
    'system_info' => [
        'php_version' => PHP_VERSION,
        'error_log' => ini_get('error_log'),
        'log_errors' => ini_get('log_errors'),
        'display_errors' => ini_get('display_errors'),
        'error_reporting' => error_reporting(),
        'session_save_path' => session_save_path(),
        'upload_max_filesize' => ini_get('upload_max_filesize'),
        'post_max_size' => ini_get('post_max_size'),
        'max_execution_time' => ini_get('max_execution_time'),
        'memory_limit' => ini_get('memory_limit')
    ],
    'logs_found' => $foundLogs || !empty($logs),
    'log_files' => $logs
];

// Se não encontrou nenhum log, mostrar mensagem
if (empty($logs)) {
    $response['message'] = 'Nenhum ficheiro de log encontrado ou acessível. Os logs podem estar em outro local ou não estar configurados.';
    $response['suggestions'] = [
        'Verifique os logs no painel do DigitalOcean',
        'Verifique se error_log está configurado no php.ini',
        'Verifique os logs do Apache/Nginx no servidor'
    ];
}

echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

