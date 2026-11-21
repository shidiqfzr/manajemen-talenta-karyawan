# Contributor onboarding: design

Date: 2026-11-21. Size: a medium change, about 20 files. The only product code is one new seeder, a small change to the existing demo accounts, and four Composer scripts.

## Intent and why

The owner wants to keep building the HRIS (PALM) on a second laptop and to make it easy for another developer to join. A new machine, or a new contributor, should go from `git clone` to a running application and a green quality gate without guessing, and without ever touching real employee data. The project holds personal data covered by UU PDP, so "easy to contribute" has to mean "easy to contribute safely".

## Goals

1. **One command to set up, one command to verify.** `composer setup` prepares a working local copy; `composer verify` runs the same checks as the CI quality gate.
2. **A contribution guide that restates the project's own standards**, not new ones: branch and commit naming as the history already uses them, the five-stage workflow, the service-layer and transaction rules, one test class per component, terminology, and the privacy rules.
3. **Realistic local data without real people.** A demo seeder creates synthetic employees and evaluations on top of the real master units and quotas, so the formation, mutation, retirement and 9-box screens show data on a fresh clone. A second demo administrator makes the four-eyes approval flows testable locally.
4. **Documents that work for strangers.** Replace the 21 absolute links that point at the first laptop (`file:///c:/Users/<user>/...`, one per line, in `AGENTS.md` 4, `DOMAIN.md` 7, `ENGINEERING.md` 5, `README.md` 5) with relative links, correct the stale README facts, and add a test that keeps them correct.
5. **Review controls ready for a second person.** `CODEOWNERS`, a pull request template and issue templates that mirror the four reference documents.
6. **Close data-leak paths.** Spreadsheets, CSV files and database dumps are ignored everywhere in the repository, not only at its root.
7. **Fix two wrong lines in the release checklist** and record the queue retry setting in `.env.example` (both came out of the first production deployment).

## Non-goals

- A container-based development environment (considered and rejected below).
- Any change to application behaviour, to the production server, or to repository visibility.
- Renaming or restructuring the CI jobs: their names are the required status checks on `main`.
- Demo trainings and demo mutation history beyond the initial placement record (follow-ups if a contributor needs them).
- The `deploy/` folder (server configuration, deploy script, backup script): a separate change after the nightly backup is verified on the server.
- Renaming the local folder of the first laptop.

## Anti-goals (a failure even if it technically works)

- A setup that only works on the owner's machine.
- Any real NIK, name, or realistic-looking employee record in the repository, in demo data, in a template example, or in a screenshot.
- A demo seeder that can run in production or on a database that already holds employees.
- A setup command that can migrate or re-seed a production database (DOMAIN.md §3.7 and the agent rules forbid re-running seeders on a retained database).
- Documents that tell contributors to bypass the reference documents, for example seeding production with demo data, editing `master_formasi.json` on a retained database, or hardcoding RM or bidang calculations.

## Constraints

- PHP ^8.4 (Composer platform pinned to 8.4.1), Node 22, Laravel 13, Livewire 4, PHPUnit 11. Local development defaults to SQLite; production is MySQL.
- Windows is the owner's primary environment. Every script must be portable: Composer `@php` and `@composer` steps, tools called by name (Composer puts `vendor/bin` on the path for scripts), no shell-only syntax.
- Composer stops any script after 300 seconds by default, and the full PHPUnit suite takes about 10 minutes, so long scripts must disable that timeout the same way the existing `dev` script does.
- The reference documents rule: service layer, `DB::transaction()` for multi-table writes, one test class per component, Pint, official terminology, no internal document names in user-visible text, structure changes only through the SK service, no hardcoded RM or bidang calculations, every placement recorded in `job_histories`.
- `main` is protected: changes arrive by pull request with the two CI checks. Commits follow the existing Conventional Commits style in English.
- Developer-facing documents on GitHub are in English (the owner's standing preference); the README keeps its Indonesian text; user-visible application text stays Indonesian.

## Approaches considered

| | Approach | Trade-off |
|---|---|---|
| A | Documents only (guide, README fixes, templates) | Cheap, but every developer installs and debugs the toolchain by hand, which is where newcomers get stuck. |
| **B** | **Documents plus one-command setup and verify, demo data, guards, `CODEOWNERS`** | Most value for the effort; follows the Laravel convention; no new software. **Chosen by the owner.** |
| C | Container-based environment (Docker, devcontainer, Sail) | Identical environments, but Docker on Windows 10 Home needs WSL2 and is heavy and slow for one or two developers. |

## Design (approach B)

### Composer scripts (`composer.json`)

All four disable Composer's process timeout.

- **`setup`**, in this order:
  1. `composer install`.
  2. Copy `.env.example` to `.env` if `.env` is missing.
  3. **Stop with a clear message if `.env` has `APP_ENV=production`.** Nothing after this point may run on a server.
  4. Generate the application key **only if `APP_KEY` is empty**. Re-generating it would invalidate encrypted sessions and data.
  5. Create `database/database.sqlite` if the connection is SQLite and the file is missing.
  6. `php artisan migrate --seed` (the existing seeders are idempotent, so a re-run is safe).
  7. `npm ci` (reproducible from the lockfile), then `npm run build`.
- **`verify`**: `pint --test`, `composer audit`, `php artisan test`, `npm audit --audit-level=high`, `npm run test:js`. These are the CI gate's checks. The asset build is already done by `setup`; the browser (Playwright) suite stays separate because it takes minutes. Needs network access for the two audits.
- **`demo`**: runs the demo seeder.
- **`dev`**: unchanged. The README also gives the three separate commands (`php artisan serve`, `php artisan queue:listen --tries=1`, `npm run dev`) for anyone whose machine cannot run `composer dev`. Imports are queued jobs, so a local queue worker is required to test them.

### Demo data

**`database/seeders/DemoEmployeeSeeder.php`** (not called by `DatabaseSeeder`)

- **Guards** (each throws a clear message and writes nothing): production environment; `employees` table not empty; master units or positions missing (tells the user to run `composer setup` first).
- **About 60 synthetic employees** named "Karyawan Contoh NN", NIK an 8-digit string starting with `99` so demo records are recognisable. Attached to real master units and positions, both Karpim and Karpel. Never more definitive holders than a position's quota; a few acting (Pj/Plt) assignments, which also count towards the quota.
- **Golongan** in the `[Gol]/[Berkala]` format, inside the strata's grade range from `KepegawaianPolicy::gradeRange()`.
- **Birth dates** spread so that some employees are within the MBT warning window, some past MBT, and most far from retirement.
- **Created through Eloquent with model events on** (no bulk insert, no `createQuietly`, no `withoutEvents`), and with `rm_level`, `tanggal_pensiun` and `tanggal_mbt` left empty. `EmployeeObserver` then derives the RM level (`ManPowerPlanningService`), the retirement and MBT dates (`KepegawaianPolicy`), and writes the initial `job_histories` row. No formula is copied into the seeder.
- **Evaluations** for about two thirds of the employees, written through `EvaluationService::processStore()`, spread so every 9-box cell has someone.
- **One transaction** for the whole run, so a failure leaves the database empty again.
- Every generated employee must satisfy the same field rules as the Excel import (NIK format, golongan format, valid dates, existing unit and position).

**Second demo administrator** (`UserSeeder`, non-production only): add `admin2` ("Admin Pemeriksa", `admin2@example.com`, the same public demo password). With `HRIS_STRUCTURE_REQUIRE_APPROVAL=true` (the default), one admin can submit an SK and the other approve it. Production behaviour is unchanged. `UserSeederTest` is updated for three demo accounts.

**Tests**: one test class for the demo seeder (`DemoEmployeeSeederTest`) covering the three guards, quota respect, NIK and golongan formats, the demo naming, the observer-derived fields and history rows, evaluation coverage, and the rollback. The existing `UserSeederTest` covers the second demo admin.

### Documents

- **`README.md`**: prerequisites (PHP 8.4 with `mbstring`, `intl`, `gd`, `zip`, `bcmath`, `xml`, `fileinfo`, `pdo_sqlite`, `sqlite3`; Composer 2; Node 22; Git), with a short Windows note on enabling extensions in `php.ini`. Quick start in three commands (`git clone`, `composer setup`, `composer dev`), plus `composer demo`. Local SQLite versus production MySQL. The demo accounts and their public password. How to run the checks. A link to the contribution guide. Relative links. Corrected facts: 47 units (not 43); MBT is six months before retirement (not age 55).
- **`CONTRIBUTING.md`** (English):
  - Setup and daily commands.
  - Branch names: `feat/`, `fix/`, `refactor/`, `chore/`, `ci/`, `docs/`.
  - Conventional Commits with examples from the history.
  - The five-stage workflow and the standards on one page, linking to the reference documents instead of repeating them.
  - Before opening a PR: `composer verify`, and the browser suite for UI changes.
  - Review expectations.
  - **Privacy rules**: demo data only; never copy the production database, a backup or an export onto a development machine or into the repository; never paste real employee data or screenshots that show it.
  - Reporting a vulnerability: SECURITY.md §6, never a public issue.
  - Deployment is done by maintainers only.
- **`.github/PULL_REQUEST_TEMPLATE.md`**: checklist mapped to the four reference documents and the agent rules (terminology, no internal document names in user-visible text, SK-only structure changes, one test class per component, `composer verify` passed, no real data).
- **`.github/ISSUE_TEMPLATE/`**: `bug_report.yml`, `feature_request.yml`, and `config.yml` that disables blank issues and links vulnerabilities to SECURITY.md §6. Each form carries the warning about real employee data.
- **`.github/CODEOWNERS`**: the owner for everything, with explicit lines for `DOMAIN.md`, `SECURITY.md`, `AGENTS.md`, `app/Services/`, `database/migrations/`, `database/data/` and `.github/`. When a second developer joins, add them as an owner of the general paths. Otherwise code-owner review would block the owner's own pull requests.
- **`.nvmrc`** (22) and `"engines": {"node": ">=22"}` in `package.json`.
- **Link fix** in `AGENTS.md`, `README.md`, `DOMAIN.md`, `ENGINEERING.md`: only the 21 link targets change. Back up the four files first, compute the new text before opening a file for writing, and confirm by diff that exactly 21 lines changed in those files and nothing else.
- **`ENGINEERING.md`**: in §4, remove `APP_TIMEZONE=Asia/Jakarta` and `APP_LOCALE=id` (the application runs in UTC by design, DOMAIN.md §3.7), and replace `route:cache` with `config:cache`, `view:cache`, `event:cache` (routes use closures). Document `composer setup`, `composer verify` and `composer demo` in §3.3 and §3.4, and the demo seeder rules in §3.
- **`SECURITY.md`**: next to the existing demo-account rule (§3 item 3), state that demo employees are synthetic, refuse to run in production or on a database with employees, and that production data and backups never go onto development machines.
- **`database/seeders/DatabaseSeeder.php`**: keep the docblock and add one sentence pointing local users to `composer demo`.
- **`.env.example`**: add `DB_QUEUE_RETRY_AFTER=660` with a comment (import jobs may run for up to 600 seconds, so the retry time must be longer).
- **`.gitignore`**: ignore `*.xlsx`, `*.xls`, `*.csv`, `*.sql`, `*.sql.gz` and `*.dump` anywhere, not only at the root. If a synthetic test fixture is ever needed, allow it by an explicit `!` exception.

### Guard test

`tests/Unit/DocumentationLinksTest.php` (one class, one subject). It fails if any root-level Markdown file (`README.md`, `AGENTS.md`, `CONTRIBUTING.md`, `DOMAIN.md`, `DESIGN.md`, `ENGINEERING.md`, `SECURITY.md`) contains `file:///` or a `c:/Users` path, or has a relative link to a file that does not exist. Files under `docs/` are out of scope, since specs and plans may quote an example path. It reads the files from disk, so it does not depend on Git.

### GitHub settings

These are not files. Apply them only with the owner's go-ahead, after the second developer joins.

- Required approvals on `main`: 1, with code-owner review. With admins included, the owner then also needs the second developer's approval. That is four-eyes review, matching the project's own approval principle, and Dependabot pull requests need an approval too.
- Enable secret scanning, push protection and private vulnerability reporting.
- Contributors get Write access (branches in this repository) or use forks; maintainers merge.

## Acceptance

1. **Fresh clone.** In a fresh clone in an empty folder, `composer setup` finishes, `composer dev` (or the three commands) starts the app, and demo `admin` can log in. `composer verify` is green without being cut off.
2. **Re-run.** Re-running `composer setup` keeps the same `APP_KEY` and changes nothing else.
3. **Production guard.** With `APP_ENV=production` in `.env`, `composer setup` stops before key generation and migrations.
4. **Demo data.**
   - `composer demo` fills an empty database. Every 9-box cell has someone, quotas are respected, and each demo employee has its initial history row.
   - A second run refuses. A run in production refuses.
   - `admin2` can approve an SK submitted by `admin`.
5. **Documents.**
   - No root-level Markdown file contains `file:///` or `c:/Users`, and the guard test passes.
   - The four reference documents differ only in the 21 link targets plus the planned `ENGINEERING.md` and `SECURITY.md` edits.
6. **Quality gate.** Pint and the full PHPUnit suite pass, and CI passes on the pull request.
7. **No real data.** No real personal data is added. `git check-ignore` confirms that a `.xlsx` and a `.sql.gz` file placed in a subfolder are ignored.

## Left to the builder

- The exact wording and layout of the documents and templates.
- The demo mix: counts per unit, the dates, which positions are used.
- Test details.
- The order of items in the checklists.
- How the guards are written inside the Composer scripts, as long as they stay portable to Windows.
