# Use a imagem oficial do PHP 8.1 com Apache
FROM php:8.1-apache

# Instale as extensões e pacotes necessários
RUN apt-get update && apt-get install -y \
    libonig-dev \
    libzip-dev \
    unzip \
    git \
    libcurl4-openssl-dev \
    && docker-php-ext-install \
    mysqli \
    pdo_mysql \
    mbstring \
    zip \
    curl \
    && docker-php-ext-enable \
    mysqli \
    pdo_mysql \
    mbstring \
    zip \
    curl

# Instale o Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Defina o diretório de trabalho
WORKDIR /var/www/html

# Copie o composer.json  (se existir)
COPY composer.json ./

# Instale as dependências do Composer
RUN if [ -f "composer.json" ]; then composer install --no-dev --optimize-autoloader; fi

# Copie o restante do código da aplicação para o contêiner
COPY . .

# Ajuste as permissões para o usuário do Apache
RUN chown -R www-data:www-data /var/www/html

# Exponha a porta 80
EXPOSE 80

# Comando para iniciar o Apache quando o contêiner for executado
CMD ["apache2-foreground"]