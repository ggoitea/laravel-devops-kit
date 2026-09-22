# Laravel DevOps Kit

[English](README.md) | [Español](README.es.md)

The fastest way to give a Laravel project a consistent Docker workflow for development and production.

From a single command, this kit adds the files and conventions needed to work locally, run the application in containers and connect the repository to a production pipeline. It includes development and production Dockerfiles, Docker Compose, Nginx, PHP configuration, shell scripts, a Makefile and a GitHub Actions workflow ready to adapt.

## The real advantage

This is not just a collection of Docker examples. It removes the repetitive setup work every Laravel project needs and keeps the team on the same workflow:

- **Bootstrap in seconds:** publish the complete Docker setup without creating each file by hand.
- **A ready-to-use development environment:** Laravel, PostgreSQL/PostGIS, Redis, Nginx and Vite, with MariaDB and MailHog available when needed.
- **One interface for daily work:** initialize the project, start services, open a container shell, run Artisan, Composer and npm through `make`.
- **Fewer permission problems:** the installer adds the host `UID` and `GID` to `.env` and `.env.example`, so the development container can run with the local user's identity.
- **A path to production:** the package includes a production Dockerfile and a GitHub Actions workflow that installs dependencies, builds assets, runs migrations and tests, builds and pushes the image, then deploys it through Docker Swarm.
- **Safe adoption:** existing files are preserved by default, local Compose overrides are ignored by Git, and `--force` is available when a project must be reset to the package defaults.

The result is a repeatable starting point for a new Laravel project and a practical way to standardize existing ones without rebuilding the DevOps setup from scratch.

## Requirements

- PHP `^8.2`
- [Composer](https://getcomposer.org/)
- [Docker](https://www.docker.com/) and Docker Compose

## Installation

Require the package with Composer:

```bash
composer require ggoitea/laravel-devops-kit
```

### As a dev dependency (VCS)

This package is meant for development tooling, so it is recommended to install it as a dev dependency. Since it is not published on Packagist yet, declare the repository in your project's `composer.json`:

```json
"repositories": [
    {
        "type": "vcs",
        "url": "https://github.com/ggoitea/laravel-devops-kit"
    }
]
```

Then require it with Composer:

```bash
composer require --dev ggoitea/laravel-devops-kit:dev-main
```

> Being a dev dependency does not require PHP on the machine that only runs the scripts: the published `devops-install.sh` is self-contained and works with just `sh`.

## Deploying the DevOps environment

Run the self-contained installer from the root of your Laravel project:

```bash
vendor/bin/devops-install.sh
```

It publishes the development and production Dockerfiles, `docker-compose.yml`, `docker-compose.override.yml`, `Makefile`, `devops.sh`, `init.sh`, the `docker/` configuration and `.github/workflows/produccion.yml`.

The installer also:

- adds `UID` and `GID` to `.env` and `.env.example` when those files exist;
- adds `docker-compose.override.yml` to `.gitignore` when `.gitignore` exists;
- creates the required directories and marks `devops.sh` and `init.sh` as executable.

If you prefer to invoke it through Laravel, the package also registers the Artisan command. It publishes the core Docker and Makefile files:

```bash
php artisan devops:install
```

Existing files are **preserved**. Both installers skip any file that already exists without overwriting it. To replace them explicitly:

```bash
php artisan devops:install --force
```

The `--force` flag overwrites every previously installed file, so use it when you want to reset your DevOps files to the package defaults.

> The published scripts (`devops.sh` and `init.sh`) are made executable automatically.

### Installing without PHP

If you don't have PHP installed on your machine, use the self-contained `devops-install.sh` script. It runs with `sh` only and installs the complete set of Docker, development and production files without requiring PHP or Composer.

If the package is installed via Composer, its binary is linked in `vendor/bin`:

```bash
vendor/bin/devops-install.sh
```

If you have the package source available locally, you can also run the script from inside your Laravel project:

```bash
sh devops-install.sh
```

Both methods produce the same files and respect the same rules — existing files are preserved unless you pass `--force`:

```bash
devops-install.sh --force
```

## Services

The stack is defined in `docker-compose.yml` and includes:

| Service   | Description                                                   | Starts by default    |
| --------- | ------------------------------------------------------------- | -------------------- |
| `app`     | Laravel application (development container)                   | Yes                  |
| `pgsql`   | PostgreSQL — the default database                             | Yes                  |
| `redis`   | Redis (cache, queues, sessions)                               | Yes                  |
| `nginx`   | Web server, exposes the app on port `8000` and Vite on `5173` | Yes                  |
| `mariadb` | MariaDB — alternative database                                | No (profile `tools`) |
| `mailhog` | Email catcher for development (UI on `8025`)                  | No (profile `tools`) |

`pgsql` is the **default database**. `mariadb` and `mailhog` belong to the `tools` profile, so they are not started by `make up`: you must start them explicitly when you need them (see below).

## Production workflow

The published `.github/workflows/produccion.yml` provides a production path for repositories using GitHub Actions. It runs on pushes to `main` or manually, installs PHP and frontend dependencies, prepares the environment, runs migrations and tests, builds the production image, pushes it to a private registry and deploys it with Docker Swarm over SSH.

Before using it, configure the `produccion` environment with these values:

- Variables: `REGISTRY_HOST`, `SSH_HOST`, `SSH_PORT`, `SSH_USER`, `STACK_PATH` and `STACK_NAME`.
- Secrets: `REGISTRY_PASSWORD` and `SSH_KEY_DEPLOY`.
- A self-hosted runner labelled `debian` with Docker access, plus the `ggoitea/laravel-ci` image used by the CI job.

The workflow is a production baseline, not a provider-specific deployment platform: adapt the image, registry, runner and deployment command to your infrastructure.

## Makefile usage

The `Makefile` includes these shortcuts:

```bash
make init          # Initialize the project (first time)
make up            # Start all default services
make down          # Stop all services
make ps            # Show service status
make logs          # Stream logs in real time
make restart       # Restart the services
make clean         # Stop services and delete volumes
make shell         # Open a shell in the app container
make artisan <cmd> # Run Artisan inside the app container
make composer <cmd># Run Composer inside the app container
make npm <cmd>     # Run npm inside the app container
make help          # Show all available commands
```

### make init

Use this the first time you start a project. It runs `init.sh` inside the `app` container and takes care of the bootstrap:

1. Copies `.env.example` to `.env` if the `.env` file does not exist yet.
2. Installs dependencies with `composer install`.
3. Generates the application key with `php artisan key:generate` when `APP_KEY` is empty.
4. Installs frontend dependencies with `npm install`.

```bash
make init
```

### make up

Starts the default services: `app`, `pgsql`, `redis` and `nginx`.

```bash
make up
```

You can also start only the services you need by passing them as arguments:

```bash
make up app nginx mariadb
```

This starts the `app`, `nginx` and `mariadb` containers only (for example, to use MariaDB instead of PostgreSQL). To start the services of the `tools` profile:

```bash
make up mariadb mailhog
```

When `app` is running, `make up` automatically launches Vite in development mode.

### make npm-dev

Restarts and launches Vite in development mode (it is triggered automatically by `make up`):

```bash
make npm-dev
```

### make down, ps, logs, restart and clean

All of them accept an optional list of services:

```bash
make down             # Stop all services
make down nginx       # Stop only nginx
make ps               # Status of all services
make logs             # Real-time logs of all services
make logs pgsql       # Real-time logs of pgsql
make restart          # Restart all services
make clean            # Stop services and remove volumes (-v)
```

> `make clean` deletes the container volumes, so the data in `pgsql`, `redis` and `mariadb` is lost.

### make shell

Opens an interactive shell inside the `app` container:

```bash
make shell            # Equivalent to: make shell app
make shell app
```

You can open a shell in any other service by passing it as an argument:

```bash
make shell pgsql
make shell nginx
make shell redis
```

### make artisan, composer and npm

Run commands inside the `app` container:

```bash
make artisan migrate
make artisan migrate --seed
make artisan tinker

make composer install
make composer require laravel/sanctum

make npm run build
make npm run dev
```

> `make npm run dev` and `make up` both start Vite; `make npm-dev` is the dedicated alias.

## Alternative: devops.sh

If you prefer not to use `make`, the published `devops.sh` provides the same basic operations:

```bash
./devops.sh build    # Build the images
./devops.sh up       # Start all services
./devops.sh down     # Stop all services
./devops.sh logs     # Stream logs in real time
```

## Development

Install dependencies and run the test suite:

```bash
composer install
composer test
```

## License

This package is released under the MIT License. See [LICENSE](LICENSE).
