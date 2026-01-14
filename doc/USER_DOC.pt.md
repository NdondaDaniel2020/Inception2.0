# Documentação de Utilizador - Inception

## Visão Geral

Bem-vindo ao Inception! Este documento fornece orientação abrangente para utilizadores finais e administradores para compreender, implementar e gerir a infraestrutura Inception.

---

## Serviços Disponibilizados

A stack Inception disponibiliza os seguintes serviços:

### Serviços Obrigatórios

| Serviço | Descrição | Porta | Objetivo |
|---------|-----------|-------|----------|
| **NGINX** | Servidor Web & Reverse Proxy | 443 (HTTPS) | Ponto de entrada para todo o tráfego web com encriptação TLS |
| **WordPress** | Sistema de Gestão de Conteúdo | 9000 (interno) | Gestão de website e criação de conteúdo |
| **MariaDB** | Servidor de Base de Dados | 3306 (interno) | Armazenamento de dados para WordPress |

### Serviços Bónus

| Serviço | Descrição | Porta | Objetivo |
|---------|-----------|-------|----------|
| **Adminer** | Gestão de Base de Dados | 8080 | Interface web para administração de base de dados |
| **Redis** | Cache em Memória | 6379 (interno) | Otimização de performance do WordPress |
| **Servidor FTP** | Protocolo de Transferência de Ficheiros | 21 | Gestão de upload/download de ficheiros |
| **Elasticsearch** | Motor de Pesquisa | 9200 (interno) | Capacidades de pesquisa avançada |
| **Website Estático** | Perfil Pessoal | 443/myprofile | Website HTML estático personalizado |

Todos os serviços executam em containers Docker isolados e comunicam através de uma rede Docker privada.

---

## Iniciar e Parar o Projeto

### Iniciar a Infraestrutura

#### Opção 1: Início Rápido (Apenas Serviços Obrigatórios)
```bash
make
```
Isto irá construir e iniciar NGINX, WordPress e MariaDB.

#### Opção 2: Stack Completa (Incluindo Serviços Bónus)
```bash
make bonus
```
Isto irá construir e iniciar todos os serviços incluindo Adminer, Redis, FTP, Elasticsearch e o website estático.

#### Opção 3: Início Passo a Passo
```bash
make build    # Construir todas as imagens Docker
make up       # Iniciar todos os containers
```

**Output Esperado:**
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

### Parar a Infraestrutura

#### Paragem Graciosa (Preserva Dados)
```bash
make down
```
Isto para todos os containers mas preserva todos os dados nos volumes.

#### Paragem Completa com Limpeza de Dados
```bash
make clean
```
Isto para os containers e remove volumes (todos os dados serão perdidos).

#### Reset Completo
```bash
make fclean
```
Isto remove tudo: containers, imagens, volumes e redes.

### Reiniciar Serviços

```bash
make restart    # Reiniciar todos os containers sem reconstruir
make re         # Reconstruir tudo do zero e reiniciar
```

---

## Aceder aos Serviços

### Pré-requisitos
Certifique-se de que o seu nome de domínio está configurado. Adicione esta linha ao seu ficheiro hosts:

**Linux/Mac:** `/etc/hosts`  
**Windows:** `C:\Windows\System32\drivers\etc\hosts`

```
127.0.0.1    nmatondo.42.fr
```

### URLs dos Serviços

Uma vez que a infraestrutura esteja em execução, aceda aos serviços através do seu navegador web:

#### Website WordPress
- **URL:** https://nmatondo.42.fr
- **Descrição:** Website principal powered by WordPress
- **Página de Login:** https://nmatondo.42.fr/wp-admin
- **Admin Padrão:**
  - Username: (ver secção de credenciais)
  - Password: (ver secção de credenciais)

#### Adminer (Administração de Base de Dados)
- **URL:** https://nmatondo.42.fr:8080 ou http://localhost:8080
- **Descrição:** Ferramenta de gestão de base de dados baseada em web
- **Credenciais de Login:**
  - Sistema: `MySQL`
  - Servidor: `mariadb`
  - Username: `wpuser` ou `root`
  - Password: (ver ficheiros secrets)
  - Base de Dados: `wordpress`

#### Website de Perfil Estático
- **URL:** https://nmatondo.42.fr/myprofile
- **Descrição:** Website estático pessoal

#### Servidor FTP
- **Host:** nmatondo.42.fr
- **Porta:** 21
- **Username:** (ver secção de credenciais)
- **Password:** (ver secção de credenciais)
- **Cliente:** Use qualquer cliente FTP (FileZilla, WinSCP, etc.)

### Aviso de Segurança do Navegador

Ao aceder ao website pela primeira vez, o seu navegador pode mostrar um aviso de segurança porque o certificado SSL é auto-assinado. Isto é normal para ambientes de desenvolvimento.

**Como proceder:**
- **Chrome/Edge:** Clique "Avançado" → "Continuar para nmatondo.42.fr (não seguro)"
- **Firefox:** Clique "Avançado" → "Aceitar o Risco e Continuar"
- **Safari:** Clique "Mostrar Detalhes" → "visitar este website"

---

## Gerir Credenciais

### Armazenamento de Credenciais

Todas as credenciais sensíveis são armazenadas no diretório `secrets/` na raiz do projeto:

```
secrets/
├── db_root_password.txt      # Password root do MariaDB
├── db_password.txt           # Password da base de dados WordPress
├── credentials.txt           # Credenciais admin do WordPress
├── redis_password.txt        # Password de autenticação Redis
└── ftp_password.txt          # Password do utilizador FTP
```

⚠️ **Aviso de Segurança:** Nunca faça commit do diretório `secrets/` para controlo de versão!

### Visualizar Credenciais

#### Passwords da Base de Dados
```bash
# Password root do MariaDB
cat secrets/db_root_password.txt

# Password da base de dados WordPress
cat secrets/db_password.txt
```

#### Credenciais Admin do WordPress
```bash
cat secrets/credentials.txt
```
Formato: `username:password`

#### Password Redis
```bash
cat secrets/redis_password.txt
```

#### Credenciais FTP
```bash
cat secrets/ftp_password.txt
```

### Atualizar Credenciais

Para atualizar credenciais:

1. **Parar a infraestrutura:**
   ```bash
   make down
   ```

2. **Editar os ficheiros de secrets:**
   ```bash
   echo "nova_password" > secrets/db_password.txt
   ```

3. **Reconstruir e reiniciar:**
   ```bash
   make clean
   make
   ```

⚠️ **Importante:** Alterar passwords da base de dados requer reconstruir o container da base de dados e pode resultar em perda de dados. Faça backup dos seus dados primeiro!

### Variáveis de Ambiente

A configuração não sensível é armazenada em `srcs/.env`:

```bash
DOMAIN_NAME=nmatondo.42.fr
DB_NAME=wordpress
DB_USER=wpuser
WP_TITLE=Inception
WP_ADMIN_USER=admin
WP_USER=user
```

Estas podem ser modificadas diretamente sem requerer uma reconstrução completa.

---

## Verificar Estado dos Serviços

### Verificação Rápida de Estado

```bash
make status
```

**Output Esperado:**
```
NAME            IMAGE               STATUS          PORTS
mariadb         inception-mariadb   Up 10 minutes   3306/tcp
wordpress       inception-wordpress Up 10 minutes   9000/tcp
nginx           inception-nginx     Up 10 minutes   0.0.0.0:443->443/tcp
redis           inception-redis     Up 10 minutes   6379/tcp
adminer         inception-adminer   Up 10 minutes   0.0.0.0:8080->8080/tcp
ftp             inception-ftp       Up 10 minutes   0.0.0.0:21->21/tcp
```

### Informação Detalhada dos Containers

```bash
docker ps
```

Isto mostra:
- Nomes dos containers
- Estado (Up/Exited)
- Mapeamento de portas
- Tempo de atividade

### Visualizar Logs dos Containers

#### Todos os Serviços
```bash
make logs
```

#### Serviço Específico
```bash
docker logs mariadb
docker logs wordpress
docker logs nginx
```

#### Seguir Logs em Tempo Real
```bash
docker logs -f nginx
```

### Verificações de Saúde

#### Verificar que MariaDB Está em Execução
```bash
docker exec mariadb mariadb -u root -p$(cat secrets/db_root_password.txt) -e "SELECT 1"
```
Esperado: `1` (indica que a base de dados está responsiva)

#### Verificar Ficheiros WordPress
```bash
docker exec wordpress ls -la /var/www/html
```
Esperado: Ficheiros WordPress listados

#### Verificar Configuração NGINX
```bash
docker exec nginx nginx -t
```
Esperado: `nginx: configuration file /etc/nginx/nginx.conf test is successful`

#### Verificar Conectividade de Rede
```bash
docker exec wordpress ping -c 3 mariadb
```
Esperado: Respostas de ping bem-sucedidas

### Testar Disponibilidade do Website

#### Usando curl
```bash
curl -k https://nmatondo.42.fr
```
Esperado: Resposta HTML do WordPress

#### Verificar Certificado SSL
```bash
openssl s_client -connect nmatondo.42.fr:443 -servername nmatondo.42.fr
```

### Resolução de Problemas Comuns

#### Container Não Inicia
```bash
# Verificar logs do container para erros
docker logs <nome_container>

# Inspecionar detalhes do container
docker inspect <nome_container>
```

#### Não Consegue Aceder ao Website
1. Verificar se os containers estão em execução: `make status`
2. Verificar configuração do ficheiro hosts
3. Verificar definições de firewall
4. Assegurar que a porta 443 não está a ser usada por outro serviço

#### Erros de Conexão à Base de Dados
1. Verificar que MariaDB está em execução: `docker ps | grep mariadb`
2. Verificar logs da base de dados: `docker logs mariadb`
3. Verificar credenciais nos ficheiros secrets
4. Assegurar que WordPress consegue alcançar MariaDB: `docker exec wordpress ping mariadb`

#### Erro "502 Bad Gateway"
- Container WordPress não está em execução ou não está pronto
- Verificar logs do WordPress: `docker logs wordpress`
- Reiniciar WordPress: `docker restart wordpress`

---

## Persistência de Dados

### Que Dados Persistem?

Todos os dados importantes são armazenados em volumes Docker e persistem mesmo quando os containers são parados:

- **Ficheiros WordPress** (temas, plugins, uploads)
- **Dados da base de dados** (posts, páginas, utilizadores)
- **Cache Redis**
- **Ficheiros carregados via FTP**

### Onde São Armazenados os Dados?

Os volumes Docker são tipicamente armazenados em:
- **Linux:** `/var/lib/docker/volumes/`
- **Windows (WSL2):** `\\wsl$\docker-desktop-data\data\docker\volumes\`
- **Mac:** `~/Library/Containers/com.docker.docker/Data/`

Para listar volumes:
```bash
docker volume ls
```

### Fazer Backup dos Dados

```bash
# Backup dos dados WordPress
docker run --rm -v inception_wordpress_data:/data -v $(pwd):/backup alpine tar czf /backup/wordpress_backup.tar.gz /data

# Backup da Base de Dados
docker exec mariadb mariadb-dump -u root -p$(cat secrets/db_root_password.txt) wordpress > wordpress_backup.sql
```

---

## Manutenção

### Atualizar WordPress

O WordPress pode ser atualizado através do painel de administração:
1. Login em https://nmatondo.42.fr/wp-admin
2. Navegar para Dashboard → Atualizações
3. Clicar "Atualizar Agora"

### Limpeza de Recursos

```bash
# Remover containers parados
docker container prune

# Remover imagens não utilizadas
docker image prune

# Remover volumes não utilizados (⚠️ perda de dados!)
docker volume prune

# Limpar tudo o que não é utilizado
docker system prune -a
```

---

## Suporte

Para problemas ou questões:
1. Verificar logs dos containers: `make logs`
2. Rever esta documentação
3. Consultar o [DEV_DOC.md](DEV_DOC.md) para detalhes técnicos
4. Consultar o [README.md](README.md) principal para informação de arquitetura

---

## Referência Rápida

| Ação | Comando |
|------|---------|
| Iniciar infraestrutura | `make` ou `make bonus` |
| Parar infraestrutura | `make down` |
| Ver logs | `make logs` |
| Verificar estado | `make status` |
| Reiniciar | `make restart` |
| Limpar | `make clean` |
| Reset completo | `make fclean` |
| Aceder WordPress | https://nmatondo.42.fr |
| Aceder Adminer | https://nmatondo.42.fr:8080 |
| Ver credenciais | `cat secrets/credentials.txt` |
