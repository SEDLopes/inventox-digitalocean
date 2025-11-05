# 🚀 Deploy InventoX via GitHub → Railway

## 📋 **Método Mais Fácil: GitHub Integration**

### **Passo 1: Criar Repositório no GitHub**

1. **Acesse**: https://github.com/new
2. **Nome**: `inventox-system`
3. **Descrição**: `Sistema de Gestão de Inventário com Scanner Mobile`
4. **Público** ou **Privado** (sua escolha)
5. **NÃO** inicializar com README (já temos)
6. **Criar repositório**

### **Passo 2: Fazer Upload do Código**

**Execute no seu terminal:**

```bash
cd "/Users/SandroLopes/Documents/CURSOR AI/InventoX"

# Adicionar remote do GitHub (substitua SEU_USERNAME)
git remote add origin https://github.com/SEU_USERNAME/inventox-system.git

# Push do código
git branch -M main
git push -u origin main
```

### **Passo 3: Conectar GitHub ao Railway**

1. **Acesse**: https://railway.app/dashboard
2. **New Project** → **Deploy from GitHub repo**
3. **Selecione**: `inventox-system`
4. **Deploy**

### **Passo 4: Adicionar MySQL**

1. **No projeto Railway**: **+ New** → **Database** → **Add MySQL**
2. **Aguardar** MySQL inicializar
3. **Conectar**: `railway connect mysql`
4. **Executar**: Copiar conteúdo de `db_init_railway.sql`

### **Passo 5: Configurar Variáveis (Automático)**

O Railway configurará automaticamente:
- `DATABASE_URL`
- `MYSQL_URL`
- `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS`

---

## 🎯 **URLs Finais**

Após deploy:
- **Frontend**: `https://inventox-system-production.up.railway.app/frontend/`
- **API**: `https://inventox-system-production.up.railway.app/api/`
- **Health**: `https://inventox-system-production.up.railway.app/api/health.php`

---

## 🔧 **Comandos Úteis**

```bash
# Ver logs (se tiver Railway CLI)
railway logs

# Redeploy (push novo commit)
git add .
git commit -m "Update"
git push

# Conectar à BD
railway connect mysql
```

---

## ✅ **Vantagens desta Abordagem**

1. ✅ **Deploy automático** a cada push
2. ✅ **Sem Railway CLI** necessário
3. ✅ **Interface web** fácil de usar
4. ✅ **Logs visuais** no dashboard
5. ✅ **Rollback fácil** se necessário

---

## 🚨 **IMPORTANTE**

1. **Substitua `SEU_USERNAME`** pelo seu username GitHub
2. **Copie o URL correto** do seu repositório
3. **Execute `db_init_railway.sql`** no MySQL
4. **Teste o health check** primeiro

---

**🎉 EXECUTE OS PASSOS ACIMA PARA DEPLOY AUTOMÁTICO!**
