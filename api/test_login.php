<?php
/**
 * Script de teste para verificar login e database
 * Acesse: https://seu-app.ondigitalocean.app/api/test_login.php
 */

require_once __DIR__ . '/db.php';

header('Content-Type: application/json');

try {
    $db = getDB();
    
    // Verificar se database está conectada
    $dbStatus = [
        'database_connected' => true,
        'database_name' => $_ENV['DB_NAME'] ?? getenv('DB_NAME') ?? 'N/A',
        'users_table_exists' => false,
        'admin_user_exists' => false,
        'admin_user_active' => false,
        'password_hash_check' => false
    ];
    
    // Verificar se tabela users existe
    try {
        $stmt = $db->query("SHOW TABLES LIKE 'users'");
        $dbStatus['users_table_exists'] = $stmt->rowCount() > 0;
    } catch (Exception $e) {
        $dbStatus['users_table_error'] = $e->getMessage();
    }
    
    // Verificar se utilizador admin existe
    if ($dbStatus['users_table_exists']) {
        try {
            $stmt = $db->prepare("SELECT id, username, email, role, is_active, password_hash FROM users WHERE username = 'admin'");
            $stmt->execute();
            $adminUser = $stmt->fetch();
            
            if ($adminUser) {
                $dbStatus['admin_user_exists'] = true;
                $dbStatus['admin_user_active'] = (bool)$adminUser['is_active'];
                $dbStatus['admin_user_id'] = $adminUser['id'];
                $dbStatus['admin_user_role'] = $adminUser['role'];
                
                // Testar password hash
                $testPassword = 'admin123';
                $dbStatus['password_hash_check'] = password_verify($testPassword, $adminUser['password_hash']);
                $dbStatus['password_hash_length'] = strlen($adminUser['password_hash']);
            }
        } catch (Exception $e) {
            $dbStatus['admin_user_error'] = $e->getMessage();
        }
    }
    
    // Testar login direto
    $loginTest = [
        'username' => 'admin',
        'password' => 'admin123',
        'login_success' => false,
        'error' => null
    ];
    
    if ($dbStatus['admin_user_exists'] && $dbStatus['admin_user_active']) {
        try {
            $stmt = $db->prepare("SELECT id, username, email, password_hash, role, is_active FROM users WHERE username = :username");
            $stmt->execute(['username' => 'admin']);
            $user = $stmt->fetch();
            
            if ($user && password_verify('admin123', $user['password_hash'])) {
                $loginTest['login_success'] = true;
            } else {
                $loginTest['error'] = 'Password não corresponde';
            }
        } catch (Exception $e) {
            $loginTest['error'] = $e->getMessage();
        }
    } else {
        $loginTest['error'] = 'Utilizador admin não existe ou está inativo';
    }
    
    // Resumo
    $summary = [
        'status' => 'ok',
        'database' => $dbStatus,
        'login_test' => $loginTest,
        'recommendations' => []
    ];
    
    if (!$dbStatus['users_table_exists']) {
        $summary['recommendations'][] = 'Tabela users não existe. Execute: /api/init_database.php?token=inventox2024';
    }
    
    if (!$dbStatus['admin_user_exists']) {
        $summary['recommendations'][] = 'Utilizador admin não existe. Execute: /api/init_database.php?token=inventox2024';
    }
    
    if (!$dbStatus['admin_user_active']) {
        $summary['recommendations'][] = 'Utilizador admin está inativo. Ative na database.';
    }
    
    if (!$dbStatus['password_hash_check']) {
        $summary['recommendations'][] = 'Password hash não corresponde. Execute: /api/init_database.php?token=inventox2024';
    }
    
    if (!$loginTest['login_success']) {
        $summary['recommendations'][] = 'Login falhou: ' . ($loginTest['error'] ?? 'Erro desconhecido');
    }
    
    echo json_encode($summary, JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ], JSON_PRETTY_PRINT);
}
?>
