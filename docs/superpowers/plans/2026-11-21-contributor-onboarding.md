# Contributor Onboarding Implementation Plan

> Steps use checkbox (`- [ ]`) syntax for tracking. Work on branch `chore/contributor-onboarding`; one commit per task.

**Goal:** A new machine or contributor goes from `git clone` to a running app and a green quality gate with `composer setup` and `composer verify`, using synthetic demo data only.

**Architecture:** A portable PHP script prepares `.env` and refuses production; Composer scripts chain it with migrations, the asset build and the CI checks. A guarded `DemoEmployeeSeeder` creates employees through Eloquent so `EmployeeObserver` derives RM level, retirement and MBT dates and writes the first `job_histories` row, and writes evaluations through `EvaluationService`. Documents, templates and a link-guard test make the repository usable by strangers.

**Tech Stack:** Laravel 13, PHP 8.4, PHPUnit 11, Pint, Composer 2 scripts, Node 22 / npm.

**Spec:** `docs/superpowers/specs/2026-11-21-contributor-onboarding-design.md`

## Global Constraints

- PHP `^8.4` (platform pinned `8.4.1`), Node `22`, PHPUnit `11` (do not upgrade). Local DB SQLite; production MySQL.
- Every script portable to Windows: `@php`, `@composer`, tools by name (`pint`), no shell-only syntax. Long Composer scripts start with `Composer\\Config::disableProcessTimeout` (default timeout is 300 s; the suite takes about 10 minutes).
- One test class per subject (AGENTS.md rule 5). Pint clean. `DB::transaction()` for multi-table writes.
- Reference documents stay in Indonesian; `CONTRIBUTING.md` and `.github/` templates are in English; user-visible app text stays Indonesian; terminology "Karyawan".
- No real personal data anywhere. Demo NIK: 8 digits, prefix `99`. Demo names: `Karyawan Contoh NN`.
- Conventional Commits in English (`type(scope): subject`). Commit messages, documents and templates contain no authorship trailers and no mention of the tools used to write them.
- Do not rename CI jobs (`Backend (Pint, audit, PHPUnit)`, `Frontend (audit, unit tests)`): they are required checks.
- Back up a file before any scripted edit; prefer the editor's exact replace over scripts.

## Review Focus

1. Re-running `composer setup` on a machine that already has `.env` and a key must keep `APP_KEY` unchanged: Task 2 test `test_existing_key_is_never_replaced`.
2. `.env` written as `APP_ENV="production"` or `APP_ENV = production` must still be refused: Task 2 data provider `productionSpellings`.
3. A unit whose positions are all pool positions (`is_overflow`/`is_diperban`) or have `kuota` 0 must receive no demo employee: Task 4 test `test_pool_positions_and_zero_quota_seats_are_never_filled`.
4. A master position with an empty `rm_level` must still give the demo employee a derived RM level: Task 4 test `test_every_demo_employee_gets_observer_derived_fields`.
5. Seeding evaluations with nobody logged in must not fail (the audit log user is null): Task 4 test runs without `actingAs` throughout.

---

### Task 1: Data-leak guards and the queue retry setting

**Files:**
- Modify: `.gitignore` (lines 30-32)
- Modify: `.env.example` (after the `HRIS_*` blocks)

- [ ] **Step 1:** In `.gitignore`, replace `/*.xlsx`, `/*.xls`, `/*.csv` with `*.xlsx`, `*.xls`, `*.csv`, `*.sql`, `*.sql.gz`, `*.dump`, under a comment `# Employee data exports and database dumps: never commit, anywhere`.
- [ ] **Step 2:** Append to `.env.example`:
  ```
  # Antrean basis data: pekerjaan impor boleh berjalan hingga 600 detik, jadi retry_after harus lebih lama.
  DB_QUEUE_RETRY_AFTER=660
  ```
- [ ] **Step 3: Verify.** Run `git check-ignore -v docs/x.xlsx tests/a/b.csv backups/db.sql.gz`. Expected: all three are reported as ignored by the new rules. Run `git ls-files '*.xlsx' '*.csv' '*.sql'`. Expected: empty.
- [ ] **Step 4: Commit.** `chore(repo): ignore spreadsheets and database dumps everywhere and record the queue retry time in .env.example`

### Task 2: Portable setup and verify commands

**Files:**
- Create: `scripts/prepare-env.php`
- Create: `tests/Feature/PrepareEnvScriptTest.php`
- Modify: `composer.json` (`scripts`), `package.json` (`engines`), `package-lock.json`
- Create: `.nvmrc`

**Interfaces:**
- Produces: `php scripts/prepare-env.php [<projectRoot>]`, where `<projectRoot>` defaults to `dirname(__DIR__)`. In order:
  1. Copy `.env.example` to `.env` if `.env` is missing.
  2. Exit `1` with `Refusing to run: .env has APP_ENV=production. composer setup is for development machines only.` on STDERR if `.env` matches `/^\s*APP_ENV\s*=\s*["']?production["']?\s*$/mi`.
  3. Write `APP_KEY=base64:<base64 of random_bytes(32)>` only if `APP_KEY` is empty or missing (`config/app.php` cipher is AES-256-CBC, so the key is 32 bytes).
  4. Create `database/database.sqlite` if `DB_CONNECTION` is `sqlite` or unset and `DB_DATABASE` is unset or commented, and the file is missing.
  5. Exit `0`.
- Produces: the Composer scripts `setup`, `verify` and `demo`. `demo` runs the seeder from Task 4.

- [ ] **Step 1: Write the failing tests** in `PrepareEnvScriptTest` (SUT: `scripts/prepare-env.php`). Each test copies `.env.example` (and nothing else) into a fresh temp directory with an empty `database/` folder, then runs the script through `Symfony\Component\Process\Process([PHP_BINARY, base_path('scripts/prepare-env.php'), $dir])`.
  - `test_fresh_folder_gets_env_key_and_sqlite_file`: exit 0; `.env` exists; `APP_KEY` matches `^base64:` and decodes to 32 bytes; `database/database.sqlite` exists.
  - `test_existing_key_is_never_replaced`: `.env` already has `APP_KEY=base64:AAAA...` (44 chars). After the run the value is identical.
  - `test_production_is_refused_before_anything_changes`, `#[DataProvider('productionSpellings')]` with `APP_ENV=production`, `APP_ENV="production"`, `APP_ENV = production`, `app_env=PRODUCTION`: exit 1; STDERR contains `Refusing to run`; `.env` byte-identical to before; no sqlite file.
  - `test_mysql_connection_creates_no_sqlite_file`: `.env` with `DB_CONNECTION=mysql`. Exit 0, no sqlite file.
  - `test_running_twice_changes_nothing`: run, hash `.env`, run again; same hash.
- [ ] **Step 2: Run** `php artisan test --filter=PrepareEnvScriptTest`. Expected: FAIL (script missing).
- [ ] **Step 3: Implement** `scripts/prepare-env.php`. Plain PHP with no Laravel bootstrap and no `vendor`. Use `str_replace`/`preg_replace` on the file text and keep every other line of `.env` untouched. Docblock: "Prepares a development .env; refuses production (composer setup)."
- [ ] **Step 4: Run** the filter again. Expected: PASS (8 cases).
- [ ] **Step 5:** Add the scripts to `composer.json`, keeping `dev` unchanged:
  ```json
  "setup": ["Composer\\Config::disableProcessTimeout", "@composer install", "@php scripts/prepare-env.php", "@php artisan migrate --seed --ansi", "npm ci", "npm run build"],
  "verify": ["Composer\\Config::disableProcessTimeout", "pint --test", "@composer audit", "@php artisan test", "npm audit --audit-level=high", "npm run test:js"],
  "demo": ["@php artisan db:seed --class=DemoEmployeeSeeder --ansi"]
  ```
- [ ] **Step 6:** Create `.nvmrc` containing `22`. Add `"engines": { "node": ">=22" }` to `package.json`. Run `npm install --package-lock-only` so the lockfile records it.
- [ ] **Step 7: Verify.** Run `composer validate --no-check-publish`. Expected: valid; the lock file is not outdated. Run `composer setup` in this checkout. Expected: completes; the existing `APP_KEY` is unchanged (compare before and after).
- [ ] **Step 8: Commit.** `feat(dev): add composer setup, verify and demo with a portable env preparer that refuses production`

### Task 3: Second demo administrator

**Files:**
- Modify: `database/seeders/UserSeeder.php` (`seedDemoAccounts`)
- Modify: `tests/Feature/UserSeederTest.php`
- Modify: `SECURITY.md` (§3 line 54)

- [ ] **Step 1: Update the tests.** Rename `test_non_production_seeds_the_demo_admin_and_user_with_the_right_roles` to `test_non_production_seeds_two_demo_admins_and_a_user_with_the_right_roles`, and add assertions that `admin2` has role `admin`, email `admin2@example.com` and password `ptpn4reg5`. In `test_non_production_seeding_twice_does_not_duplicate_accounts`, expect `3`. Production tests stay as they are (still exactly one admin, no demo accounts).
- [ ] **Step 2: Run** `php artisan test --filter=UserSeederTest`. Expected: the two changed tests FAIL.
- [ ] **Step 3:** In `seedDemoAccounts()`, add `$this->createUser('Admin Pemeriksa', 'admin2', 'admin2@example.com', 'ptpn4reg5', 'admin');` after the admin line, and extend the docblock with "a second admin so four-eyes approval can be exercised locally".
- [ ] **Step 4: Run** the filter. Expected: PASS (11 tests).
- [ ] **Step 5:** In `SECURITY.md` line 54, change `(\`admin\`, \`user\`)` to `(\`admin\`, \`admin2\`, \`user\`)` and add: "`admin2` ada agar persetujuan empat mata (SK Struktur, impor, mutasi berantai) dapat diuji di lingkungan pengembangan."
- [ ] **Step 6: Verify the browser flows that use seeded accounts.** Run `npx playwright test tests/e2e/login.spec.js tests/e2e/surat-keputusan.spec.js`; the `setup` project with `auth.setup.js` runs first automatically. Expected: PASS.
- [ ] **Step 7: Commit.** `feat(seed): add a second demo administrator so four-eyes approvals can be tested locally`

### Task 4: Synthetic demo employees and evaluations

**Files:**
- Create: `database/seeders/DemoEmployeeSeeder.php`
- Create: `tests/Feature/DemoEmployeeSeederTest.php`
- Modify: `database/seeders/DatabaseSeeder.php` (docblock: one added sentence)
- Modify: `ENGINEERING.md` (new §3.8 "Data Demo Lokal"), `SECURITY.md` (§3, after line 54)

**Interfaces:**
- Consumes: `Unit::positions()` (pivot `kuota`, `bidang`); `Position` fields `level`, `rm_level`, `is_overflow`, `is_diperban`, `is_active`; `EmployeeObserver` (saving, created); `EvaluationService::processStore(array): Evaluation`; `Evaluation::boxValue(int): string`; `KepegawaianPolicy::MBT_PERINGATAN_BULAN`.
- Produces: `DemoEmployeeSeeder extends Seeder` with `public const DEMO_COUNT = 60`, `NIK_PREFIX = '99'`, `NAME_PREFIX = 'Karyawan Contoh'`, `ACTING_COUNT = 4`, `EVALUATED_COUNT = 40`, and `run(): void`. The run throws `RuntimeException` (writing nothing) when the environment is production, when `employees` is not empty, or when no eligible seat exists (message tells the user to run `composer setup` first).

**Decisions:**
- **Eligible seat:** an active unit plus an active position with `is_overflow = false`, `is_diperban = false` and `kuota >= 1`.
- **Seat selection:** take seats round-robin across units, about 45 Karpim and 15 Karpel. Each chosen seat gets exactly one holder, which cannot exceed any quota of at least 1. `ACTING_COUNT` holders get `status_penugasan = Employee::STATUS_PJS` (a status in `Employee::ASSIGNMENT_STATUSES`, which the admin form and the import accept; Plt is not), the rest `'Definitif'`.
- **NIK and names:** NIK `99` plus a zero-padded sequence (`99000001`...); name `Karyawan Contoh 01`...
- **Golongan:** a small demo table keyed by the position's master band, in the `[Gol]/[Berkala]` format. RM-1 uses IV, RM-2 uses IIIC/IIID, RM-3 or empty uses IIIA/IIIB, Karpel bands use II. This is data, not a rule; the test proves the observer still derives the master band.
- **Left empty:** `rm_level`, `tanggal_pensiun`, `tanggal_mbt`, `job_grade`, `person_grade`.
- **Birth dates, relative to `today()`:**
  - 5 with MBT inside the warning window (pension 7 to 12 months ahead).
  - 3 past MBT but before pension (pension 1 to 5 months ahead).
  - The rest aged 25 to 50.
- **Other dates:** `tmt_bekerja`, `tanggal_dalam_jabatan` and `tmt_unit_kerja` lie between age 22 and today.
- **Other fields:** `jenis_kelamin` L/P, `agama`, `pendidikan_terakhir` (D3/S1/S2), `tempat_lahir` from Kalimantan cities, `status_karyawan = 'Tetap (PKWTT)'`.
- **Evaluations:** the first `EVALUATED_COUNT` employees get `kategori_9box = Evaluation::boxValue($box)`, with `$box` cycling 1 to 9. `skor_cli_9box` falls in the potential third and `skor_smkbk_9box` in the performance third of that box (0-33 / 34-66 / 67-100; my call, display only, because `kategori_9box` is the stored authority). The four `nilai_*` values are 5 to 9, and `tanggal_pelaksanaan_asesmen` is within the last 12 months. All are written via `EvaluationService::processStore()`.
- **Determinism:** a fixed seed (`mt_srand(20261121)` or a seeded Faker) makes every run produce the same data.
- **Transaction:** one `DB::transaction()` around everything. Create employees with `Employee::create()` (events on).

- [ ] **Step 1: Write the failing tests** in `DemoEmployeeSeederTest` (`RefreshDatabase`; `setUp` runs `PositionUnitSeeder` and `Carbon::setTestNow('2026-11-21')`):
  - `test_seeds_sixty_recognisable_synthetic_employees`: count `DEMO_COUNT`; every `nik` matches `^99\d{6}$`; every `nama` starts with `Karyawan Contoh `; every `golongan` matches the import rule `/^(I|II|III|IV)[A-D](\/[0-9]{2})?$/i` (`EmployeeImportRowValidator`).
  - `test_pool_positions_and_zero_quota_seats_are_never_filled`: per (unit, jabatan), holders ≤ pivot `kuota`, counting every holder whatever the `status_penugasan`. No holder sits on an `is_overflow` or `is_diperban` position. Set one seat's `kuota` to 0 before running and assert it stays empty.
  - `test_every_demo_employee_gets_observer_derived_fields`: `rm_level`, `tanggal_pensiun` and `tanggal_mbt` are filled. `tanggal_pensiun` equals `KepegawaianPolicy::tanggalPensiun(tanggal_lahir)`. When the master position has an `rm_level`, the employee's `rm_level` equals it. Each employee has exactly one active `job_histories` row with `sumber = JobHistory::SUMBER_DATA_AWAL` and no SK number.
  - `test_retirement_screens_have_data`: `Employee::menjelangMbt()->count() >= 5` and `Employee::mbt()->count() >= 3`, with every employee's `tanggal_pensiun` after today.
  - `test_both_strata_and_acting_assignments_exist`: some Karpim, some Karpel, and exactly `ACTING_COUNT` with `Employee::STATUS_PJS`; plus `test_every_demo_status_is_one_the_application_accepts`.
  - `test_every_nine_box_cell_has_someone`: `Evaluation::count() === EVALUATED_COUNT`, and each of the 9 `boxValue`s appears at least 4 times.
  - `test_refuses_in_production_and_writes_nothing`, `test_refuses_when_employees_already_exist`, `test_refuses_without_master_data` (no `PositionUnitSeeder`): each expects `RuntimeException` and unchanged counts of employees, evaluations and job histories.
  - `test_same_data_on_every_run`: seed, capture `nik` → `[jabatan, unit_kerja, tanggal_lahir]`, refresh the database, seed again; identical.
  - `test_failure_mid_run_leaves_nothing_behind`: bind a fake `EvaluationService` in the container whose `processStore()` throws on its 10th call. The run throws, and employees, job histories and evaluations are all 0. This proves the single transaction.
- [ ] **Step 2: Run** `php artisan test --filter=DemoEmployeeSeederTest`. Expected: FAIL (class missing).
- [ ] **Step 3: Implement** `DemoEmployeeSeeder` per the decisions above, with a class docblock: "Synthetic demo employees for local development only (SECURITY.md §3); never production, never on a database that holds employees."
- [ ] **Step 4: Run** the filter. Expected: PASS (11 tests). Resolve `EvaluationService` from the container (`app(EvaluationService::class)`), not with `new`, so the test fake applies. Then run `php artisan test --filter='EmployeeObserver|Evaluation|JobVacancy'`. Expected: PASS.
- [ ] **Step 5: Docs.**
  - In `DatabaseSeeder`, append to the docblock: "Untuk data contoh di mesin pengembangan jalankan `composer demo` (DemoEmployeeSeeder; menolak produksi)."
  - In `ENGINEERING.md`, add §3.8 "Data Demo Lokal": what it creates, the three guards, created through the observer and services, `composer demo`.
  - In `SECURITY.md` §3, add a bullet: demo employees are synthetic (NIK `99…`, "Karyawan Contoh"); the seeder refuses production and a database that holds employees; production data, backups and exports never go onto development machines or into the repository.
- [ ] **Step 6: Verify by hand.** Run `php artisan migrate:fresh --seed && composer demo` on the local SQLite DB, then run `composer demo` again. Expected: the first run seeds; the second prints the refusal and exits non-zero.
- [ ] **Step 7: Commit.** `feat(seed): add a guarded synthetic demo employee seeder with observer-derived fields and 9-box evaluations`

### Task 5: Documents that work for strangers

**Files:**
- Modify: `AGENTS.md`, `README.md`, `DOMAIN.md`, `ENGINEERING.md` (link targets only)
- Modify: `README.md` (setup section and stale facts)
- Create: `tests/Unit/DocumentationLinksTest.php`

- [ ] **Step 1: Write the failing test** `DocumentationLinksTest` (SUT: links in the root Markdown files `README.md`, `AGENTS.md`, `CONTRIBUTING.md`, `DOMAIN.md`, `DESIGN.md`, `ENGINEERING.md`, `SECURITY.md`; a missing file is skipped):
  - `test_no_document_links_to_a_local_machine_path`: no file contains `file:///` or matches `/[a-z]:\/Users\//i`.
  - `test_relative_links_point_to_existing_files`: for every `[text](target)` whose target is not `http(s):`, `mailto:` or `#…`, the path before `#` exists relative to `base_path()`.
- [ ] **Step 2: Run** `php artisan test --filter=DocumentationLinksTest`. Expected: FAIL (21 local links).
- [ ] **Step 3: Back up** the four files to the scratch folder. Every one of the 21 links starts with the same absolute prefix: `file:///` followed by the first laptop's project folder path, ending in `manajemen-talenta-karyawan/`. Copy that exact string from `AGENTS.md`, then use the editor's replace-all to replace it with an empty string in each file.
- [ ] **Step 4: Verify the diff.** Run `git diff --numstat -- AGENTS.md README.md DOMAIN.md ENGINEERING.md`. Expected: `4 4`, `5 5`, `7 7`, `5 5`. Run `git diff -- <files> | grep '^[-+][^-+]' | grep -v 'file:///' | grep -v '](DOMAIN.md\|](DESIGN.md\|](ENGINEERING.md\|](SECURITY.md\|](AGENTS.md'`. Expected: no other changed content.
- [ ] **Step 5:** Rewrite the README "Menjalankan Aplikasi Secara Lokal" section, in Indonesian:
  - **Prerequisites:** PHP 8.4 with `mbstring`, `intl`, `gd`, `zip`, `bcmath`, `xml`, `fileinfo`, `pdo_sqlite`, `sqlite3`; Composer 2; Node 22; Git; a Windows note on enabling `extension=` lines in `php.ini` (Laragon includes PHP 8.4).
  - **Quick start:** `git clone`, `composer setup`, `composer dev`; on machines where `composer dev` fails, the three commands `php artisan serve`, `php artisan queue:listen --tries=1` and `npm run dev`, with a note that imports need the queue worker.
  - **Demo data and accounts:** `composer demo`; accounts `admin` / `admin2` / `user`, password `ptpn4reg5`, local only.
  - **Database:** local SQLite versus production MySQL.
  - **Checks and guide:** `composer verify`; a link to `CONTRIBUTING.md`.
  - **Facts to fix:** line 14 becomes "47 unit kerja"; line 16 becomes "MBT 6 bulan sebelum tanggal pensiun". Keep the production note on line 64.
- [ ] **Step 6: Run** the filter. Expected: PASS once `CONTRIBUTING.md` exists (Task 6) or is skipped while it is missing.
- [ ] **Step 7: Commit.** `docs(readme): use relative links in the reference documents, add a link guard, and rewrite local setup around composer setup`

### Task 6: Contribution guide, templates and the checklist fixes

**Files:**
- Create: `CONTRIBUTING.md`, `.github/PULL_REQUEST_TEMPLATE.md`, `.github/ISSUE_TEMPLATE/bug_report.yml`, `.github/ISSUE_TEMPLATE/feature_request.yml`, `.github/ISSUE_TEMPLATE/config.yml`, `.github/CODEOWNERS`
- Modify: `ENGINEERING.md` (§3.3, §3.4, §4 lines 240 and 244)

- [ ] **Step 1: Write `CONTRIBUTING.md`** (English) with these sections:
  1. Getting started (`composer setup`, `composer dev`, `composer demo`).
  2. Branches (`feat/ fix/ refactor/ chore/ ci/ docs/`).
  3. Commits (Conventional Commits; three real examples from `git log`).
  4. Workflow (the five stages, linking to ENGINEERING.md §1).
  5. Standards in one page (service layer, `DB::transaction()`, one test class per component, terminology, no internal document names in user-visible text, SK-only structure changes, no hardcoded RM or bidang), each linking to the governing section.
  6. Before you open a pull request (`composer verify`; the Playwright suite for UI changes: `npm run e2e:install`, `npm run e2e`).
  7. Review.
  8. Privacy rules.
  9. Reporting a vulnerability (SECURITY.md §6).
  10. Deployment (maintainers only).
- [ ] **Step 2:** Create `PULL_REQUEST_TEMPLATE.md`: what and why, linked issue, then a checkbox list:
  - reference documents checked
  - service layer and transactions
  - one test class per component
  - `composer verify` green
  - browser suite for UI changes
  - terminology and no internal document names in UI text
  - SK-only structure changes
  - no real employee data or screenshots
  - docs updated
- [ ] **Step 3:** Create the issue forms. `bug_report.yml`: steps, expected, actual, environment, and a required checkbox "I confirm this report contains no real employee data". `feature_request.yml`: problem, proposal, which reference document it touches, and the same checkbox. `config.yml`: `blank_issues_enabled: false`, plus a contact link "Security vulnerability" pointing to `SECURITY.md#6-prosedur-pelaporan-kerentanan-reporting-a-vulnerability`.
- [ ] **Step 4:** Create `CODEOWNERS`: `* @shidiqfzr`, plus explicit lines for `/DOMAIN.md /SECURITY.md /AGENTS.md /app/Services/ /database/migrations/ /database/data/ /.github/`, each `@shidiqfzr`. Add a top comment: add the second developer to the `*` line when they join.
- [ ] **Step 5: Edit `ENGINEERING.md`.**
  - §3.3: add `composer verify` (seluruh gerbang CI) and `composer setup`.
  - §3.4: note that `composer verify` runs `pint --test`.
  - Line 240: replace `` `APP_TIMEZONE=Asia/Jakarta`, `APP_LOCALE=id`, `` with `` `APP_TIMEZONE` tetap `UTC` (desain penjadwal, DOMAIN.md §3.7), ``.
  - Line 244: replace `` `php artisan route:cache`, `` with `` `php artisan route:cache`, `php artisan view:cache`, `php artisan event:cache` `` (route cache stays: tested on all closure routes, identical behaviour).
- [ ] **Step 6: Run** `php artisan test --filter=DocumentationLinksTest`. Expected: PASS (it now also checks `CONTRIBUTING.md`). Run `pint --test`. Expected: pass.
- [ ] **Step 7: Commit.** `docs(contributing): add the contribution guide, PR and issue templates, CODEOWNERS, and fix the release checklist timezone and cache lines`

### Task 8: Remove the tool-workflow header line from the existing plans (owner addendum, done before Task 7)

**Files:**
- Modify: the 16 files in `docs/superpowers/plans/` that begin with a "For agentic workers" line.

- [ ] **Step 1:** Copy the plan folder to a backup. Compute every new file content in memory first (abort without writing anything if a file has other than exactly one matching line or unexpected wording), then write the bytes.
- [ ] **Step 2:** Replace that line with `> Steps use checkbox (`- [ ]`) syntax for tracking.` (or, for the one plan with a TDD note, `> Steps use TDD (RED then GREEN); commit per task.`).
- [ ] **Step 3: Verify.** `git diff --numstat -- docs/superpowers/plans` shows 16 files, each `1 1`; a byte comparison against the backup shows no other line changed and no line ending was lost; the same `git grep -nIwiE` for the tool-workflow wording, run with `-- ':!docs/superpowers/plans/2026-11-21-contributor-onboarding.md'` (this plan quotes the wording it removes), finds nothing in tracked documents.
- [ ] **Step 4: Commit.** `docs(plans): drop the tool-workflow header line from the existing implementation plans`

### Task 7: Whole-branch verification

- [ ] **Step 1: Run** `pint --test`, then `php artisan test` in the background (about 10 minutes). Expected: all green; the count is the previous 1523 plus the new tests.
- [ ] **Step 2: Clean-clone check.** In an empty temp folder, run `git clone <this checkout> c && cd c && composer setup`. Expected: completes.
  - Note `APP_KEY`, run `composer setup` again. Expected: same key.
  - Run `composer demo`. Expected: seeds; a second run refuses.
  - Set `APP_ENV=production` in that copy's `.env` and run `composer setup`. Expected: refuses, exit non-zero.
  - Start `php artisan serve` and log in as `admin`. Expected: the employee list shows 60 "Karyawan Contoh" rows.
- [ ] **Step 3: Scan** `git diff main...HEAD` for real-looking NIKs outside the demo prefix (`grep -E '\b[0-8][0-9]{7}\b'` in added lines, reviewing any hit), for any personal path, and for authorship trailers or tool names. Expected: none.
- [ ] **Step 4: Report to the owner and wait for approval** before `git push -u origin chore/contributor-onboarding` and opening the pull request (English body, no tool attribution).
