# Dockerfile otimizado para DigitalOcean App Platform
FROM php:8.1-apache

# Instalar extensões PHP necessárias
RUN apt-get update && apt-get install -y \
    libzip-dev \
    zip \
    unzip \
    && docker-php-ext-install \
    pdo \
    pdo_mysql \
    zip \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Configurar Apache
RUN a2enmod rewrite
RUN a2enmod headers

# Configurar Apache para processar PHP corretamente
RUN echo '<FilesMatch \.php$>' > /etc/apache2/conf-available/php-handler.conf && \
    echo '    SetHandler application/x-httpd-php' >> /etc/apache2/conf-available/php-handler.conf && \
    echo '</FilesMatch>' >> /etc/apache2/conf-available/php-handler.conf && \
    echo '<Directory /var/www/html>' >> /etc/apache2/conf-available/php-handler.conf && \
    echo '    Options Indexes FollowSymLinks' >> /etc/apache2/conf-available/php-handler.conf && \
    echo '    AllowOverride All' >> /etc/apache2/conf-available/php-handler.conf && \
    echo '    Require all granted' >> /etc/apache2/conf-available/php-handler.conf && \
    echo '</Directory>' >> /etc/apache2/conf-available/php-handler.conf && \
    a2enconf php-handler

# Copiar arquivos da aplicação na estrutura correta
COPY frontend/ /var/www/html/
COPY api/ /var/www/html/api/
COPY .htaccess /var/www/html/.htaccess
COPY index.php /var/www/html/index.php

# Configurar permissões
RUN chown -R www-data:www-data /var/www/html
RUN chmod -R 755 /var/www/html

# Criar pasta de uploads
RUN mkdir -p /var/www/html/uploads && chown www-data:www-data /var/www/html/uploads

# Configurar Apache para servir do diretório correto
WORKDIR /var/www/html

# Expor porta 80
EXPOSE 80

# Comando para iniciar Apache
CMD ["apache2-foreground"]