FROM alpine:3.22

# Install busybox-extras for httpd
RUN apk add --no-cache busybox-extras

# Copiar arquivos do site
COPY ./site /var/www/myprofile

WORKDIR /var/www/myprofile

# Expor porta 8888
EXPOSE 8888

# httpd do busybox - servidor legítimo
CMD ["httpd", "-f", "-v", "-p", "8888"]