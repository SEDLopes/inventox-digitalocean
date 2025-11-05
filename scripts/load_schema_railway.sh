#!/usr/bin/env bash
# Script para carregar schema db.sql no MySQL do Railway
# Uso: ./scripts/load_schema_railway.sh

echo "== Carregar schema no MySQL do Railway =="
echo ""
read -p "MYSQLHOST (RAILWAY_PRIVATE_DOMAIN ou RAILWAY_TCP_PROXY_DOMAIN): " DB_HOST
read -p "MYSQLPORT (default: 3306): " DB_PORT
read -p "MYSQLDATABASE (default: railway): " DB_NAME
read -p "MYSQLUSER (default: root): " DB_USER
read -s -p "MYSQLPASSWORD: " DB_PASS
echo ""

DB_PORT=${DB_PORT:-3306}
DB_NAME=${DB_NAME:-railway}
DB_USER=${DB_USER:-root}

echo "Carregando schema..."
mysql -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" < db.sql

if [ $? -eq 0 ]; then
    echo "✅ Schema carregado com sucesso!"
else
    echo "❌ Erro ao carregar schema"
fi
