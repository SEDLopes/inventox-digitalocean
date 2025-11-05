# 🚂 Deploy no Railway - InventoX

## 📋 Passos para Deploy

### 1. Pré-requisitos
- ✅ Conta no Railway (gratuita)
- ✅ Railway CLI instalado
- ✅ Login feito no Railway CLI

### 2. Configuração do Projeto
```bash
# Criar projeto
railway init

# Adicionar MySQL
railway add mysql

# Deploy
railway up
```

### 3. Variáveis de Ambiente
O Railway configurará automaticamente:
- `DATABASE_URL` - Conexão MySQL
- `MYSQL_URL` - URL alternativa
- `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS` - Credenciais individuais

### 4. Estrutura de Arquivos
```
/
├── frontend/          # Interface web
├── api/              # APIs PHP
├── scripts/          # Scripts Python
├── uploads/          # Uploads (criado automaticamente)
├── db.sql           # Schema da base de dados
├── railway.json     # Configuração Railway
├── nixpacks.toml    # Build configuration
└── .htaccess        # Apache configuration
```

### 5. URLs após Deploy
- **Frontend**: `https://seu-projeto.railway.app/frontend/`
- **API**: `https://seu-projeto.railway.app/api/`
- **Health Check**: `https://seu-projeto.railway.app/api/health.php`

### 6. Comandos Úteis
```bash
# Ver logs
railway logs

# Abrir no browser
railway open

# Ver variáveis
railway variables

# Conectar à base de dados
railway connect mysql
```

## 🔧 Troubleshooting

### Problema: Base de dados não inicializada
```bash
# Executar schema manualmente
railway connect mysql
# Depois copiar e colar o conteúdo de db.sql
```

### Problema: Uploads não funcionam
- Verificar se a pasta `uploads/` tem permissões de escrita
- Railway cria automaticamente, mas pode precisar de reinicialização

### Problema: CORS
- Verificar se `.htaccess` está configurado corretamente
- Railway usa Apache por padrão com PHP
