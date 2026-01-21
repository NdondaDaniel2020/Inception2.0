# Configuração do WordPress - Documentação Técnica

Este documento explica em detalhe todas as configurações e parâmetros utilizados nos ficheiros de configuração do WordPress e serviços relacionados no projeto Inception.

---

## Índice

1. [PHP-FPM Configuration (www.conf)](#php-fpm-configuration-wwwconf)
2. [WordPress Entrypoint Script](#wordpress-entrypoint-script)
3. [NGINX SSL Certificate Generation](#nginx-ssl-certificate-generation)
4. [Docker Secrets](#docker-secrets)
5. [Integração com Elasticsearch](#integração-com-elasticsearch)

---

## PHP-FPM Configuration (www.conf)

**Ficheiro:** `srcs/requirements/wordpress/conf/www.conf`

### Configuração Completa

```ini
[www]
user = www-data
group = www-data

listen = 9000

listen.owner = www-data
listen.group = www-data

pm = dynamic
pm.max_children = 10
pm.start_servers = 2
pm.min_spare_servers = 1
pm.max_spare_servers = 3

clear_env = no
```

### Explicação Detalhada de Cada Parâmetro

#### Pool Name
```ini
[www]
```
- **Descrição:** Nome do pool de processos PHP-FPM
- **Valor:** `www` (pool padrão)
- **Função:** Identifica este conjunto de configurações como o pool "www"
- **Nota:** Podem existir múltiplos pools com diferentes configurações

---

#### User and Group Settings

```ini
user = www-data
group = www-data
```
- **`user`**: Utilizador do sistema que executará os processos PHP-FPM
- **`group`**: Grupo do sistema associado aos processos
- **Valor:** `www-data` (utilizador padrão do servidor web em sistemas Debian/Alpine)
- **Função:** Define as permissões com que o PHP executará
- **Segurança:** Usar utilizador não-root aumenta a segurança
- **Compatibilidade:** `www-data` é compatível com NGINX e Apache

---

#### Listen Configuration

```ini
listen = 9000
```
- **Descrição:** Porta ou socket onde PHP-FPM escuta conexões
- **Valor:** `9000` (porta TCP)
- **Alternativas:** 
  - Porta: `9000` ou `127.0.0.1:9000`
  - Socket Unix: `/var/run/php-fpm.sock`
- **Escolha:** Porta TCP permite comunicação entre containers Docker
- **NGINX:** NGINX conecta-se a `wordpress:9000` via rede Docker

```ini
listen.owner = www-data
listen.group = www-data
```
- **`listen.owner`**: Proprietário do socket/porta
- **`listen.group`**: Grupo do socket/porta
- **Função:** Define permissões de acesso ao endpoint PHP-FPM
- **Importante:** Deve corresponder ao utilizador do NGINX para comunicação via socket Unix
- **Nota:** Em portas TCP (9000), estes parâmetros têm menos impacto

---

#### Process Manager Configuration

```ini
pm = dynamic
```
- **Descrição:** Modo de gestão de processos filhos
- **Valor:** `dynamic` (dinâmico)
- **Modos Disponíveis:**
  - **`static`**: Número fixo de processos (sempre `pm.max_children`)
  - **`dynamic`**: Número variável baseado na carga (recomendado)
  - **`ondemand`**: Processos criados sob demanda (mais lento)
- **Vantagens do Dynamic:**
  - Ajusta-se automaticamente à carga
  - Economiza recursos em baixa carga
  - Responde rapidamente a picos de tráfego

```ini
pm.max_children = 10
```
- **Descrição:** Número máximo de processos filhos simultâneos
- **Valor:** `10` processos
- **Cálculo Recomendado:**
  ```
  pm.max_children = (RAM_disponível) / (RAM_por_processo)
  Exemplo: 1GB / 100MB = 10 processos
  ```
- **Impacto:**
  - Muito baixo: Requests em fila, lentidão
  - Muito alto: Consumo excessivo de RAM, OOM (Out of Memory)
- **Recomendação:** Ajustar baseado em monitorização real

```ini
pm.start_servers = 2
```
- **Descrição:** Número de processos filhos criados no arranque
- **Valor:** `2` processos
- **Requisito:** Deve estar entre `min_spare_servers` e `max_spare_servers`
- **Fórmula Comum:**
  ```
  pm.start_servers = min_spare_servers + (max_spare_servers - min_spare_servers) / 2
  pm.start_servers = 1 + (3 - 1) / 2 = 2
  ```
- **Objetivo:** Ter processos prontos imediatamente após o arranque

```ini
pm.min_spare_servers = 1
```
- **Descrição:** Número mínimo de processos ociosos (spare) a manter
- **Valor:** `1` processo
- **Função:** Garantir processos disponíveis para novas requisições
- **Comportamento:** Se cair abaixo deste valor, novos processos são criados
- **Ambiente de Desenvolvimento:** 1 é suficiente para baixo tráfego

```ini
pm.max_spare_servers = 3
```
- **Descrição:** Número máximo de processos ociosos permitidos
- **Valor:** `3` processos
- **Função:** Evitar excesso de processos inativos consumindo recursos
- **Comportamento:** Se exceder este valor, processos extras são terminados
- **Relação:** Deve ser maior ou igual a `pm.min_spare_servers`

---

#### Environment Variables

```ini
clear_env = no
```
- **Descrição:** Limpar ou preservar variáveis de ambiente
- **Valor:** `no` (não limpar)
- **Opções:**
  - `yes`: Limpa todas as variáveis de ambiente (padrão seguro)
  - `no`: Preserva variáveis de ambiente do sistema
- **Uso no Inception:** 
  - Necessário `no` para que variáveis Docker (`.env`) sejam acessíveis no PHP
  - Permite acesso a `WORDPRESS_DB_HOST`, `DOMAIN_NAME`, etc.
- **Segurança:** Em produção, considerar `yes` e passar variáveis explicitamente via `env[]`

---

### Sumário de Recursos

Com a configuração atual:
- **Processos no Arranque:** 2
- **Processos Mínimos Ociosos:** 1
- **Processos Máximos Ociosos:** 3
- **Processos Máximos Totais:** 10
- **Consumo de RAM Estimado:** 200MB - 1GB (dependendo da carga)

---

## WordPress Entrypoint Script

**Ficheiro:** `srcs/requirements/wordpress/tools/entrypoint.sh`

### Estrutura do Script

O script executa as seguintes etapas em ordem:

1. Validação do WP-CLI
2. Leitura de Docker Secrets
3. Criação do `wp-config.php`
4. Espera pelo MariaDB
5. Instalação do WordPress
6. Configuração do Elasticsearch (opcional)
7. Ajuste de permissões
8. Inicialização do PHP-FPM

### Explicação Detalhada

#### Shebang e Error Handling

```bash
#!/bin/sh
set -e
```

- **`#!/bin/sh`**: Interpretador do script (shell POSIX)
  - Alpine Linux usa BusyBox `sh` (mais leve que bash)
  - Compatível com scripts básicos
  
- **`set -e`**: Modo de erro rigoroso
  - Script termina imediatamente se qualquer comando falhar (exit code ≠ 0)
  - Previne execução de comandos subsequentes após erro
  - **Exceções:** Comandos em condicionais (`if`, `while`) ou com `|| true`

---

#### Validação do WP-CLI

```bash
if ! command -v wp >/dev/null 2>&1; then
    echo "❌ wp-cli não está instalado"
    exit 1
fi
```

**Explicação por Componente:**
- **`command -v wp`**: Verifica se o comando `wp` existe no PATH
- **`>/dev/null 2>&1`**: Redireciona output e erros para o "lixo"
  - `>` redireciona stdout
  - `2>&1` redireciona stderr para onde stdout vai
- **`! ...`**: Negação - verdadeiro se comando NÃO existir
- **`exit 1`**: Termina script com código de erro 1

**Objetivo:** Garantir que WP-CLI está instalado antes de prosseguir

---

#### Leitura de Docker Secrets

```bash
if [ -f /run/secrets/db_password ]; then
    export WORDPRESS_DB_PASSWORD="$(cat /run/secrets/db_password)"
else
    echo "❌ Secret db_password não encontrado"
    exit 1
fi
```

**Explicação por Componente:**
- **`/run/secrets/db_password`**: Caminho onde Docker monta secrets
  - `/run/secrets/` é um `tmpfs` (sistema de ficheiros em RAM)
  - Secrets nunca são escritos em disco
  - Apenas containers com permissão conseguem ler
  
- **`[ -f /run/secrets/db_password ]`**: Testa se ficheiro existe e é regular
  - `-f`: Verdadeiro se é um ficheiro regular
  - Alternativas: `-d` (diretório), `-e` (existe)

- **`export WORDPRESS_DB_PASSWORD="$(cat /run/secrets/db_password)"`**:
  - `$(cat ...)`: Command substitution - executa comando e retorna output
  - `cat`: Lê conteúdo do ficheiro
  - `export`: Torna variável disponível para processos filhos
  - **Resultado:** Password da BD disponível como variável de ambiente

**Docker Secrets vs Environment Variables:**
| Aspeto | Docker Secrets | Environment Variables |
|--------|----------------|----------------------|
| **Armazenamento** | tmpfs (RAM) | Processo / ficheiro |
| **Visibilidade** | Ficheiro protegido | `docker inspect`, logs |
| **Rotação** | Sem rebuild | Requer restart |
| **Segurança** | ✅ Alta | ⚠️ Moderada |

---

#### Criação do wp-config.php

```bash
if [ -f /var/www/html/wp-config-docker.php ]; then
    echo "📝 Gerando wp-config.php..."
    cp -f /var/www/html/wp-config-docker.php /var/www/html/wp-config.php
    
    if [ -n "$REDIS_PASSWORD" ]; then
        sed -i "s/REDIS_PASSWORD_PLACEHOLDER/$REDIS_PASSWORD/g" /var/www/html/wp-config.php
    fi
fi
```

**Explicação por Componente:**
- **`wp-config-docker.php`**: Template de configuração personalizado
  - Contém configuração específica para Docker
  - Placeholders para valores dinâmicos

- **`cp -f`**: Copia ficheiro forçadamente
  - `-f`: Force - sobrescreve se já existir

- **`[ -n "$REDIS_PASSWORD" ]`**: Testa se variável não está vazia
  - `-n`: Verdadeiro se string tem comprimento > 0
  - Oposto: `-z` (string vazia)

- **`sed -i "s/REDIS_PASSWORD_PLACEHOLDER/$REDIS_PASSWORD/g"`**:
  - `sed`: Stream editor para transformar texto
  - `-i`: In-place - edita ficheiro diretamente
  - `s/old/new/g`: Substitui "old" por "new" globalmente
  - **Função:** Substitui placeholder pela password real do Redis

**Porque não usar variáveis de ambiente no wp-config.php?**
- WordPress não acessa `$_ENV` em todos os contextos
- Ficheiro `wp-config.php` é o método padrão e confiável
- Permite customizações complexas (constantes, lógica condicional)

---

#### Espera pelo MariaDB

```bash
DB_HOST=$(echo "$WORDPRESS_DB_HOST" | cut -d: -f1)
DB_PORT=$(echo "$WORDPRESS_DB_HOST" | cut -d: -f2 -s)
DB_PORT=${DB_PORT:-3306}
```

**Explicação por Componente:**
- **`echo "$WORDPRESS_DB_HOST" | cut -d: -f1`**:
  - `echo`: Imprime valor da variável
  - `|`: Pipe - passa output para próximo comando
  - `cut -d: -f1`: Corta string usando `:` como delimitador, retorna campo 1
  - **Exemplo:** `mariadb:3306` → `mariadb`

- **`cut -d: -f2 -s`**:
  - `-f2`: Campo 2 (depois do `:`)
  - `-s`: Suprime linhas sem delimitador
  - **Exemplo:** `mariadb:3306` → `3306` | `mariadb` → ` ` (vazio)

- **`${DB_PORT:-3306}`**: Parameter expansion com valor padrão
  - Se `DB_PORT` está vazio ou unset, usa `3306`
  - **Sintaxe:** `${variavel:-valor_padrao}`

```bash
until mariadb \
    --skip-ssl \
    -h"$DB_HOST" \
    -P"$DB_PORT" \
    -u"$WORDPRESS_DB_USER" \
    -p"$WORDPRESS_DB_PASSWORD" \
    -e "SELECT 1" >/dev/null 2>&1
do
    echo "   MariaDB indisponível - aguardando..."
    sleep 3
done
```

**Explicação por Componente:**
- **`until ... do ... done`**: Loop que executa até comando ter sucesso
  - Oposto de `while`: executa enquanto comando FALHA
  - Termina quando comando retorna exit code 0

- **`mariadb`**: Cliente CLI do MariaDB
  - **`--skip-ssl`**: Desativa verificação SSL (desnecessário em rede interna)
  - **`-h`**: Host (hostname ou IP)
  - **`-P`**: Porta (maiúscula para porta, minúscula `-p` é password)
  - **`-u`**: Utilizador
  - **`-p`**: Password (sem espaço entre `-p` e password!)
  - **`-e "SELECT 1"`**: Executa query e sai
    - Query simples para testar conectividade
    - Retorna `1` se BD está funcional

- **`sleep 3`**: Pausa execução por 3 segundos
  - Evita sobrecarga do CPU com tentativas contínuas
  - Dá tempo ao MariaDB para inicializar

**Porque não usar `depends_on` apenas?**
- `depends_on` apenas garante que container INICIA
- Não garante que serviço está PRONTO para conexões
- MariaDB precisa tempo para:
  - Inicializar sistema de ficheiros
  - Carregar bases de dados
  - Abrir socket de rede
- **Solução:** Health check + wait loop

---

#### Instalação do WordPress

```bash
if wp core is-installed --allow-root --path=/var/www/html >/dev/null 2>&1; then
    echo "ℹ️ WordPress já instalado"
else
    # Instalação...
fi
```

**Explicação por Componente:**
- **`wp core is-installed`**: Comando WP-CLI que verifica instalação
  - Retorna exit code 0 se instalado
  - Retorna exit code 1 se não instalado
  
- **`--allow-root`**: Permite execução como root
  - WP-CLI normalmente bloqueia execução como root (segurança)
  - Em containers, processo principal é PID 1 (root)
  - Flag necessária em ambiente Docker

- **`--path=/var/www/html`**: Caminho da instalação WordPress
  - Especifica onde WordPress está instalado
  - Evita ambiguidade se múltiplas instalações existirem

```bash
if [ -f /run/secrets/credentials ]; then
    WP_ADMIN_USER="$(sed -n '1p' /run/secrets/credentials)"
    WP_ADMIN_PASS="$(sed -n '2p' /run/secrets/credentials)"
    WP_USER="$(sed -n '3p' /run/secrets/credentials)"
    WP_PASS="$(sed -n '4p' /run/secrets/credentials)"
fi
```

**Explicação por Componente:**
- **`sed -n '1p'`**: Extrai linha específica de ficheiro
  - `sed`: Stream editor
  - `-n`: Modo silencioso (não imprime automaticamente)
  - `'1p'`: Imprime (`p`) linha 1
  - Alternativas: `'2p'` (linha 2), `'3,5p'` (linhas 3-5)

**Formato do ficheiro credentials:**
```
admin
senha_admin_segura
usuario_normal
senha_usuario
```
- Linha 1: Username admin
- Linha 2: Password admin
- Linha 3: Username utilizador normal
- Linha 4: Password utilizador normal

```bash
wp core install \
    --allow-root \
    --path=/var/www/html \
    --url="${DOMAIN_NAME:-localhost}" \
    --title="Inception WordPress" \
    --admin_user="$WP_ADMIN_USER" \
    --admin_password="$WP_ADMIN_PASS" \
    --admin_email="${WP_ADMIN_USER}@student.42.fr" \
    --skip-email
```

**Explicação por Componente:**
- **`wp core install`**: Instala WordPress via CLI
- **`--url`**: URL do site
  - Usado em links gerados pelo WordPress
  - `${DOMAIN_NAME:-localhost}`: Usa variável ou "localhost" como padrão
- **`--title`**: Título do site (aparece no `<title>` e admin)
- **`--admin_user`**: Username do administrador
- **`--admin_password`**: Password do administrador
- **`--admin_email`**: Email do administrador
- **`--skip-email`**: Não envia email de confirmação
  - Útil em ambientes sem servidor SMTP configurado

```bash
wp user create "$WP_USER" "$WP_USER@student.42.fr" \
    --role=author \
    --user_pass="$WP_PASS" \
    --allow-root \
    --path=/var/www/html
```

**Explicação por Componente:**
- **`wp user create`**: Cria novo utilizador WordPress
- **Argumentos posicionais:**
  1. `$WP_USER`: Username
  2. `$WP_USER@student.42.fr`: Email
- **`--role=author`**: Papel/função do utilizador
  - **Roles disponíveis:**
    - `administrator`: Acesso total
    - `editor`: Publica e gere posts
    - `author`: Publica próprios posts
    - `contributor`: Escreve posts (não publica)
    - `subscriber`: Apenas lê conteúdo
- **`--user_pass`**: Define password do utilizador

---

#### Aguardar e Configurar Elasticsearch

```bash
ES_HOST=$(echo "$ELASTICSEARCH_HOST" | cut -d: -f1)
ES_PORT=$(echo "$ELASTICSEARCH_HOST" | cut -d: -f2)
ES_PORT=${ES_PORT:-9200}
```

**Explicação:** Similar à extração de host/porta do MariaDB

```bash
MAX_RETRIES=30
RETRY_COUNT=0

until curl -s "http://${ES_HOST}:${ES_PORT}/_cluster/health" >/dev/null 2>&1 || [ $RETRY_COUNT -ge $MAX_RETRIES ]; do
    echo "   Elasticsearch indisponível - aguardando... ($((RETRY_COUNT+1))/$MAX_RETRIES)"
    RETRY_COUNT=$((RETRY_COUNT+1))
    sleep 2
done
```

**Explicação por Componente:**
- **`curl -s`**: Cliente HTTP para testar API
  - `-s`: Silent mode (sem progress bar)
  - `/_cluster/health`: Endpoint de health check do Elasticsearch
  
- **`|| [ $RETRY_COUNT -ge $MAX_RETRIES ]`**: Condição de escape
  - `||`: OR lógico
  - `-ge`: Greater or Equal (maior ou igual)
  - **Função:** Sai do loop se tentativas excederem limite
  - Previne loop infinito se Elasticsearch nunca iniciar

- **`$((RETRY_COUNT+1))`**: Aritmética em shell
  - `$((...))`: Expansão aritmética
  - Incrementa contador

```bash
wp plugin install elasticpress --activate --allow-root --path=/var/www/html
```

**Explicação por Componente:**
- **`wp plugin install`**: Instala plugin do repositório WordPress.org
- **`elasticpress`**: Nome do plugin
- **`--activate`**: Ativa plugin imediatamente após instalação

```bash
wp elasticpress set-host "http://${ELASTICSEARCH_HOST}" --allow-root --path=/var/www/html
```

**Explicação:**
- **`wp elasticpress`**: Comandos específicos do plugin ElasticPress
- **`set-host`**: Define URL do servidor Elasticsearch
- **Formato:** `http://elasticsearch:9200`

```bash
wp elasticpress activate-feature search --allow-root --path=/var/www/html
```

**Features do ElasticPress:**
- **`search`**: Pesquisa melhorada
- **`related_posts`**: Posts relacionados
- **`facets`**: Filtros de pesquisa
- **`searchordering`**: Ordenação personalizada de resultados
- **`autosuggest`**: Sugestões automáticas durante digitação

```bash
wp elasticpress index --setup --allow-root --path=/var/www/html
```

**Explicação:**
- **`index`**: Indexa conteúdo no Elasticsearch
- **`--setup`**: Cria índices necessários antes de indexar
- **Processo:**
  1. Cria mapeamento de índices no Elasticsearch
  2. Indexa todos os posts, páginas, utilizadores
  3. Mantém sincronização automática após mudanças

---

#### Ajuste de Permissões

```bash
chown -R www-data:www-data /var/www/html
```

**Explicação por Componente:**
- **`chown`**: Change owner - altera proprietário de ficheiros
- **`-R`**: Recursive - aplica a todos os ficheiros e subdiretórios
- **`www-data:www-data`**: `utilizador:grupo`
- **Objetivo:** 
  - PHP-FPM executa como `www-data`
  - WordPress precisa escrever ficheiros (uploads, cache, plugins)
  - Permissões corretas previnem erros "Permission Denied"

---

#### Inicialização do PHP-FPM

```bash
exec php-fpm83 -F
```

**Explicação por Componente:**
- **`exec`**: Substitui processo atual pelo comando
  - Shell script (PID 1) é substituído por `php-fpm83`
  - PHP-FPM torna-se PID 1 do container
  - **Vantagens:**
    - Sinais (SIGTERM, SIGKILL) vão diretamente para PHP-FPM
    - Shutdown gracioso funciona corretamente
    - Sem processos zombie

- **`php-fpm83`**: Binário do PHP-FPM versão 8.3
  - Versão específica instalada no Alpine

- **`-F`**: Foreground mode
  - PHP-FPM fica em primeiro plano (não daemoniza)
  - **Essencial em Docker:** Container para quando processo principal termina
  - Logs vão para stdout (visíveis via `docker logs`)

---

## NGINX SSL Certificate Generation

**Ficheiro:** `srcs/requirements/nginx/tools/generate_certificates.sh`

### Script Completo

```bash
#!/bin/sh

SSL_DIR=/etc/nginx/ssl

mkdir -p $SSL_DIR

openssl req -x509 -nodes -days 365 \
    -newkey rsa:2048 \
    -keyout $SSL_DIR/privkey.pem \
    -out $SSL_DIR/fullchain.pem \
    -subj "/C=AO/ST=Luanda/L=Luanda/O=42Luanda/OU=Inception/CN=nmatondo.42.fr"

echo "✅ SSL certificates generated for nmatondo.42.fr"
```

### Explicação Detalhada

#### OpenSSL Command Breakdown

```bash
openssl req -x509 -nodes -days 365 \
    -newkey rsa:2048 \
    -keyout $SSL_DIR/privkey.pem \
    -out $SSL_DIR/fullchain.pem \
    -subj "/C=AO/ST=Luanda/L=Luanda/O=42Luanda/OU=Inception/CN=nmatondo.42.fr"
```

**Comando por Componente:**

#### `openssl req`
- **Descrição:** Utilitário de gestão de certificados e pedidos
- **`req`**: Request - criar e processar pedidos de certificado

---

#### `-x509`
- **Descrição:** Gerar certificado auto-assinado em vez de CSR (Certificate Signing Request)
- **Diferença:**
  - **CSR:** Pedido enviado a CA (Certificate Authority) para assinatura
  - **x509:** Certificado completo auto-assinado
- **Uso:** Desenvolvimento e ambientes internos
- **Produção:** Usar certificados de CA reconhecida (Let's Encrypt, etc.)

---

#### `-nodes`
- **Descrição:** No DES - não encriptar chave privada
- **Explicação:** "nodes" = "no DES encryption"
- **Com encriptação:** Chave requer password para ser usada
- **Sem encriptação:** Chave pode ser usada automaticamente
- **Escolha:** 
  - ✅ Automação (servers, containers)
  - ❌ Armazenamento em dispositivos removíveis

---

#### `-days 365`
- **Descrição:** Validade do certificado em dias
- **Valor:** `365` dias (1 ano)
- **Recomendações:**
  - **Desenvolvimento:** 365-730 dias
  - **Produção:** 90 dias (Let's Encrypt padrão)
  - **Interno:** 1-3 anos
- **Expiração:** Após este período, certificado é considerado inválido
- **Renovação:** Executar script novamente gera novo certificado

---

#### `-newkey rsa:2048`
- **Descrição:** Gerar nova chave privada
- **Algoritmo:** RSA (Rivest-Shamir-Adleman)
- **Tamanho:** 2048 bits

**Tamanhos de Chave RSA:**
| Tamanho | Segurança | Performance | Uso Recomendado |
|---------|-----------|-------------|-----------------|
| 1024 bits | ❌ Inseguro | ⚡ Rápido | Obsoleto |
| 2048 bits | ✅ Seguro | ⚡ Bom | Padrão atual |
| 4096 bits | ✅✅ Muito seguro | 🐌 Lento | Alta segurança |
| 8192 bits | ✅✅✅ Extremo | 🐌🐌 Muito lento | Raramente usado |

**Escolha de 2048 bits:**
- Balanço entre segurança e performance
- Padrão da indústria
- Suportado por todos os navegadores
- Adequado para próximos 10+ anos

---

#### `-keyout $SSL_DIR/privkey.pem`
- **Descrição:** Caminho para salvar chave privada
- **Ficheiro:** `/etc/nginx/ssl/privkey.pem`
- **Formato:** PEM (Privacy Enhanced Mail)
  - Formato Base64 ASCII
  - Começa com `-----BEGIN PRIVATE KEY-----`
  - Termina com `-----END PRIVATE KEY-----`
- **Segurança:** 
  - ⚠️ Nunca compartilhar este ficheiro
  - Permissões: `600` (apenas owner pode ler/escrever)
  - Usado pelo servidor para desencriptar comunicações

---

#### `-out $SSL_DIR/fullchain.pem`
- **Descrição:** Caminho para salvar certificado público
- **Ficheiro:** `/etc/nginx/ssl/fullchain.pem`
- **Formato:** PEM
  - Começa com `-----BEGIN CERTIFICATE-----`
  - Termina com `-----END CERTIFICATE-----`
- **Conteúdo:**
  - Chave pública
  - Informações do subject
  - Assinatura digital
- **Uso:** Enviado aos clientes (navegadores) durante handshake SSL/TLS
- **Nome "fullchain":** Em certificados CA, incluiria cadeia completa de certificados

---

#### `-subj` (Subject Distinguished Name)

```
"/C=AO/ST=Luanda/L=Luanda/O=42Luanda/OU=Inception/CN=nmatondo.42.fr"
```

**Distinguished Name (DN):** Identifica a entidade certificada

### Explicação de Cada Campo

#### `/C=AO` - Country
- **Campo:** Country (País)
- **Código:** `AO` (Angola)
- **Formato:** ISO 3166-1 alpha-2 (2 letras)
- **Exemplos:**
  - `US` - United States
  - `PT` - Portugal
  - `BR` - Brasil
  - `FR` - France
  - `DE` - Germany
- **Obrigatório:** Sim (em muitos CAs)
- **Verificação:** Navegadores mostram país em detalhes do certificado

---

#### `/ST=Luanda` - State/Province
- **Campo:** State ou Province (Estado/Província)
- **Valor:** `Luanda`
- **Descrição:** Província ou estado onde organização está registada
- **Formato:** Nome completo (não abreviações)
- **Exemplos:**
  - `California` (não `CA`)
  - `São Paulo` (não `SP`)
  - `Lisboa`
- **Uso:** Identificação geográfica adicional
- **Opcional:** Pode ser omitido, mas recomendado

---

#### `/L=Luanda` - Locality
- **Campo:** Locality (Cidade/Localidade)
- **Valor:** `Luanda`
- **Descrição:** Cidade onde organização opera
- **Formato:** Nome completo da cidade
- **Exemplos:**
  - `San Francisco`
  - `Porto`
  - `Rio de Janeiro`
- **Diferença com ST:** 
  - `ST` = Província/Estado
  - `L` = Cidade específica
- **Uso:** Localização precisa da entidade

---

#### `/O=42Luanda` - Organization
- **Campo:** Organization (Organização)
- **Valor:** `42Luanda`
- **Descrição:** Nome legal da empresa/organização
- **Formato:** Nome oficial completo
- **Exemplos:**
  - `Microsoft Corporation`
  - `Escola 42 Lisboa`
  - `Universidade de Coimbra`
- **Certificados EV:** Verificação rigorosa deste campo
- **Importante:** Deve corresponder à entidade real em certificados públicos

---

#### `/OU=Inception` - Organizational Unit
- **Campo:** Organizational Unit (Unidade Organizacional)
- **Valor:** `Inception`
- **Descrição:** Departamento, divisão ou projeto dentro da organização
- **Formato:** Nome do departamento
- **Exemplos:**
  - `IT Department`
  - `Engineering`
  - `Web Services`
  - `Projeto Inception`
- **Uso:** Distinguir certificados de diferentes departamentos
- **Opcional:** Pode ser omitido

---

#### `/CN=nmatondo.42.fr` - Common Name
- **Campo:** Common Name (Nome Comum)
- **Valor:** `nmatondo.42.fr`
- **Descrição:** **CAMPO MAIS IMPORTANTE** - Nome de domínio (FQDN) do servidor
- **Obrigatório:** Sim - essencial para validação SSL/TLS
- **Verificação:** Navegador compara CN com URL acessado
- **Exemplos:**
  - `www.example.com`
  - `mail.empresa.com`
  - `*.example.com` (wildcard)

**Como funciona a verificação:**
1. Utilizador acessa `https://nmatondo.42.fr`
2. Servidor envia certificado com `CN=nmatondo.42.fr`
3. Navegador compara:
   - ✅ `nmatondo.42.fr` == `nmatondo.42.fr` → Válido
   - ❌ `nmatondo.42.fr` != `outro.42.fr` → Inválido (erro SSL)

**Wildcards:**
```
CN=*.42.fr
```
- Válido para: `nmatondo.42.fr`, `teste.42.fr`, `www.42.fr`
- Inválido para: `sub.nmatondo.42.fr` (apenas 1 nível)

**Subject Alternative Names (SAN):**
- Certificados modernos usam extensão SAN
- Permite múltiplos domínios em um certificado
- CN ainda é necessário para compatibilidade

---

### Formato Completo do Subject

```
Subject: C=AO, ST=Luanda, L=Luanda, O=42Luanda, OU=Inception, CN=nmatondo.42.fr
```

**Representação Visual:**

```
┌─────────────────────────────────────────┐
│     Distinguished Name (DN)             │
├─────────────────────────────────────────┤
│ Country (C)         = AO                │
│ State (ST)          = Luanda            │
│ Locality (L)        = Luanda            │
│ Organization (O)    = 42Luanda          │
│ Org. Unit (OU)      = Inception         │
│ Common Name (CN)    = nmatondo.42.fr    │
└─────────────────────────────────────────┘
```

---

### Ficheiros Gerados

Após execução do script:

```
/etc/nginx/ssl/
├── privkey.pem       # Chave privada RSA 2048-bit
└── fullchain.pem     # Certificado público x509
```

#### Conteúdo de privkey.pem (exemplo)
```
-----BEGIN PRIVATE KEY-----
MIIEvQIBADANBgkqhkiG9w0BAQEFAASCBKcwggSjAgEAAoIBAQC7... (dados Base64)
...
-----END PRIVATE KEY-----
```

#### Conteúdo de fullchain.pem (exemplo)
```
-----BEGIN CERTIFICATE-----
MIIDXTCCAkWgAwIBAgIJAKL3... (dados Base64)
...
Subject: C=AO, ST=Luanda, L=Luanda, O=42Luanda, OU=Inception, CN=nmatondo.42.fr
...
-----END CERTIFICATE-----
```

---

### Uso no NGINX

**Configuração em nginx.conf:**
```nginx
server {
    listen 443 ssl;
    server_name nmatondo.42.fr;

    ssl_certificate /etc/nginx/ssl/fullchain.pem;
    ssl_certificate_key /etc/nginx/ssl/privkey.pem;
    
    ssl_protocols TLSv1.2 TLSv1.3;
    # ...
}
```

**Explicação:**
- **`ssl_certificate`**: Certificado público (enviado ao cliente)
- **`ssl_certificate_key`**: Chave privada (mantida em segredo)
- **Handshake SSL/TLS:**
  1. Cliente conecta a `https://nmatondo.42.fr:443`
  2. Servidor envia `fullchain.pem`
  3. Cliente verifica:
     - CN corresponde ao domínio? ✅
     - Certificado expirado? ✅
     - Assinatura válida? ⚠️ (auto-assinado - warning)
  4. Cliente e servidor negociam chaves de sessão
  5. Comunicação encriptada estabelecida

---

## Docker Secrets

### O que são Docker Secrets?

Docker Secrets é um mecanismo para gerir dados sensíveis em containers de forma segura.

### Características

| Característica | Descrição |
|----------------|-----------|
| **Armazenamento** | tmpfs (RAM) - nunca em disco |
| **Encriptação** | Em trânsito e em repouso (Swarm) |
| **Acesso** | Apenas containers autorizados |
| **Localização** | `/run/secrets/<nome_secret>` |
| **Permissões** | 400 (apenas owner lê) |
| **Owner** | root ou utilizador especificado |

### Configuração no docker-compose.yml

```yaml
services:
  wordpress:
    secrets:
      - db_password
      - credentials
      - redis_password

secrets:
  db_password:
    file: ../secrets/db_password.txt
  credentials:
    file: ../secrets/credentials.txt
  redis_password:
    file: ../secrets/redis_password.txt
```

### Acesso em Scripts

```bash
# Ler secret
DB_PASSWORD=$(cat /run/secrets/db_password)

# Usar em comando
mariadb -u root -p$(cat /run/secrets/db_password)

# Exportar como variável
export WORDPRESS_DB_PASSWORD="$(cat /run/secrets/db_password)"
```

### Boas Práticas

✅ **Fazer:**
- Usar secrets para passwords, tokens, chaves API
- Manter ficheiros secrets fora de git (`.gitignore`)
- Permissões restritivas (600) em ficheiros secrets
- Um valor por ficheiro

❌ **Evitar:**
- Secrets em variáveis de ambiente
- Secrets em Dockerfiles
- Secrets em logs
- Secrets em nomes de volumes/containers

---

## Integração com Elasticsearch

### Plugin ElasticPress

**ElasticPress** é um plugin WordPress que integra Elasticsearch para:
- Pesquisa mais rápida e relevante
- Posts relacionados
- Faceted search (filtros)
- Autocomplete/autosuggest

### Configuração Automática

O script `entrypoint.sh` configura automaticamente:

1. **Espera pelo Elasticsearch** (max 30 tentativas)
2. **Instala plugin** ElasticPress
3. **Configura host** Elasticsearch
4. **Ativa features:**
   - Search
   - Related Posts
   - Facets
   - Search Ordering
   - Autosuggest
5. **Indexa conteúdo** existente

### Features Explicadas

#### Search
- Substitui pesquisa padrão WordPress por Elasticsearch
- Resultados mais relevantes
- Pesquisa em múltiplos campos (título, conteúdo, meta)

#### Related Posts
- Sugere posts similares baseado em conteúdo
- Usa algoritmos de similaridade do Elasticsearch

#### Facets
- Filtros laterais de pesquisa
- Exemplo: Filtrar por categoria, autor, data

#### Search Ordering
- Controlo sobre relevância de resultados
- Boost de campos específicos

#### Autosuggest
- Sugestões durante digitação
- Melhora UX de pesquisa

---

## Fluxo Completo de Inicialização

```
1. Container inicia → Executa entrypoint.sh
                    ↓
2. Valida WP-CLI está instalado
                    ↓
3. Lê secrets (db_password, redis_password, credentials)
                    ↓
4. Cria wp-config.php com configurações
                    ↓
5. Loop: Aguarda MariaDB responder
                    ↓
6. Verifica se WordPress já está instalado
   ├─ Sim → Pula instalação
   └─ Não → Instala WordPress + cria utilizadores
                    ↓
7. Aguarda Elasticsearch (se configurado)
                    ↓
8. Instala e configura ElasticPress
                    ↓
9. Indexa conteúdo no Elasticsearch
                    ↓
10. Ajusta permissões (chown www-data)
                    ↓
11. Inicia PHP-FPM em foreground (PID 1)
                    ↓
12. Container pronto para receber requests
```

---

## Troubleshooting

### Erro: wp-cli não encontrado
**Causa:** WP-CLI não instalado no Dockerfile  
**Solução:** Verificar instalação no Dockerfile WordPress

### Erro: Secret não encontrado
**Causa:** Ficheiro secret não existe ou path incorreto  
**Solução:** Criar ficheiros em `secrets/` e verificar docker-compose.yml

### Erro: MariaDB connection refused
**Causa:** MariaDB ainda não iniciou ou credenciais incorretas  
**Solução:** Aumentar timeout no loop ou verificar passwords

### Erro: Permission denied em /var/www/html
**Causa:** Permissões incorretas  
**Solução:** Executar `chown -R www-data:www-data /var/www/html`

### Warning: SSL Certificate não confiável
**Causa:** Certificado auto-assinado  
**Solução:** Normal em desenvolvimento - aceitar exceção no navegador

---

## Referências

- [PHP-FPM Documentation](https://www.php.net/manual/en/install.fpm.php)
- [WP-CLI Commands](https://developer.wordpress.org/cli/commands/)
- [OpenSSL Documentation](https://www.openssl.org/docs/)
- [Docker Secrets](https://docs.docker.com/engine/swarm/secrets/)
- [ElasticPress Documentation](https://www.elasticpress.io/documentation/)
- [X.509 Certificate Format](https://en.wikipedia.org/wiki/X.509)

---

**Última atualização:** Janeiro 2026  
**Versão:** 1.0  
**Autor:** Projeto Inception - 42 School
