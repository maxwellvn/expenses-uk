# Expense claims, RoR UK office

Staff send expense claims with receipts. An approver reviews them, finance pays them, and the admin manages roles and help requests. Plain PHP 8.2 (MVC, no framework) and MySQL.

## Roles

| Role | Can |
| --- | --- |
| Staff | Everyone active in workCentral UK. Send claims, answer questions, update a claim sent back for changes. |
| Approver | Approve, decline or ask for changes. |
| Finance | Mark approved claims paid with a bank reference, see every claim, download CSV. |
| Admin | Everything above, plus roles and help requests. The handles in `ADMIN_KC_HANDLES` are always admin. |

Nobody approves or pays their own claim.

## Setup

1. Copy `.env.example` to `.env` and fill it in. `APP_ENV=production` on a live server.
2. Run the SQL files in order: `database/schema.sql`, then `002`, `003`, `004`.
3. Make `storage/receipts` writable by the web server. Receipts are never served directly; `.htaccess` blocks `app/`, `storage/`, `database/`, `deploy/` and dotfiles.
4. The site must be served from this folder with `mod_rewrite` on.

## Staff list

Sign-in is KingsChat only, matched to `kc_handle` in the workCentral UK staff list.

- Same MySQL server as workCentral: set `STAFF_DB=workcentral_uk` and the app reads it directly.
- Anywhere else (Hostinger): leave `STAFF_DB` empty. The app keeps its own `staff` table, filled by `deploy/push-staff.sh` running on the workCentral server every 15 minutes with the same `SYNC_SECRET`. People missing from a push are switched off, never deleted.

Run the push once by hand right after deploying, or nobody can sign in.
