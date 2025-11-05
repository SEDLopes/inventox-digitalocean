# Estrutura da Base de Dados - InventoX

Documentação completa do schema da base de dados MySQL.

## 📊 Diagrama de Relacionamentos

```
users (1) ──< (N) inventory_sessions
users (1) ──< (N) stock_movements

categories (1) ──< (N) items

items (1) ──< (N) inventory_counts
items (1) ──< (N) stock_movements

inventory_sessions (1) ──< (N) inventory_counts
```

## 📋 Tabelas

### 1. `users` - Utilizadores

Armazena informações dos utilizadores do sistema.

| Campo | Tipo | Descrição | Constraints |
|-------|------|-----------|-------------|
| `id` | INT | ID único | PK, AUTO_INCREMENT |
| `username` | VARCHAR(50) | Nome de utilizador | NOT NULL, UNIQUE |
| `email` | VARCHAR(100) | Email | NOT NULL, UNIQUE |
| `password_hash` | VARCHAR(255) | Hash da senha | NOT NULL |
| `role` | ENUM | Função | 'admin', 'operador' (default: 'operador') |
| `created_at` | TIMESTAMP | Data de criação | DEFAULT CURRENT_TIMESTAMP |
| `updated_at` | TIMESTAMP | Data de atualização | ON UPDATE CURRENT_TIMESTAMP |
| `is_active` | BOOLEAN | Ativo/Inativo | DEFAULT TRUE |

**Índices**:
- `idx_username` (username)
- `idx_email` (email)

**Exemplo de INSERT**:
```sql
INSERT INTO users (username, email, password_hash, role)
VALUES ('admin', 'admin@inventox.local', '$2y$10$...', 'admin');
```

### 2. `categories` - Categorias

Categorias de produtos.

| Campo | Tipo | Descrição | Constraints |
|-------|------|-----------|-------------|
| `id` | INT | ID único | PK, AUTO_INCREMENT |
| `name` | VARCHAR(100) | Nome da categoria | NOT NULL, UNIQUE |
| `description` | TEXT | Descrição | NULL |
| `created_at` | TIMESTAMP | Data de criação | DEFAULT CURRENT_TIMESTAMP |
| `updated_at` | TIMESTAMP | Data de atualização | ON UPDATE CURRENT_TIMESTAMP |

**Índices**:
- `idx_name` (name)

**Categorias Padrão**:
- Eletrónicos
- Informática
- Ferramentas
- Material de Escritório

### 3. `items` - Artigos

Catálogo de artigos/inventário.

| Campo | Tipo | Descrição | Constraints |
|-------|------|-----------|-------------|
| `id` | INT | ID único | PK, AUTO_INCREMENT |
| `barcode` | VARCHAR(100) | Código de barras | NOT NULL, UNIQUE |
| `name` | VARCHAR(255) | Nome do artigo | NOT NULL |
| `description` | TEXT | Descrição | NULL |
| `category_id` | INT | ID da categoria | FK → categories.id |
| `quantity` | INT | Quantidade atual | DEFAULT 0 |
| `min_quantity` | INT | Quantidade mínima | DEFAULT 0 |
| `unit_price` | DECIMAL(10,2) | Preço unitário | DEFAULT 0.00 |
| `location` | VARCHAR(100) | Localização | NULL |
| `supplier` | VARCHAR(100) | Fornecedor | NULL |
| `created_at` | TIMESTAMP | Data de criação | DEFAULT CURRENT_TIMESTAMP |
| `updated_at` | TIMESTAMP | Data de atualização | ON UPDATE CURRENT_TIMESTAMP |

**Índices**:
- `idx_barcode` (barcode) - Único
- `idx_name` (name)
- `idx_category` (category_id)

**Foreign Keys**:
- `category_id` → `categories(id)` ON DELETE SET NULL

**Exemplo de INSERT**:
```sql
INSERT INTO items (barcode, name, description, category_id, quantity, min_quantity, unit_price)
VALUES ('1234567890123', 'Produto Exemplo', 'Descrição', 1, 100, 10, 25.50);
```

### 4. `inventory_sessions` - Sessões de Inventário

Sessões de contagem de inventário.

| Campo | Tipo | Descrição | Constraints |
|-------|------|-----------|-------------|
| `id` | INT | ID único | PK, AUTO_INCREMENT |
| `name` | VARCHAR(255) | Nome da sessão | NOT NULL |
| `description` | TEXT | Descrição | NULL |
| `user_id` | INT | ID do utilizador | NOT NULL, FK → users.id |
| `status` | ENUM | Status | 'aberta', 'fechada', 'cancelada' (default: 'aberta') |
| `started_at` | TIMESTAMP | Data de início | DEFAULT CURRENT_TIMESTAMP |
| `finished_at` | TIMESTAMP | Data de fim | NULL |
| `created_at` | TIMESTAMP | Data de criação | DEFAULT CURRENT_TIMESTAMP |
| `updated_at` | TIMESTAMP | Data de atualização | ON UPDATE CURRENT_TIMESTAMP |

**Índices**:
- `idx_user` (user_id)
- `idx_status` (status)
- `idx_started_at` (started_at)

**Foreign Keys**:
- `user_id` → `users(id)` ON DELETE RESTRICT

**Exemplo de INSERT**:
```sql
INSERT INTO inventory_sessions (name, description, user_id, status)
VALUES ('Inventário Janeiro 2024', 'Inventário mensal', 1, 'aberta');
```

### 5. `inventory_counts` - Contagens de Inventário

Contagens individuais de artigos em sessões.

| Campo | Tipo | Descrição | Constraints |
|-------|------|-----------|-------------|
| `id` | INT | ID único | PK, AUTO_INCREMENT |
| `session_id` | INT | ID da sessão | NOT NULL, FK → inventory_sessions.id |
| `item_id` | INT | ID do artigo | NOT NULL, FK → items.id |
| `counted_quantity` | INT | Quantidade contada | NOT NULL, DEFAULT 0 |
| `expected_quantity` | INT | Quantidade esperada | NOT NULL, DEFAULT 0 |
| `difference` | INT | Diferença | DEFAULT 0 |
| `notes` | TEXT | Notas | NULL |
| `counted_at` | TIMESTAMP | Data da contagem | DEFAULT CURRENT_TIMESTAMP |

**Índices**:
- `idx_session` (session_id)
- `idx_item` (item_id)
- `UNIQUE` (session_id, item_id) - Uma contagem por item por sessão

**Foreign Keys**:
- `session_id` → `inventory_sessions(id)` ON DELETE CASCADE
- `item_id` → `items(id)` ON DELETE CASCADE

**Exemplo de INSERT**:
```sql
INSERT INTO inventory_counts (session_id, item_id, counted_quantity, expected_quantity, difference)
VALUES (1, 5, 15, 10, 5);
```

### 6. `stock_movements` - Movimentos de Stock

Histórico de movimentos de stock (entradas, saídas, ajustes).

| Campo | Tipo | Descrição | Constraints |
|-------|------|-----------|-------------|
| `id` | INT | ID único | PK, AUTO_INCREMENT |
| `item_id` | INT | ID do artigo | NOT NULL, FK → items.id |
| `movement_type` | ENUM | Tipo | 'entrada', 'saida', 'ajuste', 'transferencia' |
| `quantity` | INT | Quantidade | NOT NULL |
| `reason` | TEXT | Motivo | NULL |
| `user_id` | INT | ID do utilizador | FK → users.id |
| `created_at` | TIMESTAMP | Data do movimento | DEFAULT CURRENT_TIMESTAMP |

**Índices**:
- `idx_item` (item_id)
- `idx_type` (movement_type)
- `idx_created_at` (created_at)

**Foreign Keys**:
- `item_id` → `items(id)` ON DELETE CASCADE
- `user_id` → `users(id)` ON DELETE SET NULL

**Exemplo de INSERT**:
```sql
INSERT INTO stock_movements (item_id, movement_type, quantity, reason, user_id)
VALUES (5, 'entrada', 50, 'Recebimento de fornecedor', 1);
```

## 🔍 Queries Úteis

### Listar Artigos com Quantidade Baixa

```sql
SELECT 
    i.id, i.barcode, i.name, i.quantity, i.min_quantity,
    c.name as category_name
FROM items i
LEFT JOIN categories c ON i.category_id = c.id
WHERE i.quantity <= i.min_quantity
ORDER BY i.quantity ASC;
```

### Relatório de Sessão de Inventário

```sql
SELECT 
    s.name as session_name,
    i.barcode,
    i.name as item_name,
    c.counted_quantity,
    c.expected_quantity,
    c.difference,
    c.notes
FROM inventory_sessions s
INNER JOIN inventory_counts c ON s.id = c.session_id
INNER JOIN items i ON c.item_id = i.id
WHERE s.id = 1
ORDER BY c.difference DESC;
```

### Histórico de Movimentos por Artigo

```sql
SELECT 
    sm.*,
    u.username as user_name
FROM stock_movements sm
LEFT JOIN users u ON sm.user_id = u.id
WHERE sm.item_id = 5
ORDER BY sm.created_at DESC;
```

### Estatísticas de Sessões

```sql
SELECT 
    s.id,
    s.name,
    s.status,
    COUNT(c.id) as total_counts,
    COUNT(CASE WHEN c.difference != 0 THEN 1 END) as discrepancies,
    SUM(c.difference) as total_difference
FROM inventory_sessions s
LEFT JOIN inventory_counts c ON s.id = c.session_id
GROUP BY s.id
ORDER BY s.started_at DESC;
```

## 🔧 Manutenção

### Backup

```bash
docker exec inventox_db mysqldump -uroot -proot inventox > backup_$(date +%Y%m%d).sql
```

### Restaurar Backup

```bash
docker exec -i inventox_db mysql -uroot -proot inventox < backup_20240115.sql
```

### Limpar Dados Antigos

```sql
-- Remover contagens de sessões fechadas há mais de 1 ano
DELETE FROM inventory_counts
WHERE session_id IN (
    SELECT id FROM inventory_sessions
    WHERE status = 'fechada'
    AND finished_at < DATE_SUB(NOW(), INTERVAL 1 YEAR)
);

-- Remover sessões fechadas há mais de 1 ano
DELETE FROM inventory_sessions
WHERE status = 'fechada'
AND finished_at < DATE_SUB(NOW(), INTERVAL 1 YEAR);
```

## 📊 Otimizações

### Índices Adicionais (se necessário)

```sql
-- Índice composto para queries frequentes
CREATE INDEX idx_session_status ON inventory_sessions(status, started_at);

-- Índice para busca de artigos
CREATE FULLTEXT INDEX idx_items_search ON items(name, description);
```

### Vacuum/Otimização

```sql
-- Otimizar tabelas
OPTIMIZE TABLE items;
OPTIMIZE TABLE inventory_counts;
OPTIMIZE TABLE stock_movements;
```

## 🔄 Migrações Futuras

Alterações na estrutura serão documentadas aqui e em ficheiros de migração SQL separados.

