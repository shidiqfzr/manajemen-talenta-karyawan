# Contributing to PALM HRIS

PALM HRIS is the talent and formation management system of PT Perkebunan Nusantara IV Regional V. It handles personal data of employees (UU No. 27/2022, UU PDP), so contributing here means two things: keep to the project's reference documents, and never let real data leave the production server.

The reference documents come first. Read the ones that touch your change before you write code:

| Document | Read it when you change |
|---|---|
| [AGENTS.md](AGENTS.md) | anything: the behaviour rules every contribution follows |
| [DOMAIN.md](DOMAIN.md) | business rules, formulas, data model, mutation and structure flows |
| [DESIGN.md](DESIGN.md) | screens, components, copy |
| [ENGINEERING.md](ENGINEERING.md) | code structure, tests, CI, release |
| [SECURITY.md](SECURITY.md) | data handling, uploads, access, audit trail |

## 1. Get a working copy

You need PHP 8.4 (with `mbstring`, `intl`, `gd`, `zip`, `bcmath`, `xml`, `fileinfo`, `pdo_sqlite`, `sqlite3`), Composer 2, Node 22 and Git. The README lists them with Windows notes.

```bash
git clone https://github.com/shidiqfzr/palm-hris.git
cd palm-hris
composer setup     # dependencies, .env, key, SQLite, migrations, master data, assets. Safe to re-run.
composer demo      # about 60 synthetic employees and 9-box evaluations, for local use only
composer dev       # server, queue, Vite (see the README if a panel fails on Windows)
```

Local accounts (public, local only): `admin`, `admin2` (password `ptpn4reg5`) and `user` (password `password`). Two administrators let you test four-eyes approvals locally.

Imports run as queued jobs, so a queue worker (`php artisan queue:listen --tries=1`) must be running when you test them.

## 2. Branches and commits

Branch from `main`, one topic per branch: `feat/…`, `fix/…`, `refactor/…`, `chore/…`, `ci/…` or `docs/…`.

Commits use Conventional Commits in English: `type(scope): subject`, imperative, no trailing period. Real examples from this repository:

```
fix(security): create a single env-configured administrator in production and never seed demo accounts there
feat(structure): add the Struktur per Tanggal screen that shows the SK version in force and its holders on any date
ci: add the pull request quality gate (Pint, composer and npm audit, PHPUnit, JS tests, asset build) and Dependabot
```

Do not edit history that has been pushed, and do not add authorship trailers to commit messages.

## 3. How a change is made

Follow the five stages in [ENGINEERING.md §1](ENGINEERING.md#1-alur-perencanaan--siklus-pengembangan-sdlc-workflow): align with the domain and design, then the service and model layer, then the Livewire UI, then automated tests and Pint, then the security and audit check.

The standards that reviewers check, each governed by a section you can read:

- **Service layer.** Business rules live in services, not in Blade views or controllers; never hardcode RM Band or bidang calculations ([ENGINEERING.md §2.1](ENGINEERING.md), [DOMAIN.md](DOMAIN.md)).
- **Transactions.** Any write that touches more than one table runs inside `DB::transaction()` ([§2.2](ENGINEERING.md)).
- **One test class per component.** All scenarios for one class or Livewire component live in one test file ([§3.5](ENGINEERING.md), AGENTS.md rule 5). Write the failing test first.
- **Terminology.** Use "Karyawan", "Karyawan Pimpinan/Pelaksana" and "Nomor Induk Karyawan" in anything a user sees (DOMAIN.md §2.0, AGENTS.md rule 7). Identifiers in code do not change.
- **No internal document names in the interface.** Never put the names of the reference documents, or the acronym "SSOT", in labels, tooltips, alerts, placeholders or messages a user can see (AGENTS.md rule 6). They may appear in code comments.
- **Organisation structure changes only through the SK service.** Units, positions, quotas and bidang change per Surat Keputusan, with four-eyes approval and an audit entry; never edit `database/data/master_formasi.json` or re-run a seeder on a database that holds real data (DOMAIN.md §3.7, AGENTS.md rule 8).
- **Placement history.** Every mutation and appointment is recorded in `job_histories`; do not edit history rows.
- **Look and feel.** Follow [DESIGN.md](DESIGN.md): colour tokens, components, explicit Livewire loading states, debounce on search (300 ms).

Keep existing documentation, docblocks and structural comments ([AGENTS.md](AGENTS.md) rule 2).

## 4. Before you open a pull request

```bash
composer verify
```

This runs what the CI gate runs: `pint --test`, `composer audit`, the PHPUnit suite, `npm audit --audit-level=high` and the JavaScript tests. It needs network access for the two audits and takes several minutes. Format with `./vendor/bin/pint` if it reports style problems.

If you changed a screen, also run the browser suite once: `npm run e2e:install` (first time only), then `npm run e2e` ([ENGINEERING.md §3.6](ENGINEERING.md)).

## 5. Pull requests and review

- `main` is protected: changes arrive only by pull request, and the two CI checks must pass. Direct pushes and force pushes are not possible.
- Keep a pull request to one topic. Fill in the template; the checklist mirrors the reference documents.
- A maintainer reviews every pull request. Changes to `DOMAIN.md`, `SECURITY.md`, `AGENTS.md`, services, migrations, seed data and `.github/` need the code owner's review (see `.github/CODEOWNERS`).
- Large changes can ship a human-readable write-up under `docs/pull-requests/`.

## 6. Privacy rules (non-negotiable)

- **Use demo data only.** `composer demo` gives you synthetic employees. Never copy the production database, a backup, an export or an import spreadsheet onto a development machine or into this repository. The `.gitignore` blocks spreadsheets and database dumps everywhere, but do not rely on it.
- **Never paste real employee data** (NIK, names, family data, assessment scores, certificates) or screenshots that show it into an issue, a pull request, a commit message or a chat.
- **No secrets in the repository.** `.env` stays local. Do not commit credentials, keys or tokens.
- Treat assessment (9-box) scores and family data as confidential in every screen and export ([SECURITY.md](SECURITY.md)).

## 7. Reporting a vulnerability

Do not open a public issue. Follow [SECURITY.md §6](SECURITY.md#6-prosedur-pelaporan-kerentanan-reporting-a-vulnerability).

## 8. Deployment

Contributors do not deploy. Releases to production, server access, production data and backups are handled by the maintainers following [ENGINEERING.md §4](ENGINEERING.md). Never run `composer setup`, a full `db:seed` or `migrate:fresh` against a production database.
