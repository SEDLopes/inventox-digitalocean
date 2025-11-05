# Guia de Instalação - InventoX

Guia detalhado de instalação do InventoX.

## 📋 Pré-requisitos

### Software Necessário

1. **Docker Desktop**
   - Windows: [Docker Desktop para Windows](https://www.docker.com/products/docker-desktop)
   - macOS: [Docker Desktop para Mac](https://www.docker.com/products/docker-desktop)
   - Linux: [Docker Engine](https://docs.docker.com/engine/install/)

2. **Docker Compose** (geralmente incluído no Docker Desktop)
   - Versão 3.8 ou superior

3. **Python 3.8+** (para scripts de importação)
   - [Python Downloads](https://www.python.org/downloads/)

4. **Git** (opcional, para clonar o repositório)

### Verificar Instalações

```bash
# Verificar Docker
docker --version
docker-compose --version

# Verificar Python
python3 --version

# Verificar Git (opcional)
git --version
```

## 🚀 Instalação Passo a Passo

### 1. Obter o Projeto

**Opção A: Clonar repositório Git**
```bash
git clone <repository-url>
cd InventoX
```

**Opção B: Descompactar arquivo ZIP**
```bash
unzip InventoX.zip
cd InventoX
```

### 2. Configurar Variáveis de Ambiente

Copiar o ficheiro de exemplo e editar:
```bash
cp .env.example .env
```

Editar o ficheiro `.env` com as suas configurações:
```env
DB_HOST=mysql
DB_NAME=inventox
DB_USER=inventox_user
DB_PASS=change_me_para_senha_segura
DB_PORT=3306
```

⚠️ **IMPORTANTE**: Em produção, altere todas as senhas padrão!

### 3. Iniciar Serviços Docker

```bash
# Construir e iniciar containers
docker-compose up -d

# Verificar status
docker-compose ps
```

Os seguintes serviços serão iniciados:
- **MySQL** (porta 3306)
- **PHP Apache** (porta 8080)
- **phpMyAdmin** (porta 8081)

### 4. Aguardar MySQL Inicializar

Aguarde alguns segundos para o MySQL estar totalmente pronto:
```bash
# Ver logs do MySQL
docker-compose logs mysql

# Verificar quando estiver pronto (procure por "ready for connections")
```

### 5. Criar Base de Dados

```bash
# Importar schema
docker exec -i inventox_db mysql -uroot -proot inventox < db.sql

# Verificar criação
docker exec -it inventox_db mysql -uroot -proot -e "USE inventox; SHOW TABLES;"
```

### 6. Instalar Dependências Python

```bash
# Instalar dependências
pip install -r requirements.txt

# Ou usando pip3
pip3 install -r requirements.txt

# Verificar instalação
python3 scripts/import_items.py --help
```

### 7. Configurar Permissões (Linux/macOS)

```bash
# Criar diretórios se necessário
mkdir -p uploads logs

# Dar permissões (se necessário)
chmod -R 755 uploads logs
```

### 8. Verificar Instalação

Acesse os seguintes URLs:

- **Frontend**: http://localhost:8080/frontend
- **API**: http://localhost:8080/api
- **phpMyAdmin**: http://localhost:8081

### 9. Fazer Login

Utilize as credenciais padrão:
- **Username**: `admin`
- **Password**: `admin123`

## 🔧 Configuração Avançada

### Personalizar Portas

Editar `docker-compose.yml`:
```yaml
services:
  php-apache:
    ports:
      - "8080:80"  # Alterar 8080 para porta desejada
```

### Configurar Volume Persistente

Por padrão, os dados MySQL são guardados em `./db_data`. Para usar um volume nomeado:
```yaml
volumes:
  mysql_data:
    driver: local

services:
  mysql:
    volumes:
      - mysql_data:/var/lib/mysql
```

### Habilitar Extensões PHP

Editar `docker-compose.yml` no serviço `php-apache`:
```yaml
command: >
  bash -c "docker-php-ext-install pdo pdo_mysql mysqli opcache &&
  apache2-foreground"
```

### Configurar Logs

Os logs são guardados em:
- `/logs/` - Logs da aplicação
- `docker-compose logs` - Logs dos containers

Para ver logs em tempo real:
```bash
docker-compose logs -f
```

## 🐛 Resolução de Problemas

### Porta já em uso

**Erro**: `Bind for 0.0.0.0:8080 failed: port is already allocated`

**Solução**: Alterar a porta no `docker-compose.yml` ou parar o serviço que está a usar a porta:
```bash
# Verificar o que está a usar a porta
lsof -i :8080  # macOS/Linux
netstat -ano | findstr :8080  # Windows
```

### MySQL não inicia

**Erro**: MySQL container para ou reinicia constantemente

**Solução**:
1. Verificar logs: `docker-compose logs mysql`
2. Verificar permissões do diretório `db_data`
3. Remover `db_data` e reiniciar (⚠️ perde dados)

### Erro de conexão à base de dados

**Erro**: `Connection refused` ou `Access denied`

**Solução**:
1. Verificar se MySQL está a correr: `docker-compose ps`
2. Verificar credenciais no `.env`
3. Aguardar alguns segundos após iniciar containers
4. Verificar variáveis de ambiente no container:
```bash
docker exec inventox_db env | grep MYSQL
```

### Scanner não funciona

**Erro**: Câmara não é acessível ou scanner não detecta códigos

**Solução**:
1. Verificar permissões da câmara no navegador
2. Usar HTTPS em produção (requisito do navegador)
3. Testar com códigos de barras bem iluminados e focados

### Importação Python falha

**Erro**: `ModuleNotFoundError` ou erro de conexão

**Solução**:
1. Verificar instalação: `pip list | grep pandas`
2. Verificar variáveis de ambiente no `.env`
3. Testar conexão manual:
```python
python3 -c "import pymysql; print('OK')"
```

## 📦 Atualização

### Atualizar Código

```bash
# Se usando Git
git pull origin main

# Parar containers
docker-compose down

# Reconstruir (se houver mudanças)
docker-compose up -d --build
```

### Atualizar Base de Dados

```bash
# Fazer backup primeiro!
docker exec inventox_db mysqldump -uroot -proot inventox > backup.sql

# Aplicar migrações (se houver)
docker exec -i inventox_db mysql -uroot -proot inventox < migration.sql
```

## 🗑️ Desinstalação

### Parar e Remover Containers

```bash
# Parar serviços
docker-compose down

# Remover volumes (⚠️ apaga dados)
docker-compose down -v
```

### Limpar Dados Completamente

```bash
# Remover containers e volumes
docker-compose down -v

# Remover diretório de dados MySQL
rm -rf db_data

# Remover uploads e logs (opcional)
rm -rf uploads/* logs/*
```

## ✅ Checklist de Instalação

- [ ] Docker e Docker Compose instalados
- [ ] Python 3.8+ instalado
- [ ] Ficheiro `.env` configurado
- [ ] Containers Docker a correr (`docker-compose ps`)
- [ ] Base de dados criada (verificar com phpMyAdmin)
- [ ] Dependências Python instaladas
- [ ] Frontend acessível em http://localhost:8080/frontend
- [ ] Login funciona com credenciais padrão

## 📞 Suporte

Se encontrar problemas:
1. Verificar logs: `docker-compose logs`
2. Consultar [README.md](./README.md)
3. Abrir uma issue no repositório

