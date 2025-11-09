<?php
/**
 * InventoX - Statistics API
 * Endpoint para estatísticas e relatórios do sistema
 */

require_once __DIR__ . '/db.php';

// Verificar autenticação (requireAuth já inicia a sessão se necessário)
requireAuth();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendJsonResponse([
        'success' => false,
        'message' => 'Método não permitido'
    ], 405);
}

try {
    $db = getDB();
    
    // Estatísticas gerais
    $stats = [];
    
    // Total de artigos
    $stmt = $db->query("SELECT COUNT(*) as total FROM items");
    $stats['total_items'] = intval($stmt->fetch()['total']);
    
    // Artigos com stock baixo (verificar se coluna existe)
    try {
        $checkColumn = $db->query("SHOW COLUMNS FROM items LIKE 'min_quantity'");
        if ($checkColumn->rowCount() > 0) {
            $stmt = $db->query("SELECT COUNT(*) as total FROM items WHERE quantity <= min_quantity");
            $stats['low_stock_items'] = intval($stmt->fetch()['total']);
        } else {
            // Fallback se coluna não existir
            $stats['low_stock_items'] = 0;
        }
    } catch (Exception $e) {
        $stats['low_stock_items'] = 0;
    }
    
    // Total de categorias
    $stmt = $db->query("SELECT COUNT(*) as total FROM categories");
    $stats['total_categories'] = intval($stmt->fetch()['total']);
    
    // Verificar se a tabela inventory_sessions existe
    $checkTable = $db->query("SHOW TABLES LIKE 'inventory_sessions'");
    if ($checkTable->rowCount() > 0) {
        // Sessões abertas
        $stmt = $db->query("SELECT COUNT(*) as total FROM inventory_sessions WHERE status = 'aberta'");
        $stats['open_sessions'] = intval($stmt->fetch()['total']);
        
        // Sessões fechadas
        $stmt = $db->query("SELECT COUNT(*) as total FROM inventory_sessions WHERE status = 'fechada'");
        $stats['closed_sessions'] = intval($stmt->fetch()['total']);
    } else {
        // Tabela não existe, usar valores padrão
        $stats['open_sessions'] = 0;
        $stats['closed_sessions'] = 0;
    }
    
    // Valor total do inventário (soma de quantity * unit_price)
    $stmt = $db->query("SELECT SUM(quantity * unit_price) as total_value FROM items WHERE quantity > 0");
    $result = $stmt->fetch();
    $stats['total_inventory_value'] = floatval($result['total_value'] ?? 0);
    
    // Verificar se a tabela stock_movements existe
    $checkTable = $db->query("SHOW TABLES LIKE 'stock_movements'");
    if ($checkTable->rowCount() > 0) {
        // Movimentos de stock (últimos 30 dias)
        $stmt = $db->query("
            SELECT COUNT(*) as total 
            FROM stock_movements 
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        ");
        $stats['movements_last_30_days'] = intval($stmt->fetch()['total']);
    } else {
        // Tabela não existe, usar valor padrão
        $stats['movements_last_30_days'] = 0;
    }
    
    // Top 5 categorias com mais artigos
    $stmt = $db->query("
        SELECT 
            c.id,
            c.name,
            COUNT(i.id) as items_count
        FROM categories c
        LEFT JOIN items i ON c.id = i.category_id
        GROUP BY c.id
        ORDER BY items_count DESC
        LIMIT 5
    ");
    $stats['top_categories'] = $stmt->fetchAll();
    
    // Artigos com stock mais baixo (verificar se coluna existe)
    try {
        $checkColumn = $db->query("SHOW COLUMNS FROM items LIKE 'min_quantity'");
        if ($checkColumn->rowCount() > 0) {
            $stmt = $db->query("
                SELECT 
                    id,
                    barcode,
                    name,
                    quantity,
                    min_quantity,
                    (min_quantity - quantity) as shortage
                FROM items
                WHERE quantity <= min_quantity
                ORDER BY (min_quantity - quantity) DESC
                LIMIT 10
            ");
            $stats['low_stock_list'] = $stmt->fetchAll();
        } else {
            // Fallback se coluna não existir
            $stats['low_stock_list'] = [];
        }
    } catch (Exception $e) {
        $stats['low_stock_list'] = [];
    }
    
    // Últimas sessões de inventário (verificar se tabela existe)
    $checkTable = $db->query("SHOW TABLES LIKE 'inventory_sessions'");
    if ($checkTable->rowCount() > 0) {
        $stmt = $db->query("
            SELECT 
                s.*,
                u.username as created_by,
                COUNT(c.id) as total_counts
            FROM inventory_sessions s
            LEFT JOIN users u ON s.user_id = u.id
            LEFT JOIN inventory_counts c ON s.id = c.session_id
            GROUP BY s.id
            ORDER BY s.started_at DESC
            LIMIT 5
        ");
        $stats['recent_sessions'] = $stmt->fetchAll();
    } else {
        // Tabela não existe, usar array vazio
        $stats['recent_sessions'] = [];
    }
    
    sendJsonResponse([
        'success' => true,
        'stats' => $stats
    ]);
    
} catch (PDOException $e) {
    error_log("Stats API error: " . $e->getMessage());
    error_log("Stats API error trace: " . $e->getTraceAsString());
    sendJsonResponse([
        'success' => false,
        'message' => 'Erro ao obter estatísticas: ' . $e->getMessage()
    ], 500);
} catch (Exception $e) {
    error_log("Stats API general error: " . $e->getMessage());
    sendJsonResponse([
        'success' => false,
        'message' => 'Erro ao obter estatísticas: ' . $e->getMessage()
    ], 500);
}

