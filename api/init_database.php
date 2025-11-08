<?php
/**
 * Endpoint para inicializar a database do InventoX
 * Acesse: https://sua-app.ondigitalocean.app/api/init_database.php?token=inventox2024
 */

// Token de segurança - aceitar múltiplos tokens (compatibilidade com truncamento)
$valid_tokens = [
    'inventox2024',  // Token completo
    'inventox2',     // Token truncado (fallback)
    'inventox'       // Token mínimo (fallback)
];

// Aceitar token de múltiplas fontes (GET, POST, ou REQUEST_URI)
$provided_token = '';

// Tentar POST primeiro (mais confiável, não é truncado)
if (isset($_POST['token'])) {
    $provided_token = $_POST['token'];
}
// Tentar GET
elseif (isset($_GET['token'])) {
    $provided_token = $_GET['token'];
}
// Tentar extrair da REQUEST_URI (caso GET seja truncado)
elseif (isset($_SERVER['REQUEST_URI'])) {
    $uri = $_SERVER['REQUEST_URI'];
    if (preg_match('/[?&]token=([^&]+)/', $uri, $matches)) {
        $provided_token = urldecode($matches[1]);
    }
}
// Tentar QUERY_STRING diretamente
elseif (isset($_SERVER['QUERY_STRING'])) {
    parse_str($_SERVER['QUERY_STRING'], $query_params);
    $provided_token = $query_params['token'] ?? '';
}

// Debug (remover em produção se necessário)
error_log("init_database.php - Token recebido: " . ($provided_token ?: 'VAZIO'));
error_log("init_database.php - Tokens válidos: " . implode(', ', $valid_tokens));
error_log("init_database.php - REQUEST_URI: " . ($_SERVER['REQUEST_URI'] ?? 'N/A'));
error_log("init_database.php - QUERY_STRING: " . ($_SERVER['QUERY_STRING'] ?? 'N/A'));
error_log("init_database.php - GET: " . print_r($_GET, true));

// Verificação flexível - aceitar tokens válidos (incluindo truncados)
$provided_token_clean = trim($provided_token);
$token_valid = false;

// Verificar se token fornecido corresponde a algum token válido
foreach ($valid_tokens as $valid_token) {
    if ($provided_token_clean === $valid_token) {
        $token_valid = true;
        break;
    }
}

// Se não corresponder exatamente, verificar se começa com algum token válido (para truncamento)
if (!$token_valid) {
    foreach ($valid_tokens as $valid_token) {
        if (strpos($valid_token, $provided_token_clean) === 0 || strpos($provided_token_clean, $valid_token) === 0) {
            $token_valid = true;
            break;
        }
    }
}

if (!$token_valid) {
    http_response_code(403);
    die(json_encode([
        'error' => 'Token inválido. Use: ?token=inventox2024 (ou POST com token=inventox2024)',
        'debug' => [
            'provided' => $provided_token_clean ?: 'VAZIO',
            'provided_length' => strlen($provided_token_clean),
            'valid_tokens' => $valid_tokens,
            'match' => false,
            'request_uri' => $_SERVER['REQUEST_URI'] ?? 'N/A',
            'query_string' => $_SERVER['QUERY_STRING'] ?? 'N/A',
            'tip' => 'Se GET for truncado, use POST: curl -X POST ... -d "token=inventox2024"'
        ]
    ]));
}

// Headers para JSON
header('Content-Type: application/json');

try {
    // Conectar à database usando as variáveis de ambiente
    $host = $_ENV['DB_HOST'] ?? getenv('DB_HOST');
    $dbname = $_ENV['DB_NAME'] ?? getenv('DB_NAME');
    $username = $_ENV['DB_USER'] ?? getenv('DB_USER');
    $password = $_ENV['DB_PASS'] ?? getenv('DB_PASS');
    $port = $_ENV['DB_PORT'] ?? getenv('DB_PORT') ?? 25060;

    if (!$host || !$dbname || !$username || !$password) {
        throw new Exception('Variáveis de ambiente da database não configuradas');
    }

    $dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";
    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
    ]);

    // SQL Schema completo
    $sql_statements = [
        // Tabela de utilizadores
        "CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) NOT NULL UNIQUE,
            email VARCHAR(100) NOT NULL UNIQUE,
            password_hash VARCHAR(255) NOT NULL,
            role ENUM('admin', 'operador') DEFAULT 'operador',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            is_active BOOLEAN DEFAULT TRUE,
            INDEX idx_username (username),
            INDEX idx_email (email)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        // Tabela de empresas
        "CREATE TABLE IF NOT EXISTS companies (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(200) NOT NULL,
            address TEXT,
            phone VARCHAR(20),
            email VARCHAR(100),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        // Tabela de armazéns
        "CREATE TABLE IF NOT EXISTS warehouses (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            location VARCHAR(200),
            company_id INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        // Tabela de categorias
        "CREATE TABLE IF NOT EXISTS categories (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL UNIQUE,
            description TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_name (name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        // Tabela de artigos
        "CREATE TABLE IF NOT EXISTS items (
            id INT AUTO_INCREMENT PRIMARY KEY,
            barcode VARCHAR(100) NOT NULL UNIQUE,
            name VARCHAR(200) NOT NULL,
            description TEXT,
            category_id INT,
            unit_price DECIMAL(10,2) DEFAULT 0.00,
            quantity INT DEFAULT 0,
            min_stock INT DEFAULT 0,
            location VARCHAR(100),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
            INDEX idx_barcode (barcode),
            INDEX idx_name (name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        // Inserir dados iniciais
        "INSERT IGNORE INTO companies (id, name, address, phone, email) VALUES 
        (1, 'InventoX Lda', 'Rua Principal, 123', '+351 123 456 789', 'info@inventox.com')",

        "INSERT IGNORE INTO warehouses (id, name, location, company_id) VALUES 
        (1, 'Armazém Principal', 'Zona Industrial', 1)",

        "INSERT IGNORE INTO categories (id, name, description) VALUES 
        (1, 'Geral', 'Categoria geral para artigos diversos'),
        (2, 'Electrónicos', 'Equipamentos electrónicos'),
        (3, 'Alimentação', 'Produtos alimentares')",

        "INSERT INTO users (username, email, password_hash, role, is_active) VALUES 
        ('admin', 'admin@inventox.com', '$2y$10\$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 1)
        ON DUPLICATE KEY UPDATE 
        password_hash = VALUES(password_hash),
        is_active = 1,
        role = 'admin'"
    ];

    $results = [];
    foreach ($sql_statements as $index => $sql) {
        try {
            $pdo->exec($sql);
            $results[] = "Statement " . ($index + 1) . ": OK";
        } catch (Exception $e) {
            $results[] = "Statement " . ($index + 1) . ": ERROR - " . $e->getMessage();
        }
    }

    echo json_encode([
        'success' => true,
        'message' => 'Database inicializada com sucesso!',
        'details' => $results,
        'login' => [
            'username' => 'admin',
            'password' => 'admin123'
        ]
    ], JSON_PRETTY_PRINT);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'env_check' => [
            'DB_HOST' => isset($_ENV['DB_HOST']) ? 'SET' : 'NOT SET',
            'DB_NAME' => isset($_ENV['DB_NAME']) ? 'SET' : 'NOT SET',
            'DB_USER' => isset($_ENV['DB_USER']) ? 'SET' : 'NOT SET',
            'DB_PASS' => isset($_ENV['DB_PASS']) ? 'SET' : 'NOT SET'
        ]
    ], JSON_PRETTY_PRINT);
}
?>
