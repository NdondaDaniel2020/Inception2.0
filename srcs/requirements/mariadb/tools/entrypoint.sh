#!/bin/sh
set -e

# Ler senhas dos secrets
MARIADB_ROOT_PASSWORD=$(cat /run/secrets/db_root_password)
MARIADB_PASSWORD=$(cat /run/secrets/db_password)

# Se é a primeira vez, inicializar o banco
if [ ! -d "/var/lib/mysql/${MARIADB_DATABASE}" ]; then
    echo "📦 First run - Initializing database..."

    # Iniciar MariaDB temporariamente
    mysqld --user=mysql --datadir=/var/lib/mysql --skip-networking --skip-grant-tables &
    pid=$!

    # Aguardar inicialização
    for i in $(seq 30); do
        mysqladmin ping --silent 2>/dev/null && break
        sleep 1
    done

    # Ajustar dump.sql para o domínio correto
    sed -i "s/nmatondo.42.fr/${DOMAIN_NAME}/g" /docker-entrypoint-initdb.d/dump.sql

    # Configurar usuários e database
    echo "📥 Configuring users and database..."
    mariadb <<-EOSQL
		FLUSH PRIVILEGES;
		ALTER USER 'root'@'localhost' IDENTIFIED BY '${MARIADB_ROOT_PASSWORD}';
		CREATE USER IF NOT EXISTS 'root'@'%' IDENTIFIED BY '${MARIADB_ROOT_PASSWORD}';
		GRANT ALL PRIVILEGES ON *.* TO 'root'@'%' WITH GRANT OPTION;
		CREATE DATABASE IF NOT EXISTS ${MARIADB_DATABASE};
		CREATE USER IF NOT EXISTS '${MARIADB_USER}'@'%' IDENTIFIED BY '${MARIADB_PASSWORD}';
		GRANT ALL PRIVILEGES ON ${MARIADB_DATABASE}.* TO '${MARIADB_USER}'@'%';
		FLUSH PRIVILEGES;
	EOSQL

    # Importar dump no database correto
    echo "📥 Importing dump.sql..."
    {
        echo "USE ${MARIADB_DATABASE};"
        cat /docker-entrypoint-initdb.d/dump.sql
    } | mariadb
    
    echo "✅ Database initialized!"
    
    # Parar MariaDB temporário
    kill $pid
    wait $pid
fi

# Iniciar MariaDB em foreground
echo "🚀 Starting MariaDB..."
exec mysqld --user=mysql --datadir=/var/lib/mysql --bind-address=0.0.0.0 --port=3306
