#!/usr/bin/env bash
# Copy the SQLite database (and WAL/SHM if present) to data/backups/.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
DB="$ROOT/data/bookings.sqlite"
DEST_DIR="$ROOT/data/backups"
STAMP="$(date +%Y%m%d-%H%M%S)"

if [[ ! -f "$DB" ]]; then
  echo "No database at $DB — nothing to back up."
  exit 0
fi

mkdir -p "$DEST_DIR"
cp -p "$DB" "$DEST_DIR/bookings-$STAMP.sqlite"
[[ -f "$DB-wal" ]] && cp -p "$DB-wal" "$DEST_DIR/bookings-$STAMP.sqlite-wal"
[[ -f "$DB-shm" ]] && cp -p "$DB-shm" "$DEST_DIR/bookings-$STAMP.sqlite-shm"

# Keep the newest 30 backups only.
ls -1t "$DEST_DIR"/bookings-*.sqlite 2>/dev/null | tail -n +31 | while read -r old; do
  rm -f "$old" "${old}-wal" "${old}-shm"
done

echo "Backed up to $DEST_DIR/bookings-$STAMP.sqlite"
