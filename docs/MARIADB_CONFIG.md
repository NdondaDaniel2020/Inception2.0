# Configuração do MariaDB - Documentação Técnica

Este documento explica em detalhe todas as configurações e parâmetros utilizados no script de entrada (entrypoint) do MariaDB no projeto Inception.

**Versão utilizada:** MariaDB latest em Alpine Linux 3.23

---

## Índice

1. [Estrutura do Script](#estrutura-do-script)
2. [Leitura de Docker Secrets](#leitura-de-docker-secrets)
3. [Inicialização da Base de Dados](#inicialização-da-base-de-dados)
4. [Configuração de Usuários e Permissões](#configuração-de-usuários-e-permissões)
5. [Importação do Dump SQL](#importação-do-dump-sql)
6. [Inicialização do MariaDB](#inicialização-do-mariadb)

---

## Estrutura do Script

**Ficheiro:** `srcs/requirements/mariadb/tools/entrypoint.sh`

O script executa as seguintes etapas em ordem:

1. Leitura de Docker Secrets
2. Verificação se é a primeira execução
3. Inicialização temporária do MariaDB
4. Configuração de usuários e base de dados
5. Importação do dump SQL
6. Inicialização final do MariaDB

### Shebang e Error Handling

```bash
#!/bin/sh
set -e
```

- **`#!/bin/sh`**: Interpretador do script (shell POSIX)
  - Alpine Linux usa BusyBox `sh` (mais leve que bash)
  - Compatível com scripts básicos
  
- **`set -e`**: Modo de erro rigoroso
  - Script termina imediatamente se qualquer comando falhar (exit code ≠ 0)
  - Exceções: Comandos em condicionais (`if`, `while`) ou com `|| true`

---

## Leitura de Docker Secrets

```bash
# Ler senhas dos secrets
MARIADB_ROOT_PASSWORD=$(cat /run/secrets/db_root_password)
MARIADB_PASSWORD=$(cat /run/secrets/db_password)
```

**Explicação por Componente:**
- **`/run/secrets/db_root_password`**: Caminho onde Docker monta secrets
  - `/run/secrets/` é um `tmpfs` (sistema de ficheiros em RAM)
  - Apenas containers com permissão conseguem ler
  
- **`$(cat /run/secrets/db_root_password)`**: Command substitution
  - Executa `cat` e captura o output como valor da variável
  - Resultado: Password do root disponível como variável de ambiente

**Docker Secrets vs Environment Variables:**
| Aspeto | Docker Secrets | Environment Variables |
|--------|----------------|----------------------|
| **Armazenamento** | tmpfs (RAM) | Processo / ficheiro |
| **Visibilidade** | Ficheiro protegido | `docker inspect`, logs |
| **Rotação** | Sem rebuild | Requer restart |
| **Segurança** | ✅ Alta | ⚠️ Moderada |

---

## Inicialização da Base de Dados

```bash
if [ ! -d "/var/lib/mysql/${MARIADB_DATABASE}" ]; then
    echo "📦 First run - Initializing database..."
    
    # Iniciar MariaDB temporariamente
    mysqld --user=mysql --datadir=/var/lib/mysql --skip-networking --skip-grant-tables &
    pid=$!
```

**Explicação por Componente:**
- **`[ ! -d "/var/lib/mysql/${MARIADB_DATABASE}" ]`**: Testa se diretório NÃO existe
  - `-d`: Verdadeiro se é um diretório
  - `!`: Negação
  - Se diretório existe, significa que BD já foi inicializada

- **`mysqld --user=mysql --datadir=/var/lib/mysql --skip-networking --skip-grant-tables &`**:
  - `mysqld`: Daemon do MariaDB/MySQL
  - `--user=mysql`: Executa como utilizador 'mysql'
  - `--datadir=/var/lib/mysql`: Diretório de dados
  - `--skip-networking`: Desativa conexões de rede (apenas socket local)
  - `--skip-grant-tables`: Ignora tabelas de permissões (acesso total)
  - `&`: Executa em background
  - `pid=$!`: Captura PID do processo em background

**Porquê iniciar temporariamente?**
- MariaDB precisa estar executando para executar comandos SQL
- `--skip-grant-tables` permite acesso sem autenticação
- Permite configuração inicial antes de aplicar segurança

```bash
# Aguardar inicialização
for i in $(seq 30); do
    mysqladmin ping --silent 2>/dev/null && break
    sleep 1
done
```

**Explicação por Componente:**
- **`for i in $(seq 30)`**: Loop de 1 a 30
  - `seq 30`: Gera números de 1 a 30
  - Executa até 30 tentativas

- **`mysqladmin ping --silent`**: Testa se MariaDB está respondendo
  - `--silent`: Não mostra output
  - Retorna exit code 0 se MariaDB está ativo

- **`2>/dev/null`**: Redireciona stderr para /dev/null
- **`&& break`**: Se ping bem-sucedido, sai do loop
- **`sleep 1`**: Pausa de 1 segundo entre tentativas

---

## Configuração de Usuários e Permissões

```bash
mariadb <<-EOSQL
    FLUSH PRIVILEGES;
    ALTER USER 'root'@'localhost' IDENTIFIED BY '${MARIADB_ROOT_PASSWORD}';
    CREATE USER IF NOT EXISTS 'root'@'%' IDENTIFIED BY '${MARIADB_ROOT_PASSWORD}';
    GRANT ALL PRIVILEGES ON *.* TO 'root'@'%' WITH GRANT OPTION;
    CREATE DATABASE IF NOT EXISTS ${MARIADB_DATABASE};
    CREATE USER IF NOT EXISTS '${MARIADB_USER}'@'%' IDENTIFIED BY '${MARIADB_PASSWORD}';
    GRANT ALL PRIVILEGES ON ${MARIADB_DATABASE}.* TO '${MARIADB_USER}'@'%';
    FLUSH PRIVILEGES;
EOSQL
```

**Explicação por Comando:**

### FLUSH PRIVILEGES;
- **Descrição:** Recarrega tabelas de permissões da memória
- **Quando usar:** Após mudanças em usuários/privilegios
- **Importante:** Sempre executar após alterações

### ALTER USER 'root'@'localhost' IDENTIFIED BY '${MARIADB_ROOT_PASSWORD}';
- **Descrição:** Altera password do usuário root local
- **'root'@'localhost'**: Usuário root apenas para conexões locais
- **Segurança:** Root local mantém acesso administrativo

### CREATE USER IF NOT EXISTS 'root'@'%' IDENTIFIED BY '${MARIADB_ROOT_PASSWORD}';
- **Descrição:** Cria usuário root para conexões remotas
- **'%'**: Qualquer host (remoto)
- **IF NOT EXISTS**: Evita erro se usuário já existir

### GRANT ALL PRIVILEGES ON *.* TO 'root'@'%' WITH GRANT OPTION;
- **Descrição:** Concede todos os privilégios ao root remoto
- **ON *.* **: Em todas as bases de dados e tabelas
- **WITH GRANT OPTION**: Permite que root conceda privilégios a outros

### CREATE DATABASE IF NOT EXISTS ${MARIADB_DATABASE};
- **Descrição:** Cria a base de dados principal se não existir
- **${MARIADB_DATABASE}**: Nome da BD (variável de ambiente)

### CREATE USER IF NOT EXISTS '${MARIADB_USER}'@'%' IDENTIFIED BY '${MARIADB_PASSWORD}';
- **Descrição:** Cria usuário específico da aplicação
- **'${MARIADB_USER}'@'%'**: Usuário WordPress com acesso remoto

### GRANT ALL PRIVILEGES ON ${MARIADB_DATABASE}.* TO '${MARIADB_USER}'@'%';
- **Descrição:** Concede privilégios apenas na BD específica
- **ON ${MARIADB_DATABASE}.***: Apenas nesta base de dados
- **Princípio de menor privilégio:** Usuário não acessa outras BDs

---

## Importação do Dump SQL

```bash
# Importar dump no database correto
echo "📥 Importing dump.sql..."
{
    echo "USE ${MARIADB_DATABASE};"
    cat /docker-entrypoint-initdb.d/dump.sql
} | mariadb
```

**Explicação por Componente:**
- **`echo "USE ${MARIADB_DATABASE};"`**: Comando SQL para selecionar BD
- **`cat /docker-entrypoint-initdb.d/dump.sql`**: Lê conteúdo do dump
- **`{ ... } | mariadb`**: Pipeline - envia comandos para mariadb client

**O que é dump.sql?**
- Ficheiro com estrutura e dados iniciais da BD
- Criado durante desenvolvimento
- Contém tabelas, dados de exemplo, configurações

**Localização:**
- **Docker:** `/docker-entrypoint-initdb.d/` é diretório especial
- MariaDB automaticamente executa scripts SQL neste diretório na primeira inicialização
- **Este script:** Faz importação manual para garantir ordem correta

---

## Inicialização do MariaDB

```bash
# Parar MariaDB temporário
kill $pid
wait $pid
```

**Explicação:**
- **`kill $pid`**: Termina processo temporário
- **`wait $pid`**: Aguarda processo terminar completamente

```bash
# Iniciar MariaDB em foreground
echo "🚀 Starting MariaDB..."
exec mysqld --user=mysql --datadir=/var/lib/mysql --bind-address=0.0.0.0 --port=3306
```

**Explicação por Componente:**
- **`exec`**: Substitui processo atual pelo comando
  - Shell script (PID 1) é substituído por `mysqld`
  - Container Docker mantém-se ativo enquanto mysqld executa

- **`mysqld --user=mysql --datadir=/var/lib/mysql --bind-address=0.0.0.0 --port=3306`**:
  - `--user=mysql`: Utilizador do sistema
  - `--datadir=/var/lib/mysql`: Diretório de dados
  - `--bind-address=0.0.0.0`: Aceita conexões de qualquer IP
  - `--port=3306`: Porta padrão MySQL/MariaDB

**Porquê foreground?**
- Docker containers precisam de processo em foreground
- Logs vão para stdout (visíveis via `docker logs`)
- Sinal de parada do container mata o processo corretamente

---

## Sumário do Processo

### Primeira Execução:
1. ✅ Ler secrets (passwords)
2. ✅ Verificar se BD existe (não existe)
3. ✅ Iniciar MariaDB temporário (--skip-grant-tables)
4. ✅ Aguardar inicialização (até 30s)
5. ✅ Configurar usuários e BD via SQL
6. ✅ Importar dump.sql
7. ✅ Parar MariaDB temporário
8. ✅ Iniciar MariaDB final (foreground)

### Execuções Subsequentes:
1. ✅ Ler secrets (passwords)
2. ✅ Verificar se BD existe (existe)
3. ✅ Pular inicialização
4. ✅ Iniciar MariaDB final diretamente

---

## Segurança e Boas Práticas

### Usuários Criados:
- **`root@localhost`**: Acesso administrativo local
- **`root@%`**: Acesso administrativo remoto (⚠️ cuidado em produção)
- **`${MARIADB_USER}@%`**: Usuário aplicação com privilégios limitados

### Recomendações de Segurança:
- ❌ Remover `root@%` em produção
- ✅ Usar senhas fortes
- ✅ Limitar hosts de conexão
- ✅ Aplicar princípio de menor privilégio
- ✅ Manter MariaDB atualizado

### Volumes Persistentes:
- **`/var/lib/mysql`**: Dados da BD
  - Deve ser volume Docker persistente
  - Sobrevive a restarts/recriação de containers
  - Backup regular recomendado

---

## Troubleshooting

### Problemas Comuns:

#### "Access denied for user"
- Verificar se secrets estão montados corretamente
- Confirmar nomes dos secrets no docker-compose.yml

#### "Database doesn't exist"
- Verificar variável `MARIADB_DATABASE`
- Confirmar se volume está persistente

#### "Can't connect to local MySQL server"
- Aguardar inicialização completa
- Verificar se porta 3306 está livre
- Confirmar permissões do diretório `/var/lib/mysql`

#### Dump não importado
- Verificar se `dump.sql` existe em `/docker-entrypoint-initdb.d/`
- Confirmar sintaxe SQL no dump
- Verificar logs do container