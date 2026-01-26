# Configuração Redis - Documentação Técnica

Este documento fornece uma explicação detalhada de todas as configurações Redis utilizadas no projeto Inception, incluindo networking, autenticação, persistência e segurança.

**Versão utilizada:** Redis latest em Alpine Linux 3.22

---

## Índice

1. [Introdução ao Redis](#introdução-ao-redis)
2. [Configuração de Rede](#configuração-de-rede)
3. [Autenticação e Segurança](#autenticação-e-segurança)
4. [Persistência de Dados](#persistência-de-dados)
5. [Logging](#logging)
6. [Comandos Perigosos](#comandos-perigosos)
7. [Script Entrypoint](#script-entrypoint)
8. [Integração com WordPress](#integração-com-wordpress)

---

## Introdução ao Redis

### O que é Redis?

**Redis** (Remote Dictionary Server) é um armazenamento de estrutura de dados em memória, usado como:
- **Cache** de dados (uso principal no Inception)
- **Banco de dados** NoSQL
- **Message broker** (pub/sub)
- **Queue** de mensagens

### Características Principais

| Característica | Descrição |
|----------------|-----------|
| **In-Memory** | Dados armazenados em RAM (muito rápido) |
| **Persistência** | Opcionalmente salva em disco (RDB, AOF) |
| **Estruturas de Dados** | Strings, Hashes, Lists, Sets, Sorted Sets |
| **Atomic Operations** | Operações garantidas atômicas |
| **Replicação** | Master-slave replication |
| **Alta Performance** | Milhares de operações/segundo |

### Uso no WordPress

```
WordPress (PHP) → Redis Cache → Performance ⬆️
        ↓
    MariaDB (quando cache miss)
```

**Benefícios:**
- Reduz queries ao banco de dados
- Acelera carregamento de páginas
- Diminui carga no MariaDB
- Melhora experiência do utilizador

---

## Configuração de Rede

**Ficheiro:** `srcs/requirements/bonus/redis/conf/redis.conf`

### bind

```conf
bind 0.0.0.0
```

#### Explicação Detalhada

- **Diretiva:** `bind`
- **Valor:** `0.0.0.0`
- **Descrição:** Define em qual(is) interface(s) de rede Redis escuta conexões

#### O que significa 0.0.0.0?

```
0.0.0.0 = "Todas as interfaces de rede"
```

**Interfaces típicas:**
- `127.0.0.1` (localhost - apenas local)
- `192.168.1.10` (IP específico da rede)
- `172.18.0.5` (IP do container Docker)
- `0.0.0.0` (todas as interfaces)

#### Comparação de Valores

| Valor | Significado | Acesso | Uso |
|-------|-------------|--------|-----|
| `127.0.0.1` | Localhost apenas | Apenas mesmo container | ❌ Não funciona para Docker |
| `172.18.0.5` | IP específico | Apenas essa interface | ⚠️ IP pode mudar |
| `0.0.0.0` | Todas interfaces | Qualquer rede | ✅ Recomendado para Docker |
| `redis` | Nome do serviço | DNS Docker | ❌ Não válido para bind |

#### Como Funciona no Docker

```
┌─────────────────────────────────────────┐
│        Docker Network (bridge)          │
├─────────────────────────────────────────┤
│                                         │
│  ┌──────────────┐    ┌──────────────┐   │
│  │  WordPress   │───▶│    Redis     │   │
│  │ 172.18.0.3   │    │ 172.18.0.4   │   │
│  └──────────────┘    └──────────────┘   │
│                          │              │
│                     bind 0.0.0.0        │
│                     (escuta em          │
│                      172.18.0.4:6379)   │
└─────────────────────────────────────────┘
```

**Conexão:**
```
WordPress → redis:6379 → DNS resolve → 172.18.0.4:6379 → Redis aceita (0.0.0.0) ✅
```

#### Alternativas e Problemas

**bind 127.0.0.1 (NÃO FUNCIONA no Docker):**
```conf
bind 127.0.0.1
```
```
WordPress → redis:6379 → 172.18.0.4:6379 → Redis rejeita (só aceita 127.0.0.1) ❌
```

**bind múltiplos IPs:**
```conf
bind 127.0.0.1 172.18.0.4
```
- Aceita conexões em ambos IPs
- IP Docker pode mudar em restart
- Menos flexível que `0.0.0.0`

#### Segurança

**Preocupação comum:**
> "0.0.0.0 expõe Redis para a internet?"

**Resposta:** Não, em Docker com configuração correta!

**Camadas de segurança:**

1. **Docker Network (isolamento):**
   ```yaml
   networks:
     - network  # Rede privada interna
   ```
   - Apenas containers na mesma rede comunicam
   - Host externo não acessa

2. **Sem Port Mapping:**
   ```yaml
   redis:
     # NÃO expõe porta:
     # ports:
     #   - "6379:6379"  ← isto SIM seria inseguro
   ```
   - Porta 6379 não mapeada para host
   - Apenas interno ao Docker

3. **Firewall do Host:**
   - Docker gerencia iptables
   - Bloqueia tráfego externo

**Resultado:**
```
Internet → Firewall → ❌ Bloqueado
Docker Network → Redis → ✅ Permitido
```

---

### port

```conf
port 6379
```

#### Explicação Detalhada

- **Diretiva:** `port`
- **Valor:** `6379`
- **Descrição:** Porta TCP onde Redis escuta conexões

#### Por que porta 6379?

**Porta padrão oficial do Redis:**
- Registrada pela IANA (Internet Assigned Numbers Authority)
- Reconhecida universalmente
- Clientes Redis esperam esta porta por padrão

**Origem curiosa:**
```
6379 = Posição das teclas M, E, R, Z no teclado de celular
MERZ = Alessia Merz (atriz italiana, inspiração do nome)
```

#### Portas Comuns de Bancos de Dados

| Banco de Dados | Porta Padrão |
|----------------|--------------|
| Redis | 6379 |
| MariaDB/MySQL | 3306 |
| PostgreSQL | 5432 |
| MongoDB | 27017 |
| Elasticsearch | 9200 |
| Memcached | 11211 |

#### Alterando a Porta

```conf
# Porta customizada
port 7000
```

**Implicações:**
- Clientes precisam especificar porta: `redis:7000`
- WordPress config precisa atualizar
- Menos padronizado (confusão em manutenção)

**Múltiplas instâncias:**
```conf
# Redis Instance 1
port 6379

# Redis Instance 2 (mesmo servidor)
port 6380

# Redis Instance 3
port 6381
```

#### Desabilitar Porta TCP

```conf
port 0
```
- Desabilita listener TCP
- Requer socket Unix: `unixsocket /var/run/redis.sock`

#### Conexão no Docker

**WordPress conecta usando:**
```php
// wp-config.php
define('WP_REDIS_HOST', 'redis');
define('WP_REDIS_PORT', 6379);
```

**Docker DNS resolve:**
```
redis:6379 → 172.18.0.4:6379
```

**Redis escuta em:**
```
0.0.0.0:6379 → Aceita conexão ✅
```

---

## Autenticação e Segurança

### requirepass

```conf
requirepass REDIS_PASSWORD_PLACEHOLDER
```

#### Explicação Detalhada

- **Diretiva:** `requirepass`
- **Valor Inicial:** `REDIS_PASSWORD_PLACEHOLDER` (placeholder temporário)
- **Valor Final:** Password real (substituída pelo entrypoint)
- **Descrição:** Senha necessária para autenticar clientes

#### Como Funciona

**Sem autenticação (INSEGURO):**
```bash
redis-cli -h redis
> SET mykey "value"
OK  ← Qualquer um pode executar comandos!
```

**Com autenticação:**
```bash
redis-cli -h redis
> SET mykey "value"
(error) NOAUTH Authentication required  ← Bloqueado!

> AUTH mypassword
OK

> SET mykey "value"
OK  ← Agora funciona
```

#### Fluxo de Substituição da Password

```
1. Dockerfile copia redis.conf:
   requirepass REDIS_PASSWORD_PLACEHOLDER

2. Container inicia → executa entrypoint.sh

3. Entrypoint lê secret:
   REDIS_PASSWORD=$(cat /run/secrets/redis_password)
   REDIS_PASSWORD="minha_senha_super_secreta_123"

4. sed substitui no arquivo:
   sed -i "s/REDIS_PASSWORD_PLACEHOLDER/minha_senha_super_secreta_123/g"

5. redis.conf agora tem:
   requirepass minha_senha_super_secreta_123

6. Redis inicia com senha configurada
```

#### Formato da Password

**Recomendações:**
```
✅ Bom: "g7K$mP9@nQ2#xR5&"  (caracteres especiais, longo)
✅ Bom: "palavra-passante-complexa-2024"  (longo, memorável)
⚠️ Fraco: "password123"  (comum, curto)
❌ Muito fraco: "123456"  (trivial)
```

**Comprimento recomendado:**
- Mínimo: 16 caracteres
- Ideal: 32+ caracteres
- Geração: `openssl rand -base64 32`

#### Comandos AUTH

**Sintaxe:**
```
AUTH <password>
```

**Exemplo em redis-cli:**
```bash
redis-cli -h redis -p 6379
> AUTH minha_senha
OK
> PING
PONG
```

**Usando -a flag (não recomendado em produção):**
```bash
redis-cli -h redis -p 6379 -a "minha_senha"
Warning: Using a password with '-a' option on the command line interface may not be safe.
> PING
PONG
```

#### Conexão WordPress

**wp-config.php:**
```php
define('WP_REDIS_HOST', 'redis');
define('WP_REDIS_PORT', 6379);
define('WP_REDIS_PASSWORD', 'minha_senha_super_secreta_123');
```

**Plugin Redis Object Cache:**
```php
$redis = new Redis();
$redis->connect('redis', 6379);
$redis->auth('minha_senha_super_secreta_123');
$redis->set('key', 'value');
```

#### Segurança

**Por que usar requirepass?**

1. **Previne acesso não autorizado:**
   ```
   Sem password: Qualquer container na rede → Redis → ✅ Acesso total
   Com password: Container sem credenciais → Redis → ❌ Bloqueado
   ```

2. **Defesa em profundidade:**
   - Network isolation ✅
   - Sem port mapping ✅
   - Autenticação ✅
   - Comandos perigosos desabilitados ✅

3. **Compliance:**
   - Muitos padrões de segurança exigem autenticação
   - PCI DSS, HIPAA, etc.

**Limitações:**
- Password em texto plano na memória
- Sem suporte a múltiplos utilizadores (apenas uma password global)
- Redis 6+ tem ACL (Access Control Lists) para controlo granular

---

### protected-mode

```conf
protected-mode no
```

#### Explicação Detalhada

- **Diretiva:** `protected-mode`
- **Valor:** `no` (desabilitado)
- **Descrição:** Modo de proteção que restringe conexões remotas

#### O que é Protected Mode?

**Introduzido no Redis 3.2** para prevenir exposição acidental à internet.

**Regras do Protected Mode (quando `yes`):**

Redis aceita apenas conexões se:
1. Nenhuma password configurada (`requirepass`)
   **E**
2. Escutando apenas em localhost (`bind 127.0.0.1`)

Se qualquer condição falhar:
- Conexões remotas são **bloqueadas**
- Apenas localhost (`127.0.0.1`) permitido

#### Comportamento com Diferentes Configurações

| bind | requirepass | protected-mode | Resultado |
|------|-------------|----------------|-----------|
| `127.0.0.1` | - | `yes` | ✅ Local apenas |
| `127.0.0.1` | ✅ | `yes` | ✅ Local apenas |
| `0.0.0.0` | - | `yes` | ❌ Erro! Precisa password |
| `0.0.0.0` | ✅ | `yes` | ✅ Remoto com auth |
| `0.0.0.0` | ✅ | `no` | ✅ Remoto com auth |

#### Nossa Configuração

```conf
bind 0.0.0.0
requirepass REDIS_PASSWORD_PLACEHOLDER
protected-mode no
```

**Análise:**
- `bind 0.0.0.0` → Aceita conexões remotas
- `requirepass` → Autenticação obrigatória
- `protected-mode no` → Não bloqueia conexões remotas

**Por que `no`?**

1. **Docker Network:** Conexões são "remotas" do ponto de vista do Redis
   ```
   WordPress (172.18.0.3) → Redis (172.18.0.4)
                           ↑
                     Conexão "remota"
   ```

2. **Já temos password:** `requirepass` fornece segurança
3. **Network isolation:** Docker network já isola

**Com `protected-mode yes`:**
```
WordPress → redis:6379
           ↓
Redis vê: Conexão remota de 172.18.0.3
           ↓
protected-mode: "Bloqueado! Conexão remota sem bind localhost"
           ↓
❌ Connection refused
```

**Com `protected-mode no`:**
```
WordPress → redis:6379
           ↓
Redis: "Conexão remota, verifico password"
           ↓
AUTH minha_senha
           ↓
✅ Autenticado! Permite conexão
```

#### Quando Usar protected-mode yes?

**Cenário ideal:**
- Redis apenas local (mesmo servidor que aplicação)
- Sem Docker network
- Ambiente de desenvolvimento

**Exemplo:**
```conf
bind 127.0.0.1
protected-mode yes
# Sem requirepass (apenas local)
```

**Nosso cenário:**
- Redis em container separado
- Docker network
- Conexões "remotas" dentro da rede Docker
- **Solução:** `protected-mode no` + `requirepass`

---

## Persistência de Dados

### save (RDB Snapshots)

```conf
save 900 1
save 300 10
save 60 10000
```

#### Explicação Detalhada

- **Diretiva:** `save`
- **Formato:** `save <segundos> <mudanças>`
- **Descrição:** Cria snapshots (RDB) do banco de dados em disco

#### Sintaxe

```
save <tempo_em_segundos> <número_mínimo_de_alterações>
```

**Significado:** 
> "Salve se pelo menos N alterações ocorreram em T segundos"

#### Decompondo Nossa Configuração

```conf
save 900 1
```
- **900 segundos** = 15 minutos
- **1 alteração** = Pelo menos uma mudança
- **Regra:** Salva a cada 15 min se houver ≥1 mudança

```conf
save 300 10
```
- **300 segundos** = 5 minutos
- **10 alterações**
- **Regra:** Salva a cada 5 min se houver ≥10 mudanças

```conf
save 60 10000
```
- **60 segundos** = 1 minuto
- **10000 alterações**
- **Regra:** Salva a cada 1 min se houver ≥10000 mudanças

#### Lógica de Ativação

**Redis verifica todas as regras:**
```
SE (15 min passaram E ≥1 mudança) OU
   (5 min passaram E ≥10 mudanças) OU
   (1 min passaram E ≥10000 mudanças)
ENTÃO
   Executa BGSAVE (salva snapshot)
```

#### Exemplos Práticos

**Cenário 1: Blog de baixo tráfego**
```
00:00 - Redis inicia
00:05 - 1 post criado (1 mudança)
00:15 - 15 minutos passaram, 1 mudança → SAVE! ✅
```

**Cenário 2: E-commerce movimentado**
```
00:00 - Redis inicia
00:00-00:05 - 50 produtos adicionados ao carrinho (50 mudanças)
00:05 - 5 minutos passaram, 50 mudanças (≥10) → SAVE! ✅
```

**Cenário 3: Site viral**
```
00:00 - Redis inicia
00:00-00:01 - 15000 pageviews (15000 mudanças)
00:01 - 1 minuto passou, 15000 mudanças (≥10000) → SAVE! ✅
```

#### O que é RDB?

**RDB (Redis Database Backup):**
- Snapshot completo da memória
- Formato binário compacto
- Ficheiro: `dump.rdb`

**Conteúdo:**
```
dump.rdb
├─ Metadados (versão Redis, timestamp)
├─ Database 0
│  ├─ key1: value1
│  ├─ key2: value2
│  └─ ...
├─ Database 1
│  └─ ...
└─ Checksum
```

#### Comando BGSAVE

**Background Save:**
```
1. Redis fork() → cria processo filho
2. Processo filho escreve dump.rdb
3. Processo pai continua servindo requisições
4. Quando completo, substitui dump.rdb antigo
```

**Vantagens:**
- ✅ Não bloqueia operações
- ✅ Rápido
- ✅ Compacto

**Desvantagens:**
- ⚠️ Usa mais memória (fork duplica processo)
- ⚠️ Pode perder dados entre snapshots

#### Desabilitando Snapshots

**Para desabilitar todos os saves:**
```conf
save ""
```

**Ou comentar todas as linhas:**
```conf
# save 900 1
# save 300 10
# save 60 10000
```

**Quando desabilitar:**
- Cache puro (dados reconstruíveis)
- Performance crítica
- Dados efêmeros

**Nosso caso (WordPress cache):**
- ✅ Mantemos snapshots
- Cache persiste entre restarts
- Reduz tempo de warm-up

#### Persistência Alternativa: AOF

**Append-Only File (não configurado no nosso Redis):**
```conf
appendonly yes
appendfilename "appendonly.aof"
```

**Diferenças RDB vs AOF:**

| Aspeto | RDB (Snapshot) | AOF (Append-Only) |
|--------|----------------|-------------------|
| **Formato** | Binário (dump.rdb) | Texto (comandos) |
| **Performance** | Rápido | Mais lento |
| **Tamanho** | Compacto | Maior |
| **Durabilidade** | Pode perder dados | Mais durável |
| **Recovery** | Rápido | Lento |
| **Uso** | Cache, analytics | Dados críticos |

**Nosso projeto usa RDB (adequado para cache).**

---

### dir

```conf
dir /data
```

#### Explicação Detalhada

- **Diretiva:** `dir`
- **Valor:** `/data`
- **Descrição:** Diretório onde Redis salva ficheiros de persistência

#### Ficheiros Armazenados

```
/data/
├── dump.rdb          # Snapshot RDB
└── appendonly.aof    # AOF (se habilitado)
```

#### Por que /data?

**Convenção Docker:**
- Diretório padrão para dados persistentes
- Volume Docker montado aqui
- Separa dados de binários/configs

#### Configuração Docker Volume

**docker-compose.yml:**
```yaml
services:
  redis:
    volumes:
      - redis_data:/data

volumes:
  redis_data:
    driver: local
```

**Mapeamento:**
```
Host: /var/lib/docker/volumes/inception_redis_data/_data/
  ↕ (montado em)
Container: /data/
  ├─ dump.rdb
  └─ (outros ficheiros)
```

#### Permissões

**Redis precisa de:**
- Leitura (carregar dump.rdb)
- Escrita (salvar novos snapshots)
- Execução (acessar diretório)

**Permissões recomendadas:**
```bash
chown redis:redis /data
chmod 755 /data
```

**Verificar no container:**
```bash
docker exec redis ls -la /data
# Deve mostrar:
# drwxr-xr-x 2 redis redis 4096 Jan 14 10:30 .
```

#### Ciclo de Vida dos Dados

```
1. Container inicia:
   Redis lê /data/dump.rdb (se existir)
   Carrega dados na memória

2. Operações normais:
   SET cache:page1 "HTML..."
   SET cache:page2 "HTML..."
   (Dados na RAM)

3. Trigger save (ex: 15 min + 1 mudança):
   BGSAVE
   Escreve /data/dump.rdb

4. Container para:
   docker stop redis
   (dados permanecem em /data/)

5. Container reinicia:
   docker start redis
   Redis lê /data/dump.rdb
   Restaura cache ✅
```

#### Backup Manual

**Backup do volume:**
```bash
# Criar backup
docker run --rm \
  -v inception_redis_data:/data \
  -v $(pwd):/backup \
  alpine tar czf /backup/redis_backup.tar.gz /data

# Restaurar backup
docker run --rm \
  -v inception_redis_data:/data \
  -v $(pwd):/backup \
  alpine tar xzf /backup/redis_backup.tar.gz -C /
```

**Copiar dump.rdb:**
```bash
docker cp redis:/data/dump.rdb ./dump.rdb.backup
```

---

## Logging

### loglevel

```conf
loglevel notice
```

#### Explicação Detalhada

- **Diretiva:** `loglevel`
- **Valor:** `notice`
- **Descrição:** Nível de verbosidade dos logs

#### Níveis Disponíveis

| Nível | Descrição | Uso | Verbosidade |
|-------|-----------|-----|-------------|
| `debug` | Tudo (muito detalhado) | Debugging profundo | 🔊🔊🔊🔊 |
| `verbose` | Muita informação útil | Desenvolvimento | 🔊🔊🔊 |
| `notice` | Eventos importantes | **Produção** ✅ | 🔊🔊 |
| `warning` | Apenas avisos/erros | Produção silenciosa | 🔊 |

#### Exemplos de Logs por Nível

**debug:**
```
[12345] 14 Jan 2024 10:30:15.123 * DB loaded from disk: 0.001 seconds
[12345] 14 Jan 2024 10:30:15.124 - Accepted 172.18.0.3:45678
[12345] 14 Jan 2024 10:30:15.125 - Client connected
[12345] 14 Jan 2024 10:30:15.126 - Reading AUTH password
[12345] 14 Jan 2024 10:30:15.127 - AUTH successful
[12345] 14 Jan 2024 10:30:15.128 - Command: SET cache:key "value"
[12345] 14 Jan 2024 10:30:15.129 - Reply: +OK
```

**verbose:**
```
[12345] 14 Jan 2024 10:30:15.123 * DB loaded from disk: 0.001 seconds
[12345] 14 Jan 2024 10:30:15.124 - Accepted 172.18.0.3:45678
[12345] 14 Jan 2024 10:30:15.127 - Client authorized
```

**notice (configuração atual):**
```
[12345] 14 Jan 2024 10:30:00.000 # Server started, Redis version 7.2.0
[12345] 14 Jan 2024 10:30:00.001 * Ready to accept connections
[12345] 14 Jan 2024 10:30:15.123 * DB loaded from disk: 0.001 seconds
[12345] 14 Jan 2024 10:45:00.000 * 10 changes in 300 seconds. Saving...
[12345] 14 Jan 2024 10:45:00.123 * Background saving started by pid 12346
[12345] 14 Jan 2024 10:45:00.500 * DB saved on disk
```

**warning:**
```
[12345] 14 Jan 2024 10:30:00.000 # WARNING: no maxmemory configured
[12345] 14 Jan 2024 10:35:00.000 # WARNING: Disk full, cannot save!
```

#### Formato de Log

```
[PID] Timestamp Nível Mensagem
 │       │        │      │
[12345] 14 Jan ... * Ready to accept connections
```

**Símbolos:**
- `#` = Aviso importante
- `*` = Informação
- `-` = Debug/verbose
- `.` = Verbose

#### Configuração de Destino

**Log para stdout (Docker padrão):**
```conf
# Sem especificar logfile → stdout
```

**Log para ficheiro:**
```conf
logfile /var/log/redis/redis.log
```

**Desabilitar logs:**
```conf
loglevel warning
logfile /dev/null
```

#### Visualizar Logs no Docker

```bash
# Seguir logs em tempo real
docker logs -f redis

# Últimas 100 linhas
docker logs --tail 100 redis

# Logs com timestamps
docker logs -t redis

# Filtrar por texto
docker logs redis | grep "Saving"
```

#### Por que `notice` é Ideal?

**Produção:**
- ✅ Não sobrecarrega logs
- ✅ Captura eventos importantes
- ✅ Suficiente para monitorização
- ✅ Facilita troubleshooting

**Desenvolvimento:** Considerar `verbose`
**Debugging:** Considerar `debug`

---

## Comandos Perigosos

### rename-command

```conf
rename-command FLUSHDB ""
rename-command FLUSHALL ""
rename-command CONFIG ""
```

#### Explicação Detalhada

- **Diretiva:** `rename-command`
- **Formato:** `rename-command <comando_original> <novo_nome>`
- **Valor:** `""` (string vazia = desabilita comando)
- **Descrição:** Renomeia ou desabilita comandos perigosos

#### Sintaxe

**Desabilitar comando:**
```conf
rename-command COMANDO ""
```

**Renomear comando:**
```conf
rename-command COMANDO NOVO_NOME_SECRETO
```

#### Comandos Desabilitados

### FLUSHDB

```conf
rename-command FLUSHDB ""
```

**O que faz:**
- Apaga **TODOS** os dados do database atual (0-15)
- Irreversível
- Instantâneo

**Exemplo de uso (se habilitado):**
```bash
redis-cli
> SELECT 0
OK
> SET key1 "value1"
OK
> SET key2 "value2"
OK
> FLUSHDB
OK
> GET key1
(nil)  ← Tudo apagado!
```

**Por que desabilitar:**
- Erro humano: `FLUSHDB` acidental
- Ataque: Atacante com acesso apaga cache
- **Impacto:** Cache WordPress inteiro perdido → Performance degrada

**Efeito da desabilitação:**
```bash
redis-cli
> FLUSHDB
(error) ERR unknown command 'FLUSHDB'  ← Bloqueado ✅
```

---

### FLUSHALL

```conf
rename-command FLUSHALL ""
```

**O que faz:**
- Apaga **TODOS** os dados de **TODOS** os databases (0-15)
- Mais destrutivo que FLUSHDB
- Irreversível

**Exemplo (se habilitado):**
```bash
redis-cli
> SELECT 0
OK
> SET db0:key "value"
OK
> SELECT 1
OK
> SET db1:key "value"
OK
> FLUSHALL
OK
> SELECT 0
OK
> KEYS *
(empty list)  ← Tudo em todos os DBs apagado!
```

**Por que desabilitar:**
- Ainda mais perigoso que FLUSHDB
- Apaga dados de múltiplos databases
- Sem necessidade em ambiente WordPress (usa apenas DB 0)

---

### CONFIG

```conf
rename-command CONFIG ""
```

**O que faz:**
- Permite modificar configuração Redis **em runtime**
- Sem necessidade de restart
- Pode alterar configurações críticas

**Exemplos de uso (se habilitado):**
```bash
redis-cli

# Ver configuração atual
> CONFIG GET requirepass
1) "requirepass"
2) "minha_senha_secreta"

# Mudar configuração
> CONFIG SET requirepass "nova_senha"
OK

# Remover autenticação (PERIGO!)
> CONFIG SET requirepass ""
OK
```

**Configurações críticas alteráveis:**
```bash
# Desabilitar autenticação
CONFIG SET requirepass ""

# Mudar diretório de dados
CONFIG SET dir /tmp

# Desabilitar persistência
CONFIG SET save ""

# Expor na internet (se não isolado)
CONFIG SET protected-mode no
```

**Por que desabilitar:**

1. **Segurança:**
   ```
   Atacante com acesso:
     → CONFIG SET requirepass ""
     → Remove autenticação
     → Acesso total sem senha
   ```

2. **Estabilidade:**
   ```
   CONFIG SET maxmemory 1byte
     → Redis não pode armazenar nada
     → Cache quebrado
   ```

3. **Consistência:**
   - Mudanças via CONFIG não persistem em redis.conf
   - Restart reverte alterações
   - Confusão entre config file e runtime

**Efeito da desabilitação:**
```bash
redis-cli
> CONFIG GET requirepass
(error) ERR unknown command 'CONFIG'  ← Bloqueado ✅
```

#### Alternativa: Renomear em vez de Desabilitar

**Se precisar de acesso eventual:**
```conf
rename-command CONFIG "CONFIG_MeuSegredo123"
```

**Uso:**
```bash
# Comando original não funciona
> CONFIG GET requirepass
(error) ERR unknown command 'CONFIG'

# Nome secreto funciona
> CONFIG_MeuSegredo123 GET requirepass
1) "requirepass"
2) "minha_senha"
```

**Vantagens:**
- Comando disponível quando necessário
- Dificulta uso acidental
- Previne ataques automatizados

**Nossa escolha:** Desabilitar completamente (mais seguro)

---

#### Outros Comandos Potencialmente Perigosos

**Não desabilitados mas considerar:**

```conf
# DEBUG (comandos de debugging)
rename-command DEBUG ""

# SHUTDOWN (desliga Redis)
rename-command SHUTDOWN ""

# BGREWRITEAOF (pode consumir muito I/O)
rename-command BGREWRITEAOF ""

# SAVE (bloqueia servidor durante save)
rename-command SAVE ""
```

**Nossa configuração:** Apenas essenciais desabilitados (FLUSHDB, FLUSHALL, CONFIG)

---

## Script Entrypoint

**Ficheiro:** `srcs/requirements/bonus/redis/tools/entrypoint.sh`

### Script Completo Anotado

```bash
#!/bin/sh
set -e

# Ler senha do secret
if [ -f /run/secrets/redis_password ]; then
    REDIS_PASSWORD=$(cat /run/secrets/redis_password)
    # Substituir no arquivo de configuração
    sed -i "s/REDIS_PASSWORD_PLACEHOLDER/$REDIS_PASSWORD/g" /etc/redis/redis.conf
fi

# Iniciar Redis
exec redis-server /etc/redis/redis.conf
```

### Explicação Linha por Linha

#### Shebang

```bash
#!/bin/sh
```

- **Descrição:** Interpretador do script
- **Valor:** `/bin/sh` (POSIX shell)
- **Alpine:** Usa BusyBox `sh`

#### Error Handling

```bash
set -e
```

- **Descrição:** Termina script se qualquer comando falhar
- **Sem `-e`:** Script continua após erros
- **Com `-e`:** Falha imediata em erro

**Exemplo:**
```bash
# Sem set -e
cat /ficheiro/inexistente  # Falha mas continua
echo "Isto executa"

# Com set -e
cat /ficheiro/inexistente  # Falha e termina script aqui
echo "Isto NÃO executa"
```

#### Verificação do Secret

```bash
if [ -f /run/secrets/redis_password ]; then
```

- **`[ -f ... ]`:** Testa se ficheiro existe e é regular
- **Caminho:** `/run/secrets/redis_password`
  - Montado pelo Docker secrets
  - tmpfs (RAM - não em disco)

**Por que verificar?**
- Secret pode não existir (configuração incorreta)
- Previne erro ao ler ficheiro inexistente
- Permite funcionamento sem senha (development)

#### Leitura do Secret

```bash
REDIS_PASSWORD=$(cat /run/secrets/redis_password)
```

- **`$(...)`: Command substitution**
  - Executa `cat`
  - Captura output
  - Armazena em variável

**Conteúdo do secret:**
```
/run/secrets/redis_password:
minha_senha_super_secreta_123
```

**Resultado:**
```bash
REDIS_PASSWORD="minha_senha_super_secreta_123"
```

#### Substituição com sed

```bash
sed -i "s/REDIS_PASSWORD_PLACEHOLDER/$REDIS_PASSWORD/g" /etc/redis/redis.conf
```

**Decompondo o comando:**

**`sed`**: Stream editor
**`-i`**: In-place (edita ficheiro diretamente)
**`"s/OLD/NEW/g"`**: Substitui OLD por NEW globalmente

**Pattern:**
- `s/` = Substitute (substituir)
- `REDIS_PASSWORD_PLACEHOLDER` = Texto a encontrar
- `$REDIS_PASSWORD` = Variável com novo texto
- `/g` = Global (todas as ocorrências na linha)

**Antes (redis.conf):**
```conf
requirepass REDIS_PASSWORD_PLACEHOLDER
```

**Variável:**
```bash
REDIS_PASSWORD="minha_senha_super_secreta_123"
```

**Comando sed expande para:**
```bash
sed -i "s/REDIS_PASSWORD_PLACEHOLDER/minha_senha_super_secreta_123/g" /etc/redis/redis.conf
```

**Depois (redis.conf):**
```conf
requirepass minha_senha_super_secreta_123
```

#### Por que sed em vez de ENV?

**Não funciona:**
```conf
# redis.conf
requirepass $REDIS_PASSWORD  ← Redis não expande variáveis!
```

**Redis não suporta:**
- Variáveis de ambiente em redis.conf
- Substituição de variáveis
- Interpolação

**Solução:** sed substitui antes do Redis ler o ficheiro

#### Inicialização do Redis

```bash
exec redis-server /etc/redis/redis.conf
```

**`exec`**: Substitui processo atual
- Shell script (PID 1) → Substituído por redis-server
- redis-server torna-se PID 1 do container

**Por que `exec`?**

**Sem exec:**
```
PID 1: sh entrypoint.sh
  └─ PID 2: redis-server  ← Processo filho
```
- Sinais (SIGTERM) vão para shell
- Shell pode não repassar para Redis
- Shutdown não gracioso

**Com exec:**
```
PID 1: redis-server  ← Processo principal
```
- Sinais vão diretamente para Redis
- Redis trata SIGTERM corretamente
- Shutdown gracioso (salva dados)

**`/etc/redis/redis.conf`**: Caminho do ficheiro de configuração

#### Fluxo Visual Completo

```
1. Container inicia → Executa entrypoint.sh
                    ↓
2. Verifica /run/secrets/redis_password
   ├─ Existe? → Continua
   └─ Não existe? → Pula substituição (sem senha)
                    ↓
3. Lê conteúdo do secret:
   REDIS_PASSWORD="minha_senha_super_secreta_123"
                    ↓
4. Substitui placeholder em redis.conf:
   requirepass REDIS_PASSWORD_PLACEHOLDER
         ↓ (sed)
   requirepass minha_senha_super_secreta_123
                    ↓
5. Inicia Redis com configuração atualizada:
   exec redis-server /etc/redis/redis.conf
                    ↓
6. Redis lê configuração:
   ✓ bind 0.0.0.0
   ✓ port 6379
   ✓ requirepass minha_senha_super_secreta_123
   ✓ save 900 1, 300 10, 60 10000
   ✓ dir /data
                    ↓
7. Redis carrega dump.rdb (se existir):
   Restaura cache do backup
                    ↓
8. Redis pronto para conexões:
   ✅ Listening on 0.0.0.0:6379
```

---

## Integração com WordPress

### Plugin Redis Object Cache

**Plugin:** Redis Object Cache (by Till Krüss)

**Instalação:**
```bash
wp plugin install redis-cache --activate --allow-root
```

### Configuração wp-config.php

**Adicionar ao wp-config.php:**
```php
// Redis Configuration
define('WP_REDIS_HOST', 'redis');
define('WP_REDIS_PORT', 6379);
define('WP_REDIS_PASSWORD', 'minha_senha_super_secreta_123');
define('WP_REDIS_DATABASE', 0);
define('WP_REDIS_TIMEOUT', 1);
define('WP_REDIS_READ_TIMEOUT', 1);
```

**Explicação das constantes:**

```php
define('WP_REDIS_HOST', 'redis');
```
- Nome do serviço Docker
- DNS resolve para IP do container Redis

```php
define('WP_REDIS_PORT', 6379);
```
- Porta Redis (padrão)

```php
define('WP_REDIS_PASSWORD', 'minha_senha_super_secreta_123');
```
- Password configurada em `requirepass`
- Deve corresponder ao secret

```php
define('WP_REDIS_DATABASE', 0);
```
- Redis database número (0-15)
- WordPress usa database 0

```php
define('WP_REDIS_TIMEOUT', 1);
define('WP_REDIS_READ_TIMEOUT', 1);
```
- Timeouts de conexão em segundos
- Previne bloqueio se Redis não responder

### Ativar Cache

**Via WP-CLI:**
```bash
wp redis enable --allow-root
```

**Via Admin:**
1. Login em WordPress admin
2. Settings → Redis
3. Click "Enable Object Cache"

### Verificar Funcionamento

**WP-CLI:**
```bash
wp redis status --allow-root
```

**Output esperado:**
```
Status: Connected
Client: PhpRedis (v5.3.7)
Redis: 7.2.0
Uptime: 1 day 5 hours
Used Memory: 2.45 MB
Cached Objects: 1,234
```

**Redis CLI:**
```bash
docker exec redis redis-cli -a "minha_senha"
> INFO stats
# Stats
total_connections_received:42
total_commands_processed:15678
instantaneous_ops_per_sec:15
```

### Tipos de Dados Cacheados

**WordPress armazena em Redis:**

1. **Object Cache:**
   ```
   Key: wp:options:alloptions
   Value: Serialized array de opções WordPress
   ```

2. **Database Queries:**
   ```
   Key: wp:posts:1
   Value: Dados do post ID 1
   ```

3. **Transients:**
   ```
   Key: wp:transient:feed_123
   Value: Cache de RSS feed
   ```

4. **User Sessions:**
   ```
   Key: wp:user_meta:1
   Value: Metadados do utilizador
   ```

### Benefícios de Performance

**Sem Redis:**
```
Request → WordPress → MariaDB query → Response (200ms)
```

**Com Redis (cache hit):**
```
Request → WordPress → Redis get → Response (5ms)
                         ↓
                    40x mais rápido!
```

**Com Redis (cache miss):**
```
Request → WordPress → MariaDB query → Response
                         ↓
                   Salva em Redis para próxima vez
```

---

## Monitorização e Debug

### Comandos Úteis

**Verificar conexão:**
```bash
docker exec redis redis-cli -a "minha_senha" PING
# Output: PONG
```

**Ver todas as chaves:**
```bash
docker exec redis redis-cli -a "minha_senha" KEYS '*'
```

**Estatísticas:**
```bash
docker exec redis redis-cli -a "minha_senha" INFO stats
docker exec redis redis-cli -a "minha_senha" INFO memory
```

**Monitorizar comandos em tempo real:**
```bash
docker exec redis redis-cli -a "minha_senha" MONITOR
```

**Verificar configuração:**
```bash
# Ver todas configs (comando CONFIG desabilitado no nosso caso)
# Alternativa: ler ficheiro
docker exec redis cat /etc/redis/redis.conf
```

### Troubleshooting

**Erro: "NOAUTH Authentication required"**
```
Causa: Password incorreta ou não configurada
Solução: Verificar secrets/redis_password.txt e wp-config.php
```

**Erro: "Connection refused"**
```
Causa: Redis não está rodando ou porta incorreta
Solução: docker ps | grep redis
```

**Erro: "Could not connect to Redis"**
```
Causa: Network issue ou nome do host incorreto
Solução: docker exec wordpress ping redis
```

---

## Referências

- [Redis Documentation](https://redis.io/documentation)
- [Redis Configuration](https://redis.io/docs/management/config/)
- [Redis Persistence](https://redis.io/docs/management/persistence/)
- [Redis Security](https://redis.io/docs/management/security/)
- [WordPress Redis Object Cache](https://wordpress.org/plugins/redis-cache/)
- [Docker Secrets](https://docs.docker.com/engine/swarm/secrets/)

---

**Última atualização:** Janeiro 2026  
**Versão:** 1.0  
**Autor:** Projeto Inception - 42 School
