# 🔧 Arquivos para Corrigir PHP no DigitalOcean

## 📋 **Arquivos que precisam ser atualizados no GitHub:**

### **1. `apache_app.conf`** (SUBSTITUIR)
```apache
# Configuração Apache para DigitalOcean
# Configurar tipos MIME primeiro
AddType application/x-httpd-php .php
AddType application/x-httpd-php-source .phps

# Forçar processamento PHP para todos os arquivos .php
<FilesMatch "\.php$">
    SetHandler application/x-httpd-php
</FilesMatch>

# Configurar diretório raiz
<Directory />
    DirectoryIndex index.php index.html
    AllowOverride All
    Options -Indexes +FollowSymLinks
    Require all granted
    
    # Forçar processamento PHP
    <FilesMatch "\.php$">
        SetHandler application/x-httpd-php
    </FilesMatch>
</Directory>

# Headers CORS
Header always set Access-Control-Allow-Origin "*"
Header always set Access-Control-Allow-Methods "GET, POST, PUT, DELETE, OPTIONS"
Header always set Access-Control-Allow-Headers "Content-Type, Authorization, X-Requested-With"

# Habilitar mod_rewrite
RewriteEngine On
```

### **2. `.htaccess`** (SUBSTITUIR)
```apache
# DigitalOcean Apache Configuration
DirectoryIndex index.html index.php

# Forçar processamento PHP
AddType application/x-httpd-php .php
<FilesMatch "\.php$">
    SetHandler application/x-httpd-php
</FilesMatch>

# Rewrite rules para SPA
RewriteEngine On

# Redirecionar root para frontend
RewriteRule ^$ /frontend/ [R=301,L]

# API routes
RewriteRule ^api/(.*)$ /api/$1 [L]

# Frontend routes (SPA)
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteCond %{REQUEST_URI} ^/frontend/
RewriteRule ^frontend/.*$ /frontend/index.html [L]

# CORS Headers
<IfModule mod_headers.c>
    Header always set Access-Control-Allow-Origin "*"
    Header always set Access-Control-Allow-Methods "GET, POST, PUT, DELETE, OPTIONS"
    Header always set Access-Control-Allow-Headers "Content-Type, Authorization"
    Header always set Access-Control-Allow-Credentials "true"
</IfModule>

# Handle OPTIONS requests
RewriteCond %{REQUEST_METHOD} OPTIONS
RewriteRule ^(.*)$ $1 [R=200,L]
```

### **3. `Procfile`** (SUBSTITUIR)
```
web: heroku-php-apache2
```

### **4. `.user.ini`** (CRIAR NOVO)
```ini
; Configuração PHP para DigitalOcean
auto_prepend_file =
auto_append_file =
default_mimetype = "text/html"
default_charset = "UTF-8"
```

## 🚀 **Passos para Upload:**

1. **Acesse seu repositório GitHub**
2. **Edite cada arquivo** acima
3. **Substitua** o conteúdo pelo código acima
4. **Commit changes**
5. **Aguardar redeploy** (2-3 minutos)

## 🧪 **Após redeploy, testar:**

- https://inventox-v2yj4.ondigitalocean.app/api/health.php
- **Deve retornar JSON**, não fazer download!

## ⚙️ **Se ainda não funcionar:**

O problema pode ser que o buildpack do Heroku não está usando o `apache_app.conf`. Nesse caso, precisamos configurar diretamente no DigitalOcean Dashboard:

1. **Settings** → **Components** → **inventox-web**
2. **Edit** → **Run Command**
3. **Alterar para**: `heroku-php-apache2 -C apache_app.conf`
4. **Save** → **Deploy**
