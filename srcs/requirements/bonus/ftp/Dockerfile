FROM alpine:3.22

# Instala vsftpd
RUN apk add --no-cache vsftpd

# Cria diretórios necessários
RUN mkdir -p /var/ftp /etc/vsftpd /var/log /var/run/vsftpd/empty

# Copia script de configuração
COPY ./tools/entrypoint.sh /usr/local/bin/

# Copia configuração customizada
COPY ./conf/vsftpd.conf /etc/vsftpd/vsftpd.conf

# Tornar o script executável
RUN chmod +x /usr/local/bin/entrypoint.sh

# Expõe portas FTP 21 ativa + 21000
EXPOSE 21 21000-21010

# Inicia o vsftpd
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
