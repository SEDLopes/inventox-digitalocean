# InventoX

Sistema moderno e responsivo para gestão de stock e inventário.

## 📋 Descrição

InventoX é uma solução completa de gestão de inventário que permite:

- ✅ Importação e exportação de artigos (CSV/XLSX)
- ✅ Contagem via leitura de código de barras (usando a câmara do telemóvel)
- ✅ Gestão de utilizadores (admin / operador)
- ✅ Controlo de sessões de inventário
- ✅ Interface web moderna e responsiva
- ✅ API RESTful em PHP
- ✅ Base de dados MySQL estruturada

## 🚀 Instalação Rápida

### Pré-requisitos

- Docker e Docker Compose instalados
- Python 3.8+ (para scripts de importação)
- Git (opcional)

### Passos

1. **Clonar o repositório** (ou descompactar):
```bash
git clone <repo-url>
cd InventoX
```

2. **Configurar variáveis de ambiente**:
```bash
cp .env.example .env
# Editar .env com as suas configurações
```

3. **Iniciar os serviços Docker**:
```bash
docker-compose up -d
```

4. **Criar a base de dados**:
```bash
docker exec -i inventox_db mysql -uroot -proot inventox < db.sql
```

5. **Instalar dependências Python**:
```bash
pip install -r requirements.txt
```

6. **Acessar a aplicação**:
   - Frontend: http://localhost:8080/frontend
   - API: http://localhost:8080/api
   - phpMyAdmin: http://localhost:8081

## 🔐 Credenciais Padrão

- **Username**: `admin`
- **Password**: `admin123`

⚠️ **IMPORTANTE**: Altere estas credenciais em produção!

## 📚 Documentação

- [Guia de Instalação](./INSTALLATION.md) - Instalação detalhada passo a passo
- [Referência da API](./API_REFERENCE.md) - Documentação completa da API
- [Estrutura da Base de Dados](./DB_STRUCTURE.md) - Schema e relacionamentos
- [Changelog](./CHANGELOG.md) - Histórico de alterações
- [Contribuindo](./CONTRIBUTING.md) - Como contribuir para o projeto

## 🛠️ Tecnologias

- **Backend**: PHP 8.2 + MySQL 8 (PDO)
- **Frontend**: HTML5, Tailwind CSS, JavaScript (ES6+)
- **Scanner**: ZXing JS Library
- **Importação**: Python 3 (pandas, sqlalchemy, pymysql)
- **Containerização**: Docker Compose

## 📁 Estrutura do Projeto

```
InventoX/
├── api/              # API PHP
├── frontend/         # Interface web
├── scripts/           # Scripts Python
├── docs/              # Documentação
├── uploads/           # Ficheiros enviados
├── logs/              # Logs da aplicação
├── docker-compose.yml # Configuração Docker
└── db.sql            # Schema da base de dados
```

## 🔒 Segurança

- Todas as senhas são armazenadas com `password_hash`
- Sanitização de todas as entradas
- Proteção contra SQL Injection (PDO Prepared Statements)
- Proteção contra XSS
- Configurações de segurança no `.htaccess`

## 🐛 Problemas Conhecidos

- O scanner de código de barras requer HTTPS em produção (limitação do navegador)
- A importação Python requer que o Docker tenha acesso ao Python local ou instalar dentro do container

## 📝 Licença

Consulte [LICENSE.md](./LICENSE.md)

## 🤝 Contribuições

Consulte [CONTRIBUTING.md](./CONTRIBUTING.md)

## 👥 Autores

Desenvolvido para gestão de inventário.

## 📧 Suporte

Para questões ou problemas, abra uma issue no repositório.

