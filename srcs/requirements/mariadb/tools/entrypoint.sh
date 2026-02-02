#!/bin/sh
set -e

# Ler senhas dos secrets
MARIADB_ROOT_PASSWORD=$(cat /run/secrets/db_root_password)
MARIADB_PASSWORD=$(cat /run/secrets/db_password)

# Se é a primeira vez, inicializar o banco
if [ ! -d "/var/lib/mysql/${MARIADB_DATABASE}" ]; then
    echo "📦 First run - Initializing database..."

    # Ajustar dump.sql para o domínio correto
    sed -i "s/nmatondo.42.fr/${DOMAIN_NAME}/g" /docker-entrypoint-initdb.d/dump.sql

    # Criar script SQL completo
    echo "📥 Configuring users and database..."
    cat > /tmp/init.sql <<-EOF
		FLUSH PRIVILEGES;
		ALTER USER 'root'@'localhost' IDENTIFIED BY '${MARIADB_ROOT_PASSWORD}';
		CREATE USER IF NOT EXISTS 'root'@'%' IDENTIFIED BY '${MARIADB_ROOT_PASSWORD}';
		GRANT ALL PRIVILEGES ON *.* TO 'root'@'%' WITH GRANT OPTION;
		CREATE DATABASE IF NOT EXISTS ${MARIADB_DATABASE};
		CREATE USER IF NOT EXISTS '${MARIADB_USER}'@'%' IDENTIFIED BY '${MARIADB_PASSWORD}';
		GRANT ALL PRIVILEGES ON ${MARIADB_DATABASE}.* TO '${MARIADB_USER}'@'%';
		FLUSH PRIVILEGES;
		USE ${MARIADB_DATABASE};
	EOF

    # Importar dump no script SQL
    echo "📥 Importing dump.sql..."
    cat /docker-entrypoint-initdb.d/dump.sql >> /tmp/init.sql

    # Executar tudo de uma vez em modo bootstrap (sem background process)
    mysqld --user=mysql --bootstrap < /tmp/init.sql
    rm -f /tmp/init.sql
    
    echo "✅ Database initialized!"
fi

# Iniciar MariaDB em foreground
echo "🚀 Starting MariaDB..."
exec mysqld --user=mysql --datadir=/var/lib/mysql --bind-address=0.0.0.0 --port=3306
