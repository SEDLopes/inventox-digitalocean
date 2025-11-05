# Referência Estendida da API - InventoX

Documentação adicional dos novos endpoints criados.

## 🔐 Logout

**Endpoint**: `POST /logout.php`

Destrói a sessão do utilizador no servidor.

**Resposta de Sucesso** (200):
```json
{
  "success": true,
  "message": "Logout realizado com sucesso"
}
```

## 📦 Gestão de Artigos (CRUD Completo)

### Listar Artigos

**Endpoint**: `GET /items.php`

**Query Parameters**:
- `page` (opcional): Número da página, padrão: 1
- `limit` (opcional): Itens por página, padrão: 50, máximo: 100
- `search` (opcional): Busca por nome, barcode ou descrição
- `category_id` (opcional): Filtrar por ID da categoria

**Exemplo**:
```
GET /items.php?page=1&limit=20&search=laptop&category_id=2
```

**Resposta** (200):
```json
{
  "success": true,
  "items": [
    {
      "id": 1,
      "barcode": "1234567890123",
      "name": "Laptop Dell",
      "description": "Portátil",
      "category_id": 2,
      "category_name": "Informática",
      "quantity": 50,
      "min_quantity": 5,
      "unit_price": 599.99,
      "location": "Loja A",
      "supplier": "Dell Portugal",
      "created_at": "2024-01-15 10:00:00",
      "updated_at": "2024-01-15 10:00:00"
    }
  ],
  "pagination": {
    "page": 1,
    "limit": 20,
    "total": 150,
    "pages": 8
  }
}
```

### Obter Artigo Específico

**Endpoint**: `GET /items.php?id={item_id}`

ou

**Endpoint**: `GET /items.php?barcode={barcode}`

**Resposta** (200):
```json
{
  "success": true,
  "item": {
    "id": 1,
    "barcode": "1234567890123",
    "name": "Laptop Dell",
    ...
  }
}
```

### Criar Artigo

**Endpoint**: `POST /items.php`

**Body**:
```json
{
  "barcode": "1234567890123",
  "name": "Novo Produto",
  "description": "Descrição opcional",
  "category_id": 1,
  "quantity": 100,
  "min_quantity": 10,
  "unit_price": 25.50,
  "location": "Loja A",
  "supplier": "Fornecedor X"
}
```

**Campos obrigatórios**: `barcode`, `name`

**Resposta** (201):
```json
{
  "success": true,
  "message": "Artigo criado com sucesso",
  "item_id": 5
}
```

### Atualizar Artigo

**Endpoint**: `PUT /items.php`

**Body**:
```json
{
  "id": 1,
  "name": "Nome Atualizado",
  "quantity": 150,
  "unit_price": 29.99
}
```

Apenas os campos fornecidos serão atualizados.

**Resposta** (200):
```json
{
  "success": true,
  "message": "Artigo atualizado com sucesso"
}
```

### Deletar Artigo

**Endpoint**: `DELETE /items.php?id={item_id}`

**Resposta** (200):
```json
{
  "success": true,
  "message": "Artigo eliminado com sucesso"
}
```

## 🏷️ Gestão de Categorias (CRUD Completo)

### Listar Categorias

**Endpoint**: `GET /categories.php`

**Resposta** (200):
```json
{
  "success": true,
  "categories": [
    {
      "id": 1,
      "name": "Informática",
      "description": "Equipamentos e acessórios",
      "items_count": 25,
      "created_at": "2024-01-15 10:00:00"
    }
  ]
}
```

### Obter Categoria Específica

**Endpoint**: `GET /categories.php?id={category_id}`

### Criar Categoria

**Endpoint**: `POST /categories.php`

**Body**:
```json
{
  "name": "Nova Categoria",
  "description": "Descrição opcional"
}
```

**Campos obrigatórios**: `name`

### Atualizar Categoria

**Endpoint**: `PUT /categories.php`

**Body**:
```json
{
  "id": 1,
  "name": "Nome Atualizado",
  "description": "Nova descrição"
}
```

### Deletar Categoria

**Endpoint**: `DELETE /categories.php?id={category_id}`

**Nota**: Apenas deleta se não houver artigos associados.

## 📊 Estatísticas

**Endpoint**: `GET /stats.php`

Retorna estatísticas gerais do sistema.

**Resposta** (200):
```json
{
  "success": true,
  "stats": {
    "total_items": 150,
    "low_stock_items": 5,
    "total_categories": 10,
    "open_sessions": 2,
    "closed_sessions": 25,
    "total_inventory_value": 12500.50,
    "movements_last_30_days": 45,
    "top_categories": [
      {
        "id": 2,
        "name": "Informática",
        "items_count": 50
      }
    ],
    "low_stock_list": [
      {
        "id": 5,
        "barcode": "1234567890123",
        "name": "Produto A",
        "quantity": 8,
        "min_quantity": 10,
        "shortage": 2
      }
    ],
    "recent_sessions": [...]
  }
}
```

## 📝 Notas

- Todos os endpoints requerem autenticação (exceto login/logout)
- Todos os endpoints usam sessões PHP via cookies
- Todas as requisições devem incluir `credentials: 'include'` no fetch
- Validações são realizadas tanto no cliente quanto no servidor

