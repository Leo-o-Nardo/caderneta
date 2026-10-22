#!/usr/bin/env bash
# Cópia do banco da Caderneta: um arquivo .sql.gz por dia, guardando os últimos 14 dias.
# Feito para o cron do root (veja docs/deploy.md). A pasta muda com BACKUP_DIR.
set -euo pipefail

cd "$(dirname "$0")/.."

DIR="${BACKUP_DIR:-/var/backups/caderneta}"
FILE="$DIR/caderneta-$(date +%F-%H%M).sql.gz"
mkdir -p "$DIR"

# O pg_dump roda dentro do container do banco, com o usuário e o banco dele.
docker compose --env-file backend/.env -f compose.prod.yml exec -T postgres \
  sh -c 'pg_dump -U "$POSTGRES_USER" -d "$POSTGRES_DB" --no-owner' | gzip > "$FILE"

find "$DIR" -name 'caderneta-*.sql.gz' -mtime +14 -delete

echo "$(date '+%F %T') backup ok: $FILE ($(du -h "$FILE" | cut -f1))"
