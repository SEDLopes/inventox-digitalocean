# InventoX - Guia de Início Rápido

Este é um guia rápido para começar a usar o InventoX.

## ⚡ Início Rápido

### 1. Configurar Ambiente

Crie um ficheiro `.env` na raiz do projeto com o seguinte conteúdo:

```env
# Configuração da Base de Dados
DB_HOST=mysql
DB_NAME=inventox
DB_USER=inventox_user
DB_PASS=change_me
DB_PORT=3306

# Configuração da API
API_BASE_URL=http://localhost:8080/api
FRONTEND_URL=http://localhost:8080/frontend

# Configurações de Segurança
JWT_SECRET=your_secret_key_here_change_in_production
SESSION_TIMEOUT=3600

# Configurações de Upload
MAX_UPLOAD_SIZE=10M
ALLOWED_FILE_TYPES=csv,xlsx,xls

# Configurações de Log
LOG_LEVEL=INFO
LOG_FILE_PATH=/var/www/html/logs/app.log

# Ambiente
APP_ENV=development
APP_DEBUG=true
```

### 2. Iniciar Docker Compose

```bash
docker-compose up -d
```

### 3. Criar Base de Dados

```bash
docker exec -i inventox_db mysql -uroot -proot inventox < db.sql
```

### 4. Instalar Dependências Python

```bash
pip install -r requirements.txt
```

### 5. Acessar Aplicação

- **Frontend**: http://localhost:8080/frontend
- **phpMyAdmin**: http://localhost:8081

### 6. Fazer Login

- **Username**: `admin`
- **Password**: `admin123`

## 📚 Documentação Completa

Consulte a pasta `/docs` para documentação detalhada:
- [README.md](docs/README.md) - Visão geral
- [INSTALLATION.md](docs/INSTALLATION.md) - Instalação detalhada
- [API_REFERENCE.md](docs/API_REFERENCE.md) - Referência da API
- [DB_STRUCTURE.md](docs/DB_STRUCTURE.md) - Estrutura da base de dados

## 🐛 Problemas?

Consulte a secção de resolução de problemas no [INSTALLATION.md](docs/INSTALLATION.md).

## 🎯 Próximos Passos

1. Altere as senhas padrão em `.env`
2. Importe os seus artigos via ficheiro CSV/XLSX
3. Crie a sua primeira sessão de inventário
4. Comece a fazer contagens usando o scanner

---

**Nota**: Este é um guia rápido. Para mais detalhes, consulte a documentação completa.

