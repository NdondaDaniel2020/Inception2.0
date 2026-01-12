FROM alpine:3.23

# Instalar dependências necessárias
RUN apk add --no-cache \
    openjdk17-jre \
    bash \
    wget \
    tar \
    gzip

# Definir variável de ambiente para versão do Elasticsearch
ENV ES_VERSION=8.11.3 \
    ES_HOME=/usr/share/elasticsearch \
    ES_PATH_CONF=/etc/elasticsearch

# Criar usuário elasticsearch
RUN addgroup -g 1000 elasticsearch && \
    adduser -D -u 1000 -G elasticsearch elasticsearch

# Criar diretórios necessários
RUN mkdir -p ${ES_HOME} ${ES_PATH_CONF} /var/lib/elasticsearch /var/log/elasticsearch && \
    chown -R elasticsearch:elasticsearch ${ES_HOME} ${ES_PATH_CONF} /var/lib/elasticsearch /var/log/elasticsearch

# Download e instalação do Elasticsearch
WORKDIR /tmp
RUN wget -q https://artifacts.elastic.co/downloads/elasticsearch/elasticsearch-${ES_VERSION}-linux-x86_64.tar.gz && \
    tar -xzf elasticsearch-${ES_VERSION}-linux-x86_64.tar.gz -C ${ES_HOME} --strip-components=1 && \
    rm -f elasticsearch-${ES_VERSION}-linux-x86_64.tar.gz && \
    rm -rf ${ES_HOME}/jdk && \
    rm -rf ${ES_HOME}/modules/x-pack-ml && \
    cp -r ${ES_HOME}/config/* ${ES_PATH_CONF}/ && \
    chown -R elasticsearch:elasticsearch ${ES_HOME} ${ES_PATH_CONF}

# Configurar para usar o Java do Alpine
ENV JAVA_HOME=/usr/lib/jvm/java-17-openjdk \
    ES_JAVA_HOME=/usr/lib/jvm/java-17-openjdk

# Copiar configuração
COPY conf/elasticsearch.yml ${ES_PATH_CONF}/elasticsearch.yml

# Copiar script de entrypoint
COPY tools/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# Definir permissões corretas
RUN chown -R elasticsearch:elasticsearch ${ES_PATH_CONF}

# Expor portas
EXPOSE 9200 9300

# Mudar para usuário elasticsearch
USER elasticsearch

WORKDIR ${ES_HOME}

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
