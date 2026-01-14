FROM alpine:3.23

# Install busybox-extras for httpd
RUN apk add --no-cache busybox-extras

# Copiar arquivos do site
COPY ./site /var/www/myprofile

WORKDIR /var/www/myprofile

# httpd do busybox - servidor legítimo
CMD ["httpd", "-f", "-v", "-p", "8888"]