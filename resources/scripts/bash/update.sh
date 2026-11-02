#!/usr/bin/env bash
#
# Updates a deployed QuizPal checkout to the latest commit of its branch.
#
# Run it from the application root through Composer, so that the PHP binary
# that runs Composer is also used for Artisan (on a server with several PHP
# versions call the one that serves the site):
#
#     php8.3 /usr/local/bin/composer update-app
#
# Steps: back up the database, fetch, drop the cached bootstrap files, reset
# the checkout to origin/<branch>, install Composer dependencies, migrate, seed
# the question bank, run the pending one-time operations (database/operations),
# rebuild the caches and restart Reverb. The front-end is
# not built here: public/build is built locally and committed, and the update
# refuses to start when the incoming commit does not contain it. It also stops
# before touching anything when tracked files were modified on the server,
# because the reset would discard those changes.
#
# Environment overrides:
#   PHP_BIN       PHP binary for Artisan (default: the PHP running Composer, else "php")
#   COMPOSER_BIN  Composer command (default: the Composer running this script)
#   BACKUP_KEEP   Database backups to keep in storage/app/private/backups (default: 10)

set -Eeuo pipefail

app_root=$(cd "$(dirname "${BASH_SOURCE[0]}")/../../.." && pwd)
cd "$app_root"

php_bin=${PHP_BIN:-${PHP_BINARY:-php}}
backup_keep=${BACKUP_KEEP:-10}
backup_dir=storage/app/private/backups
stamp=$(date +'%Y-%m-%d_%H-%M-%S')

if [ -n "${COMPOSER_BIN:-}" ]; then
    composer_cmd=("$COMPOSER_BIN")
elif [ -n "${COMPOSER_BINARY:-}" ]; then
    composer_cmd=("$php_bin" "$COMPOSER_BINARY")
else
    composer_cmd=(composer)
fi

# Prints the application environment, the database driver and the database
# name (one per line) and writes a MySQL option file with the credentials to
# the path given as the first argument, so that the password never appears on
# the command line.
# shellcheck disable=SC2016
read_database_config='
require "vendor/autoload.php";
$app = require "bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$connection = config("database.connections.".config("database.default"));
echo config("app.env"), "\n", $connection["driver"], "\n", $connection["database"], "\n";
if ($connection["driver"] === "mysql") {
    $quote = fn ($value) => "\"".addcslashes((string) $value, "\\\"")."\"";
    $lines = [
        "[client]",
        "host=".$quote($connection["host"] ?? "127.0.0.1"),
        "port=".$quote($connection["port"] ?? 3306),
        "user=".$quote($connection["username"] ?? ""),
        "password=".$quote($connection["password"] ?? ""),
    ];
    if (! empty($connection["unix_socket"])) {
        $lines[] = "socket=".$quote($connection["unix_socket"]);
    }
    file_put_contents($argv[1], implode("\n", $lines)."\n");
}
'

step() {
    echo
    echo "==> $*"
}

abort() {
    local code=$1
    shift
    echo "$*" >&2
    exit "$code"
}

# shellcheck disable=SC2154
trap 'status=$?; echo; echo "Update FAILED (exit $status) while running: $BASH_COMMAND" >&2' ERR

echo "Starting update $(date)"
echo
git --no-pager log -n1
echo

# PRE-FLIGHT

[ "$(id -u)" -ne 0 ] || abort 1 "Do not run the update as root: run it as the user that owns the application files."
command -v "$php_bin" > /dev/null || abort 1 "PHP binary not found: $php_bin"
command -v git > /dev/null || abort 1 "git is not installed."
[ -f vendor/autoload.php ] || abort 1 "vendor/autoload.php is missing: this script updates an installed application, install it first."

current_branch=$(git branch --show-current)
[ -n "$current_branch" ] || abort 1 "The checkout is on a detached HEAD: check out a branch before updating."

if [ -n "$(git status --porcelain --untracked-files=no)" ]; then
    git status --short --untracked-files=no
    abort 1 "Tracked files have local changes and the update would discard them. Commit, stash or revert them first."
fi

database_defaults=$(mktemp "${TMPDIR:-/tmp}/quizpal-database.XXXXXX")
chmod 600 "$database_defaults"
trap 'rm -f "$database_defaults"' EXIT

database_config=$("$php_bin" -r "$read_database_config" -- "$database_defaults")
app_env=$(printf '%s\n' "$database_config" | sed -n 1p)
database_driver=$(printf '%s\n' "$database_config" | sed -n 2p)
database_name=$(printf '%s\n' "$database_config" | sed -n 3p)
echo "Environment: $app_env, database: $database_driver ($database_name), branch: $current_branch"

# BACKUP

step "Backing up the database"
mkdir -p "$backup_dir"
backup_file=
case $database_driver in
    mysql)
        command -v mysqldump > /dev/null || abort 1 "mysqldump is required to back up the database before updating."
        backup_file=$backup_dir/update-$stamp.sql.gz
        mysqldump --defaults-extra-file="$database_defaults" --single-transaction --quick --no-tablespaces --set-gtid-purged=OFF "$database_name" | gzip > "$backup_file" \
            || { rm -f "$backup_file"; abort 3 "The database dump failed. Aborting update..."; }
        ;;
    sqlite)
        if [ -f "$database_name" ]; then
            backup_file=$backup_dir/update-$stamp.sqlite
            cp "$database_name" "$backup_file"
        else
            echo "SQLite file '$database_name' not found; nothing to back up."
        fi
        ;;
    *)
        echo "No backup routine for the '$database_driver' driver; continuing without one."
        ;;
esac

if [ -n "$backup_file" ]; then
    chmod 600 "$backup_file"
    ls -lh "$backup_file"
    { ls -1t "$backup_dir"/update-* 2> /dev/null || true; } | tail -n +"$((backup_keep + 1))" | while IFS= read -r old_backup; do
        echo "Removing old backup $old_backup"
        rm -f "$old_backup"
    done
fi

# UPDATE

step "Fetching origin/$current_branch"
if ! git fetch origin "$current_branch"; then
    abort 2 "Failed to fetch repository. Aborting update..."
fi
git cat-file -e "origin/$current_branch:public/build/manifest.json" 2> /dev/null \
    || abort 1 "origin/$current_branch has no public/build/manifest.json: build the assets locally (npm run build) and commit public/build before updating."

step "Clearing the cached bootstrap files"
# Not "optimize:clear": that also flushes the application cache, which is a FLUSHDB on a Redis database shared with other apps.
"$php_bin" artisan optimize:clear --except=cache

step "Resetting the checkout to origin/$current_branch"
previous_commit=$(git rev-parse HEAD)
git reset --hard "origin/$current_branch"
if [ "$previous_commit" = "$(git rev-parse HEAD)" ]; then
    echo "Already at the latest commit."
else
    git --no-pager log --oneline "$previous_commit..HEAD"
fi
echo
git --no-pager log -n1
echo

# AFTER UPDATE

step "Installing Composer dependencies"
composer_flags=(--no-interaction --prefer-dist --optimize-autoloader)
if [ "$app_env" = production ]; then
    composer_flags+=(--no-dev)
fi
"${composer_cmd[@]}" install "${composer_flags[@]}"

step "Migrating the database"
"$php_bin" artisan migrate --force

step "Seeding the question bank"
"$php_bin" artisan db:seed --class=QuestionSeeder --force

step "Running one-time operations"
# --sync: no queue worker runs on the server, and a queued operation would be marked as processed without running.
"$php_bin" artisan operations:process --sync --no-interaction

step "Rebuilding the caches"
"$php_bin" artisan optimize

step "Restarting Reverb"
"$php_bin" artisan reverb:restart

echo
echo "Ending update $(date)"
