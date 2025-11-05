<?php
/**
 * InventoX - Categories API
 * Endpoint CRUD para gestão de categorias
 */

require_once __DIR__ . '/db.php';

// Verificar autenticação (requireAuth já inicia a sessão se necessário)
requireAuth();

$method = $_SERVER['REQUEST_METHOD'];
$db = getDB();

try {
    switch ($method) {
        case 'GET':
            // Listar categorias ou obter uma específica
            $categoryId = $_GET['id'] ?? null;
            
            if ($categoryId) {
                // Obter categoria específica
                $stmt = $db->prepare("
                    SELECT 
                        c.*,
                        COUNT(i.id) as items_count
                    FROM categories c
                    LEFT JOIN items i ON c.id = i.category_id
                    WHERE c.id = :id
                    GROUP BY c.id
                ");
                $stmt->execute(['id' => $categoryId]);
                $category = $stmt->fetch();
                
                if (!$category) {
                    sendJsonResponse([
                        'success' => false,
                        'message' => 'Categoria não encontrada'
                    ], 404);
                }
                
                sendJsonResponse([
                    'success' => true,
                    'category' => $category
                ]);
            } else {
                // Listar todas as categorias
                $stmt = $db->prepare("
                    SELECT 
                        c.*,
                        COUNT(i.id) as items_count
                    FROM categories c
                    LEFT JOIN items i ON c.id = i.category_id
                    GROUP BY c.id
                    ORDER BY c.name ASC
                ");
                $stmt->execute();
                $categories = $stmt->fetchAll();
                
                sendJsonResponse([
                    'success' => true,
                    'categories' => $categories
                ]);
            }
            break;
            
        case 'POST':
            // Criar nova categoria
            $input = json_decode(file_get_contents('php://input'), true);
            
            $name = sanitizeInput($input['name'] ?? '');
            $description = sanitizeInput($input['description'] ?? '');
            
            // Validação
            if (empty($name)) {
                sendJsonResponse([
                    'success' => false,
                    'message' => 'Nome da categoria é obrigatório'
                ], 400);
            }
            
            // Verificar se nome já existe
            $checkStmt = $db->prepare("SELECT id FROM categories WHERE name = :name");
            $checkStmt->execute(['name' => $name]);
            if ($checkStmt->fetch()) {
                sendJsonResponse([
                    'success' => false,
                    'message' => 'Categoria com este nome já existe'
                ], 400);
            }
            
            $stmt = $db->prepare("
                INSERT INTO categories (name, description)
                VALUES (:name, :description)
            ");
            $stmt->execute([
                'name' => $name,
                'description' => $description ?: null
            ]);
            
            $categoryId = $db->lastInsertId();
            
            sendJsonResponse([
                'success' => true,
                'message' => 'Categoria criada com sucesso',
                'category_id' => $categoryId
            ], 201);
            break;
            
        case 'PUT':
            // Atualizar categoria
            $input = json_decode(file_get_contents('php://input'), true);
            $categoryId = $input['id'] ?? null;
            
            if (!$categoryId) {
                sendJsonResponse([
                    'success' => false,
                    'message' => 'ID da categoria é obrigatório'
                ], 400);
            }
            
            // Verificar se categoria existe
            $checkStmt = $db->prepare("SELECT id FROM categories WHERE id = :id");
            $checkStmt->execute(['id' => $categoryId]);
            if (!$checkStmt->fetch()) {
                sendJsonResponse([
                    'success' => false,
                    'message' => 'Categoria não encontrada'
                ], 404);
            }
            
            // Preparar campos para atualização
            $updates = [];
            $params = ['id' => $categoryId];
            
            if (isset($input['name'])) {
                // Verificar se novo nome não existe em outra categoria
                $nameCheck = $db->prepare("SELECT id FROM categories WHERE name = :name AND id != :id");
                $nameCheck->execute(['name' => sanitizeInput($input['name']), 'id' => $categoryId]);
                if ($nameCheck->fetch()) {
                    sendJsonResponse([
                        'success' => false,
                        'message' => 'Categoria com este nome já existe'
                    ], 400);
                }
                
                $updates[] = "name = :name";
                $params['name'] = sanitizeInput($input['name']);
            }
            if (isset($input['description'])) {
                $updates[] = "description = :description";
                $params['description'] = sanitizeInput($input['description']);
            }
            
            if (empty($updates)) {
                sendJsonResponse([
                    'success' => false,
                    'message' => 'Nenhum campo para atualizar'
                ], 400);
            }
            
            $updates[] = "updated_at = CURRENT_TIMESTAMP";
            
            $stmt = $db->prepare("
                UPDATE categories 
                SET " . implode(', ', $updates) . "
                WHERE id = :id
            ");
            $stmt->execute($params);
            
            sendJsonResponse([
                'success' => true,
                'message' => 'Categoria atualizada com sucesso'
            ]);
            break;
            
        case 'DELETE':
            // Deletar categoria (apenas se não tiver itens)
            $categoryId = $_GET['id'] ?? null;
            
            if (!$categoryId) {
                sendJsonResponse([
                    'success' => false,
                    'message' => 'ID da categoria é obrigatório'
                ], 400);
            }
            
            // Verificar se categoria existe
            $checkStmt = $db->prepare("SELECT id FROM categories WHERE id = :id");
            $checkStmt->execute(['id' => $categoryId]);
            if (!$checkStmt->fetch()) {
                sendJsonResponse([
                    'success' => false,
                    'message' => 'Categoria não encontrada'
                ], 404);
            }
            
            // Verificar se tem itens associados
            $itemsStmt = $db->prepare("SELECT COUNT(*) as count FROM items WHERE category_id = :id");
            $itemsStmt->execute(['id' => $categoryId]);
            $itemsCount = $itemsStmt->fetch()['count'];
            
            if ($itemsCount > 0) {
                sendJsonResponse([
                    'success' => false,
                    'message' => "Não é possível eliminar categoria com {$itemsCount} artigo(s) associado(s)"
                ], 400);
            }
            
            $stmt = $db->prepare("DELETE FROM categories WHERE id = :id");
            $stmt->execute(['id' => $categoryId]);
            
            sendJsonResponse([
                'success' => true,
                'message' => 'Categoria eliminada com sucesso'
            ]);
            break;
            
        default:
            sendJsonResponse([
                'success' => false,
                'message' => 'Método não permitido'
            ], 405);
    }
} catch (PDOException $e) {
    error_log("Categories API error: " . $e->getMessage());
    sendJsonResponse([
        'success' => false,
        'message' => 'Erro ao processar pedido'
    ], 500);
}

