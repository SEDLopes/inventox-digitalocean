<?php
/**
 * InventoX - Items API
 * Endpoint CRUD para gestão de artigos
 */

require_once __DIR__ . '/db.php';

// Verificar autenticação (requireAuth já inicia a sessão se necessário)
requireAuth();

$method = $_SERVER['REQUEST_METHOD'];
$db = getDB();

try {
    switch ($method) {
        case 'GET':
            // Listar artigos ou obter um específico
            $itemId = $_GET['id'] ?? null;
            $barcode = $_GET['barcode'] ?? null;
            
            if ($itemId) {
                // Obter artigo específico por ID
                // Verificar quais colunas existem antes de fazer SELECT
                $checkColumns = $db->query("SHOW COLUMNS FROM items");
                $itemColumns = $checkColumns->fetchAll(PDO::FETCH_COLUMN);
                
                $selectFields = ['i.id', 'i.barcode', 'i.name'];
                if (in_array('description', $itemColumns)) $selectFields[] = 'i.description';
                if (in_array('category_id', $itemColumns)) $selectFields[] = 'i.category_id';
                if (in_array('quantity', $itemColumns)) $selectFields[] = 'i.quantity';
                if (in_array('min_quantity', $itemColumns)) $selectFields[] = 'i.min_quantity';
                if (in_array('unit_price', $itemColumns)) $selectFields[] = 'i.unit_price';
                if (in_array('location', $itemColumns)) $selectFields[] = 'i.location';
                if (in_array('supplier', $itemColumns)) $selectFields[] = 'i.supplier';
                if (in_array('created_at', $itemColumns)) $selectFields[] = 'i.created_at';
                if (in_array('updated_at', $itemColumns)) $selectFields[] = 'i.updated_at';
                $selectFields[] = 'c.name as category_name';
                
                $stmt = $db->prepare("
                    SELECT " . implode(', ', $selectFields) . "
                    FROM items i
                    LEFT JOIN categories c ON i.category_id = c.id
                    WHERE i.id = :id
                ");
                $stmt->execute(['id' => $itemId]);
                $item = $stmt->fetch();
                
                if (!$item) {
                    sendJsonResponse([
                        'success' => false,
                        'message' => 'Artigo não encontrado'
                    ], 404);
                }
                
                sendJsonResponse([
                    'success' => true,
                    'item' => $item
                ]);
            } elseif ($barcode) {
                // Obter artigo por código de barras (já existe get_item.php, mas podemos usar este também)
                // Verificar quais colunas existem antes de fazer SELECT
                $checkColumns = $db->query("SHOW COLUMNS FROM items");
                $itemColumns = $checkColumns->fetchAll(PDO::FETCH_COLUMN);
                
                $selectFields = ['i.id', 'i.barcode', 'i.name'];
                if (in_array('description', $itemColumns)) $selectFields[] = 'i.description';
                if (in_array('category_id', $itemColumns)) $selectFields[] = 'i.category_id';
                if (in_array('quantity', $itemColumns)) $selectFields[] = 'i.quantity';
                if (in_array('min_quantity', $itemColumns)) $selectFields[] = 'i.min_quantity';
                if (in_array('unit_price', $itemColumns)) $selectFields[] = 'i.unit_price';
                if (in_array('location', $itemColumns)) $selectFields[] = 'i.location';
                if (in_array('supplier', $itemColumns)) $selectFields[] = 'i.supplier';
                if (in_array('created_at', $itemColumns)) $selectFields[] = 'i.created_at';
                if (in_array('updated_at', $itemColumns)) $selectFields[] = 'i.updated_at';
                $selectFields[] = 'c.name as category_name';
                
                $stmt = $db->prepare("
                    SELECT " . implode(', ', $selectFields) . "
                    FROM items i
                    LEFT JOIN categories c ON i.category_id = c.id
                    WHERE i.barcode = :barcode
                ");
                $stmt->execute(['barcode' => $barcode]);
                $item = $stmt->fetch();
                
                if (!$item) {
                    sendJsonResponse([
                        'success' => false,
                        'message' => 'Artigo não encontrado'
                    ], 404);
                }
                
                sendJsonResponse([
                    'success' => true,
                    'item' => $item
                ]);
            } else {
                // Listar todos os artigos com paginação opcional
                $page = max(1, intval($_GET['page'] ?? 1));
                $limit = max(1, min(100, intval($_GET['limit'] ?? 50)));
                $offset = ($page - 1) * $limit;
                $search = sanitizeInput($_GET['search'] ?? '');
                $categoryId = $_GET['category_id'] ?? null;
                $lowStock = isset($_GET['low_stock']) && ($_GET['low_stock'] === '1' || $_GET['low_stock'] === 'true');
                
                $where = [];
                $params = [];
                
                if ($search) {
                    $where[] = "(i.name LIKE :search OR i.barcode LIKE :search OR i.description LIKE :search)";
                    $params['search'] = "%{$search}%";
                }
                
                if ($categoryId) {
                    $where[] = "i.category_id = :category_id";
                    $params['category_id'] = $categoryId;
                }
                
                if ($lowStock) {
                    $where[] = "i.quantity <= i.min_quantity";
                }
                
                $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
                
                // Contar total
                $countStmt = $db->prepare("
                    SELECT COUNT(*) as total
                    FROM items i
                    {$whereClause}
                ");
                $countStmt->execute($params);
                $total = $countStmt->fetch()['total'];
                
                // Buscar itens
                $stmt = $db->prepare("
                    SELECT 
                        i.*,
                        c.name as category_name
                    FROM items i
                    LEFT JOIN categories c ON i.category_id = c.id
                    {$whereClause}
                    ORDER BY i.name ASC
                    LIMIT :limit OFFSET :offset
                ");
                $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
                $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
                foreach ($params as $key => $value) {
                    $stmt->bindValue(":{$key}", $value);
                }
                $stmt->execute();
                $items = $stmt->fetchAll();
                
                sendJsonResponse([
                    'success' => true,
                    'items' => $items,
                    'pagination' => [
                        'page' => $page,
                        'limit' => $limit,
                        'total' => intval($total),
                        'pages' => ceil($total / $limit)
                    ]
                ]);
            }
            break;
            
        case 'POST':
            // Criar novo artigo
            $input = json_decode(file_get_contents('php://input'), true);
            
            $barcode = sanitizeInput($input['barcode'] ?? '');
            $name = sanitizeInput($input['name'] ?? '');
            $description = sanitizeInput($input['description'] ?? '');
            $categoryId = $input['category_id'] ?? null;
            $quantity = intval($input['quantity'] ?? 0);
            $minQuantity = intval($input['min_quantity'] ?? 0);
            $unitPrice = floatval($input['unit_price'] ?? 0);
            $location = sanitizeInput($input['location'] ?? '');
            $supplier = sanitizeInput($input['supplier'] ?? '');
            
            // Validação
            if (empty($barcode) || empty($name)) {
                sendJsonResponse([
                    'success' => false,
                    'message' => 'Código de barras e nome são obrigatórios'
                ], 400);
            }
            
            // Verificar se barcode já existe
            $checkStmt = $db->prepare("SELECT id FROM items WHERE barcode = :barcode");
            $checkStmt->execute(['barcode' => $barcode]);
            if ($checkStmt->fetch()) {
                sendJsonResponse([
                    'success' => false,
                    'message' => 'Código de barras já existe'
                ], 400);
            }
            
            // Validar category_id se fornecido
            if ($categoryId) {
                $catStmt = $db->prepare("SELECT id FROM categories WHERE id = :id");
                $catStmt->execute(['id' => $categoryId]);
                if (!$catStmt->fetch()) {
                    sendJsonResponse([
                        'success' => false,
                        'message' => 'Categoria não encontrada'
                    ], 400);
                }
            }
            
            $stmt = $db->prepare("
                INSERT INTO items 
                (barcode, name, description, category_id, quantity, min_quantity, unit_price, location, supplier)
                VALUES 
                (:barcode, :name, :description, :category_id, :quantity, :min_quantity, :unit_price, :location, :supplier)
            ");
            $stmt->execute([
                'barcode' => $barcode,
                'name' => $name,
                'description' => $description ?: null,
                'category_id' => $categoryId ?: null,
                'quantity' => $quantity,
                'min_quantity' => $minQuantity,
                'unit_price' => $unitPrice,
                'location' => $location ?: null,
                'supplier' => $supplier ?: null
            ]);
            
            $itemId = $db->lastInsertId();
            
            // Registrar movimentação inicial se quantidade > 0
            if ($quantity > 0) {
                $movementStmt = $db->prepare("
                    INSERT INTO stock_movements 
                    (item_id, movement_type, quantity, reason, user_id)
                    VALUES 
                    (:item_id, 'entrada', :quantity, :reason, :user_id)
                ");
                $movementStmt->execute([
                    'item_id' => $itemId,
                    'quantity' => $quantity,
                    'reason' => 'Criação de artigo com stock inicial',
                    'user_id' => $_SESSION['user_id'] ?? null
                ]);
            }
            
            sendJsonResponse([
                'success' => true,
                'message' => 'Artigo criado com sucesso',
                'item_id' => $itemId
            ], 201);
            break;
            
        case 'PUT':
            // Atualizar artigo
            $input = json_decode(file_get_contents('php://input'), true);
            $itemId = $input['id'] ?? null;
            
            if (!$itemId) {
                sendJsonResponse([
                    'success' => false,
                    'message' => 'ID do artigo é obrigatório'
                ], 400);
            }
            
            // Verificar se artigo existe
            $checkStmt = $db->prepare("SELECT id FROM items WHERE id = :id");
            $checkStmt->execute(['id' => $itemId]);
            if (!$checkStmt->fetch()) {
                sendJsonResponse([
                    'success' => false,
                    'message' => 'Artigo não encontrado'
                ], 404);
            }
            
            // Preparar campos para atualização
            $updates = [];
            $params = ['id' => $itemId];
            
            if (isset($input['name'])) {
                $updates[] = "name = :name";
                $params['name'] = sanitizeInput($input['name']);
            }
            if (isset($input['description'])) {
                $updates[] = "description = :description";
                $params['description'] = sanitizeInput($input['description']);
            }
            if (isset($input['category_id'])) {
                $updates[] = "category_id = :category_id";
                $params['category_id'] = $input['category_id'] ?: null;
            }
            // Buscar quantidade atual para calcular diferença
            $currentStmt = $db->prepare("SELECT quantity FROM items WHERE id = :id");
            $currentStmt->execute(['id' => $itemId]);
            $currentItem = $currentStmt->fetch();
            $oldQuantity = $currentItem ? intval($currentItem['quantity']) : 0;
            
            if (isset($input['quantity'])) {
                $newQuantity = intval($input['quantity']);
                $updates[] = "quantity = :quantity";
                $params['quantity'] = $newQuantity;
            }
            if (isset($input['min_quantity'])) {
                $updates[] = "min_quantity = :min_quantity";
                $params['min_quantity'] = intval($input['min_quantity']);
            }
            if (isset($input['unit_price'])) {
                $updates[] = "unit_price = :unit_price";
                $params['unit_price'] = floatval($input['unit_price']);
            }
            if (isset($input['location'])) {
                $updates[] = "location = :location";
                $params['location'] = sanitizeInput($input['location']) ?: null;
            }
            if (isset($input['supplier'])) {
                $updates[] = "supplier = :supplier";
                $params['supplier'] = sanitizeInput($input['supplier']) ?: null;
            }
            
            if (empty($updates)) {
                sendJsonResponse([
                    'success' => false,
                    'message' => 'Nenhum campo para atualizar'
                ], 400);
            }
            
            $updates[] = "updated_at = CURRENT_TIMESTAMP";
            
            $stmt = $db->prepare("
                UPDATE items 
                SET " . implode(', ', $updates) . "
                WHERE id = :id
            ");
            $stmt->execute($params);
            
            // Registrar movimentação de stock se a quantidade mudou
            if (isset($input['quantity']) && $oldQuantity != $newQuantity) {
                $quantityDiff = $newQuantity - $oldQuantity;
                $movementType = 'ajuste';
                $reason = isset($input['movement_reason']) ? sanitizeInput($input['movement_reason']) : 'Atualização manual de quantidade';
                
                // Determinar tipo de movimentação baseado na diferença
                if ($quantityDiff > 0) {
                    $movementType = 'entrada';
                } elseif ($quantityDiff < 0) {
                    $movementType = 'saida';
                }
                
                $movementStmt = $db->prepare("
                    INSERT INTO stock_movements 
                    (item_id, movement_type, quantity, reason, user_id)
                    VALUES 
                    (:item_id, :movement_type, :quantity, :reason, :user_id)
                ");
                $movementStmt->execute([
                    'item_id' => $itemId,
                    'movement_type' => $movementType,
                    'quantity' => abs($quantityDiff),
                    'reason' => $reason,
                    'user_id' => $_SESSION['user_id'] ?? null
                ]);
            }
            
            sendJsonResponse([
                'success' => true,
                'message' => 'Artigo atualizado com sucesso'
            ]);
            break;
            
        case 'DELETE':
            // Deletar artigo
            $itemId = $_GET['id'] ?? null;
            
            if (!$itemId) {
                sendJsonResponse([
                    'success' => false,
                    'message' => 'ID do artigo é obrigatório'
                ], 400);
            }
            
            // Verificar se artigo existe
            $checkStmt = $db->prepare("SELECT id FROM items WHERE id = :id");
            $checkStmt->execute(['id' => $itemId]);
            if (!$checkStmt->fetch()) {
                sendJsonResponse([
                    'success' => false,
                    'message' => 'Artigo não encontrado'
                ], 404);
            }
            
            $stmt = $db->prepare("DELETE FROM items WHERE id = :id");
            $stmt->execute(['id' => $itemId]);
            
            sendJsonResponse([
                'success' => true,
                'message' => 'Artigo eliminado com sucesso'
            ]);
            break;
            
        default:
            sendJsonResponse([
                'success' => false,
                'message' => 'Método não permitido'
            ], 405);
    }
} catch (PDOException $e) {
    $errorCode = $e->getCode();
    $errorMessage = $e->getMessage();
    
    // Detectar erros específicos do MySQL
    if ($errorCode == 23000) { // Integrity constraint violation
        if (strpos($errorMessage, 'Duplicate entry') !== false) {
            if (strpos($errorMessage, 'barcode') !== false) {
                sendJsonResponse([
                    'success' => false,
                    'message' => 'Código de barras já existe'
                ], 409);
            }
        } elseif (strpos($errorMessage, 'FOREIGN KEY') !== false) {
            if (strpos($errorMessage, 'category_id') !== false) {
                sendJsonResponse([
                    'success' => false,
                    'message' => 'Categoria não encontrada ou inválida'
                ], 404);
            }
        }
    }
    
    error_log("Items API error: " . $errorMessage . " (Code: " . $errorCode . ")");
    sendJsonResponse([
        'success' => false,
        'message' => 'Erro ao processar pedido: ' . $errorMessage
    ], 500);
} catch (Exception $e) {
    error_log("Items API general error: " . $e->getMessage());
    sendJsonResponse([
        'success' => false,
        'message' => 'Erro ao processar pedido: ' . $e->getMessage()
    ], 500);
}

