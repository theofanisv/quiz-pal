# QuizPal

QuizPal is a real-time multiplayer quiz you play with friends on your phones. One person creates a room, shares the link or the QR code, everyone picks a name and presses ready, and the room plays through a set of questions together. Fast, correct answers score the most points.

The app and the whole question bank are available in **English and Greek**; every player picks their own language.

## Features

- Rooms with short, easy-to-type codes (for example `KXWP`), shareable as a link or a QR code
- No accounts: players only choose a name, and the room creator is the admin for the room's lifetime
- Lobby with ready states, live presence ("away" players never block the game) and admin-only settings: number of rounds, seconds per question and categories
- Timed rounds: everyone sees that someone has answered but not what they chose, then the choices and the correct answer are revealed when everyone has answered or the timer runs out
- Speed-based scoring: a correct answer is worth 1000 points when instant and 500 at the buzzer; wrong answers score nothing
- Final leaderboard and "play again" with the same players
- Live updates through [Laravel Reverb](https://reverb.laravel.com) WebSockets. Reverb is mandatory: the lobby refuses to start a game while its server is down, and every page also polls every 10 seconds as a safety net for dropped connections
- 300 curated questions across 10 categories, stored as bilingual JSON files and importable with one command

## How to play

1. Open the home page, type your name and press **Create room**. You are the admin.
2. Share the link or let friends scan the QR code shown in the lobby. They open it, pick a name and join.
3. Adjust rounds, timer and categories if you like. Everyone presses **I'm ready**; the admin presses **Start game**.
4. Each round shows a question with four answers. Tap one before the timer ends. The faster you are, the more points a correct answer earns.
5. When the round is revealed, the admin presses **Next question**. After the last round the final results appear and the admin can start a new game in the same room.

## Tech stack

- [Laravel 13](https://laravel.com) (PHP 8.3) with SQLite
- [Livewire 4](https://livewire.laravel.com) and [Flux UI](https://fluxui.dev) for the interface, Tailwind CSS 4 for styling
- [Laravel Reverb](https://reverb.laravel.com) and Laravel Echo for real-time events
- Pest 4 for tests, Larastan (PHPStan level 7) and Laravel Pint for code quality

See [INSTALLATION.md](INSTALLATION.md) for setup and deployment instructions.

## How it works

Game state lives in the database. Every action (joining, pressing ready, answering, revealing) goes through an action class in `app/Actions/Game`, which then broadcasts a lightweight `RoomUpdated` event on the room's presence channel. Each player's page listens for that event and re-renders from the database, so all devices always show the same state and no client is trusted with timing or scoring.

Key places:

| Path | What lives there |
| --- | --- |
| `app/Actions/Game` | One class per game rule: `CreateRoom`, `JoinRoom`, `StartGame`, `SubmitAnswer`, `RevealRound`, ... |
| `app/Livewire/Home.php`, `app/Livewire/Game/Room.php` | The two pages (home and room) |
| `app/Models` | `Room`, `Player`, `Round`, `Answer`, `Category`, `Question` |
| `app/Support/Scoring.php` | The points formula |
| `routes/channels.php` | Presence channel authorization for anonymous players |
| `database/data/questions/*.json` | The question bank, one file per category |
| `lang/el.json`, `lang/el/validation.php` | Greek translations of the interface |

Players are anonymous. A dedicated `player` session guard identifies the visitor's player record, which also lets Reverb authorize them on the room's presence channel.

## Questions

Questions live in `database/data/questions/`, one JSON file per category:

```json
{
    "category": { "slug": "science", "order": 2, "name": { "en": "Science & Nature", "el": "Επιστήμη & Φύση" } },
    "questions": [
        {
            "text": { "en": "Which planet is known as the Red Planet?", "el": "Ποιος πλανήτης είναι γνωστός ως ο «Κόκκινος Πλανήτης»;" },
            "answers": { "en": ["Venus", "Mars", "Jupiter", "Mercury"], "el": ["Αφροδίτη", "Άρης", "Δίας", "Ερμής"] },
            "correct": 1,
            "difficulty": 1
        }
    ]
}
```

`correct` is the zero-based index of the right answer in both answer lists; `difficulty` (1–3) is optional. Answers are shuffled for each round, the same way for every player.

Import or update questions with:

```bash
php artisan quiz:import-questions                  # every file in database/data/questions
php artisan quiz:import-questions path/to/file.json
```

Importing the same file twice updates the existing rows instead of duplicating them, so you can edit a file and re-import it. The test suite validates every bundled file (four distinct answers in both languages, a valid correct index, no duplicate questions).

### Open Trivia DB

A room can also play the multiple-choice questions of [Open Trivia DB](https://opentdb.com) ([CC BY-SA 4.0](https://creativecommons.org/licenses/by-sa/4.0/)). They ship with the app as a snapshot in `database/data/opentdb/`, one file per OpenTDB category in the format above plus a `source`/`external_id` tag on the category and an attribution line. The seeder and `php artisan quiz:import-questions` load them together with the built-in bank.

Questions that have not been translated yet have no `el` keys and are shown in English in both languages. Translating a category means adding the `el` keys to its file, importing it and committing. Refreshing the snapshot from OpenTDB is a developer task:

```bash
php artisan quiz:sync-opentdb       # pull OpenTDB into the local database; about 20 minutes, one request every 5 seconds
php artisan quiz:export-opentdb     # rewrite database/data/opentdb/*.json, keeping the translations that still match
```

The admin picks the question source in the lobby; the Open Trivia DB option stays disabled until the snapshot has been imported.

## Development

```bash
php artisan dev        # web server, Reverb, queue, logs and Vite in one terminal
php artisan test       # test suite
vendor/bin/pint        # code style
composer types:check   # static analysis
composer test          # lint, static analysis and tests, as CI runs them
```

Rooms that have been idle for 7 days are deleted by the scheduled `quiz:prune-rooms` command (run `php artisan quiz:prune-rooms --days=1` for a shorter threshold).
