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
# Verificar se cache Redis está habilitado e disponível
# --------------------------------------------------
REDIS_AVAILABLE=false

if [ "$WP_CACHE" = "true" ]; then
    echo "🔍 WP_CACHE habilitado - Verificando disponibilidade do Redis..."
    REDIS_AVAILABLE=true
    
    REDIS_HOST="${WP_REDIS_HOST:-redis}"
    REDIS_PORT="${WP_REDIS_PORT:-6379}"
    
    # Tentar conectar ao Redis (4 tentativas)
    MAX_RETRIES=4
    RETRY_COUNT=0

    # Ler senha do Redis via Docker Secret
    if [ -f /run/secrets/redis_password ]; then
        export REDIS_PASSWORD="$(cat /run/secrets/redis_password)"
        export WP_REDIS_PASSWORD="$REDIS_PASSWORD"
    else
        echo "⚠️  Secret redis_password não encontrado - Redis sem senha"
        export REDIS_PASSWORD=""
        export WP_REDIS_PASSWORD=""
    fi
else
    echo "ℹ️  WP_CACHE desabilitado - WordPress funcionará sem cache Redis"
fi

# --------------------------------------------------
# Criar diretório se não existir
# --------------------------------------------------
if [ ! -d /var/www/html ]; then
    mkdir -p /var/www/html
    chown www-data:www-data /var/www/html
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
    -e "SELECT 1 FROM information_schema.SCHEMATA WHERE SCHEMA_NAME='$WORDPRESS_DB_NAME'" >/dev/null 2>&1
do
    echo "   MariaDB indisponível - aguardando..."
    sleep 3
done

echo "✅ MariaDB está pronto!"

# --------------------------------------------------
# Instalar WordPress se necessário
# --------------------------------------------------
if ! wp core is-installed --allow-root --path=/var/www/html >/dev/null 2>&1; then
    echo "📦 Instalando WordPress..."
    
    # Ler credenciais do secret
    if [ -f /run/secrets/credentials ]; then
        CREDENTIALS_LINE_1="$(sed -n '1p' /run/secrets/credentials)"

        # Formato recomendado: username:password
        if echo "$CREDENTIALS_LINE_1" | grep -q ':'; then
            ADMIN_USER="$(echo "$CREDENTIALS_LINE_1" | cut -d: -f1)"
            ADMIN_PASSWORD="$(echo "$CREDENTIALS_LINE_1" | cut -d: -f2-)"
            ADMIN_EMAIL="${WP_ADMIN_EMAIL:-admin@${DOMAIN_NAME}}"
            AUTHOR_USER=""
            AUTHOR_EMAIL=""
            AUTHOR_PASSWORD=""
        else
            # Compatibilidade legada: múltiplas linhas
            ADMIN_USER="$CREDENTIALS_LINE_1"
            ADMIN_EMAIL="$(sed -n '2p' /run/secrets/credentials)"
            AUTHOR_USER="$(sed -n '3p' /run/secrets/credentials)"
            AUTHOR_EMAIL="$(sed -n '4p' /run/secrets/credentials)"
            ADMIN_PASSWORD="$(sed -n '5p' /run/secrets/credentials)"
            AUTHOR_PASSWORD="$(sed -n '6p' /run/secrets/credentials)"

            [ -n "$ADMIN_PASSWORD" ] || ADMIN_PASSWORD="$ADMIN_EMAIL"
            [ -n "$AUTHOR_PASSWORD" ] || AUTHOR_PASSWORD="$AUTHOR_EMAIL"
        fi
    else
        ADMIN_USER="admin"
        ADMIN_PASSWORD="admin123"
        ADMIN_EMAIL="admin@${DOMAIN_NAME}"
        AUTHOR_USER=""
        AUTHOR_EMAIL=""
        AUTHOR_PASSWORD=""
    fi
    
    # Baixar WordPress core se necessário
    if [ ! -f /var/www/html/wp-load.php ]; then
        echo "📥 Baixando WordPress..."
        wp core download --allow-root --path=/var/www/html
    fi
    
    # Criar wp-config.php se não existir
    if [ ! -f /var/www/html/wp-config.php ]; then
        echo "📝 Criando wp-config.php..."
        wp config create \
            --dbname="$WORDPRESS_DB_NAME" \
            --dbuser="$WORDPRESS_DB_USER" \
            --dbpass="$WORDPRESS_DB_PASSWORD" \
            --dbhost="$WORDPRESS_DB_HOST" \
            --locale=pt_PT \
            --allow-root \
            --path=/var/www/html
        
    fi
    
    # Instalar WordPress
    echo "🚀 Instalando WordPress..."
    wp core install \
        --url="https://${DOMAIN_NAME}" \
        --title="Organic Store" \
        --admin_user="$ADMIN_USER" \
        --admin_password="$ADMIN_PASSWORD" \
        --admin_email="$ADMIN_EMAIL" \
        --allow-root \
        --path=/var/www/html
    
    echo "✅ WordPress instalado!"
    
    # Instalar plugins essenciais
    echo "📦 Instalando plugins..."

    # Gutenberg (editor de blocos moderno)
    wp plugin install gutenberg --activate --allow-root --path=/var/www/html
    
    echo "✅ Instalação básica completa!"
    echo "ℹ️  Acesse https://${DOMAIN_NAME}/wp-admin para importar o template Organic Store"
    
    # Criar segundo usuário se definido
    if [ -n "$AUTHOR_USER" ] && [ "$AUTHOR_USER" != "$ADMIN_USER" ]; then
        echo "👤 Criando usuário autor: $AUTHOR_USER"
        wp user create "$AUTHOR_USER" "$AUTHOR_EMAIL" \
            --role=author \
            --user_pass="$AUTHOR_PASSWORD" \
            --allow-root \
            --path=/var/www/html 2>/dev/null || true
    fi
else
    echo "ℹ️  WordPress já instalado"
fi

# Garantir configurações Redis no wp-config.php quando WP_CACHE estiver habilitado
if [ "$WP_CACHE" = "true" ] && [ -f /var/www/html/wp-config.php ]; then
    echo "   Normalizando constantes de cache no wp-config.php..."

    # Limpar possíveis definições duplicadas inseridas manualmente
    sed -i "/define('WP_CACHE', true);/d" /var/www/html/wp-config.php
    sed -i "/define('WP_REDIS_HOST'/d" /var/www/html/wp-config.php
    sed -i "/define('WP_REDIS_PORT'/d" /var/www/html/wp-config.php
    sed -i "/define('WP_REDIS_PASSWORD'/d" /var/www/html/wp-config.php
    sed -i "/define('WP_REDIS_DATABASE'/d" /var/www/html/wp-config.php
    sed -i "/define('WP_CACHE_KEY_SALT'/d" /var/www/html/wp-config.php
    sed -i "/define( 'WP_CACHE_KEY_SALT'/d" /var/www/html/wp-config.php

    # Recriar constantes de forma idempotente e no local correto
    wp config set WP_CACHE true --raw --type=constant --allow-root --path=/var/www/html
    wp config set WP_REDIS_HOST "${WP_REDIS_HOST:-redis}" --type=constant --allow-root --path=/var/www/html
    wp config set WP_REDIS_PORT "${WP_REDIS_PORT:-6379}" --raw --type=constant --allow-root --path=/var/www/html
    wp config set WP_REDIS_PASSWORD "$WP_REDIS_PASSWORD" --type=constant --allow-root --path=/var/www/html
    wp config set WP_REDIS_DATABASE 0 --raw --type=constant --allow-root --path=/var/www/html
    wp config set WP_CACHE_KEY_SALT "${DOMAIN_NAME:-wordpress}" --type=constant --allow-root --path=/var/www/html
fi

# --------------------------------------------------
# Instalar e configurar Redis Cache (se disponível)
# --------------------------------------------------
if [ "$WP_CACHE" = true ]; then
    echo "📦 Configurando Redis Cache..."
    REDIS_HOST=$(echo "${WP_REDIS_HOST:-redis}" | cut -d: -f1)
    REDIS_PORT="${WP_REDIS_PORT:-6379}"

    MAX_RETRIES=4
    RETRY_COUNT=0

    until nc -z "$REDIS_HOST" "$REDIS_PORT" >/dev/null 2>&1 || [ $RETRY_COUNT -ge $MAX_RETRIES ]; do
        echo " Redis indisponível - aguardando... ($((RETRY_COUNT+1))/$MAX_RETRIES)"
        RETRY_COUNT=$((RETRY_COUNT+1))
        sleep 2
    done

    
    if [ $RETRY_COUNT -lt $MAX_RETRIES ]; then
        echo "Redis está pronto!"
        echo "Configurando Redis Cache..."

        # Reabilitar object-cache.php se foi desabilitado
        if [ -f /var/www/html/wp-content/object-cache.php.disabled ]; then
            mv /var/www/html/wp-content/object-cache.php.disabled /var/www/html/wp-content/object-cache.php
        fi

        # Verificar se o plugin já está instalado
        if ! wp plugin is-installed redis-cache --allow-root --path=/var/www/html 2>/dev/null; then
            echo "   Instalando plugin Redis Object Cache..."
            wp plugin install redis-cache --activate --allow-root --path=/var/www/html
        else
            echo "   Plugin Redis Cache já instalado"
            # Ativar se não estiver ativo
            if ! wp plugin is-active redis-cache --allow-root --path=/var/www/html 2>/dev/null; then
                wp plugin activate redis-cache --allow-root --path=/var/www/html
            fi
        fi

        # Habilitar o Object Cache drop-in
        echo "   Habilitando Redis Object Cache..."
        ENABLE_RETRIES=5
        ENABLE_COUNT=0
        ENABLED=false

        while [ $ENABLE_COUNT -lt $ENABLE_RETRIES ]; do
            if wp redis enable --allow-root --path=/var/www/html >/dev/null 2>&1; then
                ENABLED=true
                break
            fi

            ENABLE_COUNT=$((ENABLE_COUNT+1))
            echo "   Redis ainda não pronto para object cache... ($ENABLE_COUNT/$ENABLE_RETRIES)"
            sleep 2
        done

        if [ "$ENABLED" = true ]; then
            echo "   ✅ Object cache habilitado"
        else
            echo "   :warning: Object cache será habilitado via admin"
        fi

        # Verificar status do Redis
        echo "   Verificando conexão com Redis..."
        wp redis status --allow-root --path=/var/www/html 2>/dev/null || echo "   :warning: Redis configurado, mas conexão não verificada"

        echo "Redis Cache configurado!"
    else
        echo "Redis não respondeu - continuando sem cache"
        # Desabilitar cache temporariamente se Redis não estiver disponível
        if [ -f /var/www/html/wp-content/object-cache.php ]; then
            mv /var/www/html/wp-content/object-cache.php /var/www/html/wp-content/object-cache.php.disabled
        fi
    fi
else
    # Desativar plugin Redis Cache se estiver ativo
    if wp plugin is-active redis-cache --allow-root --path=/var/www/html 2>/dev/null; then
        echo "   Desativando plugin Redis Cache (Redis não disponível)..."
        wp plugin deactivate redis-cache --allow-root --path=/var/www/html 2>/dev/null || true
    fi
fi

# --------------------------------------------------
# Aguardar Elasticsearch (se configurado)
# --------------------------------------------------
if [ -n "$ELASTICSEARCH_HOST" ]; then
    echo "⏳ Aguardando Elasticsearch..."
    ES_HOST=$(echo "$ELASTICSEARCH_HOST" | cut -d: -f1)
    ES_PORT=$(echo "$ELASTICSEARCH_HOST" | cut -d: -f2)
    ES_PORT=${ES_PORT:-9200}
    
    MAX_RETRIES=4
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
        wp config set EP_HOST "http://${ELASTICSEARCH_HOST}" --type=constant --allow-root --path=/var/www/html >/dev/null 2>&1 || true
        wp option update ep_host "http://${ELASTICSEARCH_HOST}" --allow-root --path=/var/www/html >/dev/null 2>&1 || true
        
        # Ativar funcionalidades do ElasticPress
        echo "   Ativando funcionalidades ElasticPress..."
        wp elasticpress activate-feature search --allow-root --path=/var/www/html 2>/dev/null || true
        wp elasticpress activate-feature related_posts --allow-root --path=/var/www/html 2>/dev/null || true
        wp elasticpress activate-feature facets --allow-root --path=/var/www/html 2>/dev/null || true
        wp elasticpress activate-feature searchordering --allow-root --path=/var/www/html 2>/dev/null || true
        wp elasticpress activate-feature autosuggest --allow-root --path=/var/www/html 2>/dev/null || true
        
        # Indexar conteúdo
        echo "   Sincronizando conteúdo no Elasticsearch..."
        if wp elasticpress sync --setup --yes --allow-root --path=/var/www/html >/dev/null 2>&1; then
            echo "   ✅ ElasticPress sincronizado"
        elif wp elasticpress index --setup --yes --allow-root --path=/var/www/html >/dev/null 2>&1; then
            echo "   ✅ ElasticPress indexado"
        else
            echo "   ⚠️ Indexação será feita posteriormente via admin"
        fi
        
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
