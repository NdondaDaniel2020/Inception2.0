*This project has been created as part of the 42 curriculum by nmatondo.*

# Inception

## 🎯 Description

Inception is a comprehensive system administration project that demonstrates advanced containerization techniques using Docker. The project creates a complete production-ready infrastructure with multiple isolated services, each running in its own dedicated container, orchestrated through Docker Compose.

### Key Features

- **Complete Infrastructure as Code**: All services defined in declarative configuration
- **Security-First Design**: TLS encryption, secret management, and isolated network
- **Data Persistence**: Volume management with bind mounts to host filesystem
- **Service Health Monitoring**: Health checks for critical services
- **Scalable Architecture**: Modular design allowing easy service addition

### 🏗️ Infrastructure Components

#### Mandatory Services

| Service | Technology | Purpose | Port |
|---------|-----------|---------|------|
| **NGINX** | Alpine 3.23 + NGINX | Reverse proxy with TLS 1.2/1.3 | 443 |
| **WordPress** | Alpine 3.23 + PHP-FPM | Content Management System | 9000 (internal) |
| **MariaDB** | Alpine 3.23 + MariaDB | Relational database | 3306 (internal) |

#### Bonus Services

| Service | Technology | Purpose | Port |
|---------|-----------|---------|------|
| **Redis** | Alpine 3.23 + Redis | In-memory cache for WordPress | 6379 (internal) |
| **FTP** | Alpine 3.23 + vsftpd | File transfer server | 21, 21000-21010 |
| **Adminer** | Alpine 3.23 + PHP | Database management interface | 8080 |
| **Elasticsearch** | Alpine 3.23 + ES | Search and analytics engine | 9200, 9300 |
| **MyProfile** | Alpine 3.23 + httpd | Static personal website | 8888 |

All services are built from **Alpine Linux 3.23** using custom Dockerfiles without pre-built application images from Docker Hub.

---

## 📋 Prerequisites

Before starting, ensure you have the following installed:

- **Docker Engine** 20.10+ ([Installation Guide](https://docs.docker.com/engine/install/))
- **Docker Compose** 2.0+ (included with Docker Desktop)
- **GNU Make** 4.0+
- **Git** 2.0+

**System Requirements:**
- RAM: 4GB minimum (8GB recommended)
- Disk Space: 10GB free
- OS: Linux, macOS, or Windows with WSL2

**Verify Installation:**
```bash
docker --version          # Should show 20.10+
docker compose version    # Should show v2.0+
make --version           # Should show 4.0+
```

---

## 🚀 Installation

### 1. Clone the Repository

```bash
git clone <repository-url>
cd Inception
```

### 2. Configure Domain Name

Add the domain to your hosts file:

**Linux/Mac:**
```bash
sudo nano /etc/hosts
# Add this line:
127.0.0.1    nmatondo.42.fr
```

**Windows (WSL2):**
```powershell
# Edit C:\Windows\System32\drivers\etc\hosts as Administrator
127.0.0.1    nmatondo.42.fr
```

### 3. Configure Environment Variables

Create `srcs/.env` file:

```env
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
```

### 4. Set Up Secrets

Create the `secrets/` directory and populate with password files:

```bash
mkdir -p secrets

# Create secret files (replace with your secure passwords)
echo "your_strong_root_password" > secrets/db_root_password.txt
echo "your_db_password" > secrets/db_password.txt
echo "admin:your_admin_password" > secrets/credentials.txt
echo "your_redis_password" > secrets/redis_password.txt
echo "ftpuser:your_ftp_password" > secrets/ftp_credentials.txt

# Secure the secrets
chmod 600 secrets/*.txt
```

### 5. Create Data Directories

```bash
mkdir -p /home/nmatondo/data/{mariadb,wordpress,redis,elasticsearch,myprofile}
```

---

## 🎮 Usage

### Quick Start

**Start mandatory services only (NGINX, WordPress, MariaDB):**
```bash
make
```

**Start all services including bonus:**
```bash
make bonus
```

### Available Commands

| Command | Description |
|---------|-------------|
| `make` or `make all` | Build and start mandatory services |
| `make bonus` | Build and start all services (including bonus) |
| `make build` | Build Docker images only |
| `make up` | Start containers |
| `make down` | Stop containers (preserves data) |
| `make clean` | Stop containers and remove volumes |
| `make fclean` | Complete cleanup (containers, images, volumes) |
| `make logs` | View container logs (follow mode) |
| `make restart` | Restart all containers |
| `make status` | Show container status |
| `make re` | Rebuild mandatory services from scratch |
| `make bre` | Rebuild bonus services from scratch |

### Access Points

After starting the services, access them via:

| Service | URL | Credentials |
|---------|-----|-------------|
| **WordPress** | https://nmatondo.42.fr | Admin from secrets/credentials.txt |
| **Adminer** | http://nmatondo.42.fr:8080 | DB credentials from .env |
| **MyProfile** | http://nmatondo.42.fr:8888 | None (static site) |
| **FTP** | ftp://nmatondo.42.fr:21 | FTP credentials from secrets |
| **Elasticsearch** | http://nmatondo.42.fr:9200 | None |

---

## 💻 Technologies Stack

### Core Technologies

- **Base OS**: Alpine Linux 3.23 (all containers)
- **Web Server**: NGINX (latest Alpine)
- **Programming Language**: PHP 8.3 with 15+ extensions
- **Database**: MariaDB (latest Alpine)
- **Cache**: Redis (latest Alpine)
- **FTP Server**: vsftpd (latest Alpine)
- **Search Engine**: Elasticsearch (latest Alpine)
- **Static Web Server**: httpd from busybox-extras
- **Database Admin**: Adminer (single PHP file)

### WordPress PHP Extensions

The WordPress container includes:
```
php83-fpm php83-mysqli php83-pdo php83-pdo_mysql
php83-gd php83-intl php83-mbstring php83-xml php83-zip
php83-opcache php83-curl php83-tokenizer php83-session php83-phar
```

### Tools and Utilities

- **WP-CLI**: WordPress command-line interface
- **OpenSSL**: TLS certificate generation
- **MariaDB Client**: Database connectivity
- **Curl**: HTTP requests and downloads

---

## 🏛️ Architecture

### Network Architecture

```
Internet
    │
    ↓
[NGINX:443] ← TLS/HTTPS Entry Point (TLS 1.2/1.3)
    │
    ├─→ [WordPress:9000] ← PHP 8.3 FPM
    │        │
    │        ├─→ [MariaDB:3306] ← Database
    │        ├─→ [Redis:6379] ← Object Cache
    │        └─→ [Elasticsearch:9200] ← Search
    │
    ├─→ [Adminer:8080] ← DB Management UI
    ├─→ [FTP:21, 21000-21010] ← File Transfer (passive mode)
    └─→ [MyProfile:8888] ← Static Personal Site

All containers communicate via Docker bridge network "network"
```

### Data Persistence

All service data is persisted using Docker volumes with bind mounts to `/home/nmatondo/data/`:

- `mariadb_data` → Database files
- `wordpress_data` → WordPress files and uploads
- `redis_data` → Redis persistence
- `elasticsearch_data` → Elasticsearch indices
- `myprofile_data` → Static website files

### Secret Management

Sensitive data is managed using Docker secrets, stored in the `secrets/` directory and mounted securely into containers at `/run/secrets/`.

---

## 🔧 Development

For detailed development documentation, see [DEV_DOC.md](DEV_DOC.md).

### Project Structure

```
Inception/
├── Makefile              # Build automation
├── README.md            # This file
├── DEV_DOC.md          # Developer documentation
├── USER_DOC.md         # User guide
├── secrets/            # Sensitive credentials
├── srcs/
│   ├── .env            # Environment variables
│   ├── docker-compose.yml  # Service orchestration
│   └── requirements/   # Service configurations
│       ├── nginx/      # Web server
│       ├── wordpress/  # CMS
│       ├── mariadb/    # Database
│       └── bonus/      # Additional services
└── docs/              # Additional documentation
```

### Building Individual Services

```bash
# Build specific service
docker compose -f srcs/docker-compose.yml build <service-name>

# Example: Build only NGINX
docker compose -f srcs/docker-compose.yml build nginx
```

### Debugging

```bash
# View logs for all services
make logs

# View logs for specific service
docker compose -f srcs/docker-compose.yml logs -f mariadb

# Execute commands inside a container
docker exec -it <container-name> sh

# Check container status
docker ps -a
```

---

## 📚 Documentation

- [DEV_DOC.md](DEV_DOC.md) - Complete developer documentation
- [USER_DOC.md](USER_DOC.md) - User guide and service documentation
- [docs/](docs/) - Service-specific configuration guides

### Service-Specific Documentation

- [NGINX_CONFIG.md](docs/NGINX_CONFIG.md) - NGINX configuration details
- [WORDPRESS_CONFIG.md](docs/WORDPRESS_CONFIG.md) - WordPress setup
- [MARIADB_CONFIG.md](docs/MARIADB_CONFIG.md) - Database configuration
- [REDIS_CONFIG.md](docs/REDIS_CONFIG.md) - Redis cache setup
- [FTP_CONFIG.md](docs/FTP_CONFIG.md) - FTP server configuration
- [ELASTICSEARCH_CONFIG.md](docs/ELASTICSEARCH_CONFIG.md) - Elasticsearch setup

---

## 🔒 Security Features

- **TLS Encryption**: All HTTP traffic redirected to HTTPS with TLS 1.2/1.3
- **Secret Management**: Passwords and sensitive data stored as Docker secrets
- **Network Isolation**: Services communicate through private Docker network
- **Minimal Attack Surface**: Alpine Linux base images (minimal size)
- **Health Checks**: Automated service health monitoring
- **No Root Processes**: Services run as non-privileged users where possible

---

## 🐛 Troubleshooting

### Common Issues

**Problem: Port already in use**
```bash
# Find and kill process using port 443
sudo lsof -i :443
sudo kill -9 <PID>
```

**Problem: Containers not starting**
```bash
# Check logs
make logs

# Verify secrets exist
ls -la secrets/

# Check environment file
cat srcs/.env
```

**Problem: Cannot access services**
```bash
# Verify domain in hosts file
cat /etc/hosts | grep nmatondo.42.fr

# Check container status
docker ps

# Test connectivity
curl -k https://nmatondo.42.fr

# Test MyProfile (HTTP on port 8888)
curl http://nmatondo.42.fr:8888
```

**Problem: Database connection errors**
```bash
# Check MariaDB health
docker exec -it mariadb mysql -u root -p

# Verify WordPress can connect
docker exec -it wordpress ping mariadb
```
**Problem: FTP connection issues**
```bash
# Ensure passive ports are accessible
# Ports 21000-21010 must be open for passive mode
sudo ufw allow 21/tcp
sudo ufw allow 21000:21010/tcp

# Check FTP container logs
docker logs ftp
```
---

## 📝 License

This project is part of the 42 School curriculum and is intended for educational purposes.

---

## 👤 Author

**nmatondo**
- 42 Intra: nmatondo
- Project: Inception

---

## 🙏 Acknowledgments

- 42 School for the project subject
- Docker documentation
- Alpine Linux community
- WordPress, NGINX, MariaDB, and other open-source projects used

---

## 📞 Support

For issues and questions:
1. Check [USER_DOC.md](USER_DOC.md) for user guides
2. Review [DEV_DOC.md](DEV_DOC.md) for technical details
3. Consult service-specific documentation in [docs/](docs/)
4. Check container logs: `make logs`

---

## 🔍 Technical Deep Dive

### Docker Architecture

This project leverages **Docker** containerization to create an isolated, reproducible, and portable infrastructure. Each service runs in its own container, ensuring:

1. **Isolation:** Services are separated from each other and the host system
2. **Reproducibility:** The same environment can be recreated anywhere
3. **Resource Efficiency:** Containers share the host OS kernel
4. **Scalability:** Services can be scaled independently

### Design Principles

#### Custom Dockerfiles
All containers are built from custom Dockerfiles (no pre-built images from Docker Hub except base OS). This provides:
- Full control over the build process
- Understanding of each service's dependencies
- Security through minimal base images (Alpine Linux 3.23)
- Optimization for specific use cases

#### Health Checks
Critical services include health checks to ensure proper startup order and automatic recovery:
```yaml
healthcheck:
  test: ["CMD-SHELL", "mariadb -u root -p$$(cat /run/secrets/db_root_password) -e 'SELECT 1'"]
  interval: 10s
  timeout: 5s
  retries: 5
```

#### PID 1 Problem Solution
Each container uses `exec` in entrypoint scripts to ensure the main process runs as PID 1, enabling proper signal handling for graceful shutdowns.

---

## 📊 Technical Comparisons

### Virtual Machines vs Docker Containers

| Aspect | Virtual Machines | Docker Containers |
|--------|-----------------|-------------------|
| **Architecture** | Full OS with hypervisor | Shares host OS kernel |
| **Size** | GBs (includes full OS) | MBs (only app + dependencies) |
| **Startup Time** | Minutes | Seconds |
| **Resource Usage** | High (dedicated resources) | Low (shared kernel) |
| **Isolation** | Complete (hardware-level) | Process-level (namespaces) |
| **Portability** | Limited (hypervisor-dependent) | High (runs anywhere) |
| **Performance** | Overhead from virtualization | Near-native performance |

**Why Docker for Inception:** Lightweight, fast startup, easy version control, efficient resource utilization, and industry standard for microservices.

### Docker Secrets vs Environment Variables

| Aspect | Docker Secrets | Environment Variables |
|--------|----------------|----------------------|
| **Security** | Encrypted at rest and in transit | Plaintext, visible in logs |
| **Storage** | `/run/secrets/` (tmpfs - RAM) | Process environment |
| **Visibility** | Only accessible to specified services | Visible everywhere |
| **Rotation** | Can be updated without rebuilding | Requires container restart |
| **Best For** | Passwords, API keys, certificates | Configuration, non-sensitive data |

**Implementation in Inception:**
```yaml
secrets:
  db_password:
    file: ../secrets/db_password.txt
  ftp_credentials:
    file: ../secrets/ftp_credentials.txt
```
Secrets are mounted at `/run/secrets/<secret_name>` in containers (tmpfs - RAM only), never written to disk.

### Docker Volumes vs Bind Mounts

| Aspect | Docker Volumes | Bind Mounts |
|--------|----------------|-------------|
| **Management** | Managed by Docker | User manages host path |
| **Location** | Docker storage area | Any host path |
| **Portability** | Portable across hosts | Host-path dependent |
| **Performance** | Optimized by Docker | Direct filesystem access |
| **Use Case** | Production data persistence | Development, host file sharing |

**Implementation in Inception:**
```yaml
volumes:
  wordpress_data:
    driver: local
    driver_opts:
      type: none
      o: bind
      device: /home/nmatondo/data/wordpress
```
This approach combines Docker volume management with direct host path access for data persistence.

---

## 📚 Resources

### Docker Documentation
- [Docker Official Documentation](https://docs.docker.com/)
- [Docker Compose Documentation](https://docs.docker.com/compose/)
- [Dockerfile Best Practices](https://docs.docker.com/develop/develop-images/dockerfile_best-practices/)
- [Docker Security](https://docs.docker.com/engine/security/)
- [Docker Networking](https://docs.docker.com/network/)

### Service-Specific Resources
- [NGINX Documentation](https://nginx.org/en/docs/)
- [MariaDB Documentation](https://mariadb.com/kb/en/)
- [WordPress Developer Resources](https://developer.wordpress.org/)
- [PHP-FPM Configuration](https://www.php.net/manual/en/install.fpm.php)
- [Redis Documentation](https://redis.io/documentation)
- [vsftpd Documentation](https://security.appspot.com/vsftpd.html)

---

## 🤖 AI Usage Declaration

AI assistance was utilized in the following aspects of this project:

**1. Documentation and Research:**
- Understanding Docker networking concepts
- Researching Dockerfile best practices
- Learning Docker secrets implementation

**2. Troubleshooting:**
- Debugging container startup issues
- Resolving network connectivity problems
- Fixing volume permission issues

**3. Configuration Optimization:**
- NGINX reverse proxy configuration
- PHP-FPM pool optimization
- MariaDB performance tuning
- Redis cache configuration

**4. Code Review:**
- Reviewing Dockerfile efficiency
- Security vulnerability assessment
- Entrypoint script validation

**Note:** All AI-generated suggestions were reviewed, tested, and adapted to meet project requirements. The core implementation and architecture decisions were made with AI as a learning and research assistant.

---

**Last Updated:** January 2026  
**Project Status:** ✅ Complete and Functional
