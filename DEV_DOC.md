# Developer Documentation - Inception

## Overview

This document provides comprehensive technical guidance for developers working on the Inception project. It covers environment setup, build processes, container management, and data persistence mechanisms.

---

## Table of Contents

1. [Technologies and Versions](#technologies-and-versions)
2. [Environment Setup from Scratch](#environment-setup-from-scratch)
3. [Building and Launching the Project](#building-and-launching-the-project)
4. [Container and Volume Management](#container-and-volume-management)
5. [Data Storage and Persistence](#data-storage-and-persistence)
6. [Development Workflow](#development-workflow)
7. [Troubleshooting and Debugging](#troubleshooting-and-debugging)

---

## Technologies and Versions

### Base Images and Core Technologies

All services are built from **Alpine Linux 3.23** for minimal image size and security.

#### Mandatory Services Stack

| Service | Technology | Version | Key Components |
|---------|-----------|---------|----------------|
| **NGINX** | NGINX + OpenSSL | Latest (Alpine) | Reverse proxy, TLS 1.2/1.3, self-signed certificates |
| **WordPress** | PHP-FPM | 8.3 | WP-CLI, 15+ PHP extensions, www-data user |
| **MariaDB** | MariaDB Server | Latest (Alpine) | InnoDB engine, health checks |

#### Bonus Services Stack

| Service | Technology | Version | Key Components |
|---------|-----------|---------|----------------|
| **Redis** | Redis Server | Latest (Alpine) | In-memory cache, password authentication |
| **Adminer** | PHP + Adminer | 8.3 + Latest | Single-file database manager |
| **FTP** | vsftpd | Latest (Alpine) | Passive mode (21000-21010), SSL support |
| **Elasticsearch** | Elasticsearch | Latest (Alpine) | Cluster health monitoring |
| **MyProfile** | httpd (Apache) | Latest (Alpine) | Static HTML/CSS/JS website |

### WordPress PHP Extensions

The WordPress container includes all necessary PHP 8.3 extensions:

```dockerfile
php83 php83-fpm php83-mysqli php83-pdo php83-pdo_mysql
php83-gd php83-intl php83-mbstring php83-xml php83-zip
php83-opcache php83-curl php83-tokenizer php83-session php83-phar
```

### Development Tools

- **WP-CLI**: WordPress command-line interface for automation
- **MariaDB Client**: Database connectivity tools
- **Curl**: HTTP client for testing and downloads
- **OpenSSL**: Certificate generation and encryption

### Network Architecture

- **Network Driver**: Bridge (default Docker network)
- **Service Discovery**: DNS-based via Docker network
- **Published Ports**: NGINX (443), Adminer (8080), FTP (21, 21000-21010), Elasticsearch (9200, 9300), MyProfile (8888)
- **Internal Ports**: MariaDB (3306), Redis (6379), WordPress (9000)

### Security Features

- **Docker Secrets**: All passwords managed via `/run/secrets/`
- **TLS Encryption**: NGINX with self-signed certificates (TLS 1.2/1.3)
- **User Isolation**: Services run as non-root users where possible
- **Network Isolation**: Private bridge network for inter-service communication

---

## Environment Setup from Scratch

### Prerequisites

#### Required Software

| Software | Minimum Version | Installation |
|----------|----------------|--------------|
| Docker Engine | 20.10+ | [Install Docker](https://docs.docker.com/engine/install/) |
| Docker Compose | 2.0+ | Included with Docker Desktop |
| GNU Make | 4.0+ | `apt install make` (Linux) / Xcode (Mac) |
| Git | 2.0+ | [Install Git](https://git-scm.com/downloads) |

#### System Requirements

- **RAM:** Minimum 4GB (8GB recommended)
- **Disk Space:** 10GB free space
- **OS:** Linux, macOS, or Windows with WSL2

#### Verify Installation

```bash
docker --version          # Docker version 24.0.0+
docker compose version    # Docker Compose version v2.20.0+
make --version           # GNU Make 4.0+
```

### Initial Setup

#### 1. Clone the Repository

```bash
git clone <repository-url>
cd Inception
```

#### 2. Configure Hosts File

Add your domain to the system hosts file:

**Linux/Mac:**
```bash
sudo nano /etc/hosts
# Add this line:
127.0.0.1    nmatondo.42.fr
```

**Windows (WSL2):**
```bash
# Edit both Windows and WSL hosts files
# Windows: C:\Windows\System32\drivers\etc\hosts
# WSL: /etc/hosts
```

#### 3. Create Environment File

Create `srcs/.env` with the following configuration:

```bash
cat > srcs/.env << 'EOF'
# Domain Configuration
DOMAIN_NAME=nmatondo.42.fr
CERT_=./requirements/nginx/tools/nmatondo.42.fr.crt
KEY_=./requirements/nginx/tools/nmatondo.42.fr.key

# Database Configuration
DB_NAME=wordpress
DB_USER=wpuser
DB_HOST=mariadb

# WordPress Configuration
WP_TITLE=Inception
WP_URL=https://nmatondo.42.fr
WP_ADMIN_USER=admin
WP_ADMIN_EMAIL=admin@nmatondo.42.fr
WP_USER=user
WP_USER_EMAIL=user@nmatondo.42.fr

# FTP Configuration
FTP_USER=ftpuser

# Redis Configuration
REDIS_HOST=redis:6379
EOF
```

#### 4. Create Secrets Directory

```bash
mkdir -p secrets
```

#### 5. Generate Secrets

Create all required secret files with secure passwords:

```bash
# Generate random passwords
openssl rand -base64 32 > secrets/db_root_password.txt
openssl rand -base64 32 > secrets/db_password.txt
openssl rand -base64 32 > secrets/redis_password.txt

# Create FTP credentials (username:password format)
echo "ftpuser:$(openssl rand -base64 16)" > secrets/ftp_credentials.txt

# Create WordPress admin credentials (username:password format)
echo "admin:$(openssl rand -base64 16)" > secrets/credentials.txt
```

⚠️ **Security:** Add `secrets/` to `.gitignore`:
```bash
echo "secrets/" >> .gitignore
```

#### 6. Create Data Directories (Optional)

If using bind mounts instead of named volumes:

```bash
mkdir -p /home/$USER/data/{mariadb,wordpress,redis,elasticsearch,myprofile}
```

Update `DATA_PATH` in Makefile:
```makefile
DATA_PATH = /home/$USER/data
```

---

## Building and Launching the Project

### Project Architecture

```
Inception/
├── Makefile                    # Build automation
├── srcs/
│   ├── .env                   # Environment variables
│   ├── docker-compose.yml     # Service orchestration
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

### Using the Makefile

The Makefile provides convenient commands for project management:

#### Mandatory Services

```bash
# Build images and start containers
make

# Or step by step:
make build    # Build Docker images
make up       # Start containers
```

#### Full Stack with Bonus

```bash
make bonus

# Or step by step:
make bonus_build    # Build all images
make bonus_up       # Start all containers
```

#### Makefile Targets

| Target | Description | Command Executed |
|--------|-------------|------------------|
| `all` | Default: build + up | `build up` |
| `bonus` | Build and start with bonus | `bonus_build bonus_up` |
| `build` | Build mandatory images | `docker compose build mariadb wordpress nginx` |
| `up` | Start mandatory containers | `docker compose up -d mariadb wordpress nginx` |
| `down` | Stop containers | `docker compose down` |
| `clean` | Stop and remove volumes | `docker compose down -v` + `docker system prune -af` |
| `fclean` | Complete cleanup | Remove all containers, images, volumes, networks |
| `logs` | Follow container logs | `docker compose logs -f` |
| `restart` | Restart containers | `docker compose restart` |
| `status` | Show container status | `docker compose ps` |
| `re` | Rebuild from scratch | `fclean all` |
| `bre` | Bonus rebuild from scratch | `fclean bonus` |

### Using Docker Compose Directly

For more control, use Docker Compose commands directly:

```bash
cd srcs/

# Build specific service
docker compose build mariadb

# Start specific service
docker compose up -d nginx

# Scale services (if applicable)
docker compose up -d --scale wordpress=2

# View service logs
docker compose logs -f wordpress

# Execute command in running container
docker compose exec wordpress bash

# Restart specific service
docker compose restart nginx
```

### Build Process Details

#### Image Build Order

1. **MariaDB** - Independent, builds first (with health check)
2. **Redis** - Depends on MariaDB (starts after)
3. **Elasticsearch** - Depends on MariaDB (with health check)
4. **WordPress** - Depends on MariaDB health check
5. **NGINX** - Depends on WordPress
6. **Adminer** - Depends on MariaDB health check
7. **FTP** - Depends on WordPress
8. **MyProfile** - Independent static website

#### Build Arguments and Context

Each Dockerfile uses Alpine Linux 3.23 as the base image:

```dockerfile
# All services use Alpine 3.23
:3.23

# WordPress uses PHP 8.3
RUN apk add --no-cache php83 php83-fpm ...
```

#### Container Images

All services are built from Alpine Linux 3.23 without using pre-built Docker Hub images:

```dockerfile
# All Dockerfiles start with:
FROM alpine:3.22

# Then install required packages:
RUN apk add --no-cache nginx openssl  # NGINX
RUN apk add --no-cache mariadb mariadb-client  # MariaDB
RUN apk add --no-cache php83 php83-fpm ...  # WordPress
```

---

## Container and Volume Management

### Docker Compose Configuration

#### Service Definition Structure

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
      test: ["CMD-SHELL", "mariadb -u root -p$$(cat /run/secrets/db_root_password) -e 'SELECT 1' >/dev/null 2>&1"]
      start_period: 30s
      interval: 10s
      timeout: 5s
      retries: 5
```

#### Networking Configuration

All containers communicate through a custom bridge network:

```yaml
networks:
  network:
    driver: bridge  # Using default bridge driver
```

**Secrets Management:**

Docker secrets are used for sensitive data:

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

Secrets are mounted at `/run/secrets/<secret_name>` inside containers.

**Service Discovery:**
- Containers can reach each other by service name
- Example: WordPress connects to `mariadb:3306`
- DNS resolution handled by Docker

**Published Ports:**
- **NGINX**: 443 (HTTPS)
- **Adminer**: 8080 (HTTP)
- **FTP**: 21 (control), 21000-21010 (passive mode)
- **Elasticsearch**: 9200 (HTTP API), 9300 (cluster communication)
- **MyProfile**: 8888 (HTTP)
- **WordPress**: 9000 (internal only, accessed via NGINX)

#### Health Checks

Health checks ensure services are ready before dependent services start:

```yaml
# MariaDB health check
mariadb:
  healthcheck:
    test: ["CMD-SHELL", "mariadb -u root -p$$(cat /run/secrets/db_root_password) -e 'SELECT 1' >/dev/null 2>&1"]
    start_period: 30s
    interval: 10s
    timeout: 5s
    retries: 5

# Elasticsearch health check
elasticsearch:
  healthcheck:
    test: ["CMD-SHELL", "wget -q -O /dev/null http://nmatondo.42.fr:9200/_cluster/health || exit 1"]
    start_period: 60s
    interval: 10s
    timeout: 5s
    retries: 5

depends_on:
  mariadb:
    condition: service_healthy
```

### Container Management Commands

#### Lifecycle Management

```bash
# Start containers
docker compose up -d

# Stop containers (preserve volumes)
docker compose down

# Stop and remove volumes
docker compose down -v

# Restart specific container
docker restart mariadb

# Pause/unpause container
docker pause wordpress
docker unpause wordpress

# Remove container
docker rm -f nginx
```

#### Inspecting Containers

```bash
# View running containers
docker ps

# View all containers (including stopped)
docker ps -a

# Inspect container details
docker inspect mariadb

# View container resource usage
docker stats

# View container processes
docker top wordpress
```

#### Executing Commands in Containers

```bash
# Interactive shell
docker exec -it mariadb /bin/bash
docker exec -it wordpress /bin/sh  # Alpine uses sh

# Single command execution
docker exec mariadb mariadb -u root -p$(cat ../secrets/db_root_password.txt) -e "SHOW DATABASES;"

# Or using Docker secrets path inside container
docker exec mariadb mariadb -u root -p$(docker exec mariadb cat /run/secrets/db_root_password) -e "SHOW DATABASES;"

# Execute as specific user
docker exec -u www-data wordpress ls -la /var/www/html
```

#### Viewing Logs

```bash
# Follow all logs
docker compose logs -f

# Specific service logs
docker logs mariadb

# Last 100 lines
docker logs --tail 100 nginx

# Logs with timestamps
docker logs -t wordpress

# Follow logs from specific time
docker logs --since 10m wordpress
```

### Volume Management

#### Understanding Volumes

The project uses Docker volumes for data persistence:

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
```

#### Volume Commands

```bash
# List all volumes
docker volume ls

# Inspect volume
docker volume inspect inception_mariadb_data

# Create volume manually
docker volume create my_volume

# Remove unused volumes
docker volume prune

# Remove specific volume
docker volume rm inception_mariadb_data

# Backup volume
docker run --rm -v inception_wordpress_data:/data -v $(pwd):/backup alpine tar czf /backup/wordpress.tar.gz /data

# Restore volume
docker run --rm -v inception_wordpress_data:/data -v $(pwd):/backup alpine tar xzf /backup/wordpress.tar.gz -C /
```

#### Volume Permissions

Fix permission issues:

```bash
# Change ownership in volume
docker run --rm -v inception_wordpress_data:/data alpine chown -R 82:82 /data

# View volume permissions
docker run --rm -v inception_wordpress_data:/data alpine ls -la /data
```

---

## Data Storage and Persistence

### Data Location

#### Docker Volume Storage

**Named Volumes Location:**
- **Linux:** `/var/lib/docker/volumes/inception_<volume_name>/_data`
- **Mac:** `~/Library/Containers/com.docker.docker/Data/vms/0/data/docker/volumes/`
- **Windows (WSL2):** `\\wsl$\docker-desktop-data\data\docker\volumes\`

**Access Volume Data:**
```bash
# Linux
sudo ls -la /var/lib/docker/volumes/inception_wordpress_data/_data

# Mac/Windows - use a container
docker run --rm -v inception_wordpress_data:/data alpine ls -la /data
```

#### Bind Mounts

If using bind mounts (configured in Makefile):

```makefile
DATA_PATH = /home/nmatondo/data
```

Data is stored directly on the host:
- MariaDB: `$DATA_PATH/mariadb`
- WordPress: `$DATA_PATH/wordpress`
- Redis: `$DATA_PATH/redis`
- Elasticsearch: `$DATA_PATH/elasticsearch`
- MyProfile: (uses Docker-managed volume)

### Data Persistence Strategy

#### What Persists

| Service | Data Type | Volume | Path in Container |
|---------|-----------|--------|------------------|
| **MariaDB** | Database files | `mariadb_data` | `/var/lib/mysql` |
| **WordPress** | Files, themes, plugins, uploads | `wordpress_data` | `/var/www/html` |
| **Redis** | Cache data | `redis_data` | `/data` |
| **Elasticsearch** | Index data | `elasticsearch_data` | `/var/lib/elasticsearch` |
| **MyProfile** | Static website files | `myprofile_data` | `/var/www/myprofile` |

#### How Persistence Works

1. **Container Creation:** Volume is mounted to container path
2. **Data Written:** Application writes to container path
3. **Volume Storage:** Data is stored in Docker volume
4. **Container Removal:** Data remains in volume
5. **Container Recreation:** Data is remounted from volume

#### Data Lifecycle

```bash
# Create container with volume
docker compose up -d wordpress
# WordPress writes data → /var/www/html → wordpress_data volume

# Stop and remove container
docker compose down
# Container deleted, but wordpress_data volume persists

# Recreate container
docker compose up -d wordpress
# Container recreated, wordpress_data remounted → data intact!
```

### Backup and Restore

#### Database Backup

```bash
# Export database
docker exec mariadb mariadb-dump \
  -u root \
  -p$(docker exec mariadb cat /run/secrets/db_root_password) \
  --all-databases > backup_$(date +%Y%m%d).sql

# Backup specific database
docker exec mariadb mariadb-dump \
  -u root \
  -p$(docker exec mariadb cat /run/secrets/db_root_password) \
  wordpress > wordpress_backup.sql
```

#### Database Restore

```bash
# Import database
docker exec -i mariadb mariadb \
  -u root \
  -p$(docker exec mariadb cat /run/secrets/db_root_password) \
  < backup.sql

# Restore specific database
docker exec -i mariadb mariadb \
  -u root \
  -p$(docker exec mariadb cat /run/secrets/db_root_password) \
  wordpress < wordpress_backup.sql
```

#### Volume Backup

```bash
# Backup WordPress volume
docker run --rm \
  -v inception_wordpress_data:/data \
  -v $(pwd):/backup \
  alpine tar czf /backup/wordpress_$(date +%Y%m%d).tar.gz /data

# Backup MariaDB volume (stop container first!)
docker stop mariadb
docker run --rm \
  -v inception_mariadb_data:/data \
  -v $(pwd):/backup \
  alpine tar czf /backup/mariadb_$(date +%Y%m%d).tar.gz /data
docker start mariadb
```

#### Volume Restore

```bash
# Restore WordPress volume
docker run --rm \
  -v inception_wordpress_data:/data \
  -v $(pwd):/backup \
  alpine sh -c "cd / && tar xzf /backup/wordpress_backup.tar.gz"

# Restore MariaDB volume (container must be stopped!)
docker stop mariadb
docker run --rm \
  -v inception_mariadb_data:/data \
  -v $(pwd):/backup \
  alpine sh -c "cd / && tar xzf /backup/mariadb_backup.tar.gz"
docker start mariadb
```

---

## Development Workflow

### Dockerfile Modifications

When modifying a Dockerfile:

```bash
# Rebuild specific service
docker compose build mariadb

# Rebuild without cache
docker compose build --no-cache mariadb

# Recreate container with new image
docker compose up -d --force-recreate mariadb
```

### Configuration Changes

#### Environment Variables (.env)

Changes to `.env` require container restart:

```bash
# Edit .env
nano srcs/.env

# Restart containers
docker compose down
docker compose up -d
```

#### Service Configuration Files

Changes to config files (nginx.conf, www.conf, etc.):

```bash
# Edit config
nano srcs/requirements/nginx/conf/nginx.conf

# Rebuild and restart
docker compose build nginx
docker compose up -d --force-recreate nginx
```

### Live Development

#### WordPress Development

```bash
# Access WordPress container
docker exec -it wordpress /bin/sh

# Edit PHP files directly
docker exec wordpress vi /var/www/html/wp-config.php

# Watch WordPress logs
docker logs -f wordpress
```

#### NGINX Configuration Testing

```bash
# Test NGINX config without restarting
docker exec nginx nginx -t

# Reload NGINX (without downtime)
docker exec nginx nginx -s reload
```

### Debugging Techniques

#### Check Service Connectivity

```bash
# From WordPress to MariaDB
docker exec wordpress ping -c 3 mariadb

# Check if port is listening
docker exec wordpress nc -zv mariadb 3306

# Test HTTP endpoint
docker exec nginx curl -I http://wordpress:9000
```

#### Inspect Network

```bash
# List networks
docker network ls

# Inspect network
docker network inspect inception_network

# View connected containers
docker network inspect inception_network | grep -A 3 Containers
```

#### Monitor Resources

```bash
# Real-time resource usage
docker stats

# Specific container
docker stats mariadb

# One-time snapshot
docker stats --no-stream
```

---

## Troubleshooting and Debugging

### Common Issues

#### Port Already in Use

```bash
# Find process using port 443
sudo lsof -i :443
# or
sudo netstat -tulpn | grep :443

# Kill the process
sudo kill -9 <PID>
```

#### Permission Denied Errors

```bash
# Fix volume permissions
docker run --rm \
  -v inception_wordpress_data:/data \
  alpine chown -R 82:82 /data

# Check SELinux (if applicable)
sudo setenforce 0
```

#### Container Exits Immediately

```bash
# View exit code and error
docker ps -a
docker logs <container_name>

# Inspect container state
docker inspect <container_name> | grep -A 10 State
```

#### Database Connection Refused

```bash
# Check if MariaDB is running
docker ps | grep mariadb

# Check MariaDB logs
docker logs mariadb

# Verify password
cat secrets/db_password.txt

# Test connection manually
docker exec mariadb mariadb -u wpuser -p$(docker exec mariadb cat /run/secrets/db_password) -e "SELECT 1"
```

### Advanced Debugging

#### Enable Debug Mode

**WordPress:**
```php
// Add to wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

**NGINX:**
```nginx
# Add to nginx.conf
error_log /var/log/nginx/error.log debug;
```

#### Use strace

```bash
# Trace system calls
docker exec mariadb apk add strace
docker exec mariadb strace -p 1
```

#### Analyze Container Diff

```bash
# See what changed in container filesystem
docker diff wordpress
```

### Performance Optimization

#### Image Size Reduction

```dockerfile
# Use multi-stage builds
# Use alpine base images
# Minimize layers
# Remove build dependencies
```

#### Build Cache Optimization

```dockerfile
# Order Dockerfile for cache efficiency
# Copy dependency files first
# Copy source code last
```

#### Resource Limits

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

## Project Conventions

### File Naming
- Dockerfiles: `Dockerfile` (capitalized)
- Scripts: `entrypoint.sh`, `init.sh`
- Configs: Service-specific (e.g., `nginx.conf`, `www.conf`)

### Secrets Management
- Store in `secrets/` directory
- Never commit to version control
- Use `.txt` extension
- One value per file

### Environment Variables
- Non-sensitive config only
- Store in `srcs/.env`
- Use `UPPERCASE_WITH_UNDERSCORES`
- Document all variables

---

## Quick Reference

### Essential Commands

```bash
# Build and start
make

# View logs
make logs

# Check status
make status

# Restart
make restart

# Clean rebuild
make re

# Access container
docker exec -it <container> /bin/sh

# View volume data
docker run --rm -v <volume>:/data alpine ls -la /data

# Backup database
docker exec mariadb mariadb-dump -u root -p$(docker exec mariadb cat /run/secrets/db_root_password) wordpress > backup.sql
```

---

## Additional Resources

- [Docker Documentation](https://docs.docker.com/)
- [Docker Compose Reference](https://docs.docker.com/compose/compose-file/)
- [Dockerfile Best Practices](https://docs.docker.com/develop/develop-images/dockerfile_best-practices/)
- [WordPress Developer Resources](https://developer.wordpress.org/)
- [NGINX Documentation](https://nginx.org/en/docs/)

For user-facing documentation, see [USER_DOC.md](USER_DOC.md).
