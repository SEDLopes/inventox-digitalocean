#!/bin/bash

# InventoX - Script de Inicialização
# Script para configurar e iniciar o projeto rapidamente

set -e

echo "🚀 InventoX - Script de Inicialização"
echo "======================================"
echo ""

# Cores para output
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

# Verificar Docker
if ! command -v docker &> /dev/null; then
    echo -e "${RED}❌ Docker não encontrado. Instale o Docker primeiro.${NC}"
    exit 1
fi

if ! command -v docker-compose &> /dev/null; then
    echo -e "${RED}❌ Docker Compose não encontrado. Instale o Docker Compose primeiro.${NC}"
    exit 1
fi

echo -e "${GREEN}✅ Docker encontrado${NC}"

# Criar ficheiro .env se não existir
if [ ! -f .env ]; then
    echo ""
    echo -e "${YELLOW}📝 Criando ficheiro .env...${NC}"
    cat > .env << 'EOF'
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
EOF
    echo -e "${GREEN}✅ Ficheiro .env criado${NC}"
else
    echo -e "${GREEN}✅ Ficheiro .env já existe${NC}"
fi

# Criar diretórios necessários
echo ""
echo -e "${YELLOW}📁 Criando diretórios...${NC}"
mkdir -p uploads logs
touch uploads/.gitkeep logs/.gitkeep
echo -e "${GREEN}✅ Diretórios criados${NC}"

# Construir e iniciar containers
echo ""
echo -e "${YELLOW}🐳 Iniciando containers Docker...${NC}"
docker-compose up -d --build

# Aguardar MySQL
echo ""
echo -e "${YELLOW}⏳ Aguardando MySQL iniciar (15 segundos)...${NC}"
sleep 15

# Verificar se MySQL está pronto
MAX_RETRIES=10
RETRY=0
while [ $RETRY -lt $MAX_RETRIES ]; do
    if docker exec inventox_db mysqladmin ping -h localhost -uroot -proot --silent; then
        echo -e "${GREEN}✅ MySQL está pronto${NC}"
        break
    fi
    RETRY=$((RETRY+1))
    echo -e "${YELLOW}⏳ Aguardando MySQL... (tentativa $RETRY/$MAX_RETRIES)${NC}"
    sleep 3
done

if [ $RETRY -eq $MAX_RETRIES ]; then
    echo -e "${RED}❌ Timeout aguardando MySQL. Verifique os logs com: docker-compose logs mysql${NC}"
    exit 1
fi

# Criar base de dados
echo ""
echo -e "${YELLOW}🗄️  Criando base de dados...${NC}"
if docker exec -i inventox_db mysql -uroot -proot < db.sql; then
    echo -e "${GREEN}✅ Base de dados criada${NC}"
else
    echo -e "${RED}❌ Erro ao criar base de dados${NC}"
    exit 1
fi

# Inserir dados de exemplo (opcional)
if [ -f exemplo_dados.sql ]; then
    echo ""
    read -p "Deseja inserir dados de exemplo? (s/n): " -n 1 -r
    echo
    if [[ $REPLY =~ ^[Ss]$ ]]; then
        echo -e "${YELLOW}📦 Inserindo dados de exemplo...${NC}"
        docker exec -i inventox_db mysql -uroot -proot inventox < exemplo_dados.sql
        echo -e "${GREEN}✅ Dados de exemplo inseridos${NC}"
    fi
fi

# Instalar dependências Python (se Python estiver disponível)
if command -v python3 &> /dev/null || command -v python &> /dev/null; then
    echo ""
    read -p "Deseja instalar dependências Python? (s/n): " -n 1 -r
    echo
    if [[ $REPLY =~ ^[Ss]$ ]]; then
        echo -e "${YELLOW}🐍 Instalando dependências Python...${NC}"
        if command -v pip3 &> /dev/null; then
            pip3 install -r requirements.txt
        elif command -v pip &> /dev/null; then
            pip install -r requirements.txt
        fi
        echo -e "${GREEN}✅ Dependências Python instaladas${NC}"
    fi
fi

# Resumo
echo ""
echo "======================================"
echo -e "${GREEN}✅ Instalação concluída!${NC}"
echo "======================================"
echo ""
echo "🌐 Acesse:"
echo "   • Frontend:  http://localhost:8080/frontend"
echo "   • phpMyAdmin: http://localhost:8081"
echo ""
echo "🔐 Credenciais padrão:"
echo "   • Username: admin"
echo "   • Password: admin123"
echo ""
echo "📚 Comandos úteis:"
echo "   • Ver logs:        docker-compose logs -f"
echo "   • Parar:           docker-compose down"
echo "   • Reiniciar:       docker-compose restart"
echo ""
echo "⚠️  IMPORTANTE: Altere a senha padrão em produção!"
echo ""

