# --------------------------------------------------
# Aguardar Elasticsearch (se configurado)
# --------------------------------------------------
if [ -n "$ELASTICSEARCH_HOST" ]; then
    echo ":hourglass_flowing_sand: Aguardando Elasticsearch..."
    ES_HOST=$(echo "$ELASTICSEARCH_HOST" | cut -d: -f1)
    ES_PORT=$(echo "$ELASTICSEARCH_HOST" | cut -d: -f2)
    ES_PORT=${ES_PORT:-9200}

    MAX_RETRIES=8
    RETRY_COUNT=0

    until curl -s "http://${ES_HOST}:${ES_PORT}/_cluster/health" >/dev/null 2>&1 || [ $RETRY_COUNT -ge $MAX_RETRIES ]; do
        echo "   Elasticsearch indisponível - aguardando... ($((RETRY_COUNT+1))/$MAX_RETRIES)"
        RETRY_COUNT=$((RETRY_COUNT+1))
        sleep 2
    done

    if [ $RETRY_COUNT -lt $MAX_RETRIES ]; then
        echo ":white_check_mark: Elasticsearch está pronto!"

        # --------------------------------------------------
        # Instalar e configurar ElasticPress
        # --------------------------------------------------
        echo ":package: Configurando ElasticPress..."

        # Verificar se o plugin já está instalado
        if ! wp plugin is-installed elasticpress --allow-root --path=/var/www/html 2>/dev/null; then
            echo "   Instalando plugin ElasticPress..."
            wp plugin install elasticpress --activate --allow-root --path=/var/www/html
        else
            echo "   Plugin ElasticPress já instalado"
            # Ativar se não estiver ativo
            if ! wp plugin is-active elasticpress --allow-root --path=/var/www/html 2>/dev/null; then
                wp plugin activate elasticpress --allow-root --path=/var/www/html
            fi
        fi

        # Configurar host do Elasticsearch
        echo "   Configurando host Elasticsearch: http://${ELASTICSEARCH_HOST}"
        wp elasticpress set-host "http://${ELASTICSEARCH_HOST}" --allow-root --path=/var/www/html 2>/dev/null || true

        # Ativar funcionalidades do ElasticPress
        echo "   Ativando funcionalidades ElasticPress..."
        wp elasticpress activate-feature search --allow-root --path=/var/www/html 2>/dev/null || true
        wp elasticpress activate-feature related_posts --allow-root --path=/var/www/html 2>/dev/null || true
        wp elasticpress activate-feature facets --allow-root --path=/var/www/html 2>/dev/null || true
        wp elasticpress activate-feature searchordering --allow-root --path=/var/www/html 2>/dev/null || true
        wp elasticpress activate-feature autosuggest --allow-root --path=/var/www/html 2>/dev/null || true

        # Indexar conteúdo
        echo "   Indexando conteúdo no Elasticsearch..."
        wp elasticpress index --setup --allow-root --path=/var/www/html 2>/dev/null || echo "   :warning: Indexação será feita posteriormente via admin"

        echo ":white_check_mark: ElasticPress configurado!"
    else
        echo ":warning: Elasticsearch não respondeu - ElasticPress não será configurado"
    fi
fi

# --------------------------------------------------
# Configurar Redis Cache Plugin
# --------------------------------------------------
if [ -n "$REDIS_PASSWORD" ]; then


    if [ $RETRY_COUNT -lt $MAX_RETRIES ]; then
        echo ":white_check_mark: Redis está pronto!"
        echo ":package: Configurando Redis Cache..."

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

        echo ":white_check_mark: Redis Cache configurado!"
    else
        echo ":warning: Redis não respondeu - continuando sem cache"
        # Desabilitar cache temporariamente se Redis não estiver disponível
        if [ -f /var/www/html/wp-content/object-cache.php ]; then
            mv /var/www/html/wp-content/object-cache.php /var/www/html/wp-content/object-cache.php.disabled
        fi
    fi
fi



























RUN apk add --no-cache \
    php83 \
    php83-fpm \
    php83-mysqli \
    php83-pdo \
    php83-pdo_mysql \
    php83-gd \
    php83-intl \
    php83-mbstring \
    php83-xml \
    php83-zip \
    php83-opcache \
    php83-curl \
    php83-tokenizer \
    php83-session \
    php83-phar \
    php83-pecl-redis \
    mariadb-client \
    curl \
    less