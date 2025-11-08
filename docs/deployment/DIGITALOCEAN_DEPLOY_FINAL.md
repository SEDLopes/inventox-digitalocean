# 🚀 Deploy Final no DigitalOcean

Guia completo para fazer deploy do InventoX no DigitalOcean App Platform.

## 📋 Pré-requisitos

- ✅ Conta DigitalOcean ativa
- ✅ Repositório GitHub (`SEDLopes/inventox-app`)
- ✅ Código organizado e limpo

## 🎯 Passo a Passo

### 1. Criar App Platform

1. Acesse [DigitalOcean Dashboard](https://cloud.digitalocean.com/)
2. Clique em **Apps** → **Create App**
3. Selecione **GitHub** como fonte
4. Autorize DigitalOcean a acessar seu GitHub (se necessário)
5. Selecione o repositório: `SEDLopes/inventox-app`
6. Selecione branch: `main`

### 2. Configuração Automática

O DigitalOcean detectará automaticamente:
- ✅ `Dockerfile` - Para build da aplicação
- ✅ `.do/app.yaml` - Para configuração do app

### 3. Configurar Database

1. Na tela de configuração, clique em **Add Database**
2. Selecione **MySQL 8**
3. Nome: `inventox-db`
4. Plano: **Basic** (para testes)
5. Clique em **Add Database**

### 4. Variáveis de Ambiente

As variáveis serão configuradas automaticamente via `.do/app.yaml`:
- `DB_HOST` → `${inventox-db.HOSTNAME}`
- `DB_NAME` → `${inventox-db.DATABASE}`
- `DB_USER` → `${inventox-db.USERNAME}`
- `DB_PASS` → `${inventox-db.PASSWORD}`
- `DB_PORT` → `${inventox-db.PORT}`

### 5. Inicializar Database

Após o deploy, acesse:
```
https://seu-app.ondigitalocean.app/api/init_database.php?token=inventox2024
```

Isso criará todas as tabelas e dados iniciais.

### 6. Verificar Deploy

1. Acesse a URL do app (fornecida pelo DigitalOcean)
2. Teste o endpoint de health:
   ```
   https://seu-app.ondigitalocean.app/api/health.php
   ```
3. Acesse a aplicação:
   ```
   https://seu-app.ondigitalocean.app/frontend/
   ```

## 🔧 Troubleshooting

### Build Falha

- Verifique os logs no DigitalOcean Dashboard
- Certifique-se que o `Dockerfile` está correto
- Verifique se todas as dependências estão instaladas

### PHP não executa

- Verifique se o Apache está rodando
- Teste o endpoint `/api/health.php`
- Verifique os logs do container

### Database não conecta

- Verifique as variáveis de ambiente
- Teste a conexão manualmente
- Verifique se o database está rodando

## 📊 Estrutura de Arquivos

```
InventoX/
├── Dockerfile          # Build Docker
├── .do/
│   └── app.yaml        # Config DigitalOcean
├── frontend/           # Interface web
├── api/                # API PHP
└── docs/               # Documentação
```

## ✅ Checklist Final

- [ ] App Platform criado
- [ ] Database MySQL configurado
- [ ] Deploy bem-sucedido
- [ ] Database inicializado
- [ ] Health check funcionando
- [ ] Aplicação acessível

## 🎉 Pronto!

Após completar estes passos, sua aplicação estará rodando no DigitalOcean!

Para atualizações futuras, basta fazer `git push` para o repositório GitHub e o DigitalOcean fará deploy automático.
