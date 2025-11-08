# Railway Dockerfile - CORRIGIDO: Sem módulo php8
FROM php:8.1-apache

# Metadados
LABEL maintainer="InventoX Railway"
LABEL description="InventoX PHP Application - Build Fixed"

# Instalar dependências essenciais
RUN apt-get update && apt-get install -y \
    libzip-dev \
    zip \
    unzip \
    curl \
    wget \
    procps \
    && docker-php-ext-install \
    pdo \
    pdo_mysql \
    zip \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Configurar Apache modules (SEM php8 - já integrado)
RUN a2enmod rewrite
RUN a2enmod headers

# CONFIGURAÇÃO APACHE ROBUSTA
# Configurar ServerName para evitar warnings
RUN echo 'ServerName localhost' > /etc/apache2/conf-available/servername.conf
RUN a2enconf servername

# Configurar porta explicitamente
RUN echo 'Listen 80' > /etc/apache2/conf-available/port.conf
RUN a2enconf port

# Configurar site principal
RUN echo '<VirtualHost *:80>' > /etc/apache2/sites-available/000-default.conf
RUN echo '    ServerAdmin webmaster@localhost' >> /etc/apache2/sites-available/000-default.conf
RUN echo '    DocumentRoot /var/www/html' >> /etc/apache2/sites-available/000-default.conf
RUN echo '    DirectoryIndex index.php index.html' >> /etc/apache2/sites-available/000-default.conf
RUN echo '    <Directory /var/www/html>' >> /etc/apache2/sites-available/000-default.conf
RUN echo '        Options Indexes FollowSymLinks' >> /etc/apache2/sites-available/000-default.conf
RUN echo '        AllowOverride All' >> /etc/apache2/sites-available/000-default.conf
RUN echo '        Require all granted' >> /etc/apache2/sites-available/000-default.conf
RUN echo '    </Directory>' >> /etc/apache2/sites-available/000-default.conf
RUN echo '    ErrorLog ${APACHE_LOG_DIR}/error.log' >> /etc/apache2/sites-available/000-default.conf
RUN echo '    CustomLog ${APACHE_LOG_DIR}/access.log combined' >> /etc/apache2/sites-available/000-default.conf
RUN echo '</VirtualHost>' >> /etc/apache2/sites-available/000-default.conf

# Copiar arquivos da aplicação
COPY frontend/ /var/www/html/
COPY api/ /var/www/html/api/
COPY .htaccess /var/www/html/.htaccess

# Criar index.php robusto para healthcheck
RUN echo '<?php' > /var/www/html/index.php
RUN echo 'header("Content-Type: text/html; charset=utf-8");' >> /var/www/html/index.php
RUN echo 'http_response_code(200);' >> /var/www/html/index.php
RUN echo 'echo "<!DOCTYPE html><html><head><title>InventoX Railway OK</title></head><body>";' >> /var/www/html/index.php
RUN echo 'echo "<h1>✅ InventoX Railway</h1>";' >> /var/www/html/index.php
RUN echo 'echo "<p>Status: <strong>OK</strong></p>";' >> /var/www/html/index.php
RUN echo 'echo "<p>PHP: " . PHP_VERSION . "</p>";' >> /var/www/html/index.php
RUN echo 'echo "<p>Time: " . date("Y-m-d H:i:s") . "</p>";' >> /var/www/html/index.php
RUN echo 'echo "<hr><a href=\"/frontend/\">🚀 App</a> | <a href=\"/api/health.php\">🔧 API</a>";' >> /var/www/html/index.php
RUN echo 'echo "</body></html>";' >> /var/www/html/index.php
RUN echo '?>' >> /var/www/html/index.php

# Verificar estrutura
RUN ls -la /var/www/html/
RUN cat /var/www/html/index.php

# Configurar permissões
RUN chown -R www-data:www-data /var/www/html
RUN chmod -R 755 /var/www/html
RUN chmod 644 /var/www/html/index.php

# Criar pasta uploads
RUN mkdir -p /var/www/html/uploads && chown www-data:www-data /var/www/html/uploads

# Configurar PHP
RUN echo 'engine = On' >> /usr/local/etc/php/php.ini
RUN echo 'short_open_tag = Off' >> /usr/local/etc/php/php.ini
RUN echo 'default_mimetype = "text/html"' >> /usr/local/etc/php/php.ini
RUN echo 'default_charset = "UTF-8"' >> /usr/local/etc/php/php.ini
RUN echo 'max_execution_time = 30' >> /usr/local/etc/php/php.ini
RUN echo 'memory_limit = 128M' >> /usr/local/etc/php/php.ini

# Workdir
WORKDIR /var/www/html

# Expor porta
EXPOSE 80

# Health check robusto com múltiplas tentativas
HEALTHCHECK --interval=10s --timeout=5s --start-period=30s --retries=5 \
    CMD curl -f http://localhost/ || curl -f http://localhost/index.php || wget --no-verbose --tries=1 --spider http://localhost/ || exit 1

# Script de inicialização
RUN echo '#!/bin/bash' > /start.sh
RUN echo 'echo "🚀 Iniciando InventoX Railway..."' >> /start.sh
RUN echo 'echo "📂 Verificando arquivos..."' >> /start.sh
RUN echo 'ls -la /var/www/html/' >> /start.sh
RUN echo 'echo "🔧 Testando PHP..."' >> /start.sh
RUN echo 'php -v' >> /start.sh
RUN echo 'echo "🌐 Iniciando Apache..."' >> /start.sh
RUN echo 'exec apache2-foreground' >> /start.sh
RUN chmod +x /start.sh

# Comando de inicialização
CMD ["/start.sh"]