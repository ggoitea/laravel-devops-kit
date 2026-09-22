#!/usr/bin/env sh
#
# generate-devops-install.sh — regenera bin/devops-install.sh desde
# resources/stubs/. Uso: sh bin/generate-devops-install.sh
#
set -eu

ROOT=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)
STUBS="$ROOT/resources/stubs"
OUT="$ROOT/bin/devops-install.sh"

FILES="
Dockerfile
docker-compose.yml
Makefile
devops.sh
init.sh
docker/Dockerfile.dev
docker/Dockerfile.prod
docker/nginx/default.conf
docker/php/php.ini-development
docker/php/php.ini-production
.dockerignore
"

for f in $FILES; do
    if [ ! -f "$STUBS/$f" ]; then
        printf 'Falta el stub: %s\n' "$STUBS/$f" >&2
        exit 1
    fi
done

{
cat <<'PREAMBLE_EOF'
#!/usr/bin/env sh
#
# devops-install.sh — publica los archivos DevOps del paquete laravel-devops-kit
# sin necesidad de PHP ni Composer.
#
# Replica el comportamiento de `php artisan devops:install`.
#
# ARCHIVO GENERADO: no lo edites a mano. Regenera con:
#   sh bin/generate-devops-install.sh
# Cada archivo se copia de resources/stubs/ cuando el paquete está presente;
# si no (script usado de forma autónoma), se instala la copia incrustada en
# los heredocs (que el generador mantiene idéntica a los stubs).
#
# Uso: devops-install.sh [--force] [directorio]
#   --force        Sobrescribir archivos existentes
#   directorio     Destino (por defecto: directorio actual)

set -eu

FORCE=0
TARGET="${1:-.}"

usage() {
    cat <<'EOF'
Uso: devops-install.sh [--force] [directorio]

Publica los archivos Docker y Makefile de laravel-devops-kit en un proyecto.

Opciones:
  --force   Sobrescribir archivos existentes
  -h, --help  Muestra esta ayuda

Argumentos:
  directorio  Destino de instalación (por defecto: directorio actual)

Ejemplos:
  devops-install.sh            # Instala en el directorio actual
  devops-install.sh --force    # Reemplaza archivos existentes
  devops-install.sh /tmp/demo  # Instala en /tmp/demo
EOF
}

for arg in "$@"; do
    case "$arg" in
        --force)
            FORCE=1
            ;;
        -h|--help)
            usage
            exit 0
            ;;
        -*)
            printf 'Opción desconocida: %s\nUsa --help para más información.\n' "$arg" >&2
            exit 1
            ;;
        *)
            TARGET="$arg"
            ;;
    esac
done

if [ ! -d "$TARGET" ]; then
    printf 'Error: el directorio no existe: %s\n' "$TARGET" >&2
    exit 1
fi

CURRENT_SCRIPT="$0"
if command -v readlink >/dev/null 2>&1; then
    resolved=$(readlink -f "$CURRENT_SCRIPT" 2>/dev/null) || {
        resolved=$(readlink "$CURRENT_SCRIPT" 2>/dev/null) || resolved="$CURRENT_SCRIPT"
    }
    CURRENT_SCRIPT="$resolved"
fi
SCRIPT_DIR=$(CDPATH= cd -- "$(dirname -- "$CURRENT_SCRIPT")" && pwd)
STUBS="$SCRIPT_DIR/../resources/stubs"

write_file() {
    dest="$1"
    file="$TARGET/$dest"
    dir=$(dirname "$file")

    if [ ! -d "$dir" ]; then
        mkdir -p "$dir" || {
            printf 'Error: no se pudo crear el directorio: %s\n' "$dir" >&2
            exit 1
        }
    fi

    if [ -e "$file" ] && [ "$FORCE" -eq 0 ]; then
        printf 'Omitido: %s ya existe. Usa --force para reemplazarlo.\n' "$dest"
        return 0
    fi

    tmp=$(mktemp "${TMPDIR:-/tmp}/devops.XXXXXX") || {
        printf 'Error: no se pudo crear un archivo temporal.\n' >&2
        exit 1
    }
    trap 'rm -f "$tmp"' EXIT HUP INT TERM

    stub="$STUBS/$dest"
    if [ -f "$stub" ]; then
        cp "$stub" "$tmp" || {
            printf 'Error: no se pudo escribir: %s\n' "$dest" >&2
            rm -f "$tmp"
            exit 1
        }
    else
        cat > "$tmp" || {
            printf 'Error: no se pudo escribir: %s\n' "$dest" >&2
            rm -f "$tmp"
            exit 1
        }
    fi

    cp "$tmp" "$file" || {
        printf 'Error: no se pudo instalar: %s\n' "$dest" >&2
        rm -f "$tmp"
        exit 1
    }

    case "$dest" in
        devops.sh|init.sh)
            chmod 0755 "$file" || {
                printf 'Error: no se pudo marcar como ejecutable: %s\n' "$dest" >&2
                rm -f "$tmp"
                exit 1
            }
            ;;
    esac

    rm -f "$tmp"
    trap - EXIT HUP INT TERM

    printf 'Instalado: %s\n' "$dest"
}
PREAMBLE_EOF

for f in $FILES; do
    delim="STUB_EOF_$(printf '%s' "$f" | tr -c 'A-Za-z0-9' '_')"
    printf 'write_file %s <<'"'"'%s'"'"'\n' "$f" "$delim"
    cat "$STUBS/$f"
    printf '%s\n' "$delim"
done
} > "$OUT"

chmod 0755 "$OUT"
count=0
for f in $FILES; do
    count=$((count + 1))
done
printf 'Generado: %s (%s archivos)\n' "$OUT" "$count"