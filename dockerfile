# Usar a imagem PHP oficial com Apache
FROM php:7.4-apache

# Instalar as extensões necessárias para o MySQL e a AWS SDK
RUN docker-php-ext-install mysqli \
    && apt-get update \
    && apt-get install -y unzip \
    && curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Instalar a AWS SDK via Composer
COPY composer.json /var/www/html/
WORKDIR /var/www/html/
RUN composer install

# Copiar o código da aplicação PHP para o diretório padrão do Apache
COPY ./php/ /var/www/html/

# Expor a porta 80 para o Apache
EXPOSE 80