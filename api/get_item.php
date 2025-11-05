<?php
/**
 * InventoX - Get Item API
 * Endpoint para buscar artigo por código de barras
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

$barcode = sanitizeInput($_GET['barcode'] ?? '');

if (empty($barcode)) {
    sendJsonResponse([
        'success' => false,
        'message' => 'Código de barras é obrigatório'
    ], 400);
}

try {
    $db = getDB();
    
    // Buscar artigo com categoria - primeiro por barcode exato
    $stmt = $db->prepare("
        SELECT 
            i.*,
            c.name as category_name,
            'barcode' as match_type
        FROM items i
        LEFT JOIN categories c ON i.category_id = c.id
        WHERE i.barcode = :barcode
    ");
    $stmt->execute(['barcode' => $barcode]);
    $item = $stmt->fetch();

    // Se não encontrou por barcode, tentar por código de referência (busca parcial)
    if (!$item) {
        // Buscar por barcode que contenha o código (para códigos de referência)
        $stmt = $db->prepare("
            SELECT 
                i.*,
                c.name as category_name,
                'reference' as match_type
            FROM items i
            LEFT JOIN categories c ON i.category_id = c.id
            WHERE i.barcode LIKE :barcode_pattern
               OR i.name LIKE :name_pattern
            ORDER BY 
                CASE 
                    WHEN i.barcode = :exact_barcode THEN 1
                    WHEN i.barcode LIKE :barcode_start THEN 2
                    WHEN i.name LIKE :name_start THEN 3
                    ELSE 4
                END
            LIMIT 1
        ");
        
        $searchPattern = '%' . $barcode . '%';
        $startPattern = $barcode . '%';
        
        $stmt->execute([
            'barcode_pattern' => $searchPattern,
            'name_pattern' => $searchPattern,
            'exact_barcode' => $barcode,
            'barcode_start' => $startPattern,
            'name_start' => $startPattern
        ]);
        $item = $stmt->fetch();
    }

    if (!$item) {
        sendJsonResponse([
            'success' => false,
            'message' => 'Artigo não encontrado. Verifique o código de barras ou código de referência.'
        ], 404);
    }

    sendJsonResponse([
        'success' => true,
        'item' => $item
    ]);

} catch (PDOException $e) {
    error_log("Get item error: " . $e->getMessage());
    sendJsonResponse([
        'success' => false,
        'message' => 'Erro ao buscar artigo'
    ], 500);
}

