# Deploy Espace Tayeb on Laravel Cloud

**GitHub repository:** [https://github.com/abdoelectro63/espace-tayeb](https://github.com/abdoelectro63/espace-tayeb)

Laravel Cloud connects to GitHub in the browser; this file only documents what to configure after you link that repo.

## 1. Create the application

1. Sign in at [Laravel Cloud](https://cloud.laravel.com/).
2. Create an **application** and choose **Import from GitHub**.
3. Select repository **`abdoelectro63/espace-tayeb`** and branch **`main`** (or the branch you use).

## 2. Attach resources

- **MySQL** — attach a database; Cloud injects `DB_*` variables.
- Optional later: **Object storage (S3)** for durable uploads (Filament / `public` disk). The local filesystem on Cloud is **ephemeral**; `php artisan storage:link` during deploy **does not persist** across deployments ([docs](https://cloud.laravel.com/docs/environments#build-and-deploy-commands)).

## 3. PHP & Node

- **PHP:** `8.3` or `8.4` (project requires `^8.3` in `composer.json`).
- **Node:** `20`, `22`, or `24` (for Vite 8 / Tailwind 4).

## 4. Environment variables

Set in the environment **Variables** UI (adjust to your secrets):

| Key | Notes |
|-----|--------|
| `APP_ENV` | `production` |
| `APP_DEBUG` | `false` |
| `APP_URL` | Your `https://….laravel.cloud` (or custom domain) |
| `APP_KEY` | Cloud can generate, or `base64:…` from `php artisan key:generate --show` |
| `DB_*` | Usually filled when MySQL is attached |
| `SESSION_DRIVER` / `QUEUE_CONNECTION` / `CACHE_STORE` | Prefer **`database`** or **Redis/KV** if you add a cache; ensure migrations have run so `sessions`, `jobs`, `cache` tables exist |
| `MAIL_*` | For notifications |
| `VITIPS_*`, `BACKUP_*`, etc. | Copy from local `.env` as needed |

After changing variables, **redeploy**.

## 5. Build commands

In **Environment → Deployments → Build commands** (must finish within 15 minutes), use:

```bash
composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader
npm ci
npm run build
php artisan optimize
```

`php artisan optimize` belongs in **build**, not deploy ([Laravel Cloud environments](https://cloud.laravel.com/docs/environments#build-and-deploy-commands)).

## 6. Deploy commands

In **Deploy commands**:

```bash
php artisan migrate --force
```

`--force` is required in production so migrations do not prompt ([knowledge base](https://cloud.laravel.com/docs/knowledge-base/command-failed-prod-app.md)).

## 7. First deploy checklist

- [ ] Migrations succeed on **MySQL** (fix any ordering / duplicate-table issues locally first).
- [ ] Build log shows `npm run build` and `public/build` manifest.
- [ ] Create an admin user (`php artisan tinker` from Cloud **Commands**, or a one-time seeder).
- [ ] Scheduler: enable if you rely on `routes/console.php` (e.g. `backup:run`).
- [ ] Queue worker: add if `QUEUE_CONNECTION=database` and you process jobs.

## 8. Optional: GitHub deploy hook

If you turn off “push to deploy” and use an HTTP **deploy hook** instead, add the hook URL as a GitHub Actions secret and follow [Deployments → Deploy hooks](https://cloud.laravel.com/docs/deployments#deploy-hooks).

## 9. Push code to GitHub

From your machine (after committing):

```bash
git remote add origin https://github.com/abdoelectro63/espace-tayeb.git   # if not already set
git push -u origin main
```

Pushing to the connected branch triggers a deploy when **push to deploy** is enabled (default).
