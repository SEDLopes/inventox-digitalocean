-- InventoX Database Schema para Railway
-- Execute este script no cliente MySQL do Railway

-- Criar base de dados (pode já existir)
CREATE DATABASE IF NOT EXISTS railway CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE railway;

-- Tabela de utilizadores
CREATE TABLE IF NOT EXISTS users (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de categorias
CREATE TABLE IF NOT EXISTS categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de artigos
CREATE TABLE IF NOT EXISTS items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    barcode VARCHAR(100) NOT NULL UNIQUE,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    category_id INT,
    quantity INT DEFAULT 0,
    min_quantity INT DEFAULT 0,
    unit_price DECIMAL(10, 2) DEFAULT 0.00,
    location VARCHAR(100),
    supplier VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
    INDEX idx_barcode (barcode),
    INDEX idx_name (name),
    INDEX idx_category (category_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de empresas
CREATE TABLE IF NOT EXISTS companies (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    address TEXT,
    phone VARCHAR(20),
    email VARCHAR(100),
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de armazéns
CREATE TABLE IF NOT EXISTS warehouses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    location VARCHAR(255),
    company_id INT,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    INDEX idx_name (name),
    INDEX idx_company (company_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de sessões de inventário
CREATE TABLE IF NOT EXISTS inventory_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    warehouse_id INT,
    user_id INT,
    status ENUM('active', 'completed', 'cancelled') DEFAULT 'active',
    started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (warehouse_id) REFERENCES warehouses(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_status (status),
    INDEX idx_warehouse (warehouse_id),
    INDEX idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de contagens de inventário
CREATE TABLE IF NOT EXISTS inventory_counts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_id INT NOT NULL,
    item_id INT NOT NULL,
    expected_quantity INT DEFAULT 0,
    counted_quantity INT DEFAULT 0,
    difference_quantity INT DEFAULT 0,
    notes TEXT,
    counted_by INT,
    counted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (session_id) REFERENCES inventory_sessions(id) ON DELETE CASCADE,
    FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE,
    FOREIGN KEY (counted_by) REFERENCES users(id) ON DELETE SET NULL,
    UNIQUE KEY unique_session_item (session_id, item_id),
    INDEX idx_session (session_id),
    INDEX idx_item (item_id),
    INDEX idx_counted_by (counted_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de movimentos de stock
CREATE TABLE IF NOT EXISTS stock_movements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    item_id INT NOT NULL,
    movement_type ENUM('in', 'out', 'adjustment', 'transfer') NOT NULL,
    quantity INT NOT NULL,
    previous_quantity INT NOT NULL,
    new_quantity INT NOT NULL,
    reference VARCHAR(100),
    notes TEXT,
    user_id INT,
    warehouse_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (warehouse_id) REFERENCES warehouses(id) ON DELETE SET NULL,
    INDEX idx_item (item_id),
    INDEX idx_type (movement_type),
    INDEX idx_user (user_id),
    INDEX idx_warehouse (warehouse_id),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dados iniciais

-- Utilizador admin
INSERT IGNORE INTO users (username, email, password_hash, role) VALUES 
('admin', 'admin@inventox.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin');

-- Categorias padrão
INSERT IGNORE INTO categories (name, description) VALUES 
('Geral', 'Categoria geral para artigos diversos'),
('Informática', 'Equipamentos e acessórios informáticos'),
('Escritório', 'Material de escritório e papelaria');

-- Empresa padrão
INSERT IGNORE INTO companies (name, address, phone, email) VALUES 
('InventoX Lda', 'Rua Principal, 123', '+351 123 456 789', 'geral@inventox.com');

-- Armazém padrão
INSERT IGNORE INTO warehouses (name, location, company_id) VALUES 
('Armazém Principal', 'Zona Industrial', 1);

-- Artigos de exemplo
INSERT IGNORE INTO items (barcode, name, description, category_id, quantity, min_quantity, unit_price, location, supplier) VALUES 
('1234567890123', 'Laptop Dell Inspiron', 'Laptop Dell Inspiron 15 3000', 2, 5, 2, 599.99, 'A1-01', 'Dell'),
('2345678901234', 'Mouse Logitech', 'Mouse óptico Logitech M100', 2, 25, 5, 12.99, 'A1-02', 'Logitech'),
('3456789012345', 'Teclado Mecânico', 'Teclado mecânico RGB', 2, 10, 3, 89.99, 'A1-03', 'Corsair'),
('4567890123456', 'Resma A4', 'Resma papel A4 500 folhas', 3, 50, 10, 4.99, 'B2-01', 'Navigator'),
('5678901234567', 'Caneta Bic', 'Caneta esferográfica azul', 3, 100, 20, 0.50, 'B2-02', 'Bic');

COMMIT;
