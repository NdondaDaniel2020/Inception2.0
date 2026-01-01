#!/bin/sh
set -e

echo "🔧 MariaDB Initialization Script"

# Aguardar MariaDB iniciar
echo "⏳ Waiting for MariaDB to start..."
sleep 5

# Ler senhas dos secrets
MARIADB_ROOT_PASSWORD=$(cat /run/secrets/db_root_password)
MARIADB_PASSWORD=$(cat /run/secrets/db_password)

echo "📦 Configuring database and user..."

# Configurar MariaDB
mysql -u root <<-EOSQL
    -- Configurar senha root
    ALTER USER 'root'@'localhost' IDENTIFIED BY '${MARIADB_ROOT_PASSWORD}';
    
    -- Criar database se não existir
    CREATE DATABASE IF NOT EXISTS ${MARIADB_DATABASE};
    
    -- Criar usuário se não existir
    CREATE USER IF NOT EXISTS '${MARIADB_USER}'@'%' IDENTIFIED BY '${MARIADB_PASSWORD}';
    
    -- Dar permissões ao usuário
    GRANT ALL PRIVILEGES ON ${MARIADB_DATABASE}.* TO '${MARIADB_USER}'@'%';
    
    -- Aplicar mudanças
    FLUSH PRIVILEGES;
EOSQL

echo "✅ MariaDB configured successfully!"
echo "   Database: ${MARIADB_DATABASE}"
echo "   User: ${MARIADB_USER}"
