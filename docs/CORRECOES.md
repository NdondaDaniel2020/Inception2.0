# 📚 CORREÇÕES DO PROJETO INCEPTION

## 🎯 Objetivo
Este documento descreve todas as correções aplicadas ao projeto Inception para resolver problemas de configuração, segurança e funcionalidade.

---

## 🔧 CORREÇÕES IMPLEMENTADAS

### 1️⃣ **Makefile Completo** ✅
**Arquivo:** `Makefile`

**Problema:** Arquivo vazio, sem comandos para gerenciar o projeto.

**Solução:** Criado Makefile completo com os seguintes comandos:
- `make` ou `make all` - Build e inicia os containers
- `make build` - Apenas constrói as imagens Docker
- `make up` - Inicia os containers
- `make down` - Para os containers
- `make clean` - Para containers e remove volumes
- `make fclean` - Limpeza total (containers, imagens, volumes, networks)
- `make re` - Rebuild completo (fclean + all)
- `make logs` - Visualiza logs dos containers
- `make restart` - Reinicia os containers
- `make status` - Mostra status dos containers

---

### 2️⃣ **wp-config.php Dinâmico** ✅
**Arquivo:** `srcs/requirements/wordpress/organic/wp-config-docker.php`

**Problema:** 
- Credenciais hardcoded no `wp-config.php`
- Não utilizava as variáveis de ambiente do Docker
- DB_HOST apontava para `localhost` ao invés de `mariadb`

**Solução:**
- Criado `wp-config-docker.php` que usa variáveis de ambiente:
  ```php
  define( 'DB_NAME', getenv('WORDPRESS_DB_NAME') ?: 'organic' );
  define( 'DB_USER', getenv('WORDPRESS_DB_USER') ?: 'nmatondo' );
  define( 'DB_PASSWORD', getenv('WORDPRESS_DB_PASSWORD') ?: '' );
  define( 'DB_HOST', getenv('WORDPRESS_DB_HOST') ?: 'mariadb' );
  ```
- O entrypoint substitui o wp-config.php pelo dinâmico na inicialização

---

### 3️⃣ **Script de Inicialização MariaDB** ✅
**Arquivo:** `srcs/requirements/mariadb/tools/init_db.sh`

**Problema:** 
- MariaDB não criava automaticamente usuário e database
- Não aplicava as senhas dos secrets

**Solução:**
- Criado script que:
  - Lê senhas dos Docker secrets
  - Configura senha do root
  - Cria database `organic` se não existir
  - Cria usuário `nmatondo` com senha do secret
  - Aplica permissões corretas
- Dockerfile atualizado para copiar e executar o script

---

### 4️⃣ **WordPress Entrypoint Melhorado** ✅
**Arquivo:** `srcs/requirements/wordpress/tools/entrypoint.sh`

**Problema:**
- Script antigo apenas exportava senha
- Não aguardava MariaDB estar pronto
- Não instalava WordPress automaticamente
- Não criava usuário admin

**Solução:**
- Script completo que:
  1. **Aguarda MariaDB** estar pronto com retry automático
  2. **Substitui wp-config.php** pelo dinâmico
  3. **Verifica se WordPress já está instalado**
  4. **Instala WordPress via WP-CLI** se necessário:
     - Lê credenciais admin de `/run/secrets/credentials`
     - Configura site com domínio correto
     - Cria usuário admin automaticamente
  5. **Ajusta permissões** corretas
  6. **Inicia PHP-FPM**

---

### 5️⃣ **Nginx com FastCGI** ✅
**Arquivo:** `srcs/requirements/nginx/conf/nginx.conf`

**Problema:**
- Faltava configuração FastCGI para processar PHP
- `server_name` era `localhost` ao invés do domínio
- Protocolo SSL antigo (apenas TLSv1.2)

**Solução:**
- Atualizado `server_name` para `nmatondo.42.fr`
- Adicionado TLSv1.3 aos protocolos SSL
- Configurado bloco `location ~ \.php$`:
  ```nginx
  location ~ \.php$ {
      try_files $uri =404;
      fastcgi_pass wordpress:9000;
      fastcgi_index index.php;
      include fastcgi_params;
      fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
  }
  ```
- Melhorado `location /` para suportar permalinks WordPress

---

### 6️⃣ **Arquivo .env Completo** ✅
**Arquivo:** `srcs/.env`

**Problema:**
- Variáveis incompletas
- `MYSQL_USER=XXXXXXXXXXXX` (placeholder)

**Solução:**
```env
# Domain Configuration
DOMAIN_NAME=nmatondo.42.fr

# MariaDB Configuration
MARIADB_DATABASE=organic
MARIADB_USER=nmatondo

# WordPress Configuration
WORDPRESS_DB_HOST=mariadb:3306
WORDPRESS_DB_NAME=organic
WORDPRESS_DB_USER=nmatondo

# WordPress Admin
WP_ADMIN_USER=nmatondo
```

---

### 7️⃣ **Certificados SSL Corretos** ✅
**Arquivo:** `srcs/requirements/nginx/tools/generate_certificates.sh`

**Problema:**
- CN (Common Name) era `localhost`
- Informações incompletas no certificado

**Solução:**
- Atualizado subject do certificado:
  ```bash
  -subj "/C=AO/ST=Luanda/L=Luanda/O=42Luanda/OU=Inception/CN=nmatondo.42.fr"
  ```
- Adicionado mensagem de confirmação

---

### 8️⃣ **Dockerfiles Atualizados** ✅

#### **MariaDB Dockerfile:**
- Adicionado cópia do script `init_db.sh`
- Script tornado executável

#### **WordPress Dockerfile:**
- **Instalado WP-CLI** para gerenciamento WordPress
- Adicionado pacotes: `curl`, `less`
- Alterado CMD para `ENTRYPOINT` apontando para `entrypoint.sh`

---

### 9️⃣ **Docker Compose Atualizado** ✅
**Arquivo:** `srcs/docker-compose.yml`

**Mudanças:**
1. **WordPress service:**
   - Adicionado variável `DOMAIN_NAME`
   - Adicionado secret `credentials` para credenciais admin
   - Removido secret `db_root_password` (não necessário)

2. **Secrets:**
   - Adicionado `credentials` apontando para `../secrets/credentials.txt`

---

## 📂 ARQUIVOS DE SECRETS

### `secrets/db_root_password.txt`
Senha do usuário root do MariaDB:
```
nmatondo@student.42luanda.com
```

### `secrets/db_password.txt`
Senha do usuário `nmatondo` no MariaDB:
```
nmatondo@student.42luanda.com
```

### `secrets/credentials.txt`
Credenciais do admin WordPress (linha 1: usuário, linha 2: senha):
```
nmatondo
nmatondo@student.42luanda.com
```

---

## 🔐 CREDENCIAIS FINAIS

### **MariaDB:**
- **Root:** 
  - Usuário: `root`
  - Senha: `nmatondo@student.42luanda.com`
- **Usuário Normal:**
  - Usuário: `nmatondo`
  - Senha: `nmatondo@student.42luanda.com`
  - Database: `organic`

### **WordPress:**
- **Admin:**
  - Usuário: `nmatondo`
  - Senha: `nmatondo@student.42luanda.com`
  - URL: `https://nmatondo.42.fr`

---

## 🚀 COMO USAR

### 1. **Configurar hosts** (necessário para acessar nmatondo.42.fr)
#### Windows:
Editar `C:\Windows\System32\drivers\etc\hosts`:
```
127.0.0.1  nmatondo.42.fr
```

#### Linux/Mac:
Editar `/etc/hosts`:
```
127.0.0.1  nmatondo.42.fr
```

### 2. **Iniciar o projeto:**
```bash
make
```

### 3. **Acessar WordPress:**
- Site: `https://nmatondo.42.fr`
- Admin: `https://nmatondo.42.fr/wp-admin`

### 4. **Comandos úteis:**
```bash
make logs      # Ver logs
make status    # Ver status dos containers
make restart   # Reiniciar
make re        # Rebuild completo
```

---

## 📊 RESUMO DAS MELHORIAS

| Área | Antes | Depois |
|------|-------|--------|
| **Makefile** | ❌ Vazio | ✅ Completo com 9 comandos |
| **wp-config.php** | ❌ Hardcoded | ✅ Dinâmico (env vars) |
| **MariaDB Init** | ❌ Manual | ✅ Automático via script |
| **WordPress Install** | ❌ Manual | ✅ Automático via WP-CLI |
| **Nginx FastCGI** | ❌ Faltando | ✅ Configurado |
| **SSL Cert** | ❌ CN=localhost | ✅ CN=nmatondo.42.fr |
| **.env** | ❌ Incompleto | ✅ Todas variáveis |
| **Secrets** | ⚠️ Parcial | ✅ Todos configurados |

---

## ✅ CHECKLIST DE VERIFICAÇÃO

- [x] Makefile funcional
- [x] Credenciais via Docker secrets
- [x] wp-config.php dinâmico
- [x] MariaDB auto-configurado
- [x] WordPress auto-instalado
- [x] Nginx servindo PHP via FastCGI
- [x] SSL com domínio correto
- [x] Variáveis de ambiente completas
- [x] Documentação completa

---

## 🛠️ PRÓXIMOS PASSOS (OPCIONAL)

1. **Implementar volumes persistentes** em diretórios específicos
2. **Adicionar serviços bonus** (Redis, FTP, etc.)
3. **Melhorar segurança** com senhas diferentes para root e usuário
4. **Adicionar healthchecks** nos services do docker-compose
5. **Implementar backup automático** dos dados

---

**Autor:** GitHub Copilot  
**Data:** Janeiro 2026  
**Projeto:** Inception - 42 Luanda
