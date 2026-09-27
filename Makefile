.PHONY: all build up down clean fclean re logs bonus bonus_build bonus_up

COMPOSE_FILE = srcs/docker-compose.yml
PROJECT_NAME = inception
DATA_PATH = ~/data

all: build up

bonus: bonus_build bonus_up

build:
	@echo "🔨 Building Docker images..."
	@mkdir -p $(DATA_PATH) $(DATA_PATH)/mariadb $(DATA_PATH)/wordpress
	@docker compose -p $(PROJECT_NAME) -f $(COMPOSE_FILE) build mariadb wordpress nginx

up:
	@echo "🚀 Starting containers..."
	@docker compose -p $(PROJECT_NAME) -f $(COMPOSE_FILE) up -d mariadb wordpress nginx

down:
	@echo "🛑 Stopping containers..."
	@docker compose -p $(PROJECT_NAME) -f $(COMPOSE_FILE) down

clean: down
	@echo "🧹 Cleaning project containers and volumes..."
	@docker compose -p $(PROJECT_NAME) -f $(COMPOSE_FILE) down -v

fclean: clean
	@echo "🗑️  Removing project images and data..."
	@docker compose -p $(PROJECT_NAME) -f $(COMPOSE_FILE) down --rmi all -v 2>/dev/null || true
	@rm -rf $(DATA_PATH)/mariadb $(DATA_PATH)/wordpress $(DATA_PATH)/redis $(DATA_PATH)/elasticsearch $(DATA_PATH)/myprofile 2>/dev/null || true

logs:
	@docker compose -p $(PROJECT_NAME) -f $(COMPOSE_FILE) logs -f

restart:
	@echo "🔄 Restarting containers..."
	@docker compose -p $(PROJECT_NAME) -f $(COMPOSE_FILE) restart

status:
	@docker compose -p $(PROJECT_NAME) -f $(COMPOSE_FILE) ps

bonus_build:
	@echo "🔨 Building Docker images..."
	@mkdir -p $(DATA_PATH) $(DATA_PATH)/mariadb $(DATA_PATH)/wordpress $(DATA_PATH)/redis $(DATA_PATH)/elasticsearch $(DATA_PATH)/myprofile
	@docker compose -p $(PROJECT_NAME) -f $(COMPOSE_FILE) build

bonus_up:
	@echo "🚀 Starting containers..."
	@docker compose -p $(PROJECT_NAME) -f $(COMPOSE_FILE) up -d 

re: fclean all

bre: fclean bonus
