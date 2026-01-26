FROM alpine:3.22

# Instala PHP e as extensões necessárias para o Adminer
RUN apk add --no-cache \
    php83 \
    php83-session \
    php83-mysqli \
    php83-pgsql \
    php83-sqlite3 \
    curl

# Define o diretório de trabalho
WORKDIR /var/www/adminer

# Baixa o arquivo único do Adminer (aprox. 200KB)
RUN curl -L --insecure https://www.adminer.org/latest.php -o index.php

# Expõe a porta 8080
EXPOSE 8080

# Inicia o servidor web interno do PHP
CMD ["php83", "-S", "0.0.0.0:8080", "-t", "/var/www/adminer"]
