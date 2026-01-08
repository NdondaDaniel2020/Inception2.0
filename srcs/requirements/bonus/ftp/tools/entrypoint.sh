#!/bin/sh

# Cria usuário FTP se não existir
if ! id -u ${FTP_USER} >/dev/null 2>&1; then
    adduser -D -h /var/www/html ${FTP_USER}
    FTP_PASSWORD=$(cat /run/secrets/ftp_credentials)
    echo "${FTP_USER}:${FTP_PASSWORD}" | chpasswd
fi

# Ajusta permissões do diretório WordPress
chown -R ${FTP_USER}:${FTP_USER} /var/www/html
chmod -R 755 /var/www/html

# Inicia vsftpd
exec vsftpd /etc/vsftpd/vsftpd.conf
