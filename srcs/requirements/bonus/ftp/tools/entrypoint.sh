#!/bin/sh

# Cria grupo www-data se não existir (mesmo GID do WordPress)
addgroup -g 82 -S www-data 2>/dev/null || true

# Cria usuário FTP se não existir (com mesmo UID/GID do WordPress)
if ! id -u ${FTP_USER} >/dev/null 2>&1; then
    adduser -u 82 -D -h /var/www/html -G www-data ${FTP_USER}
    FTP_PASSWORD=$(cat /run/secrets/ftp_credentials)
    echo "${FTP_USER}:${FTP_PASSWORD}" | chpasswd
fi

# Garantir permissões corretas (mantém www-data como dono)
chown -R www-data:www-data /var/www/html
chmod -R 755 /var/www/html

# Inicia vsftpd
exec vsftpd /etc/vsftpd/vsftpd.conf
