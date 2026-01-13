FROM alpine:3.23

# Copiar arquivos do site
COPY ./site /var/www/myprofile

WORKDIR /var/www/myprofile

# httpd do busybox - servidor legítimo
CMD ["httpd", "-f", "-v", "-p", "8888"]