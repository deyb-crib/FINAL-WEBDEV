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

## Useful commands
- Console: `docker compose exec php php bin/console list`
- Re-seed centers: empty the `dialysis_centers` table, then `docker compose exec php php bin/console app:seed`
- Clear cache: `docker compose exec php php bin/console cache:clear`
- Stop: `docker compose down` (add `-v` to also wipe the database)

## Structure
- `src/Controller` pages, auth, booking API, dashboard
- `src/Entity` User, DialysisCenter, Appointment (tables are created automatically at startup)
- `templates/` Twig versions of your index.php / all-hospitals.php, plus login, signup, dashboard, about
- `public/` style.css, script.js, css/, js/, images/
