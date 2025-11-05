# 🚂 Instruções de Setup Railway - InventoX

## 🎯 **PASSO A PASSO COMPLETO**

### 1️⃣ **Login no Railway CLI (OBRIGATÓRIO)**
```bash
cd "/Users/SandroLopes/Documents/CURSOR AI/InventoX"
export PATH="$HOME/.railway/bin:$PATH"
railway login
```
**➡️ Isso abrirá o browser. Faça login na sua conta Railway.**

### 2️⃣ **Conectar ao Projeto Existente (com MySQL)**
```bash
railway link
```
**➡️ Selecione o projeto que já tem MySQL configurado.**

**OU criar novo projeto:**
```bash
railway init
railway add mysql
```

### 3️⃣ **Deploy do Código**
```bash
railway up
```

### 4️⃣ **Configurar Variáveis de Ambiente (Automático)**
O Railway configurará automaticamente:
- `DATABASE_URL`
- `MYSQL_URL` 
- `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS`

### 5️⃣ **Inicializar Base de Dados**
```bash
# Conectar à base de dados
railway connect mysql

# Executar o schema (copiar e colar o conteúdo de db.sql)
```

### 6️⃣ **Verificar Deploy**
```bash
# Ver logs
railway logs

# Abrir no browser
railway open
```

---

## 🌐 **URLs após Deploy**

- **Frontend**: `https://seu-projeto.railway.app/frontend/`
- **API**: `https://seu-projeto.railway.app/api/`
- **Health Check**: `https://seu-projeto.railway.app/api/health.php`

---

## 📋 **Arquivos Preparados**

✅ **railway.json** - Configuração do Railway
✅ **nixpacks.toml** - Build configuration  
✅ **api/health.php** - Health check endpoint
✅ **.htaccess** - Apache configuration
✅ **.gitignore** - Git ignore rules
✅ **uploads/** - Pasta para uploads

---

## 🔧 **Comandos Úteis**

```bash
# Ver status
railway status

# Ver variáveis
railway variables

# Ver logs em tempo real
railway logs --follow

# Conectar à base de dados
railway connect mysql

# Redeploy
railway up --detach
```

---

## 🚨 **IMPORTANTE**

1. **Faça login primeiro**: `railway login`
2. **Conecte ao projeto**: `railway link` 
3. **Deploy**: `railway up`
4. **Inicialize a BD**: Copie `db.sql` para o MySQL

---

## 🎉 **Após Deploy**

1. Acesse: `https://seu-projeto.railway.app/frontend/`
2. Login: `admin` / `admin123`
3. Teste todas as funcionalidades
4. Importe dados CSV/XLSX
5. Teste scanner em dispositivos móveis

---

**🚀 EXECUTE OS COMANDOS ACIMA PARA COMPLETAR O DEPLOY!**
