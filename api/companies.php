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
        
        // Listar todas as empresas (verificar se colunas existem)
        $activeOnly = isset($_GET['active_only']) && $_GET['active_only'] === 'true';
        
        // Verificar quais colunas existem
        $columns = [];
        $checkColumns = $db->query("SHOW COLUMNS FROM companies");
        $existingColumns = $checkColumns->fetchAll(PDO::FETCH_COLUMN);
        
        // Construir SELECT com apenas colunas existentes
        $selectFields = ['id', 'name'];
        if (in_array('code', $existingColumns)) $selectFields[] = 'code';
        if (in_array('address', $existingColumns)) $selectFields[] = 'address';
        if (in_array('phone', $existingColumns)) $selectFields[] = 'phone';
        if (in_array('email', $existingColumns)) $selectFields[] = 'email';
        if (in_array('tax_id', $existingColumns)) $selectFields[] = 'tax_id';
        if (in_array('is_active', $existingColumns)) $selectFields[] = 'is_active';
        if (in_array('created_at', $existingColumns)) $selectFields[] = 'created_at';
        if (in_array('updated_at', $existingColumns)) $selectFields[] = 'updated_at';
        
        $query = "SELECT " . implode(', ', $selectFields) . " FROM companies";
        if ($activeOnly && in_array('is_active', $existingColumns)) {
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
        
        $name = trim(sanitizeInput($input['name'] ?? ''));
        $code = trim(sanitizeInput($input['code'] ?? ''));
        $address = trim(sanitizeInput($input['address'] ?? ''));
        $phone = trim(sanitizeInput($input['phone'] ?? ''));
        $email = trim(sanitizeInput($input['email'] ?? ''));
        $taxId = trim(sanitizeInput($input['tax_id'] ?? ''));
        $isActive = isset($input['is_active']) ? (bool)$input['is_active'] : true;
        
        // Validações
        if (empty($name)) {
            sendJsonResponse([
                'success' => false,
                'message' => 'Nome da empresa é obrigatório'
            ], 400);
        }
        
        // Validar email se fornecido
        if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            sendJsonResponse([
                'success' => false,
                'message' => 'Email inválido'
            ], 400);
        }
        
        // Verificar se nome já existe (UNIQUE constraint)
        $checkName = $db->prepare("SELECT id FROM companies WHERE name = :name");
        $checkName->execute(['name' => $name]);
        if ($checkName->fetch()) {
            sendJsonResponse([
                'success' => false,
                'message' => 'Já existe uma empresa com este nome'
            ], 409);
        }
        
        // Verificar se código já existe (se fornecido)
        if (!empty($code)) {
            $checkCode = $db->prepare("SELECT id FROM companies WHERE code = :code");
            $checkCode->execute(['code' => $code]);
            if ($checkCode->fetch()) {
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
            'code' => !empty($code) ? $code : null,
            'address' => !empty($address) ? $address : null,
            'phone' => !empty($phone) ? $phone : null,
            'email' => !empty($email) ? $email : null,
            'tax_id' => !empty($taxId) ? $taxId : null,
            'is_active' => $isActive ? 1 : 0
        ]);
        
        $companyId = $db->lastInsertId();
        
        if (!$companyId) {
            throw new Exception('Falha ao obter ID da empresa criada');
        }
        
        $stmt = $db->prepare("
            SELECT id, name, code, address, phone, email, tax_id, is_active, created_at, updated_at
            FROM companies WHERE id = :id
        ");
        $stmt->execute(['id' => $companyId]);
        $company = $stmt->fetch();
        
        if (!$company) {
            throw new Exception('Falha ao recuperar empresa criada');
        }
        
        sendJsonResponse([
            'success' => true,
            'message' => 'Empresa criada com sucesso',
            'company' => $company
        ], 201);
        
    } catch (PDOException $e) {
        $errorCode = $e->getCode();
        $errorMessage = $e->getMessage();
        
        // Detectar erros específicos do MySQL
        if ($errorCode == 23000) { // Integrity constraint violation
            if (strpos($errorMessage, 'Duplicate entry') !== false) {
                if (strpos($errorMessage, 'name') !== false) {
                    sendJsonResponse([
                        'success' => false,
                        'message' => 'Já existe uma empresa com este nome'
                    ], 409);
                } elseif (strpos($errorMessage, 'code') !== false) {
                    sendJsonResponse([
                        'success' => false,
                        'message' => 'Código da empresa já existe'
                    ], 409);
                }
            }
        }
        
        error_log("Create company error: " . $errorMessage . " (Code: " . $errorCode . ")");
        sendJsonResponse([
            'success' => false,
            'message' => 'Erro ao criar empresa: ' . $errorMessage
        ], 500);
    } catch (Exception $e) {
        error_log("Create company general error: " . $e->getMessage());
        sendJsonResponse([
            'success' => false,
            'message' => 'Erro ao criar empresa: ' . $e->getMessage()
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

