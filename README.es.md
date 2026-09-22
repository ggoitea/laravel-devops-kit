# Laravel DevOps Kit

[English](README.md) | [Español](README.es.md)

La forma más rápida de añadir un flujo Docker coherente para desarrollo y producción a cualquier proyecto Laravel.

Con un solo comando, este kit incorpora los archivos y las convenciones necesarias para trabajar en local, ejecutar la aplicación dentro de contenedores y conectarla con un pipeline de producción. Incluye Dockerfiles de desarrollo y producción, Docker Compose, Nginx, configuración de PHP, scripts shell, un Makefile y un workflow de GitHub Actions listo para adaptar.

## La ventaja real

No es solo una colección de ejemplos de Docker. El kit elimina el trabajo repetitivo que necesita cada proyecto Laravel y mantiene al equipo en el mismo flujo:

- **Arranque en segundos:** publica todo el entorno Docker sin crear cada archivo manualmente.
- **Entorno de desarrollo listo:** Laravel, PostgreSQL/PostGIS, Redis, Nginx y Vite, con MariaDB y MailHog disponibles cuando hagan falta.
- **Una única interfaz para el día a día:** inicializa el proyecto, levanta servicios, abre una shell en un contenedor y ejecuta Artisan, Composer y npm mediante `make`.
- **Menos problemas de permisos:** el instalador añade el `UID` y el `GID` del usuario anfitrión a `.env` y `.env.example`, para que el contenedor de desarrollo use su misma identidad.
- **Camino hacia producción:** incluye un Dockerfile de producción y un workflow de GitHub Actions que instala dependencias, compila assets, ejecuta migraciones y pruebas, construye y publica la imagen y finalmente despliega mediante Docker Swarm.
- **Adopción segura:** los archivos existentes se conservan por defecto, los overrides locales de Compose se excluyen de Git y `--force` permite restaurar los valores del paquete cuando sea necesario.

El resultado es un punto de partida repetible para proyectos Laravel nuevos y una forma práctica de estandarizar proyectos existentes sin reconstruir el setup DevOps desde cero.

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

Ejecuta el instalador autocontenido desde la raíz de tu proyecto Laravel:

```bash
vendor/bin/devops-install.sh
```

Publica los Dockerfiles de desarrollo y producción, `docker-compose.yml`, `docker-compose.override.yml`, `Makefile`, `devops.sh`, `init.sh`, la configuración de `docker/` y `.github/workflows/produccion.yml`.

Además, el instalador:

- añade `UID` y `GID` a `.env` y `.env.example` cuando esos archivos existen;
- añade `docker-compose.override.yml` a `.gitignore` cuando `.gitignore` existe;
- crea los directorios necesarios y marca `devops.sh` e `init.sh` como ejecutables.

Si prefieres invocarlo desde Laravel, el paquete también registra el comando Artisan. Este publica los archivos Docker y Makefile principales:

```bash
php artisan devops:install
```

Los archivos existentes se **conservan**. Ambos instaladores omiten cualquier archivo que ya exista sin sobrescribirlo. Para reemplazarlos explícitamente:

```bash
php artisan devops:install --force
```

La opción `--force` sobrescribe todos los archivos previamente instalados, úsala cuando quieras restaurar los archivos DevOps a los valores por defecto del paquete.

> Los scripts publicados (`devops.sh` e `init.sh`) se marcan como ejecutables automáticamente.

### Instalación sin PHP

Si no tienes PHP instalado en tu máquina, usa el script autocontenido `devops-install.sh`. Funciona únicamente con `sh` e instala el conjunto completo de archivos Docker, desarrollo y producción sin depender de PHP ni Composer.

Si el paquete está instalado vía Composer, su binario queda enlazado en `vendor/bin`:

```bash
vendor/bin/devops-install.sh
```

Si tienes disponible el código fuente del paquete, también puedes ejecutar el script desde dentro de tu proyecto Laravel:

```bash
sh devops-install.sh
```

Ambos métodos generan los mismos archivos y respetan las mismas reglas: los archivos existentes se conservan salvo que pases `--force`:

```bash
devops-install.sh --force
```

## Servicios

El stack se define en `docker-compose.yml` e incluye:

| Servicio  | Descripción                                                      | Arranca por defecto |
| --------- | ---------------------------------------------------------------- | ------------------- |
| `app`     | Aplicación Laravel (contenedor de desarrollo)                    | Sí                  |
| `pgsql`   | PostgreSQL — la base de datos por defecto                        | Sí                  |
| `redis`   | Redis (cache, colas, sesiones)                                   | Sí                  |
| `nginx`   | Servidor web, expone la app en el puerto `8000` y Vite en `5173` | Sí                  |
| `mariadb` | MariaDB — base de datos alternativa                              | No (perfil `tools`) |
| `mailhog` | Capturador de correo para desarrollo (UI en `8025`)              | No (perfil `tools`) |

`pgsql` es la base de datos **por defecto**. `mariadb` y `mailhog` pertenecen al perfil `tools`, así que `make up` no los arranca: debes iniciarlos explícitamente cuando los necesites (ver más abajo).

## Workflow de producción

El workflow publicado en `.github/workflows/produccion.yml` ofrece un camino de producción para repositorios que usan GitHub Actions. Se ejecuta al hacer push a `main` o manualmente, instala las dependencias de PHP y frontend, prepara el entorno, ejecuta migraciones y pruebas, construye la imagen de producción, la publica en un registro privado y la despliega mediante Docker Swarm por SSH.

Antes de usarlo, configura el entorno `produccion` con estos valores:

- Variables: `REGISTRY_HOST`, `SSH_HOST`, `SSH_PORT`, `SSH_USER`, `STACK_PATH` y `STACK_NAME`.
- Secretos: `REGISTRY_PASSWORD` y `SSH_KEY_DEPLOY`.
- Un runner self-hosted etiquetado como `debian` con acceso a Docker, además de la imagen `ggoitea/laravel-ci` usada por el job de CI.

El workflow es una base de producción, no una plataforma de despliegue específica: adapta la imagen, el registro, el runner y el comando de despliegue a tu infraestructura.

## Uso del Makefile

El `Makefile` incluye estos atajos:

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
