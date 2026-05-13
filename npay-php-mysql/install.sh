#!/usr/bin/env bash
# install.sh — Quick installer for NPay PHP+MySQL example.
# Usage:
#   bash install.sh            # interactive
#   DB_NAME=npay_demo DB_USER=root DB_PASS=secret bash install.sh
set -euo pipefail

ROOT="$(cd "$(dirname "$0")" && pwd)"
cd "$ROOT"

echo "============================================"
echo "  NPay PHP+MySQL Installer"
echo "============================================"

# --- config.php --------------------------------------------------------------
if [[ ! -f config.php ]]; then
  cp config.example.php config.php
  echo "✔ Created config.php from config.example.php"
  echo "  → Hãy mở config.php và điền: db credentials, api_token, account_number, bank_short..."
else
  echo "ℹ config.php đã tồn tại — bỏ qua."
fi

# --- DB ----------------------------------------------------------------------
DB_HOST="${DB_HOST:-127.0.0.1}"
DB_PORT="${DB_PORT:-3306}"
DB_NAME="${DB_NAME:-npay_demo}"
DB_USER="${DB_USER:-root}"
DB_PASS="${DB_PASS:-}"

if command -v mysql >/dev/null 2>&1; then
  read -r -p "Tạo database '${DB_NAME}' và import db.sql? [y/N] " ans
  if [[ "${ans,,}" == "y" ]]; then
    MYSQL_PWD="$DB_PASS" mysql -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USER" \
      -e "CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
    MYSQL_PWD="$DB_PASS" mysql -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USER" "$DB_NAME" < db.sql
    echo "✔ Database '${DB_NAME}' đã sẵn sàng."
  fi
else
  echo "⚠ Không tìm thấy 'mysql' CLI. Hãy import db.sql thủ công vào DB của bạn."
fi

# --- Permissions -------------------------------------------------------------
chmod 640 config.php 2>/dev/null || true
chmod -R 755 assets tools lib 2>/dev/null || true
find . -type f -name "*.php" -exec chmod 644 {} \;

# --- Lint --------------------------------------------------------------------
if command -v php >/dev/null 2>&1; then
  echo "--- php -l ---"
  find . -type f -name "*.php" -print0 | xargs -0 -n1 php -l
fi

echo "============================================"
echo "  Done. Trỏ web server tới $ROOT"
echo "  Sau đó cấu hình webhook URL trên https://my.npay.vn:"
echo "    <BASE_URL>/webhook.php"
echo "============================================"
