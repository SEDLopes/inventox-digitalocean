<?php
/**
 * InventoX - Export Reports API
 * Endpoint para exportação de relatórios (CSV, Excel, JSON)
 */

require_once __DIR__ . '/db.php';

requireAuth();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendJsonResponse([
        'success' => false,
        'message' => 'Método não permitido'
    ], 405);
}

$reportType = strtolower($_GET['type'] ?? 'items'); // items, stock_low, movements, sessions
$format = strtolower($_GET['format'] ?? 'csv'); // csv, json

try {
    $db = getDB();
    
    $data = [];
    $filename = '';
    
    switch ($reportType) {
        case 'items':
            // Relatório de todos os artigos
            $stmt = $db->prepare("
                SELECT 
                    i.id,
                    i.barcode,
                    i.name,
                    i.description,
                    c.name as category_name,
                    i.quantity,
                    i.min_quantity,
                    i.unit_price,
                    i.location,
                    i.supplier,
                    i.created_at,
                    i.updated_at,
                    CASE 
                        WHEN i.quantity <= i.min_quantity THEN 'Stock Baixo'
                        WHEN i.quantity = 0 THEN 'Sem Stock'
                        ELSE 'Normal'
                    END as status
                FROM items i
                LEFT JOIN categories c ON i.category_id = c.id
                ORDER BY i.name
            ");
            $stmt->execute();
            $data = $stmt->fetchAll();
            $filename = 'relatorio_artigos_' . date('Y-m-d');
            $headers = ['ID', 'Código de Barras', 'Nome', 'Descrição', 'Categoria', 
                       'Quantidade', 'Qtd. Mínima', 'Preço Unitário', 'Localização', 
                       'Fornecedor', 'Status', 'Criado em', 'Atualizado em'];
            break;
            
        case 'stock_low':
            // Relatório de artigos com stock baixo
            $stmt = $db->prepare("
                SELECT 
                    i.id,
                    i.barcode,
                    i.name,
                    i.description,
                    c.name as category_name,
                    i.quantity,
                    i.min_quantity,
                    (i.min_quantity - i.quantity) as shortage,
                    i.unit_price,
                    i.location,
                    i.supplier
                FROM items i
                LEFT JOIN categories c ON i.category_id = c.id
                WHERE i.quantity <= i.min_quantity
                ORDER BY (i.min_quantity - i.quantity) DESC
            ");
            $stmt->execute();
            $data = $stmt->fetchAll();
            $filename = 'relatorio_stock_baixo_' . date('Y-m-d');
            $headers = ['ID', 'Código de Barras', 'Nome', 'Descrição', 'Categoria', 
                       'Quantidade', 'Qtd. Mínima', 'Falta', 'Preço Unitário', 
                       'Localização', 'Fornecedor'];
            break;
            
        case 'movements':
            // Relatório de movimentações de stock
            $dateFrom = $_GET['date_from'] ?? null;
            $dateTo = $_GET['date_to'] ?? null;
            $movementType = $_GET['movement_type'] ?? null;
            
            $query = "
                SELECT 
                    sm.id,
                    sm.created_at,
                    i.barcode,
                    i.name as item_name,
                    sm.movement_type,
                    sm.quantity,
                    sm.reason,
                    u.username as user_name
                FROM stock_movements sm
                INNER JOIN items i ON sm.item_id = i.id
                LEFT JOIN users u ON sm.user_id = u.id
                WHERE 1=1
            ";
            $params = [];
            
            if ($dateFrom) {
                $query .= " AND DATE(sm.created_at) >= :date_from";
                $params['date_from'] = $dateFrom;
            }
            if ($dateTo) {
                $query .= " AND DATE(sm.created_at) <= :date_to";
                $params['date_to'] = $dateTo;
            }
            if ($movementType && in_array($movementType, ['entrada', 'saida', 'ajuste', 'transferencia'])) {
                $query .= " AND sm.movement_type = :movement_type";
                $params['movement_type'] = $movementType;
            }
            
            $query .= " ORDER BY sm.created_at DESC";
            
            $stmt = $db->prepare($query);
            $stmt->execute($params);
            $data = $stmt->fetchAll();
            
            // Adicionar labels
            foreach ($data as &$row) {
                $typeLabels = [
                    'entrada' => 'Entrada',
                    'saida' => 'Saída',
                    'ajuste' => 'Ajuste',
                    'transferencia' => 'Transferência'
                ];
                $row['movement_type_label'] = $typeLabels[$row['movement_type']] ?? $row['movement_type'];
            }
            
            $filename = 'relatorio_movimentacoes_' . date('Y-m-d');
            $headers = ['ID', 'Data', 'Código de Barras', 'Nome do Artigo', 
                       'Tipo de Movimento', 'Quantidade', 'Motivo', 'Utilizador'];
            break;
            
        case 'sessions':
            // Relatório de sessões de inventário
            $status = $_GET['status'] ?? null;
            
            $query = "
                SELECT 
                    s.id,
                    s.name,
                    s.description,
                    s.status,
                    s.started_at,
                    s.finished_at,
                    u.username as user_name,
                    COUNT(DISTINCT c.id) as items_counted,
                    SUM(CASE WHEN c.difference != 0 THEN 1 ELSE 0 END) as discrepancies_count
                FROM inventory_sessions s
                LEFT JOIN users u ON s.user_id = u.id
                LEFT JOIN inventory_counts c ON s.id = c.session_id
                WHERE 1=1
            ";
            $params = [];
            
            if ($status && in_array($status, ['aberta', 'fechada', 'cancelada'])) {
                $query .= " AND s.status = :status";
                $params['status'] = $status;
            }
            
            $query .= " GROUP BY s.id ORDER BY s.started_at DESC";
            
            $stmt = $db->prepare($query);
            $stmt->execute($params);
            $data = $stmt->fetchAll();
            
            $filename = 'relatorio_sessoes_' . date('Y-m-d');
            $headers = ['ID', 'Nome', 'Descrição', 'Status', 'Iniciada em', 
                       'Finalizada em', 'Utilizador', 'Artigos Contados', 'Divergências'];
            break;
            
        default:
            sendJsonResponse([
                'success' => false,
                'message' => 'Tipo de relatório inválido'
            ], 400);
    }
    
    if ($format === 'csv') {
        // Exportar como CSV
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
        
        // BOM para UTF-8 (Excel compatibility)
        echo "\xEF\xBB\xBF";
        
        // Cabeçalhos
        echo implode(';', $headers) . "\n";
        
        // Dados
        foreach ($data as $row) {
            $csvRow = [];
            foreach ($headers as $header) {
                // Mapear cabeçalho para campo
                $fieldMap = [
                    'ID' => 'id',
                    'Código de Barras' => 'barcode',
                    'Nome' => 'name',
                    'Nome do Artigo' => 'item_name',
                    'Descrição' => 'description',
                    'Categoria' => 'category_name',
                    'Quantidade' => 'quantity',
                    'Qtd. Mínima' => 'min_quantity',
                    'Falta' => 'shortage',
                    'Preço Unitário' => 'unit_price',
                    'Localização' => 'location',
                    'Fornecedor' => 'supplier',
                    'Status' => 'status',
                    'Criado em' => 'created_at',
                    'Atualizado em' => 'updated_at',
                    'Data' => 'created_at',
                    'Tipo de Movimento' => 'movement_type_label',
                    'Motivo' => 'reason',
                    'Utilizador' => 'user_name',
                    'Iniciada em' => 'started_at',
                    'Finalizada em' => 'finished_at',
                    'Artigos Contados' => 'items_counted',
                    'Divergências' => 'discrepancies_count'
                ];
                
                $field = $fieldMap[$header] ?? strtolower(str_replace(' ', '_', $header));
                $value = $row[$field] ?? '';
                
                // Formatar datas
                if (in_array($header, ['Criado em', 'Atualizado em', 'Data', 'Iniciada em', 'Finalizada em']) && $value) {
                    $value = date('d/m/Y H:i', strtotime($value));
                }
                
                // Formatar preços
                if ($header === 'Preço Unitário' && $value) {
                    $value = number_format($value, 2, ',', '.') . ' €';
                }
                
                $csvRow[] = $value;
            }
            
            // Escapar ponto e vírgula e quebras de linha
            $csvRow = array_map(function($field) {
                return '"' . str_replace('"', '""', $field) . '"';
            }, $csvRow);
            echo implode(';', $csvRow) . "\n";
        }
        exit;
    } else {
        // Exportar como JSON
        sendJsonResponse([
            'success' => true,
            'report_type' => $reportType,
            'data' => $data,
            'total' => count($data)
        ]);
    }
    
} catch (PDOException $e) {
    error_log("Export Reports error: " . $e->getMessage());
    sendJsonResponse([
        'success' => false,
        'message' => 'Erro ao exportar relatório'
    ], 500);
} catch (Exception $e) {
    error_log("Export Reports error: " . $e->getMessage());
    sendJsonResponse([
        'success' => false,
        'message' => 'Erro ao exportar relatório'
    ], 500);
}

