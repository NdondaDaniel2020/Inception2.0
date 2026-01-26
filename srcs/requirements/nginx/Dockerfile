FROM alpine:3.22

# Instalar NGINX e OpenSSL
RUN apk add --no-cache nginx openssl

# Copiar configuração do nginx
COPY ./conf/nginx.conf /etc/nginx/nginx.conf

# Copiar e executar script de geração de certificados
COPY ./tools/generate_certificates.sh /usr/local/bin/generate_certificates.sh
RUN chmod +x /usr/local/bin/generate_certificates.sh && \
    /usr/local/bin/generate_certificates.sh

EXPOSE 443

CMD ["nginx", "-g", "daemon off;"]
