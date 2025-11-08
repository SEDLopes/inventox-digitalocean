# 🔐 Erro 401 (Unauthorized) no Login

Guia para resolver o erro 401 ao fazer login no InventoX.

## ❌ Problema

Ao tentar fazer login, você recebe:
```
Error: HTTP error! status: 401
Erro no login: Error: HTTP error! status: 401
```

## 🔍 Causas Possíveis

### 1. Credenciais Incorretas

**Solução:**
- Verificar se está usando as credenciais corretas:
  - **Usuário:** `admin`
  - **Senha:** `admin123`
- Certificar-se que não há espaços extras
- Verificar se Caps Lock está desativado

### 2. Utilizador Não Existe na Database

**Solução:**
1. Verificar se a database foi inicializada:
   ```
   https://seu-app.ondigitalocean.app/api/init_database.php?token=inventox2024
   ```

2. Verificar se o utilizador `admin` foi criado:
   - Acesse o console da database no DigitalOcean
   - Execute: `SELECT * FROM users WHERE username = 'admin';`

### 3. Password Hash Incorreto

**Solução:**
Se o utilizador existe mas o password não funciona:

1. **Opção A: Re-inicializar Database**
   ```
   https://seu-app.ondigitalocean.app/api/init_database.php?token=inventox2024
   ```
   Isso recriará o utilizador `admin` com password `admin123`.

2. **Opção B: Atualizar Password Manualmente**
   - Acesse o console da database
   - Execute:
     ```sql
     UPDATE users 
     SET password_hash = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi' 
     WHERE username = 'admin';
     ```
   - Este hash corresponde à senha: `admin123`

### 4. Problema com a API

**Solução:**
1. Testar a API diretamente:
   ```bash
   curl -X POST https://seu-app.ondigitalocean.app/api/login.php \
     -H "Content-Type: application/json" \
     -d '{"username":"admin","password":"admin123"}'
   ```

2. Verificar logs do servidor no DigitalOcean Dashboard

3. Verificar se a API está acessível:
   ```
   https://seu-app.ondigitalocean.app/api/health.php
   ```

## ✅ Checklist de Verificação

- [ ] Database inicializada
- [ ] Utilizador `admin` existe na database
- [ ] Credenciais corretas (`admin` / `admin123`)
- [ ] API `/api/login.php` está acessível
- [ ] Sem espaços extras nas credenciais
- [ ] Caps Lock desativado

## 🔧 Solução Rápida

1. **Re-inicializar Database:**
   ```
   https://seu-app.ondigitalocean.app/api/init_database.php?token=inventox2024
   ```

2. **Aguardar 1-2 minutos**

3. **Tentar login novamente:**
   - Usuário: `admin`
   - Senha: `admin123`

## 🐛 Debug Avançado

### Verificar Logs do Servidor

1. Acesse DigitalOcean Dashboard
2. Vá para **Apps** → Seu app → **Runtime Logs**
3. Procure por erros relacionados a login

### Testar API Diretamente

```bash
# Teste básico
curl -X POST https://seu-app.ondigitalocean.app/api/login.php \
  -H "Content-Type: application/json" \
  -d '{"username":"admin","password":"admin123"}'

# Resposta esperada:
# {"success":true,"message":"Login realizado com sucesso","user":{...}}
```

### Verificar Database

```sql
-- Verificar se utilizador existe
SELECT id, username, email, role, is_active 
FROM users 
WHERE username = 'admin';

-- Verificar password hash
SELECT username, password_hash 
FROM users 
WHERE username = 'admin';
```

## 🎯 Solução Definitiva

Se nada funcionar:

1. **Re-inicializar Database completamente:**
   ```
   https://seu-app.ondigitalocean.app/api/init_database.php?token=inventox2024
   ```

2. **Aguardar 2-3 minutos**

3. **Tentar login:**
   - Usuário: `admin`
   - Senha: `admin123`

4. **Se ainda não funcionar:**
   - Verificar logs do servidor
   - Verificar se a database está conectada
   - Verificar variáveis de ambiente

## 📚 Documentação Relacionada

- `docs/deployment/INITIAL_SETUP.md` - Setup inicial
- `docs/deployment/CONFIG_ENV_VARS.md` - Variáveis de ambiente
