#!/bin/sh
set -e

# Ler senhas dos secrets
MARIADB_ROOT_PASSWORD=$(cat /run/secrets/db_root_password)
MARIADB_PASSWORD=$(cat /run/secrets/db_password)

# Garantir permissões básicas (sem recursivo em datadir para evitar problemas com bind mount)
chown -R mysql:mysql /run/mysqld
mkdir -p /var/lib/mysql
chown mysql:mysql /var/lib/mysql

# Se é a primeira vez, inicializar o banco
if [ ! -d "/var/lib/mysql/${MARIADB_DATABASE}" ]; then
    echo "📦 First run - Initializing database..."

    # Script SQL para criar banco e usuários
    echo "📥 Configuring database and users..."
    cat > /tmp/init.sql <<-EOF
		FLUSH PRIVILEGES;
		ALTER USER 'root'@'localhost' IDENTIFIED BY '${MARIADB_ROOT_PASSWORD}';
		CREATE USER IF NOT EXISTS 'root'@'%' IDENTIFIED BY '${MARIADB_ROOT_PASSWORD}';
		GRANT ALL PRIVILEGES ON *.* TO 'root'@'%' WITH GRANT OPTION;
		CREATE DATABASE IF NOT EXISTS ${MARIADB_DATABASE};
		CREATE USER IF NOT EXISTS '${MARIADB_USER}'@'%' IDENTIFIED BY '${MARIADB_PASSWORD}';
		GRANT ALL PRIVILEGES ON ${MARIADB_DATABASE}.* TO '${MARIADB_USER}'@'%';
		FLUSH PRIVILEGES;
	EOF

    # Executar configuração em modo bootstrap
    mysqld --user=mysql --bootstrap < /tmp/init.sql
    rm -f /tmp/init.sql
    
    echo "✅ Database initialized!"
fi

# Iniciar MariaDB permanente em FOREGROUND (processo principal do container)
echo "🚀 Starting MariaDB (foreground)..."
exec mysqld --user=mysql --datadir=/var/lib/mysql --bind-address=0.0.0.0 --port=3306
