*This project has been created as part of the 42 curriculum by nmatondo.*

# Inception

## Description

Inception is a system administration project that focuses on containerization using Docker. The goal is to create a small infrastructure composed of different services, each running in its own dedicated container. The project involves setting up a multi-container Docker application using Docker Compose, with emphasis on security, networking, and data persistence.

The infrastructure includes:
- **NGINX** with TLSv1.2 or TLSv1.3 as the only entry point
- **WordPress** with PHP-FPM for content management
- **MariaDB** as the database server
- **Redis** cache for WordPress optimization
- **FTP server** for file management
- **Adminer** for database administration
- **Elasticsearch** for search capabilities
- **Static website** (personal profile page)

All services are built from penultimate stable versions using Alpine or Debian (penultimate stable) as base images, with custom Dockerfiles and no pre-built images from Docker Hub (except for base OS images).

---

## Instructions

### Prerequisites

- Docker Engine
- Docker Compose
- Make
- A domain name configured to point to your local machine (e.g., `nmatondo.42.fr` pointing to `127.0.0.1` in `/etc/hosts`)

### Installation

1. **Clone the repository:**
   ```bash
   git clone <repository-url>
   cd Inception
   ```

2. **Configure environment variables:**
   
   Create a `.env` file in the `srcs/` directory with the following variables:
   ```env
   DOMAIN_NAME=nmatondo.42.fr
   CERT_=./requirements/nginx/tools/nmatondo.42.fr.crt
   KEY_=./requirements/nginx/tools/nmatondo.42.fr.key
   DB_NAME=wordpress
   DB_USER=wpuser
   DB_HOST=mariadb
   FTP_USER=ftpuser
   REDIS_HOST=redis:6379
   WP_TITLE=Inception
   WP_URL=https://nmatondo.42.fr
   WP_ADMIN_USER=admin
   WP_ADMIN_EMAIL=admin@nmatondo.42.fr
   WP_USER=user
   WP_USER_EMAIL=user@nmatondo.42.fr
   ```

3. **Set up secrets:**
   
   Create the following secret files in the `secrets/` directory:
   - `db_root_password.txt` - MariaDB root password
   - `db_password.txt` - WordPress database user password
   - `credentials.txt` - WordPress admin credentials (format: `username:password`)
   - `redis_password.txt` - Redis authentication password
   - `ftp_password.txt` - FTP user password

### Compilation and Execution

#### Mandatory Part

Build and start the mandatory services (NGINX, WordPress, MariaDB):
```bash
make
```

Or step by step:
```bash
make build    # Build Docker images
make up       # Start containers
```

#### Bonus Services

Build and start all services including bonus ones:
```bash
make bonus
```

#### Other Commands

```bash
make down      # Stop all containers
make clean     # Stop containers and remove volumes
make fclean    # Complete cleanup (containers, images, volumes, networks)
make logs      # View container logs
make restart   # Restart all containers
make status    # View container status
make re        # Rebuild everything from scratch
```

### Accessing the Services

Once running, access the services at:
- **WordPress:** https://nmatondo.42.fr
- **Adminer:** https://nmatondo.42.fr:8080
- **FTP Server:** ftp://nmatondo.42.fr:21
- **Static Website:** https://nmatondo.42.fr/myprofile

---

## Project Description

### Docker Architecture

This project leverages **Docker** containerization to create an isolated, reproducible, and portable infrastructure. Each service runs in its own container, ensuring:

1. **Isolation:** Services are separated from each other and the host system
2. **Reproducibility:** The same environment can be recreated anywhere
3. **Resource Efficiency:** Containers share the host OS kernel
4. **Scalability:** Services can be scaled independently

### Main Design Choices

#### Custom Dockerfiles
All containers are built from custom Dockerfiles (no pre-built images from Docker Hub except base OS). This provides:
- Full control over the build process
- Understanding of each service's dependencies
- Security through minimal base images (Alpine/Debian)
- Optimization for specific use cases

#### Multi-Stage Builds
Where applicable, multi-stage builds reduce final image size by excluding build dependencies.

#### Health Checks
Services include health checks to ensure proper startup order and automatic recovery:
```yaml
healthcheck:
  test: ["CMD-SHELL", "mariadb -u root -p... -e 'SELECT 1'"]
  interval: 10s
  timeout: 5s
  retries: 5
```

#### PID 1 Problem Solution
Each container uses `exec` in entrypoint scripts to ensure the main process runs as PID 1, enabling proper signal handling for graceful shutdowns.

#### Security First
- No passwords in Dockerfiles or environment files
- Docker secrets for sensitive data
- TLS/SSL encryption for NGINX
- Non-root users where possible
- Read-only filesystems where applicable

---

## Technical Comparisons

### Virtual Machines vs Docker

| Aspect | Virtual Machines | Docker Containers |
|--------|-----------------|-------------------|
| **Architecture** | Full OS with hypervisor | Shares host OS kernel |
| **Size** | GBs (includes full OS) | MBs (only app + dependencies) |
| **Startup Time** | Minutes | Seconds |
| **Resource Usage** | High (each VM has dedicated resources) | Low (shared kernel, isolated processes) |
| **Isolation** | Complete (hardware-level) | Process-level (namespace isolation) |
| **Portability** | Limited (hypervisor-dependent) | High (runs anywhere Docker runs) |
| **Performance** | Overhead from virtualization | Near-native performance |
| **Use Case** | Running different OS, complete isolation | Microservices, rapid deployment |

**Why Docker for Inception:**
- Lightweight and fast startup
- Easy to version control (Dockerfiles)
- Efficient resource utilization
- Perfect for microservices architecture
- Industry standard for development and deployment

### Secrets vs Environment Variables

| Aspect | Docker Secrets | Environment Variables |
|--------|----------------|----------------------|
| **Security** | Encrypted at rest and in transit | Plaintext, visible in container inspect |
| **Storage** | `/run/secrets/` (tmpfs - RAM) | Process environment |
| **Visibility** | Only accessible to specified services | Visible in logs, process lists |
| **Rotation** | Can be updated without rebuilding | Requires container restart |
| **Best For** | Passwords, API keys, certificates | Configuration, non-sensitive data |

**Implementation in Inception:**
```yaml
secrets:
  - db_root_password
  - db_password

secrets:
  db_password:
    file: ../secrets/db_password.txt
```

Secrets are stored in RAM (`tmpfs`) and never written to disk, providing superior security for sensitive credentials.

### Docker Network vs Host Network

| Aspect | Docker Network (Bridge) | Host Network |
|--------|------------------------|--------------|
| **Isolation** | Network namespace isolation | Shares host network stack |
| **Port Mapping** | Required (e.g., `8080:80`) | Direct access to host ports |
| **Security** | Better (isolated, firewall rules) | Lower (direct host exposure) |
| **Performance** | Slight overhead (NAT) | No overhead |
| **DNS** | Built-in service discovery | Manual configuration |
| **Use Case** | Multi-container apps | High-performance networking |

**Why Docker Network for Inception:**
```yaml
networks:
  network:
    driver: bridge
```
- Service discovery by name (e.g., `mariadb:3306`)
- Isolation from host network
- Controlled port exposure
- Better security posture
- Simpler container-to-container communication

### Docker Volumes vs Bind Mounts

| Aspect | Docker Volumes | Bind Mounts |
|--------|----------------|-------------|
| **Management** | Managed by Docker | User manages host path |
| **Location** | Docker storage area | Any host path |
| **Portability** | Portable across hosts | Host-path dependent |
| **Performance** | Optimized by Docker | Direct filesystem access |
| **Backup** | Docker volume commands | Standard filesystem tools |
| **Permissions** | Docker handles | Host permissions apply |
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

volumes:
  - wordpress_data:/var/www/html
```

**Why Volumes:**
- Data persists after container removal
- Can be shared between containers
- Managed by Docker (backed up, migrated)
- Decoupled from host filesystem structure
- Better for production environments

---

## Resources

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

### Tutorials and Guides
- [Docker for Beginners](https://docker-curriculum.com/)
- [Understanding Docker Volumes](https://docs.docker.com/storage/volumes/)
- [SSL/TLS with NGINX](https://nginx.org/en/docs/http/configuring_https_servers.html)

### AI Usage

AI assistance was utilized in the following aspects of this project:

1. **Documentation and Research:**
   - Understanding Docker networking concepts
   - Researching best practices for Dockerfiles
   - Learning about Docker secrets implementation
   - Comparing virtualization technologies

2. **Troubleshooting:**
   - Debugging container startup issues
   - Resolving dependency problems
   - Network connectivity issues between containers
   - Permission problems with volumes

3. **Configuration Optimization:**
   - NGINX configuration for reverse proxy
   - PHP-FPM pool configuration
   - MariaDB performance tuning
   - Redis cache optimization

4. **Code Review:**
   - Reviewing Dockerfile efficiency
   - Security vulnerability assessment
   - Entrypoint script logic validation

5. **Learning and Explanation:**
   - Understanding container orchestration
   - Grasping concepts of volumes vs bind mounts
   - Learning about health checks and dependencies

**Note:** All AI-generated suggestions were reviewed, tested, and adapted to ensure they meet project requirements and best practices. The core implementation, architecture decisions, and problem-solving were performed with AI as a learning and research assistant.

---

## Project Structure

```
.
├── Makefile                    # Build and deployment automation
├── README.md                   # This file
├── secrets/                    # Sensitive credentials (not in git)
│   ├── db_password.txt
│   ├── db_root_password.txt
│   ├── credentials.txt
│   ├── redis_password.txt
│   └── ftp_password.txt
└── srcs/
    ├── .env                    # Environment variables
    ├── docker-compose.yml      # Service orchestration
    └── requirements/
        ├── mariadb/            # Database service
        ├── nginx/              # Web server and reverse proxy
        ├── wordpress/          # CMS application
        └── bonus/
            ├── adminer/        # Database admin interface
            ├── elasticsearch/  # Search engine
            ├── ftp/            # File transfer service
            ├── myprofile/      # Static website
            └── redis/          # Cache service
```

---

## License

This project is part of the 42 school curriculum and is intended for educational purposes.
