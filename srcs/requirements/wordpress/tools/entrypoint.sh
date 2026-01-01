#!/bin/sh
set -e

echo "🔧 WordPress Initialization Script"

# --------------------------------------------------
# Validar wp-cli
# --------------------------------------------------
if ! command -v wp >/dev/null 2>&1; then
    echo "❌ wp-cli não está instalado"
    exit 1
fi

# --------------------------------------------------
# Ler senha do banco via Docker Secret
# --------------------------------------------------
if [ -f /run/secrets/db_password ]; then
    export WORDPRESS_DB_PASSWORD="$(cat /run/secrets/db_password)"
else
    echo "❌ Secret db_password não encontrado"
    exit 1
fi

# --------------------------------------------------
# Criar wp-config.php ANTES de qualquer wp-cli
# --------------------------------------------------
if [ -f /var/www/html/wp-config-docker.php ]; then
    echo "📝 Gerando wp-config.php..."
    cp -f /var/www/html/wp-config-docker.php /var/www/html/wp-config.php
fi

# --------------------------------------------------
# Aguardar MariaDB
# --------------------------------------------------
echo "⏳ Waiting for MariaDB to be ready..."

# Extrair host e porta
DB_HOST=$(echo "$WORDPRESS_DB_HOST" | cut -d: -f1)
DB_PORT=$(echo "$WORDPRESS_DB_HOST" | cut -d: -f2 -s)
DB_PORT=${DB_PORT:-3306}

until mariadb \
    --skip-ssl \
    -h"$DB_HOST" \
    -P"$DB_PORT" \
    -u"$WORDPRESS_DB_USER" \
    -p"$WORDPRESS_DB_PASSWORD" \
    -e "SELECT 1" >/dev/null 2>&1
do
    echo "   MariaDB indisponível - aguardando..."
    sleep 3
done

echo "✅ MariaDB está pronto!"

# --------------------------------------------------
# Instalar WordPress se necessário
# --------------------------------------------------
if wp core is-installed --allow-root --path=/var/www/html >/dev/null 2>&1; then
    echo "ℹ️ WordPress já instalado"
else
    echo "📦 Instalando WordPress..."

    # Ler credenciais do admin
    if [ -f /run/secrets/credentials ]; then
        WP_ADMIN_USER="$(sed -n '1p' /run/secrets/credentials)"
        WP_ADMIN_PASS="$(sed -n '2p' /run/secrets/credentials)"
    else
        WP_ADMIN_USER="${WP_ADMIN_USER:-admin}"
        WP_ADMIN_PASS="${WP_ADMIN_PASS:-password}"
    fi

    wp core install \
        --allow-root \
        --path=/var/www/html \
        --url="${DOMAIN_NAME:-localhost}" \
        --title="Inception WordPress" \
        --admin_user="$WP_ADMIN_USER" \
        --admin_password="$WP_ADMIN_PASS" \
        --admin_email="${WP_ADMIN_USER}@student.42.fr" \
        --skip-email

    echo "✅ WordPress instalado com sucesso!"
fi

# --------------------------------------------------
# Ajustar permissões
# --------------------------------------------------
chown -R www-data:www-data /var/www/html

# --------------------------------------------------
# Iniciar PHP-FPM
# --------------------------------------------------
echo "🚀 Iniciando PHP-FPM..."
exec php-fpm -F
