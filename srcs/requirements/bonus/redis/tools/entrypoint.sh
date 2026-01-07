#!/bin/sh
set -e

# Ler senha do secret
if [ -f /run/secrets/redis_password ]; then
    REDIS_PASSWORD=$(cat /run/secrets/redis_password)
    # Substituir no arquivo de configuração
    sed -i "s/REDIS_PASSWORD_PLACEHOLDER/$REDIS_PASSWORD/g" /etc/redis/redis.conf
fi

# Iniciar Redis
exec redis-server /etc/redis/redis.conf