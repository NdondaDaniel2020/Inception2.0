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
# Ler senha do Redis via Docker Secret
# --------------------------------------------------
if [ -f /run/secrets/redis_password ]; then
    export REDIS_PASSWORD="$(cat /run/secrets/redis_password)"
else
    echo "⚠️  Secret redis_password não encontrado - Redis sem senha"
    export REDIS_PASSWORD=""
fi

# --------------------------------------------------
# Criar wp-config.php ANTES de qualquer wp-cli
# --------------------------------------------------
if [ -f /var/www/html/wp-config-docker.php ]; then
    echo "📝 Gerando wp-config.php..."
    cp -f /var/www/html/wp-config-docker.php /var/www/html/wp-config.php
    
    # Substituir placeholder da senha Redis
    if [ -n "$REDIS_PASSWORD" ]; then
        sed -i "s/REDIS_PASSWORD_PLACEHOLDER/$REDIS_PASSWORD/g" /var/www/html/wp-config.php
    fi
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
    # --------------------------------------------------
    # Sincronizar senhas dos usuários do dump com secrets
    # --------------------------------------------------
    echo "🔐 Sincronizando senhas com secrets..."
    if [ -f /run/secrets/credentials ] && [ "$(wc -l < /run/secrets/credentials)" -eq 4 ]; then
        # Ler credenciais do secret
        SECRET_ADMIN_USER="$(sed -n '1p' /run/secrets/credentials)"
        SECRET_ADMIN_PASS="$(sed -n '2p' /run/secrets/credentials)"
        SECRET_USER="$(sed -n '3p' /run/secrets/credentials)"
        SECRET_USER_PASS="$(sed -n '4p' /run/secrets/credentials)"
        
        # Verificar e atualizar/criar usuário admin do dump (nmatondo)
        if wp user get "$SECRET_ADMIN_USER" --allow-root --path=/var/www/html >/dev/null 2>&1; then
            echo "   Atualizando senha do usuário: $SECRET_ADMIN_USER"
            wp user update "$SECRET_ADMIN_USER" --user_pass="$SECRET_ADMIN_PASS" --allow-root --path=/var/www/html
        else
            echo "   Criando usuário: $SECRET_ADMIN_USER"
            wp user create "$SECRET_ADMIN_USER" "${SECRET_ADMIN_USER}@student.42luanda.com" \
                --role=administrator \
                --user_pass="$SECRET_ADMIN_PASS" \
                --allow-root \
                --path=/var/www/html
        fi
        
        # Verificar e atualizar/criar segundo usuário do dump (ndonda)
        if wp user get "$SECRET_USER" --allow-root --path=/var/www/html >/dev/null 2>&1; then
            echo "   Atualizando senha do usuário: $SECRET_USER"
            wp user update "$SECRET_USER" --user_pass="$SECRET_USER_PASS" --allow-root --path=/var/www/html
        else
            echo "   Criando usuário: $SECRET_USER"
            wp user create "$SECRET_USER" "${SECRET_USER}@student.42luanda.com" \
                --role=author \
                --user_pass="$SECRET_USER_PASS" \
                --allow-root \
                --path=/var/www/html
        fi
        
        echo "✅ Senhas sincronizadas com sucesso!"
    fi

else
    echo "📦 Instalando WordPress..."

    # Ler credenciais do admin
    if [ -f /run/secrets/credentials ]; then
        WP_ADMIN_USER="$(sed -n '1p' /run/secrets/credentials)"
        WP_ADMIN_PASS="$(sed -n '2p' /run/secrets/credentials)"
        WP_USER="$(sed -n '3p' /run/secrets/credentials)"
        WP_PASS="$(sed -n '4p' /run/secrets/credentials)"
    else
        WP_ADMIN_USER="${WP_ADMIN_USER:-admin}"
        WP_ADMIN_PASS="${WP_ADMIN_PASS:-password}"
        WP_USER="${WP_USER:-usuario_normal}"
        WP_PASS="${WP_PASS:-senha123}"
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

    wp user create "$WP_USER" "$WP_USER@student.42.fr" \
    --role=author \
    --user_pass="$WP_PASS" \
    --allow-root \
    --path=/var/www/html

    echo "✅ WordPress instalado com sucesso!"
fi

# --------------------------------------------------
# Aguardar Elasticsearch (se configurado)
# --------------------------------------------------
if [ -n "$ELASTICSEARCH_HOST" ]; then
    echo "⏳ Aguardando Elasticsearch..."
    ES_HOST=$(echo "$ELASTICSEARCH_HOST" | cut -d: -f1)
    ES_PORT=$(echo "$ELASTICSEARCH_HOST" | cut -d: -f2)
    ES_PORT=${ES_PORT:-9200}
    
    MAX_RETRIES=30
    RETRY_COUNT=0
    
    until curl -s "http://${ES_HOST}:${ES_PORT}/_cluster/health" >/dev/null 2>&1 || [ $RETRY_COUNT -ge $MAX_RETRIES ]; do
        echo "   Elasticsearch indisponível - aguardando... ($((RETRY_COUNT+1))/$MAX_RETRIES)"
        RETRY_COUNT=$((RETRY_COUNT+1))
        sleep 2
    done
    
    if [ $RETRY_COUNT -lt $MAX_RETRIES ]; then
        echo "✅ Elasticsearch está pronto!"
        
        # --------------------------------------------------
        # Instalar e configurar ElasticPress
        # --------------------------------------------------
        echo "📦 Configurando ElasticPress..."
        
        # Verificar se o plugin já está instalado
        if ! wp plugin is-installed elasticpress --allow-root --path=/var/www/html 2>/dev/null; then
            echo "   Instalando plugin ElasticPress..."
            wp plugin install elasticpress --activate --allow-root --path=/var/www/html
        else
            echo "   Plugin ElasticPress já instalado"
            # Ativar se não estiver ativo
            if ! wp plugin is-active elasticpress --allow-root --path=/var/www/html 2>/dev/null; then
                wp plugin activate elasticpress --allow-root --path=/var/www/html
            fi
        fi
        
        # Configurar host do Elasticsearch
        echo "   Configurando host Elasticsearch: http://${ELASTICSEARCH_HOST}"
        wp elasticpress set-host "http://${ELASTICSEARCH_HOST}" --allow-root --path=/var/www/html 2>/dev/null || true
        
        # Ativar funcionalidades do ElasticPress
        echo "   Ativando funcionalidades ElasticPress..."
        wp elasticpress activate-feature search --allow-root --path=/var/www/html 2>/dev/null || true
        wp elasticpress activate-feature related_posts --allow-root --path=/var/www/html 2>/dev/null || true
        wp elasticpress activate-feature facets --allow-root --path=/var/www/html 2>/dev/null || true
        wp elasticpress activate-feature searchordering --allow-root --path=/var/www/html 2>/dev/null || true
        wp elasticpress activate-feature autosuggest --allow-root --path=/var/www/html 2>/dev/null || true
        
        # Indexar conteúdo
        echo "   Indexando conteúdo no Elasticsearch..."
        wp elasticpress index --setup --allow-root --path=/var/www/html 2>/dev/null || echo "   ⚠️ Indexação será feita posteriormente via admin"
        
        echo "✅ ElasticPress configurado!"
    else
        echo "⚠️ Elasticsearch não respondeu - ElasticPress não será configurado"
    fi
fi

# --------------------------------------------------
# Ajustar permissões
# --------------------------------------------------
chown -R www-data:www-data /var/www/html

# --------------------------------------------------
# Iniciar PHP-FPM
# --------------------------------------------------
echo "🚀 Iniciando PHP-FPM..."
exec php-fpm83 -F
