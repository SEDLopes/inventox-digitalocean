# Dockerfile corrigido para DigitalOcean App Platform
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

# Configurar Apache para PHP
RUN a2enmod rewrite
RUN a2enmod headers

# Configurar Apache DocumentRoot
ENV APACHE_DOCUMENT_ROOT /var/www/html
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# Copiar arquivos da aplicação
COPY . /var/www/html/

# Configurar permissões
RUN chown -R www-data:www-data /var/www/html
RUN chmod -R 755 /var/www/html

# Criar pasta de uploads
RUN mkdir -p /var/www/html/uploads && chown www-data:www-data /var/www/html/uploads

# Configurar .htaccess para API
RUN echo "RewriteEngine On" > /var/www/html/.htaccess
RUN echo "RewriteCond %{REQUEST_FILENAME} !-f" >> /var/www/html/.htaccess
RUN echo "RewriteCond %{REQUEST_FILENAME} !-d" >> /var/www/html/.htaccess
RUN echo "RewriteRule ^api/(.*)$ api/$1 [L]" >> /var/www/html/.htaccess

# Expor porta 80
EXPOSE 80

# Comando para iniciar Apache
CMD ["apache2-foreground"]
