# Deploy InventoX no Fly.io com MySQL no Railway

Este guia publica o InventoX com um domínio gratuito `*.fly.dev` (HTTPS incluído) e usa uma base de dados MySQL gratuita no Railway.

## Pré-requisitos
- Conta no Fly.io e Railway
- CLI: `flyctl` e `railway`
- Docker instalado

## 1) Instalar CLIs
```bash
# Fly.io (macOS via Homebrew)
brew install flyctl

# Railway (Node 16+)
npm i -g @railway/cli
```

## 2) Login nas plataformas
```bash
fly auth login
railway login
```

## 3) Criar MySQL no Railway
```bash
# Inicializar projeto (se ainda não existir)
railway init --project InventoX

# Adicionar serviço MySQL
railway add --plugin mysql
```
- No painel do Railway → serviço MySQL → separador Variables: anota `MYSQLHOST, MYSQLPORT, MYSQLUSER, MYSQLPASSWORD, MYSQLDATABASE`.

### 3.1) Carregar schema
```bash
mysql -h <MYSQLHOST> -P <MYSQLPORT> -u <MYSQLUSER> -p<MYSQLPASSWORD> <MYSQLDATABASE> < db.sql
```

## 4) Configurar Fly.io (uma vez)
Ficheiros já incluídos no repositório:
- `fly.toml` (config da app)
- `Dockerfile` (build do PHP-Apache + Python)

```bash
# Inicializar app (não faz deploy)
make fly-launch

# Definir variáveis de ambiente (secrets)
fly secrets set \
  DB_HOST=<MYSQLHOST> \
  DB_PORT=<MYSQLPORT> \
  DB_NAME=<MYSQLDATABASE> \
  DB_USER=<MYSQLUSER> \
  DB_PASS=<MYSQLPASSWORD>
```

## 5) Deploy
```bash
make fly-deploy
```
No fim, a CLI mostrará o URL da app, ex.: `https://inventox.fly.dev`.

## 6) Testes rápidos
- Frontend: `https://<app>.fly.dev/frontend/`
- API: `https://<app>.fly.dev/api/stats.php`
- Login: `admin / admin123`

## Notas
- Em free tier, máquinas podem hibernar (primeiro acesso pode ser mais lento)
- Sessões PHP são guardadas no FS do container (aceitável para demo). Em produção, usar Redis ou Sticky Sessions.
- Para domínios próprios, adicionar DNS no Fly e configurar certificados.
