# InventoX - Guia de Início Rápido 🚀

Guia rápido para começar a usar o InventoX em menos de 5 minutos.

## 📋 Pré-requisitos

- Docker e Docker Compose instalados
- Python 3.8+ (opcional, para scripts)

## ⚡ Instalação Automática (Recomendado)

Se você tem `make` instalado:

```bash
make install
```

Isso irá:
1. Criar o ficheiro `.env`
2. Iniciar todos os containers
3. Criar a base de dados
4. Inserir dados de exemplo
5. Instalar dependências Python

## 📦 Instalação Manual

### 1. Criar ficheiro `.env`

Copie e cole no terminal:

```bash
cat > .env << 'EOF'
DB_HOST=mysql
DB_NAME=inventox
DB_USER=inventox_user
DB_PASS=change_me
DB_PORT=3306
EOF
```

### 2. Iniciar Docker Compose

```bash
docker-compose up -d
```

### 3. Aguardar MySQL (10-15 segundos)

```bash
# Verificar quando estiver pronto
docker-compose logs mysql | grep "ready for connections"
```

### 4. Criar Base de Dados

```bash
docker exec -i inventox_db mysql -uroot -proot inventox < db.sql
```

### 5. (Opcional) Inserir Dados de Exemplo

```bash
docker exec -i inventox_db mysql -uroot -proot inventox < exemplo_dados.sql
```

### 6. Instalar Dependências Python

```bash
pip install -r requirements.txt
# ou
pip3 install -r requirements.txt
```

## 🎯 Acessar Aplicação

- **Frontend**: http://localhost:8080/frontend
- **phpMyAdmin**: http://localhost:8081

## 🔐 Login

- **Username**: `admin`
- **Password**: `admin123`

⚠️ **IMPORTANTE**: Altere a senha em produção!

## 📥 Importar Artigos

1. Acesse o frontend e faça login
2. Vá ao tab "Importar"
3. Selecione o ficheiro `exemplo_importacao.csv` (na raiz do projeto)
4. Clique em "Carregar Ficheiro"

## 🎬 Primeira Sessão de Inventário

1. Vá ao tab "Scanner"
2. Clique em "Criar Nova Sessão"
   - Nome: "Inventário Inicial"
   - Descrição: "Primeiro inventário"
3. Clique em "Iniciar Scanner"
4. Leia um código de barras ou digite manualmente
5. Ajuste a quantidade contada
6. Clique em "Guardar Contagem"

## 🛠️ Comandos Úteis (com Make)

```bash
make help          # Ver todos os comandos
make up            # Iniciar containers
make down          # Parar containers
make logs          # Ver logs
make db-reset       # Resetar base de dados
make status        # Ver status dos containers
```

## 🐛 Problemas?

### Porta já em uso?

Edite `docker-compose.yml` e altere as portas:
- `8080:80` → `8082:80` (PHP)
- `8081:80` → `8083:80` (phpMyAdmin)

### MySQL não inicia?

```bash
# Ver logs
docker-compose logs mysql

# Resetar dados (⚠️ apaga tudo)
rm -rf db_data
docker-compose up -d
```

### Erro de conexão?

1. Verifique se MySQL está rodando: `docker-compose ps`
2. Aguarde mais alguns segundos após iniciar
3. Verifique o ficheiro `.env`

## 📚 Próximos Passos

- Leia [docs/INSTALLATION.md](docs/INSTALLATION.md) para instalação detalhada
- Consulte [docs/API_REFERENCE.md](docs/API_REFERENCE.md) para usar a API
- Veja [docs/DB_STRUCTURE.md](docs/DB_STRUCTURE.md) para entender a base de dados

## ✨ Funcionalidades

- ✅ Scanner de código de barras (câmara do dispositivo)
- ✅ Gestão de sessões de inventário
- ✅ Importação CSV/XLSX
- ✅ Exportação JSON/CSV
- ✅ Interface responsiva (mobile-friendly)
- ✅ API RESTful completa

---

**Divirta-se usando o InventoX!** 🎉

