# Use a imagem PHP oficial com Apache
FROM php:8.1-apache

# Instale as extensões necessárias para o MySQL
RUN docker-php-ext-install mysqli

# Instale o unzip
RUN apt-get update && apt-get install -y unzip

# Instale o Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Defina o diretório de trabalho
WORKDIR /var/www/html

# Copie o composer.json para o diretório de trabalho
COPY composer.json ./

# Instale as dependências do Composer
RUN apt-get update && apt-get install -y \
    libcurl4-openssl-dev \
    libssl-dev \
    pkg-config \
    libsslcommon2-dev \
    && docker-php-ext-install curl mbstring

RUN composer install --no-dev --no-interaction --prefer-dist

# Copie o código da aplicação PHP para o diretório de trabalho
COPY ./php/ ./

# Exponha a porta 80 para o Apache
EXPOSE 80