FROM php:8.0-apache

# Instala dependencias necesarias y extensiones para MySQL
RUN apt-get update && apt-get install -y \
    default-mysql-client \
    libzip-dev \
    zip \
    unzip \
    libonig-dev \
    libxml2-dev \
    && docker-php-ext-install mysqli pdo_mysql mbstring xml zip \
    && a2enmod rewrite

# Copiar código de la aplicación al directorio público de Apache
COPY . /var/www/html/

# Ajustar permisos (opcional, dependiendo del host)
RUN chown -R www-data:www-data /var/www/html \
    && find /var/www/html -type d -exec chmod 755 {} \; \
    && find /var/www/html -type f -exec chmod 644 {} \;

EXPOSE 80

CMD ["apache2-foreground"]
