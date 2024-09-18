# Usar a imagem PHP oficial com Apache
FROM php:7.4-apache

# Instalar as extensões necessárias para o MySQL e a AWS SDK
RUN docker-php-ext-install mysqli \
    && apt-get update \
    && apt-get install -y unzip \
    && curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Definir o diretório de trabalho
WORKDIR /var/www/html/

# Copiar o arquivo composer.json
COPY composer.json /var/www/html/

# Instalar as dependências do Composer
RUN composer install

# Copiar o código PHP para o diretório do Apache
COPY ./php/ /var/www/html/

# Expor a porta 80 para o Apache
EXPOSE 80
