FROM nginx:1.26-alpine

# Instalar openssl para gerar certificados
RUN apk add --no-cache openssl

# Copiar configuração do nginx
COPY ./conf/nginx.conf /etc/nginx/nginx.conf

# Copiar e executar script de geração de certificados
COPY ./tools/generate_certificates.sh /usr/local/bin/generate_certificates.sh
RUN chmod +x /usr/local/bin/generate_certificates.sh && /usr/local/bin/generate_certificates.sh

EXPOSE 443

CMD ["nginx", "-g", "daemon off;"]
