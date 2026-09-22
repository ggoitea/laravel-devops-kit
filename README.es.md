# Laravel DevOps Kit

[English](README.md) | [Español](README.es.md)

Kit de herramientas DevOps para añadir un entorno dockerizado completo a tus proyectos Laravel en minutos.

En lugar de configurar a mano el `Dockerfile`, el `docker-compose.yml`, un `Makefile` y scripts shell, este paquete publica todo listo para usar: un Makefile con las tareas más habituales (`init`, `up`, `down`, `shell`, `artisan`, ...) y un stack con PostgreSQL por defecto, Redis, Nginx, Vite y servicios opcionales (MariaDB y MailHog).

## Requisitos

- PHP `^8.2`
- [Composer](https://getcomposer.org/)
- [Docker](https://www.docker.com/) y Docker Compose

## Instalación

Instala el paquete con Composer:

```bash
composer require ggoitea/laravel-devops-kit
```

### Como dependencia de desarrollo (VCS)

Este paquete es tooling de desarrollo, por lo que se recomienda instalarlo como dependencia de desarrollo. Como aún no está publicado en Packagist, declara el repositorio en el `composer.json` de tu proyecto:

```json
"repositories": [
    {
        "type": "vcs",
        "url": "https://github.com/ggoitea/laravel-devops-kit"
    }
]
```

Y luego instálalo con Composer:

```bash
composer require --dev ggoitea/laravel-devops-kit:dev-main
```

> Ser dependencia de desarrollo no obliga a tener PHP solo para ejecutar los scripts: el `devops-install.sh` publicado es autocontenido y funciona únicamente con `sh`.

## Instalación del entorno DevOps

Ejecuta el comando Artisan dentro de tu proyecto Laravel para publicar el `Dockerfile`, `docker-compose.yml`, `Makefile`, `devops.sh`, `init.sh` y el directorio `docker/`:

```bash
php artisan devops:install
```

Los archivos existentes se **conservan**. El instalador omite cualquier archivo que ya exista en tu proyecto sin sobrescribirlo. Para reemplazarlos explícitamente:

```bash
php artisan devops:install --force
```

La opción `--force` sobrescribe todos los archivos previamente instalados, úsala cuando quieras restaurar los archivos DevOps a los valores por defecto del paquete.

> Los scripts publicados (`devops.sh` e `init.sh`) se marcan como ejecutables automáticamente.

### Instalación sin PHP

Si no tienes PHP instalado en tu máquina, usa el script autocontenido `devops-install.sh`. Replica el comando Artisan `devops:install` sin depender de PHP ni Composer.

Si el paquete está instalado vía Composer, su binario queda enlazado en `vendor/bin`:

```bash
vendor/bin/devops-install.sh
```

También puedes descargarlo por separado y ejecutarlo desde dentro de tu proyecto Laravel:

```bash
sh devops-install.sh
```

Ambos generan los mismos archivos y respetan las mismas reglas: los archivos existentes se conservan salvo que pases `--force`:

```bash
devops-install.sh --force
```

## Servicios

El stack se define en `docker-compose.yml` e incluye:

| Servicio  | Descripción                                   | Arranca por defecto |
|-----------|-----------------------------------------------|---------------------|
| `app`     | Aplicación Laravel (contenedor de desarrollo) | Sí                  |
| `pgsql`   | PostgreSQL — la base de datos por defecto     | Sí                  |
| `redis`   | Redis (cache, colas, sesiones)                | Sí                  |
| `nginx`   | Servidor web, expone la app en el puerto `8000` y Vite en `5173` | Sí |
| `mariadb` | MariaDB — base de datos alternativa           | No (perfil `tools`) |
| `mailhog` | Capturador de correo para desarrollo (UI en `8025`) | No (perfil `tools`) |

`pgsql` es la base de datos **por defecto**. `mariadb` y `mailhog` pertenecen al perfil `tools`, así que `make up` no los arranca: debes iniciarlos explícitamente cuando los necesites (ver más abajo).

## Uso del Makefile

Resumen de los atajos que encontrarás en el `Makefile`:

```bash
make init          # Inicializa el proyecto (primera vez)
make up            # Levanta todos los servicios por defecto
make down          # Detiene todos los servicios
make ps            # Muestra el estado de los servicios
make logs          # Muestra los logs en tiempo real
make restart       # Reinicia los servicios
make clean         # Detiene los servicios y elimina los volúmenes
make shell         # Abre una shell en el contenedor app
make artisan <cmd> # Ejecuta Artisan en el contenedor app
make composer <cmd># Ejecuta Composer en el contenedor app
make npm <cmd>     # Ejecuta npm en el contenedor app
make help          # Muestra todos los comandos disponibles
```

### make init

Úsalo la primera vez que inicies un proyecto. Ejecuta `init.sh` dentro del contenedor `app` y se encarga del arranque:

1. Copia `.env.example` a `.env` si el archivo `.env` aún no existe.
2. Instala las dependencias con `composer install`.
3. Genera la clave de aplicación con `php artisan key:generate` cuando `APP_KEY` está vacía.
4. Instala las dependencias del frontend con `npm install`.

```bash
make init
```

### make up

Levanta los servicios por defecto: `app`, `pgsql`, `redis` y `nginx`.

```bash
make up
```

También puedes levantar únicamente los servicios que necesites pasándolos como argumento:

```bash
make up app nginx mariadb
```

Esto arranca solo los contenedores `app`, `nginx` y `mariadb` (por ejemplo, para usar MariaDB en lugar de PostgreSQL). Para iniciar los servicios del perfil `tools`:

```bash
make up mariadb mailhog
```

Cuando `app` está en ejecución, `make up` lanza automáticamente Vite en modo desarrollo.

### make npm-dev

Reinicia y levanta Vite en modo desarrollo (se dispara automáticamente con `make up`):

```bash
make npm-dev
```

### make down, ps, logs, restart y clean

Todos aceptan una lista opcional de servicios:

```bash
make down             # Detiene todos los servicios
make down nginx       # Detiene únicamente nginx
make ps               # Estado de todos los servicios
make logs             # Logs en tiempo real de todos los servicios
make logs pgsql       # Logs en tiempo real de pgsql
make restart          # Reinicia todos los servicios
make clean            # Detiene los servicios y elimina volúmenes (-v)
```

> `make clean` elimina los volúmenes de los contenedores, por lo que se pierden los datos de `pgsql`, `redis` y `mariadb`.

### make shell

Abre una shell interactiva dentro del contenedor `app`:

```bash
make shell            # Equivalente a: make shell app
make shell app
```

Puedes abrir una shell en cualquier otro servicio pasándolo como argumento:

```bash
make shell pgsql
make shell nginx
make shell redis
```

### make artisan, composer y npm

Ejecuta comandos dentro del contenedor `app`:

```bash
make artisan migrate
make artisan migrate --seed
make artisan tinker

make composer install
make composer require laravel/sanctum

make npm run build
make npm run dev
```

> `make npm run dev` y `make up` levantan Vite; `make npm-dev` es el alias dedicado.

## Alternativa: devops.sh

Si prefieres no usar `make`, el `devops.sh` publicado ofrece las mismas operaciones básicas:

```bash
./devops.sh build    # Construye las imágenes
./devops.sh up       # Levanta todos los servicios
./devops.sh down     # Detiene todos los servicios
./devops.sh logs     # Muestra los logs en tiempo real
```

## Desarrollo

Instala las dependencias y ejecuta la suite de pruebas:

```bash
composer install
composer test
```

## Licencia

Este paquete se distribuye bajo la licencia MIT. Consulta [LICENSE](LICENSE).