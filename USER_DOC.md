# User Documentation - Inception

## Overview

Welcome to Inception! This document provides comprehensive guidance for end users and administrators to understand, deploy, and manage the Inception infrastructure.

---

## Services Provided

The Inception stack provides the following services:

### Mandatory Services

| Service | Description | Port | Purpose |
|---------|-------------|------|---------|
| **NGINX** | Web server & Reverse Proxy | 443 (HTTPS) | Entry point for all web traffic with TLS encryption |
| **WordPress** | Content Management System | 9000 (internal) | Website management and content creation |
| **MariaDB** | Database Server | 3306 (internal) | Data storage for WordPress |

### Bonus Services

| Service | Description | Port | Purpose |
|---------|-------------|------|---------|
| **Adminer** | Database Management | 8080 | Web-based database administration interface |
| **Redis** | In-Memory Cache | 6379 (internal) | WordPress performance optimization |
| **FTP Server** | File Transfer Protocol | 21 | File upload/download management |
| **Elasticsearch** | Search Engine | 9200 (internal) | Advanced search capabilities |
| **Static Website** | Personal Profile | 443/myprofile | Custom static HTML website |

All services run in isolated Docker containers and communicate through a private Docker network.

---

## Starting and Stopping the Project

### Starting the Infrastructure

#### Option 1: Quick Start (Mandatory Services Only)
```bash
make
```
This will build and start NGINX, WordPress, and MariaDB.

#### Option 2: Full Stack (Including Bonus Services)
```bash
make bonus
```
This will build and start all services including Adminer, Redis, FTP, Elasticsearch, and the static website.

#### Option 3: Step-by-Step Start
```bash
make build    # Build all Docker images
make up       # Start all containers
```

**Expected Output:**
```
🔨 Building Docker images...
[+] Building 45.2s (...)
✅ Successfully built all images

🚀 Starting containers...
[+] Running 8/8
✅ Container mariadb       Started
✅ Container wordpress     Started
✅ Container nginx         Started
✅ Container redis         Started
✅ Container adminer       Started
✅ Container ftp           Started
✅ Container elasticsearch Started
✅ Container myprofile     Started
```

### Stopping the Infrastructure

#### Graceful Shutdown (Preserves Data)
```bash
make down
```
This stops all containers but preserves all data in volumes.

#### Complete Shutdown with Data Cleanup
```bash
make clean
```
This stops containers and removes volumes (all data will be lost).

#### Full Reset
```bash
make fclean
```
This removes everything: containers, images, volumes, and networks.

### Restarting Services

```bash
make restart    # Restart all containers without rebuilding
make re         # Rebuild everything from scratch and restart
```

---

## Accessing the Services

### Prerequisites
Ensure your domain name is configured. Add this line to your hosts file:

**Linux/Mac:** `/etc/hosts`  
**Windows:** `C:\Windows\System32\drivers\etc\hosts`

```
127.0.0.1    nmatondo.42.fr
```

### Service URLs

Once the infrastructure is running, access the services through your web browser:

#### WordPress Website
- **URL:** https://nmatondo.42.fr
- **Description:** Main website powered by WordPress
- **Login Page:** https://nmatondo.42.fr/wp-admin
- **Default Admin:**
  - Username: (see credentials section)
  - Password: (see credentials section)

#### Adminer (Database Administration)
- **URL:** https://nmatondo.42.fr:8080 or http://localhost:8080
- **Description:** Web-based database management tool
- **Login Credentials:**
  - System: `MySQL`
  - Server: `mariadb`
  - Username: `wpuser` or `root`
  - Password: (see secrets files)
  - Database: `wordpress`

#### Static Profile Website
- **URL:** https://nmatondo.42.fr/myprofile
- **Description:** Personal static website

#### FTP Server
- **Host:** nmatondo.42.fr
- **Port:** 21
- **Username:** (see credentials section)
- **Password:** (see credentials section)
- **Client:** Use any FTP client (FileZilla, WinSCP, etc.)

### Browser Security Warning

When first accessing the website, your browser may show a security warning because the SSL certificate is self-signed. This is normal for development environments.

**How to proceed:**
- **Chrome/Edge:** Click "Advanced" → "Proceed to nmatondo.42.fr (unsafe)"
- **Firefox:** Click "Advanced" → "Accept the Risk and Continue"
- **Safari:** Click "Show Details" → "visit this website"

---

## Managing Credentials

### Credential Storage

All sensitive credentials are stored in the `secrets/` directory at the root of the project:

```
secrets/
├── db_root_password.txt      # MariaDB root password
├── db_password.txt           # WordPress database password
├── credentials.txt           # WordPress admin credentials
├── redis_password.txt        # Redis authentication password
└── ftp_password.txt          # FTP user password
```

⚠️ **Security Notice:** Never commit the `secrets/` directory to version control!

### Viewing Credentials

#### Database Passwords
```bash
# MariaDB root password
cat secrets/db_root_password.txt

# WordPress database password
cat secrets/db_password.txt
```

#### WordPress Admin Credentials
```bash
cat secrets/credentials.txt
```
Format: `username:password`

#### Redis Password
```bash
cat secrets/redis_password.txt
```

#### FTP Credentials
```bash
cat secrets/ftp_password.txt
```

### Updating Credentials

To update credentials:

1. **Stop the infrastructure:**
   ```bash
   make down
   ```

2. **Edit the secret files:**
   ```bash
   echo "new_password" > secrets/db_password.txt
   ```

3. **Rebuild and restart:**
   ```bash
   make clean
   make
   ```

⚠️ **Important:** Changing database passwords requires rebuilding the database container and may result in data loss. Back up your data first!

### Environment Variables

Non-sensitive configuration is stored in `srcs/.env`:

```bash
DOMAIN_NAME=nmatondo.42.fr
DB_NAME=wordpress
DB_USER=wpuser
WP_TITLE=Inception
WP_ADMIN_USER=admin
WP_USER=user
```

These can be modified directly without requiring a full rebuild.

---

## Checking Service Status

### Quick Status Check

```bash
make status
```

**Expected Output:**
```
NAME            IMAGE               STATUS          PORTS
mariadb         inception-mariadb   Up 10 minutes   3306/tcp
wordpress       inception-wordpress Up 10 minutes   9000/tcp
nginx           inception-nginx     Up 10 minutes   0.0.0.0:443->443/tcp
redis           inception-redis     Up 10 minutes   6379/tcp
adminer         inception-adminer   Up 10 minutes   0.0.0.0:8080->8080/tcp
ftp             inception-ftp       Up 10 minutes   0.0.0.0:21->21/tcp
```

### Detailed Container Information

```bash
docker ps
```

This shows:
- Container names
- Status (Up/Exited)
- Port mappings
- Uptime

### Viewing Container Logs

#### All Services
```bash
make logs
```

#### Specific Service
```bash
docker logs mariadb
docker logs wordpress
docker logs nginx
```

#### Follow Logs in Real-Time
```bash
docker logs -f nginx
```

### Health Checks

#### Verify MariaDB is Running
```bash
docker exec mariadb mariadb -u root -p$(cat secrets/db_root_password.txt) -e "SELECT 1"
```
Expected: `1` (indicates database is responsive)

#### Verify WordPress Files
```bash
docker exec wordpress ls -la /var/www/html
```
Expected: WordPress files listed

#### Verify NGINX Configuration
```bash
docker exec nginx nginx -t
```
Expected: `nginx: configuration file /etc/nginx/nginx.conf test is successful`

#### Check Network Connectivity
```bash
docker exec wordpress ping -c 3 mariadb
```
Expected: Successful ping responses

### Testing Website Availability

#### Using curl
```bash
curl -k https://nmatondo.42.fr
```
Expected: HTML response from WordPress

#### Check SSL Certificate
```bash
openssl s_client -connect nmatondo.42.fr:443 -servername nmatondo.42.fr
```

### Troubleshooting Common Issues

#### Container Won't Start
```bash
# Check container logs for errors
docker logs <container_name>

# Inspect container details
docker inspect <container_name>
```

#### Can't Access Website
1. Check if containers are running: `make status`
2. Verify hosts file configuration
3. Check firewall settings
4. Ensure port 443 is not used by another service

#### Database Connection Errors
1. Verify MariaDB is running: `docker ps | grep mariadb`
2. Check database logs: `docker logs mariadb`
3. Verify credentials in secrets files
4. Ensure WordPress can reach MariaDB: `docker exec wordpress ping mariadb`

#### "502 Bad Gateway" Error
- WordPress container is not running or not ready
- Check WordPress logs: `docker logs wordpress`
- Restart WordPress: `docker restart wordpress`

---

## Data Persistence

### What Data Persists?

All important data is stored in Docker volumes and persists even when containers are stopped:

- **WordPress files** (themes, plugins, uploads)
- **Database data** (posts, pages, users)
- **Redis cache**
- **FTP uploaded files**

### Where is Data Stored?

Docker volumes are typically stored in:
- **Linux:** `/var/lib/docker/volumes/`
- **Windows (WSL2):** `\\wsl$\docker-desktop-data\data\docker\volumes\`
- **Mac:** `~/Library/Containers/com.docker.docker/Data/`

To list volumes:
```bash
docker volume ls
```

### Backing Up Data

```bash
# Backup WordPress data
docker run --rm -v inception_wordpress_data:/data -v $(pwd):/backup alpine tar czf /backup/wordpress_backup.tar.gz /data

# Backup Database
docker exec mariadb mariadb-dump -u root -p$(cat secrets/db_root_password.txt) wordpress > wordpress_backup.sql
```

---

## Maintenance

### Updating WordPress

WordPress can be updated through the admin panel:
1. Login to https://nmatondo.42.fr/wp-admin
2. Navigate to Dashboard → Updates
3. Click "Update Now"

### Cleaning Up Resources

```bash
# Remove stopped containers
docker container prune

# Remove unused images
docker image prune

# Remove unused volumes (⚠️ data loss!)
docker volume prune

# Clean everything unused
docker system prune -a
```

---

## Support

For issues or questions:
1. Check container logs: `make logs`
2. Review this documentation
3. Check the [DEV_DOC.md](DEV_DOC.md) for technical details
4. Consult the main [README.md](README.md) for architecture information

---

## Quick Reference

| Action | Command |
|--------|---------|
| Start infrastructure | `make` or `make bonus` |
| Stop infrastructure | `make down` |
| View logs | `make logs` |
| Check status | `make status` |
| Restart | `make restart` |
| Clean up | `make clean` |
| Full reset | `make fclean` |
| Access WordPress | https://nmatondo.42.fr |
| Access Adminer | https://nmatondo.42.fr:8080 |
| View credentials | `cat secrets/credentials.txt` |
