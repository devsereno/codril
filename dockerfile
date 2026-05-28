FROM php:8.3-apache

# Instala extensões necessárias para PHP + MySQL
RUN apt-get update && apt-get install -y \
    libzip-dev \
    && docker-php-ext-install pdo pdo_mysql mysqli zip

# Copia todos os arquivos do projeto
COPY . /var/www/html/
WORKDIR /var/www/html

# Configura porta
EXPOSE 8080

# Inicia o Apache
CMD ["apache2-foreground"]
