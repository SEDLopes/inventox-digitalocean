<?php
/**
 * InventoX - Stock History API
 * Histórico de movimentações de stock
 */

require_once __DIR__ . '/db.php';

// Verificar autenticação
requireAuth();

$method = $_SERVER['REQUEST_METHOD'];

// Apenas GET permitido
if ($method !== 'GET') {
    sendJsonResponse([
        'success' => false,
        'message' => 'Método não permitido'
    ], 405);
}

try {
    $db = getDB();
    
    // Parâmetros de filtro
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $limit = isset($_GET['limit']) ? max(1, min(100, intval($_GET['limit']))) : 20;
    $offset = ($page - 1) * $limit;
    
    $itemId = isset($_GET['item_id']) ? intval($_GET['item_id']) : null;
    $movementType = isset($_GET['type']) ? sanitizeInput($_GET['type']) : null;
    $dateFrom = isset($_GET['date_from']) ? sanitizeInput($_GET['date_from']) : null;
    $dateTo = isset($_GET['date_to']) ? sanitizeInput($_GET['date_to']) : null;
    
    // Construir query
    $query = "
        SELECT 
            sm.id,
            sm.item_id,
            i.barcode,
            i.name as item_name,
            sm.movement_type,
            sm.quantity,
            sm.reason,
            sm.user_id,
            u.username as user_name,
            sm.created_at
        FROM stock_movements sm
        INNER JOIN items i ON sm.item_id = i.id
        LEFT JOIN users u ON sm.user_id = u.id
        WHERE 1=1
    ";
    
    $params = [];
    
    if ($itemId) {
        $query .= " AND sm.item_id = :item_id";
        $params['item_id'] = $itemId;
    }
    
    if ($movementType && in_array($movementType, ['entrada', 'saida', 'ajuste', 'transferencia'])) {
        $query .= " AND sm.movement_type = :movement_type";
        $params['movement_type'] = $movementType;
    }
    
    if ($dateFrom) {
        $query .= " AND DATE(sm.created_at) >= :date_from";
        $params['date_from'] = $dateFrom;
    }
    
    if ($dateTo) {
        $query .= " AND DATE(sm.created_at) <= :date_to";
        $params['date_to'] = $dateTo;
    }
    
    $query .= " ORDER BY sm.created_at DESC LIMIT :limit OFFSET :offset";
    
    $stmt = $db->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue(":{$key}", $value);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $movements = $stmt->fetchAll();
    
    // Contar total
    $countQuery = "
        SELECT COUNT(*) as total
        FROM stock_movements sm
        WHERE 1=1
    ";
    
    $countParams = [];
    
    if ($itemId) {
        $countQuery .= " AND sm.item_id = :item_id";
        $countParams['item_id'] = $itemId;
    }
    
    if ($movementType) {
        $countQuery .= " AND sm.movement_type = :movement_type";
        $countParams['movement_type'] = $movementType;
    }
    
    if ($dateFrom) {
        $countQuery .= " AND DATE(sm.created_at) >= :date_from";
        $countParams['date_from'] = $dateFrom;
    }
    
    if ($dateTo) {
        $countQuery .= " AND DATE(sm.created_at) <= :date_to";
        $countParams['date_to'] = $dateTo;
    }
    
    $countStmt = $db->prepare($countQuery);
    foreach ($countParams as $key => $value) {
        $countStmt->bindValue(":{$key}", $value);
    }
    $countStmt->execute();
    $total = $countStmt->fetch()['total'];
    
    // Formatar dados
    foreach ($movements as &$movement) {
        // Traduzir tipo de movimento
        $typeLabels = [
            'entrada' => 'Entrada',
            'saida' => 'Saída',
            'ajuste' => 'Ajuste',
            'transferencia' => 'Transferência'
        ];
        $movement['movement_type_label'] = $typeLabels[$movement['movement_type']] ?? $movement['movement_type'];
        
        // Formatar data
        $movement['formatted_date'] = date('d/m/Y H:i', strtotime($movement['created_at']));
    }
    
    sendJsonResponse([
        'success' => true,
        'movements' => $movements,
        'pagination' => [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'pages' => ceil($total / $limit)
        ]
    ]);
    
} catch (Exception $e) {
    error_log("Erro ao obter histórico de movimentações: " . $e->getMessage());
    sendJsonResponse([
        'success' => false,
        'message' => 'Erro ao obter histórico de movimentações'
    ], 500);
}

