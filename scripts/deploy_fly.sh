#!/usr/bin/env bash
set -euo pipefail

# Helper to set Fly secrets from env or prompt
require() {
  local var="$1"
  if [[ -z "${!var:-}" ]]; then
    read -r -p "$var: " value
    export "$var"="$value"
  fi
}

APP_NAME=${APP_NAME:-inventox}

echo "==> Checking required tools"
command -v fly >/dev/null || { echo "flyctl not found"; exit 1; }

echo "==> Gathering DB_* values"
require DB_HOST
require DB_PORT
require DB_NAME
require DB_USER
require DB_PASS

set -x
fly secrets set DB_HOST="$DB_HOST" DB_PORT="$DB_PORT" DB_NAME="$DB_NAME" DB_USER="$DB_USER" DB_PASS="$DB_PASS" --app "$APP_NAME"
fly deploy --app "$APP_NAME"
set +x

echo "==> Done. Visit: https://$APP_NAME.fly.dev/frontend/"
