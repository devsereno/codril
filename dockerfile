FROM php:8.3-apache

# Instala extensões necessárias (PostgreSQL + MySQL)
RUN apt-get update && apt-get install -y \
    libpq-dev \
    libzip-dev \
    && docker-php-ext-install pdo pdo_pgsql pgsql zip

# Copia os arquivos
COPY . /var/www/html/
WORKDIR /var/www/html

EXPOSE 8080
CMD ["apache2-foreground"]
