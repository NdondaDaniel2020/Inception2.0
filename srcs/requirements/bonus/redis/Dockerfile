FROM alpine:3.22

# Instalar Redis
RUN apk add --no-cache redis

# Copiar configuração customizada (opcional mas recomendado)
COPY conf/redis.conf /etc/redis/redis.conf

COPY ./tools/entrypoint.sh /usr/local/bin/

RUN chmod +x /usr/local/bin/entrypoint.sh

# Expor porta padrão
EXPOSE 6379

# Iniciar Redis com configuração
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
