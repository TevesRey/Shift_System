# Deploy ShiftMate to Vercel

Vercel runs this Laravel app as a Docker container (FrankenPHP, PHP 8.4).
Files that make that work: `Dockerfile`, `Caddyfile`, `vercel.json`, `.dockerignore`.

## 0. You need
- Vercel account + CLI: `npm i -g vercel`
- Docker Desktop running (Vercel builds the image)
- PHP 8.4 + Composer locally (only to generate the key and run migrations)
- A hosted MySQL 8 database (Vercel has no MySQL of its own)

## 1. Create the database
Any hosted MySQL works. Note host, port, database, user, password.
Pick a region close to your Vercel function region to keep it fast.

## 2. Add environment variables
Copy every name from `.env.vercel.example` into
Vercel -> Project -> Settings -> Environment Variables (Production), or:
`vercel env add APP_KEY production`

Generate a fresh key first:  `php artisan key:generate --show`

## 3. Create the tables (run once, from your computer)
Real shell variables override your local `.env`, so this is safe:

    export APP_ENV=production APP_KEY="base64:..." \
      DB_CONNECTION=mysql DB_HOST=... DB_PORT=3306 \
      DB_DATABASE=... DB_USERNAME=... DB_PASSWORD=... \
      MYSQL_ATTR_SSL_CA=/etc/ssl/certs/ca-certificates.crt \
      SHIFTMATE_ADMIN_EMAIL=you@example.com \
      SHIFTMATE_ADMIN_PASSWORD='a-long-unique-password'
    php artisan migrate --force
    php artisan db:seed --force      # shift types + first admin

(On Windows, use `set` / `$env:NAME="value"` instead of `export`. If TLS fails
locally, point MYSQL_ATTR_SSL_CA at your provider's CA file.)

Never run migrations at container start: several instances can start at once.

## 3b. Test locally first (recommended)
    bash scripts/preflight.sh
It builds the same image Vercel builds and checks it boots. If it prints
PREFLIGHT PASSED, the build side is good.

## 4. Deploy
    vercel login
    vercel link
    vercel deploy --prod

Then set `APP_URL` to the real https URL, and redeploy: `vercel deploy --prod`.

## 5. Check
- `https://YOUR-URL/up`  -> Laravel health page (200)
- `https://YOUR-URL/`    -> ShiftMate login
- Log in with the admin email/password from step 3

## Profile photos (optional but recommended)
Container disk is wiped on every deploy/scale event, so uploaded photos vanish.
To persist them with S3 (or R2 / any S3-compatible store):

    composer require league/flysystem-aws-s3-v3 "^3.0"

Commit the updated composer.json + composer.lock, then set
FILESYSTEM_DISK=s3, AWS_ACCESS_KEY_ID, AWS_SECRET_ACCESS_KEY,
AWS_DEFAULT_REGION, AWS_BUCKET (and AWS_ENDPOINT / AWS_URL for non-AWS stores).
The upload code (AuthController::uploadProfilePhoto) already supports it.

## Troubleshooting
| Symptom | Cause / fix |
|---|---|
| 502 | Keep `:{$PORT:80}` in Caddyfile; check Runtime Logs |
| "No application encryption key" | Add APP_KEY, redeploy |
| "Table 'sessions' doesn't exist" | Step 3 not done against the production DB |
| SQLSTATE connection refused / SSL error | Wrong DB_HOST, or set MYSQL_ATTR_SSL_CA |
| Login loops / 419 | Set APP_URL to the https URL + SESSION_SECURE_COOKIE=true |
| Seeder says "Set a unique SHIFTMATE_ADMIN_..." | Provide a non-default admin email + password |
| Build: "requires php >=8.4.1" | Do not lower the PHP version in Dockerfile |
