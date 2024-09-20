# Use a imagem PHP oficial com Apache
FROM php:8.1-apache

# Instale as extensões necessárias para o MySQL
RUN docker-php-ext-install mysqli

# Instale o unzip e outras dependências necessárias
RUN apt-get update && apt-get install -y \
    unzip \
    libcurl4-openssl-dev \
    libssl-dev \
    pkg-config \
    libxml2-dev

# Instale as extensões PHP necessárias
RUN docker-php-ext-install curl mbstring xml

# Instale o Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Defina o diretório de trabalho
WORKDIR /var/www/html

# Copie o código da aplicação PHP para o diretório de trabalho
COPY ./php/ ./

# Copie o composer.json e o composer.lock para o diretório de trabalho
COPY composer.json composer.lock ./

# Instale as dependências do Composer
RUN composer install --no-dev --no-interaction --prefer-dist

# Ajuste as permissões dos arquivos
RUN chown -R www-data:www-data /var/www/html

# Exponha a porta 80 para o Apache
EXPOSE 80

# Comando para iniciar o Apache
CMD ["apache2-foreground"]