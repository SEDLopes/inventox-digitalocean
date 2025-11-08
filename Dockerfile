# Railway Dockerfile - CORRIGIDO: Sem Listen 80 duplicado
FROM php:8.1-apache

# Metadados
LABEL maintainer="InventoX Railway"
LABEL description="InventoX PHP Application - Apache Fixed"

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

# Configurar Apache modules
RUN a2enmod rewrite
RUN a2enmod headers

# CONFIGURAÇÃO APACHE - ADICIONAR, SEM Listen 80 (já existe)
# Adicionar configurações ao final do apache2.conf existente
RUN echo '' >> /etc/apache2/apache2.conf && \
    echo '# InventoX Railway Configuration' >> /etc/apache2/apache2.conf && \
    echo 'ServerName localhost' >> /etc/apache2/apache2.conf && \
    echo '' >> /etc/apache2/apache2.conf && \
    echo '<VirtualHost *:80>' >> /etc/apache2/apache2.conf && \
    echo '    ServerAdmin webmaster@localhost' >> /etc/apache2/apache2.conf && \
    echo '    DocumentRoot /var/www/html' >> /etc/apache2/apache2.conf && \
    echo '    DirectoryIndex index.php index.html' >> /etc/apache2/apache2.conf && \
    echo '    <Directory /var/www/html>' >> /etc/apache2/apache2.conf && \
    echo '        Options Indexes FollowSymLinks' >> /etc/apache2/apache2.conf && \
    echo '        AllowOverride All' >> /etc/apache2/apache2.conf && \
    echo '        Require all granted' >> /etc/apache2/apache2.conf && \
    echo '    </Directory>' >> /etc/apache2/apache2.conf && \
    echo '    ErrorLog ${APACHE_LOG_DIR}/error.log' >> /etc/apache2/apache2.conf && \
    echo '    CustomLog ${APACHE_LOG_DIR}/access.log combined' >> /etc/apache2/apache2.conf && \
    echo '</VirtualHost>' >> /etc/apache2/apache2.conf

# Copiar arquivos da aplicação
COPY frontend/ /var/www/html/
COPY api/ /var/www/html/api/
COPY .htaccess /var/www/html/.htaccess

# Criar index.php SIMPLES e GARANTIDO
RUN echo '<?php' > /var/www/html/index.php && \
    echo 'header("Content-Type: text/html; charset=utf-8");' >> /var/www/html/index.php && \
    echo 'http_response_code(200);' >> /var/www/html/index.php && \
    echo 'echo "<!DOCTYPE html><html><head><title>InventoX Railway</title></head><body>";' >> /var/www/html/index.php && \
    echo 'echo "<h1>✅ InventoX Railway OK</h1>";' >> /var/www/html/index.php && \
    echo 'echo "<p><strong>Status:</strong> Funcionando</p>";' >> /var/www/html/index.php && \
    echo 'echo "<p><strong>PHP:</strong> " . PHP_VERSION . "</p>";' >> /var/www/html/index.php && \
    echo 'echo "<p><strong>Time:</strong> " . date("Y-m-d H:i:s") . "</p>";' >> /var/www/html/index.php && \
    echo 'echo "<hr>";' >> /var/www/html/index.php && \
    echo 'echo "<a href=\"/frontend/\">🚀 Aplicação</a> | ";' >> /var/www/html/index.php && \
    echo 'echo "<a href=\"/api/health.php\">🔧 API Health</a>";' >> /var/www/html/index.php && \
    echo 'echo "</body></html>";' >> /var/www/html/index.php && \
    echo '?>' >> /var/www/html/index.php

# Verificar conteúdo criado
RUN cat /var/www/html/index.php
RUN ls -la /var/www/html/

# Configurar permissões
RUN chown -R www-data:www-data /var/www/html
RUN chmod -R 755 /var/www/html
RUN chmod 644 /var/www/html/index.php

# Criar pasta uploads
RUN mkdir -p /var/www/html/uploads && chown www-data:www-data /var/www/html/uploads

# Configurar PHP básico
RUN echo 'engine = On' >> /usr/local/etc/php/php.ini && \
    echo 'short_open_tag = Off' >> /usr/local/etc/php/php.ini && \
    echo 'default_mimetype = "text/html"' >> /usr/local/etc/php/php.ini && \
    echo 'default_charset = "UTF-8"' >> /usr/local/etc/php/php.ini && \
    echo 'max_execution_time = 30' >> /usr/local/etc/php/php.ini && \
    echo 'memory_limit = 128M' >> /usr/local/etc/php/php.ini

# Workdir
WORKDIR /var/www/html

# Expor porta
EXPOSE 80

# Health check SIMPLES - apenas curl
HEALTHCHECK --interval=30s --timeout=10s --start-period=60s --retries=3 \
    CMD curl -f http://localhost/ || exit 1

# Comando DIRETO - sem script complexo
CMD ["apache2-foreground"]