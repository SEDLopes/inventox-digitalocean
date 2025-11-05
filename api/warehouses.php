<?php
/**
 * InventoX - Warehouses API
 * Gestão de armazéns
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
        handleGetWarehouses($input);
        break;
    
    case 'POST':
        requireAdmin();
        handleCreateWarehouse($input);
        break;
    
    case 'PUT':
        requireAdmin();
        handleUpdateWarehouse($input);
        break;
    
    case 'DELETE':
        requireAdmin();
        handleDeleteWarehouse($input);
        break;
    
    default:
        sendJsonResponse([
            'success' => false,
            'message' => 'Método não permitido'
        ], 405);
}

function handleGetWarehouses($input) {
    try {
        $db = getDB();
        
        if (isset($_GET['id'])) {
            $warehouseId = intval($_GET['id']);
            
            $stmt = $db->prepare("
                SELECT w.*, c.name as company_name
                FROM warehouses w
                INNER JOIN companies c ON w.company_id = c.id
                WHERE w.id = :id
            ");
            $stmt->execute(['id' => $warehouseId]);
            $warehouse = $stmt->fetch();
            
            if (!$warehouse) {
                sendJsonResponse([
                    'success' => false,
                    'message' => 'Armazém não encontrado'
                ], 404);
            }
            
            sendJsonResponse([
                'success' => true,
                'warehouse' => $warehouse
            ]);
        }
        
        // Listar armazéns (filtro por empresa opcional)
        $companyId = $_GET['company_id'] ?? null;
        $activeOnly = isset($_GET['active_only']) && $_GET['active_only'] === 'true';
        
        $query = "
            SELECT w.*, c.name as company_name
            FROM warehouses w
            INNER JOIN companies c ON w.company_id = c.id
            WHERE 1=1
        ";
        $params = [];
        
        if ($companyId) {
            $query .= " AND w.company_id = :company_id";
            $params['company_id'] = $companyId;
        }
        
        if ($activeOnly) {
            $query .= " AND w.is_active = 1";
        }
        
        $query .= " ORDER BY c.name, w.name";
        
        $stmt = $db->prepare($query);
        $stmt->execute($params);
        $warehouses = $stmt->fetchAll();
        
        sendJsonResponse([
            'success' => true,
            'warehouses' => $warehouses
        ]);
        
    } catch (PDOException $e) {
        error_log("Get warehouses error: " . $e->getMessage());
        sendJsonResponse([
            'success' => false,
            'message' => 'Erro ao listar armazéns'
        ], 500);
    }
}

function handleCreateWarehouse($input) {
    try {
        $db = getDB();
        
        $companyId = intval($input['company_id'] ?? 0);
        $name = sanitizeInput($input['name'] ?? '');
        $code = sanitizeInput($input['code'] ?? '');
        $address = sanitizeInput($input['address'] ?? '');
        $location = sanitizeInput($input['location'] ?? '');
        $isActive = isset($input['is_active']) ? (bool)$input['is_active'] : true;
        
        if (!$companyId || empty($name)) {
            sendJsonResponse([
                'success' => false,
                'message' => 'ID da empresa e nome do armazém são obrigatórios'
            ], 400);
        }
        
        // Verificar se empresa existe
        $checkCompany = $db->prepare("SELECT id FROM companies WHERE id = :id");
        $checkCompany->execute(['id' => $companyId]);
        if (!$checkCompany->fetch()) {
            sendJsonResponse([
                'success' => false,
                'message' => 'Empresa não encontrada'
            ], 404);
        }
        
        // Verificar se código já existe para esta empresa (se fornecido)
        if ($code) {
            $checkStmt = $db->prepare("SELECT id FROM warehouses WHERE company_id = :company_id AND code = :code");
            $checkStmt->execute(['company_id' => $companyId, 'code' => $code]);
            if ($checkStmt->fetch()) {
                sendJsonResponse([
                    'success' => false,
                    'message' => 'Código do armazém já existe para esta empresa'
                ], 409);
            }
        }
        
        $stmt = $db->prepare("
            INSERT INTO warehouses (company_id, name, code, address, location, is_active)
            VALUES (:company_id, :name, :code, :address, :location, :is_active)
        ");
        $stmt->execute([
            'company_id' => $companyId,
            'name' => $name,
            'code' => $code ?: null,
            'address' => $address ?: null,
            'location' => $location ?: null,
            'is_active' => $isActive ? 1 : 0
        ]);
        
        $warehouseId = $db->lastInsertId();
        
        $stmt = $db->prepare("
            SELECT w.*, c.name as company_name
            FROM warehouses w
            INNER JOIN companies c ON w.company_id = c.id
            WHERE w.id = :id
        ");
        $stmt->execute(['id' => $warehouseId]);
        $warehouse = $stmt->fetch();
        
        sendJsonResponse([
            'success' => true,
            'message' => 'Armazém criado com sucesso',
            'warehouse' => $warehouse
        ], 201);
        
    } catch (PDOException $e) {
        error_log("Create warehouse error: " . $e->getMessage());
        sendJsonResponse([
            'success' => false,
            'message' => 'Erro ao criar armazém'
        ], 500);
    }
}

function handleUpdateWarehouse($input) {
    try {
        $db = getDB();
        
        $warehouseId = intval($input['id'] ?? 0);
        
        if (!$warehouseId) {
            sendJsonResponse([
                'success' => false,
                'message' => 'ID do armazém é obrigatório'
            ], 400);
        }
        
        $updates = [];
        $params = ['id' => $warehouseId];
        
        if (isset($input['company_id'])) {
            $companyId = intval($input['company_id']);
            // Verificar se empresa existe
            $checkCompany = $db->prepare("SELECT id FROM companies WHERE id = :id");
            $checkCompany->execute(['id' => $companyId]);
            if (!$checkCompany->fetch()) {
                sendJsonResponse([
                    'success' => false,
                    'message' => 'Empresa não encontrada'
                ], 404);
            }
            $updates[] = "company_id = :company_id";
            $params['company_id'] = $companyId;
        }
        
        if (isset($input['name'])) {
            $updates[] = "name = :name";
            $params['name'] = sanitizeInput($input['name']);
        }
        
        if (isset($input['code'])) {
            // Verificar se código já existe para esta empresa (exceto para este armazém)
            if ($input['code']) {
                $currentCompanyId = isset($params['company_id']) ? $params['company_id'] : null;
                if (!$currentCompanyId) {
                    $getCurrent = $db->prepare("SELECT company_id FROM warehouses WHERE id = :id");
                    $getCurrent->execute(['id' => $warehouseId]);
                    $current = $getCurrent->fetch();
                    $currentCompanyId = $current['company_id'];
                }
                $checkStmt = $db->prepare("SELECT id FROM warehouses WHERE company_id = :company_id AND code = :code AND id != :id");
                $checkStmt->execute(['company_id' => $currentCompanyId, 'code' => sanitizeInput($input['code']), 'id' => $warehouseId]);
                if ($checkStmt->fetch()) {
                    sendJsonResponse([
                        'success' => false,
                        'message' => 'Código do armazém já existe para esta empresa'
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
        
        if (isset($input['location'])) {
            $updates[] = "location = :location";
            $params['location'] = sanitizeInput($input['location']) ?: null;
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
        
        $query = "UPDATE warehouses SET " . implode(', ', $updates) . " WHERE id = :id";
        $stmt = $db->prepare($query);
        $stmt->execute($params);
        
        $stmt = $db->prepare("
            SELECT w.*, c.name as company_name
            FROM warehouses w
            INNER JOIN companies c ON w.company_id = c.id
            WHERE w.id = :id
        ");
        $stmt->execute(['id' => $warehouseId]);
        $warehouse = $stmt->fetch();
        
        sendJsonResponse([
            'success' => true,
            'message' => 'Armazém atualizado com sucesso',
            'warehouse' => $warehouse
        ]);
        
    } catch (PDOException $e) {
        error_log("Update warehouse error: " . $e->getMessage());
        sendJsonResponse([
            'success' => false,
            'message' => 'Erro ao atualizar armazém'
        ], 500);
    }
}

function handleDeleteWarehouse($input) {
    try {
        $db = getDB();
        
        $warehouseId = intval($_GET['id'] ?? 0);
        
        if (!$warehouseId) {
            sendJsonResponse([
                'success' => false,
                'message' => 'ID do armazém é obrigatório'
            ], 400);
        }
        
        // Verificar se há sessões associadas
        $sessionsStmt = $db->prepare("SELECT COUNT(*) as count FROM inventory_sessions WHERE warehouse_id = :warehouse_id");
        $sessionsStmt->execute(['warehouse_id' => $warehouseId]);
        $sessionsCount = $sessionsStmt->fetch()['count'];
        
        if ($sessionsCount > 0) {
            sendJsonResponse([
                'success' => false,
                'message' => 'Não é possível eliminar armazém com sessões de inventário associadas'
            ], 409);
        }
        
        $stmt = $db->prepare("DELETE FROM warehouses WHERE id = :id");
        $stmt->execute(['id' => $warehouseId]);
        
        sendJsonResponse([
            'success' => true,
            'message' => 'Armazém eliminado com sucesso'
        ]);
        
    } catch (PDOException $e) {
        error_log("Delete warehouse error: " . $e->getMessage());
        sendJsonResponse([
            'success' => false,
            'message' => 'Erro ao eliminar armazém'
        ], 500);
    }
}

