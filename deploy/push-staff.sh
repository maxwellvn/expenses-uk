#!/bin/sh
# Runs on the workCentral UK server (cron, every 15 minutes). Reads the staff list from the
# workCentral MySQL container and sends it to the expenses portal. Read-only on workCentral.
#   */15 * * * * EXPENSES_SYNC_SECRET=... /opt/expenses/push-staff.sh >> /var/log/expenses-staff-sync.log 2>&1
set -eu
URL="${EXPENSES_SYNC_URL:-https://expenses-uk.rorportal.org/sync/staff}"
: "${EXPENSES_SYNC_SECRET:?set EXPENSES_SYNC_SECRET}"

# The workCentral database container: the one that has a workcentral_uk database.
CONTAINER=""
for c in $(docker ps --format '{{.Names}}'); do
  if docker exec "$c" sh -c 'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mysql -uroot -N -e "SHOW DATABASES LIKE \"workcentral_uk\""' 2>/dev/null | grep -q workcentral_uk; then
    CONTAINER="$c"; break
  fi
done
[ -n "$CONTAINER" ] || { echo "$(date -Is) no workcentral_uk container found"; exit 1; }

# MYSQL_PWD instead of -p: no "password on the command line" warning mixed into the output.
JSON=$(docker exec "$CONTAINER" sh -c 'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mysql -uroot --default-character-set=utf8mb4 -N --raw workcentral_uk -e "
  SELECT JSON_ARRAYAGG(JSON_OBJECT(\"id\", u.id, \"name\", u.name, \"role\", u.role, \"title\", u.title,
    \"department\", COALESCE(d.name, u.department), \"avatar_url\", u.avatar_url, \"kc_handle\", u.kc_handle,
    \"email\", u.email, \"active\", u.active))
  FROM users u LEFT JOIN departments d ON d.id = u.department_id"')

case "$JSON" in
  \[*\]) ;;
  *) echo "$(date -Is) staff query did not return a JSON list, not sending"; exit 1 ;;
esac

printf '%s' "$JSON" | curl -sS --fail-with-body --max-time 30 -X POST "$URL" \
  -H "Authorization: Bearer $EXPENSES_SYNC_SECRET" -H 'Content-Type: application/json' --data-binary @- \
  && echo " $(date -Is) ok"
