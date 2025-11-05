<?php
/**
 * InventoX - Companies API
 * Gestão de empresas
 */

require_once __DIR__ . '/db.php';

requireAuth();

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    $input = [];
}

switch ($method) {
    case 'GET':
        handleGetCompanies($input);
        break;
    
    case 'POST':
        requireAdmin();
        handleCreateCompany($input);
        break;
    
    case 'PUT':
        requireAdmin();
        handleUpdateCompany($input);
        break;
    
    case 'DELETE':
        requireAdmin();
        handleDeleteCompany($input);
        break;
    
    default:
        sendJsonResponse([
            'success' => false,
            'message' => 'Método não permitido'
        ], 405);
}

function handleGetCompanies($input) {
    try {
        $db = getDB();
        
        if (isset($_GET['id'])) {
            $companyId = intval($_GET['id']);
            
            $stmt = $db->prepare("
                SELECT id, name, code, address, phone, email, tax_id, is_active, created_at, updated_at
                FROM companies
                WHERE id = :id
            ");
            $stmt->execute(['id' => $companyId]);
            $company = $stmt->fetch();
            
            if (!$company) {
                sendJsonResponse([
                    'success' => false,
                    'message' => 'Empresa não encontrada'
                ], 404);
            }
            
            sendJsonResponse([
                'success' => true,
                'company' => $company
            ]);
        }
        
        // Listar todas as empresas
        $activeOnly = isset($_GET['active_only']) && $_GET['active_only'] === 'true';
        
        $query = "SELECT id, name, code, address, phone, email, tax_id, is_active, created_at, updated_at FROM companies";
        if ($activeOnly) {
            $query .= " WHERE is_active = 1";
        }
        $query .= " ORDER BY name";
        
        $stmt = $db->query($query);
        $companies = $stmt->fetchAll();
        
        sendJsonResponse([
            'success' => true,
            'companies' => $companies
        ]);
        
    } catch (PDOException $e) {
        error_log("Get companies error: " . $e->getMessage());
        sendJsonResponse([
            'success' => false,
            'message' => 'Erro ao listar empresas'
        ], 500);
    }
}

function handleCreateCompany($input) {
    try {
        $db = getDB();
        
        $name = sanitizeInput($input['name'] ?? '');
        $code = sanitizeInput($input['code'] ?? '');
        $address = sanitizeInput($input['address'] ?? '');
        $phone = sanitizeInput($input['phone'] ?? '');
        $email = sanitizeInput($input['email'] ?? '');
        $taxId = sanitizeInput($input['tax_id'] ?? '');
        $isActive = isset($input['is_active']) ? (bool)$input['is_active'] : true;
        
        if (empty($name)) {
            sendJsonResponse([
                'success' => false,
                'message' => 'Nome da empresa é obrigatório'
            ], 400);
        }
        
        // Verificar se código já existe (se fornecido)
        if ($code) {
            $checkStmt = $db->prepare("SELECT id FROM companies WHERE code = :code");
            $checkStmt->execute(['code' => $code]);
            if ($checkStmt->fetch()) {
                sendJsonResponse([
                    'success' => false,
                    'message' => 'Código da empresa já existe'
                ], 409);
            }
        }
        
        $stmt = $db->prepare("
            INSERT INTO companies (name, code, address, phone, email, tax_id, is_active)
            VALUES (:name, :code, :address, :phone, :email, :tax_id, :is_active)
        ");
        $stmt->execute([
            'name' => $name,
            'code' => $code ?: null,
            'address' => $address ?: null,
            'phone' => $phone ?: null,
            'email' => $email ?: null,
            'tax_id' => $taxId ?: null,
            'is_active' => $isActive ? 1 : 0
        ]);
        
        $companyId = $db->lastInsertId();
        
        $stmt = $db->prepare("
            SELECT id, name, code, address, phone, email, tax_id, is_active, created_at, updated_at
            FROM companies WHERE id = :id
        ");
        $stmt->execute(['id' => $companyId]);
        $company = $stmt->fetch();
        
        sendJsonResponse([
            'success' => true,
            'message' => 'Empresa criada com sucesso',
            'company' => $company
        ], 201);
        
    } catch (PDOException $e) {
        error_log("Create company error: " . $e->getMessage());
        sendJsonResponse([
            'success' => false,
            'message' => 'Erro ao criar empresa'
        ], 500);
    }
}

function handleUpdateCompany($input) {
    try {
        $db = getDB();
        
        $companyId = intval($input['id'] ?? 0);
        
        if (!$companyId) {
            sendJsonResponse([
                'success' => false,
                'message' => 'ID da empresa é obrigatório'
            ], 400);
        }
        
        $updates = [];
        $params = ['id' => $companyId];
        
        if (isset($input['name'])) {
            $updates[] = "name = :name";
            $params['name'] = sanitizeInput($input['name']);
        }
        
        if (isset($input['code'])) {
            // Verificar se código já existe (exceto para esta empresa)
            if ($input['code']) {
                $checkStmt = $db->prepare("SELECT id FROM companies WHERE code = :code AND id != :id");
                $checkStmt->execute(['code' => sanitizeInput($input['code']), 'id' => $companyId]);
                if ($checkStmt->fetch()) {
                    sendJsonResponse([
                        'success' => false,
                        'message' => 'Código da empresa já existe'
                    ], 409);
                }
            }
            $updates[] = "code = :code";
            $params['code'] = sanitizeInput($input['code']) ?: null;
        }
        
        if (isset($input['address'])) {
            $updates[] = "address = :address";
            $params['address'] = sanitizeInput($input['address']) ?: null;
        }
        
        if (isset($input['phone'])) {
            $updates[] = "phone = :phone";
            $params['phone'] = sanitizeInput($input['phone']) ?: null;
        }
        
        if (isset($input['email'])) {
            $updates[] = "email = :email";
            $params['email'] = sanitizeInput($input['email']) ?: null;
        }
        
        if (isset($input['tax_id'])) {
            $updates[] = "tax_id = :tax_id";
            $params['tax_id'] = sanitizeInput($input['tax_id']) ?: null;
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
        
        $query = "UPDATE companies SET " . implode(', ', $updates) . " WHERE id = :id";
        $stmt = $db->prepare($query);
        $stmt->execute($params);
        
        $stmt = $db->prepare("
            SELECT id, name, code, address, phone, email, tax_id, is_active, created_at, updated_at
            FROM companies WHERE id = :id
        ");
        $stmt->execute(['id' => $companyId]);
        $company = $stmt->fetch();
        
        sendJsonResponse([
            'success' => true,
            'message' => 'Empresa atualizada com sucesso',
            'company' => $company
        ]);
        
    } catch (PDOException $e) {
        error_log("Update company error: " . $e->getMessage());
        sendJsonResponse([
            'success' => false,
            'message' => 'Erro ao atualizar empresa'
        ], 500);
    }
}

function handleDeleteCompany($input) {
    try {
        $db = getDB();
        
        $companyId = intval($_GET['id'] ?? 0);
        
        if (!$companyId) {
            sendJsonResponse([
                'success' => false,
                'message' => 'ID da empresa é obrigatório'
            ], 400);
        }
        
        // Verificar se há armazéns associados
        $warehousesStmt = $db->prepare("SELECT COUNT(*) as count FROM warehouses WHERE company_id = :company_id");
        $warehousesStmt->execute(['company_id' => $companyId]);
        $warehousesCount = $warehousesStmt->fetch()['count'];
        
        if ($warehousesCount > 0) {
            sendJsonResponse([
                'success' => false,
                'message' => 'Não é possível eliminar empresa com armazéns associados'
            ], 409);
        }
        
        // Verificar se há sessões associadas
        $sessionsStmt = $db->prepare("SELECT COUNT(*) as count FROM inventory_sessions WHERE company_id = :company_id");
        $sessionsStmt->execute(['company_id' => $companyId]);
        $sessionsCount = $sessionsStmt->fetch()['count'];
        
        if ($sessionsCount > 0) {
            sendJsonResponse([
                'success' => false,
                'message' => 'Não é possível eliminar empresa com sessões de inventário associadas'
            ], 409);
        }
        
        $stmt = $db->prepare("DELETE FROM companies WHERE id = :id");
        $stmt->execute(['id' => $companyId]);
        
        sendJsonResponse([
            'success' => true,
            'message' => 'Empresa eliminada com sucesso'
        ]);
        
    } catch (PDOException $e) {
        error_log("Delete company error: " . $e->getMessage());
        sendJsonResponse([
            'success' => false,
            'message' => 'Erro ao eliminar empresa'
        ], 500);
    }
}

