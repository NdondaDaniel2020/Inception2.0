.PHONY: all build up down clean fclean re logs bonus bbuild bup bdown bclean bfclean bre blogs brestart bstatus bre

COMPOSE_BONUS_FILE = srcs/requirements/bonus/docker-compose.yml
BONUS_PROJECT_NAME = inception_bonus
COMPOSE_FILE = srcs/docker-compose.yml
PROJECT_NAME = inception
DATA_PATH = /home/nmatondo/data


all: build up

build:
	@echo "🔨 Building Docker images..."
	@mkdir -p $(DATA_PATH)/mariadb $(DATA_PATH)/wordpress
	@docker compose -p $(PROJECT_NAME) -f $(COMPOSE_FILE) build

up:
	@echo "🚀 Starting containers..."
	@docker compose -p $(PROJECT_NAME) -f $(COMPOSE_FILE) up -d

down:
	@echo "🛑 Stopping containers..."
	@docker compose -p $(PROJECT_NAME) -f $(COMPOSE_FILE) down

clean: down
	@echo "🧹 Cleaning containers..."
	@docker compose -p $(PROJECT_NAME) -f $(COMPOSE_FILE) down -v
	@docker system prune -af

fclean: clean
	@echo "🗑️  Removing all Docker data..."
	@docker stop $$(docker ps -qa) 2>/dev/null || true
	@docker rm $$(docker ps -qa) 2>/dev/null || true
	@docker rmi -f $$(docker images -qa) 2>/dev/null || true
	@docker volume rm $$(docker volume ls -q) 2>/dev/null || true
	@docker network rm $$(docker network ls -q) 2>/dev/null || true
	@rm -rf $(DATA_PATH)/mariadb $(DATA_PATH)/wordpress 2>/dev/null || true

logs:
	@docker compose -p $(PROJECT_NAME) -f $(COMPOSE_FILE) logs -f

restart:
	@echo "🔄 Restarting containers..."
	@docker compose -p $(PROJECT_NAME) -f $(COMPOSE_FILE) restart

status:
	@docker compose -p $(PROJECT_NAME) -f $(COMPOSE_FILE) ps

re: fclean all


bonus: bbuild bup

bbuild:
	@echo "🔨 Building Docker images..."
	@mkdir -p $(DATA_PATH)/mariadb $(DATA_PATH)/wordpress
	@docker compose -p $(BONUS_PROJECT_NAME) -f $(COMPOSE_BONUS_FILE) build

bup:
	@echo "🚀 Starting containers..."
	@docker compose -p $(BONUS_PROJECT_NAME) -f $(COMPOSE_BONUS_FILE) up -d

bdown:
	@echo "🛑 Stopping containers..."
	@docker compose -p $(BONUS_PROJECT_NAME) -f $(COMPOSE_BONUS_FILE) down

bclean: down
	@echo "🧹 Cleaning containers..."
	@docker compose -p $(BONUS_PROJECT_NAME) -f $(COMPOSE_BONUS_FILE) down -v
	@docker system prune -af

bfclean: clean
	@echo "🗑️  Removing all Docker data..."
	@docker stop $$(docker ps -qa) 2>/dev/null || true
	@docker rm $$(docker ps -qa) 2>/dev/null || true
	@docker rmi -f $$(docker images -qa) 2>/dev/null || true
	@docker volume rm $$(docker volume ls -q) 2>/dev/null || true
	@docker network rm $$(docker network ls -q) 2>/dev/null || true
	@rm -rf $(DATA_PATH)/mariadb $(DATA_PATH)/wordpress 2>/dev/null || true

blogs:
	@docker compose -p $(BONUS_PROJECT_NAME) -f $(COMPOSE_BONUS_FILE) logs -f

brestart:
	@echo "🔄 Restarting containers..."
	@docker compose -p $(BONUS_PROJECT_NAME) -f $(COMPOSE_BONUS_FILE) restart

bstatus:
	@docker compose -p $(BONUS_PROJECT_NAME) -f $(COMPOSE_BONUS_FILE) ps

bre: bfclean bonus
