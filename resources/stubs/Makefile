.PHONY: help up npm-dev down ps logs db redis restart clean artisan composer npm shell app

help:
	@echo "Comandos disponibles:"
	@echo ""
	@echo "  make init                 Inicializa el proyecto y ejecuta init.sh"
	@echo "  make up [servicios...]    Inicia los servicios indicados o todos"
	@echo "  make npm-dev              Reinicia y levanta Vite en modo desarrollo"
	@echo "  make down [servicios...]  Detiene los servicios indicados o todos"
	@echo "  make ps [servicios...]    Muestra el estado de los servicios"
	@echo "  make shell [contenedor]   Abre una shell; por defecto usa app"
	@echo "  make logs [servicios...]  Muestra los logs en tiempo real"
	@echo "  make restart [servicios...] Reinicia los servicios indicados o todos"
	@echo "  make clean [servicios...] Detiene servicios y elimina volumenes"
	@echo "  make artisan <comando>    Ejecuta un comando de Artisan en app"
	@echo "  make composer <comando>   Ejecuta un comando de Composer en app"
	@echo "  make npm <comando>        Ejecuta un comando de npm en app"
	@echo ""
	@echo "Ejemplos:"
	@echo "  make up app nginx pgsql redis"
	@echo "  make shell nginx"
	@echo "  make artisan migrate --seed"
	@echo "  make composer install"
	@echo "  make npm run build"

init:
	@if [ ! -f .env ]; then \
		cp .env.example .env; \
	fi
	docker compose run --rm app ./init.sh

up:
	@docker compose up -d $(filter-out $@,$(MAKECMDGOALS))
	@if docker compose ps --status running --services | grep -qx app; then \
		$(MAKE) npm-dev; \
	fi

npm-dev:
	@docker compose exec -d app npm run dev

down:
	@docker compose down $(filter-out $@,$(MAKECMDGOALS))

ps:
	@docker compose ps $(filter-out $@,$(MAKECMDGOALS))

shell:
	@docker compose exec -it $(or $(filter-out $@,$(MAKECMDGOALS)),app) sh


logs:
	@docker compose logs -f $(filter-out $@,$(MAKECMDGOALS))


restart:
	@docker compose restart $(filter-out $@,$(MAKECMDGOALS))

clean:
	@docker compose down -v $(filter-out $@,$(MAKECMDGOALS))

artisan: 
	@docker compose exec -it app php artisan $(filter-out $@,$(MAKECMDGOALS))

composer:
	docker compose exec -it app composer $(filter-out $@,$(MAKECMDGOALS))

npm:
	docker compose exec -it app npm $(filter-out $@,$(MAKECMDGOALS))

%:
	@:
