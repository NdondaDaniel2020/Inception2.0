FROM alpine:3.22

RUN apk add --no-cache mariadb mariadb-client

RUN mkdir -p /run/mysqld && chown -R mysql:mysql /run/mysqld /var/lib/mysql

RUN mysql_install_db --user=mysql --datadir=/var/lib/mysql

# Desabilitar skip-networking
RUN sed -i 's/skip-networking/#skip-networking/' /etc/my.cnf.d/mariadb-server.cnf

COPY ./conf/dump.sql /docker-entrypoint-initdb.d/

RUN chown -R mysql:mysql /docker-entrypoint-initdb.d

COPY ./tools/entrypoint.sh /usr/local/bin/

RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 3306

USER mysql

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
