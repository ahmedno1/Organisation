#!/usr/bin/env bash
set -Eeuo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ENV_FILE="${SCRIPT_DIR}/deploy.env"

if [[ -f "${ENV_FILE}" ]]; then
  # shellcheck disable=SC1090
  source "${ENV_FILE}"
fi

APP_DIR="${APP_DIR:-/var/www/institutions-system}"
PHP_BIN="${PHP_BIN:-php}"
COMPOSER_BIN="${COMPOSER_BIN:-composer}"
NPM_BIN="${NPM_BIN:-npm}"
BRANCH="${BRANCH:-main}"
RUN_MIGRATIONS="${RUN_MIGRATIONS:-1}"

echo "==> Deploy started at $(date -u +'%Y-%m-%d %H:%M:%S UTC')"
echo "==> App dir: ${APP_DIR}"

cd "${APP_DIR}"

echo "==> Pulling latest code (${BRANCH})"
git fetch --all --prune
git checkout "${BRANCH}"
git pull origin "${BRANCH}"

echo "==> Installing PHP dependencies"
"${COMPOSER_BIN}" install --no-dev --optimize-autoloader --no-interaction --prefer-dist

echo "==> Installing Node dependencies and building assets"
"${NPM_BIN}" ci
"${NPM_BIN}" run build

if [[ ! -f ".env" ]]; then
  echo "ERROR: .env file not found in ${APP_DIR}"
  exit 1
fi

echo "==> Laravel optimize clear"
"${PHP_BIN}" artisan optimize:clear

echo "==> Laravel storage link (safe if exists)"
"${PHP_BIN}" artisan storage:link || true

if [[ "${RUN_MIGRATIONS}" == "1" ]]; then
  echo "==> Running migrations"
  "${PHP_BIN}" artisan migrate --force
fi

echo "==> Caching config/routes/views"
"${PHP_BIN}" artisan config:cache
"${PHP_BIN}" artisan route:cache
"${PHP_BIN}" artisan view:cache

echo "==> Restarting queue workers"
"${PHP_BIN}" artisan queue:restart || true

echo "==> Deploy completed successfully"
