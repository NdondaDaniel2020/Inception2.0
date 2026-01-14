*Este projeto foi criado como parte do currículo da 42 por nmatondo.*

# Inception

## Descrição

Inception é um projeto de administração de sistemas que se concentra em containerização usando Docker. O objetivo é criar uma pequena infraestrutura composta por diferentes serviços, cada um rodando em seu próprio container dedicado. O projeto envolve a configuração de uma aplicação Docker multi-container usando Docker Compose, com ênfase em segurança, networking e persistência de dados.

A infraestrutura inclui:
- **NGINX** com TLSv1.2 ou TLSv1.3 como único ponto de entrada
- **WordPress** com PHP-FPM para gestão de conteúdo
- **MariaDB** como servidor de base de dados
- **Redis** cache para otimização do WordPress
- **Servidor FTP** para gestão de ficheiros
- **Adminer** para administração de base de dados
- **Elasticsearch** para capacidades de pesquisa
- **Website estático** (página de perfil pessoal)

Todos os serviços são construídos a partir de versões estáveis penúltimas usando Alpine ou Debian (penúltima estável) como imagens base, com Dockerfiles personalizados e sem imagens pré-construídas do Docker Hub (exceto para imagens base do SO).

---

## Instruções

### Pré-requisitos

- Docker Engine
- Docker Compose
- Make
- Um nome de domínio configurado para apontar para a sua máquina local (ex: `nmatondo.42.fr` apontando para `127.0.0.1` em `/etc/hosts`)

### Instalação

1. **Clonar o repositório:**
   ```bash
   git clone <repository-url>
   cd Inception
   ```

2. **Configurar variáveis de ambiente:**
   
   Criar um ficheiro `.env` no diretório `srcs/` com as seguintes variáveis:
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

3. **Configurar secrets:**
   
   Criar os seguintes ficheiros de secrets no diretório `secrets/`:
   - `db_root_password.txt` - Password root do MariaDB
   - `db_password.txt` - Password do utilizador da base de dados WordPress
   - `credentials.txt` - Credenciais de admin do WordPress (formato: `username:password`)
   - `redis_password.txt` - Password de autenticação do Redis
   - `ftp_password.txt` - Password do utilizador FTP

### Compilação e Execução

#### Parte Obrigatória

Construir e iniciar os serviços obrigatórios (NGINX, WordPress, MariaDB):
```bash
make
```

Ou passo a passo:
```bash
make build    # Construir imagens Docker
make up       # Iniciar containers
```

#### Serviços Bónus

Construir e iniciar todos os serviços incluindo os bónus:
```bash
make bonus
```

#### Outros Comandos

```bash
make down      # Parar todos os containers
make clean     # Parar containers e remover volumes
make fclean    # Limpeza completa (containers, imagens, volumes, redes)
make logs      # Ver logs dos containers
make restart   # Reiniciar todos os containers
make status    # Ver status dos containers
make re        # Reconstruir tudo do zero
```

### Aceder aos Serviços

Uma vez em execução, aceder aos serviços em:
- **WordPress:** https://nmatondo.42.fr
- **Adminer:** https://nmatondo.42.fr:8080
- **Servidor FTP:** ftp://nmatondo.42.fr:21
- **Website Estático:** https://nmatondo.42.fr/myprofile

---

## Descrição do Projeto

### Arquitetura Docker

Este projeto utiliza a containerização **Docker** para criar uma infraestrutura isolada, reproduzível e portável. Cada serviço executa no seu próprio container, garantindo:

1. **Isolamento:** Os serviços estão separados uns dos outros e do sistema host
2. **Reprodutibilidade:** O mesmo ambiente pode ser recriado em qualquer lugar
3. **Eficiência de Recursos:** Os containers partilham o kernel do SO host
4. **Escalabilidade:** Os serviços podem ser escalados independentemente

### Principais Escolhas de Design

#### Dockerfiles Personalizados
Todos os containers são construídos a partir de Dockerfiles personalizados (sem imagens pré-construídas do Docker Hub exceto SO base). Isto proporciona:
- Controlo total sobre o processo de build
- Compreensão das dependências de cada serviço
- Segurança através de imagens base mínimas (Alpine/Debian)
- Otimização para casos de uso específicos

#### Multi-Stage Builds
Quando aplicável, builds multi-estágio reduzem o tamanho da imagem final ao excluir dependências de build.

#### Health Checks
Os serviços incluem health checks para garantir ordem de arranque adequada e recuperação automática:
```yaml
healthcheck:
  test: ["CMD-SHELL", "mariadb -u root -p... -e 'SELECT 1'"]
  interval: 10s
  timeout: 5s
  retries: 5
```

#### Solução do Problema PID 1
Cada container usa `exec` nos scripts entrypoint para garantir que o processo principal execute como PID 1, permitindo o tratamento adequado de sinais para desligamentos graciosos.

#### Segurança em Primeiro Lugar
- Sem passwords em Dockerfiles ou ficheiros de ambiente
- Docker secrets para dados sensíveis
- Encriptação TLS/SSL para NGINX
- Utilizadores não-root sempre que possível
- Sistemas de ficheiros read-only quando aplicável

---

## Comparações Técnicas

### Máquinas Virtuais vs Docker

| Aspeto | Máquinas Virtuais | Containers Docker |
|--------|------------------|-------------------|
| **Arquitetura** | SO completo com hypervisor | Partilha kernel do SO host |
| **Tamanho** | GBs (inclui SO completo) | MBs (apenas app + dependências) |
| **Tempo de Arranque** | Minutos | Segundos |
| **Uso de Recursos** | Alto (cada VM tem recursos dedicados) | Baixo (kernel partilhado, processos isolados) |
| **Isolamento** | Completo (nível de hardware) | Nível de processo (isolamento namespace) |
| **Portabilidade** | Limitada (dependente do hypervisor) | Alta (executa em qualquer lugar com Docker) |
| **Performance** | Overhead da virtualização | Performance quase nativa |
| **Caso de Uso** | Executar SO diferentes, isolamento completo | Microserviços, deployment rápido |

**Porquê Docker para Inception:**
- Leve e arranque rápido
- Fácil de versionar (Dockerfiles)
- Utilização eficiente de recursos
- Perfeito para arquitetura de microserviços
- Padrão da indústria para desenvolvimento e deployment

### Secrets vs Variáveis de Ambiente

| Aspeto | Docker Secrets | Variáveis de Ambiente |
|--------|----------------|----------------------|
| **Segurança** | Encriptados em repouso e em trânsito | Texto plano, visível no inspect do container |
| **Armazenamento** | `/run/secrets/` (tmpfs - RAM) | Ambiente do processo |
| **Visibilidade** | Apenas acessível aos serviços especificados | Visível em logs, listas de processos |
| **Rotação** | Pode ser atualizado sem rebuild | Requer reinício do container |
| **Melhor Para** | Passwords, chaves API, certificados | Configuração, dados não sensíveis |

**Implementação no Inception:**
```yaml
secrets:
  - db_root_password
  - db_password

secrets:
  db_password:
    file: ../secrets/db_password.txt
```

Os secrets são armazenados em RAM (`tmpfs`) e nunca escritos em disco, proporcionando segurança superior para credenciais sensíveis.

### Rede Docker vs Rede Host

| Aspeto | Rede Docker (Bridge) | Rede Host |
|--------|---------------------|-----------|
| **Isolamento** | Isolamento de namespace de rede | Partilha stack de rede do host |
| **Mapeamento de Portas** | Necessário (ex: `8080:80`) | Acesso direto às portas do host |
| **Segurança** | Melhor (isolado, regras de firewall) | Menor (exposição direta do host) |
| **Performance** | Ligeiro overhead (NAT) | Sem overhead |
| **DNS** | Descoberta de serviços integrada | Configuração manual |
| **Caso de Uso** | Apps multi-container | Networking de alta performance |

**Porquê Rede Docker para Inception:**
```yaml
networks:
  network:
    driver: bridge
```
- Descoberta de serviços por nome (ex: `mariadb:3306`)
- Isolamento da rede host
- Exposição controlada de portas
- Melhor postura de segurança
- Comunicação container-to-container mais simples

### Volumes Docker vs Bind Mounts

| Aspeto | Volumes Docker | Bind Mounts |
|--------|----------------|-------------|
| **Gestão** | Gerido pelo Docker | Utilizador gere o caminho do host |
| **Localização** | Área de armazenamento Docker | Qualquer caminho do host |
| **Portabilidade** | Portável entre hosts | Dependente do caminho do host |
| **Performance** | Otimizado pelo Docker | Acesso direto ao sistema de ficheiros |
| **Backup** | Comandos de volume Docker | Ferramentas standard do sistema de ficheiros |
| **Permissões** | Docker trata | Aplicam-se permissões do host |
| **Caso de Uso** | Persistência de dados em produção | Desenvolvimento, partilha de ficheiros do host |

**Implementação no Inception:**
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

**Porquê Volumes:**
- Os dados persistem após remoção do container
- Podem ser partilhados entre containers
- Geridos pelo Docker (backup, migração)
- Desacoplados da estrutura do sistema de ficheiros do host
- Melhor para ambientes de produção

---

## Recursos

### Documentação Docker
- [Documentação Oficial Docker](https://docs.docker.com/)
- [Documentação Docker Compose](https://docs.docker.com/compose/)
- [Boas Práticas Dockerfile](https://docs.docker.com/develop/develop-images/dockerfile_best-practices/)
- [Segurança Docker](https://docs.docker.com/engine/security/)
- [Networking Docker](https://docs.docker.com/network/)

### Recursos Específicos de Serviços
- [Documentação NGINX](https://nginx.org/en/docs/)
- [Documentação MariaDB](https://mariadb.com/kb/en/)
- [Recursos para Programadores WordPress](https://developer.wordpress.org/)
- [Configuração PHP-FPM](https://www.php.net/manual/en/install.fpm.php)
- [Documentação Redis](https://redis.io/documentation)
- [Documentação vsftpd](https://security.appspot.com/vsftpd.html)

### Tutoriais e Guias
- [Docker para Iniciantes](https://docker-curriculum.com/)
- [Compreender Volumes Docker](https://docs.docker.com/storage/volumes/)
- [SSL/TLS com NGINX](https://nginx.org/en/docs/http/configuring_https_servers.html)

### Uso de IA

A assistência de IA foi utilizada nos seguintes aspetos deste projeto:

1. **Documentação e Pesquisa:**
   - Compreensão de conceitos de networking Docker
   - Pesquisa de boas práticas para Dockerfiles
   - Aprendizagem sobre implementação de Docker secrets
   - Comparação de tecnologias de virtualização

2. **Resolução de Problemas:**
   - Debug de problemas de arranque de containers
   - Resolução de problemas de dependências
   - Problemas de conectividade de rede entre containers
   - Problemas de permissões com volumes

3. **Otimização de Configuração:**
   - Configuração NGINX para reverse proxy
   - Configuração de pool PHP-FPM
   - Afinação de performance do MariaDB
   - Otimização de cache Redis

4. **Revisão de Código:**
   - Revisão de eficiência de Dockerfiles
   - Avaliação de vulnerabilidades de segurança
   - Validação de lógica de scripts entrypoint

5. **Aprendizagem e Explicação:**
   - Compreensão de orquestração de containers
   - Assimilação de conceitos de volumes vs bind mounts
   - Aprendizagem sobre health checks e dependências

**Nota:** Todas as sugestões geradas por IA foram revistas, testadas e adaptadas para garantir que cumprem os requisitos do projeto e as boas práticas. A implementação central, decisões de arquitetura e resolução de problemas foram realizadas com IA como assistente de aprendizagem e pesquisa.

---

## Estrutura do Projeto

```
.
├── Makefile                    # Automação de build e deployment
├── README.md                   # Documentação em inglês
├── README.pt.md                # Este ficheiro
├── secrets/                    # Credenciais sensíveis (não em git)
│   ├── db_password.txt
│   ├── db_root_password.txt
│   ├── credentials.txt
│   ├── redis_password.txt
│   └── ftp_password.txt
└── srcs/
    ├── .env                    # Variáveis de ambiente
    ├── docker-compose.yml      # Orquestração de serviços
    └── requirements/
        ├── mariadb/            # Serviço de base de dados
        ├── nginx/              # Servidor web e reverse proxy
        ├── wordpress/          # Aplicação CMS
        └── bonus/
            ├── adminer/        # Interface de admin de BD
            ├── elasticsearch/  # Motor de pesquisa
            ├── ftp/            # Serviço de transferência de ficheiros
            ├── myprofile/      # Website estático
            └── redis/          # Serviço de cache
```

---

## Licença

Este projeto faz parte do currículo da escola 42 e destina-se a fins educacionais.
