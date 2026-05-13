#!/usr/bin/env bash
# npay-ladipage installer.
# Creates SQLite DB, copies config, sets permissions.

set -euo pipefail

DIR="$(cd "$(dirname "$0")" && pwd)"
DATA_DIR="$DIR/data"
DB_FILE="$DATA_DIR/npay.sqlite"
CONFIG_FILE="$DIR/config.php"
EXAMPLE_FILE="$DIR/config.example.php"

echo "==> npay-ladipage installer"
echo "    base: $DIR"

# 1. Create data dir
mkdir -p "$DATA_DIR"
chmod 775 "$DATA_DIR"
echo "==> data/ ready"

# 2. Copy config if missing
if [ ! -f "$CONFIG_FILE" ]; then
    cp "$EXAMPLE_FILE" "$CONFIG_FILE"
    chmod 640 "$CONFIG_FILE"
    echo "==> config.php created from template — EDIT IT before going live."
else
    echo "==> config.php already exists (kept)"
fi

# 3. Initialise SQLite DB by invoking Database::__construct().
if command -v php >/dev/null 2>&1; then
    php -r "
        require '$DIR/lib/Database.php';
        \$cfg = require '$CONFIG_FILE';
        new Database(\$cfg['db_path']);
        echo \"==> SQLite initialised at \" . \$cfg['db_path'] . PHP_EOL;
    "
else
    echo "!! php not in PATH; skipping DB init (will run on first request)."
fi

# 4. Lint every PHP file
if command -v php >/dev/null 2>&1; then
    echo "==> Linting PHP files..."
    find "$DIR" -maxdepth 2 -name '*.php' -print0 | xargs -0 -n1 php -l
fi

echo
echo "Done. Next steps:"
echo "  1. Edit $CONFIG_FILE (api_token, account_number, bank_bin, account_holder, base_url)."
echo "  2. Point LadiPage form submit URL  -> {base_url}/webhook.php?source=ladipage"
echo "  3. Point NPay dashboard webhook URL -> {base_url}/webhook.php?source=npay"
echo "  4. Open {base_url}/admin.php?token=YOUR_TOKEN to view orders."
