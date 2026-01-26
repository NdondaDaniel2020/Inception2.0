*Este projeto foi criado como parte do currículo da 42 por nmatondo.*

# Inception

## 🎯 Descrição

Inception é um projeto abrangente de administração de sistemas que demonstra técnicas avançadas de containerização usando Docker. O projeto cria uma infraestrutura completa pronta para produção com múltiplos serviços isolados, cada um executando em seu próprio container dedicado, orquestrados através do Docker Compose.

### Características Principais

- **Infraestrutura Completa como Código**: Todos os serviços definidos em configuração declarativa
- **Design Focado em Segurança**: Criptografia TLS, gestão de secrets e rede isolada
- **Persistência de Dados**: Gestão de volumes com bind mounts ao sistema de arquivos do host
- **Monitoramento de Saúde dos Serviços**: Health checks para serviços críticos
- **Arquitetura Escalável**: Design modular permitindo fácil adição de serviços

### 🏗️ Componentes da Infraestrutura

#### Serviços Obrigatórios

| Serviço | Tecnologia | Propósito | Porta |
|---------|-----------|-----------|-------|
| **NGINX** | Alpine 3.22 + NGINX | Proxy reverso com TLS 1.2/1.3 | 443 |
| **WordPress** | Alpine 3.22 + PHP-FPM | Sistema de Gestão de Conteúdo | 9000 (interna) |
| **MariaDB** | Alpine 3.22 + MariaDB | Base de dados relacional | 3306 (interna) |

#### Serviços Bônus

| Serviço | Tecnologia | Propósito | Porta |
|---------|-----------|-----------|-------|
| **Redis** | Alpine 3.22 + Redis | Cache em memória para WordPress | 6379 (interna) |
| **FTP** | Alpine 3.22 + vsftpd | Servidor de transferência de arquivos | 21, 21000-21010 |
| **Adminer** | Alpine 3.22 + PHP | Interface de gestão de base de dados | 8080 |
| **Elasticsearch** | Alpine 3.22 + ES | Motor de busca e análise | 9200, 9300 |
| **MyProfile** | Alpine 3.22 + httpd | Website estático pessoal | 8888 |

Todos os serviços são construídos a partir do **Alpine Linux 3.22** usando Dockerfiles personalizados sem imagens de aplicação pré-construídas do Docker Hub.

---

## 📋 Pré-requisitos

Antes de começar, certifique-se de ter o seguinte instalado:

- **Docker Engine** 20.10+ ([Guia de Instalação](https://docs.docker.com/engine/install/))
- **Docker Compose** 2.0+ (incluído com Docker Desktop)
- **GNU Make** 4.0+
- **Git** 2.0+

**Requisitos do Sistema:**
- RAM: 4GB mínimo (8GB recomendado)
- Espaço em Disco: 10GB livres
- SO: Linux, macOS, ou Windows com WSL2

**Verificar Instalação:**
```bash
docker --version          # Deve mostrar 20.10+
docker compose version    # Deve mostrar v2.0+
make --version           # Deve mostrar 4.0+
```

---

## 🚀 Instalação

### 1. Clonar o Repositório

```bash
git clone <repository-url>
cd Inception
```

### 2. Configurar Nome de Domínio

Adicione o domínio ao seu arquivo hosts:

**Linux/Mac:**
```bash
sudo nano /etc/hosts
# Adicione esta linha:
127.0.0.1    nmatondo.42.fr
```

**Windows (WSL2):**
```powershell
# Edite C:\Windows\System32\drivers\etc\hosts como Administrador
127.0.0.1    nmatondo.42.fr
```

### 3. Configurar Variáveis de Ambiente

Crie o arquivo `srcs/.env`:

```env
# Configuração de Domínio
DOMAIN_NAME=nmatondo.42.fr
CERT_=./requirements/nginx/tools/nmatondo.42.fr.crt
KEY_=./requirements/nginx/tools/nmatondo.42.fr.key

# Configuração de Base de Dados
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
```

### 4. Configurar Secrets

Crie o diretório `secrets/` e popule com arquivos de senha:

```bash
mkdir -p secrets

# Crie arquivos de secrets (substitua com suas senhas seguras)
echo "sua_senha_root_forte" > secrets/db_root_password.txt
echo "sua_senha_db" > secrets/db_password.txt
echo "admin:sua_senha_admin" > secrets/credentials.txt
echo "sua_senha_redis" > secrets/redis_password.txt
echo "ftpuser:sua_senha_ftp" > secrets/ftp_credentials.txt

# Proteja os secrets
chmod 600 secrets/*.txt
```

### 5. Criar Diretórios de Dados

```bash
mkdir -p /home/nmatondo/data/{mariadb,wordpress,redis,elasticsearch,myprofile}
```

---

## 🎮 Uso

### Início Rápido

**Iniciar apenas serviços obrigatórios (NGINX, WordPress, MariaDB):**
```bash
make
```

**Iniciar todos os serviços incluindo bônus:**
```bash
make bonus
```

### Comandos Disponíveis

| Comando | Descrição |
|---------|-----------|
| `make` ou `make all` | Construir e iniciar serviços obrigatórios |
| `make bonus` | Construir e iniciar todos os serviços (incluindo bônus) |
| `make build` | Construir apenas imagens Docker |
| `make up` | Iniciar containers |
| `make down` | Parar containers (preserva dados) |
| `make clean` | Parar containers e remover volumes |
| `make fclean` | Limpeza completa (containers, imagens, volumes) |
| `make logs` | Ver logs dos containers (modo follow) |
| `make restart` | Reiniciar todos os containers |
| `make status` | Mostrar status dos containers |
| `make re` | Reconstruir serviços obrigatórios do zero |
| `make bre` | Reconstruir serviços bônus do zero |

### Pontos de Acesso

Após iniciar os serviços, acesse-os via:

| Serviço | URL | Credenciais |
|---------|-----|-------------|
| **WordPress** | https://nmatondo.42.fr | Admin de secrets/credentials.txt |
| **Adminer** | http://nmatondo.42.fr:8080 | Credenciais DB de .env |
| **MyProfile** | http://nmatondo.42.fr:8888 | Nenhuma (site estático) |
| **FTP** | ftp://nmatondo.42.fr:21 | Credenciais FTP de secrets |
| **Elasticsearch** | http://nmatondo.42.fr:9200 | Nenhuma |

---

## 🏛️ Arquitetura

### Arquitetura de Rede

```
Internet
    │
    ↓
[NGINX:443] ← Ponto de Entrada TLS/HTTPS
    │
    ├─→ [WordPress:9000] ← PHP-FPM
    │        │
    │        ├─→ [MariaDB:3306] ← Base de Dados
    │        ├─→ [Redis:6379] ← Cache
    │        └─→ [Elasticsearch:9200] ← Busca
    │
    ├─→ [Adminer:8080] ← Gestão de BD
    ├─→ [FTP:21] ← Gestão de Arquivos
    └─→ [MyProfile:8888] ← Site Estático
```

### Persistência de Dados

Todos os dados dos serviços são persistidos usando volumes Docker com bind mounts para `/home/nmatondo/data/`:

- `mariadb_data` → Arquivos da base de dados
- `wordpress_data` → Arquivos WordPress e uploads
- `redis_data` → Persistência Redis
- `elasticsearch_data` → Índices Elasticsearch
- `myprofile_data` → Arquivos do website estático

### Gestão de Secrets

Dados sensíveis são geridos usando Docker secrets, armazenados no diretório `secrets/` e montados de forma segura nos containers em `/run/secrets/`.

---

## 🔧 Desenvolvimento

Para documentação detalhada de desenvolvimento, consulte [DEV_DOC.pt.md](DEV_DOC.pt.md).

### Estrutura do Projeto

```
Inception/
├── Makefile              # Automação de build
├── README.md            # Arquivo principal (inglês)
├── DEV_DOC.md          # Documentação do desenvolvedor
├── USER_DOC.md         # Guia do usuário
├── secrets/            # Credenciais sensíveis
├── srcs/
│   ├── .env            # Variáveis de ambiente
│   ├── docker-compose.yml  # Orquestração de serviços
│   └── requirements/   # Configurações dos serviços
│       ├── nginx/      # Servidor web
│       ├── wordpress/  # CMS
│       ├── mariadb/    # Base de dados
│       └── bonus/      # Serviços adicionais
└── docs/              # Documentação adicional
```

### Construir Serviços Individuais

```bash
# Construir serviço específico
docker compose -f srcs/docker-compose.yml build <nome-do-serviço>

# Exemplo: Construir apenas NGINX
docker compose -f srcs/docker-compose.yml build nginx
```

### Depuração

```bash
# Ver logs de todos os serviços
make logs

# Ver logs de serviço específico
docker compose -f srcs/docker-compose.yml logs -f mariadb

# Executar comandos dentro de um container
docker exec -it <nome-do-container> sh

# Verificar status dos containers
docker ps -a
```

---

## 📚 Documentação

- [DEV_DOC.pt.md](DEV_DOC.pt.md) - Documentação completa do desenvolvedor
- [USER_DOC.pt.md](USER_DOC.pt.md) - Guia do usuário e documentação de serviços
- [docs/](../docs/) - Guias de configuração específicos por serviço

### Documentação Específica por Serviço

- [NGINX_CONFIG.md](NGINX_CONFIG.md) - Detalhes de configuração NGINX
- [WORDPRESS_CONFIG.md](WORDPRESS_CONFIG.md) - Configuração WordPress
- [MARIADB_CONFIG.md](MARIADB_CONFIG.md) - Configuração da base de dados
- [REDIS_CONFIG.md](REDIS_CONFIG.md) - Configuração do cache Redis
- [FTP_CONFIG.md](FTP_CONFIG.md) - Configuração do servidor FTP
- [ELASTICSEARCH_CONFIG.md](ELASTICSEARCH_CONFIG.md) - Configuração Elasticsearch

---

## 🔒 Funcionalidades de Segurança

- **Criptografia TLS**: Todo o tráfego HTTP redirecionado para HTTPS com TLS 1.2/1.3
- **Gestão de Secrets**: Senhas e dados sensíveis armazenados como Docker secrets
- **Isolamento de Rede**: Serviços comunicam através de rede Docker privada
- **Superfície de Ataque Mínima**: Imagens base Alpine Linux (tamanho mínimo)
- **Health Checks**: Monitoramento automatizado de saúde dos serviços
- **Sem Processos Root**: Serviços executam como usuários não privilegiados quando possível

---

## 🐛 Resolução de Problemas

### Problemas Comuns

**Problema: Porta já em uso**
```bash
# Encontrar e matar processo usando porta 443
sudo lsof -i :443
sudo kill -9 <PID>
```

**Problema: Containers não iniciam**
```bash
# Verificar logs
make logs

# Verificar se secrets existem
ls -la secrets/

# Verificar arquivo de ambiente
cat srcs/.env
```

**Problema: Não consigo aceder aos serviços**
```bash
# Verificar domínio no arquivo hosts
cat /etc/hosts | grep nmatondo.42.fr

# Verificar status dos containers
docker ps

# Testar conectividade
curl -k https://nmatondo.42.fr
```

**Problema: Erros de conexão à base de dados**
```bash
# Verificar saúde do MariaDB
docker exec -it mariadb mysql -u root -p

# Verificar se WordPress consegue conectar
docker exec -it wordpress ping mariadb
```

---

## 📝 Licença

Este projeto faz parte do currículo da Escola 42 e destina-se a fins educacionais.

---

## 👤 Autor

**nmatondo**
- 42 Intra: nmatondo
- Projeto: Inception

---

## 🙏 Agradecimentos

- Escola 42 pelo enunciado do projeto
- Documentação Docker
- Comunidade Alpine Linux
- WordPress, NGINX, MariaDB e outros projetos open-source utilizados

---

## 📞 Suporte

Para problemas e questões:
1. Consulte [USER_DOC.pt.md](USER_DOC.pt.md) para guias do usuário
2. Reveja [DEV_DOC.pt.md](DEV_DOC.pt.md) para detalhes técnicos
3. Consulte documentação específica por serviço em [docs/](../docs/)
4. Verifique logs dos containers: `make logs`

---

## 🔍 Análise Técnica Aprofundada

### Arquitetura Docker

Este projeto aproveita a containerização **Docker** para criar uma infraestrutura isolada, reproduzível e portável. Cada serviço executa em seu próprio container, garantindo:

1. **Isolamento:** Serviços separados entre si e do sistema host
2. **Reprodutibilidade:** O mesmo ambiente pode ser recriado em qualquer lugar
3. **Eficiência de Recursos:** Containers partilham o kernel do SO host
4. **Escalabilidade:** Serviços podem ser escalados independentemente

### Princípios de Design

#### Dockerfiles Personalizados
Todos os containers são construídos a partir de Dockerfiles personalizados (sem imagens pré-construídas do Docker Hub exceto SO base). Isto proporciona:
- Controlo total sobre o processo de build
- Compreensão das dependências de cada serviço
- Segurança através de imagens base mínimas (Alpine Linux 3.22)
- Otimização para casos de uso específicos

#### Health Checks
Serviços críticos incluem health checks para garantir ordem de inicialização adequada e recuperação automática:
```yaml
healthcheck:
  test: ["CMD-SHELL", "mariadb -u root -p$$(cat /run/secrets/db_root_password) -e 'SELECT 1'"]
  interval: 10s
  timeout: 5s
  retries: 5
```

#### Solução do Problema PID 1
Cada container usa `exec` nos scripts de entrypoint para garantir que o processo principal execute como PID 1, permitindo manipulação adequada de sinais para desligamentos graciosos.

---

## 📊 Comparações Técnicas

### Máquinas Virtuais vs Containers Docker

| Aspecto | Máquinas Virtuais | Containers Docker |
|---------|------------------|-------------------|
| **Arquitetura** | SO completo com hypervisor | Partilha kernel do SO host |
| **Tamanho** | GBs (inclui SO completo) | MBs (apenas app + dependências) |
| **Tempo de Inicialização** | Minutos | Segundos |
| **Uso de Recursos** | Alto (recursos dedicados) | Baixo (kernel partilhado) |
| **Isolamento** | Completo (nível de hardware) | Nível de processo (namespaces) |
| **Portabilidade** | Limitada (dependente de hypervisor) | Alta (executa em qualquer lugar) |
| **Performance** | Overhead de virtualização | Performance quase nativa |

**Por que Docker para Inception:** Leve, inicialização rápida, fácil controlo de versão, utilização eficiente de recursos e padrão da indústria para microserviços.

### Docker Secrets vs Variáveis de Ambiente

| Aspecto | Docker Secrets | Variáveis de Ambiente |
|---------|----------------|----------------------|
| **Segurança** | Encriptado em repouso e em trânsito | Texto simples, visível em logs |
| **Armazenamento** | `/run/secrets/` (tmpfs - RAM) | Ambiente do processo |
| **Visibilidade** | Apenas acessível a serviços especificados | Visível em todo o lado |
| **Rotação** | Pode ser atualizado sem reconstruir | Requer reinício do container |
| **Melhor Para** | Senhas, chaves API, certificados | Configuração, dados não sensíveis |

**Implementação no Inception:**
```yaml
secrets:
  db_password:
    file: ../secrets/db_password.txt
```
Secrets são armazenados em RAM (`tmpfs`) e nunca escritos em disco, proporcionando segurança superior.

### Docker Volumes vs Bind Mounts

| Aspecto | Docker Volumes | Bind Mounts |
|---------|----------------|-------------|
| **Gestão** | Gerido pelo Docker | Utilizador gere caminho do host |
| **Localização** | Área de armazenamento Docker | Qualquer caminho do host |
| **Portabilidade** | Portável entre hosts | Dependente do caminho do host |
| **Performance** | Otimizado pelo Docker | Acesso direto ao sistema de arquivos |
| **Caso de Uso** | Persistência de dados de produção | Desenvolvimento, partilha de arquivos do host |

**Implementação no Inception:**
```yaml
volumes:
  wordpress_data:
    driver: local
    driver_opts:
      type: none
      o: bind
      device: /home/nmatondo/data/wordpress
```
Esta abordagem combina gestão de volumes Docker com acesso direto ao caminho do host para persistência de dados.

---

## 📚 Recursos

### Documentação Docker
- [Documentação Oficial Docker](https://docs.docker.com/)
- [Documentação Docker Compose](https://docs.docker.com/compose/)
- [Melhores Práticas Dockerfile](https://docs.docker.com/develop/develop-images/dockerfile_best-practices/)
- [Segurança Docker](https://docs.docker.com/engine/security/)
- [Redes Docker](https://docs.docker.com/network/)

### Recursos Específicos por Serviço
- [Documentação NGINX](https://nginx.org/en/docs/)
- [Documentação MariaDB](https://mariadb.com/kb/en/)
- [Recursos para Desenvolvedores WordPress](https://developer.wordpress.org/)
- [Configuração PHP-FPM](https://www.php.net/manual/en/install.fpm.php)
- [Documentação Redis](https://redis.io/documentation)
- [Documentação vsftpd](https://security.appspot.com/vsftpd.html)

---

## 🤖 Declaração de Uso de IA

A assistência de IA foi utilizada nos seguintes aspetos deste projeto:

**1. Documentação e Pesquisa:**
- Compreensão de conceitos de redes Docker
- Pesquisa de melhores práticas para Dockerfiles
- Aprendizagem da implementação de Docker secrets

**2. Resolução de Problemas:**
- Depuração de problemas de inicialização de containers
- Resolução de problemas de conectividade de rede
- Correção de problemas de permissões de volumes

**3. Otimização de Configuração:**
- Configuração de proxy reverso NGINX
- Otimização de pool PHP-FPM
- Ajuste de performance MariaDB
- Configuração de cache Redis

**4. Revisão de Código:**
- Revisão de eficiência de Dockerfiles
- Avaliação de vulnerabilidades de segurança
- Validação de scripts de entrypoint

**Nota:** Todas as sugestões geradas por IA foram revistas, testadas e adaptadas para atender aos requisitos do projeto. A implementação central e as decisões de arquitetura foram feitas com IA como assistente de aprendizagem e pesquisa.

---

**Última Atualização:** Janeiro 2026  
**Status do Projeto:** ✅ Completo e Funcional
