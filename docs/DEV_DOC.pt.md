# Documentação para Programadores - Inception

## Visão Geral

Este documento fornece orientação técnica abrangente para programadores que trabalham no projeto Inception. Cobre configuração de ambiente, processos de build, gestão de containers e mecanismos de persistência de dados.

---

## Índice

1. [Configuração de Ambiente do Zero](#configuração-de-ambiente-do-zero)
2. [Construir e Lançar o Projeto](#construir-e-lançar-o-projeto)
3. [Gestão de Containers e Volumes](#gestão-de-containers-e-volumes)
4. [Armazenamento e Persistência de Dados](#armazenamento-e-persistência-de-dados)
5. [Fluxo de Trabalho de Desenvolvimento](#fluxo-de-trabalho-de-desenvolvimento)
6. [Resolução de Problemas e Debug](#resolução-de-problemas-e-debug)

---

## Configuração de Ambiente do Zero

### Pré-requisitos

#### Software Necessário

| Software | Versão Mínima | Instalação |
|----------|---------------|------------|
| Docker Engine | 20.10+ | [Instalar Docker](https://docs.docker.com/engine/install/) |
| Docker Compose | 2.0+ | Incluído com Docker Desktop |
| GNU Make | 4.0+ | `apt install make` (Linux) / Xcode (Mac) |
| Git | 2.0+ | [Instalar Git](https://git-scm.com/downloads) |

#### Requisitos do Sistema

- **RAM:** Mínimo 4GB (8GB recomendado)
- **Espaço em Disco:** 10GB de espaço livre
- **SO:** Linux, macOS, ou Windows com WSL2

#### Verificar Instalação

```bash
docker --version          # Docker version 24.0.0+
docker compose version    # Docker Compose version v2.20.0+
make --version           # GNU Make 4.0+
```

### Configuração Inicial

#### 1. Clonar o Repositório

```bash
git clone <repository-url>
cd Inception
```

#### 2. Configurar Ficheiro Hosts

Adicionar o seu domínio ao ficheiro hosts do sistema:

**Linux/Mac:**
```bash
sudo nano /etc/hosts
# Adicionar esta linha:
127.0.0.1    nmatondo.42.fr
```

**Windows (WSL2):**
```bash
# Editar ambos os ficheiros hosts Windows e WSL
# Windows: C:\Windows\System32\drivers\etc\hosts
# WSL: /etc/hosts
```

#### 3. Criar Ficheiro de Ambiente

Criar `srcs/.env` com a seguinte configuração:

```bash
cat > srcs/.env << 'EOF'
# Configuração de Domínio
DOMAIN_NAME=nmatondo.42.fr
CERT_=./requirements/nginx/tools/nmatondo.42.fr.crt
KEY_=./requirements/nginx/tools/nmatondo.42.fr.key

# Configuração da Base de Dados
DB_NAME=wordpress
DB_USER=wpuser
DB_HOST=mariadb

# Configuração WordPress
WP_TITLE=Inception
WP_URL=https://nmatondo.42.fr
WP_ADMIN_USER=admin
WP_ADMIN_EMAIL=admin@nmatondo.42.fr
WP_USER=user
WP_USER_EMAIL=user@nmatondo.42.fr

# Configuração FTP
FTP_USER=ftpuser

# Configuração Redis
REDIS_HOST=redis:6379
EOF
```

#### 4. Criar Diretório Secrets

```bash
mkdir -p secrets
```

#### 5. Gerar Secrets

Criar todos os ficheiros de secrets necessários com passwords seguras:

```bash
# Gerar passwords aleatórias
openssl rand -base64 32 > secrets/db_root_password.txt
openssl rand -base64 32 > secrets/db_password.txt
openssl rand -base64 32 > secrets/redis_password.txt

# Criar credenciais FTP (formato username:password)
echo "ftpuser:$(openssl rand -base64 16)" > secrets/ftp_credentials.txt

# Criar credenciais admin WordPress
echo "admin:$(openssl rand -base64 16)" > secrets/credentials.txt
```

⚠️ **Segurança:** Adicionar `secrets/` ao `.gitignore`:
```bash
echo "secrets/" >> .gitignore
```

#### 6. Criar Diretórios de Dados (Opcional)

Se usar bind mounts em vez de named volumes:

```bash
mkdir -p /home/$USER/data/{mariadb,wordpress,redis,elasticsearch,myprofile}
```

Atualizar `DATA_PATH` no Makefile:
```makefile
DATA_PATH = /home/$USER/data
```

---

## Construir e Lançar o Projeto

### Arquitetura do Projeto

```
Inception/
├── Makefile                    # Automação de build
├── srcs/
│   ├── .env                   # Variáveis de ambiente
│   ├── docker-compose.yml     # Orquestração de serviços
│   └── requirements/
│       ├── mariadb/
│       │   ├── Dockerfile
│       │   ├── conf/
│       │   │   └── dump.sql
│       │   └── tools/
│       │       └── entrypoint.sh
│       ├── nginx/
│       │   ├── Dockerfile
│       │   ├── conf/
│       │   │   └── nginx.conf
│       │   └── tools/
│       │       └── generate_certificates.sh
│       ├── wordpress/
│       │   ├── Dockerfile
│       │   ├── conf/
│       │   │   └── www.conf
│       │   └── tools/
│       │       └── entrypoint.sh
│       └── bonus/
│           ├── adminer/
│           ├── redis/
│           ├── ftp/
│           ├── elasticsearch/
│           └── myprofile/
└── secrets/
    ├── db_root_password.txt
    ├── db_password.txt
    ├── credentials.txt
    ├── redis_password.txt
    └── ftp_credentials.txt
```

### Usar o Makefile

O Makefile fornece comandos convenientes para gestão do projeto:

#### Serviços Obrigatórios

```bash
# Construir imagens e iniciar containers
make

# Ou passo a passo:
make build    # Construir imagens Docker
make up       # Iniciar containers
```

#### Stack Completa com Bónus

```bash
make bonus

# Ou passo a passo:
make bonus_build    # Construir todas as imagens
make bonus_up       # Iniciar todos os containers
```

#### Targets do Makefile

| Target | Descrição | Comando Executado |
|--------|-----------|-------------------|
| `all` | Padrão: build + up | `build up` |
| `bonus` | Construir e iniciar com bónus | `bonus_build bonus_up` |
| `build` | Construir imagens obrigatórias | `docker compose build mariadb wordpress nginx` |
| `up` | Iniciar containers obrigatórios | `docker compose up -d mariadb wordpress nginx` |
| `down` | Parar containers | `docker compose down` |
| `clean` | Parar e remover volumes | `docker compose down -v` + `docker system prune -af` |
| `fclean` | Limpeza completa | Remover todos os containers, imagens, volumes, redes |
| `logs` | Seguir logs dos containers | `docker compose logs -f` |
| `restart` | Reiniciar containers | `docker compose restart` |
| `status` | Mostrar estado dos containers | `docker compose ps` |
| `re` | Reconstruir do zero | `fclean all` |

### Usar Docker Compose Diretamente

Para mais controlo, usar comandos Docker Compose diretamente:

```bash
cd srcs/

# Construir serviço específico
docker compose build mariadb

# Iniciar serviço específico
docker compose up -d nginx

# Escalar serviços (se aplicável)
docker compose up -d --scale wordpress=2

# Ver logs do serviço
docker compose logs -f wordpress

# Executar comando em container em execução
docker compose exec wordpress bash

# Reiniciar serviço específico
docker compose restart nginx
```

### Detalhes do Processo de Build

#### Ordem de Build das Imagens

1. **MariaDB** - Independente, constrói primeiro
2. **Redis** - Independente, constrói em paralelo
3. **Elasticsearch** - Independente, constrói em paralelo
4. **WordPress** - Depende do health check do MariaDB
5. **NGINX** - Depende do WordPress
6. **Adminer** - Depende do MariaDB
7. **FTP** - Independente
8. **Website Estático** - Independente

#### Argumentos de Build e Contexto

Cada Dockerfile usa argumentos de build para flexibilidade:

```dockerfile
# Exemplo do Dockerfile WordPress
ARG PHP_VERSION=8.2
ARG ALPINE_VERSION=3.18

FROM php:${PHP_VERSION}-fpm-alpine${ALPINE_VERSION}
```

#### Multi-Stage Builds

Alguns serviços usam multi-stage builds para otimização:

```dockerfile
# Estágio de build
FROM alpine:3.18 AS builder
RUN apk add --no-cache build-base
# ... compilar do código fonte

# Estágio de runtime
FROM alpine:3.18
COPY --from=builder /compiled-binary /usr/local/bin/
```

---

## Gestão de Containers e Volumes

### Configuração Docker Compose

#### Estrutura de Definição de Serviço

```yaml
services:
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
      test: ["CMD-SHELL", "mariadb -u root -p... -e 'SELECT 1'"]
      interval: 10s
      timeout: 5s
      retries: 5
```

#### Configuração de Networking

Todos os containers comunicam através de uma rede bridge personalizada:

```yaml
networks:
  network:
    driver: bridge
```

**Descoberta de Serviços:**
- Containers podem alcançar-se uns aos outros pelo nome do serviço
- Exemplo: WordPress conecta a `mariadb:3306`
- Resolução DNS tratada pelo Docker

#### Health Checks

Health checks asseguram que os serviços estão prontos antes dos serviços dependentes iniciarem:

```yaml
depends_on:
  mariadb:
    condition: service_healthy
```

### Comandos de Gestão de Containers

#### Gestão do Ciclo de Vida

```bash
# Iniciar containers
docker compose up -d

# Parar containers (preservar volumes)
docker compose down

# Parar e remover volumes
docker compose down -v

# Reiniciar container específico
docker restart mariadb

# Pausar/despausar container
docker pause wordpress
docker unpause wordpress

# Remover container
docker rm -f nginx
```

#### Inspecionar Containers

```bash
# Ver containers em execução
docker ps

# Ver todos os containers (incluindo parados)
docker ps -a

# Inspecionar detalhes do container
docker inspect mariadb

# Ver uso de recursos do container
docker stats

# Ver processos do container
docker top wordpress
```

#### Executar Comandos em Containers

```bash
# Shell interativa
docker exec -it mariadb /bin/bash
docker exec -it wordpress /bin/sh  # Alpine usa sh

# Execução de comando único
docker exec mariadb mariadb -u root -p$(cat ../secrets/db_root_password.txt) -e "SHOW DATABASES;"

# Executar como utilizador específico
docker exec -u www-data wordpress ls -la /var/www/html
```

#### Visualizar Logs

```bash
# Seguir todos os logs
docker compose logs -f

# Logs de serviço específico
docker logs mariadb

# Últimas 100 linhas
docker logs --tail 100 nginx

# Logs com timestamps
docker logs -t wordpress

# Seguir logs desde tempo específico
docker logs --since 10m wordpress
```

### Gestão de Volumes

#### Compreender Volumes

O projeto usa volumes Docker para persistência de dados:

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
```

#### Comandos de Volume

```bash
# Listar todos os volumes
docker volume ls

# Inspecionar volume
docker volume inspect inception_mariadb_data

# Criar volume manualmente
docker volume create my_volume

# Remover volumes não utilizados
docker volume prune

# Remover volume específico
docker volume rm inception_mariadb_data

# Backup de volume
docker run --rm -v inception_wordpress_data:/data -v $(pwd):/backup alpine tar czf /backup/wordpress.tar.gz /data

# Restaurar volume
docker run --rm -v inception_wordpress_data:/data -v $(pwd):/backup alpine tar xzf /backup/wordpress.tar.gz -C /
```

#### Permissões de Volumes

Corrigir problemas de permissões:

```bash
# Alterar ownership no volume
docker run --rm -v inception_wordpress_data:/data alpine chown -R 82:82 /data

# Ver permissões do volume
docker run --rm -v inception_wordpress_data:/data alpine ls -la /data
```

---

## Armazenamento e Persistência de Dados

### Localização de Dados

#### Armazenamento de Volumes Docker

**Localização de Named Volumes:**
- **Linux:** `/var/lib/docker/volumes/inception_<volume_name>/_data`
- **Mac:** `~/Library/Containers/com.docker.docker/Data/vms/0/data/docker/volumes/`
- **Windows (WSL2):** `\\wsl$\docker-desktop-data\data\docker\volumes\`

**Aceder a Dados do Volume:**
```bash
# Linux
sudo ls -la /var/lib/docker/volumes/inception_wordpress_data/_data

# Mac/Windows - usar um container
docker run --rm -v inception_wordpress_data:/data alpine ls -la /data
```

#### Bind Mounts

Se usar bind mounts (configurado no Makefile):

```makefile
DATA_PATH = /home/nmatondo/data
```

Os dados são armazenados diretamente no host:
- MariaDB: `$DATA_PATH/mariadb`
- WordPress: `$DATA_PATH/wordpress`
- Redis: `$DATA_PATH/redis`

### Estratégia de Persistência de Dados

#### O Que Persiste

| Serviço | Tipo de Dados | Volume | Caminho no Container |
|---------|---------------|--------|---------------------|
| **MariaDB** | Ficheiros de base de dados | `mariadb_data` | `/var/lib/mysql` |
| **WordPress** | Ficheiros, temas, plugins, uploads | `wordpress_data` | `/var/www/html` |
| **Redis** | Dados de cache | `redis_data` | `/data` |
| **Elasticsearch** | Dados de índice | `elasticsearch_data` | `/usr/share/elasticsearch/data` |

#### Como Funciona a Persistência

1. **Criação do Container:** Volume é montado no caminho do container
2. **Dados Escritos:** Aplicação escreve no caminho do container
3. **Armazenamento em Volume:** Dados são armazenados no volume Docker
4. **Remoção do Container:** Dados permanecem no volume
5. **Recriação do Container:** Dados são remontados a partir do volume

#### Ciclo de Vida dos Dados

```bash
# Criar container com volume
docker compose up -d wordpress
# WordPress escreve dados → /var/www/html → volume wordpress_data

# Parar e remover container
docker compose down
# Container apagado, mas volume wordpress_data persiste

# Recriar container
docker compose up -d wordpress
# Container recriado, wordpress_data remontado → dados intactos!
```

### Backup e Restore

#### Backup de Base de Dados

```bash
# Exportar base de dados
docker exec mariadb mariadb-dump \
  -u root \
  -p$(cat secrets/db_root_password.txt) \
  --all-databases > backup_$(date +%Y%m%d).sql

# Backup de base de dados específica
docker exec mariadb mariadb-dump \
  -u root \
  -p$(cat secrets/db_root_password.txt) \
  wordpress > wordpress_backup.sql
```

#### Restore de Base de Dados

```bash
# Importar base de dados
docker exec -i mariadb mariadb \
  -u root \
  -p$(cat secrets/db_root_password.txt) \
  < backup.sql

# Restaurar base de dados específica
docker exec -i mariadb mariadb \
  -u root \
  -p$(cat secrets/db_root_password.txt) \
  wordpress < wordpress_backup.sql
```

#### Backup de Volume

```bash
# Backup do volume WordPress
docker run --rm \
  -v inception_wordpress_data:/data \
  -v $(pwd):/backup \
  alpine tar czf /backup/wordpress_$(date +%Y%m%d).tar.gz /data

# Backup do volume MariaDB (parar container primeiro!)
docker stop mariadb
docker run --rm \
  -v inception_mariadb_data:/data \
  -v $(pwd):/backup \
  alpine tar czf /backup/mariadb_$(date +%Y%m%d).tar.gz /data
docker start mariadb
```

#### Restore de Volume

```bash
# Restaurar volume WordPress
docker run --rm \
  -v inception_wordpress_data:/data \
  -v $(pwd):/backup \
  alpine sh -c "cd / && tar xzf /backup/wordpress_backup.tar.gz"

# Restaurar volume MariaDB (container deve estar parado!)
docker stop mariadb
docker run --rm \
  -v inception_mariadb_data:/data \
  -v $(pwd):/backup \
  alpine sh -c "cd / && tar xzf /backup/mariadb_backup.tar.gz"
docker start mariadb
```

---

## Fluxo de Trabalho de Desenvolvimento

### Modificações de Dockerfile

Ao modificar um Dockerfile:

```bash
# Reconstruir serviço específico
docker compose build mariadb

# Reconstruir sem cache
docker compose build --no-cache mariadb

# Recriar container com nova imagem
docker compose up -d --force-recreate mariadb
```

### Alterações de Configuração

#### Variáveis de Ambiente (.env)

Alterações ao `.env` requerem reinício do container:

```bash
# Editar .env
nano srcs/.env

# Reiniciar containers
docker compose down
docker compose up -d
```

#### Ficheiros de Configuração de Serviços

Alterações a ficheiros de configuração (nginx.conf, www.conf, etc.):

```bash
# Editar config
nano srcs/requirements/nginx/conf/nginx.conf

# Reconstruir e reiniciar
docker compose build nginx
docker compose up -d --force-recreate nginx
```

### Desenvolvimento ao Vivo

#### Desenvolvimento WordPress

```bash
# Aceder ao container WordPress
docker exec -it wordpress /bin/sh

# Editar ficheiros PHP diretamente
docker exec wordpress vi /var/www/html/wp-config.php

# Observar logs WordPress
docker logs -f wordpress
```

#### Teste de Configuração NGINX

```bash
# Testar config NGINX sem reiniciar
docker exec nginx nginx -t

# Recarregar NGINX (sem downtime)
docker exec nginx nginx -s reload
```

### Técnicas de Debugging

#### Verificar Conectividade de Serviços

```bash
# De WordPress para MariaDB
docker exec wordpress ping -c 3 mariadb

# Verificar se porta está a ouvir
docker exec wordpress nc -zv mariadb 3306

# Testar endpoint HTTP
docker exec nginx curl -I http://wordpress:9000
```

#### Inspecionar Rede

```bash
# Listar redes
docker network ls

# Inspecionar rede
docker network inspect inception_network

# Ver containers conectados
docker network inspect inception_network | grep -A 3 Containers
```

#### Monitorizar Recursos

```bash
# Uso de recursos em tempo real
docker stats

# Container específico
docker stats mariadb

# Snapshot único
docker stats --no-stream
```

---

## Resolução de Problemas e Debug

### Problemas Comuns

#### Porta Já em Uso

```bash
# Encontrar processo a usar porta 443
sudo lsof -i :443
# ou
sudo netstat -tulpn | grep :443

# Terminar o processo
sudo kill -9 <PID>
```

#### Erros de Permissão Negada

```bash
# Corrigir permissões de volume
docker run --rm \
  -v inception_wordpress_data:/data \
  alpine chown -R 82:82 /data

# Verificar SELinux (se aplicável)
sudo setenforce 0
```

#### Container Sai Imediatamente

```bash
# Ver código de saída e erro
docker ps -a
docker logs <nome_container>

# Inspecionar estado do container
docker inspect <nome_container> | grep -A 10 State
```

#### Conexão à Base de Dados Recusada

```bash
# Verificar se MariaDB está em execução
docker ps | grep mariadb

# Verificar logs do MariaDB
docker logs mariadb

# Verificar password
cat secrets/db_password.txt

# Testar conexão manualmente
docker exec mariadb mariadb -u wpuser -p$(cat secrets/db_password.txt) -e "SELECT 1"
```

### Debug Avançado

#### Ativar Modo Debug

**WordPress:**
```php
// Adicionar a wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

**NGINX:**
```nginx
# Adicionar a nginx.conf
error_log /var/log/nginx/error.log debug;
```

#### Usar strace

```bash
# Rastrear chamadas do sistema
docker exec mariadb apk add strace
docker exec mariadb strace -p 1
```

#### Analisar Diff do Container

```bash
# Ver o que mudou no sistema de ficheiros do container
docker diff wordpress
```

### Otimização de Performance

#### Redução de Tamanho de Imagem

```dockerfile
# Usar multi-stage builds
# Usar imagens base alpine
# Minimizar camadas
# Remover dependências de build
```

#### Otimização de Cache de Build

```dockerfile
# Ordenar Dockerfile para eficiência de cache
# Copiar ficheiros de dependências primeiro
# Copiar código fonte por último
```

#### Limites de Recursos

```yaml
services:
  mariadb:
    deploy:
      resources:
        limits:
          cpus: '1.0'
          memory: 1G
        reservations:
          memory: 512M
```

---

## Convenções do Projeto

### Nomenclatura de Ficheiros
- Dockerfiles: `Dockerfile` (capitalizado)
- Scripts: `entrypoint.sh`, `init.sh`
- Configs: Específicos do serviço (ex: `nginx.conf`, `www.conf`)

### Gestão de Secrets
- Armazenar no diretório `secrets/`
- Nunca fazer commit para controlo de versão
- Usar extensão `.txt`
- Um valor por ficheiro

### Variáveis de Ambiente
- Apenas config não sensível
- Armazenar em `srcs/.env`
- Usar `MAIUSCULAS_COM_UNDERSCORES`
- Documentar todas as variáveis

---

## Referência Rápida

### Comandos Essenciais

```bash
# Construir e iniciar
make

# Ver logs
make logs

# Verificar estado
make status

# Reiniciar
make restart

# Reconstrução limpa
make re

# Aceder a container
docker exec -it <container> /bin/sh

# Ver dados do volume
docker run --rm -v <volume>:/data alpine ls -la /data

# Backup de base de dados
docker exec mariadb mariadb-dump -u root -p$(cat secrets/db_root_password.txt) wordpress > backup.sql
```

---

## Recursos Adicionais

- [Documentação Docker](https://docs.docker.com/)
- [Referência Docker Compose](https://docs.docker.com/compose/compose-file/)
- [Boas Práticas Dockerfile](https://docs.docker.com/develop/develop-images/dockerfile_best-practices/)
- [Recursos para Programadores WordPress](https://developer.wordpress.org/)
- [Documentação NGINX](https://nginx.org/en/docs/)

Para documentação voltada para o utilizador, ver [USER_DOC.md](USER_DOC.md).
