FROM php:8.2-apache

# Instalar dependências do sistema
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    zip \
    unzip \
    git \
        curl \
        python3 \
        python3-pip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) pdo pdo_mysql mysqli gd \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Habilitar mod_rewrite do Apache
RUN a2enmod rewrite

# Configurar PHP
RUN echo "upload_max_filesize = 10M" >> /usr/local/etc/php/conf.d/uploads.ini \
    && echo "post_max_size = 10M" >> /usr/local/etc/php/conf.d/uploads.ini \
    && echo "memory_limit = 256M" >> /usr/local/etc/php/conf.d/uploads.ini \
    && echo "max_execution_time = 300" >> /usr/local/etc/php/conf.d/uploads.ini

# Configurar sessões PHP
RUN mkdir -p /var/lib/php/sessions \
    && chmod 1733 /var/lib/php/sessions \
    && echo "session.save_path = \"/var/lib/php/sessions\"" >> /usr/local/etc/php/conf.d/sessions.ini \
    && echo "session.cookie_httponly = 1" >> /usr/local/etc/php/conf.d/sessions.ini \
    && echo "session.cookie_samesite = \"Lax\"" >> /usr/local/etc/php/conf.d/sessions.ini \
    && echo "session.use_strict_mode = 1" >> /usr/local/etc/php/conf.d/sessions.ini

# Instalar dependências Python para import XLS/XLSX
RUN pip3 install --no-cache-dir --break-system-packages pandas sqlalchemy pymysql openpyxl python-dotenv

# Expor porta 80
EXPOSE 80

# Iniciar Apache
CMD ["apache2-foreground"]

