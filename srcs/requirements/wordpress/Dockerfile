FROM php:8.3-fpm-alpine

# Instalar dependências do WordPress + WP-CLI
RUN apk add --no-cache \
    bash \
    mariadb-client \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    libzip-dev \
    zip \
    unzip \
    oniguruma-dev \
    icu-dev \
    libxml2-dev \
    curl \
    less

# Extensões PHP obrigatórias para WordPress
RUN docker-php-ext-configure gd \
    --with-freetype \
    --with-jpeg \
 && docker-php-ext-install \
    gd \
    mysqli \
    pdo \
    pdo_mysql \
    intl \
    mbstring \
    xml \
    zip \
    opcache

# Instalar WP-CLI
RUN curl -O https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar \
    && chmod +x wp-cli.phar \
    && mv wp-cli.phar /usr/local/bin/wp

# Diretório padrão do PHP-FPM
WORKDIR /var/www/html

# Copiar WordPress (teu site)
COPY organic/ /var/www/html/

# Configuração do PHP-FPM
COPY ./conf/www.conf /usr/local/etc/php-fpm.d/www.conf

# Script de entrada
COPY ./tools/entrypoint.sh /usr/local/bin/

# Tornar o script executável
RUN chmod +x /usr/local/bin/entrypoint.sh

# Permissões
RUN chown -R www-data:www-data /var/www/html

EXPOSE 9000

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
