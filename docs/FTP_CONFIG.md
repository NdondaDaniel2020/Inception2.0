# Configuração FTP - Documentação Técnica

Este documento fornece uma explicação detalhada de todas as configurações FTP utilizadas no projeto Inception, incluindo o servidor vsftpd, configuração de usuários, permissões e integração com WordPress.

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