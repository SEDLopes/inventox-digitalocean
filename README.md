# 📦 InventoX - Sistema de Gestão de Inventário

Sistema completo de gestão de inventário com digitalização de códigos de barras, otimizado para dispositivos móveis.

## 🚀 **Funcionalidades**

- ✅ **Scanner de Códigos de Barras** (câmara móvel otimizada)
- ✅ **Gestão de Inventário** (artigos, categorias, armazéns)
- ✅ **Importação CSV/XLSX** (mapeamento inteligente de colunas)
- ✅ **Busca por Códigos de Referência** (busca parcial)
- ✅ **Interface Mobile-First** (iOS/Android otimizado)
- ✅ **Sessões de Inventário** (contagens organizadas)
- ✅ **Relatórios e Exportação** (dados detalhados)
- ✅ **Gestão de Utilizadores** (admin/operador)

## 🛠️ **Tecnologias**

- **Frontend**: HTML5, CSS3, JavaScript (Vanilla)
- **Backend**: PHP 8.2 + Apache
- **Base de Dados**: MySQL 8.0
- **Scanner**: ZXing-js (WebRTC)
- **Import**: Python (pandas, openpyxl)
- **Deploy**: Railway (Docker/Nixpacks)

## 🌐 **Deploy no Railway**

Este projeto está configurado para deploy automático no Railway:

1. **Conecte este repositório ao Railway**
2. **Adicione MySQL service**
3. **Deploy automático** (via Git push)

### Arquivos de Configuração:
- `railway.json` - Configuração Railway
- `nixpacks.toml` - Build settings
- `api/health.php` - Health check
- `.htaccess` - Apache config

## 📱 **Uso Mobile**

O sistema detecta automaticamente dispositivos móveis e:
- 📷 **Força câmara traseira** por padrão
- 🔄 **Botão para trocar câmara** (frontal/traseira)
- 📳 **Vibração** ao detectar código
- 🎯 **Interface otimizada** para touch

## 🗄️ **Base de Dados**

Execute `db_init_railway.sql` no MySQL do Railway para inicializar:
- 👤 **Admin**: `admin` / `admin123`
- 📦 **Dados de exemplo** incluídos
- 🏗️ **Schema completo** com índices

## 📋 **APIs Disponíveis**

- `GET /api/health.php` - Health check
- `POST /api/login.php` - Autenticação
- `GET /api/items.php` - Listar artigos
- `GET /api/get_item.php?barcode=X` - Buscar artigo
- `POST /api/items_import.php` - Importar CSV/XLSX
- `GET /api/stats.php` - Estatísticas
- `GET /api/session_count.php` - Sessões

## 🔧 **Desenvolvimento Local**

```bash
# Docker Compose
docker-compose up -d

# URLs
Frontend: http://localhost:8080/frontend/
API: http://localhost:8080/api/
phpMyAdmin: http://localhost:8081/
```

## 📄 **Licença**

MIT License - Uso livre para projetos pessoais e comerciais.

---

**🚀 Deploy automático no Railway - Push para main branch!**
