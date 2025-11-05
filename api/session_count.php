<?php
/**
 * InventoX - Session Count API
 * Endpoints para gestão de contagens de inventário
 */

require_once __DIR__ . '/db.php';

// Verificar autenticação (requireAuth já inicia a sessão se necessário)
requireAuth();

$method = $_SERVER['REQUEST_METHOD'];
$db = getDB();

try {
    switch ($method) {
        case 'GET':
            // Listar sessões ou obter uma específica
            $sessionId = $_GET['id'] ?? null;
            
            if ($sessionId) {
                // Obter sessão específica com contagens, empresa e armazém
                $stmt = $db->prepare("
                    SELECT 
                        s.*,
                        u.username as created_by,
                        c.name as company_name,
                        c.code as company_code,
                        w.name as warehouse_name,
                        w.code as warehouse_code,
                        COUNT(co.id) as total_counts,
                        COUNT(CASE WHEN co.difference != 0 THEN 1 END) as discrepancies
                    FROM inventory_sessions s
                    LEFT JOIN users u ON s.user_id = u.id
                    LEFT JOIN companies c ON s.company_id = c.id
                    LEFT JOIN warehouses w ON s.warehouse_id = w.id
                    LEFT JOIN inventory_counts co ON s.id = co.session_id
                    WHERE s.id = :id
                    GROUP BY s.id
                ");
                $stmt->execute(['id' => $sessionId]);
                $session = $stmt->fetch();

                if (!$session) {
                    sendJsonResponse([
                        'success' => false,
                        'message' => 'Sessão não encontrada'
                    ], 404);
                }

                // Obter contagens da sessão
                $stmt = $db->prepare("
                    SELECT 
                        c.*,
                        i.barcode,
                        i.name as item_name,
                        i.quantity as current_quantity
                    FROM inventory_counts c
                    INNER JOIN items i ON c.item_id = i.id
                    WHERE c.session_id = :session_id
                    ORDER BY c.counted_at DESC
                ");
                $stmt->execute(['session_id' => $sessionId]);
                $counts = $stmt->fetchAll();

                $session['counts'] = $counts;

                sendJsonResponse([
                    'success' => true,
                    'session' => $session
                ]);
            } else {
                // Listar todas as sessões com empresa e armazém
                $stmt = $db->prepare("
                    SELECT 
                        s.*,
                        u.username as created_by,
                        c.name as company_name,
                        c.code as company_code,
                        w.name as warehouse_name,
                        w.code as warehouse_code,
                        COUNT(co.id) as total_counts
                    FROM inventory_sessions s
                    LEFT JOIN users u ON s.user_id = u.id
                    LEFT JOIN companies c ON s.company_id = c.id
                    LEFT JOIN warehouses w ON s.warehouse_id = w.id
                    LEFT JOIN inventory_counts co ON s.id = co.session_id
                    GROUP BY s.id
                    ORDER BY s.started_at DESC
                ");
                $stmt->execute();
                $sessions = $stmt->fetchAll();

                sendJsonResponse([
                    'success' => true,
                    'sessions' => $sessions
                ]);
            }
            break;

        case 'POST':
            // Criar nova sessão ou adicionar contagem
            $input = json_decode(file_get_contents('php://input'), true);

            if (isset($input['name'])) {
                // Criar nova sessão
                $name = sanitizeInput($input['name']);
                $description = sanitizeInput($input['description'] ?? '');
                $companyId = intval($input['company_id'] ?? 0);
                $warehouseId = intval($input['warehouse_id'] ?? 0);

                if (empty($name)) {
                    sendJsonResponse([
                        'success' => false,
                        'message' => 'Nome da sessão é obrigatório'
                    ], 400);
                }

                if (!$companyId || !$warehouseId) {
                    sendJsonResponse([
                        'success' => false,
                        'message' => 'Empresa e armazém são obrigatórios'
                    ], 400);
                }

                // Verificar se empresa existe
                $checkCompany = $db->prepare("SELECT id FROM companies WHERE id = :id AND is_active = 1");
                $checkCompany->execute(['id' => $companyId]);
                if (!$checkCompany->fetch()) {
                    sendJsonResponse([
                        'success' => false,
                        'message' => 'Empresa não encontrada ou inativa'
                    ], 404);
                }

                // Verificar se armazém existe e pertence à empresa
                $checkWarehouse = $db->prepare("SELECT id FROM warehouses WHERE id = :id AND company_id = :company_id AND is_active = 1");
                $checkWarehouse->execute(['id' => $warehouseId, 'company_id' => $companyId]);
                if (!$checkWarehouse->fetch()) {
                    sendJsonResponse([
                        'success' => false,
                        'message' => 'Armazém não encontrado, inativo ou não pertence à empresa selecionada'
                    ], 404);
                }

                $stmt = $db->prepare("
                    INSERT INTO inventory_sessions (name, description, company_id, warehouse_id, user_id, status)
                    VALUES (:name, :description, :company_id, :warehouse_id, :user_id, 'aberta')
                ");
                $stmt->execute([
                    'name' => $name,
                    'description' => $description,
                    'company_id' => $companyId,
                    'warehouse_id' => $warehouseId,
                    'user_id' => $_SESSION['user_id']
                ]);

                $sessionId = $db->lastInsertId();

                sendJsonResponse([
                    'success' => true,
                    'message' => 'Sessão criada com sucesso',
                    'session_id' => $sessionId
                ], 201);
            } else {
                // Adicionar contagem
                $sessionId = $input['session_id'] ?? null;
                $barcode = sanitizeInput($input['barcode'] ?? '');
                $countedQuantity = intval($input['counted_quantity'] ?? 0);

                if (!$sessionId || empty($barcode)) {
                    sendJsonResponse([
                        'success' => false,
                        'message' => 'ID da sessão e código de barras são obrigatórios'
                    ], 400);
                }

                // Buscar item pelo barcode
                $stmt = $db->prepare("SELECT id, quantity FROM items WHERE barcode = :barcode");
                $stmt->execute(['barcode' => $barcode]);
                $item = $stmt->fetch();

                if (!$item) {
                    sendJsonResponse([
                        'success' => false,
                        'message' => 'Artigo não encontrado'
                    ], 404);
                }

                $expectedQuantity = $item['quantity'];
                $difference = $countedQuantity - $expectedQuantity;

                // Inserir ou atualizar contagem
                $stmt = $db->prepare("
                    INSERT INTO inventory_counts 
                        (session_id, item_id, counted_quantity, expected_quantity, difference, notes)
                    VALUES 
                        (:session_id, :item_id, :counted_quantity, :expected_quantity, :difference, :notes)
                    ON DUPLICATE KEY UPDATE
                        counted_quantity = :counted_quantity2,
                        expected_quantity = :expected_quantity2,
                        difference = :difference2,
                        notes = :notes2,
                        counted_at = CURRENT_TIMESTAMP
                ");
                $stmt->execute([
                    'session_id' => $sessionId,
                    'item_id' => $item['id'],
                    'counted_quantity' => $countedQuantity,
                    'expected_quantity' => $expectedQuantity,
                    'difference' => $difference,
                    'notes' => sanitizeInput($input['notes'] ?? ''),
                    'counted_quantity2' => $countedQuantity,
                    'expected_quantity2' => $expectedQuantity,
                    'difference2' => $difference,
                    'notes2' => sanitizeInput($input['notes'] ?? '')
                ]);

                sendJsonResponse([
                    'success' => true,
                    'message' => 'Contagem registada com sucesso',
                    'count' => [
                        'item_id' => $item['id'],
                        'counted_quantity' => $countedQuantity,
                        'expected_quantity' => $expectedQuantity,
                        'difference' => $difference
                    ]
                ], 201);
            }
            break;

        case 'PUT':
            // Atualizar sessão (fechar, cancelar, etc.)
            $input = json_decode(file_get_contents('php://input'), true);
            $sessionId = $input['session_id'] ?? null;
            $status = sanitizeInput($input['status'] ?? '');

            if (!$sessionId || !in_array($status, ['aberta', 'fechada', 'cancelada'])) {
                sendJsonResponse([
                    'success' => false,
                    'message' => 'ID da sessão e status válido são obrigatórios'
                ], 400);
            }

            $finishedAt = ($status === 'fechada' || $status === 'cancelada') 
                ? date('Y-m-d H:i:s') 
                : null;

            $stmt = $db->prepare("
                UPDATE inventory_sessions 
                SET status = :status, finished_at = :finished_at
                WHERE id = :id
            ");
            $stmt->execute([
                'id' => $sessionId,
                'status' => $status,
                'finished_at' => $finishedAt
            ]);

            sendJsonResponse([
                'success' => true,
                'message' => 'Sessão atualizada com sucesso'
            ]);
            break;

        default:
            sendJsonResponse([
                'success' => false,
                'message' => 'Método não permitido'
            ], 405);
    }
} catch (PDOException $e) {
    error_log("Session count error: " . $e->getMessage());
    sendJsonResponse([
        'success' => false,
        'message' => 'Erro ao processar pedido'
    ], 500);
}

