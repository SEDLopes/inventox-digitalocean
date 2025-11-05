-- InventoX - Dados de Exemplo
-- Ficheiro SQL para inserir dados de exemplo após a criação da base de dados

USE inventox;

-- Inserir alguns artigos de exemplo
INSERT INTO items (barcode, name, description, category_id, quantity, min_quantity, unit_price, location, supplier) VALUES
('1234567890123', 'Laptop Dell Inspiron 15', 'Portátil com 8GB RAM e 256GB SSD', 2, 50, 5, 599.99, 'Loja A', 'Dell Portugal'),
('9876543210987', 'Teclado Mecânico RGB', 'Teclado gaming com switches Cherry MX', 2, 120, 10, 89.99, 'Loja B', 'Logitech'),
('4567891230456', 'Rato Óptico Sem Fios', 'Rato ergonómico com sensor óptico 1600 DPI', 2, 200, 20, 29.99, 'Loja A', 'Microsoft'),
('7891234560789', 'Monitor LG 24 polegadas', 'Monitor Full HD IPS 24"', 2, 75, 8, 149.99, 'Loja C', 'LG Electronics'),
('3216549870321', 'Cabo HDMI 2m', 'Cabo HDMI de alta velocidade 2 metros', 2, 500, 50, 9.99, 'Loja A', 'Genérico'),
('6543219870654', 'Webcam HD 1080p', 'Webcam com microfone integrado', 2, 80, 8, 49.99, 'Loja B', 'Logitech'),
('1472583690147', 'Headset Gaming RGB', 'Auscultadores gaming com 7.1 surround', 2, 60, 6, 79.99, 'Loja A', 'Corsair'),
('2583691470258', 'Disco Externo 1TB', 'Disco rígido externo USB 3.0', 2, 90, 9, 69.99, 'Loja C', 'Western Digital'),
('3691472580369', 'Pendrive 64GB USB 3.0', 'Pendrive USB 3.0 com 64GB', 2, 300, 30, 12.99, 'Loja A', 'SanDisk'),
('7418529630741', 'Carregador USB-C Universal', 'Carregador rápido 45W USB-C', 2, 150, 15, 24.99, 'Loja B', 'Anker'),
('8529637410852', 'Smartphone Samsung Galaxy', 'Smartphone Android com 128GB', 1, 30, 3, 699.99, 'Loja A', 'Samsung'),
('9637418520963', 'Tablet iPad 10.2', 'Tablet Apple iPad 10.2" 64GB', 1, 25, 3, 499.99, 'Loja B', 'Apple'),
('1597534860159', 'Relógio Smartwatch', 'Smartwatch com GPS e monitor cardíaco', 1, 40, 4, 199.99, 'Loja C', 'Xiaomi'),
('7531594860753', 'Auscultadores Bluetooth', 'Auscultadores sem fios com noise cancellation', 1, 100, 10, 89.99, 'Loja A', 'Sony'),
('4861597530486', 'Powerbank 20000mAh', 'Powerbank com carregamento rápido', 1, 80, 8, 34.99, 'Loja B', 'Anker')
ON DUPLICATE KEY UPDATE name=VALUES(name);

-- Inserir alguns movimentos de stock de exemplo
INSERT INTO stock_movements (item_id, movement_type, quantity, reason, user_id) VALUES
(1, 'entrada', 50, 'Recebimento inicial', 1),
(2, 'entrada', 120, 'Recebimento inicial', 1),
(3, 'entrada', 200, 'Recebimento inicial', 1),
(1, 'saida', 5, 'Venda ao cliente', 1),
(2, 'saida', 10, 'Venda ao cliente', 1);

