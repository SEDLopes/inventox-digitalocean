<?php
/**
 * InventoX - Users API
 * Gestão de utilizadores (apenas para administradores)
 */

require_once __DIR__ . '/db.php';

// Verificar autenticação e permissões de admin
requireAuth();
requireAdmin();

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    $input = [];
}

// Operações por método HTTP
switch ($method) {
    case 'GET':
        handleGetUsers($input);
        break;
    
    case 'POST':
        handleCreateUser($input);
        break;
    
    case 'PUT':
        handleUpdateUser($input);
        break;
    
    case 'DELETE':
        handleDeleteUser($input);
        break;
    
    default:
        sendJsonResponse([
            'success' => false,
            'message' => 'Método não permitido'
        ], 405);
}

// GET - Listar ou obter utilizador específico
function handleGetUsers($input) {
    try {
        $db = getDB();
        
        // Verificar se é para obter um utilizador específico
        if (isset($_GET['id'])) {
            $userId = intval($_GET['id']);
            
            $stmt = $db->prepare("
                SELECT id, username, email, role, is_active, created_at, updated_at
                FROM users
                WHERE id = :id
            ");
            $stmt->execute(['id' => $userId]);
            $user = $stmt->fetch();
            
            if (!$user) {
                sendJsonResponse([
                    'success' => false,
                    'message' => 'Utilizador não encontrado'
                ], 404);
            }
            
            sendJsonResponse([
                'success' => true,
                'user' => $user
            ]);
        }
        
        // Listar todos os utilizadores
        $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
        $limit = isset($_GET['limit']) ? max(1, min(100, intval($_GET['limit']))) : 20;
        $offset = ($page - 1) * $limit;
        $search = sanitizeInput($_GET['search'] ?? '');
        
        $query = "
            SELECT id, username, email, role, is_active, created_at, updated_at
            FROM users
        ";
        $params = [];
        
        if ($search) {
            $query .= " WHERE username LIKE :search OR email LIKE :search";
            $params['search'] = "%{$search}%";
        }
        
        $query .= " ORDER BY created_at DESC LIMIT :limit OFFSET :offset";
        
        $stmt = $db->prepare($query);
        foreach ($params as $key => $value) {
            $stmt->bindValue(":{$key}", $value);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $users = $stmt->fetchAll();
        
        // Contar total
        $countQuery = "SELECT COUNT(*) as total FROM users";
        if ($search) {
            $countQuery .= " WHERE username LIKE :search OR email LIKE :search";
        }
        $countStmt = $db->prepare($countQuery);
        if ($search) {
            $countStmt->execute(['search' => "%{$search}%"]);
        } else {
            $countStmt->execute();
        }
        $total = $countStmt->fetch()['total'];
        
        sendJsonResponse([
            'success' => true,
            'users' => $users,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'pages' => ceil($total / $limit)
            ]
        ]);
        
    } catch (Exception $e) {
        error_log("Erro ao listar utilizadores: " . $e->getMessage());
        sendJsonResponse([
            'success' => false,
            'message' => 'Erro ao listar utilizadores'
        ], 500);
    }
}

// POST - Criar novo utilizador
function handleCreateUser($input) {
    try {
        $db = getDB();
        
        $username = sanitizeInput($input['username'] ?? '');
        $email = sanitizeInput($input['email'] ?? '');
        $password = $input['password'] ?? '';
        $role = sanitizeInput($input['role'] ?? 'operador');
        $isActive = isset($input['is_active']) ? (bool)$input['is_active'] : true;
        
        // Validações
        if (empty($username) || empty($email) || empty($password)) {
            sendJsonResponse([
                'success' => false,
                'message' => 'Username, email e password são obrigatórios'
            ], 400);
        }
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            sendJsonResponse([
                'success' => false,
                'message' => 'Email inválido'
            ], 400);
        }
        
        if (!in_array($role, ['admin', 'operador'])) {
            sendJsonResponse([
                'success' => false,
                'message' => 'Role inválida'
            ], 400);
        }
        
        if (strlen($password) < 6) {
            sendJsonResponse([
                'success' => false,
                'message' => 'Password deve ter pelo menos 6 caracteres'
            ], 400);
        }
        
        // Verificar se username ou email já existem
        $checkStmt = $db->prepare("
            SELECT id FROM users WHERE username = :username OR email = :email
        ");
        $checkStmt->execute(['username' => $username, 'email' => $email]);
        if ($checkStmt->fetch()) {
            sendJsonResponse([
                'success' => false,
                'message' => 'Username ou email já existem'
            ], 409);
        }
        
        // Criar hash da password
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        
        // Inserir utilizador
        $stmt = $db->prepare("
            INSERT INTO users (username, email, password_hash, role, is_active)
            VALUES (:username, :email, :password_hash, :role, :is_active)
        ");
        $stmt->execute([
            'username' => $username,
            'email' => $email,
            'password_hash' => $passwordHash,
            'role' => $role,
            'is_active' => $isActive ? 1 : 0
        ]);
        
        $userId = $db->lastInsertId();
        
        // Buscar utilizador criado (sem password)
        $stmt = $db->prepare("
            SELECT id, username, email, role, is_active, created_at, updated_at
            FROM users WHERE id = :id
        ");
        $stmt->execute(['id' => $userId]);
        $user = $stmt->fetch();
        
        sendJsonResponse([
            'success' => true,
            'message' => 'Utilizador criado com sucesso',
            'user' => $user
        ], 201);
        
    } catch (PDOException $e) {
        error_log("Erro ao criar utilizador: " . $e->getMessage());
        sendJsonResponse([
            'success' => false,
            'message' => 'Erro ao criar utilizador'
        ], 500);
    } catch (Exception $e) {
        error_log("Erro ao criar utilizador: " . $e->getMessage());
        sendJsonResponse([
            'success' => false,
            'message' => 'Erro ao criar utilizador'
        ], 500);
    }
}

// PUT - Atualizar utilizador
function handleUpdateUser($input) {
    try {
        $db = getDB();
        
        $userId = intval($input['id'] ?? 0);
        
        if (!$userId) {
            sendJsonResponse([
                'success' => false,
                'message' => 'ID do utilizador é obrigatório'
            ], 400);
        }
        
        // Verificar se utilizador existe
        $checkStmt = $db->prepare("SELECT id FROM users WHERE id = :id");
        $checkStmt->execute(['id' => $userId]);
        if (!$checkStmt->fetch()) {
            sendJsonResponse([
                'success' => false,
                'message' => 'Utilizador não encontrado'
            ], 404);
        }
        
        // Não permitir auto-eliminação do último admin
        if (isset($input['is_active']) && !$input['is_active']) {
            $currentUser = $_SESSION['user_id'];
            if ($userId == $currentUser) {
                sendJsonResponse([
                    'success' => false,
                    'message' => 'Não pode desativar o seu próprio utilizador'
                ], 403);
            }
        }
        
        // Preparar campos para atualizar
        $updates = [];
        $params = ['id' => $userId];
        
        if (isset($input['username'])) {
            $username = sanitizeInput($input['username']);
            if (empty($username)) {
                sendJsonResponse([
                    'success' => false,
                    'message' => 'Username não pode estar vazio'
                ], 400);
            }
            
            // Verificar se username já existe (exceto para este utilizador)
            $checkStmt = $db->prepare("SELECT id FROM users WHERE username = :username AND id != :id");
            $checkStmt->execute(['username' => $username, 'id' => $userId]);
            if ($checkStmt->fetch()) {
                sendJsonResponse([
                    'success' => false,
                    'message' => 'Username já existe'
                ], 409);
            }
            
            $updates[] = "username = :username";
            $params['username'] = $username;
        }
        
        if (isset($input['email'])) {
            $email = sanitizeInput($input['email']);
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                sendJsonResponse([
                    'success' => false,
                    'message' => 'Email inválido'
                ], 400);
            }
            
            // Verificar se email já existe (exceto para este utilizador)
            $checkStmt = $db->prepare("SELECT id FROM users WHERE email = :email AND id != :id");
            $checkStmt->execute(['email' => $email, 'id' => $userId]);
            if ($checkStmt->fetch()) {
                sendJsonResponse([
                    'success' => false,
                    'message' => 'Email já existe'
                ], 409);
            }
            
            $updates[] = "email = :email";
            $params['email'] = $email;
        }
        
        if (isset($input['password']) && !empty($input['password'])) {
            $password = $input['password'];
            if (strlen($password) < 6) {
                sendJsonResponse([
                    'success' => false,
                    'message' => 'Password deve ter pelo menos 6 caracteres'
                ], 400);
            }
            $updates[] = "password_hash = :password_hash";
            $params['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
        }
        
        if (isset($input['role'])) {
            $role = sanitizeInput($input['role']);
            if (!in_array($role, ['admin', 'operador'])) {
                sendJsonResponse([
                    'success' => false,
                    'message' => 'Role inválida'
                ], 400);
            }
            $updates[] = "role = :role";
            $params['role'] = $role;
        }
        
        if (isset($input['is_active'])) {
            $updates[] = "is_active = :is_active";
            $params['is_active'] = $input['is_active'] ? 1 : 0;
        }
        
        if (empty($updates)) {
            sendJsonResponse([
                'success' => false,
                'message' => 'Nenhum campo para atualizar'
            ], 400);
        }
        
        // Atualizar
        $query = "UPDATE users SET " . implode(', ', $updates) . " WHERE id = :id";
        $stmt = $db->prepare($query);
        $stmt->execute($params);
        
        // Buscar utilizador atualizado
        $stmt = $db->prepare("
            SELECT id, username, email, role, is_active, created_at, updated_at
            FROM users WHERE id = :id
        ");
        $stmt->execute(['id' => $userId]);
        $user = $stmt->fetch();
        
        sendJsonResponse([
            'success' => true,
            'message' => 'Utilizador atualizado com sucesso',
            'user' => $user
        ]);
        
    } catch (PDOException $e) {
        error_log("Erro ao atualizar utilizador: " . $e->getMessage());
        sendJsonResponse([
            'success' => false,
            'message' => 'Erro ao atualizar utilizador'
        ], 500);
    } catch (Exception $e) {
        error_log("Erro ao atualizar utilizador: " . $e->getMessage());
        sendJsonResponse([
            'success' => false,
            'message' => 'Erro ao atualizar utilizador'
        ], 500);
    }
}

// DELETE - Eliminar utilizador
function handleDeleteUser($input) {
    try {
        $db = getDB();
        
        $userId = intval($_GET['id'] ?? 0);
        
        if (!$userId) {
            sendJsonResponse([
                'success' => false,
                'message' => 'ID do utilizador é obrigatório'
            ], 400);
        }
        
        // Verificar se utilizador existe
        $checkStmt = $db->prepare("SELECT id FROM users WHERE id = :id");
        $checkStmt->execute(['id' => $userId]);
        if (!$checkStmt->fetch()) {
            sendJsonResponse([
                'success' => false,
                'message' => 'Utilizador não encontrado'
            ], 404);
        }
        
        // Não permitir auto-eliminação
        $currentUser = $_SESSION['user_id'];
        if ($userId == $currentUser) {
            sendJsonResponse([
                'success' => false,
                'message' => 'Não pode eliminar o seu próprio utilizador'
            ], 403);
        }
        
        // Verificar se há sessões de inventário associadas
        $sessionsStmt = $db->prepare("SELECT COUNT(*) as count FROM inventory_sessions WHERE user_id = :user_id");
        $sessionsStmt->execute(['user_id' => $userId]);
        $sessionsCount = $sessionsStmt->fetch()['count'];
        
        if ($sessionsCount > 0) {
            sendJsonResponse([
                'success' => false,
                'message' => 'Não é possível eliminar utilizador com sessões de inventário associadas'
            ], 409);
        }
        
        // Eliminar utilizador
        $stmt = $db->prepare("DELETE FROM users WHERE id = :id");
        $stmt->execute(['id' => $userId]);
        
        sendJsonResponse([
            'success' => true,
            'message' => 'Utilizador eliminado com sucesso'
        ]);
        
    } catch (PDOException $e) {
        error_log("Erro ao eliminar utilizador: " . $e->getMessage());
        sendJsonResponse([
            'success' => false,
            'message' => 'Erro ao eliminar utilizador'
        ], 500);
    } catch (Exception $e) {
        error_log("Erro ao eliminar utilizador: " . $e->getMessage());
        sendJsonResponse([
            'success' => false,
            'message' => 'Erro ao eliminar utilizador'
        ], 500);
    }
}

// Função requireAdmin() está definida em db.php - não redeclarar aqui

