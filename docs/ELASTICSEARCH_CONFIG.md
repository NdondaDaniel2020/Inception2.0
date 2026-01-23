# Configuração Elasticsearch - Documentação Técnica

Este documento fornece uma explicação detalhada de todas as configurações Elasticsearch utilizadas no projeto Inception, incluindo cluster, networking, segurança e integração com WordPress via ElasticPress.

---

## Índice

1. [Introdução ao Elasticsearch](#introdução-ao-elasticsearch)
2. [Configuração elasticsearch.yml](#configuração-elasticsearchyml)
3. [Script Entrypoint](#script-entrypoint)
4. [Integração com WordPress](#integração-com-wordpress)
5. [Monitorização e Debug](#monitorização-e-debug)
6. [Referências](#referências)

---

## Introdução ao Elasticsearch

### O que é Elasticsearch?

**Elasticsearch** é um motor de busca e analytics distribuído baseado em Apache Lucene.

**Características principais:**
- ✅ Busca full-text rápida
- ✅ Analytics em tempo real
- ✅ RESTful API JSON
- ✅ Escalabilidade horizontal
- ✅ Schema-free (NoSQL)
- ✅ Alta disponibilidade

### Componentes Arquiteturais

```
┌─────────────────────────────────────────────────────────┐
│                     Cluster                             │
├─────────────────────────────────────────────────────────┤
│  ┌─────────────┐    ┌─────────────┐    ┌─────────────┐  │
│  │   Node 1    │    │   Node 2    │    │   Node 3    │  │
│  │             │    │             │    │             │  │
│  │ ┌─────────┐ │    │ ┌─────────┐ │    │ ┌─────────┐ │  │
│  │ │ Index 1 │ │    │ │ Index 2 │ │    │ │ Index 3 │ │  │
│  │ │ ├─────┤ │ │    │ │ ├─────┤ │ │    │ │ ├─────┤ │ │  │
│  │ │ │Shard│ │ │    │ │ │Shard│ │ │    │ │ │Shard│ │ │  │
│  │ │ └─────┘ │ │    │ │ └─────┘ │ │    │ │ └─────┘ │ │  │
│  │ └─────────┘ │    │ └─────────┘ │    │ └─────────┘ │  │
│  └─────────────┘    └─────────────┘    └─────────────┘  │
└─────────────────────────────────────────────────────────┘
```

**Conceitos chave:**

| Conceito | Descrição | Exemplo |
|----------|-----------|---------|
| **Cluster** | Grupo de nós | `inception-cluster` |
| **Node** | Instância Elasticsearch | `inception-node-1` |
| **Index** | Coleção de documentos | `wp_posts` |
| **Shard** | Partição de dados | Primary/Replica |
| **Document** | Unidade de dados JSON | Post WordPress |

### Uso no WordPress

**ElasticPress Plugin:**
```
WordPress → ElasticPress → Elasticsearch
    ↓              ↓              ↓
   MySQL        Indexa dados    Busca rápida
    ↑              ↑              ↑
   UPDATE       Reindexa       Busca instantânea
```

**Benefícios:**
- ✅ Busca full-text em posts/páginas
- ✅ Filtros avançados (categorias, tags, autores)
- ✅ Busca facetada
- ✅ Autocomplete
- ✅ Performance superior ao MySQL

---

## Configuração elasticsearch.yml

**Ficheiro:** `srcs/requirements/bonus/elasticsearch/conf/elasticsearch.yml`

### Configuração do Cluster

#### cluster.name

```yaml
cluster.name: inception-cluster
```

**Explicação Detalhada:**

- **Diretiva:** `cluster.name`
- **Valor:** `inception-cluster`
- **Descrição:** Nome único do cluster Elasticsearch

**Por que importante?**
- Nós descobrem-se através do nome do cluster
- Isolamento entre ambientes
- Monitorização e administração

**Exemplo de descoberta:**
```
Node 1: cluster.name = "prod-cluster"
Node 2: cluster.name = "prod-cluster" → Junta-se ✅

Node 3: cluster.name = "dev-cluster" → Não junta-se ❌
```

**Convenções de nome:**
- `production-cluster`
- `development-cluster`
- `inception-cluster` (nosso projeto)

**Alterar nome:**
- Para cluster existente: **NÃO POSSÍVEL**
- Requer novo cluster
- Dados não migráveis automaticamente

---

#### node.name

```yaml
node.name: inception-node-1
```

**Explicação Detalhada:**

- **Diretiva:** `node.name`
- **Valor:** `inception-node-1`
- **Descrição:** Nome único do nó dentro do cluster

**Identificação:**
- Aparece em logs e APIs
- Facilita monitorização
- Distingue nós em cluster multi-node

**Convenções:**
```
inception-node-1
inception-node-2
inception-data-1
inception-master-1
```

**API para verificar:**
```bash
curl -X GET "elasticsearch:9200/_cat/nodes?v"
# Output: name, ip, node.role, etc.
```

---

### Diretórios

#### path.data

```yaml
path.data: /var/lib/elasticsearch
```

**Explicação Detalhada:**

- **Diretiva:** `path.data`
- **Valor:** `/var/lib/elasticsearch`
- **Descrição:** Diretório onde Elasticsearch armazena dados

**Conteúdo armazenado:**
```
/var/lib/elasticsearch/
├── nodes/
│   └── 0/
│       ├── indices/          # Dados dos índices
│       ├── _state/           # Estado do cluster
│       └── translog/         # Transaction logs
├── cluster/                  # Metadados do cluster
└── temp/                     # Arquivos temporários
```

**Requisitos:**
- ✅ Permissões de escrita
- ✅ Espaço em disco suficiente
- ✅ Backup regular
- ✅ Volume Docker persistente

**Docker volume:**
```yaml
volumes:
  - elasticsearch_data:/var/lib/elasticsearch
```

---

#### path.logs

```yaml
path.logs: /var/log/elasticsearch
```

**Explicação Detalhada:**

- **Diretiva:** `path.logs`
- **Valor:** `/var/log/elasticsearch`
- **Descrição:** Diretório para logs do Elasticsearch

**Logs gerados:**
```
/var/log/elasticsearch/
├── inception-cluster.log          # Log principal
├── inception-cluster_deprecation.log  # Avisos de depreciação
├── inception-cluster_index_search_slowlog.log  # Queries lentas
└── inception-cluster_index_indexing_slowlog.log  # Indexação lenta
```

**Configuração de log levels:**
```yaml
logger:
  org.elasticsearch.search: DEBUG
  org.elasticsearch.index: INFO
```

**Monitorização:**
```bash
docker logs elasticsearch
tail -f /var/log/elasticsearch/inception-cluster.log
```

---

### Network Settings

#### network.host

```yaml
network.host: 0.0.0.0
```

**Explicação Detalhada:**

- **Diretiva:** `network.host`
- **Valor:** `0.0.0.0`
- **Descrição:** Interface de rede onde Elasticsearch escuta

**Significado de 0.0.0.0:**
- Aceita conexões em todas as interfaces
- Equivalente a `listen` em outros serviços

**Interfaces disponíveis:**
- `127.0.0.1` - Apenas localhost
- `172.18.0.5` - IP específico do container
- `0.0.0.0` - Todas as interfaces

**Docker networking:**
```
Container: 172.18.0.5
network.host: 0.0.0.0 → Escuta em 172.18.0.5:9200

WordPress → elasticsearch:9200 → 172.18.0.5:9200 ✅
```

**Segurança:**
- ✅ Isolado pela rede Docker
- ✅ Não exposto externamente
- ✅ Sem port mapping no docker-compose

---

#### http.port

```yaml
http.port: 9200
```

**Explicação Detalhada:**

- **Diretiva:** `http.port`
- **Valor:** `9200`
- **Descrição:** Porta HTTP para API REST

**Uso da porta:**
- Cliente HTTP/REST → Elasticsearch
- WordPress/ElasticPress → API
- Ferramentas como Kibana

**Exemplo de request:**
```bash
curl -X GET "elasticsearch:9200/_cluster/health"
```

**Porta padrão:**
- 9200 é padrão oficial
- Mudar apenas se conflito
- Documentado universalmente

---

#### transport.port

```yaml
transport.port: 9300
```

**Explicação Detalhada:**

- **Diretiva:** `transport.port`
- **Valor:** `9300`
- **Descrição:** Porta para comunicação inter-node (TCP)

**Uso:**
- Comunicação entre nós do cluster
- Replicação de dados
- Coordenação de shards

**Diferença HTTP vs Transport:**

| Porta | Protocolo | Uso | Cliente |
|-------|-----------|-----|---------|
| 9200 | HTTP/REST | API externa | WordPress, curl |
| 9300 | TCP Binary | Inter-node | Nós Elasticsearch |

**No nosso caso (single-node):**
- Porta 9300 não usada
- Mas configurada para consistência

---

### Descoberta de Nós

#### discovery.type

```yaml
discovery.type: single-node
```

**Explicação Detalhada:**

- **Diretiva:** `discovery.type`
- **Valor:** `single-node`
- **Descrição:** Configuração para cluster de nó único

**Tipos de discovery:**

| Tipo | Descrição | Uso |
|------|-----------|-----|
| `single-node` | Nó único, sem descoberta | **Desenvolvimento** ✅ |
| `zen` | Descoberta automática | Produção multi-node |
| `file` | Lista de hosts em arquivo | Configurado manualmente |

**Por que single-node?**
- Ambiente de desenvolvimento
- Não há outros nós
- Simplifica configuração
- Evita timeouts de descoberta

**Para multi-node (produção):**
```yaml
discovery.type: zen
discovery.zen.ping.unicast.hosts: ["node1:9300", "node2:9300"]
```

---

### Segurança (X-Pack)

#### xpack.security.enabled

```yaml
xpack.security.enabled: false
```

**Explicação Detalhada:**

- **Diretiva:** `xpack.security.enabled`
- **Valor:** `false`
- **Descrição:** Desabilita módulo de segurança X-Pack

**O que inclui:**
- Autenticação de usuários
- Autorização baseada em roles
- SSL/TLS para transporte
- Audit logging

**Por que desabilitar?**
- Ambiente de desenvolvimento
- Simplifica configuração
- Não há usuários múltiplos
- Rede Docker já isola

**Para produção:**
```yaml
xpack.security.enabled: true
xpack.security.transport.ssl.enabled: true
xpack.security.http.ssl.enabled: true
```

---

#### xpack.security.enrollment.enabled

```yaml
xpack.security.enrollment.enabled: false
```

**Explicação Detalhada:**

- **Diretiva:** `xpack.security.enrollment.enabled`
- **Valor:** `false`
- **Descrição:** Desabilita enrollment automático de nós

**Enrollment:**
- Processo automático de configuração de segurança
- Gera certificados SSL
- Configura usuários iniciais

**Desabilitado porque:**
- Segurança já desabilitada
- Configuração manual não necessária
- Ambiente containerizado

---

#### xpack.security.http.ssl.enabled

```yaml
xpack.security.http.ssl.enabled: false
```

**Explicação Detalhada:**

- **Diretiva:** `xpack.security.http.ssl.enabled`
- **Valor:** `false`
- **Descrição:** Desabilita SSL para API HTTP

**SSL HTTP:**
- Criptografa comunicação REST
- Previne MITM attacks
- Requer certificados

**Desabilitado porque:**
- Rede Docker interna
- Desenvolvimento local
- Overhead desnecessário

---

#### xpack.security.transport.ssl.enabled

```yaml
xpack.security.transport.ssl.enabled: false
```

**Explicação Detalhada:**

- **Diretiva:** `xpack.security.transport.ssl.enabled`
- **Valor:** `false`
- **Descrição:** Desabilita SSL para comunicação inter-node

**SSL Transport:**
- Criptografa dados entre nós
- Autenticação de nós
- Integridade de dados

**Desabilitado porque:**
- Single-node (sem comunicação inter-node)
- Ambiente isolado

---

### Machine Learning

#### xpack.ml.enabled

```yaml
xpack.ml.enabled: false
```

**Explicação Detalhada:**

- **Diretiva:** `xpack.ml.enabled`
- **Valor:** `false`
- **Descrição:** Desabilita Machine Learning

**O que é X-Pack ML:**
- Análise preditiva
- Detecção de anomalias
- Forecasting
- Requer binários nativos

**Por que desabilitar?**
- Alpine Linux incompatível
- Binários nativos não disponíveis
- Não usado no projeto
- Reduz tamanho da imagem

**Erro sem desabilitar:**
```
Native controller process has stopped - no new native processes can be started
```

---

### Memória

#### bootstrap.memory_lock

```yaml
bootstrap.memory_lock: false
```

**Explicação Detalhada:**

- **Diretiva:** `bootstrap.memory_lock`
- **Valor:** `false`
- **Descrição:** Desabilita lock de memória JVM

**Memory Lock:**
- Previne swapping da heap JVM
- Performance consistente
- Requer permissões root

**Desabilitado porque:**
- Container Docker
- Usuário elasticsearch (não root)
- Ambiente de desenvolvimento

**Para habilitar:**
```yaml
bootstrap.memory_lock: true
# E permissões no container
```

---

### Configurações de Índice

#### action.auto_create_index

```yaml
action.auto_create_index: true
```

**Explicação Detalhada:**

- **Diretiva:** `action.auto_create_index`
- **Valor:** `true`
- **Descrição:** Permite criação automática de índices

**Auto-create:**
- ElasticPress cria índices automaticamente
- `wp_posts`, `wp_users`, etc.
- Sem intervenção manual

**Exemplo:**
```bash
# ElasticPress instala
PUT /wp_posts → Cria índice automaticamente ✅
```

**Para controle manual:**
```yaml
action.auto_create_index: false
# Criar índices explicitamente
```

**Padrões de nome:**
```yaml
action.auto_create_index: "wp_*,-wp_users"
# Permite wp_* exceto wp_users
```

---

## Script Entrypoint

**Ficheiro:** `srcs/requirements/bonus/elasticsearch/tools/entrypoint.sh`

### Script Completo Anotado

```bash
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
```

### Explicação Linha por Linha

#### Shebang

```bash
#!/bin/bash
```

- **Interpretador:** Bash shell
- **Alpine Linux:** Tem bash disponível
- **Funcionalidades:** Suporte a arrays, etc.

#### Error Handling

```bash
set -e
```

- **Descrição:** Termina script se qualquer comando falhar
- **Importante:** Previne inicialização com erros

#### Log Inicial

```bash
echo "Starting Elasticsearch..."
```

- **Output:** Mensagem no log do container
- **Debug:** Confirma que entrypoint executou

#### Verificação Diretório Data

```bash
if [ ! -d "/var/lib/elasticsearch" ]; then
    mkdir -p /var/lib/elasticsearch
fi
```

**`[ ! -d ... ]`:**
- Testa se diretório NÃO existe
- `!` = negação
- `-d` = é diretório

**`mkdir -p`:**
- Cria diretório recursivamente
- `-p` = pais também, se necessário

**Por que verificar?**
- Volume Docker pode estar vazio
- Primeiro run do container
- Garante diretório existe

#### Verificação Diretório Logs

```bash
if [ ! -d "/var/log/elasticsearch" ]; then
    mkdir -p /var/log/elasticsearch
fi
```

- **Igual ao anterior:** Para `/var/log/elasticsearch`
- **Separação:** Logs ≠ dados
- **Permissões:** Elasticsearch precisa escrever

#### Configuração JVM

```bash
export ES_JAVA_OPTS="-Xms512m -Xmx512m"
```

**`export`:**
- Torna variável disponível para subprocessos
- Elasticsearch herda a variável

**`ES_JAVA_OPTS`:**
- Opções passadas para JVM
- Usadas pelo script elasticsearch

**`-Xms512m`:**
- Minimum heap size: 512MB
- JVM aloca pelo menos 512MB

**`-Xmx512m`:**
- Maximum heap size: 512MB
- JVM não usa mais que 512MB

**Por que 512MB?**
- Container limitado
- Suficiente para desenvolvimento
- Evita OOM (Out of Memory)

**Regras gerais:**
- Xms = Xmx (heap fixo)
- Máximo 50% da RAM do sistema
- Para produção: 31GB máximo (ponto de otimização JVM)

#### Inicialização

```bash
exec /usr/share/elasticsearch/bin/elasticsearch
```

**`exec`:**
- Substitui shell por elasticsearch
- Elasticsearch torna-se PID 1

**Caminho:**
- `/usr/share/elasticsearch/bin/elasticsearch`
- Localização padrão na imagem Docker

**Por que exec?**
- Sinais vão para elasticsearch
- Shutdown gracioso
- Container para corretamente

---

## Integração com WordPress

### ElasticPress Plugin

**Instalação:**
```bash
wp plugin install elasticpress --activate --allow-root
```

**Configuração:**
```php
// wp-config.php
define('EP_HOST', 'http://elasticsearch:9200');
define('EP_INDEX_PREFIX', 'wp');
```

### Funcionalidades

#### Busca Full-Text

**Sem ElasticPress:**
```sql
SELECT * FROM wp_posts 
WHERE post_content LIKE '%palavra%'
```

**Com ElasticPress:**
```json
GET /wp_posts/_search
{
  "query": {
    "multi_match": {
      "query": "palavra",
      "fields": ["post_title", "post_content"]
    }
  }
}
```

#### Indexação Automática

**Quando conteúdo muda:**
```
WordPress → Hook (save_post) → ElasticPress → Indexa no Elasticsearch
```

**Campos indexados:**
- post_title
- post_content
- post_excerpt
- taxonomies (categories, tags)
- meta fields
- author

#### Busca Avançada

**Filtros:**
```json
{
  "query": {
    "bool": {
      "must": [
        {"match": {"post_title": "wordpress"}}
      ],
      "filter": [
        {"term": {"category": "tutorials"}},
        {"range": {"post_date": {"gte": "2024-01-01"}}}
      ]
    }
  }
}
```

### Monitorização

**Status do plugin:**
```bash
wp elasticpress status --allow-root
```

**Reindexar tudo:**
```bash
wp elasticpress index --allow-root
```

**Estatísticas:**
```bash
curl -X GET "elasticsearch:9200/_cat/indices/wp_*?v"
```

---

## Monitorização e Debug

### Verificar Status

**Health check:**
```bash
curl -X GET "elasticsearch:9200/_cluster/health?pretty"
```

**Output:**
```json
{
  "cluster_name": "inception-cluster",
  "status": "green",
  "number_of_nodes": 1,
  "active_primary_shards": 5,
  "active_shards": 5
}
```

### APIs Úteis

**Lista de índices:**
```bash
curl -X GET "elasticsearch:9200/_cat/indices?v"
```

**Estatísticas do cluster:**
```bash
curl -X GET "elasticsearch:9200/_cluster/stats?pretty"
```

**Informações do nó:**
```bash
curl -X GET "elasticsearch:9200/_nodes/stats?pretty"
```

### Logs

**Logs do container:**
```bash
docker logs elasticsearch
```

**Logs específicos:**
```bash
docker exec elasticsearch tail -f /var/log/elasticsearch/inception-cluster.log
```

### Troubleshooting

**Erro: "max virtual memory areas vm.max_map_count [65530] is too low"**
```
Causa: Limite de memória virtual baixo
Solução: sysctl -w vm.max_map_count=262144
```

**Erro: "insufficient memory"**
```
Causa: Heap JVM muito grande
Solução: Reduzir ES_JAVA_OPTS
```

**Erro: "node validation exception"**
```
Causa: Configuração inválida
Solução: Verificar elasticsearch.yml
```

**Cluster status yellow/red:**
```
Causa: Shards não alocados
Solução: Verificar discos e nós
```

### Performance Tuning

**Para desenvolvimento:**
```yaml
# elasticsearch.yml
bootstrap.memory_lock: false
xpack.ml.enabled: false
discovery.type: single-node
```

**Para produção:**
```yaml
# elasticsearch.yml
bootstrap.memory_lock: true
xpack.security.enabled: true
discovery.zen.minimum_master_nodes: 2
```

---

## Referências

- [Elasticsearch Documentation](https://www.elastic.co/guide/en/elasticsearch/reference/current/index.html)
- [ElasticPress Plugin](https://wordpress.org/plugins/elasticpress/)
- [Docker Elasticsearch](https://www.elastic.co/guide/en/elasticsearch/reference/current/docker.html)
- [JVM Options](https://www.elastic.co/guide/en/elasticsearch/reference/current/jvm-options.html)

---

**Última atualização:** Janeiro 2026  
**Versão:** 1.0  
**Autor:** Projeto Inception - 42 School