#!/bin/bash

set -e

echo "Starting Elasticsearch..."

# Verificar se os diretórios existem e têm permissões corretas
if [ ! -d "/var/lib/elasticsearch" ]; then
    mkdir -p /var/lib/elasticsearch
fi

if [ ! -d "/var/log/elasticsearch" ]; then
    mkdir -p /var/log/elasticsearch
fi

# Configurar JVM options para ambientes com pouca memória
export ES_JAVA_OPTS="-Xms512m -Xmx512m"

# Iniciar Elasticsearch
exec /usr/share/elasticsearch/bin/elasticsearch
