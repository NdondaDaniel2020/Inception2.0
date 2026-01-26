# Configuração FTP - Documentação Técnica

Este documento fornece uma explicação detalhada de todas as configurações FTP utilizadas no projeto Inception, incluindo o servidor vsftpd, configuração de usuários, permissões e integração com WordPress.
**Versão utilizada:** vsftpd latest em Alpine Linux 3.22
---

## Índice

1. [Introdução ao FTP](#introdução-ao-ftp)
2. [Configuração vsftpd.conf](#configuração-vsftpdconf)
3. [Script Entrypoint](#script-entrypoint)
4. [Integração com WordPress](#integração-com-wordpress)
5. [Monitorização e Debug](#monitorização-e-debug)
6. [Referências](#referências)

---

## Introdução ao FTP

### O que é FTP?

**FTP (File Transfer Protocol)** é um protocolo padrão para transferência de arquivos entre computadores em uma rede.

### vsftpd (Very Secure FTP Daemon)

**vsftpd** é um servidor FTP seguro e leve para sistemas Unix/Linux.

**Características principais:**
- ✅ Seguro (Very Secure)
- ✅ Leve e rápido
- ✅ Suporte a IPv4/IPv6
- ✅ Modos ativo/passivo
- ✅ Chroot (jail) para usuários
- ✅ Controle de permissões granular

### Uso no WordPress

```
Desenvolvedor → FTP → vsftpd → /var/www/html
                                    ├─ wp-content/
                                    ├─ wp-admin/
                                    ├─ wp-includes/
                                    └─ ...
```

**Cenários de uso:**
- Upload de temas/plugins
- Edição de arquivos WordPress
- Backup/restore de arquivos
- Desenvolvimento colaborativo

### Modos FTP

#### Modo Ativo

```
Cliente (porta aleatória) → Servidor FTP (porta 21)
Cliente ← Servidor FTP (porta 20)
```

**Problemas:**
- Firewall bloqueia conexões entrantes
- NAT/PAT complicam
- Menos usado hoje

#### Modo Passivo (Usado no Projeto)

```
Cliente → Servidor FTP (porta 21)
Cliente ← Servidor FTP (porta 21000-21010)
```

**Vantagens:**
- Cliente inicia todas as conexões
- Funciona melhor com firewalls
- Suportado por todos os clientes FTP

---

## Configuração vsftpd.conf

**Ficheiro:** `srcs/requirements/bonus/ftp/conf/vsftpd.conf`

### Configuração Base

#### listen

```conf
listen=YES
```

**Explicação Detalhada:**

- **Diretiva:** `listen`
- **Valor:** `YES`
- **Descrição:** Faz vsftpd escutar em modo standalone (não via inetd/xinetd)

**Modos de execução:**

| Modo | Descrição | Uso |
|------|-----------|-----|
| `listen=YES` | Standalone (independente) | **Docker** ✅ |
| `listen=NO` | Via inetd/xinetd | Servidores tradicionais |

**Por que standalone no Docker?**
- Container precisa de processo principal
- Não há inetd/xinetd no container Alpine
- vsftpd roda como PID 1

**Alternativa (não usada):**
```conf
listen=NO
# Requer inetd/xinetd configurado
```

---

#### listen_ipv6

```conf
listen_ipv6=NO
```

**Explicação Detalhada:**

- **Diretiva:** `listen_ipv6`
- **Valor:** `NO`
- **Descrição:** Desabilita escuta IPv6

**Protocolos:**

| Configuração | IPv4 | IPv6 | Recomendado |
|--------------|------|------|-------------|
| `listen_ipv6=YES` | ❌ | ✅ | Servidores modernos |
| `listen_ipv6=NO` | ✅ | ❌ | **Docker simples** ✅ |

**Por que desabilitar IPv6?**
- Ambiente Docker usa IPv4 internamente
- Simplifica configuração
- Evita problemas de resolução DNS IPv6
- Menos portas abertas

**Quando habilitar IPv6:**
```conf
listen_ipv6=YES
listen=NO  # IPv6 usa listen_ipv6
```

---

#### anonymous_enable

```conf
anonymous_enable=NO
```

**Explicação Detalhada:**

- **Diretiva:** `anonymous_enable`
- **Valor:** `NO`
- **Descrição:** Desabilita login anônimo

**Tipos de acesso:**

| Tipo | Descrição | Segurança | Uso |
|------|-----------|-----------|-----|
| Anônimo | Sem senha | ❌ Inseguro | Downloads públicos |
| Local | Usuário sistema | ⚠️ Cuidado | **Desenvolvimento** ✅ |
| Virtual | Usuários virtuais | ✅ Seguro | Produção |

**Por que desabilitar anônimo?**
- WordPress precisa de autenticação
- Previne acesso não autorizado
- Segue princípio "least privilege"

**Configuração para anônimo (não usada):**
```conf
anonymous_enable=YES
anon_root=/var/ftp/pub
```

---

#### local_enable

```conf
local_enable=YES
```

**Explicação Detalhada:**

- **Diretiva:** `local_enable`
- **Valor:** `YES`
- **Descrição:** Permite login de usuários locais do sistema

**Usuários locais:**
- Criados com `adduser` ou `useradd`
- Têm home directory
- Podem ter senha
- Acesso ao sistema de arquivos

**No nosso projeto:**
- Usuário FTP criado dinamicamente
- Home directory: `/var/www/html`
- Acesso aos arquivos WordPress

**Alternativa: usuários virtuais**
```conf
local_enable=NO
guest_enable=YES
virtual_use_local_privs=YES
```

---

#### write_enable

```conf
write_enable=YES
```

**Explicação Detalhada:**

- **Diretiva:** `write_enable`
- **Valor:** `YES`
- **Descrição:** Permite operações de escrita (upload, mkdir, delete)

**Operações permitidas:**

| Comando FTP | Descrição | write_enable |
|-------------|-----------|--------------|
| STOR | Upload arquivo | ✅ YES |
| STOU | Upload único | ✅ YES |
| APPE | Append arquivo | ✅ YES |
| DELE | Delete arquivo | ✅ YES |
| RMD | Remove diretório | ✅ YES |
| MKD | Make diretório | ✅ YES |
| RNFR/RNTO | Rename | ✅ YES |

**Para read-only:**
```conf
write_enable=NO
# Apenas download permitido
```

**Nosso caso:** YES (desenvolvimento WordPress precisa upload)

---

#### local_umask

```conf
local_umask=022
```

**Explicação Detalhada:**

- **Diretiva:** `local_umask`
- **Valor:** `022`
- **Descrição:** Máscara de permissões para arquivos criados por usuários locais

**Como funciona umask:**

**Cálculo de permissões:**
```
Permissões padrão: 666 (arquivos) / 777 (diretórios)
Menos umask:     -022
Resultado:       644 (arquivos) / 755 (diretórios)
```

**Explicação detalhada:**

| Componente | Binário | Decimal | Significado |
|------------|---------|----------|-------------|
| **u** (user) | 0 | 0 | Owner: read+write |
| **g** (group) | 2 | 2 | Group: read only |
| **o** (other) | 2 | 2 | Others: read only |

**Para arquivos:**
```
666 (rw-rw-rw-) - 022 = 644 (rw-r--r--)
```

**Para diretórios:**
```
777 (rwxrwxrwx) - 022 = 755 (rwxr-xr-x)
```

**Valores comuns:**

| umask | Arquivos | Diretórios | Uso |
|-------|----------|------------|-----|
| 000 | 666 | 777 | Compartilhamento total |
| 002 | 664 | 775 | Grupo pode escrever |
| **022** | **644** | **755** | **Padrão web** ✅ |
| 027 | 640 | 750 | Grupo restrito |
| 077 | 600 | 700 | Privado |

**Por que 022 para WordPress?**
- Arquivos: 644 (web server lê)
- Diretórios: 755 (web server navega)
- Segurança adequada para desenvolvimento

---

### Chroot (Jail)

#### chroot_local_user

```conf
chroot_local_user=YES
```

**Explicação Detalhada:**

- **Diretiva:** `chroot_local_user`
- **Valor:** `YES`
- **Descrição:** Prende usuários locais em seu home directory

**O que é chroot?**
- **Change Root:** Muda o diretório raiz do processo
- Usuário vê `/` como seu home directory
- Não pode acessar arquivos acima

**Sem chroot:**
```
/ (root filesystem)
├── etc/
├── home/user/
├── var/www/html/  ← Usuário acessa tudo
└── ...
```

**Com chroot:**
```
Usuário vê:
/ (aponta para /var/www/html)
├── wp-content/
├── wp-admin/
└── ... (apenas arquivos WordPress)
```

**Segurança:**
- ✅ Usuário não acessa `/etc/passwd`
- ✅ Não pode instalar software
- ✅ Limitado a arquivos WordPress
- ✅ Previne exploração de vulnerabilidades

**Problema comum:**
```
500 OOPS: vsftpd: refusing to run with writable root inside chroot
```

**Solução:** `allow_writeable_chroot=YES`

---

#### allow_writeable_chroot

```conf
allow_writeable_chroot=YES
```

**Explicação Detalhada:**

- **Diretiva:** `allow_writeable_chroot`
- **Valor:** `YES`
- **Descrição:** Permite chroot em diretórios graváveis

**Por que necessário?**

**vsftpd por segurança:**
- Não permite chroot em diretórios graváveis
- Previne ataques onde usuário modifica root jail

**Mas no WordPress:**
- `/var/www/html` precisa ser gravável
- Usuário FTP precisa escrever arquivos
- Temas/plugins são instalados

**Sem esta configuração:**
```
Login FTP → Erro 500: "refusing to run with writable root"
```

**Com `allow_writeable_chroot=YES`:**
```
Login FTP → Chroot funciona mesmo com diretório gravável ✅
```

**Riscos:**
- ⚠️ Usuário pode tentar escapar da jail
- ✅ Mas com permissões corretas, seguro
- ✅ Melhor que desabilitar chroot completamente

---

### Passive Mode

#### pasv_enable

```conf
pasv_enable=YES
```

**Explicação Detalhada:**

- **Diretiva:** `pasv_enable`
- **Valor:** `YES`
- **Descrição:** Habilita modo passivo FTP

**Modo passivo explicado:**

**Handshake:**
```
Cliente → Servidor: PASV
Cliente ← Servidor: 227 Entering Passive Mode (172,18,0,4,82,136)
```

**Cálculo da porta:**
```
21000 + (82 * 256) + 136 = 21000 + 21056 + 136 = 42192? Não...

Correto: (82 * 256) + 136 = 21056 + 136 = 21192
```

**Conexão de dados:**
```
Cliente (porta aleatória) → Servidor (porta 21192)
```

**Por que passivo?**
- ✅ Funciona com NAT/firewalls
- ✅ Cliente inicia conexão de dados
- ✅ Padrão moderno

---

#### pasv_min_port / pasv_max_port

```conf
pasv_min_port=21000
pasv_max_port=21010
```

**Explicação Detalhada:**

- **Diretiva:** `pasv_min_port` / `pasv_max_port`
- **Valor:** 21000-21010
- **Descrição:** Range de portas para conexões de dados passivas

**Como funciona:**

**Cliente solicita PASV:**
```
Cliente: PASV
Servidor: 227 Entering Passive Mode (172,18,0,4,82,136)
```

**Porta calculada:**
```
82 * 256 + 136 = 21192
Porta está entre 21000-21010? Sim ✅
```

**Range de 11 portas:**
- 21000, 21001, 21002, ..., 21010
- Suficiente para múltiplos clientes
- Evita conflito com outras portas

**Configuração firewall:**
```bash
# Abrir range no firewall
iptables -A INPUT -p tcp --dport 21000:21010 -j ACCEPT
```

**Por que este range?**
- Acima da porta 1024 (não privilegiada)
- Não conflita com portas comuns (80, 443, 3306, etc.)
- Fácil de identificar como FTP

---

### Segurança

#### seccomp_sandbox

```conf
seccomp_sandbox=NO
```

**Explicação Detalhada:**

- **Diretiva:** `seccomp_sandbox`
- **Valor:** `NO`
- **Descrição:** Desabilita sandbox seccomp

**O que é seccomp?**
- **Secure Computing Mode:** Restringe syscalls disponíveis
- Sandbox adicional de segurança
- Previne exploits de kernel

**Por que desabilitar?**
- Alpine Linux pode ter problemas de compatibilidade
- Alguns syscalls necessários não disponíveis
- vsftpd pode falhar ao iniciar

**Quando habilitar:**
```conf
seccomp_sandbox=YES
# Em sistemas com suporte completo
```

**Alternativas de segurança:**
- ✅ Chroot ativo
- ✅ Usuários não-root
- ✅ Permissões corretas
- ✅ Firewall

---

### Logging

#### xferlog_enable

```conf
xferlog_enable=YES
```

**Explicação Detalhada:**

- **Diretiva:** `xferlog_enable`
- **Valor:** `YES`
- **Descrição:** Habilita logging de transferências

**Logs gerados:**
```
/var/log/vsftpd.log
```

**Formato de log:**
```
Mon Jan 13 10:30:15 2025 1 172.18.0.3 0 /var/www/html/wp-content/themes/twentytwentyone/style.css b _ o r user ftp 0 * c
```

**Campos:**
- Data/hora
- Transferência ID
- IP cliente
- Bytes transferidos
- Caminho arquivo
- Tipo (ascii/binary)
- Tipo operação (upload/download)
- Usuário
- etc.

**Para desabilitar:**
```conf
xferlog_enable=NO
```

**Arquivo de log customizado:**
```conf
xferlog_file=/var/log/ftp.log
```

---

## Script Entrypoint

**Ficheiro:** `srcs/requirements/bonus/ftp/tools/entrypoint.sh`

### Script Completo Anotado

```bash
#!/bin/sh

# Cria usuário FTP se não existir
if ! id -u ${FTP_USER} >/dev/null 2>&1; then
    adduser -D -h /var/www/html ${FTP_USER}
    FTP_PASSWORD=$(cat /run/secrets/ftp_credentials)
    echo "${FTP_USER}:${FTP_PASSWORD}" | chpasswd
fi

# Ajusta permissões do diretório WordPress
chown -R ${FTP_USER}:${FTP_USER} /var/www/html
chmod -R 755 /var/www/html

# Inicia vsftpd
exec vsftpd /etc/vsftpd/vsftpd.conf
```

### Explicação Linha por Linha

#### Shebang

```bash
#!/bin/sh
```

- **Interpretador:** POSIX shell (`/bin/sh`)
- **Alpine Linux:** Usa BusyBox sh
- **Compatibilidade:** Funciona em qualquer Unix-like

#### Verificação de Usuário Existente

```bash
if ! id -u ${FTP_USER} >/dev/null 2>&1; then
```

**Decompondo:**

**`id -u ${FTP_USER}`:**
- `id`: Comando para informações do usuário
- `-u`: Mostra apenas UID (User ID)
- `${FTP_USER}`: Variável de ambiente (ex: "ftpuser")

**`>/dev/null 2>&1`:**
- `>/dev/null`: Descarta stdout
- `2>&1`: Redireciona stderr para stdout
- Resultado: Comando silencioso

**`!`:**
- Negação: executa se comando falhar

**Lógica:**
```
Se usuário ${FTP_USER} NÃO existir:
    Então cria o usuário
Senão:
    Pula criação (usuário já existe)
```

**Por que verificar?**
- Container pode ser restartado
- Usuário persiste entre restarts
- Evita erro "user already exists"

#### Criação do Usuário

```bash
adduser -D -h /var/www/html ${FTP_USER}
```

**Comando `adduser` (Alpine):**

**`-D`:**
- Don't assign password (não pede senha interativa)
- Cria usuário sem senha inicialmente

**`-h /var/www/html`:**
- Home directory do usuário
- Onde chroot irá prender o usuário

**`${FTP_USER}`:**
- Nome do usuário (ex: "ftpuser")

**Resultado:**
- Usuário criado
- UID/GID atribuídos automaticamente
- Home directory: `/var/www/html`
- Sem senha ainda

#### Leitura da Senha

```bash
FTP_PASSWORD=$(cat /run/secrets/ftp_credentials)
```

**Docker Secrets:**
- `/run/secrets/ftp_credentials`: Arquivo com senha
- Montado como tmpfs (RAM)
- Seguro (não persiste em disco)

**Conteúdo típico:**
```
/run/secrets/ftp_credentials:
minha_senha_ftp_super_secreta_123
```

**Variável:**
```bash
FTP_PASSWORD="minha_senha_ftp_super_secreta_123"
```

#### Configuração da Senha

```bash
echo "${FTP_USER}:${FTP_PASSWORD}" | chpasswd
```

**`chpasswd`:**
- Comando para alterar senhas em lote
- Lê formato "usuario:senha" da stdin

**Pipeline:**
```
echo "ftpuser:minha_senha_ftp_super_secreta_123" | chpasswd
```

**Resultado:**
- Senha do usuário ${FTP_USER} definida
- Hash armazenado em `/etc/shadow`

**Segurança:**
- ✅ Senha não aparece em logs
- ✅ Hash criptografado
- ✅ Docker secret não exposto

#### Ajuste de Permissões

```bash
chown -R ${FTP_USER}:${FTP_USER} /var/www/html
```

**`chown`:**
- Change owner (muda dono)
- `-R`: Recursivo (todos os arquivos/subdiretórios)

**`${FTP_USER}:${FTP_USER}`:**
- Owner: ftpuser
- Group: ftpuser

**`/var/www/html`:**
- Diretório WordPress
- Arquivos precisam pertencer ao usuário FTP

**Por que necessário?**
- Arquivos WordPress copiados do volume
- Podem ter owner diferente (root, www-data)
- FTP precisa escrever neles

#### Configuração de Permissões

```bash
chmod -R 755 /var/www/html
```

**`chmod`:**
- Change mode (muda permissões)
- `-R`: Recursivo

**`755`:**
- Owner: rwx (7)
- Group: r-x (5)
- Other: r-x (5)

**Equivalente:**
```
-rwxr-xr-x arquivos
drwxr-xr-x diretórios
```

**Por que 755?**
- ✅ Web server (nginx/php) pode ler
- ✅ FTP pode escrever
- ✅ Segurança adequada

#### Inicialização do vsftpd

```bash
exec vsftpd /etc/vsftpd/vsftpd.conf
```

**`exec`:**
- Substitui processo shell por vsftpd
- vsftpd torna-se PID 1 do container

**Por que `exec`?**
- Sinais (SIGTERM) vão diretamente para vsftpd
- Shutdown gracioso
- Container para corretamente

**`/etc/vsftpd/vsftpd.conf`:**
- Caminho do arquivo de configuração

---

## Integração com WordPress

### Cliente FTP

**Credenciais:**
- **Host:** `ftp` (nome do serviço Docker)
- **Port:** `21`
- **Usuário:** `${FTP_USER}` (ex: "ftpuser")
- **Password:** Conteúdo de `secrets/ftp_credentials.txt`
- **Diretório inicial:** `/` (chroot para `/var/www/html`)

### Operações Comuns

#### Upload de Tema

```
Cliente FTP → Conecta ao ftp:21
           → Autentica com ftpuser/senha
           → Upload tema.zip para /wp-content/themes/
           → Extrai arquivos
           → Tema instalado ✅
```

#### Edição de Arquivo

```
Cliente FTP → Conecta
           → Navega para /wp-config.php
           → Download arquivo
           → Edita localmente
           → Upload modificado
           → Arquivo atualizado ✅
```

### Segurança

**Isolamento:**
- ✅ Chroot ativo
- ✅ Usuário não-root
- ✅ Permissões 755
- ✅ Apenas diretório WordPress

**Acesso:**
- ✅ Autenticação obrigatória
- ✅ Senha via Docker secrets
- ✅ Não exposto externamente (apenas rede Docker)

### Docker Compose

```yaml
ftp:
  build: ./srcs/requirements/bonus/ftp
  environment:
    - FTP_USER=ftpuser
  secrets:
    - ftp_credentials
  volumes:
    - wordpress_data:/var/www/html
  networks:
    - inception
```

---

## Como Usar o FTP - Guia Prático

### Login no Servidor FTP

#### Método 1: Cliente FTP Nativo (Linux/Mac)

**Conexão básica:**
```bash
ftp nmatondo.42.fr 21
```

**Saída esperada:**
```
Connected to nmatondo.42.fr.
220 Welcome to vsftpd FTP service.
Name (nmatondo.42.fr:user): ftpuser
331 Please specify the password.
Password: [digite a senha]
230 Login successful.
Remote system type is UNIX.
Using binary mode to transfer files.
ftp>
```

**Conexão com credenciais inline:**
```bash
ftp -n nmatondo.42.fr 21 << EOF
user ftpuser sua_senha
EOF
```

#### Método 2: lftp (Recomendado)

**Instalação:**
```bash
# Debian/Ubuntu
sudo apt install lftp

# Alpine/Docker
apk add lftp

# macOS
brew install lftp
```

**Conexão:**
```bash
lftp -u ftpuser,sua_senha ftp://nmatondo.42.fr
```

**Ou interativo:**
```bash
lftp
lftp> open nmatondo.42.fr
lftp nmatondo.42.fr> user ftpuser
Password: [digite a senha]
lftp ftpuser@nmatondo.42.fr:~>
```

**Conexão com prompt de senha:**
```bash
lftp -u ftpuser ftp://nmatondo.42.fr
# Pedirá a senha de forma segura
```

#### Método 3: FileZilla (GUI)

**Configuração:**
1. Abrir FileZilla
2. Arquivo → Gestor de Sites → Novo Site
3. Configurar:
   - **Protocolo:** FTP - File Transfer Protocol
   - **Host:** nmatondo.42.fr
   - **Porta:** 21
   - **Criptografia:** Usar FTP simples (inseguro)
   - **Tipo de autenticação:** Normal
   - **Utilizador:** ftpuser
   - **Senha:** [sua senha do secrets/ftp_credentials.txt]
4. Conectar

**Conexão rápida:**
- Host: `nmatondo.42.fr`
- Utilizador: `ftpuser`
- Senha: `[senha]`
- Porta: `21`
- Clicar "Ligação Rápida"

#### Método 4: Dentro do Container Docker

**Conectar do host para o container:**
```bash
# Primeiro, entrar no container WordPress
docker exec -it wordpress sh

# Instalar cliente FTP
apk add lftp

# Conectar ao FTP
lftp -u ftpuser,senha ftp://ftp
```

**Testar conexão:**
```bash
# Verificar se porta 21 está aberta
nc -zv nmatondo.42.fr 21

# Telnet para teste manual
telnet nmatondo.42.fr 21
```

---

### Operações CRUD com FTP

#### CREATE (Criar/Upload)

##### Upload de Arquivo Único

**Cliente FTP nativo:**
```bash
ftp nmatondo.42.fr
ftp> user ftpuser
Password: ****
ftp> cd wp-content/themes
ftp> put meu-tema.zip
local: meu-tema.zip remote: meu-tema.zip
227 Entering Passive Mode (172,18,0,4,82,8)
150 Ok to send data.
226 Transfer complete.
15432 bytes sent in 0.05 secs (308.64 KB/s)
ftp> bye
```

**lftp (mais moderno):**
```bash
lftp -u ftpuser,senha ftp://nmatondo.42.fr
lftp> cd wp-content/themes
lftp> put meu-tema.zip
lftp> bye
```

**lftp one-liner:**
```bash
lftp -u ftpuser,senha -e "cd wp-content/themes; put meu-tema.zip; bye" ftp://nmatondo.42.fr
```

##### Upload de Múltiplos Arquivos

**Cliente FTP nativo:**
```bash
ftp> mput *.php
mput index.php? y
mput functions.php? y
mput style.css? y
```

**lftp (mais eficiente):**
```bash
lftp> mput *.php
```

**lftp com confirmação automática:**
```bash
lftp> set confirm:yes no
lftp> mput *.php
```

##### Upload de Diretório Completo

**lftp (mirror upload):**
```bash
lftp> mirror -R meu-tema wp-content/themes/meu-tema
```

**Explicação:**
- `-R`: Reverse (upload, não download)
- `meu-tema`: Diretório local
- `wp-content/themes/meu-tema`: Diretório remoto

**Com exclusões:**
```bash
lftp> mirror -R --exclude .git/ --exclude node_modules/ meu-tema wp-content/themes/meu-tema
```

##### Criar Diretório

**Cliente FTP nativo:**
```bash
ftp> mkdir wp-content/uploads/2026
257 "/wp-content/uploads/2026" created.
```

**lftp:**
```bash
lftp> mkdir wp-content/uploads/2026
```

**Criar estrutura de diretórios:**
```bash
lftp> mkdir -p wp-content/uploads/2026/01
```

##### Upload com Modo Binário vs ASCII

**Modo Binário (padrão, recomendado):**
```bash
ftp> binary
200 Switching to Binary mode.
ftp> put imagem.jpg
```

**Modo ASCII (apenas para arquivos de texto):**
```bash
ftp> ascii
200 Switching to ASCII mode.
ftp> put readme.txt
```

---

#### READ (Ler/Download)

##### Download de Arquivo Único

**Cliente FTP nativo:**
```bash
ftp> get wp-config.php
local: wp-config.php remote: wp-config.php
227 Entering Passive Mode (172,18,0,4,82,8)
150 Opening BINARY mode data connection for wp-config.php (2853 bytes).
226 Transfer complete.
2853 bytes received in 0.02 secs (142.65 KB/s)
```

**lftp:**
```bash
lftp> get wp-config.php
```

**lftp com renomeação:**
```bash
lftp> get wp-config.php -o wp-config-backup.php
```

##### Download de Múltiplos Arquivos

**Cliente FTP nativo:**
```bash
ftp> mget *.php
mget index.php? y
mget functions.php? y
```

**lftp:**
```bash
lftp> mget *.php
```

**lftp com padrão:**
```bash
lftp> mget wp-content/themes/twentytwentyone/*.css
```

##### Download de Diretório Completo

**lftp (mirror download):**
```bash
lftp> mirror wp-content/themes/meu-tema backup-tema
```

**Explicação:**
- Sem `-R`: Download (padrão)
- `wp-content/themes/meu-tema`: Diretório remoto
- `backup-tema`: Diretório local

**Mirror com opções:**
```bash
lftp> mirror --verbose --parallel=4 wp-content/uploads backup-uploads
```

**Opções úteis:**
- `--verbose`: Mostra progresso
- `--parallel=4`: 4 downloads simultâneos
- `--only-newer`: Apenas arquivos novos
- `--delete`: Apaga arquivos locais que não existem remotamente

##### Listar Arquivos (Read Listing)

**Cliente FTP nativo:**
```bash
ftp> ls
227 Entering Passive Mode (172,18,0,4,82,8)
150 Here comes the directory listing.
drwxr-xr-x    5 ftpuser  ftpuser      4096 Jan 13 10:30 wp-admin
drwxr-xr-x    2 ftpuser  ftpuser      4096 Jan 13 10:30 wp-content
drwxr-xr-x    3 ftpuser  ftpuser      4096 Jan 13 10:30 wp-includes
-rw-r--r--    1 ftpuser  ftpuser      2853 Jan 13 10:30 wp-config.php
226 Directory send OK.
```

**Listar detalhado:**
```bash
ftp> dir
```

**lftp (mais legível):**
```bash
lftp> ls
lftp> ls -la  # Detalhado incluindo ocultos
lftp> cls     # Apenas nomes (clean list)
```

**Listar recursivo:**
```bash
lftp> find
# Ou
lftp> du -h  # Com tamanhos
```

##### Ver Conteúdo de Arquivo Remoto

**lftp:**
```bash
lftp> cat wp-config.php
# Mostra conteúdo do arquivo

lftp> more wp-config.php
# Paginado

lftp> less wp-config.php
# Navegável
```

##### Verificar Tamanho de Arquivo

**Cliente FTP nativo:**
```bash
ftp> size wp-config.php
213 2853
```

**lftp:**
```bash
lftp> du -h wp-config.php
2.8K    wp-config.php
```

---

#### UPDATE (Atualizar/Modificar)

##### Sobrescrever Arquivo Existente

**Cliente FTP nativo:**
```bash
ftp> put wp-config.php
local: wp-config.php remote: wp-config.php
227 Entering Passive Mode (172,18,0,4,82,8)
150 Ok to send data.
226 Transfer complete.
2900 bytes sent in 0.02 secs (145.00 KB/s)
```

**lftp (sobrescreve automaticamente):**
```bash
lftp> put wp-config.php
```

**lftp com backup antes de sobrescrever:**
```bash
lftp> !cp wp-config.php wp-config-backup.php  # Backup local
lftp> get wp-config.php -o wp-config-remote-backup.php  # Backup remoto
lftp> put wp-config.php  # Sobrescreve
```

##### Renomear Arquivo (Atualizar Nome)

**Cliente FTP nativo:**
```bash
ftp> rename antigo.php novo.php
350 Ready for RNTO.
250 Rename successful.
```

**lftp:**
```bash
lftp> rename antigo.php novo.php
# Ou
lftp> mv antigo.php novo.php
```

##### Mover Arquivo (Atualizar Localização)

**Cliente FTP nativo (não suporta diretamente):**
```bash
# Workaround: download e upload
ftp> get arquivo.php
ftp> cd novo-diretorio
ftp> put arquivo.php
ftp> cd ..
ftp> delete arquivo.php
```

**lftp:**
```bash
lftp> mv wp-content/themes/arquivo.php wp-content/plugins/arquivo.php
```

##### Modificar Permissões (Update Permissions)

**Cliente FTP nativo:**
```bash
ftp> chmod 644 wp-config.php
200 SITE CHMOD command ok.
```

**lftp:**
```bash
lftp> chmod 644 wp-config.php
lftp> chmod 755 wp-content/uploads
```

**Recursivo (lftp):**
```bash
lftp> find wp-content/uploads -type f -exec chmod 644 {} \;
lftp> find wp-content/uploads -type d -exec chmod 755 {} \;
```

##### Sincronizar Arquivos (Update Incremental)

**lftp mirror bidirecional:**
```bash
# Upload apenas arquivos novos/modificados
lftp> mirror -R --only-newer meu-tema wp-content/themes/meu-tema

# Download apenas arquivos novos/modificados
lftp> mirror --only-newer wp-content/uploads backup-uploads
```

**Com exclusões:**
```bash
lftp> mirror -R --only-newer --exclude .git/ --exclude .DS_Store meu-tema wp-content/themes/meu-tema
```

---

#### DELETE (Apagar)

##### Apagar Arquivo Único

**Cliente FTP nativo:**
```bash
ftp> delete arquivo-teste.txt
250 Delete operation successful.
```

**lftp:**
```bash
lftp> rm arquivo-teste.txt
```

**Com confirmação:**
```bash
lftp> rm -i arquivo-teste.txt
rm ok, `arquivo-teste.txt'? (yes/no) yes
```

##### Apagar Múltiplos Arquivos

**Cliente FTP nativo:**
```bash
ftp> mdelete *.tmp
mdelete cache-1.tmp? y
mdelete cache-2.tmp? y
```

**lftp:**
```bash
lftp> mrm *.tmp
# Ou
lftp> rm *.tmp
```

**lftp com padrão glob:**
```bash
lftp> rm wp-content/uploads/2025/*.tmp
```

##### Apagar Diretório Vazio

**Cliente FTP nativo:**
```bash
ftp> rmdir diretorio-vazio
250 Remove directory operation successful.
```

**lftp:**
```bash
lftp> rmdir diretorio-vazio
```

##### Apagar Diretório com Conteúdo

**Cliente FTP nativo (não suporta diretamente):**
```bash
# Não há comando nativo para apagar diretório recursivamente
# Precisa apagar arquivos primeiro, depois diretórios
```

**lftp (recomendado):**
```bash
lftp> rm -r diretorio-completo
```

**Com confirmação:**
```bash
lftp> rm -ri diretorio-completo
```

**Mirror com delete (sincronização destrutiva):**
```bash
lftp> mirror -R --delete meu-tema wp-content/themes/meu-tema
```
- Apaga arquivos remotos que não existem localmente

##### Apagar Tudo de um Diretório

**lftp:**
```bash
lftp> cd wp-content/cache
lftp> rm -r *
```

**Cuidado extremo:**
```bash
# PERIGOSO - Apaga TUDO do diretório atual
lftp> glob rm -r *
```

---

### Operações Avançadas

#### Transferência Retomável (Resume)

**lftp (suporta resume automático):**
```bash
lftp> get -c arquivo-grande.zip
# -c = continue (retoma download interrompido)

lftp> put -c arquivo-grande.zip
# Retoma upload interrompido
```

#### Transferências em Background

**lftp:**
```bash
lftp> get arquivo-grande.zip &
# Transfere em background

lftp> jobs
# Lista jobs em background

lftp> wait
# Aguarda conclusão de todos os jobs
```

#### Transferências Paralelas

**lftp mirror paralelo:**
```bash
lftp> mirror --parallel=4 wp-content/uploads backup-uploads
# 4 downloads simultâneos
```

#### Script Batch FTP

**Criar arquivo de comandos:**
```bash
cat > ftp-commands.txt << EOF
user ftpuser senha
binary
cd wp-content/uploads
put imagem1.jpg
put imagem2.jpg
put imagem3.jpg
bye
EOF
```

**Executar script:**
```bash
ftp -n nmatondo.42.fr < ftp-commands.txt
```

**lftp script:**
```bash
cat > ftp-script.lftp << EOF
open -u ftpuser,senha ftp://nmatondo.42.fr
cd wp-content/uploads
mput *.jpg
bye
EOF

lftp -f ftp-script.lftp
```

#### Navegação Rápida

**lftp:**
```bash
lftp> cd wp-content/themes/meu-tema
lftp> lcd ~/projetos/meu-tema  # Local cd
lftp> pwd                       # Diretório remoto atual
lftp> lpwd                      # Diretório local atual
```

**Bookmarks:**
```bash
lftp> bookmark add tema-dir
lftp> bookmark list
lftp> bookmark tema-dir
```

---

### Exemplos Práticos WordPress

#### 1. Instalar Tema WordPress via FTP

```bash
# Preparar tema
cd ~/Downloads
unzip meu-tema.zip

# Conectar e fazer upload
lftp -u ftpuser,senha ftp://nmatondo.42.fr

# Upload do tema
lftp> mirror -R meu-tema wp-content/themes/meu-tema

# Verificar
lftp> ls wp-content/themes/meu-tema

# Ajustar permissões
lftp> chmod -R 755 wp-content/themes/meu-tema

lftp> bye
```

#### 2. Backup de wp-content

```bash
lftp -u ftpuser,senha ftp://nmatondo.42.fr

# Download completo de wp-content
lftp> mirror wp-content backup-wp-content-$(date +%Y%m%d)

# Ou apenas uploads
lftp> mirror wp-content/uploads backup-uploads

lftp> bye
```

#### 3. Editar wp-config.php

```bash
lftp -u ftpuser,senha ftp://nmatondo.42.fr

# Backup do arquivo original
lftp> get wp-config.php -o wp-config-backup.php

# Download para editar
lftp> get wp-config.php

# Editar localmente com seu editor favorito
lftp> !nano wp-config.php

# Upload do arquivo modificado
lftp> put wp-config.php

lftp> bye
```

#### 4. Limpar Cache WordPress

```bash
lftp -u ftpuser,senha ftp://nmatondo.42.fr

# Apagar todos os arquivos de cache
lftp> cd wp-content/cache
lftp> rm -r *

# Ou específicos
lftp> rm wp-content/cache/*.tmp

lftp> bye
```

#### 5. Atualizar Plugin Manualmente

```bash
# Download do plugin
wget https://downloads.wordpress.org/plugin/meu-plugin.zip
unzip meu-plugin.zip

# Conectar FTP
lftp -u ftpuser,senha ftp://nmatondo.42.fr

# Backup do plugin antigo
lftp> mirror wp-content/plugins/meu-plugin backup-meu-plugin

# Apagar plugin antigo
lftp> rm -r wp-content/plugins/meu-plugin

# Upload plugin novo
lftp> mirror -R meu-plugin wp-content/plugins/meu-plugin

lftp> bye
```

#### 6. Upload de Múltiplas Imagens

```bash
lftp -u ftpuser,senha ftp://nmatondo.42.fr

# Criar diretório do mês
lftp> mkdir -p wp-content/uploads/2026/01

# Navegar até lá
lftp> cd wp-content/uploads/2026/01

# Upload de todas as imagens
lftp> lcd ~/imagens
lftp> mput *.jpg *.png

lftp> bye
```

---

### Dicas de Segurança

#### 1. Não Armazenar Senhas em Arquivos de Texto

**Ruim:**
```bash
lftp -u ftpuser,minha_senha_123 ftp://nmatondo.42.fr
```

**Melhor (prompt de senha):**
```bash
lftp -u ftpuser ftp://nmatondo.42.fr
# Pedirá senha interativamente
```

**Ou usar variável de ambiente:**
```bash
export FTP_PASSWORD=$(cat ../secrets/ftp_credentials.txt)
lftp -u ftpuser,$FTP_PASSWORD ftp://nmatondo.42.fr
```

#### 2. Usar Conexões do Ambiente Docker

**Do container WordPress:**
```bash
docker exec -it wordpress sh
apk add lftp
lftp -u ftpuser,senha ftp://ftp
# Tráfego não sai da rede Docker
```

#### 3. Verificar Permissões Após Upload

```bash
lftp> ls -la wp-config.php
-rw-r--r--  1 ftpuser ftpuser  2853 Jan 13 10:30 wp-config.php

# Se necessário, ajustar
lftp> chmod 644 wp-config.php
```

#### 4. Sempre Fazer Backup Antes de Modificar

```bash
# Antes de qualquer operação destrutiva
lftp> get arquivo-importante.php -o arquivo-importante.backup.php
# Ou
lftp> mirror wp-content backup-$(date +%Y%m%d)
```

---

### Comparação de Clientes FTP

| Recurso | ftp nativo | lftp | FileZilla |
|---------|-----------|------|-----------|
| **Interface** | CLI | CLI | GUI |
| **Resume** | ❌ | ✅ | ✅ |
| **Mirror** | ❌ | ✅ | ✅ (sync) |
| **Paralelo** | ❌ | ✅ | ✅ |
| **Scripting** | Básico | Avançado | ❌ |
| **Facilidade** | Difícil | Médio | Fácil |
| **Automação** | ⚠️ | ✅ | ❌ |

**Recomendação:**
- **Iniciantes:** FileZilla (GUI intuitivo)
- **Desenvolvedores:** lftp (poderoso, scriptável)
- **Scripts/CI/CD:** lftp (automação)

---

## Monitorização e Debug

### Verificar Status

**Container rodando:**
```bash
docker ps | grep ftp
```

**Logs:**
```bash
docker logs ftp
```

**Conexões ativas:**
```bash
docker exec ftp netstat -tlnp | grep :21
```

### Teste de Conexão

**Via cliente FTP:**
```bash
ftp ftp 21
Name: ftpuser
Password: [senha]
ftp> ls
```

**Via lftp:**
```bash
lftp -u ftpuser,senha ftp://ftp
lftp> ls
```

### Troubleshooting

**Erro: "Connection refused"**
```
Causa: vsftpd não iniciou
Solução: docker logs ftp
```

**Erro: "Login incorrect"**
```
Causa: Senha incorreta
Solução: Verificar secrets/ftp_credentials.txt
```

**Erro: "Permission denied"**
```
Causa: Permissões incorretas
Solução: chown/chmod no entrypoint
```

**Erro: "500 OOPS: vsftpd: refusing to run with writable root"**
```
Causa: allow_writeable_chroot=NO
Solução: allow_writeable_chroot=YES
```

### Logs de Transferência

**Ver logs:**
```bash
docker exec ftp tail -f /var/log/vsftpd.log
```

**Exemplo de log:**
```
Mon Jan 13 10:30:15 2025 1 172.18.0.3 15432 /var/www/html/wp-content/themes/style.css b _ o r ftpuser ftp 0 * c
```

---

## Referências

- [vsftpd Documentation](https://security.appspot.com/vsftpd.html)
- [FTP Protocol RFC 959](https://tools.ietf.org/html/rfc959)
- [Docker Secrets](https://docs.docker.com/engine/swarm/secrets/)
- [WordPress FTP Setup](https://wordpress.org/support/article/ftp-clients/)

---

**Última atualização:** Janeiro 2026  
**Versão:** 1.0  
**Autor:** Projeto Inception - 42 School