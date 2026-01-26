# Configuração NGINX - Documentação Técnica

Este documento fornece uma explicação detalhada de todas as configurações NGINX utilizadas no projeto Inception, incluindo SSL/TLS, reverse proxy, e integração com PHP-FPM.

**Versão utilizada:** NGINX latest em Alpine Linux 3.23

---

## Índice

1. [Estrutura do nginx.conf](#estrutura-do-nginxconf)
2. [Configurações Globais](#configurações-globais)
3. [Bloco Events](#bloco-events)
4. [Bloco HTTP](#bloco-http)
5. [Bloco Server](#bloco-server)
6. [Configuração SSL/TLS](#configuração-ssltls)
7. [Location Blocks](#location-blocks)
8. [FastCGI para PHP](#fastcgi-para-php)
9. [Reverse Proxy](#reverse-proxy)
10. [Geração de Certificados SSL](#geração-de-certificados-ssl)

---

## Estrutura do nginx.conf

**Ficheiro:** `srcs/requirements/nginx/conf/nginx.conf`

### Hierarquia de Contextos

```
main (global)
├── events
└── http
    └── server
        └── location
```

### Ficheiro Completo Anotado

```nginx
worker_processes auto;                    # Contexto: main

events {                                  # Contexto: events
    worker_connections 1024;
}

http {                                    # Contexto: http
    include       mime.types;
    default_type  application/octet-stream;

    resolver 127.0.0.11 valid=30s;        # DNS resolver (Docker)

    server {                              # Contexto: server
        listen 443 ssl;
        server_name nmatondo.42.fr;

        # Configurações SSL...
        # Configurações root...
        
        location / { }                    # Contexto: location
        location ~ \.php$ { }
        location ~ /\.ht { }
        location /myprofile/ { }          # Reverse proxy com rewrite
        location /adminer/ { }            # Reverse proxy com rewrite
    }
}
```

---

## Configurações Globais

### worker_processes

```nginx
worker_processes auto;
```

#### Explicação Detalhada

- **Contexto:** `main` (global)
- **Descrição:** Define o número de worker processes (processos trabalhadores) do NGINX
- **Valor:** `auto`

#### O que são Worker Processes?

```
┌─────────────────────────────────────┐
│      NGINX Architecture             │
├─────────────────────────────────────┤
│  Master Process (PID 1)             │
│    ├─ Worker Process 1              │
│    ├─ Worker Process 2              │
│    ├─ Worker Process 3              │
│    └─ Worker Process 4              │
└─────────────────────────────────────┘
```

- **Master Process:** Gerencia workers, lê configuração, gerencia portas
- **Worker Processes:** Lidam com requisições HTTP reais

#### Valores Possíveis

| Valor | Descrição | Uso Recomendado |
|-------|-----------|-----------------|
| `auto` | NGINX detecta automaticamente número de CPUs | ✅ Recomendado (padrão moderno) |
| `1` | Um único worker process | Desenvolvimento, testes |
| `2`, `4`, `8` | Número fixo de workers | Controlo manual |
| `$(nproc)` | Shell expansion (não suportado) | ❌ Não usar |

#### Como `auto` Funciona?

1. NGINX lê `/proc/cpuinfo` (Linux) ou equivalente
2. Detecta número de núcleos de CPU disponíveis
3. Cria 1 worker por núcleo

**Exemplo:**
- CPU com 4 núcleos → 4 worker processes
- CPU com 8 núcleos → 8 worker processes

#### Fórmula Recomendada (se manual)

```
worker_processes = número_de_nucleos_CPU
```

**Caso especial - CPU Hyperthreading:**
- 4 cores físicos, 8 threads lógicos
- Recomendado: `worker_processes 4;` (cores físicos)
- Alternativa: `worker_processes 8;` (threads lógicos)

#### Performance

| Cenário | Impacto |
|---------|---------|
| **Poucos workers** | Saturação de CPU, requests em fila |
| **Muitos workers** | Context switching, overhead de memória |
| **auto** | Balanceamento ideal automático |

---

## Bloco Events

```nginx
events {
    worker_connections 1024;
}
```

### worker_connections

```nginx
worker_connections 1024;
```

#### Explicação Detalhada

- **Contexto:** `events`
- **Descrição:** Número máximo de conexões simultâneas que cada worker process pode lidar
- **Valor:** `1024`

#### Cálculo de Capacidade Total

```
Conexões Totais = worker_processes × worker_connections
```

**Exemplo com configuração atual:**
- `worker_processes auto` = 4 (assumindo 4 CPUs)
- `worker_connections 1024`
- **Total:** 4 × 1024 = **4096 conexões simultâneas**

#### O que conta como "Conexão"?

Em servidor web típico:
- **1 requisição cliente** = 1 conexão
- **1 proxy pass** = 2 conexões (cliente → NGINX → backend)

**Exemplo - Requisição PHP:**
```
Cliente → NGINX (1 conexão)
NGINX → PHP-FPM (1 conexão)
Total: 2 conexões por requisição
```

**Portanto, capacidade real:**
```
Requisições simultâneas = (worker_processes × worker_connections) / 2
Exemplo: (4 × 1024) / 2 = 2048 requisições simultâneas
```

#### Limites do Sistema Operativo

**Linux - `ulimit`:**
```bash
# Ver limite atual
ulimit -n
# Output: 1024 (padrão)

# Aumentar limite (temporário)
ulimit -n 65535

# Aumentar limite (permanente)
# Editar /etc/security/limits.conf
nginx soft nofile 65535
nginx hard nofile 65535
```

**Relação:**
```
worker_connections ≤ ulimit -n
```

#### Valores Comuns

| Valor | Cenário | Capacidade (4 workers) |
|-------|---------|------------------------|
| `512` | Desenvolvimento | 2048 conexões |
| `1024` | Pequeno/médio (padrão) | 4096 conexões |
| `2048` | Médio/grande | 8192 conexões |
| `4096` | Grande tráfego | 16384 conexões |
| `8192` | Muito alto tráfego | 32768 conexões |

#### Memória Consumida

Cada conexão consome memória:
```
Memória ≈ worker_connections × (2KB - 10KB)
1024 conexões ≈ 2MB - 10MB por worker
```

#### Tuning Recomendado

**Baixo tráfego (desenvolvimento):**
```nginx
worker_processes 1;
events {
    worker_connections 512;
}
```

**Médio tráfego (produção normal):**
```nginx
worker_processes auto;
events {
    worker_connections 1024;  # Configuração atual
}
```

**Alto tráfego (alta performance):**
```nginx
worker_processes auto;
events {
    worker_connections 4096;
    use epoll;  # Linux
    multi_accept on;
}
```

---

## Bloco HTTP

```nginx
http {
    include       mime.types;
    default_type  application/octet-stream;

    resolver 127.0.0.11 valid=30s;

    server {
        # ...
    }
}
```

### resolver

```nginx
resolver 127.0.0.11 valid=30s;
```

#### Explicação Detalhada

- **Contexto:** `http`, `server`, `location`
- **Descrição:** Define servidor DNS para resolução de nomes em tempo de execução
- **Valor:** `127.0.0.11` (DNS interno do Docker)
- **TTL:** `valid=30s` (cache de resolução por 30 segundos)

#### Quando é Necessário?

**Resolução estática vs dinâmica:**

```nginx
# Resolução estática (em tempo de configuração)
proxy_pass http://backend:8080;
# ✅ Nome resolvido quando NGINX inicia
# ❌ Se backend mudar de IP, requer reload

# Resolução dinâmica (em tempo de execução)
set $backend "backend:8080";
proxy_pass http://$backend;
# ⚠️ Requer resolver configurado!
# ✅ Nome resolvido a cada requisição
```

**Nossa configuração usa variáveis:**
```nginx
set $_myprofile_proxy "myprofile:8888";
proxy_pass http://$_myprofile_proxy;
# ↑ Requer resolver!
```

#### DNS Docker (127.0.0.11)

**Funcionamento:**
- Docker fornece servidor DNS interno
- Endereço sempre `127.0.0.11` (dentro de containers)
- Resolve nomes de serviços Docker Compose
- Atualiza automaticamente quando containers mudam

**Resolução:**
```
myprofile → 172.18.0.5 (IP do container)
adminer → 172.18.0.6
wordpress → 172.18.0.7
```

#### Parâmetro valid

```nginx
resolver 127.0.0.11 valid=30s;
```

- **Função:** Tempo de cache da resolução DNS
- **Valor:** `30s` (30 segundos)
- **Benefício:** Reduz queries DNS repetitivas

**Valores comuns:**
- `valid=30s` - Balanceado (configuração atual)
- `valid=10s` - Containers dinâmicos
- `valid=300s` - Ambientes estáveis

#### Sem Resolver Configurado

**Erro típico:**
```
no resolver defined to resolve myprofile
```

**Quando ocorre:**
```nginx
set $backend "myprofile:8888";
proxy_pass http://$backend;
# ↑ Sem resolver = ERRO!
```

**Solução:**
```nginx
resolver 127.0.0.11 valid=30s;
set $backend "myprofile:8888";
proxy_pass http://$backend;
# ✅ Funciona!
```

#### Resolvers Alternativos

```nginx
# Google Public DNS
resolver 8.8.8.8 8.8.4.4 valid=300s;

# Cloudflare DNS
resolver 1.1.1.1 1.0.0.1 valid=300s;

# DNS local + fallback
resolver 127.0.0.11 8.8.8.8 valid=30s;

# IPv6
resolver [2001:4860:4860::8888] valid=300s;
```

---

### include mime.types

```nginx
include mime.types;
```

#### Explicação Detalhada

- **Contexto:** `http`
- **Descrição:** Inclui ficheiro externo com mapeamento de extensões → Content-Type
- **Ficheiro:** `/etc/nginx/mime.types` (localização padrão)

#### O que são MIME Types?

**MIME** (Multipurpose Internet Mail Extensions) tipos identificam o formato de dados transmitidos.

**Estrutura:**
```
tipo/subtipo
```

#### Conteúdo de mime.types

```nginx
types {
    text/html                             html htm shtml;
    text/css                              css;
    text/xml                              xml;
    image/gif                             gif;
    image/jpeg                            jpeg jpg;
    image/png                             png;
    application/javascript                js;
    application/json                      json;
    application/pdf                       pdf;
    application/zip                       zip;
    video/mp4                             mp4;
    # ... centenas de tipos
}
```

#### Como Funciona

1. Cliente solicita: `GET /style.css`
2. NGINX identifica extensão: `.css`
3. Consulta `mime.types`: `text/css`
4. Responde com header: `Content-Type: text/css`

**Header HTTP enviado:**
```http
HTTP/1.1 200 OK
Content-Type: text/css
Content-Length: 1234
```

#### Importância

**Sem MIME type correto:**
- Navegador não interpreta CSS (não aplica estilos)
- JavaScript não executa
- Imagens não renderizam corretamente
- Downloads forçados em vez de visualização

**Exemplo - PDF:**
- ✅ `Content-Type: application/pdf` → Abre no navegador
- ❌ `Content-Type: application/octet-stream` → Download forçado

---

### default_type

```nginx
default_type application/octet-stream;
```

#### Explicação Detalhada

- **Contexto:** `http`, `server`, `location`
- **Descrição:** MIME type padrão quando extensão não encontrada em `mime.types`
- **Valor:** `application/octet-stream`

#### O que é application/octet-stream?

- **Significado:** "Stream de bytes binários genéricos"
- **Tradução:** "Não sei que tipo de ficheiro é isto"
- **Comportamento navegador:** Geralmente força download

#### Quando é Usado?

```nginx
# Ficheiros sem extensão ou extensão desconhecida
/arquivo_sem_extensao
/script.xyz
/dados.custom
```

#### Valores Alternativos

| Valor | Uso | Comportamento |
|-------|-----|---------------|
| `application/octet-stream` | Padrão seguro | Download |
| `text/plain` | Logs, configs | Exibe como texto |
| `text/html` | ⚠️ Perigoso | Interpreta como HTML |

**Nunca usar `text/html` como padrão:**
```nginx
default_type text/html;  # ❌ PERIGO - XSS risk
```
- Ficheiros desconhecidos seriam interpretados como HTML
- Potencial vulnerabilidade XSS

---

## Bloco Server

```nginx
server {
    listen 443 ssl;
    server_name nmatondo.42.fr;
    
    # SSL config...
    # Root config...
    # Locations...
}
```

### listen

```nginx
listen 443 ssl;
```

#### Explicação Detalhada

- **Contexto:** `server`
- **Descrição:** Define porta e protocolo que NGINX escuta
- **Porta:** `443` (HTTPS padrão)
- **Flag:** `ssl` (habilita SSL/TLS)

#### Sintaxe Completa

```nginx
listen [endereço:]porta [parâmetros];
```

#### Variações de Uso

```nginx
# Apenas porta
listen 80;
listen 443;

# Porta + SSL
listen 443 ssl;
listen 443 ssl http2;  # Com HTTP/2

# IP + Porta
listen 192.168.1.10:443 ssl;
listen [::]:443 ssl;  # IPv6

# Porta + default_server
listen 443 ssl default_server;

# Socket Unix
listen unix:/var/run/nginx.sock;
```

#### Parâmetros Importantes

| Parâmetro | Descrição | Exemplo |
|-----------|-----------|---------|
| `ssl` | Habilita SSL/TLS | `listen 443 ssl;` |
| `http2` | Habilita HTTP/2 | `listen 443 ssl http2;` |
| `default_server` | Server block padrão | `listen 443 default_server;` |
| `reuseport` | Performance (socket por worker) | `listen 443 reuseport;` |
| `backlog=N` | Tamanho da fila de conexões | `listen 443 backlog=4096;` |

#### Portas Padrão

| Porta | Protocolo | Uso |
|-------|-----------|-----|
| `80` | HTTP | Web não encriptado |
| `443` | HTTPS | Web encriptado (SSL/TLS) |
| `8080` | HTTP | Alternativa (desenvolvimento) |
| `8443` | HTTPS | Alternativa |

#### Por que 443?

```
Cliente (navegador)
    ↓
https://nmatondo.42.fr  →  Porta 443 (implícita)
    ↓
NGINX escuta porta 443
    ↓
Conexão SSL/TLS estabelecida
```

**URL sem porta explícita:**
- `http://site.com` → Porta 80
- `https://site.com` → Porta 443

**URL com porta explícita:**
- `http://site.com:8080` → Porta 8080
- `https://site.com:8443` → Porta 8443

#### Flag `ssl`

**Com flag `ssl`:**
```nginx
listen 443 ssl;
```
- NGINX espera handshake SSL/TLS
- Requer `ssl_certificate` e `ssl_certificate_key`

**Sem flag `ssl`:**
```nginx
listen 443;  # ❌ Não funciona para HTTPS
```
- NGINX trata como HTTP normal
- Cliente SSL receberá erro

---

### server_name

```nginx
server_name nmatondo.42.fr;
```

#### Explicação Detalhada

- **Contexto:** `server`
- **Descrição:** Nome(s) de domínio que este bloco server atende
- **Valor:** `nmatondo.42.fr`

#### Como Funciona

```
1. Cliente faz requisição: https://nmatondo.42.fr/
   Header: Host: nmatondo.42.fr

2. NGINX compara header "Host" com server_name

3. Se match → usa este bloco server
   Se não match → usa default_server ou retorna 404
```

#### Sintaxe e Variações

```nginx
# Domínio único
server_name nmatondo.42.fr;

# Múltiplos domínios
server_name nmatondo.42.fr www.nmatondo.42.fr;

# Wildcard - qualquer subdomínio
server_name *.42.fr;
server_name .42.fr;  # Equivalente

# Wildcard - início
server_name nmatondo.*;

# Regex
server_name ~^(www\.)?nmatondo\.42\.fr$;

# Catch-all (qualquer domínio)
server_name _;
```

#### Prioridade de Match

Quando múltiplos `server` blocks existem:

1. **Nome exato:** `server_name example.com;`
2. **Wildcard início:** `server_name *.example.com;`
3. **Wildcard fim:** `server_name example.*;`
4. **Regex:** `server_name ~^example;`
5. **Default server:** `listen 443 default_server;`

#### Exemplo com Múltiplos Server Blocks

```nginx
# Server block 1 - específico
server {
    listen 443 ssl;
    server_name nmatondo.42.fr;
    # Config para nmatondo.42.fr
}

# Server block 2 - wildcard
server {
    listen 443 ssl;
    server_name *.42.fr;
    # Config para qualquer subdomínio
}

# Server block 3 - default
server {
    listen 443 ssl default_server;
    server_name _;
    return 444;  # Fecha conexão sem resposta
}
```

**Requisições:**
- `nmatondo.42.fr` → Server block 1 (match exato)
- `teste.42.fr` → Server block 2 (wildcard)
- `outro.dominio.com` → Server block 3 (default)

#### Virtual Hosting

`server_name` permite múltiplos sites em um NGINX:

```nginx
server {
    listen 443 ssl;
    server_name site1.com;
    root /var/www/site1;
}

server {
    listen 443 ssl;
    server_name site2.com;
    root /var/www/site2;
}
```

---

## Configuração SSL/TLS

```nginx
ssl_certificate     /etc/nginx/ssl/fullchain.pem;
ssl_certificate_key /etc/nginx/ssl/privkey.pem;

ssl_protocols TLSv1.2 TLSv1.3;
ssl_ciphers HIGH:!aNULL:!MD5;
```

### ssl_certificate

```nginx
ssl_certificate /etc/nginx/ssl/fullchain.pem;
```

#### Explicação Detalhada

- **Contexto:** `http`, `server`
- **Descrição:** Caminho para o certificado SSL/TLS público
- **Ficheiro:** `fullchain.pem` (contém certificado + cadeia)

#### Conteúdo do Certificado

```
-----BEGIN CERTIFICATE-----
MIIDXTCCAkWgAwIBAgIJAKL3... (Base64)
...
-----END CERTIFICATE-----
```

#### O que Contém?

1. **Chave pública** do servidor
2. **Informações do subject** (CN, O, etc.)
3. **Assinatura digital** (auto-assinada neste caso)
4. **Período de validade**
5. **Extensões** (SANs, key usage, etc.)

#### "fullchain" vs "cert"

| Nome | Conteúdo | Uso |
|------|----------|-----|
| `cert.pem` | Apenas certificado do servidor | ❌ Incompleto |
| `fullchain.pem` | Certificado + intermediários | ✅ Recomendado |
| `chain.pem` | Apenas intermediários | Para concatenar |

**Certificado completo (production):**
```
fullchain.pem:
  ├─ Certificado do servidor
  ├─ Certificado intermediário 1
  ├─ Certificado intermediário 2
  └─ (Certificado root - opcional)
```

**Nosso caso (auto-assinado):**
```
fullchain.pem:
  └─ Apenas certificado do servidor (auto-assinado)
```

#### Processo de Verificação

```
1. Cliente conecta → Servidor envia fullchain.pem
                           ↓
2. Cliente verifica:
   ├─ Certificado expirado? ✅
   ├─ CN match domínio? ✅
   ├─ Assinatura válida? ⚠️ (auto-assinado)
   └─ Cadeia até root CA? ⚠️ (não confiável)
                           ↓
3. Navegador:
   ├─ CA confiável → 🔒 Conexão segura
   └─ Auto-assinado → ⚠️ Warning
```

---

### ssl_certificate_key

```nginx
ssl_certificate_key /etc/nginx/ssl/privkey.pem;
```

#### Explicação Detalhada

- **Contexto:** `http`, `server`
- **Descrição:** Caminho para chave privada SSL/TLS
- **Ficheiro:** `privkey.pem`
- **⚠️ CRÍTICO:** Nunca compartilhar este ficheiro!

#### Conteúdo da Chave Privada

```
-----BEGIN PRIVATE KEY-----
MIIEvQIBADANBgkqhkiG9w0BAQEFAASCBKcw... (Base64)
...
-----END PRIVATE KEY-----
```

#### Segurança da Chave Privada

**Permissões recomendadas:**
```bash
chmod 600 /etc/nginx/ssl/privkey.pem
chown nginx:nginx /etc/nginx/ssl/privkey.pem
```

**Permissões em octal:**
- `600` = `rw-------` (apenas owner pode ler/escrever)
- `400` = `r--------` (apenas owner pode ler - ainda mais seguro)

**Verificar permissões:**
```bash
ls -l /etc/nginx/ssl/privkey.pem
# Deve mostrar: -rw------- 1 nginx nginx ...
```

#### Relação com Certificado

```
Certificado Público (fullchain.pem)
    ↓ (par criptográfico)
Chave Privada (privkey.pem)
```

**Processo de encriptação:**
1. Cliente gera chave de sessão simétrica
2. Encripta com chave pública do servidor
3. Envia para servidor
4. Servidor desencripta com chave privada
5. Comunicação prossegue com chave simétrica

#### Tipos de Chaves

```nginx
# RSA (mais comum)
ssl_certificate_key /path/to/rsa-privkey.pem;

# ECDSA (mais rápido, menor)
ssl_certificate_key /path/to/ecdsa-privkey.pem;

# Ambos (dual certificate)
ssl_certificate     /path/to/rsa-cert.pem;
ssl_certificate_key /path/to/rsa-key.pem;
ssl_certificate     /path/to/ecdsa-cert.pem;
ssl_certificate_key /path/to/ecdsa-key.pem;
```

#### Chave Protegida por Password

**Se chave tem password:**
```nginx
ssl_certificate_key /path/to/encrypted-key.pem;
ssl_password_file /path/to/passwords.txt;
```

**Desencriptar chave (remover password):**
```bash
openssl rsa -in encrypted-key.pem -out privkey.pem
```

---

### ssl_protocols

```nginx
ssl_protocols TLSv1.2 TLSv1.3;
```

#### Explicação Detalhada

- **Contexto:** `http`, `server`
- **Descrição:** Versões de SSL/TLS permitidas
- **Valores:** `TLSv1.2 TLSv1.3` (versões modernas e seguras)

#### Evolução do SSL/TLS

| Versão | Ano | Status | Segurança |
|--------|-----|--------|-----------|
| SSLv2 | 1995 | ❌ Obsoleto | Inseguro |
| SSLv3 | 1996 | ❌ Obsoleto | Vulnerável (POODLE) |
| TLSv1.0 | 1999 | ⚠️ Depreciado | Fraco |
| TLSv1.1 | 2006 | ⚠️ Depreciado | Fraco |
| TLSv1.2 | 2008 | ✅ Seguro | Recomendado |
| TLSv1.3 | 2018 | ✅ Mais seguro | Recomendado |

#### Por que TLSv1.2 e TLSv1.3?

**TLSv1.2:**
- Amplamente suportado (99%+ navegadores)
- Seguro se configurado corretamente
- Requerido por muitos padrões de compliance

**TLSv1.3:**
- Melhor performance (menos round-trips)
- Algoritmos mais seguros
- Remove ciphers fracos
- Forward secrecy obrigatório

#### Handshake Comparison

**TLSv1.2 (2-RTT):**
```
Cliente → ClientHello → Servidor
Cliente ← ServerHello ← Servidor
Cliente → KeyExchange → Servidor
Cliente ← Finished ← Servidor
```

**TLSv1.3 (1-RTT):**
```
Cliente → ClientHello → Servidor
Cliente ← ServerHello + Finished ← Servidor
```

#### Configurações por Caso de Uso

**Máxima compatibilidade (não recomendado):**
```nginx
ssl_protocols TLSv1 TLSv1.1 TLSv1.2 TLSv1.3;
```

**Balanceado (recomendado - configuração atual):**
```nginx
ssl_protocols TLSv1.2 TLSv1.3;
```

**Máxima segurança (moderna):**
```nginx
ssl_protocols TLSv1.3;
```

**Enterprise/PCI DSS:**
```nginx
ssl_protocols TLSv1.2 TLSv1.3;
ssl_prefer_server_ciphers on;
```

#### Browser Support

**TLSv1.2:**
- Chrome 30+ (2013)
- Firefox 27+ (2014)
- Safari 7+ (2013)
- IE 11+ (2013)
- Edge todos

**TLSv1.3:**
- Chrome 70+ (2018)
- Firefox 63+ (2018)
- Safari 12.1+ (2019)
- Edge 76+ (2019)

#### Testar Configuração

```bash
# Testar TLS 1.2
openssl s_client -connect nmatondo.42.fr:443 -tls1_2

# Testar TLS 1.3
openssl s_client -connect nmatondo.42.fr:443 -tls1_3

# Testar TLS 1.1 (deve falhar)
openssl s_client -connect nmatondo.42.fr:443 -tls1_1
```

---

### ssl_ciphers

```nginx
ssl_ciphers HIGH:!aNULL:!MD5;
```

#### Explicação Detalhada

- **Contexto:** `http`, `server`
- **Descrição:** Lista de cipher suites permitidos
- **Formato:** String OpenSSL
- **Valor:** `HIGH:!aNULL:!MD5`

#### O que são Cipher Suites?

**Cipher Suite** define algoritmos para:
1. **Key Exchange** (troca de chaves): RSA, ECDHE, DHE
2. **Authentication** (autenticação): RSA, ECDSA
3. **Encryption** (encriptação): AES, ChaCha20
4. **MAC** (integridade): SHA256, SHA384

**Exemplo de cipher suite:**
```
ECDHE-RSA-AES256-GCM-SHA384
  │     │    │     │    │
  │     │    │     │    └─ MAC: SHA384
  │     │    │     └────── Mode: GCM
  │     │    └──────────── Encryption: AES 256-bit
  │     └───────────────── Auth: RSA
  └─────────────────────── Key Exchange: ECDHE
```

#### Sintaxe OpenSSL

```nginx
ssl_ciphers "CIPHER1:CIPHER2:!EXCLUDED";
```

**Operadores:**
- `:` - Adiciona cipher
- `!` - Exclui cipher
- `-` - Remove cipher da lista
- `+` - Move cipher para o fim

#### Componentes da Configuração Atual

```nginx
ssl_ciphers HIGH:!aNULL:!MD5;
```

**Decompondo:**

1. **`HIGH`**
   - Ciphers de alta segurança
   - Encriptação ≥ 128 bits
   - Inclui: AES256, AES128, ChaCha20
   - Exclui: DES, RC4, exportação

2. **`:!aNULL`**
   - `!` = Excluir
   - `aNULL` = Authentication NULL
   - Exclui ciphers sem autenticação
   - Previne ataques man-in-the-middle

3. **`:!MD5`**
   - `!` = Excluir
   - `MD5` = Message Digest 5
   - Exclui ciphers usando MD5 (vulnerável)
   - MD5 tem colisões conhecidas

#### Categorias de Ciphers

| Categoria | Descrição | Segurança |
|-----------|-----------|-----------|
| `HIGH` | Encriptação forte (≥128 bits) | ✅ Bom |
| `MEDIUM` | Encriptação média (≥64 bits) | ⚠️ Aceitável |
| `LOW` | Encriptação fraca | ❌ Inseguro |
| `EXPORT` | Exportação (40/56 bits) | ❌ Muito inseguro |
| `aNULL` | Sem autenticação | ❌ Muito inseguro |
| `eNULL` | Sem encriptação | ❌ Muito inseguro |

#### Configurações Recomendadas

**Moderna (TLSv1.3 principalmente):**
```nginx
ssl_protocols TLSv1.3;
# TLSv1.3 ignora ssl_ciphers, usa própria lista
```

**Intermediária (balanceada - melhor que atual):**
```nginx
ssl_protocols TLSv1.2 TLSv1.3;
ssl_ciphers 'ECDHE-ECDSA-AES128-GCM-SHA256:ECDHE-RSA-AES128-GCM-SHA256:ECDHE-ECDSA-AES256-GCM-SHA384:ECDHE-RSA-AES256-GCM-SHA384:ECDHE-ECDSA-CHACHA20-POLY1305:ECDHE-RSA-CHACHA20-POLY1305';
ssl_prefer_server_ciphers off;
```

**Antiga (máxima compatibilidade):**
```nginx
ssl_protocols TLSv1 TLSv1.1 TLSv1.2 TLSv1.3;
ssl_ciphers HIGH:!aNULL:!MD5;  # Configuração atual
ssl_prefer_server_ciphers on;
```

#### Forward Secrecy

**Ciphers com Forward Secrecy:**
- `ECDHE-*` (Elliptic Curve Diffie-Hellman Ephemeral)
- `DHE-*` (Diffie-Hellman Ephemeral)

**Benefício:**
- Chaves de sessão temporárias
- Se chave privada for comprometida, sessões passadas permanecem seguras

**Adicionar Forward Secrecy à config atual:**
```nginx
ssl_ciphers 'ECDHE-RSA-AES256-GCM-SHA384:ECDHE-RSA-AES128-GCM-SHA256:HIGH:!aNULL:!MD5';
```

#### Testar Ciphers

```bash
# Ver ciphers suportados
nmap --script ssl-enum-ciphers -p 443 nmatondo.42.fr

# Testar cipher específico
openssl s_client -connect nmatondo.42.fr:443 -cipher ECDHE-RSA-AES256-GCM-SHA384

# Ver cipher negociado
echo | openssl s_client -connect nmatondo.42.fr:443 2>/dev/null | grep "Cipher"
```

#### Verificação Online

Ferramentas para testar SSL:
- [SSL Labs](https://www.ssllabs.com/ssltest/)
- [Mozilla Observatory](https://observatory.mozilla.org/)
- [Security Headers](https://securityheaders.com/)

---

## Location Blocks

### Root e Index

```nginx
root /var/www/html;
index index.php index.html index.htm;
```

#### root

```nginx
root /var/www/html;
```

- **Contexto:** `http`, `server`, `location`
- **Descrição:** Diretório raiz para servir ficheiros
- **Valor:** `/var/www/html`

**Como funciona:**
```
URL: https://nmatondo.42.fr/page.html
Root: /var/www/html
Ficheiro servido: /var/www/html/page.html
```

**Com location:**
```nginx
location /images/ {
    root /var/www;
}
```
```
URL: https://nmatondo.42.fr/images/logo.png
Ficheiro: /var/www/images/logo.png
         (root + URI completo)
```

#### index

```nginx
index index.php index.html index.htm;
```

- **Contexto:** `http`, `server`, `location`
- **Descrição:** Ficheiros index a procurar quando URI termina em `/`
- **Ordem:** Tenta cada ficheiro na ordem listada

**Exemplo:**
```
URL: https://nmatondo.42.fr/
```

NGINX procura:
1. `/var/www/html/index.php` ← Encontra! Usa este
2. Se não existe → `/var/www/html/index.html`
3. Se não existe → `/var/www/html/index.htm`
4. Se nenhum existe → 403 Forbidden (ou autoindex se habilitado)

---

### Location / (Root Location)

```nginx
location / {
    try_files $uri $uri/ /index.php?$args;
}
```

#### Explicação Detalhada

- **Padrão:** `/` (qualquer URI)
- **Prioridade:** Mais baixa (catch-all)
- **Uso:** Requisições que não correspondem a outras locations

#### try_files

```nginx
try_files $uri $uri/ /index.php?$args;
```

**Sintaxe:**
```nginx
try_files arquivo1 arquivo2 ... fallback;
```

**Funcionamento:**
1. Tenta servir `$uri` como ficheiro
2. Se falhar, tenta `$uri/` como diretório
3. Se falhar, redireciona para `/index.php?$args`

**Exemplo passo-a-passo:**

**Requisição:** `GET /about/`

1. **`$uri`** = `/about/`
   - Verifica: `/var/www/html/about/` (ficheiro)
   - Não existe como ficheiro → Próximo

2. **`$uri/`** = `/about//` → `/about/`
   - Verifica: `/var/www/html/about/` (diretório)
   - Existe! Procura index → `/about/index.php`
   - Se index existe → Serve
   - Se não existe → Próximo

3. **`/index.php?$args`** (fallback)
   - Internal rewrite para `/index.php?$args`
   - WordPress processa rota

#### Variáveis NGINX

**`$uri`:**
- URI normalizado da requisição
- Sem query string
- Decodificado

**`$args`:**
- Query string (tudo após `?`)
- Exemplo: `page=1&category=tech`

**Exemplo completo:**
```
Requisição: /blog/post-1?utm_source=google

$uri = /blog/post-1
$args = utm_source=google

try_files:
  1. /var/www/html/blog/post-1 (ficheiro) → Não existe
  2. /var/www/html/blog/post-1/ (dir) → Não existe
  3. /index.php?utm_source=google (WordPress processa)
```

#### WordPress Permalink Support

Esta configuração suporta permalinks WordPress:

```
/2024/01/my-post/  →  Não existe fisicamente
                     ↓
                  try_files
                     ↓
              /index.php?$args
                     ↓
            WordPress router processa
                     ↓
         Renderiza post do banco de dados
```

**Sem try_files:**
- URLs bonitos falhariam (404)
- Apenas `/index.php?p=123` funcionaria

---

### Location ~ \.php$ (PHP Processing)

```nginx
location ~ \.php$ {
    try_files $uri =404;
    fastcgi_split_path_info ^(.+\.php)(/.+)$;
    fastcgi_pass wordpress:9000;
    fastcgi_index index.php;
    include fastcgi_params;
    fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    fastcgi_param PATH_INFO $fastcgi_path_info;
}
```

#### Location Modifier: ~

```nginx
location ~ \.php$ { }
```

- **`~`**: Case-sensitive regex match
- **Padrão:** `\.php$`
  - `\.php` = Literal ".php"
  - `$` = Fim da string
- **Match:** `/index.php`, `/wp-admin/admin.php`
- **Não match:** `/image.PNG`, `/style.css`

#### try_files $uri =404

```nginx
try_files $uri =404;
```

**Explicação:**
- Verifica se ficheiro PHP existe fisicamente
- Se não existe → Retorna 404
- **Segurança:** Previne execução de PHP inexistente

**Vulnerabilidade sem isto:**
```
URL: /uploads/malicious.jpg/index.php

Sem try_files:
  → FastCGI processa qualquer .php
  → PHP pode executar código em jpg
  → 🔴 VULNERABILIDADE

Com try_files:
  → /uploads/malicious.jpg/index.php não existe
  → 404 retornado
  → ✅ Seguro
```

#### fastcgi_split_path_info

```nginx
fastcgi_split_path_info ^(.+\.php)(/.+)$;
```

**Regex breakdown:**
- `^(.+\.php)` = Grupo 1: Caminho até .php (inclusive)
- `(/.+)$` = Grupo 2: PATH_INFO (após .php)

**Exemplo:**
```
URI: /index.php/some/path

Grupo 1 (SCRIPT_NAME): /index.php
Grupo 2 (PATH_INFO): /some/path
```

**Usado em:**
- APIs REST
- Frameworks (Laravel, Symfony)
- WordPress permalinks com index.php

**Variáveis criadas:**
- `$fastcgi_script_name` = Grupo 1
- `$fastcgi_path_info` = Grupo 2

#### fastcgi_pass

```nginx
fastcgi_pass wordpress:9000;
```

**Explicação:**
- Encaminha requisição para PHP-FPM
- **Endereço:** `wordpress:9000`
  - `wordpress` = Nome do serviço Docker (DNS)
  - `9000` = Porta PHP-FPM
- **Protocolo:** FastCGI (binário, rápido)

**Alternativas:**
```nginx
# Porta TCP
fastcgi_pass 127.0.0.1:9000;
fastcgi_pass wordpress:9000;  # Configuração atual

# Socket Unix (mesma máquina)
fastcgi_pass unix:/var/run/php-fpm.sock;

# Upstream (load balancing)
upstream php_backend {
    server wordpress1:9000;
    server wordpress2:9000;
}
fastcgi_pass php_backend;
```

#### fastcgi_index

```nginx
fastcgi_index index.php;
```

- **Descrição:** Ficheiro padrão quando URI termina em `/`
- **Exemplo:**
  ```
  URI: /admin/
  fastcgi_index: index.php
  Resultado: /admin/index.php
  ```

#### include fastcgi_params

```nginx
include fastcgi_params;
```

**Ficheiro:** `/etc/nginx/fastcgi_params`

**Conteúdo:**
```nginx
fastcgi_param  QUERY_STRING       $query_string;
fastcgi_param  REQUEST_METHOD     $request_method;
fastcgi_param  CONTENT_TYPE       $content_type;
fastcgi_param  CONTENT_LENGTH     $content_length;

fastcgi_param  SCRIPT_NAME        $fastcgi_script_name;
fastcgi_param  REQUEST_URI        $request_uri;
fastcgi_param  DOCUMENT_URI       $document_uri;
fastcgi_param  DOCUMENT_ROOT      $document_root;
fastcgi_param  SERVER_PROTOCOL    $server_protocol;
# ... muitos outros
```

**Função:** Define variáveis CGI/FastCGI enviadas ao PHP

#### fastcgi_param SCRIPT_FILENAME

```nginx
fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
```

**Mais importante:** Diz ao PHP qual ficheiro executar

**Variáveis:**
- `$document_root` = `/var/www/html` (do `root`)
- `$fastcgi_script_name` = `/index.php`
- **Resultado:** `/var/www/html/index.php`

**Sem isto:**
- PHP não sabe qual ficheiro executar
- Erro: "Primary script unknown"

#### fastcgi_param PATH_INFO

```nginx
fastcgi_param PATH_INFO $fastcgi_path_info;
```

- Passa `PATH_INFO` para PHP
- Usado por frameworks/APIs
- Permite roteamento interno

**Exemplo completo:**
```
Requisição: GET /api.php/users/123?format=json

NGINX processa:
  ├─ $fastcgi_script_name = /api.php
  ├─ $fastcgi_path_info = /users/123
  └─ $query_string = format=json

Envia para PHP:
  SCRIPT_FILENAME=/var/www/html/api.php
  PATH_INFO=/users/123
  QUERY_STRING=format=json

PHP acessa:
  $_SERVER['SCRIPT_FILENAME'] = /var/www/html/api.php
  $_SERVER['PATH_INFO'] = /users/123
  $_SERVER['QUERY_STRING'] = format=json
```

---

### Location ~ /\.ht (Security)

```nginx
location ~ /\.ht {
    deny all;
}
```

#### Explicação Detalhada

- **Padrão:** `~ /\.ht` (regex)
- **Match:** Qualquer ficheiro/diretório começando com `.ht`
- **Ação:** `deny all` (bloqueia acesso)

#### Ficheiros .ht

Ficheiros de configuração Apache (geralmente):
- `.htaccess` - Configuração de diretório
- `.htpasswd` - Passwords de autenticação
- `.htgroups` - Grupos de utilizadores

**Exemplo de .htaccess:**
```apache
RewriteEngine On
RewriteRule ^old-page$ /new-page [R=301]

# Database credentials (má prática!)
# DB_PASSWORD=secret123
```

#### Segurança

**Problema sem bloqueio:**
```
URL: https://site.com/.htaccess

Sem proteção:
  → NGINX serve ficheiro como texto
  → Atacante vê configuração/passwords
  → 🔴 VAZAMENTO DE DADOS

Com deny all:
  → 403 Forbidden
  → ✅ Protegido
```

#### Padrão Regex

```
/\.ht
│ ││
│ │└─ Literal 'ht'
│ └── Literal '.'
└──── Literal '/'
```

**Match:**
- `/.htaccess`
- `/admin/.htpasswd`
- `/config/.htgroups`

**Não match:**
- `/thermal.txt` (. não no início)
- `/hta.conf` (sem ponto)

#### Proteção Adicional

**Bloquear todos os ficheiros ocultos:**
```nginx
location ~ /\. {
    deny all;
}
```

**Match:**
- `.htaccess`
- `.env`
- `.git`
- `.svn`
- `.DS_Store`

**Exceto .well-known (para ACME/Let's Encrypt):**
```nginx
location ~ /\. {
    deny all;
}

location ~ /\.well-known {
    allow all;
}
```

---

### Location /myprofile/ (Reverse Proxy)

```nginx
location /myprofile/ {
    set $_myprofile_proxy "myprofile:8888";
    rewrite ^/myprofile/(.*) /$1 break;
    proxy_pass http://$_myprofile_proxy;
    proxy_set_header Host $host;
    proxy_set_header X-Forwarded-Host $host;
    proxy_set_header X-Real-IP $remote_addr;
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    proxy_set_header X-Forwarded-Proto $scheme;

    error_page 502 503 504 = /service-unavailable.html;
}
```

#### set $_myprofile_proxy

```nginx
set $_myprofile_proxy "myprofile:8888";
```

**Explicação:**
- Define variável `$_myprofile_proxy` com endereço do backend
- **Sintaxe:** `set $variavel "valor";`
- **Escopo:** Válida apenas neste location block
- **Uso:** Permite configuração dinâmica

**Vantagens de usar variável:**
```nginx
# ❌ Hard-coded
proxy_pass http://myprofile:8888;

# ✅ Com variável
set $_myprofile_proxy "myprofile:8888";
proxy_pass http://$_myprofile_proxy;
```

- **Manutenibilidade:** Fácil alterar endereço
- **Legibilidade:** Nome descritivo
- **Reutilização:** Mesmo padrão para múltiplos backends

#### rewrite

```nginx
rewrite ^/myprofile/(.*) /$1 break;
```

**Explicação:**
- **Função:** Remove prefixo `/myprofile/` do URI antes de enviar ao backend
- **Regex:** `^/myprofile/(.*)` - Captura tudo após `/myprofile/`
- **Substituição:** `/$1` - Mantém apenas a parte capturada
- **Flag:** `break` - Para processamento de rewrites

**Por que necessário?**

```
Servidor myprofile espera arquivos na raiz:
  /                  → index.html
  /styles.css        → styles.css
  /script.js         → script.js
```

**Sem rewrite:**
```
Cliente: https://nmatondo.42.fr/myprofile/styles.css
           ↓
NGINX: proxy_pass http://myprofile:8888
           ↓
Backend recebe: /myprofile/styles.css
           ↓
Backend procura: /var/www/myprofile/myprofile/styles.css
           ↓
❌ 404 Not Found (caminho incorreto!)
```

**Com rewrite:**
```
Cliente: https://nmatondo.42.fr/myprofile/styles.css
           ↓
NGINX: rewrite ^/myprofile/(.*) /$1 break;
       /myprofile/styles.css → /styles.css
           ↓
proxy_pass http://myprofile:8888
           ↓
Backend recebe: /styles.css
           ↓
Backend procura: /var/www/myprofile/styles.css
           ↓
✅ 200 OK (arquivo encontrado!)
```

**Regex Breakdown:**
```
^/myprofile/(.*)
│ │         │
│ │         └─ Captura grupo 1: tudo após /myprofile/
│ └──────────── Literal "/myprofile/"
└────────────── Início da string

/$1
│ │
│ └─ Substitui por conteúdo do grupo 1
└─── Adiciona / no início
```

**Exemplos:**

| URL Original | Após Rewrite |
|--------------|--------------|
| `/myprofile/` | `/` |
| `/myprofile/index.html` | `/index.html` |
| `/myprofile/styles.css` | `/styles.css` |
| `/myprofile/js/script.js` | `/js/script.js` |
| `/myprofile/api/users` | `/api/users` |

**Flag break:**
- Para processamento de rewrite rules
- Continua com proxy_pass
- Não testa outras location blocks

**Flags alternativos:**

| Flag | Comportamento |
|------|---------------|
| `break` | Para rewrites, continua no location atual |
| `last` | Para rewrites, refaz location matching |
| `redirect` | Retorna 302 redirect temporário |
| `permanent` | Retorna 301 redirect permanente |

**Ordem importa:**
```nginx
# ✅ CORRETO
set $_myprofile_proxy "myprofile:8888";
rewrite ^/myprofile/(.*) /$1 break;
proxy_pass http://$_myprofile_proxy;

# ❌ ERRO - rewrite antes de set pode causar problemas
rewrite ^/myprofile/(.*) /$1 break;
set $_myprofile_proxy "myprofile:8888";
proxy_pass http://$_myprofile_proxy;
```

#### proxy_pass (com rewrite)

```nginx
proxy_pass http://$_myprofile_proxy;
```

**Importante:** Sem barra final!

**Com rewrite:**
```nginx
rewrite ^/myprofile/(.*) /$1 break;
proxy_pass http://$_myprofile_proxy;  # Sem /
# ✅ Correto - URI já foi reescrito
```

**Sem rewrite (alternativa):**
```nginx
proxy_pass http://$_myprofile_proxy/;  # Com /
# Remove prefixo /myprofile/ automaticamente
# MAS não funciona bem com variáveis!
```

**Diferença sutil:**

| Configuração | Resultado |
|--------------|-----------|
| `proxy_pass http://backend;` | Envia URI completo |
| `proxy_pass http://backend/;` | Remove prefixo da location |
| `rewrite + proxy_pass (sem /)` | Envia URI reescrito |

#### proxy_set_header X-Forwarded-Host

```nginx
proxy_set_header X-Forwarded-Host $host;
```

**Novo header adicionado:**
- **Função:** Preserva hostname original através de múltiplos proxies
- **Valor:** `$host` (mesmo que header `Host`)
- **Uso:** Aplicações que precisam saber domínio original

**Diferença Host vs X-Forwarded-Host:**

```
Cliente → Proxy1 → Proxy2 → NGINX → Backend

Host: nmatondo.42.fr (sempre atual)
X-Forwarded-Host: nmatondo.42.fr (original preservado)
```

**Backend pode usar:**
```php
// Hostname original (mesmo após múltiplos proxies)
$original_host = $_SERVER['HTTP_X_FORWARDED_HOST'] ?? $_SERVER['HTTP_HOST'];
```

#### error_page

```nginx
error_page 502 503 504 = /service-unavailable.html;
```

**Explicação:**
- **502 Bad Gateway:** Backend não responde
- **503 Service Unavailable:** Backend sobrecarregado
- **504 Gateway Timeout:** Backend demorou demais
- **Ação:** Redireciona para página de erro customizada

**Funcionamento:**
```
Backend down (502/503/504)
          ↓
NGINX intercepta erro
          ↓
Serve /service-unavailable.html
          ↓
Cliente vê página amigável
```

**Sem error_page:**
```
Backend down → Cliente vê erro feio do NGINX
```

**Com error_page:**
```
Backend down → Cliente vê página customizada
```

**Exemplo visual:**
```
URL externa: https://nmatondo.42.fr/myprofile/about.html
              ↓
        NGINX location /myprofile/
              ↓
    set $_myprofile_proxy "myprofile:8888"
              ↓
    rewrite ^/myprofile/(.*) /$1 break
    /myprofile/about.html → /about.html
              ↓
    proxy_pass http://$_myprofile_proxy
              ↓
URL interna: http://myprofile:8888/about.html
              ↓
      Servidor myprofile processa
              ↓
   Resposta retorna para NGINX
              ↓
  NGINX retorna para cliente
```

#### proxy_set_header Host

```nginx
proxy_set_header Host $host;
```

**Função:** Define header `Host` enviado ao backend

**Valor `$host`:**
- Hostname da requisição original
- Exemplo: `nmatondo.42.fr`

**Importância:**
- Backend sabe qual domínio foi requisitado
- Essencial para virtual hosting
- WordPress/apps precisam para gerar URLs corretos

**Sem isto:**
```
Host: myprofile:8888
  ↓
App gera URLs: http://myprofile:8888/link
  ↓
🔴 Links quebrados para clientes
```

**Com isto:**
```
Host: nmatondo.42.fr
  ↓
App gera URLs: https://nmatondo.42.fr/myprofile/link
  ↓
✅ Links corretos
```

#### proxy_set_header X-Real-IP

```nginx
proxy_set_header X-Real-IP $remote_addr;
```

**Função:** Passa IP real do cliente para backend

**Variável `$remote_addr`:**
- IP do cliente que conectou ao NGINX
- Exemplo: `192.168.1.100`

**Por que necessário:**

Sem proxy:
```
Cliente (IP: 203.0.113.5) → Servidor
                              ↓
              Servidor vê: 203.0.113.5
```

Com proxy (sem X-Real-IP):
```
Cliente (IP: 203.0.113.5) → NGINX → Backend
                                      ↓
                    Backend vê: IP do NGINX (172.18.0.2)
                    🔴 IP real perdido!
```

Com proxy (com X-Real-IP):
```
Cliente (IP: 203.0.113.5) → NGINX → Backend
                              ↓
                  X-Real-IP: 203.0.113.5
                              ↓
                    Backend lê header
                    ✅ IP real preservado
```

**Uso no backend (PHP):**
```php
$client_ip = $_SERVER['HTTP_X_REAL_IP'] ?? $_SERVER['REMOTE_ADDR'];
```

#### proxy_set_header X-Forwarded-For

```nginx
proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
```

**Função:** Cadeia de proxies pelo qual requisição passou

**Variável `$proxy_add_x_forwarded_for`:**
- Adiciona `$remote_addr` ao header existente
- Cria lista separada por vírgulas

**Cadeia de proxies:**
```
Cliente → Proxy1 → Proxy2 → NGINX → Backend
(IP: A)   (IP: B)  (IP: C)   (IP: D)

X-Forwarded-For: A, B, C
```

**Exemplo:**
```
Requisição inicial:
  (sem X-Forwarded-For)

Passa por Proxy1 (NGINX 1):
  X-Forwarded-For: 203.0.113.5

Passa por NGINX (nossa config):
  X-Forwarded-For: 203.0.113.5, 10.0.1.50

Backend recebe:
  X-Forwarded-For: 203.0.113.5, 10.0.1.50
  [IP cliente]      [IP proxy intermediário]
```

**Diferença X-Real-IP vs X-Forwarded-For:**

| Header | Conteúdo | Uso |
|--------|----------|-----|
| `X-Real-IP` | IP único (cliente original) | Simples, direto |
| `X-Forwarded-For` | Lista de IPs (cadeia) | Auditoria, múltiplos proxies |

#### proxy_set_header X-Forwarded-Proto

```nginx
proxy_set_header X-Forwarded-Proto $scheme;
```

**Função:** Informa protocolo original (HTTP/HTTPS)

**Variável `$scheme`:**
- `http` ou `https`
- Baseado na requisição original

**Importância:**

```
Cliente HTTPS → NGINX (443) → Backend HTTP (8888)
                   ↓
         X-Forwarded-Proto: https
                   ↓
    Backend sabe que cliente usou HTTPS
                   ↓
  Gera URLs/redirects com https://
```

**Sem isto:**
```
Cliente: https://site.com/myprofile
          ↓
  NGINX → Backend (HTTP)
          ↓
Backend vê apenas HTTP
          ↓
Redireciona: http://site.com/login
          ↓
🔴 Downgrade para HTTP não seguro!
```

**Com isto:**
```
Backend verifica X-Forwarded-Proto: https
          ↓
Mantém protocolo seguro
          ↓
✅ Redireciona: https://site.com/login
```

**PHP usage:**
```php
$is_https = ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
if ($is_https) {
    // Force HTTPS URLs
}
```

---

### Location /adminer/ (Reverse Proxy)

```nginx
location /adminer/ {
    set $_adminer_proxy "adminer:8080";
    rewrite ^/adminer/(.*) /$1 break;
    proxy_pass http://$_adminer_proxy;
    proxy_set_header Host $host;
    proxy_set_header X-Forwarded-Host $host;
    proxy_set_header X-Real-IP $remote_addr;
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    proxy_set_header X-Forwarded-Proto $scheme;
    
    error_page 502 503 504 = /service-unavailable.html;
}
```

#### set $_adminer_proxy

```nginx
set $_adminer_proxy "adminer:8080";
```

**Explicação:**
- Define variável `$_adminer_proxy` com endereço do backend
- **Sintaxe:** `set $variavel "valor";`
- **Escopo:** Válida apenas neste location block
- **Uso:** Permite configuração dinâmica

**Vantagens de usar variável:**
```nginx
# ❌ Hard-coded
proxy_pass http://adminer:8080;

# ✅ Com variável
set $_adminer_proxy "adminer:8080";
proxy_pass http://$_adminer_proxy;
```

- **Manutenibilidade:** Fácil alterar endereço
- **Legibilidade:** Nome descritivo
- **Reutilização:** Mesmo padrão para múltiplos backends

#### rewrite (Adminer)

```nginx
rewrite ^/adminer/(.*) /$1 break;
```

**Funcionamento idêntico ao /myprofile/:**

```
Cliente: https://nmatondo.42.fr/adminer/
           ↓
rewrite: /adminer/ → /
           ↓
Backend recebe: /
           ↓
✅ Adminer index.php é servido
```

**Recursos estáticos do Adminer:**
```
/adminer/?file=default.css → /?file=default.css
/adminer/?file=functions.js → /?file=functions.js
```

Adminer usa query strings para recursos, então o rewrite preserva corretamente os parâmetros.

#### error_page

```nginx
error_page 502 503 504 = /service-unavailable.html;
```

**Explicação:**
- **502 Bad Gateway:** Backend não responde
- **503 Service Unavailable:** Backend sobrecarregado
- **504 Gateway Timeout:** Backend demorou demais
- **Ação:** Redireciona para página de erro customizada

**Funcionamento:**
```
Backend down (502/503/504)
          ↓
NGINX intercepta erro
          ↓
Serve /service-unavailable.html
          ↓
Cliente vê página amigável
```

**Sem error_page:**
```
Backend down → Cliente vê erro feio do NGINX
```

**Com error_page:**
```
Backend down → Cliente vê página customizada
```

#### rewrite (Adminer)

```nginx
rewrite ^/adminer/(.*) /$1 break;
```

**Funcionamento idêntico ao /myprofile/:**

```
Cliente: https://nmatondo.42.fr/adminer/
           ↓
rewrite: /adminer/ → /
           ↓
Backend recebe: /
           ↓
✅ Adminer index.php é servido
```

**Recursos estáticos do Adminer:**
```
/adminer/?file=default.css → /?file=default.css
/adminer/?file=functions.js → /?file=functions.js
```

Adminer usa query strings para recursos, então o rewrite preserva corretamente os parâmetros.

---

### Location = /service-unavailable.html (Error Page)

```nginx
location = /service-unavailable.html {
    root /usr/share/nginx/html;
    internal;
}
```

#### Location Modifier: =

```nginx
location = /service-unavailable.html { }
```

- **`=`**: Match exato (exact match)
- **Prioridade:** Mais alta possível
- **Match:** Apenas `/service-unavailable.html`
- **Não match:** `/service-unavailable.html?param=1`, `/other.html`

#### root

```nginx
root /usr/share/nginx/html;
```

- **Diretório:** `/usr/share/nginx/html`
- **Ficheiro servido:** `/usr/share/nginx/html/service-unavailable.html`

#### internal

```nginx
internal;
```

**Explicação:**
- **Função:** Bloqueia acesso direto à URL
- **Comportamento:** 
  - ✅ Acesso via error_page (interno)
  - ❌ Acesso direto do cliente (404)

**Por que internal?**

**Sem internal:**
```
Cliente: https://site.com/service-unavailable.html
          ↓
✅ NGINX serve página de erro diretamente
          ↓
Cliente vê página de erro sem erro real
```

**Com internal:**
```
Cliente: https://site.com/service-unavailable.html
          ↓
❌ 404 Not Found
          ↓
Cliente não consegue acessar diretamente

Backend down → error_page → internal location
          ↓
✅ Página de erro mostrada apenas quando necessário
```

**Uso típico:**
```nginx
# Página de erro customizada
location = /50x.html {
    root /usr/share/nginx/html;
    internal;
}

# Em qualquer location
error_page 500 502 503 504 /50x.html;
```

---

## Geração de Certificados SSL

**Ficheiro:** `srcs/requirements/nginx/tools/generate_certificates.sh`

### Script Completo

```bash
#!/bin/sh

SSL_DIR=/etc/nginx/ssl

mkdir -p $SSL_DIR

# Gerar certificado SSL auto-assinado
openssl req -x509 -nodes -days 365 \
    -newkey rsa:2048 \
    -keyout $SSL_DIR/privkey.pem \
    -out $SSL_DIR/fullchain.pem \
    -subj "/C=AO/ST=Luanda/L=Luanda/O=42Luanda/OU=Inception/CN=nmatondo.42.fr"

echo "✅ SSL certificates generated for nmatondo.42.fr"
```

### Integração com NGINX

Este script é executado durante o build do container NGINX e gera os certificados referenciados em:

```nginx
ssl_certificate     /etc/nginx/ssl/fullchain.pem;
ssl_certificate_key /etc/nginx/ssl/privkey.pem;
```

Para explicação detalhada do comando OpenSSL e cada campo do Subject DN, consultar [doc/WORDPRESS_CONFIG.md - Seção SSL Certificate Generation](WORDPRESS_CONFIG.md#nginx-ssl-certificate-generation).

---

## Fluxo Completo de Requisição

### Requisição HTTPS para WordPress

```
1. Cliente: https://nmatondo.42.fr/blog/my-post
                ↓
2. DNS: nmatondo.42.fr → 127.0.0.1 (hosts file)
                ↓
3. TCP: Connect to 127.0.0.1:443
                ↓
4. TLS Handshake:
   ├─ Cliente: ClientHello
   ├─ NGINX: ServerHello + Certificate (fullchain.pem)
   ├─ Cliente: Verifica certificado
   └─ Chaves de sessão negociadas
                ↓
5. NGINX: Recebe requisição HTTP encriptada
                ↓
6. NGINX: Match server_name nmatondo.42.fr ✅
                ↓
7. NGINX: Testa locations:
   ├─ /myprofile/ ❌
   ├─ /adminer/ ❌
   ├─ ~ \.php$ ❌ (não termina em .php)
   └─ / ✅ (catch-all)
                ↓
8. Location /:
   try_files /blog/my-post /blog/my-post/ /index.php?...
   ├─ /var/www/html/blog/my-post (ficheiro) ❌
   ├─ /var/www/html/blog/my-post/ (dir) ❌
   └─ Internal redirect → /index.php
                ↓
9. Location ~ \.php$: ✅ Match!
   ├─ try_files: /var/www/html/index.php ✅ Existe
   ├─ fastcgi_pass wordpress:9000
   └─ Headers: SCRIPT_FILENAME, PATH_INFO, etc.
                ↓
10. PHP-FPM (wordpress:9000):
    ├─ Executa /var/www/html/index.php
    ├─ WordPress router processa /blog/my-post
    ├─ Consulta database (mariadb:3306)
    ├─ Renderiza HTML
    └─ Retorna para NGINX
                ↓
11. NGINX:
    ├─ Recebe resposta do PHP-FPM
    ├─ Adiciona headers (Content-Type, etc.)
    ├─ Encripta com TLS
    └─ Envia para cliente
                ↓
12. Cliente:
    ├─ Desencripta resposta
    ├─ Renderiza HTML
    └─ 🎉 Página exibida!
```

---

## Troubleshooting

### Erro: "SSL: error:..."

**Causa:** Certificados inválidos ou não encontrados  
**Solução:** Verificar caminhos e executar `generate_certificates.sh`

### Erro: "Primary script unknown"

**Causa:** `SCRIPT_FILENAME` incorreto  
**Solução:** Verificar `fastcgi_param SCRIPT_FILENAME`

### Erro: "502 Bad Gateway"

**Causa:** PHP-FPM não responde  
**Soluções:**
- Verificar se WordPress container está rodando
- Verificar conectividade: `docker exec nginx ping wordpress`
- Verificar porta: `docker exec nginx nc -zv wordpress 9000`

### Erro: "403 Forbidden"

**Causas possíveis:**
- Permissões incorretas em `/var/www/html`
- Ficheiro index não encontrado
- Location com `deny all`

**Solução:**
```bash
docker exec nginx ls -la /var/www/html
docker exec wordpress chown -R www-data:www-data /var/www/html
```

### Erro: "Connection refused" ao proxy

**Causa:** Backend (myprofile/adminer) não acessível  
**Solução:** Verificar se containers backend estão rodando

---

## Otimizações de Performance

### HTTP/2

```nginx
listen 443 ssl http2;
```

**Benefícios:**
- Multiplexing (múltiplas requisições em uma conexão)
- Header compression
- Server push

### Gzip Compression

```nginx
http {
    gzip on;
    gzip_vary on;
    gzip_types text/plain text/css application/json application/javascript text/xml;
    gzip_min_length 1000;
}
```

### Caching

```nginx
location ~* \.(jpg|jpeg|png|gif|ico|css|js)$ {
    expires 1y;
    add_header Cache-Control "public, immutable";
}
```

### Rate Limiting

```nginx
http {
    limit_req_zone $binary_remote_addr zone=one:10m rate=10r/s;
    
    server {
        location / {
            limit_req zone=one burst=20;
        }
    }
}
```

---

## Security Headers

```nginx
add_header X-Frame-Options "SAMEORIGIN" always;
add_header X-Content-Type-Options "nosniff" always;
add_header X-XSS-Protection "1; mode=block" always;
add_header Referrer-Policy "no-referrer-when-downgrade" always;
add_header Content-Security-Policy "default-src 'self' https:; script-src 'self' 'unsafe-inline' 'unsafe-eval'; style-src 'self' 'unsafe-inline';" always;
```

---

## Referências

- [NGINX Documentation](https://nginx.org/en/docs/)
- [NGINX SSL/TLS Configuration](https://nginx.org/en/docs/http/configuring_https_servers.html)
- [FastCGI Module](https://nginx.org/en/docs/http/ngx_http_fastcgi_module.html)
- [Proxy Module](https://nginx.org/en/docs/http/ngx_http_proxy_module.html)
- [Mozilla SSL Configuration Generator](https://ssl-config.mozilla.org/)
- [SSL Labs](https://www.ssllabs.com/ssltest/)

---

**Última atualização:** Janeiro 2026  
**Versão:** 1.1  
**Autor:** Projeto Inception - 42 School
