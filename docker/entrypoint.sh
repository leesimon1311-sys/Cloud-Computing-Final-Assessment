#!/bin/sh
# ---------------------------------------------------------------------------
# Cloud platforms assign the port the container must listen on through the
# PORT environment variable. Apache is configured for port 80 by default, so
# both the listener and the virtual host are rewritten before it starts.
# ---------------------------------------------------------------------------
set -e

PORT="${PORT:-80}"

sed -ri "s/^Listen .*/Listen ${PORT}/" /etc/apache2/ports.conf
sed -ri "s/<VirtualHost \*:[0-9]+>/<VirtualHost *:${PORT}>/" /etc/apache2/sites-available/000-default.conf

echo "Starting Apache on port ${PORT} (APP_ENV=${APP_ENV:-local})"

exec apache2-foreground
