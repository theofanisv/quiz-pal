---
name: review-reported-questions
description: "Review the quiz questions players reported on production: fetch them from the server, fact-check each one, fix the wrong ones in the question JSON files, and mark the correct ones verified on production. Run only when the owner asks for it (e.g. \"review the reported questions\", \"/review-reported-questions\"). Not for importing, syncing or translating question banks."
disable-model-invocation: true
---

# Review reported questions

Players flag a question they think is wrong with the "Report question" button. That sets
`questions.reported_at`. New games skip a question while it **waits for review**, which means `reported_at` is
set and `verified_at` is empty or older. This skill reviews those questions and closes them in two ways:

- **Correct as it is:** mark it verified on production (`verified_at = now()`), and games use it again.
- **Wrong:** fix it in the question JSON file and commit. After the owner deploys, the next run of this skill
  finds the corrected content on production and verifies it.

Production runs on the owner's VPS (`ssh parkingmou`, app in `/var/www/quizpal`, PHP binary `php8.3`). Follow the
owner's server notes (see the global CLAUDE.md). Announce each step, read before writing, and stop and ask on
anything unexpected.

## 1. Fetch the list (read-only)

```bash
ssh parkingmou 'cd /var/www/quizpal && php8.3 artisan quiz:reported-questions --json'
```

Each entry has `id`, `category.slug`, `category.source` (`custom` or `opentdb`), `reported_at`, `verified_at`,
and `text`, `answers`, `correct` in the same shape as the question files. An empty list means there is nothing
to do. Say so and stop.

## 2. Find each question in the repository

Question content lives in JSON files. `QuestionSeeder` re-imports them on every deploy, matching rows by
category and English text:

- `database/data/questions/*.json`: the built-in bilingual bank (`category.source` `custom`).
- `database/data/opentdb/*.json`: the Open Trivia DB snapshot (`opentdb-<id>` slugs). One question per line. The
  Greek text is optional, and when it is missing players see the English.

Find the file with `grep -l '"slug": "<slug>"' database/data/*/*.json`, then find the question by its English
text with `grep -nF`. Some OpenTDB texts start with a space. If the repository copy differs from production, the
fix may already be committed but not deployed. Report it and leave that question alone.

## 3. Judge each question

Check every point, in both languages when Greek is present:

- Exactly one answer is correct, and `correct` (0-based) points to it.
- The question is clear and still true today, so it is not out of date.
- The Greek text means the same as the English, and `answers.el[i]` is the translation of `answers.en[i]`, in
  the same order.
- The answers are four distinct, non-empty strings, and no text is longer than 500 characters.

Fact-check with web search and keep one source link per verdict. If the evidence is mixed, call the question
"unclear" and leave it for the owner rather than guessing.

## 4. Fix the wrong ones in the JSON file

- Edit only what is wrong. Keep the file's formatting: the OpenTDB files have one question per line.
- **Keep the English text when you can.** It is the key that matches the row on production. If the English text
  itself has to change, add `"replaces": "<old English text>"` to the question. The importer then renames the
  existing row in place and keeps its id, its game history and its reported state. Without `replaces`, the
  import would add a second row and leave the wrong one waiting forever.
- Never delete a question from a file. The importer never deletes rows. Rewrite a broken question into a correct
  one with `replaces` instead.
- Validate the file against the local database: `php83 artisan quiz:import-questions <file>`. It must print
  "Imported …" with no error.
- OpenTDB caveat: re-running `quiz:sync-opentdb` and `quiz:export-opentdb` later rewrites the English fields from
  the API. Whoever does that must check the snapshot diff for fixes that got reverted.

## 5. Report to the owner and ask before any production write

Show one table: id, category, verdict (correct / fixed / unclear / already fixed, not deployed), reason, source
link, and the change you made. Then ask the owner to confirm marking the **correct** ones verified on
production. Only after a yes:

```bash
ssh parkingmou 'cd /var/www/quizpal && php8.3 artisan quiz:verify-questions <id> <id> …'
```

Never verify a question whose fix is not live on production yet. Check that the command's output says
"Verified N questions." with the expected N, and show any warnings.

## 6. Commit the fixes

Another Claude session may be working in the same checkout. Before committing, run `git log --oneline -3` and
`git status --short`. Use a branch if the owner asks for one, stage the JSON files by explicit path (never
`git add -A`), and commit with a message that names the question ids and what was wrong. Do not push or deploy
unless the owner asks. Once the owner deploys with `composer update-app`, run this skill again to verify the
fixed questions.
