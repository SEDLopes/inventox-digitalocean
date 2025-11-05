<?php
/**
 * InventoX - Login API
 * Endpoint de autenticação de utilizadores
 */

require_once __DIR__ . '/db.php';

// Permitir apenas POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJsonResponse([
        'success' => false,
        'message' => 'Método não permitido'
    ], 405);
}

// Obter dados do POST
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    $input = $_POST;
}

$username = sanitizeInput($input['username'] ?? '');
$password = $input['password'] ?? '';

// Validação
if (empty($username) || empty($password)) {
    sendJsonResponse([
        'success' => false,
        'message' => 'Username e password são obrigatórios'
    ], 400);
}

try {
    $db = getDB();
    
    // Buscar utilizador (usar named parameter corretamente para OR)
    $stmt = $db->prepare("
        SELECT id, username, email, password_hash, role, is_active 
        FROM users 
        WHERE username = :username OR email = :email
    ");
    $stmt->execute(['username' => $username, 'email' => $username]);
    $user = $stmt->fetch();

    if (!$user) {
        sendJsonResponse([
            'success' => false,
            'message' => 'Credenciais inválidas'
        ], 401);
    }

    // Verificar se utilizador está ativo
    if (!$user['is_active']) {
        sendJsonResponse([
            'success' => false,
            'message' => 'Utilizador inativo'
        ], 403);
    }

    // Verificar password
    if (!password_verify($password, $user['password_hash'])) {
        sendJsonResponse([
            'success' => false,
            'message' => 'Credenciais inválidas'
        ], 401);
    }

    // Se já existir uma sessão ativa, destruí-la primeiro para evitar conflitos
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
    
    // Configurar cookies de sessão ANTES de session_start()
    // IMPORTANTE: ini_set deve ser chamado ANTES de session_start()
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax');
    ini_set('session.use_strict_mode', '1');
    ini_set('session.cookie_path', '/');
    ini_set('session.cookie_domain', '');
    ini_set('session.cookie_lifetime', 0); // Cookie de sessão (expira ao fechar navegador)
    ini_set('session.cookie_secure', '0'); // 0 para HTTP local, 1 para HTTPS em produção
    
    // Configurar diretório de sessões (se não estiver configurado)
    $sessionPath = ini_get('session.save_path');
    if (empty($sessionPath)) {
        // Tentar usar diretório padrão ou criar um
        $defaultPath = '/var/lib/php/sessions';
        if (is_dir($defaultPath) || @mkdir($defaultPath, 1733, true)) {
            ini_set('session.save_path', $defaultPath);
        }
    }
    
    // Iniciar sessão
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Regenerar ID de sessão ANTES de armazenar dados (para segurança)
    // Isso evita session fixation attacks
    session_regenerate_id(true);
    
    // Armazenar dados na sessão
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['email'] = $user['email'];
    $_SESSION['role'] = $user['role'];
    
    // Garantir que a sessão foi escrita (PHP faz isso automaticamente ao finalizar script)
    // Mas vamos verificar que os dados estão realmente na sessão
    
    // Log de login para debug
    $cookieName = session_name();
    $sessionId = session_id();
    error_log("Login successful - Session ID: " . $sessionId . 
              ", Cookie name: " . $cookieName . 
              ", User: {$user['username']}, " .
              "Session has user_id: " . (isset($_SESSION['user_id']) ? 'YES' : 'NO'));
    
    // Verificar se os dados estão na sessão
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['username'])) {
        error_log("ERRO CRÍTICO: Dados da sessão não foram salvos após login!");
        sendJsonResponse([
            'success' => false,
            'message' => 'Erro ao criar sessão'
        ], 500);
    }

    sendJsonResponse([
        'success' => true,
        'message' => 'Login realizado com sucesso',
        'user' => [
            'id' => $user['id'],
            'username' => $user['username'],
            'email' => $user['email'],
            'role' => $user['role']
        ]
    ]);

} catch (PDOException $e) {
    error_log("Login error: " . $e->getMessage());
    error_log("Login error trace: " . $e->getTraceAsString());
    sendJsonResponse([
        'success' => false,
        'message' => 'Erro ao processar login: ' . $e->getMessage()
    ], 500);
} catch (Exception $e) {
    error_log("Login general error: " . $e->getMessage());
    sendJsonResponse([
        'success' => false,
        'message' => 'Erro ao processar login: ' . $e->getMessage()
    ], 500);
}

