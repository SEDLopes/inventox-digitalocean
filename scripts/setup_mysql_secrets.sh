#!/usr/bin/env bash
# Script para configurar secrets do MySQL no Fly.io
# Uso: ./scripts/setup_mysql_secrets.sh

export FLYCTL_INSTALL="$HOME/.fly"
export PATH="$FLYCTL_INSTALL/bin:$PATH"

echo "== Configurar MySQL Secrets no Fly.io =="
echo ""
read -p "MYSQLHOST: " DB_HOST
read -p "MYSQLPORT (default: 3306): " DB_PORT
read -p "MYSQLDATABASE: " DB_NAME
read -p "MYSQLUSER: " DB_USER
read -s -p "MYSQLPASSWORD: " DB_PASS
echo ""

DB_PORT=${DB_PORT:-3306}

echo "Configurando secrets..."
fly secrets set \
  DB_HOST="$DB_HOST" \
  DB_PORT="$DB_PORT" \
  DB_NAME="$DB_NAME" \
  DB_USER="$DB_USER" \
  DB_PASS="$DB_PASS" \
  --app inventox

echo ""
echo "✅ Secrets configurados!"
echo "🔄 A reiniciar máquinas para aplicar..."
fly machine restart --app inventox --select

echo ""
echo "✅ Pronto! Tenta fazer login agora: https://inventox.fly.dev/frontend/"
