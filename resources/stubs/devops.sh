#!/usr/bin/env sh

set -eu

case "${1:-up}" in
    build)
        docker compose build
        ;;
    up)
        docker compose up -d
        ;;
    down)
        docker compose down
        ;;
    logs)
        docker compose logs -f
        ;;
    *)
        printf 'Uso: %s {build|up|down|logs}\n' "$0"
        exit 1
        ;;
esac
