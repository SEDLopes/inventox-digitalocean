# Referência da API - InventoX

Documentação completa da API RESTful do InventoX.

## 🌐 Base URL

```
http://localhost:8080/api
```

## 📋 Autenticação

A maioria dos endpoints requer autenticação via sessão PHP. Após o login, uma sessão é criada automaticamente.

### Login

**Endpoint**: `POST /login.php`

**Body**:
```json
{
  "username": "admin",
  "password": "admin123"
}
```

**Resposta de Sucesso** (200):
```json
{
  "success": true,
  "message": "Login realizado com sucesso",
  "user": {
    "id": 1,
    "username": "admin",
    "email": "admin@inventox.local",
    "role": "admin"
  }
}
```

**Resposta de Erro** (401):
```json
{
  "success": false,
  "message": "Credenciais inválidas"
}
```

## 📦 Endpoints

### 1. Gestão de Sessões de Inventário

#### Listar Sessões

**Endpoint**: `GET /session_count.php`

**Resposta** (200):
```json
{
  "success": true,
  "sessions": [
    {
      "id": 1,
      "name": "Inventário Janeiro 2024",
      "description": "Inventário mensal",
      "user_id": 1,
      "created_by": "admin",
      "status": "aberta",
      "started_at": "2024-01-15 10:00:00",
      "finished_at": null,
      "total_counts": 25
    }
  ]
}
```

#### Obter Sessão Específica

**Endpoint**: `GET /session_count.php?id={session_id}`

**Resposta** (200):
```json
{
  "success": true,
  "session": {
    "id": 1,
    "name": "Inventário Janeiro 2024",
    "description": "Inventário mensal",
    "user_id": 1,
    "created_by": "admin",
    "status": "aberta",
    "started_at": "2024-01-15 10:00:00",
    "finished_at": null,
    "total_counts": 25,
    "discrepancies": 3,
    "counts": [
      {
        "id": 1,
        "item_id": 5,
        "barcode": "1234567890123",
        "item_name": "Produto Exemplo",
        "current_quantity": 10,
        "counted_quantity": 12,
        "expected_quantity": 10,
        "difference": 2,
        "notes": "",
        "counted_at": "2024-01-15 10:30:00"
      }
    ]
  }
}
```

#### Criar Nova Sessão

**Endpoint**: `POST /session_count.php`

**Body**:
```json
{
  "name": "Inventário Janeiro 2024",
  "description": "Inventário mensal"
}
```

**Resposta** (201):
```json
{
  "success": true,
  "message": "Sessão criada com sucesso",
  "session_id": 1
}
```

#### Adicionar Contagem

**Endpoint**: `POST /session_count.php`

**Body**:
```json
{
  "session_id": 1,
  "barcode": "1234567890123",
  "counted_quantity": 15,
  "notes": "Nota opcional"
}
```

**Resposta** (201):
```json
{
  "success": true,
  "message": "Contagem registada com sucesso",
  "count": {
    "item_id": 5,
    "counted_quantity": 15,
    "expected_quantity": 10,
    "difference": 5
  }
}
```

#### Atualizar Sessão

**Endpoint**: `PUT /session_count.php`

**Body**:
```json
{
  "session_id": 1,
  "status": "fechada"
}
```

**Status válidos**: `aberta`, `fechada`, `cancelada`

**Resposta** (200):
```json
{
  "success": true,
  "message": "Sessão atualizada com sucesso"
}
```

### 2. Importação de Artigos

#### Importar Ficheiro

**Endpoint**: `POST /items_import.php`

**Content-Type**: `multipart/form-data`

**Form Data**:
- `file`: Ficheiro CSV ou XLSX

**Formato CSV Esperado**:
```csv
barcode,name,description,quantity,min_quantity,unit_price,category,location,supplier
1234567890123,Produto 1,Descrição 1,100,10,25.50,Categoria 1,Localização A,Fornecedor X
9876543210987,Produto 2,Descrição 2,50,5,15.75,Categoria 2,Localização B,Fornecedor Y
```

**Resposta de Sucesso** (200):
```json
{
  "success": true,
  "message": "Importação concluída: 10 importados, 5 atualizados",
  "imported": 10,
  "updated": 5,
  "errors": []
}
```

**Resposta de Erro** (400):
```json
{
  "success": false,
  "message": "Tipo de ficheiro não permitido. Use CSV ou XLSX."
}
```

### 3. Exportação de Sessões

#### Exportar em JSON

**Endpoint**: `GET /export_session.php?id={session_id}`

**Resposta** (200):
```json
{
  "success": true,
  "session": {
    "id": 1,
    "name": "Inventário Janeiro 2024",
    ...
  },
  "counts": [...],
  "summary": {
    "total_items": 25,
    "with_discrepancies": 3,
    "total_difference": 10
  }
}
```

#### Exportar em CSV

**Endpoint**: `GET /export_session.php?id={session_id}&format=csv`

**Content-Type**: `text/csv`

**Response**: Ficheiro CSV para download

## 🔒 Códigos de Status HTTP

- `200 OK` - Pedido bem-sucedido
- `201 Created` - Recurso criado com sucesso
- `400 Bad Request` - Erro nos dados enviados
- `401 Unauthorized` - Não autenticado
- `403 Forbidden` - Sem permissão
- `404 Not Found` - Recurso não encontrado
- `405 Method Not Allowed` - Método HTTP não permitido
- `500 Internal Server Error` - Erro no servidor

## 📝 Formatos de Resposta

### Resposta de Sucesso

```json
{
  "success": true,
  "message": "Mensagem de sucesso",
  "data": {...}
}
```

### Resposta de Erro

```json
{
  "success": false,
  "message": "Mensagem de erro"
}
```

## 🔐 Segurança

### Proteções Implementadas

1. **SQL Injection**: Uso de PDO Prepared Statements
2. **XSS**: Sanitização de todas as entradas
3. **Autenticação**: Sessões PHP seguras
4. **CORS**: Configurado no `.htaccess`

### Boas Práticas

- Sempre validar dados no cliente e servidor
- Usar HTTPS em produção
- Armazenar senhas com `password_hash`
- Limitar taxa de pedidos (rate limiting) em produção

## 🧪 Exemplos de Uso

### Exemplo: Criar Sessão e Adicionar Contagens

```javascript
// 1. Criar sessão
const createSession = async () => {
  const response = await fetch('http://localhost:8080/api/session_count.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      name: 'Inventário Teste',
      description: 'Teste de API'
    })
  });
  const data = await response.json();
  return data.session_id;
};

// 2. Adicionar contagem
const addCount = async (sessionId, barcode, quantity) => {
  const response = await fetch('http://localhost:8080/api/session_count.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      session_id: sessionId,
      barcode: barcode,
      counted_quantity: quantity
    })
  });
  return await response.json();
};
```

### Exemplo: Importar Ficheiro

```javascript
const importFile = async (file) => {
  const formData = new FormData();
  formData.append('file', file);
  
  const response = await fetch('http://localhost:8080/api/items_import.php', {
    method: 'POST',
    body: formData
  });
  
  return await response.json();
};
```

## 📚 Recursos Adicionais

- [Estrutura da Base de Dados](./DB_STRUCTURE.md)
- [Guia de Instalação](./INSTALLATION.md)

## 🐛 Limitações Conhecidas

- Importação limitada a ficheiros de 10MB
- Scanner requer HTTPS em produção (limitação do navegador)
- Sessões expiram após período de inatividade (configurável)

## 🔄 Versão da API

**Versão atual**: 1.0.0

Mudanças futuras serão documentadas no [CHANGELOG.md](./CHANGELOG.md).

