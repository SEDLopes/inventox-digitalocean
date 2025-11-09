# 🚀 Setup Local - InventoX

**Data:** 2024-11-09  
**Objetivo:** Configurar ambiente local para testes antes de fazer deploy

---

## 📋 Passos para Configurar Ambiente Local

### 1. **Criar ficheiro `.env`**

Criar ficheiro `.env` na raiz do projeto:

```env
# Database Configuration
DB_HOST=localhost
DB_NAME=inventox
DB_USER=inventox
DB_PASS=inventox123
DB_PORT=3306

# Application Configuration
DEBUG_MODE=true
ENVIRONMENT=development
```

### 2. **Iniciar Docker Compose**

```bash
docker-compose up -d
```

Isso irá:
- ✅ Iniciar MySQL na porta 3306
- ✅ Iniciar Apache/PHP na porta 80
- ✅ Criar volumes para dados persistentes

### 3. **Inicializar Base de Dados**

Acessar no navegador:
```
http://localhost/api/init_database.php?token=inventox2024
```

Ou executar manualmente:
```bash
docker-compose exec db mysql -u inventox -pinventox123 inventox < db.sql
```

### 4. **Testar Aplicação**

Acessar no navegador:
```
http://localhost/frontend/
```

Login padrão:
- **Username:** `admin`
- **Password:** `admin123`

---

## 🧪 Testes a Realizar

### Checklist de Funcionalidades

- [ ] **Login funciona** - Fazer login e verificar se sessão é mantida
- [ ] **Criar empresa** - Criar uma nova empresa e verificar se é salva
- [ ] **Criar armazém** - Criar um novo armazém e associar a empresa
- [ ] **Criar artigo** - Criar um novo artigo e verificar se é salvo
- [ ] **Criar sessão** - Criar uma nova sessão de inventário
- [ ] **Criar utilizador** - Criar um novo utilizador e verificar login
- [ ] **Listar registos** - Verificar se listagens funcionam
- [ ] **Editar registos** - Editar registos existentes
- [ ] **Eliminar registos** - Eliminar registos (se aplicável)

---

## 🔍 Verificar Logs

### Ver Logs do Servidor

```bash
# Logs do Apache/PHP
docker-compose logs web

# Logs do MySQL
docker-compose logs db

# Logs em tempo real
docker-compose logs -f web
```

### Verificar Base de Dados

```bash
# Conectar ao MySQL
docker-compose exec db mysql -u inventox -pinventox123 inventox

# Verificar tabelas
SHOW TABLES;

# Verificar estrutura
DESCRIBE users;
DESCRIBE companies;
DESCRIBE warehouses;
DESCRIBE items;
DESCRIBE inventory_sessions;
```

---

## 🐛 Problemas Comuns

### 1. **Erro 401 (Unauthorized)**
**Solução:**
- Verificar se cookies estão sendo enviados (DevTools → Application → Cookies)
- Verificar configuração de sessão
- Verificar se `credentials: 'include'` está presente no frontend

### 2. **Erro 500 (Internal Server Error)**
**Solução:**
- Verificar logs: `docker-compose logs web`
- Verificar se tabelas existem: `SHOW TABLES;`
- Executar `init_database.php` ou `migrate_database.php`

### 3. **Erro de Conexão com Base de Dados**
**Solução:**
- Verificar se `.env` existe e tem valores corretos
- Verificar se MySQL está em execução: `docker-compose ps`
- Testar conexão: `docker-compose exec db mysql -u inventox -pinventox123 inventox`

---

## ✅ Quando Tudo Estiver Funcionando

Após testar localmente e confirmar que tudo funciona:

1. **Commitar alterações:**
   ```bash
   git add .
   git commit -m "Correções finais após testes locais"
   git push origin main
   ```

2. **Fazer deploy:**
   - O deploy será automático se estiver configurado com GitHub
   - Ou fazer deploy manual conforme necessário

---

**Última Atualização:** 2024-11-09

