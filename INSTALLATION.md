# Installing QuizPal

## Requirements

- PHP 8.3 or newer with the `pdo_mysql`, `pdo_sqlite`, `sqlite3`, `mbstring`, `xml`, `xmlwriter`, `curl` and `pcntl` extensions
- MySQL 8 for production
- Composer 2
- Node.js 22 or newer with npm
- Git

Locally QuizPal uses a SQLite file by default and the tests use SQLite in memory, so no database server is needed to
develop. Production runs on MySQL; to use MySQL locally as well, set the `DB_*` values described below.

## Local setup

```bash
git clone <repository-url> quizpal
cd quizpal
composer setup
```

`composer setup` installs PHP and Node dependencies, creates `.env` from `.env.example`, generates the application key, creates and migrates the SQLite database and builds the front-end assets.

Then load the question bank:

```bash
php artisan db:seed --class=QuestionSeeder
```

### Configure the environment

Open `.env` and check these values:

| Variable | Meaning |
| --- | --- |
| `APP_URL` | The URL you open in the browser (the share links and QR codes are built from it) |
| `APP_LOCALE` | Default interface language: `el` (Greek) or `en` (English). Players can switch at any time |
| `DB_CONNECTION` | `sqlite` (the default) keeps everything in `database/database.sqlite`. For MySQL set `mysql` and fill in `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME` and `DB_PASSWORD`; the database must exist before `php artisan migrate` runs |
| `REVERB_APP_ID`, `REVERB_APP_KEY`, `REVERB_APP_SECRET` | Credentials shared by the app and the Reverb server. `php artisan reverb:install` generates random values; any random strings work |
| `REVERB_HOST`, `REVERB_PORT`, `REVERB_SCHEME` | Where browsers connect for WebSockets. Locally `localhost`, `8080`, `http` |
| `VITE_REVERB_*` | The same values exposed to the browser bundle |

Vite bakes the `VITE_*` values into the JavaScript bundle, so run `npm run build` (or keep `npm run dev` running) whenever you change them.

### Run it

```bash
php artisan dev
```

This starts, in one terminal, the PHP development server (`http://localhost:8000`), the Reverb WebSocket server (`localhost:8080`), the queue listener, the log viewer and Vite. Open `http://localhost:8000`.

You can also run the processes separately:

```bash
php artisan serve
php artisan reverb:start
npm run dev          # or: npm run build
```

### Play from phones on the same Wi-Fi

Browsers on other devices must reach both the web server and Reverb through your computer's LAN address (for example `192.168.1.20`):

1. In `.env` set `APP_URL=http://192.168.1.20:8000` and `REVERB_HOST=192.168.1.20`.
2. Rebuild the assets: `npm run build`.
3. Start the servers bound to all interfaces:

   ```bash
   php artisan serve --host=0.0.0.0
   php artisan reverb:start --host=0.0.0.0
   ```

4. Create a room and scan the QR code with a phone.

## Running the tests

```bash
php artisan test            # Pest test suite (uses an in-memory SQLite database)
composer test               # Pint, PHPStan and the test suite, as the CI workflow runs them
```

## Production deployment

QuizPal is a standard Laravel application plus one long-running Reverb process. The steps below install the
application first and add Reverb last, so the site is usable at every point: until Reverb is running, the game
works through its 10-second polling fallback.

If the server has several PHP versions, call the one that serves the site explicitly everywhere (for example
`php8.3 artisan ...` and `php8.3 /usr/local/bin/composer ...`), including in the supervisor program and the cron entry.

### Application

1. **Build**: `composer install --no-dev --optimize-autoloader`, `npm ci && npm run build`.
2. **Environment**: set `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://your-domain`, `APP_LOCALE` (`el` or
   `en`), random `REVERB_APP_ID`, `REVERB_APP_KEY` and `REVERB_APP_SECRET`, and `BROADCAST_CONNECTION=log` for now.
   Generate a fresh key with `php artisan key:generate --force` rather than copying a development key.
3. **Browser-side Reverb values** (baked into the assets, so set them before `npm run build`):

   ```dotenv
   VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
   VITE_REVERB_HOST=your-domain
   VITE_REVERB_PORT=443
   VITE_REVERB_SCHEME=https
   ```

   Browsers connect to `wss://your-domain/app/...` and the web server proxies that path to Reverb, so these values
   never change when Reverb's own port changes.
4. **Database**: production runs on MySQL. Create a database and a user that owns only that database. Use `utf8mb4`,
   the question bank contains Greek text:

   ```sql
   CREATE DATABASE quizpal CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   CREATE USER 'quizpal'@'localhost' IDENTIFIED BY '<random password>';
   GRANT ALL PRIVILEGES ON quizpal.* TO 'quizpal'@'localhost';
   ```

   Point `.env` at it with `DB_CONNECTION=mysql`, `DB_HOST=127.0.0.1`, `DB_PORT=3306`, `DB_DATABASE`, `DB_USERNAME` and
   `DB_PASSWORD` (`.env.example` ships with `DB_CONNECTION=sqlite`, so set it explicitly), then
   `php artisan migrate --force` and `php artisan db:seed --class=QuestionSeeder --force`. The seeder loads the built-in
   questions and the Open Trivia DB snapshot; run it again after every deploy to pick up new questions and translations.

   An installation that ran on SQLite before (until November 2026) is switched the same way: point `.env` at MySQL,
   migrate, seed, then `php artisan optimize`. The old `database/database.sqlite` is left alone; nothing is copied from it.
5. **Cache**: `php artisan optimize`. Run it again after every change to `.env`, because the configuration is cached.
6. **Permissions**: `storage/` and `bootstrap/cache/` must be writable by the user PHP-FPM runs as. Keep `.env` readable only by that user.
7. **Scheduler**: add a cron entry so idle rooms get cleaned up every day:

   ```cron
   * * * * * cd /var/www/quizpal && php artisan schedule:run >> /dev/null 2>&1
   ```

8. **HTTPS**: with the nginx site in place, `certbot --nginx -d your-domain --redirect` adds the certificate and the
   HTTP-to-HTTPS redirect. certbot writes its lines into the site's nginx file, so do not overwrite that file later.

No queue worker is required: game events are broadcast synchronously, so a Reverb outage never leaves jobs behind.

### Reverb

Reverb is the WebSocket server that pushes room updates to every player. Run **one Reverb process per
application**. Reverb can technically serve several apps from one process (the `apps.apps` list in
`config/reverb.php`), but then the credentials of one app live in another app's config and every restart of that
process takes both apps' live updates down. A separate process costs 30 to 50 MB of RAM.

1. **Pick a port** that nothing else uses, and check the `.env` of the other apps on the server too, not only
   `ss -ltn`: another app may name a port it does not use yet. Bind Reverb to `127.0.0.1`; the web server is the
   only client.
2. **Trial run**: `timeout 6 php artisan reverb:start --host=127.0.0.1 --port=8001` should print "Starting server"
   and exit cleanly.
3. **Supervisor program** (`/etc/supervisor/conf.d/quizpal-reverb.conf`):

   ```ini
   [program:quizpal-reverb]
   process_name=%(program_name)s
   command=/usr/bin/php8.3 /var/www/quizpal/artisan reverb:start --host=127.0.0.1 --port=8001
   directory=/var/www/quizpal
   autostart=true
   autorestart=true
   user=www-data
   redirect_stderr=true
   stdout_logfile=/var/www/quizpal/storage/logs/reverb.log
   stdout_logfile_maxbytes=10MB
   stdout_logfile_backups=3
   stopwaitsecs=30
   ```

   Register it with `supervisorctl reread` then `supervisorctl update`; these only add the new program and leave the
   running ones alone. Check with `supervisorctl status quizpal-reverb` and `ss -ltnp | grep 8001`.
4. **Reverse proxy** (inside the HTTPS server block of the site, before the PHP location):

   ```nginx
   location /app {
       proxy_http_version 1.1;
       proxy_set_header Upgrade $http_upgrade;
       proxy_set_header Connection "upgrade";
       proxy_set_header Host $host;
       proxy_set_header X-Real-IP $remote_addr;
       proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
       proxy_set_header X-Forwarded-Proto $scheme;
       proxy_read_timeout 180s;
       proxy_pass http://127.0.0.1:8001;
   }
   ```

   `proxy_read_timeout` must be longer than Reverb's ping interval (60 seconds by default) or nginx closes idle
   connections. Validate with `nginx -t` and reload.
5. **Check the handshake** through the real hostname. Use HTTP/1.1 explicitly: over HTTP/2 nginx cannot forward a
   WebSocket upgrade, curl then gets a 404 or 500 from Reverb, while browsers (which always use HTTP/1.1 for `wss://`)
   work fine.

   ```bash
   curl --http1.1 -s -o /dev/null -w '%{http_code}\n' -m 5 \
     -H 'Connection: Upgrade' -H 'Upgrade: websocket' \
     -H 'Sec-WebSocket-Version: 13' -H 'Sec-WebSocket-Key: dGhlIHNhbXBsZSBub25jZQ==' \
     "https://your-domain/app/$REVERB_APP_KEY?protocol=7"      # expect 101
   ```

6. **Switch the application to Reverb** in `.env`. The server-side values tell PHP where to publish events; use the
   IP, not `localhost`, which may resolve to `::1` while Reverb listens on IPv4 only:

   ```dotenv
   BROADCAST_CONNECTION=reverb
   REVERB_HOST=127.0.0.1
   REVERB_PORT=8001
   REVERB_SCHEME=http
   REVERB_SERVER_HOST=127.0.0.1
   REVERB_SERVER_PORT=8001
   ```

   Then `php artisan optimize` and `php artisan reverb:restart` (supervisor starts it again).
7. **Verify live**: the lobby of any room must not show the "Live updates are unavailable" warning. Then open the site in two different browsers, create a room in one and join from the other. The new
   player must appear on the first screen within a second or two; if it only shows up after about ten seconds, the
   WebSocket is not connected and the page is polling.

After each deployment run `php artisan migrate --force`, then `php artisan optimize` and `php artisan reverb:restart`, so that new migrations are applied and Reverb picks up new code.

## Troubleshooting

- **Players do not see each other's actions until they reload, or only after about ten seconds**: Reverb is not
  reachable. Check that the supervisor program is running, that `VITE_REVERB_HOST/PORT/SCHEME` match how the browser
  reaches the site, that the assets were rebuilt after changing them, and that the `/app` proxy points at Reverb's port.
  The page still polls every 10 seconds, so the game keeps working, only slower.
- **The lobby shows "Live updates are unavailable" and Start game is disabled**: the application cannot reach
  Reverb's health check at `REVERB_SCHEME://REVERB_HOST:REVERB_PORT/up`. Check the port, use `127.0.0.1` rather than
  `localhost`, and confirm the supervisor program is up (`supervisorctl status quizpal-reverb`). Reverb is mandatory
  for starting a game; joining, pressing ready and changing settings keep working through polling meanwhile, and each
  failed broadcast is reported to the application log.
- **curl handshake returns 404 or 500 but browsers work**: the test used HTTP/2. Add `--http1.1`.
- **"Mixed content" errors in the browser console**: the site is served over HTTPS but `VITE_REVERB_SCHEME` is `http`.
  Use `https` and port `443` through the reverse proxy, then rebuild the assets.
- **SQLSTATE[HY000] [1045] Access denied** or **[2002] Connection refused** on every page: the `DB_*` values in `.env`
  do not match the MySQL user, or the configuration cache is stale. Fix `.env`, then `php artisan optimize`.
- **Database is locked** (SQLite, local setups only): the SQLite file or its directory is not writable by the user
  running PHP.
