# Configuração Docker Compose - Documentação Técnica

Este documento fornece uma explicação detalhada de todas as configurações Docker Compose utilizadas no projeto Inception, incluindo serviços, networking, volumes, secrets e dependências.

**Sistema Base:** Todos os serviços utilizam Alpine Linux 3.22 como imagem base

---

## Índice

1. [Introdução ao Docker Compose](#introdução-ao-docker-compose)
2. [Serviços](#serviços)
3. [Secrets](#secrets)
4. [Networks](#networks)
5. [Volumes](#volumes)
6. [Integração Geral](#integração-geral)

---

## Introdução ao Docker Compose

### O que é Docker Compose?

**Docker Compose** é uma ferramenta para definir e executar aplicações Docker multi-container. Permite descrever toda a arquitetura da aplicação em um único arquivo YAML.

### Arquitetura do Inception

```
┌─────────────────────────────────────────────────────────┐
│                    Inception Stack                      │
├─────────────────────────────────────────────────────────┤
│  ┌─────────────┐    ┌─────────────┐    ┌─────────────┐  │
│  │   NGINX     │    │ WordPress   │    │  MariaDB    │  │
│  │   (443)     │◄──▶│   (9000)    │◄──▶│   (3306)    │  │
│  │             │    │             │    │             │  │
│  └─────────────┘    └─────────────┘    └─────────────┘  │
│                                                         │
│  ┌─────────────┐    ┌─────────────┐    ┌─────────────┐  │
│  │   Redis     │    │   Adminer   │    │     FTP     │  │
│  │   (6379)    │    │   (8080)    │    │   (21)      │  │
│  └─────────────┘    └─────────────┘    └─────────────┘  │
│                                                         │
│  ┌─────────────┐    ┌─────────────┐    ┌─────────────┐  │
│  │Elasticsearch│    │  MyProfile  │    │             │  │
│  │  (9200)     │    │   (8888)    │    │             │  │
│  └─────────────┘    └─────────────┘    └─────────────┘  │
└─────────────────────────────────────────────────────────┘
```

### Componentes Principais

| Componente | Descrição | Arquivo |
|------------|-----------|---------|
| **Services** | Containers da aplicação | `services:` |
| **Networks** | Redes de comunicação | `networks:` |
| **Volumes** | Persistência de dados | `volumes:` |
| **Secrets** | Credenciais seguras | `secrets:` |

### Fluxo de Dependências

```
NGINX (443) → WordPress (9000) → MariaDB (3306)
    ↓              ↓              ↓
   HTTPS        PHP-FPM       Database
    ↓              ↓              ↓
WordPress     WordPress      MySQL Data
   Site          Code          Files
```

---

## Serviços

### MariaDB

```yaml
mariadb:
  build:
    context: ./requirements/mariadb
    dockerfile: Dockerfile
  container_name: mariadb
  networks:
    - network
  volumes:
    - mariadb_data:/var/lib/mysql
  restart: always
  env_file:
    - ./.env
  secrets:
    - db_root_password
    - db_password
  healthcheck:
    test: ["CMD-SHELL", "mariadb -u root -p$$(cat /run/secrets/db_root_password) -e 'SELECT 1' >/dev/null 2>&1"]
    start_period: 30s
    interval: 10s
    timeout: 5s
    retries: 5
```

#### Explicação Detalhada

**Build Configuration:**
```yaml
build:
  context: ./requirements/mariadb
  dockerfile: Dockerfile
```
- **context:** Diretório onde está o Dockerfile
- **dockerfile:** Nome do arquivo de build

**Container Identity:**
```yaml
container_name: mariadb
```
- Nome fixo do container (não aleatório)
- Facilita referências em outros serviços

**Networking:**
```yaml
networks:
  - network
```
- Conecta à rede `network`
- Comunicação com outros serviços

**Data Persistence:**
```yaml
volumes:
  - mariadb_data:/var/lib/mysql
```
- **mariadb_data:** Volume nomeado
- **/var/lib/mysql:** Diretório MySQL no container
- Dados persistem entre restarts

**Restart Policy:**
```yaml
restart: always
```
- Container reinicia automaticamente se parar
- Útil para produção

**Environment Variables:**
```yaml
env_file:
  - ./.env
```
- Carrega variáveis do arquivo `.env`
- Configurações como `MYSQL_DATABASE`, `MYSQL_USER`

**Secrets Management:**
```yaml
secrets:
  - db_root_password
  - db_password
```
- Credenciais seguras via Docker secrets
- Não expostas em variáveis de ambiente

**Health Check:**
```yaml
healthcheck:
  test: ["CMD-SHELL", "mariadb -u root -p$$(cat /run/secrets/db_root_password) -e 'SELECT 1' >/dev/null 2>&1"]
  start_period: 30s
  interval: 10s
  timeout: 5s
  retries: 5
```

**Decompondo o healthcheck:**

- **`test`:** Comando executado para verificar saúde
- **`start_period`:** Tempo de inicialização antes de verificar
- **`interval`:** Frequência das verificações
- **`timeout`:** Tempo máximo para resposta
- **`retries`:** Tentativas antes de marcar como unhealthy

**Comando de verificação:**
```bash
mariadb -u root -p$(cat /run/secrets/db_root_password) -e 'SELECT 1'
```
- Conecta ao MySQL como root
- Executa query simples `SELECT 1`
- Se retornar resultado → healthy ✅

---

### WordPress

```yaml
wordpress:
  build:
    context: ./requirements/wordpress
    dockerfile: Dockerfile
  container_name: wordpress
  depends_on:
    mariadb:
      condition: service_healthy
  ports:
    - "9000:9000"
  networks:
    - network
  restart: always
  env_file:
    - ./.env
  secrets:
    - db_password
    - credentials
    - redis_password
  volumes:
    - wordpress_data:/var/www/html
```

#### Dependências

```yaml
depends_on:
  mariadb:
    condition: service_healthy
```

**Significado:**
- WordPress só inicia **após** MariaDB estar healthy
- Garante que banco de dados está pronto
- Previne erros de conexão na inicialização

**Sem dependência:**
```
WordPress inicia → Tenta conectar MariaDB → Falha (ainda inicializando) ❌
```

**Com dependência:**
```
MariaDB healthy → WordPress inicia → Conecta com sucesso ✅
```

#### Port Mapping

```yaml
ports:
  - "9000:9000"
```

**Explicação:**
- **Host:9000** → **Container:9000**
- PHP-FPM escuta na porta 9000
- NGINX conecta via FastCGI

**Fluxo:**
```
Cliente → NGINX:443 → WordPress:9000 (FastCGI)
```

#### Secrets

```yaml
secrets:
  - db_password
  - credentials
  - redis_password
```

**Credenciais necessárias:**
- **db_password:** Para conectar ao MariaDB
- **credentials:** Para autenticação WordPress
- **redis_password:** Para conectar ao Redis

---

### NGINX

```yaml
nginx:
  build:
    context: ./requirements/nginx
    dockerfile: Dockerfile
  container_name: nginx
  depends_on:
    - wordpress
  ports:
    - "443:443"
  networks:
    - network
  restart: always
  volumes:
    - wordpress_data:/var/www/html
  env_file:
    - ./.env
```

#### Dependências

```yaml
depends_on:
  - wordpress
```

**Diferença de `service_healthy`:**
- `depends_on: [wordpress]` → Só espera WordPress iniciar
- `depends_on: wordpress: {condition: service_healthy}` → Espera WordPress healthy

**No nosso caso:** NGINX pode iniciar enquanto WordPress está inicializando

#### SSL/TLS

```yaml
ports:
  - "443:443"
```

**Porta 443:**
- Porta padrão HTTPS
- Certificado SSL configurado no container
- Comunicação criptografada

#### Volume Compartilhado

```yaml
volumes:
  - wordpress_data:/var/www/html
```

**Compartilhamento:**
- Mesmo volume do WordPress
- NGINX serve arquivos estáticos diretamente
- PHP requests vão para WordPress via FastCGI

---

### Adminer

```yaml
adminer:
  build:
    context: ./requirements/bonus/adminer
    dockerfile: Dockerfile
  container_name: adminer
  depends_on:
    mariadb:
      condition: service_healthy
  ports:
    - "8080:8080"
  networks:
    - network
  restart: always
  env_file:
    - ./.env
  secrets:
    - db_root_password
    - db_password
```

#### Interface Web para Banco

**Adminer:**
- Cliente web para bancos de dados
- Suporte MySQL/MariaDB, PostgreSQL, etc.
- Alternativa ao phpMyAdmin (mais leve)

**Acesso:**
```
http://localhost:8080
```

**Credenciais:**
- **Sistema:** MySQL
- **Servidor:** mariadb (nome do serviço)
- **Usuário:** root ou wordpress_user
- **Password:** Via secrets

---

### Redis

```yaml
redis:
  build:
    context: ./requirements/bonus/redis
    dockerfile: Dockerfile
  container_name: redis
  networks:
    - network
  restart: always
  env_file:
    - ./.env
  secrets:
    - redis_password
  volumes:
    - redis_data:/data
  depends_on:
    - mariadb
```

#### Cache de Objetos

**Função:**
- Cache de objetos WordPress
- Reduz queries ao MariaDB
- Melhora performance

**Conexão WordPress:**
```php
define('WP_REDIS_HOST', 'redis');
define('WP_REDIS_PORT', 6379);
define('WP_REDIS_PASSWORD', '[redis_password]');
```

#### Dependência MariaDB

```yaml
depends_on:
  - mariadb
```

**Por que depende de MariaDB?**
- Redis é cache dos dados do MariaDB
- Ordem lógica: DB primeiro, depois cache
- Não precisa de `service_healthy` (Redis inicia rápido)

---

### FTP

```yaml
ftp:
  build:
    context: ./requirements/bonus/ftp
    dockerfile: Dockerfile
  container_name: ftp
  ports:
    - "21:21"
    - "21000-21010:21000-21010"
  networks:
    - network
  restart: always
  env_file:
    - ./.env
  volumes:
    - wordpress_data:/var/www/html
  depends_on:
    - wordpress
  secrets:
    - ftp_credentials
```

#### Servidor FTP Seguro

**vsftpd:**
- Very Secure FTP Daemon
- Servidor FTP leve e seguro
- Suporte chroot (jail users)

#### Portas

```yaml
ports:
  - "21:21"              # Controle FTP
  - "21000-21010:21000-21010"  # Dados (passive mode)
```

**Porta 21:** Comando FTP (login, comandos)
**Portas 21000-21010:** Transferência dados (passive mode)

#### Acesso aos Arquivos WordPress

```yaml
volumes:
  - wordpress_data:/var/www/html
```

**Permite:**
- Upload temas/plugins
- Edição arquivos WordPress
- Backup/restore

---

### Elasticsearch

```yaml
elasticsearch:
  build:
    context: ./requirements/bonus/elasticsearch
    dockerfile: Dockerfile
  container_name: elasticsearch
  ports:
    - "9200:9200"
    - "9300:9300"
  networks:
    - network
  restart: always
  env_file:
    - ./.env
  volumes:
    - elasticsearch_data:/var/lib/elasticsearch
  depends_on:
    - mariadb
  healthcheck:
    test: ["CMD-SHELL", "wget -q -O /dev/null http://nmatondo.42.fr:9200/_cluster/health || exit 1"]
    start_period: 60s
    interval: 10s
    timeout: 5s
    retries: 5
```

#### Busca Full-Text

**ElasticPress Plugin:**
- Busca avançada no WordPress
- Indexação automática de conteúdo
- Performance superior ao MySQL LIKE

#### Portas

```yaml
ports:
  - "9200:9200"  # API HTTP
  - "9300:9300"  # Comunicação inter-node
```

**Porta 9200:** REST API (WordPress conecta aqui)
**Porta 9300:** TCP binary (comunicação cluster)

#### Health Check

```yaml
healthcheck:
  test: ["CMD-SHELL", "wget -q -O /dev/null http://nmatondo.42.fr:9200/_cluster/health || exit 1"]
  start_period: 60s
  interval: 10s
  timeout: 5s
  retries: 5
```

**Verificação:**
```bash
wget -q -O /dev/null http://nmatondo.42.fr:9200/_cluster/health
```

**Endpoint:** `/cluster/health` retorna status do cluster

---

### MyProfile

```yaml
myprofile:
  build:
    context: ./requirements/bonus/myprofile
    dockerfile: Dockerfile
  container_name: myprofile
  ports:
    - "8888:8888"
  networks:
    - network
  restart: always
  volumes:
    - myprofile_data:/var/www/myprofile
  env_file:
    - ./.env
```

#### Website Pessoal

**Serviço estático:**
- Servidor web simples
- Página pessoal/portfolio
- Arquivos HTML/CSS/JS

**Acesso:**
```
http://localhost:8888
```

**Integração NGINX:**
- Pode ser proxy via `/myprofile/` (comentado no nginx.conf)
- Volume separado do WordPress

---

## Secrets

```yaml
secrets:
  db_root_password:
    file: ../secrets/db_root_password.txt
  db_password:
    file: ../secrets/db_password.txt
  credentials:
    file: ../secrets/credentials.txt
  redis_password:
    file: ../secrets/redis_password.txt
  ftp_credentials:
    file: ../secrets/ftp_credentials.txt
```

### Docker Secrets

**Vantagem sobre env vars:**
- ✅ Criptografados em trânsito
- ✅ Armazenados em tmpfs (RAM)
- ✅ Não visíveis em `docker inspect`
- ✅ Não logados em histórico

### Arquivos de Secret

**Estrutura:**
```
secrets/
├── db_root_password.txt     # Root MariaDB
├── db_password.txt          # User WordPress
├── credentials.txt          # Admin WordPress
├── redis_password.txt       # Redis auth
└── ftp_credentials.txt      # FTP user
```

**Conteúdo exemplo:**
```bash
# db_root_password.txt
super_secret_root_password_123

# credentials.txt
admin_username
admin_password_hash
```

### Acesso nos Containers

**Montagem automática:**
```
/run/secrets/
├── db_root_password
├── db_password
├── credentials
├── redis_password
└── ftp_credentials
```

**Leitura em scripts:**
```bash
MYSQL_ROOT_PASSWORD=$(cat /run/secrets/db_root_password)
```

---

## Networks

```yaml
networks:
  network:
```

### Rede Isolada

**Docker Network:**
- Rede bridge isolada
- Apenas containers conectados comunicam
- DNS automático entre serviços

**Benefícios:**
- ✅ Isolamento de segurança
- ✅ Nomes de host resolvem automaticamente
- ✅ Sem exposição externa (exceto portas mapeadas)

### Comunicação

**DNS Resolution:**
```
wordpress → mariadb:3306
nginx → wordpress:9000
wordpress → redis:6379
```

**Sem rede externa:**
- Containers não acessam internet diretamente
- Comunicação apenas entre serviços da stack

---

## Volumes

```yaml
volumes:
  mariadb_data:
    driver: local
    driver_opts:
      type: none
      o: bind
      device: /home/nmatondo/data/mariadb
  wordpress_data:
    driver: local
    driver_opts:
      type: none
      o: bind
      device: /home/nmatondo/data/wordpress
  redis_data:
    driver: local
    driver_opts:
      type: none
      o: bind
      device: /home/nmatondo/data/redis
  elasticsearch_data:
    driver: local
    driver_opts:
      type: none
      o: bind
      device: /home/nmatondo/data/elasticsearch
  myprofile_data:
    driver: local
    driver_opts:
      type: none
      o: bind
      device: /home/nmatondo/data/myprofile
```

### Bind Mounts

**Configuração:**
```yaml
driver: local
driver_opts:
  type: none
  o: bind
  device: /host/path
```

**Significado:**
- **type: none** → Bind mount (não named volume)
- **o: bind** → Operação bind
- **device:** Caminho no host

### Diretórios Host

**Estrutura esperada:**
```
/home/nmatondo/data/
├── mariadb/        # Dados MySQL
├── wordpress/      # Arquivos WordPress
├── redis/          # Cache Redis
├── elasticsearch/  # Índices ES
└── myprofile/      # Site pessoal
```

### Persistência

**Vantagens bind mounts:**
- ✅ Dados visíveis no host
- ✅ Backup fácil
- ✅ Acesso direto aos arquivos
- ✅ Performance melhor

**Desvantagens:**
- ⚠️ Dependente do host
- ⚠️ Permissões podem ser complexas
- ⚠️ Caminho fixo no host

### Comparação com Named Volumes

| Aspecto | Bind Mount | Named Volume |
|---------|------------|--------------|
| **Host Path** | Especificado | Gerenciado pelo Docker |
| **Backup** | Fácil | Mais complexo |
| **Performance** | Melhor | Boa |
| **Portabilidade** | Baixa | Alta |
| **Permissões** | Manual | Automático |

---

## Integração Geral

### Ordem de Inicialização

```
1. MariaDB (sem dependências)
   ↓
2. Redis & Elasticsearch (dependem MariaDB)
   ↓
3. WordPress (depende MariaDB healthy)
   ↓
4. NGINX (depende WordPress)
   ↓
5. Adminer & FTP & MyProfile (dependem MariaDB/WordPress)
```

### Fluxo de Dados

#### WordPress Normal
```
Cliente → NGINX:443 → WordPress:9000 → MariaDB:3306
```

#### Com Cache
```
Cliente → NGINX:443 → WordPress:9000 → Redis:6379 (cache hit)
                                      → MariaDB:3306 (cache miss)
```

#### Com Busca
```
Cliente → WordPress → Elasticsearch:9200 (busca full-text)
```

### Monitorização

**Status dos serviços:**
```bash
docker compose ps
```

**Logs:**
```bash
docker compose logs -f [service]
```

**Health checks:**
```bash
docker ps --filter "health=healthy"
```

### Troubleshooting

**Serviço não inicia:**
```bash
# Verificar dependências
docker compose logs [service]

# Verificar health
docker inspect [container] | grep -A 10 "Health"
```

**Rede não funciona:**
```bash
# Testar conectividade
docker exec wordpress ping mariadb

# Verificar DNS
docker exec wordpress nslookup redis
```

---

## Referências

- [Docker Compose Documentation](https://docs.docker.com/compose/)
- [Docker Compose File Reference](https://docs.docker.com/compose/compose-file/)
- [Docker Secrets](https://docs.docker.com/engine/swarm/secrets/)
- [Docker Networks](https://docs.docker.com/network/)
- [Docker Volumes](https://docs.docker.com/storage/volumes/)

---

**Última atualização:** Janeiro 2026  
**Versão:** 1.0  
**Autor:** Projeto Inception - 42 School