#!/usr/bin/env bash
#
# Despliegue en PRODUCCION: migracion legacy bonificados.
#
# Encadena los pasos del plan acordado:
#   1. Backup de la BD del Panel
#   2. Modo mantenimiento
#   3. git pull + composer install + cache rebuild
#   4. php artisan migrate --force (5 migraciones nuevas, todas seguras)
#   5. Dry-run de alumnos:migrar-legacy (preview)
#   6. Confirmacion interactiva
#   7. Ejecucion real SIN --force (preserva alumnos existentes en produccion)
#   8. Verificacion post-ejecucion
#   9. Salir de mantenimiento
#
# Uso:
#   ./scripts/deploy-legacy-prod.sh                # interactivo (recomendado)
#   ./scripts/deploy-legacy-prod.sh --auto         # sin confirmaciones (NO recomendado)
#   ./scripts/deploy-legacy-prod.sh --skip-backup  # saltar backup (solo si ya tienes uno)
#
# Requisitos en el servidor:
#   - mysqldump y mysql disponibles
#   - usuario MySQL con SELECT sobre webcourses2014
#   - .env apuntando a la BD de produccion del Panel

set -euo pipefail

# ---------- Configuracion ----------
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
cd "$PROJECT_ROOT"

BACKUP_DIR="${BACKUP_DIR:-$PROJECT_ROOT/storage/backups}"
TIMESTAMP="$(date +%Y%m%d_%H%M%S)"
BACKUP_FILE="$BACKUP_DIR/panel_pre_legacy_${TIMESTAMP}.sql"
LOG_FILE="$BACKUP_DIR/deploy_legacy_${TIMESTAMP}.log"

AUTO=false
SKIP_BACKUP=false
for arg in "$@"; do
    case "$arg" in
        --auto) AUTO=true ;;
        --skip-backup) SKIP_BACKUP=true ;;
        -h|--help)
            sed -n '2,25p' "$0"
            exit 0
            ;;
    esac
done

# ---------- Helpers ----------
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
BOLD='\033[1m'
NC='\033[0m'

log()  { echo -e "${BLUE}[$(date +%H:%M:%S)]${NC} $*" | tee -a "$LOG_FILE"; }
ok()   { echo -e "${GREEN}[OK]${NC} $*" | tee -a "$LOG_FILE"; }
warn() { echo -e "${YELLOW}[!]${NC} $*" | tee -a "$LOG_FILE"; }
err()  { echo -e "${RED}[ERROR]${NC} $*" | tee -a "$LOG_FILE"; }

confirm() {
    local prompt="${1:-Continuar?}"
    if [ "$AUTO" = true ]; then
        log "AUTO: $prompt -> si"
        return 0
    fi
    read -r -p "$(echo -e "${BOLD}${prompt}${NC} [s/N] ")" resp
    [[ "$resp" =~ ^[sSyY]$ ]]
}

require_artisan() {
    if [ ! -f "$PROJECT_ROOT/artisan" ]; then
        err "No se encontro artisan en $PROJECT_ROOT"
        exit 1
    fi
}

leer_env() {
    local key="$1"
    if [ ! -f "$PROJECT_ROOT/.env" ]; then
        err ".env no existe en $PROJECT_ROOT"
        exit 1
    fi
    grep -E "^${key}=" "$PROJECT_ROOT/.env" | tail -1 | cut -d'=' -f2- | sed -e 's/^"//' -e 's/"$//' -e "s/^'//" -e "s/'\$//"
}

# ---------- Banner ----------
mkdir -p "$BACKUP_DIR"
echo "===========================================" | tee "$LOG_FILE"
echo " Deploy migracion legacy - PRODUCCION" | tee -a "$LOG_FILE"
echo " Inicio: $(date)" | tee -a "$LOG_FILE"
echo " Proyecto: $PROJECT_ROOT" | tee -a "$LOG_FILE"
echo " Log: $LOG_FILE" | tee -a "$LOG_FILE"
echo "===========================================" | tee -a "$LOG_FILE"

require_artisan

if [ "$AUTO" = false ]; then
    warn "Vas a ejecutar el deploy en este servidor."
    warn "Asegurate de estar en PRODUCCION y de que el .env apunta a la BD correcta."
    confirm "Confirmas que quieres continuar?" || { warn "Abortado por el usuario."; exit 0; }
fi

# ---------- Paso 0: verificar acceso a webcourses2014 ----------
log "Verificando acceso a webcourses2014..."
DB_CONN="$(leer_env DB_CONNECTION)"
DB_HOST="$(leer_env DB_HOST)"
DB_PORT="$(leer_env DB_PORT)"
DB_USER="$(leer_env DB_USERNAME)"
DB_PASS="$(leer_env DB_PASSWORD)"
DB_NAME="$(leer_env DB_DATABASE)"
LEGACY_DB="$(leer_env LEGACY_DB_DATABASE)"
LEGACY_DB="${LEGACY_DB:-webcourses2014}"

log "BD Panel:  ${DB_NAME} en ${DB_HOST}:${DB_PORT:-3306}"
log "BD Legacy: ${LEGACY_DB}"

if ! mysql -h "$DB_HOST" -P "${DB_PORT:-3306}" -u "$DB_USER" -p"$DB_PASS" -e "SELECT COUNT(*) FROM ${LEGACY_DB}.tbl_member;" >/dev/null 2>&1; then
    err "No se puede acceder a ${LEGACY_DB}.tbl_member con las credenciales del .env"
    err "Verifica permisos: GRANT SELECT ON ${LEGACY_DB}.* TO '${DB_USER}'@'%';"
    exit 1
fi
ok "Acceso a ${LEGACY_DB} confirmado"

# ---------- Paso 1: Backup ----------
if [ "$SKIP_BACKUP" = true ]; then
    warn "--skip-backup activo: NO se hara backup. Asegurate de tener uno reciente."
    confirm "Continuar sin backup?" || { warn "Abortado."; exit 0; }
else
    log "Paso 1/8: Backup de ${DB_NAME} -> ${BACKUP_FILE}"
    mysqldump \
        -h "$DB_HOST" -P "${DB_PORT:-3306}" \
        -u "$DB_USER" -p"$DB_PASS" \
        --single-transaction --routines --triggers --quick \
        "$DB_NAME" > "$BACKUP_FILE"

    BACKUP_SIZE="$(du -h "$BACKUP_FILE" | cut -f1)"
    ok "Backup creado: ${BACKUP_FILE} (${BACKUP_SIZE})"

    # Sanity check: el backup debe tener al menos contenido minimo
    if [ ! -s "$BACKUP_FILE" ] || [ "$(wc -l < "$BACKUP_FILE")" -lt 10 ]; then
        err "El backup parece vacio o invalido. Abortando."
        exit 1
    fi
fi

# ---------- Paso 2: Modo mantenimiento ----------
log "Paso 2/8: Activando modo mantenimiento..."
php artisan down --render="errors::503" || warn "No se pudo activar mantenimiento (continuando)"

# Trap para asegurar que la app vuelve a levantarse aunque algo falle
restore_on_exit() {
    local code=$?
    if [ $code -ne 0 ]; then
        warn "El script termino con error (codigo $code). Levantando app..."
        php artisan up || true
    fi
}
trap restore_on_exit EXIT

# ---------- Paso 3: Pull + dependencias + cache ----------
log "Paso 3/8: git pull + composer + cache..."

CURRENT_BRANCH="$(git rev-parse --abbrev-ref HEAD)"
log "Rama actual: $CURRENT_BRANCH"

git fetch --all
git pull origin "$CURRENT_BRANCH"

composer install --no-dev --optimize-autoloader --no-interaction

php artisan config:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
ok "Codigo actualizado y caches reconstruidos"

# ---------- Paso 4: Migraciones ----------
log "Paso 4/8: php artisan migrate --force"
php artisan migrate --force
ok "Migraciones aplicadas"

php artisan migrate:status | tail -10 | tee -a "$LOG_FILE"

# ---------- Paso 5: Dry-run ----------
log "Paso 5/8: Dry-run de alumnos:migrar-legacy (preview, no escribe nada)"
echo "----- INICIO DRY-RUN -----" | tee -a "$LOG_FILE"
php artisan alumnos:migrar-legacy --dry-run 2>&1 | tee -a "$LOG_FILE"
echo "----- FIN DRY-RUN -----" | tee -a "$LOG_FILE"

# ---------- Paso 6: Confirmacion ----------
echo
warn "Revisa el resumen del dry-run arriba."
warn "Especialmente la linea 'Ya existian (omitidos): N' -> esos son los alumnos"
warn "actuales de produccion que SE PRESERVAN. Verifica que el numero tenga sentido."
echo

if ! confirm "Procedo con la ejecucion REAL (sin --force, preserva existentes)?"; then
    warn "Abortado por el usuario tras dry-run."
    log "Levantando app..."
    php artisan up
    trap - EXIT
    exit 0
fi

# ---------- Paso 7: Ejecucion real ----------
log "Paso 7/8: Ejecucion real de alumnos:migrar-legacy (SIN --force)"
echo "----- INICIO EJECUCION REAL -----" | tee -a "$LOG_FILE"
php artisan alumnos:migrar-legacy 2>&1 | tee -a "$LOG_FILE"
echo "----- FIN EJECUCION REAL -----" | tee -a "$LOG_FILE"
ok "Migracion legacy ejecutada"

# ---------- Paso 8: Verificacion ----------
log "Paso 8/8: Verificacion post-ejecucion"
php artisan tinker --execute="
echo 'Alumnos totales:           '.\App\Models\Alumno::count().PHP_EOL;
echo 'Alumnos datos_pendientes:  '.\App\Models\Alumno::where('datos_pendientes', true)->count().PHP_EOL;
echo 'Pool legacy:               '.\App\Models\AlumnoLegacyPool::count().PHP_EOL;
echo 'Cursos legacy:             '.\App\Models\AlumnoLegacyCurso::count().PHP_EOL;
echo 'Cursos legacy enriquecidos:'.\App\Models\AlumnoLegacyCurso::whereNotNull('grupo_id_fundae')->count().PHP_EOL;
" 2>&1 | tee -a "$LOG_FILE"

# ---------- Salir de mantenimiento ----------
log "Levantando app..."
php artisan up
trap - EXIT

echo
echo "===========================================" | tee -a "$LOG_FILE"
ok "DEPLOY COMPLETADO"
log "Fin: $(date)"
log "Log: $LOG_FILE"
[ "$SKIP_BACKUP" = false ] && log "Backup: $BACKUP_FILE"
echo "===========================================" | tee -a "$LOG_FILE"
echo
warn "Si necesitas rollback de esquema:  php artisan migrate:rollback --step=5 --force"
warn "Si necesitas restaurar la BD:      mysql ... < $BACKUP_FILE"
