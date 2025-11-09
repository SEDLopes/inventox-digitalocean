<?php
/**
 * InventoX - Items Import API
 * Endpoint para importação de artigos (CSV/XLSX)
 */

require_once __DIR__ . '/db.php';

// Verificar autenticação (requireAuth já inicia a sessão se necessário)
requireAuth();

// CSRF protection (exceto para FormData que não envia token facilmente)
// Para uploads, validar por outros meios (autenticação já é suficiente)

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJsonResponse([
        'success' => false,
        'message' => 'Método não permitido'
    ], 405);
}

// Verificar se há ficheiro enviado
if (!isset($_FILES['file'])) {
    sendJsonResponse([
        'success' => false,
        'message' => 'Nenhum ficheiro foi enviado'
    ], 400);
}

if ($_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    $errorMsg = 'Erro desconhecido';
    switch ($_FILES['file']['error']) {
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            $errorMsg = 'Ficheiro demasiado grande';
            break;
        case UPLOAD_ERR_PARTIAL:
            $errorMsg = 'Ficheiro enviado parcialmente';
            break;
        case UPLOAD_ERR_NO_FILE:
            $errorMsg = 'Nenhum ficheiro foi enviado';
            break;
        case UPLOAD_ERR_NO_TMP_DIR:
            $errorMsg = 'Diretório temporário não encontrado';
            break;
        case UPLOAD_ERR_CANT_WRITE:
            $errorMsg = 'Erro ao escrever ficheiro';
            break;
        case UPLOAD_ERR_EXTENSION:
            $errorMsg = 'Extensão bloqueada';
            break;
    }
    error_log("Items import - Upload error: " . $_FILES['file']['error'] . " - $errorMsg");
    sendJsonResponse([
        'success' => false,
        'message' => 'Erro ao enviar ficheiro: ' . $errorMsg
    ], 400);
}

$file = $_FILES['file'];
$fileName = $file['name'];
$fileTmpPath = $file['tmp_name'];
$fileSize = $file['size'];
$fileType = $file['type'];

// Validar tipo de ficheiro
$allowedTypes = ['text/csv', 'application/vnd.ms-excel', 
                 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'];
$allowedExtensions = ['csv', 'xls', 'xlsx'];
$fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

if (!in_array($fileExtension, $allowedExtensions)) {
    sendJsonResponse([
        'success' => false,
        'message' => 'Tipo de ficheiro não permitido. Use CSV ou XLSX.'
    ], 400);
}

// Limitar tamanho do ficheiro (10MB)
$maxSize = 10 * 1024 * 1024;
if ($fileSize > $maxSize) {
    sendJsonResponse([
        'success' => false,
        'message' => 'Ficheiro demasiado grande. Máximo: 10MB'
    ], 400);
}

try {
    // Mover ficheiro para uploads
    $uploadDir = __DIR__ . '/../uploads/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $newFileName = date('Y-m-d_His') . '_' . uniqid() . '.' . $fileExtension;
    $uploadPath = $uploadDir . $newFileName;

    if (!move_uploaded_file($fileTmpPath, $uploadPath)) {
        sendJsonResponse([
            'success' => false,
            'message' => 'Erro ao guardar ficheiro'
        ], 500);
    }

    // Se for CSV, processar diretamente em PHP (fallback mais fiável)
    if ($fileExtension === 'csv') {
        $imported = 0;
        $updated = 0;
        $errors = [];
        $db = getDB();

        $fileHandle = fopen($uploadPath, 'r');
        if ($fileHandle === FALSE) {
            @unlink($uploadPath);
            sendJsonResponse(['success' => false, 'message' => 'Não foi possível abrir o ficheiro CSV.'], 500);
        }

        // Detectar BOM e remover se presente
        $firstLine = fgets($fileHandle);
        if (str_starts_with($firstLine, "\xEF\xBB\xBF")) {
            $firstLine = substr($firstLine, 3);
        }
        rewind($fileHandle); // Voltar ao início do ficheiro

        // Detectar delimitador (vírgula ou ponto e vírgula)
        $delimiter = ',';
        if (str_contains($firstLine, ';') && !str_contains($firstLine, ',')) {
            $delimiter = ';';
        }

        $headers = fgetcsv($fileHandle, 0, $delimiter);
        if ($headers === FALSE) {
            fclose($fileHandle);
            @unlink($uploadPath);
            sendJsonResponse(['success' => false, 'message' => 'Não foi possível ler os cabeçalhos do ficheiro CSV.'], 500);
        }
        $headers = array_map('trim', $headers);
        $headers = array_map('strtolower', $headers);

        // Mapeamento de colunas (incluindo variações com acentos e pontuação)
        $columnMapping = [
            'barcode' => ['barcode', 'codigo_barras', 'código_barras', 'cód._barras', 'cod._barras', 'codigo'],
            'name' => ['name', 'nome', 'artigo', 'produto'],
            'description' => ['description', 'descricao', 'descrição'],
            'category' => ['category', 'categoria'],
            'quantity' => ['quantity', 'quantidade', 'qtd', 'qtd._stock', 'qtd_stock', 'stock'],
            'min_quantity' => ['min_quantity', 'quantidade_minima', 'qtd_minima'],
            'unit_price' => ['unit_price', 'preco_unitario', 'preço_unitario', 'custo_unitário', 'preco', 'preço', 'pvp', 'pvp1'],
            'location' => ['location', 'localizacao', 'localização'],
            'supplier' => ['supplier', 'fornecedor']
        ];

        $mappedHeaders = [];
        foreach ($columnMapping as $dbCol => $possibleHeaders) {
            foreach ($possibleHeaders as $ph) {
                if (in_array($ph, $headers)) {
                    $mappedHeaders[$dbCol] = array_search($ph, $headers);
                    break;
                }
            }
        }

        // Validar colunas obrigatórias
        $requiredCols = ['barcode', 'name'];
        $missingCols = [];
        foreach ($requiredCols as $col) {
            if (!isset($mappedHeaders[$col])) {
                $missingCols[] = $col;
            }
        }
        if (!empty($missingCols)) {
            fclose($fileHandle);
            @unlink($uploadPath);
            sendJsonResponse(['success' => false, 'message' => 'Colunas obrigatórias em falta no CSV: ' . implode(', ', $missingCols)], 400);
        }

        $db = getDB();
        $imported = 0; $updated = 0; $errors = [];

        $lineNum = 1; // Começa em 1 para o header, dados começam na linha 2
        while (($rowData = fgetcsv($fileHandle, 0, $delimiter)) !== FALSE) {
            $lineNum++;
            if (count($rowData) != count($headers)) {
                $errors[] = "Linha {$lineNum}: Número de colunas inconsistente.";
                continue;
            }

            $itemData = [];
            foreach ($mappedHeaders as $dbCol => $colIndex) {
                $itemData[$dbCol] = trim($rowData[$colIndex]);
            }

            try {
                $barcode = $itemData['barcode'];
                $name = $itemData['name'];

                if (empty($barcode) || empty($name)) {
                    $errors[] = "Linha {$lineNum}: Código de barras e nome são obrigatórios.";
                    continue;
                }

                // Processar categoria
                $categoryId = null;
                if (!empty($itemData['category'])) {
                    $stmtCat = $db->prepare("SELECT id FROM categories WHERE name = :name");
                    $stmtCat->execute(['name' => $itemData['category']]);
                    $category = $stmtCat->fetch();

                    if ($category) {
                        $categoryId = $category['id'];
                    } else {
                        // Criar nova categoria
                        $stmtInsertCat = $db->prepare("INSERT INTO categories (name) VALUES (:name)");
                        $stmtInsertCat->execute(['name' => $itemData['category']]);
                        $categoryId = $db->lastInsertId();
                    }
                }

                // Verificar se artigo existe
                $stmtCheckItem = $db->prepare("SELECT id FROM items WHERE barcode = :barcode");
                $stmtCheckItem->execute(['barcode' => $barcode]);
                $existingItem = $stmtCheckItem->fetch();

                $params = [
                    'barcode' => $barcode,
                    'name' => $name,
                    'description' => $itemData['description'] ?: null,
                    'category_id' => $categoryId,
                    'quantity' => intval($itemData['quantity'] ?? 0),
                    'min_quantity' => intval($itemData['min_quantity'] ?? 0),
                    'unit_price' => floatval($itemData['unit_price'] ?? 0),
                    'location' => $itemData['location'] ?: null,
                    'supplier' => $itemData['supplier'] ?: null
                ];

                if ($existingItem) {
                    // Atualizar
                    $updateParams = [];
                    $updateFields = [];
                    foreach ($params as $key => $value) {
                        if ($key !== 'barcode') { // Não atualizar barcode
                            $updateFields[] = "{$key} = :{$key}";
                            $updateParams[$key] = $value;
                        }
                    }
                    $updateParams['id'] = $existingItem['id'];
                    $stmtUpdate = $db->prepare("UPDATE items SET " . implode(', ', $updateFields) . " WHERE id = :id");
                    $stmtUpdate->execute($updateParams);
                    $updated++;
                } else {
                    // Inserir
                    $stmtInsert = $db->prepare("
                        INSERT INTO items (barcode, name, description, category_id, quantity, min_quantity, unit_price, location, supplier)
                        VALUES (:barcode, :name, :description, :category_id, :quantity, :min_quantity, :unit_price, :location, :supplier)
                    ");
                    $stmtInsert->execute($params);
                    $imported++;
                }
            } catch (Exception $er) {
                $errors[] = $er->getMessage();
                continue;
            }
        }

        fclose($fileHandle);
        @unlink($uploadPath);

        sendJsonResponse([
            'success' => true,
            'message' => "Importação CSV concluída: {$imported} importados, {$updated} atualizados.",
            'imported' => $imported,
            'updated' => $updated,
            'errors' => $errors
        ]);
    }

    // Ficheiros XLS/XLSX: tentar via Python se disponível
    $pythonScript = __DIR__ . '/../scripts/import_items.py';
    
    // Verificar se Python está disponível
    $pythonCheck = [];
    $pythonCheckCode = 0;
    exec("which python3 2>&1", $pythonCheck, $pythonCheckCode);
    
    if ($pythonCheckCode !== 0) {
        error_log("Items import - Error: Python3 not found");
        @unlink($uploadPath);
        sendJsonResponse([
            'success' => false,
            'message' => 'Python3 não está disponível no servidor. Contacte o administrador.'
        ], 500);
    }
    
    // Verificar se o script Python existe
    if (!file_exists($pythonScript)) {
        error_log("Items import - Error: Python script not found at $pythonScript");
        @unlink($uploadPath);
        sendJsonResponse([
            'success' => false,
            'message' => 'Script de importação não encontrado. Contacte o administrador.'
        ], 500);
    }
    
    $command = escapeshellcmd("python3 " . $pythonScript . " " . escapeshellarg($uploadPath));
    
    $output = [];
    $returnCode = 0;
    exec($command . " 2>&1", $output, $returnCode);

    if ($returnCode !== 0) {
        // Falhou Python: devolver erro claro
        error_log("Items import - Python import error (return code $returnCode): " . implode("\n", $output));
        
        $errorMessage = implode("\n", $output);
        if (empty($errorMessage)) {
            $errorMessage = "Erro desconhecido ao processar ficheiro XLSX";
        }
        
        sendJsonResponse([
            'success' => false,
            'message' => 'Falha a processar XLS/XLSX: ' . $errorMessage,
            'details' => $output,
            'return_code' => $returnCode
        ], 500);
    }

    @unlink($uploadPath);

    $outputString = implode("\n", $output);
    $result = json_decode($outputString, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("Items import - JSON decode error: " . json_last_error_msg());
        sendJsonResponse([
            'success' => false,
            'message' => 'Erro ao processar resposta do script de importação: ' . json_last_error_msg()
        ], 500);
    }
    
    if ($result && isset($result['success'])) {
        sendJsonResponse([
            'success' => true,
            'message' => $result['message'] ?? 'Importação concluída',
            'imported' => $result['imported'] ?? 0,
            'updated' => $result['updated'] ?? 0,
            'errors' => $result['errors'] ?? []
        ]);
    }
    
    error_log("Items import - Warning: Unexpected result format");
    sendJsonResponse([
        'success' => false,
        'message' => 'Formato de resposta inesperado do script de importação'
    ], 500);

} catch (Exception $e) {
    error_log("Import error: " . $e->getMessage());
    sendJsonResponse([
        'success' => false,
        'message' => 'Erro ao processar importação: ' . $e->getMessage()
    ], 500);
}

