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
    
    REDIS_HOST="${WP_REDIS_HOST:-redis}"
    REDIS_PORT="${WP_REDIS_PORT:-6379}"
    
    # Tentar conectar ao Redis (4 tentativas)
    MAX_RETRIES=4
    RETRY_COUNT=0

    # Ler senha do Redis via Docker Secret
    if [ -f /run/secrets/redis_password ]; then
        export REDIS_PASSWORD="$(cat /run/secrets/redis_password)"
    else
        echo "⚠️  Secret redis_password não encontrado - Redis sem senha"
        export REDIS_PASSWORD=""
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
        ADMIN_USER="$(sed -n '1p' /run/secrets/credentials)"
        ADMIN_EMAIL="$(sed -n '2p' /run/secrets/credentials)"
        AUTHOR_USER="$(sed -n '3p' /run/secrets/credentials)"
        AUTHOR_EMAIL="$(sed -n '4p' /run/secrets/credentials)"
    else
        ADMIN_USER="admin"
        ADMIN_EMAIL="admin@${DOMAIN_NAME}"
        AUTHOR_USER=""
        AUTHOR_EMAIL=""
    fi
    
    # Senha vem do db_password
    if [ -f /run/secrets/db_password ]; then
        USER_PASS="$(cat /run/secrets/db_password)"
    else
        USER_PASS="changeme"
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
        
        # Adicionar configurações do Redis se disponível
        if [ "$REDIS_AVAILABLE" = true ]; then
            echo "   Adicionando configurações do Redis..."
            cat >> /var/www/html/wp-config.php <<-'EOF'
				
				/* Redis Cache Configuration */
				define('WP_REDIS_HOST', getenv('WP_REDIS_HOST') ?: 'redis');
				define('WP_REDIS_PORT', getenv('WP_REDIS_PORT') ?: 6379);
				define('WP_REDIS_PASSWORD', getenv('WP_REDIS_PASSWORD') ?: '');
				define('WP_REDIS_DATABASE', 0);
				define('WP_CACHE_KEY_SALT', getenv('DOMAIN_NAME') ?: 'wordpress');
				EOF
        fi
    fi
    
    # Instalar WordPress
    echo "🚀 Instalando WordPress..."
    wp core install \
        --url="https://${DOMAIN_NAME}" \
        --title="Organic Store" \
        --admin_user="$ADMIN_USER" \
        --admin_password="$USER_PASS" \
        --admin_email="$ADMIN_EMAIL" \
        --allow-root \
        --path=/var/www/html
    
    echo "✅ WordPress instalado!"
    
    # Instalar e ativar tema Astra
    echo "🎨 Instalando tema Astra..."
    wp theme install astra --activate --allow-root --path=/var/www/html
    
    # Instalar plugins essenciais para Organic Store
    echo "📦 Instalando plugins..."
    
    # WooCommerce (loja online)
    wp plugin install woocommerce --activate --allow-root --path=/var/www/html
    
    # Elementor (page builder usado pelo Astra)
    wp plugin install elementor --activate --allow-root --path=/var/www/html
    
    # Starter Templates (para importar demos do Astra)
    wp plugin install astra-sites --activate --allow-root --path=/var/www/html
    
    echo "✅ Instalação básica completa!"
    echo "ℹ️  Acesse https://${DOMAIN_NAME}/wp-admin para importar o template Organic Store"
    
    # Criar segundo usuário se definido
    if [ -n "$AUTHOR_USER" ] && [ "$AUTHOR_USER" != "$ADMIN_USER" ]; then
        echo "👤 Criando usuário autor: $AUTHOR_USER"
        wp user create "$AUTHOR_USER" "$AUTHOR_EMAIL" \
            --role=author \
            --user_pass="$USER_PASS" \
            --allow-root \
            --path=/var/www/html 2>/dev/null || true
    fi
else
    echo "ℹ️  WordPress já instalado"
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
        wp redis enable --allow-root --path=/var/www/html 2>/dev/null || echo "   :warning: Object cache será habilitado via admin"

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
