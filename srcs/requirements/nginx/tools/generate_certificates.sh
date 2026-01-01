#!/bin/sh

SSL_DIR=/etc/nginx/ssl

mkdir -p $SSL_DIR

# Gerar certificado SSL auto-assinado
openssl req -x509 -nodes -days 365 \
    -newkey rsa:2048 \
    -keyout $SSL_DIR/privkey.pem \
    -out $SSL_DIR/fullchain.pem \
    -subj "/C=AO/ST=Luanda/L=Luanda/O=42Luanda/OU=Inception/CN=nmatondo.42.fr"

echo "✅ SSL certificates generated for nmatondo.42.fr"
