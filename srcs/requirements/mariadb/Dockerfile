FROM mariadb:10.11

# Copiar dump SQL (será executado automaticamente)
COPY ./conf/dump.sql /docker-entrypoint-initdb.d/

EXPOSE 3306

CMD ["mysqld"]
