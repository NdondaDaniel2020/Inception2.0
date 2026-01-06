FROM alpine:3.23

# Instalar PHP-FPM e extensões necessárias para WordPress
RUN apk add --no-cache \
    php83 \
    php83-fpm \
    php83-mysqli \
    php83-pdo \
    php83-pdo_mysql \
    php83-gd \
    php83-intl \
    php83-mbstring \
    php83-xml \
    php83-zip \
    php83-opcache \
    php83-curl \
    php83-tokenizer \
    php83-session \
    php83-phar \
    bash \
    mariadb-client \
    curl \
    less

# Instalar WP-CLI
RUN curl -O https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar \
    && chmod +x wp-cli.phar \
    && mv wp-cli.phar /usr/local/bin/wp

# Criar usuário www-data se não existir
RUN set -x \
    && addgroup -g 82 -S www-data 2>/dev/null || true \
    && adduser -u 82 -D -S -G www-data www-data 2>/dev/null || true

# Criar symlink para php (WP-CLI espera 'php', mas Alpine usa 'php83')
RUN ln -sf /usr/bin/php83 /usr/bin/php

# Diretório padrão do WordPress
WORKDIR /home/nmatondo/data

# Copiar WordPress (teu site)
COPY organic/ /home/nmatondo/data/

# Configuração do PHP-FPM (caminho correto para Alpine)
COPY ./conf/www.conf /etc/php83/php-fpm.d/www.conf

# Script de entrada
COPY ./tools/entrypoint.sh /usr/local/bin/

# Tornar o script executável
RUN chmod +x /usr/local/bin/entrypoint.sh

# Permissões
RUN chown -R www-data:www-data /home/nmatondo/data

EXPOSE 9000

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
