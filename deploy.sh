#!/usr/bin/env bash
#
# Copy this project to the server and rebuild its containers there.
#
#   DEPLOY_HOST=root@203.0.113.10 ./deploy.sh
#
# Do not run it while the nightly crawl is running (02:00 UTC): rebuilding
# restarts the containers and would cut the crawl short.

set -euo pipefail

cd "$(dirname "$0")"

test -f artisan || { echo "This does not look like the API folder (no artisan file)." >&2; exit 1; }

: "${DEPLOY_HOST:?Set DEPLOY_HOST, for example DEPLOY_HOST=root@203.0.113.10}"

APP_DIR="/opt/funeral-referral-api"

rsync -az --include '.env.production.example' \
    --exclude '.git' --exclude 'vendor' --exclude 'node_modules' \
    --exclude '.env' --exclude '.env.*' --exclude '.idea' --exclude '.claude' \
    --exclude 'storage/logs' ./ "${DEPLOY_HOST}:${APP_DIR}/"

ssh "${DEPLOY_HOST}" "cd ${APP_DIR} && docker compose --env-file .env.production -f docker-compose.prod.yml up -d --build"

echo "Deployed. Health check:"
ssh "${DEPLOY_HOST}" "cd ${APP_DIR} && docker compose --env-file .env.production -f docker-compose.prod.yml ps --format '{{.Service}}: {{.Status}}'"
