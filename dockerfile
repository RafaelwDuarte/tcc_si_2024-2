# Use a imagem PHP oficial com Apache
FROM php:8.1-apache

# Instale as extensões necessárias para o MySQL
RUN docker-php-ext-install mysqli

# Instale o AWS SDK para PHP
RUN apt-get update && apt-get install -y unzip
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
COPY composer.json /var/www/html/
RUN composer install

# Copie o código da aplicação PHP para o diretório padrão do Apache
COPY ./php/ /var/www/html/

# Exponha a porta 80 para o Apache
EXPOSE 80
