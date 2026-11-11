# KATOC (Symfony + Docker)

## Run it
1. Install Docker Desktop and start it. Stop XAMPP's Apache/MySQL if they use ports 8082/8081/3307.
2. Copy your original `images/` folder into `public/images/`.
3. In this folder run:  `docker compose up -d --build`
4. First start downloads Symfony (a few minutes). Watch progress: `docker compose logs -f php`
   Wait for ">> KATOC is ready".

| What | URL |
|---|---|
| Website | http://localhost:8082 |
| phpMyAdmin | http://localhost:8081 (user `root`, password `root`) |
| MySQL from host | 127.0.0.1:3307 (db/user/pass: `katoc`) |

## Admin access
1. Generate a private invite code with at least 64 characters:
   `php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"`
2. Add the generated value as `ADMIN_SIGNUP_CODE=...` in the project-root `.env` file. Do not share or commit this value.
3. Start or restart the app with `docker compose up -d`.
4. Open `/admin/signup` and share the invite code only with people allowed to create admin accounts.
5. Sign in at `/admin/login`. Administrators can manage appointments and dialysis centers at `/admin`.

If admin signup is not configured, `/admin/signup` is disabled. To grant admin access directly instead, register a regular account and run:
`docker compose exec php php bin/console app:admin:grant admin@example.com`

## Admin dashboard
Open `/admin` after signing in with an active administrator account. The dashboard summarizes registered/active/inactive accounts, appointment requests, completed requests, active centers, live alerts, and recent audited activity.

The admin navigation includes:
- **User management:** search/filter accounts, inspect account appointment history, create and edit patient, hospital administrator, and system administrator accounts, assign hospital administrators to one or more dialysis centers, deactivate/activate accounts, and delete accounts only when they have no appointment history. The last active administrator and the signed-in administrator are protected from lockout.
- **Records management:** search/filter appointment requests and update statuses; dialysis centers can be added, edited, activated, or deactivated.
- **Reports & analytics:** database-backed appointment trends, status and registration distributions, date filtering (up to one year), and CSV export.
- **Activity logs and notifications:** login/logout and account/appointment/center changes are audited; notifications are live summaries of pending appointments, past confirmed dates, and inactive centers. Notifications are derived from current records and do not have individual read/unread states.
- **Profile and system settings:** edit the signed-in administrator's own name/email and view read-only runtime/database health. Secrets and database credentials remain outside the dashboard in the environment.

The dashboard adds `users.is_active` and the `admin_activities` audit table. Docker startup applies these additive Doctrine schema changes automatically; on a deployment without the included startup script, run `php bin/console doctrine:schema:update --force --no-interaction` after reviewing the schema diff. Back up the database before applying schema updates.

## Hospital administrator access
Create a hospital administrator from **Admin → User management → Add user**, select the **Hospital administrator** role, and assign one or more dialysis centers. Each account can be assigned to a center independently, so a center can have multiple hospital administrators. Sign-in redirects them to `/hospital-admin`, where they can view bookings and update appointment statuses only for their assigned centers. The system administrator retains global access.

Appointments now have a center relationship in addition to the saved center-name snapshot. On Docker startup, `app:backfill-appointment-centers` links existing bookings to centers by exact name after the schema update and center seeding. If you deploy without the included startup script, run the schema update, `php bin/console app:seed` if needed, and `php bin/console app:backfill-appointment-centers`. Unmatched or ambiguous legacy center names are deliberately left unassigned; review those records before assigning access.

## Useful commands
- Console: `docker compose exec php php bin/console list`
- Grant admin access: `docker compose exec php php bin/console app:admin:grant admin@example.com`
- Re-seed centers: empty the `dialysis_centers` table, then `docker compose exec php php bin/console app:seed`
- Clear cache: `docker compose exec php php bin/console cache:clear`
- Stop: `docker compose down` (add `-v` to also wipe the database)

## Structure
- `src/Controller` pages, auth, booking API, dashboard, admin
- `src/Entity` User, DialysisCenter, Appointment (tables are created automatically at startup)
- `templates/` Twig versions of your index.php / all-hospitals.php, plus login, signup, dashboard, about
- `public/` style.css, script.js, css/, js/, images/
