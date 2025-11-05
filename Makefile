.PHONY: help build up down restart logs clean install test

help: ## Mostra esta mensagem de ajuda
	@echo "InventoX - Comandos disponíveis:"
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-15s\033[0m %s\n", $$1, $$2}'

build: ## Constrói os containers Docker
	docker-compose build

up: ## Inicia os containers
	docker-compose up -d

down: ## Para os containers
	docker-compose down

restart: ## Reinicia os containers
	docker-compose restart

logs: ## Mostra os logs dos containers
	docker-compose logs -f

clean: ## Remove containers e volumes (⚠️ apaga dados)
	docker-compose down -v
	rm -rf db_data

install: ## Instala o projeto completo
	@echo "Instalando InventoX..."
	@if [ ! -f .env ]; then \
		echo "Criando ficheiro .env..."; \
		echo "DB_HOST=mysql" > .env; \
		echo "DB_NAME=inventox" >> .env; \
		echo "DB_USER=inventox_user" >> .env; \
		echo "DB_PASS=change_me" >> .env; \
		echo "DB_PORT=3306" >> .env; \
	fi
	docker-compose up -d
	@echo "Aguardando MySQL iniciar..."
	@sleep 10
	@echo "Criando base de dados..."
	docker exec -i inventox_db mysql -uroot -proot inventox < db.sql
	@echo "Inserindo dados de exemplo (opcional)..."
	@if [ -f exemplo_dados.sql ]; then \
		docker exec -i inventox_db mysql -uroot -proot inventox < exemplo_dados.sql; \
	fi
	@echo "Instalando dependências Python..."
	pip install -r requirements.txt || pip3 install -r requirements.txt
	@echo ""
	@echo "✅ Instalação concluída!"
	@echo ""
	@echo "Acesse:"
	@echo "  - Frontend: http://localhost:8080/frontend"
	@echo "  - phpMyAdmin: http://localhost:8081"
	@echo ""
	@echo "Login padrão: admin / admin123"

db-reset: ## Reseta a base de dados (⚠️ apaga todos os dados)
	docker exec -i inventox_db mysql -uroot -proot -e "DROP DATABASE IF EXISTS inventox; CREATE DATABASE inventox;"
	docker exec -i inventox_db mysql -uroot -proot inventox < db.sql

db-seed: ## Insere dados de exemplo na base de dados
	@if [ -f exemplo_dados.sql ]; then \
		docker exec -i inventox_db mysql -uroot -proot inventox < exemplo_dados.sql; \
		echo "✅ Dados de exemplo inseridos!"; \
	else \
		echo "❌ Ficheiro exemplo_dados.sql não encontrado"; \
	fi

php-logs: ## Mostra logs do PHP
	docker-compose logs -f php-apache

mysql-logs: ## Mostra logs do MySQL
	docker-compose logs -f mysql

shell-php: ## Abre shell no container PHP
	docker exec -it inventox_web bash

shell-mysql: ## Abre shell no container MySQL
	docker exec -it inventox_db bash

mysql-cli: ## Abre cliente MySQL
	docker exec -it inventox_db mysql -uroot -proot inventox

status: ## Mostra status dos containers
	docker-compose ps

.PHONY: fly-login fly-launch fly-deploy fly-secrets

fly-login:
	@echo "==> Fly auth login" && fly auth login

fly-launch:
	@echo "==> Fly launch (accept defaults)" && fly launch --no-deploy --copy-config

fly-deploy:
	@echo "==> Fly deploy" && fly deploy

fly-secrets:
	@echo "==> Set Fly secrets (DB_*)" && \
	read -p "DB_HOST: " DB_HOST && \
	read -p "DB_NAME: " DB_NAME && \
	read -p "DB_USER: " DB_USER && \
	read -s -p "DB_PASS: " DB_PASS && echo "" && \
	fly secrets set DB_HOST=$$DB_HOST DB_NAME=$$DB_NAME DB_USER=$$DB_USER DB_PASS=$$DB_PASS

