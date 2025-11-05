<?php
/**
 * InventoX - Export Session API
 * Endpoint para exportação de sessões de inventário (CSV/JSON)
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

$sessionId = $_GET['id'] ?? null;
$format = strtolower($_GET['format'] ?? 'csv'); // csv, json, excel

if (!$sessionId) {
    sendJsonResponse([
        'success' => false,
        'message' => 'ID da sessão é obrigatório'
    ], 400);
}

try {
    $db = getDB();

    // Buscar sessão
    $stmt = $db->prepare("
        SELECT 
            s.*,
            u.username as created_by
        FROM inventory_sessions s
        LEFT JOIN users u ON s.user_id = u.id
        WHERE s.id = :id
    ");
    $stmt->execute(['id' => $sessionId]);
    $session = $stmt->fetch();

    if (!$session) {
        sendJsonResponse([
            'success' => false,
            'message' => 'Sessão não encontrada'
        ], 404);
    }

    // Buscar contagens
    $stmt = $db->prepare("
        SELECT 
            c.*,
            i.barcode,
            i.name as item_name,
            i.quantity as current_quantity,
            cat.name as category_name
        FROM inventory_counts c
        INNER JOIN items i ON c.item_id = i.id
        LEFT JOIN categories cat ON i.category_id = cat.id
        WHERE c.session_id = :session_id
        ORDER BY i.name
    ");
    $stmt->execute(['session_id' => $sessionId]);
    $counts = $stmt->fetchAll();

    if ($format === 'csv') {
        // Exportar como CSV
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="sessao_' . $sessionId . '_' . date('Y-m-d') . '.csv"');
        
        // BOM para UTF-8 (Excel compatibility)
        echo "\xEF\xBB\xBF";
        
        // Cabeçalhos
        $headers = ['Código de Barras', 'Nome do Artigo', 'Categoria', 
                   'Quantidade Esperada', 'Quantidade Contada', 'Diferença', 'Notas'];
        echo implode(';', $headers) . "\n";
        
        // Dados
        foreach ($counts as $count) {
            $row = [
                $count['barcode'],
                $count['item_name'],
                $count['category_name'] ?? '',
                $count['expected_quantity'],
                $count['counted_quantity'],
                $count['difference'],
                $count['notes'] ?? ''
            ];
            // Escapar ponto e vírgula e quebras de linha
            $row = array_map(function($field) {
                return '"' . str_replace('"', '""', $field) . '"';
            }, $row);
            echo implode(';', $row) . "\n";
        }
        exit;
    } else {
        // Exportar como JSON (padrão)
        sendJsonResponse([
            'success' => true,
            'session' => $session,
            'counts' => $counts,
            'summary' => [
                'total_items' => count($counts),
                'with_discrepancies' => count(array_filter($counts, function($c) {
                    return $c['difference'] != 0;
                })),
                'total_difference' => array_sum(array_column($counts, 'difference'))
            ]
        ]);
    }

} catch (PDOException $e) {
    error_log("Export error: " . $e->getMessage());
    sendJsonResponse([
        'success' => false,
        'message' => 'Erro ao exportar sessão'
    ], 500);
}

