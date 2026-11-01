# Installing QuizPal

## Requirements

- PHP 8.3 or newer with the `pdo_sqlite`, `sqlite3`, `mbstring`, `xml`, `xmlwriter`, `curl` and `pcntl` extensions
- Composer 2
- Node.js 22 or newer with npm
- Git

No database server is needed: QuizPal stores everything in a SQLite file.

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

QuizPal is a standard Laravel application plus one long-running Reverb process.

1. **Build**: `composer install --no-dev --optimize-autoloader`, `npm ci && npm run build`.
2. **Environment**: set `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://your-domain`, random `REVERB_APP_*` values, and for browsers behind HTTPS:

   ```dotenv
   REVERB_HOST=your-domain
   REVERB_PORT=443
   REVERB_SCHEME=https
   ```

   Reverb itself keeps listening on `127.0.0.1:8080`; the web server proxies to it (see below). Rebuild the assets after changing `VITE_REVERB_*`.
3. **Database**: `touch database/database.sqlite`, make the `database/` directory writable by the web server user, then `php artisan migrate --force` and `php artisan db:seed --class=QuestionSeeder --force`.
4. **Cache**: `php artisan optimize`.
5. **Reverb as a service** (Supervisor example):

   ```ini
   [program:quizpal-reverb]
   command=php /var/www/quizpal/artisan reverb:start --host=127.0.0.1 --port=8080
   directory=/var/www/quizpal
   user=www-data
   autostart=true
   autorestart=true
   stdout_logfile=/var/log/quizpal-reverb.log
   ```

   Run `php artisan reverb:restart` after each deployment so the server picks up new code.
6. **Reverse proxy** (nginx snippet inside the HTTPS server block of the site):

   ```nginx
   location /app {
       proxy_http_version 1.1;
       proxy_set_header Host $host;
       proxy_set_header Upgrade $http_upgrade;
       proxy_set_header Connection "Upgrade";
       proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
       proxy_pass http://127.0.0.1:8080;
   }

   location /apps {
       proxy_http_version 1.1;
       proxy_set_header Host $host;
       proxy_pass http://127.0.0.1:8080;
   }
   ```

7. **Scheduler**: add a cron entry so idle rooms get cleaned up every day:

   ```cron
   * * * * * cd /var/www/quizpal && php artisan schedule:run >> /dev/null 2>&1
   ```

No queue worker is required: game events are broadcast synchronously, so a Reverb outage never leaves jobs behind.

## Troubleshooting

- **Players do not see each other's actions until they reload**: Reverb is not reachable. Check that `php artisan reverb:start` is running, that `VITE_REVERB_HOST/PORT/SCHEME` match how the browser reaches it, and that the assets were rebuilt after changing them. The page still polls every 10 seconds, so the game keeps working, only slower.
- **"Mixed content" errors in the browser console**: the site is served over HTTPS but `VITE_REVERB_SCHEME` is `http`. Use `https` and port `443` through the reverse proxy.
- **Database is locked**: the SQLite file or its directory is not writable by the web server user.
