#!/usr/bin/env bash
set -Eeuo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "${SCRIPT_DIR}/../.." && pwd)"
ENV_FILE="${SCRIPT_DIR}/deploy.env"

if [[ ! -f "${ENV_FILE}" ]]; then
  echo "ERROR: ${ENV_FILE} is missing."
  echo "Copy deploy/scripts/deploy.env.example to deploy/scripts/deploy.env and edit values."
  exit 1
fi

# shellcheck disable=SC1090
source "${ENV_FILE}"

: "${APP_DIR:?APP_DIR is required}"
: "${DOMAIN:?DOMAIN is required}"
DOMAIN_WWW="${DOMAIN_WWW:-}"
PHP_FPM_SOCK="${PHP_FPM_SOCK:-/run/php/php8.2-fpm.sock}"
SUPERVISOR_USER="${SUPERVISOR_USER:-www-data}"
SUPERVISOR_PROCS="${SUPERVISOR_PROCS:-2}"

echo "==> Preparing Nginx config"
NGINX_TEMPLATE="${REPO_ROOT}/deploy/nginx/institutions-system.conf"
NGINX_TARGET="/etc/nginx/sites-available/institutions-system.conf"
sudo cp "${NGINX_TEMPLATE}" "${NGINX_TARGET}"
sudo sed -i "s#server_name .*#server_name ${DOMAIN} ${DOMAIN_WWW};#g" "${NGINX_TARGET}"
sudo sed -i "s#/var/www/institutions-system#${APP_DIR}#g" "${NGINX_TARGET}"
sudo sed -i "s#/run/php/php8.2-fpm.sock#${PHP_FPM_SOCK}#g" "${NGINX_TARGET}"

if [[ ! -L /etc/nginx/sites-enabled/institutions-system.conf ]]; then
  sudo ln -s "${NGINX_TARGET}" /etc/nginx/sites-enabled/institutions-system.conf
fi

sudo nginx -t
sudo systemctl reload nginx

echo "==> Preparing Supervisor config"
SUP_TEMPLATE="${REPO_ROOT}/deploy/supervisor/laravel-worker.conf"
SUP_TARGET="/etc/supervisor/conf.d/institutions-system-worker.conf"
sudo cp "${SUP_TEMPLATE}" "${SUP_TARGET}"
sudo sed -i "s#/var/www/institutions-system#${APP_DIR}#g" "${SUP_TARGET}"
sudo sed -i "s#user=www-data#user=${SUPERVISOR_USER}#g" "${SUP_TARGET}"
sudo sed -i "s#numprocs=2#numprocs=${SUPERVISOR_PROCS}#g" "${SUP_TARGET}"

sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl restart institutions-system-worker:* || true

echo "==> Registering Laravel scheduler cron"
CRON_LINE="* * * * * ${PHP_BIN:-php} ${APP_DIR}/artisan schedule:run >> /dev/null 2>&1"
if ! sudo crontab -l 2>/dev/null | grep -Fq "${APP_DIR}/artisan schedule:run"; then
  (sudo crontab -l 2>/dev/null; echo "${CRON_LINE}") | sudo crontab -
fi

echo "==> Server setup completed"
echo "Next: run ${APP_DIR}/deploy/scripts/deploy.sh"
