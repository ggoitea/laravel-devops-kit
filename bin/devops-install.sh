#!/usr/bin/env sh

set -eu

FORCE=false
HELP=false
TARGET=

while [ "$#" -gt 0 ]; do
    case "$1" in
        --force) FORCE=true ;;
        -h|--help) HELP=true ;;
        *)
            if [ -z "$TARGET" ]; then
                TARGET="$1"
            else
                printf 'Uso: %s [--force] [destino]\n' "$0" >&2
                exit 1
            fi
            ;;
    esac
    shift
done

if [ "$HELP" = true ]; then
    cat <<'EOF'
Uso: devops-install.sh [--force] [destino]

Copia los archivos Docker, Makefile y scripts del paquete (resources/stubs/)
al proyecto actual, respetando su estructura. No sobrescribe archivos
existentes a menos que se use --force.

Opciones:
  --force   Sobrescribe los archivos que ya existen.
  destino   Directorio donde instalar (por defecto: directorio actual).
EOF
    exit 0
fi

SCRIPT="$0"
while [ -h "$SCRIPT" ]; do
    LINK="$(readlink "$SCRIPT")"
    case "$LINK" in
        /*) SCRIPT="$LINK" ;;
        *) SCRIPT="$(dirname "$SCRIPT")/$LINK" ;;
    esac
done
SCRIPT_DIR="$(cd "$(dirname "$SCRIPT")" && pwd)"

TARGET="${TARGET:-$(pwd)}"

if [ ! -d "$TARGET" ]; then
    printf 'Error: el destino %s no existe.\n' "$TARGET" >&2
    exit 1
fi

add_if_missing() {
    FILE="$1"
    VAR="$2"
    VALUE="$3"

    if [ -f "$FILE" ] && ! grep -q "^${VAR}=" "$FILE"; then
        printf '%s=%s\n' "$VAR" "$VALUE" >> "$FILE"
    fi
}

UID_VALUE="$(id -u)"
GID_VALUE="$(id -g)"

add_if_missing "$TARGET/.env.example" "UID" "$UID_VALUE"
add_if_missing "$TARGET/.env.example" "GID" "$GID_VALUE"

add_if_missing "$TARGET/.env" "UID" "$UID_VALUE"
add_if_missing "$TARGET/.env" "GID" "$GID_VALUE"

if [ -f "$TARGET/.gitignore" ] && ! grep -Fxq "docker-compose.override.yml" "$TARGET/.gitignore"; then
    printf '%s\n' "docker-compose.override.yml" >> "$TARGET/.gitignore"
fi

STUBS_DIR="$SCRIPT_DIR/../resources/stubs"

if [ ! -d "$STUBS_DIR" ]; then
    STUBS_DIR="$PWD/resources/stubs"
fi

if [ ! -d "$STUBS_DIR" ]; then
    printf 'Error: no se encontro el directorio de stubs.\n' >&2
    exit 1
fi

installed=0
skipped=0

IFS='
'
for source in $(find "$STUBS_DIR" -type f); do
    rel=${source#${STUBS_DIR}/}
    target="$TARGET/$rel"

    if [ -e "$target" ] && [ "$FORCE" = false ]; then
        printf 'Omitido: %s ya existe.\n' "$rel"
        skipped=$((skipped + 1))
        continue
    fi

    mkdir -p "$(dirname "$target")"

    if ! cp "$source" "$target"; then
        printf 'Error: no se pudo instalar %s\n' "$rel" >&2
        exit 1
    fi

    case "$rel" in
        devops.sh|init.sh) chmod 0755 "$target" ;;
    esac

    printf 'Instalado: %s\n' "$rel"
    installed=$((installed + 1))
done
unset IFS

printf 'Listo. %d instalado(s), %d omitido(s).\n' "$installed" "$skipped"