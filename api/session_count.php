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
    // Verificar se as tabelas necessárias existem
    $checkTable = $db->query("SHOW TABLES LIKE 'inventory_sessions'");
    $hasInventorySessions = $checkTable->rowCount() > 0;
    
    $checkTable = $db->query("SHOW TABLES LIKE 'inventory_counts'");
    $hasInventoryCounts = $checkTable->rowCount() > 0;
    
    if (!$hasInventorySessions) {
        // Tabela não existe, retornar vazio para GET ou erro para POST/PUT
        if ($method === 'GET') {
            sendJsonResponse([
                'success' => true,
                'sessions' => [],
                'session' => null
            ]);
        } else {
            sendJsonResponse([
                'success' => false,
                'message' => 'Tabela inventory_sessions não existe. Execute a migração da base de dados.'
            ], 500);
        }
    }
    
    switch ($method) {
        case 'GET':
            // Listar sessões ou obter uma específica
            $sessionId = $_GET['id'] ?? null;
            
            if ($sessionId) {
                // Obter sessão específica com contagens, empresa e armazém
                // Verificar quais colunas existem antes de fazer SELECT
                $checkColumns = $db->query("SHOW COLUMNS FROM companies");
                $companyColumns = $checkColumns->fetchAll(PDO::FETCH_COLUMN);
                $hasCompanyCode = in_array('code', $companyColumns);
                
                $checkColumns = $db->query("SHOW COLUMNS FROM warehouses");
                $warehouseColumns = $checkColumns->fetchAll(PDO::FETCH_COLUMN);
                $hasWarehouseCode = in_array('code', $warehouseColumns);
                
                $selectFields = [
                    's.*',
                    'u.username as created_by',
                    'c.name as company_name'
                ];
                if ($hasCompanyCode) {
                    $selectFields[] = 'c.code as company_code';
                }
                $selectFields[] = 'w.name as warehouse_name';
                if ($hasWarehouseCode) {
                    $selectFields[] = 'w.code as warehouse_code';
                }
                $selectFields[] = 'COUNT(co.id) as total_counts';
                $selectFields[] = 'COUNT(CASE WHEN co.difference != 0 THEN 1 END) as discrepancies';
                
                $stmt = $db->prepare("
                    SELECT " . implode(', ', $selectFields) . "
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

                // Obter contagens da sessão (verificar se tabela existe)
                $counts = [];
                if ($hasInventoryCounts) {
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
                }

                $session['counts'] = $counts;

                sendJsonResponse([
                    'success' => true,
                    'session' => $session
                ]);
            } else {
                // Listar todas as sessões com empresa e armazém
                // Verificar quais colunas existem antes de fazer SELECT
                $checkColumns = $db->query("SHOW COLUMNS FROM companies");
                $companyColumns = $checkColumns->fetchAll(PDO::FETCH_COLUMN);
                $hasCompanyCode = in_array('code', $companyColumns);
                
                $checkColumns = $db->query("SHOW COLUMNS FROM warehouses");
                $warehouseColumns = $checkColumns->fetchAll(PDO::FETCH_COLUMN);
                $hasWarehouseCode = in_array('code', $warehouseColumns);
                
                $selectFields = [
                    's.*',
                    'u.username as created_by',
                    'c.name as company_name'
                ];
                if ($hasCompanyCode) {
                    $selectFields[] = 'c.code as company_code';
                }
                $selectFields[] = 'w.name as warehouse_name';
                if ($hasWarehouseCode) {
                    $selectFields[] = 'w.code as warehouse_code';
                }
                if ($hasInventoryCounts) {
                    $selectFields[] = 'COUNT(co.id) as total_counts';
                } else {
                    $selectFields[] = '0 as total_counts';
                }
                
                $query = "
                    SELECT " . implode(', ', $selectFields) . "
                    FROM inventory_sessions s
                    LEFT JOIN users u ON s.user_id = u.id
                    LEFT JOIN companies c ON s.company_id = c.id
                    LEFT JOIN warehouses w ON s.warehouse_id = w.id
                ";
                if ($hasInventoryCounts) {
                    $query .= "LEFT JOIN inventory_counts co ON s.id = co.session_id";
                }
                $query .= " GROUP BY s.id ORDER BY s.started_at DESC";
                
                $stmt = $db->prepare($query);
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
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                error_log("Session count POST - JSON decode error: " . json_last_error_msg());
                sendJsonResponse([
                    'success' => false,
                    'message' => 'Erro ao processar dados: ' . json_last_error_msg()
                ], 400);
            }

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
                $company = $checkCompany->fetch();
                if (!$company) {
                    sendJsonResponse([
                        'success' => false,
                        'message' => 'Empresa não encontrada ou inativa'
                    ], 404);
                }

                // Verificar se armazém existe e pertence à empresa
                $checkWarehouse = $db->prepare("SELECT id FROM warehouses WHERE id = :id AND company_id = :company_id AND is_active = 1");
                $checkWarehouse->execute(['id' => $warehouseId, 'company_id' => $companyId]);
                $warehouse = $checkWarehouse->fetch();
                if (!$warehouse) {
                    sendJsonResponse([
                        'success' => false,
                        'message' => 'Armazém não encontrado, inativo ou não pertence à empresa selecionada'
                    ], 404);
                }

                $userId = $_SESSION['user_id'] ?? null;
                if (!$userId) {
                    sendJsonResponse([
                        'success' => false,
                        'message' => 'Sessão de utilizador inválida. Por favor, faça login novamente.'
                    ], 401);
                }

                try {
                    $stmt = $db->prepare("
                        INSERT INTO inventory_sessions (name, description, company_id, warehouse_id, user_id, status)
                        VALUES (:name, :description, :company_id, :warehouse_id, :user_id, 'aberta')
                    ");
                    $stmt->execute([
                        'name' => $name,
                        'description' => !empty($description) ? $description : null,
                        'company_id' => $companyId,
                        'warehouse_id' => $warehouseId,
                        'user_id' => $userId
                    ]);

                    $sessionId = $db->lastInsertId();

                    if (!$sessionId) {
                        throw new Exception('Falha ao obter ID da sessão criada');
                    }

                    sendJsonResponse([
                        'success' => true,
                        'message' => 'Sessão criada com sucesso',
                        'session_id' => $sessionId
                    ], 201);
                } catch (PDOException $e) {
                    $errorCode = $e->getCode();
                    $errorMessage = $e->getMessage();
                    
                    // Detectar erros específicos do MySQL
                    if ($errorCode == 23000) { // Integrity constraint violation
                        if (strpos($errorMessage, 'FOREIGN KEY') !== false) {
                            if (strpos($errorMessage, 'company_id') !== false) {
                                sendJsonResponse([
                                    'success' => false,
                                    'message' => 'Empresa não encontrada ou inválida'
                                ], 404);
                            } elseif (strpos($errorMessage, 'warehouse_id') !== false) {
                                sendJsonResponse([
                                    'success' => false,
                                    'message' => 'Armazém não encontrado ou inválido'
                                ], 404);
                            } elseif (strpos($errorMessage, 'user_id') !== false) {
                                sendJsonResponse([
                                    'success' => false,
                                    'message' => 'Utilizador não encontrado ou inválido'
                                ], 404);
                            }
                        }
                    }
                    
                    error_log("Session count POST - PDO error: " . $errorMessage . " (Code: " . $errorCode . ")");
                    sendJsonResponse([
                        'success' => false,
                        'message' => 'Erro ao criar sessão: ' . $errorMessage
                    ], 500);
                } catch (Exception $e) {
                    error_log("Session count POST - General error: " . $e->getMessage());
                    sendJsonResponse([
                        'success' => false,
                        'message' => 'Erro ao criar sessão: ' . $e->getMessage()
                    ], 500);
                }
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

                // Verificar se a tabela inventory_counts existe
                if (!$hasInventoryCounts) {
                    sendJsonResponse([
                        'success' => false,
                        'message' => 'Tabela inventory_counts não existe. Execute a migração da base de dados.'
                    ], 500);
                }
                
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

                // Criar movimento de stock se houver diferença
                if ($difference != 0) {
                    try {
                        $movementType = $difference > 0 ? 'entrada' : 'saida';
                        $movementQuantity = abs($difference);
                        $reason = "Ajuste de inventário - Sessão: " . $sessionId . " (Contado: $countedQuantity, Esperado: $expectedQuantity)";
                        
                        // Verificar se a tabela stock_movements existe
                        $checkStockTable = $db->query("SHOW TABLES LIKE 'stock_movements'");
                        if ($checkStockTable->rowCount() > 0) {
                            $stockStmt = $db->prepare("
                                INSERT INTO stock_movements (item_id, movement_type, quantity, reason, user_id)
                                VALUES (:item_id, :movement_type, :quantity, :reason, :user_id)
                            ");
                            $stockStmt->execute([
                                'item_id' => $item['id'],
                                'movement_type' => $movementType,
                                'quantity' => $difference, // Manter sinal (+ ou -)
                                'reason' => $reason,
                                'user_id' => $userId
                            ]);
                            
                            // Atualizar quantidade do item
                            $updateItemStmt = $db->prepare("
                                UPDATE items SET quantity = :new_quantity WHERE id = :item_id
                            ");
                            $updateItemStmt->execute([
                                'new_quantity' => $countedQuantity,
                                'item_id' => $item['id']
                            ]);
                            
                            error_log("Stock movement created: Item {$item['id']}, Type: $movementType, Quantity: $difference");
                        }
                    } catch (PDOException $e) {
                        error_log("Error creating stock movement: " . $e->getMessage());
                        // Não falhar a contagem por causa do movimento
                    }
                }

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
    $errorCode = $e->getCode();
    $errorMessage = $e->getMessage();
    
    // Detectar erros específicos do MySQL
    if ($errorCode == 23000) { // Integrity constraint violation
        if (strpos($errorMessage, 'FOREIGN KEY') !== false) {
            if (strpos($errorMessage, 'company_id') !== false) {
                sendJsonResponse([
                    'success' => false,
                    'message' => 'Empresa não encontrada ou inválida'
                ], 404);
            } elseif (strpos($errorMessage, 'warehouse_id') !== false) {
                sendJsonResponse([
                    'success' => false,
                    'message' => 'Armazém não encontrado ou inválido'
                ], 404);
            } elseif (strpos($errorMessage, 'user_id') !== false) {
                sendJsonResponse([
                    'success' => false,
                    'message' => 'Utilizador não encontrado ou inválido'
                ], 404);
            }
        }
    }
    
    error_log("Session count error: " . $errorMessage . " (Code: " . $errorCode . ")");
    sendJsonResponse([
        'success' => false,
        'message' => 'Erro ao processar pedido: ' . $errorMessage
    ], 500);
} catch (Exception $e) {
    error_log("Session count general error: " . $e->getMessage());
    sendJsonResponse([
        'success' => false,
        'message' => 'Erro ao processar pedido: ' . $e->getMessage()
    ], 500);
}

