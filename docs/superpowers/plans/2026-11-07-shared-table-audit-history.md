# Shared Data Table — Audit History Implementation Plan (Spec step 3a)

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Move the Riwayat Perubahan Master screen (`App\Livewire\Admin\Master\AuditHistory`) onto the shared data table layer, adding the two shared pieces it needs (date filters and an active-filter chip row) and the trait view helpers the final review asked for before step 3.

**Architecture:** Date filters are two ordinary single-property `Filter`s (`dateFrom`, `dateTo`), so `QueryPipeline` and `WithDataTable` work unchanged. The toolbar renders date inputs and a "Filter Aktif" chip row for every active filter. AuditHistory keeps its admin-only guard, its URL keys and its row markup, and drops its hand-written search/sort/per-page code.

**Tech Stack:** Laravel 13, Livewire 4.4.6, PHPUnit 11, Tailwind tokens from `DESIGN.md`, SQLite (tests) / MySQL (prod).

**Spec:** `docs/superpowers/specs/2026-11-06-shared-data-table-design.md` (§4–§6, §7 step 3, §9 deltas). Previous plan: `docs/superpowers/plans/2026-11-06-shared-data-table-foundation.md`.

**Branch:** create `feat/shared-table-audit-history` from `feat/shared-data-table` (the foundation branch is not merged yet).

**Out of scope:** export on this screen (it has none today), MutasiHistory (step 3b, own plan), numeric export values, neutraliser extraction, `whereLikeAny` extraction, DESIGN.md/ENGINEERING.md (step 7).

## Global Constraints

- PHP: composer.json declares `^8.3` (dev machine runs 8.5) — no syntax or functions newer than PHP 8.3.
- Code, docblocks, commit messages English; user-facing text Indonesian business wording; rendered output never contains `AGENTS.md`, `DOMAIN.md`, `DESIGN.md`, `ENGINEERING.md`, `SECURITY.md` or `SSOT`.
- Date filter values are `Y-m-d` and must be real calendar dates; `dateFrom` bounds `>= {date} 00:00:00`, `dateTo` bounds `<= {date} 23:59:59` (both days inclusive).
- URL keys of the audit screen stay exactly: `search`, `entity_type`, `user_id`, `from`, `to`, `sort`, `direction`, `per_page`, `page`.
- Exact copy:
  - Chip row heading: `Filter Aktif:`
  - Search chip: `Pencarian: {term}`; select chip: `{filter label}: {option label}`; boolean chip: `{filter label}`; date chip: `{filter label}: {Y-m-d}`
  - Chip remove button `aria-label`: `Hapus filter {filter label}` (search chip: `Hapus filter Pencarian`)
  - Audit filter labels: `Jenis Data` (all-label `Semua Jenis Data`, icon `fas fa-tag`), `Admin` (all-label `Semua Admin`, icon `fas fa-user`), `Dari Tanggal`, `Sampai Tanggal`
  - Audit empty states: no data `Belum ada perubahan master yang tercatat.`; filtered: the shared `Tidak ada data yang sesuai dengan pencarian atau filter.` + `Reset filter`
  - Live region: `{n} catatan ditemukan` (kept, `sr-only`, `role="status"`)
- Search: `wire:model.live.debounce.300ms="search"`, max 100 characters (shared toolbar already does this).
- Run `./vendor/bin/pint` before every commit; one test class per system under test (AuditHistory tests stay in `tests/Feature/MasterAuditLogTest.php`, where they live today).
- Commit trailer: `Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>`.

## Review Focus

1. **Bookmarked audit URLs** (`?entity_type=seat&user_id=3&from=2020-01-01&to=2020-01-31&sort=entity_label&direction=asc&per_page=50&page=2`) must still filter, sort and page the same → Task 4 `test_history_keeps_its_url_keys`.
2. **Impossible or foreign dates** (`2026-02-30`, `31/01/2026`, `bukan-tanggal`) from the URL or the date input must be ignored, never a 500 → Task 2 `test_date_filters_allow_only_real_calendar_dates`, Task 4 `test_history_ignores_tampered_url_state`.
3. **"Sampai" date includes the whole day** — a change at 23:59 on the end date is listed, one at 00:00 the next day is not → Task 2 `test_date_filters_bound_both_days_inclusively`, Task 4 `test_history_date_range_includes_the_whole_end_day`.
4. **"Dari" after "Sampai"** shows the filtered empty state with Reset, not an error → Task 4 `test_history_from_after_to_shows_the_filtered_empty_state`.
5. **Search for `%` or `_`** in unit/jabatan labels (e.g. `Seat_1`) matches literally → Task 4 `test_history_search_treats_wildcards_literally`.

---

## File Map

| File | Responsibility |
|---|---|
| `app/Livewire/Concerns/WithDataTable.php` | + view helpers `tableLoadingTargets`, `tablePageKeys`, `tableColumnCount` |
| `app/Support/DataTable/Filter.php` | + `dateFrom`, `dateTo`, date validation, day-bounded default apply |
| `resources/views/components/admin/table/toolbar.blade.php` | + date inputs, + "Filter Aktif" chip row |
| `app/Livewire/Admin/Master/AuditHistory.php` | migrated onto `WithDataTable` |
| `resources/views/livewire/admin/master/audit-history.blade.php` | rebuilt from shared components, row cells unchanged |
| `resources/views/livewire/tables/{training,evaluation,job-history}-table.blade.php`, `tests/Fixtures/DataTable/FixtureTable.php` | use the new view helpers |
| `tests/Fixtures/DataTable/FixtureRow.php` | + nullable `tanggal` datetime column |
| `tests/Fixtures/DataTable/FixtureTableDateFilter.php` | fixture variant with date filters |
| `docs/superpowers/specs/2026-11-06-shared-data-table-design.md` | §9 deltas 8–9 |

---

### Task 1: Trait view helpers

**Files:**
- Modify: `app/Livewire/Concerns/WithDataTable.php`, the three views in `resources/views/livewire/tables/`, `tests/Fixtures/DataTable/FixtureTable.php` (render)
- Test: `tests/Feature/DataTable/WithDataTableTest.php`

**Interfaces — Produces (all `protected`, callable from the module view):**
- `tableLoadingTargets(string ...$actions): string` — comma-joined `search, sortBy, perPage, gotoPage, nextPage, previousPage, resetFilters`, then `$actions`, then `filterProperties()`; empty parts dropped.
- `tablePageKeys(): array` — current page keys as strings via `getAttribute($this->rowKey())`.
- `tableColumnCount(): int` — visible (non-export-only) columns, +1 when `bulkActions()` is non-empty.

- [ ] **Step 1: Write failing tests** — `test_table_loading_targets_include_actions_and_filters` (fixture: `invade($component)->tableLoadingTargets('hapus')` === `'search, sortBy, perPage, gotoPage, nextPage, previousPage, resetFilters, hapus, bidang'`), `test_table_page_keys_use_the_row_key`, `test_table_column_count_adds_the_selection_column`.
- [ ] **Step 2: Run** `php artisan test tests/Feature/DataTable/WithDataTableTest.php` — Expected: new tests FAIL (method not found).
- [ ] **Step 3: Implement the helpers; replace the `$pageKeys` / `$loadingTargets` / `$columnCount` lines in the three views and the fixture render with the helpers** (`detachTraining` / `deleteRow` passed as `$actions` in the views). Rendered HTML must not change.
- [ ] **Step 4: Run** `php artisan test tests/Feature/DataTable tests/Feature/TrainingTableTest.php tests/Feature/EvaluationTableTest.php tests/Feature/JobHistoryTableTest.php tests/Feature/EmployeeCrudTest.php` — Expected: PASS (the existing `test_row_action_shows_loading_state` tests prove the output is unchanged).
- [ ] **Step 5: Pint + commit** — `refactor(table): move repeated view setup into trait helpers`

---

### Task 2: Date filters

**Files:**
- Modify: `app/Support/DataTable/Filter.php`, `tests/Fixtures/DataTable/FixtureRow.php` (`tanggal` nullable datetime), `docs/superpowers/specs/2026-11-06-shared-data-table-design.md` (§9)
- Test: `tests/Unit/DataTable/FilterTest.php`, `tests/Unit/DataTable/QueryPipelineTest.php`

**Interfaces — Produces:**
- `Filter::dateFrom(string $property, string $label, string $column): self` (type `date_from`) and `Filter::dateTo(string $property, string $label, string $column): self` (type `date_to`). A new private constructor field `?string $column` (null for existing types).
- `allows()` for date types: `''`/null allowed; otherwise a string matching `/^\d{4}-\d{2}-\d{2}$/` that passes `checkdate()`.
- `applyTo()` default for date types (no custom `apply`): `date_from` → `where(column, '>=', "{value} 00:00:00")`, `date_to` → `where(column, '<=', "{value} 23:59:59")`.
- `select`/`boolean` behaviour unchanged.

- [ ] **Step 1: Write failing tests** — FilterTest: `test_date_filters_allow_only_real_calendar_dates` (`'2026-11-07'` true; `'2026-02-30'`, `'31/01/2026'`, `'bukan-tanggal'`, `['2026-11-07']` false; `''` true), `test_date_filters_bound_both_days_inclusively` (SQL contains `>= ?` / `<= ?`, bindings `'2026-11-01 00:00:00'` / `'2026-11-30 23:59:59'`, column qualified as given), `test_date_filters_keep_custom_apply`. QueryPipelineTest: `test_date_filters_include_the_whole_end_day` (rows `tanggal` 2026-01-31 23:59:00 in, 2026-02-01 00:00:00 out for `to = 2026-01-31`).
- [ ] **Step 2: Run** `php artisan test tests/Unit/DataTable` — Expected: FAIL.
- [ ] **Step 3: Implement; add `tanggal` (nullable datetime) to `FixtureRow::createTable()`; append to spec §9:** `8. Date ranges are two single-property filters, Filter::dateFrom and Filter::dateTo, instead of one dateRange(from, to) filter, so the pipeline and trait handle them like any other filter.`
- [ ] **Step 4: Run** `php artisan test tests/Unit/DataTable tests/Feature/DataTable` — Expected: PASS.
- [ ] **Step 5: Pint + commit** — `feat(table): add day-bounded date filters`

---

### Task 3: Toolbar date inputs and active-filter chips

**Files:**
- Modify: `resources/views/components/admin/table/toolbar.blade.php`, `docs/superpowers/specs/2026-11-06-shared-data-table-design.md` (§9)
- Create: `tests/Fixtures/DataTable/FixtureTableDateFilter.php` (extends `FixtureTable`; adds `#[Url(except: '')] public string $dari = ''`, `$sampai`; filters = parent + `Filter::dateFrom('dari', 'Dari Tanggal', 'tanggal')`, `Filter::dateTo('sampai', 'Sampai Tanggal', 'tanggal')`)
- Test: `tests/Feature/DataTable/TableComponentsTest.php`

**Interfaces:**
- Consumes: Task 2 filter types; existing toolbar props.
- Produces: new toolbar prop `search` (string, default `''`) — module views pass `:search="$search"`. Date inputs: `<input type="date" id="{tableId}-filter-{property}" wire:model.live="{property}">` with an `sr-only` `<label>` of the filter label; a `date_from` directly followed by a `date_to` renders as one group with an `aria-hidden` `–` between them. Chip row: shown when `has-active-filters` is true, under the controls, heading `Filter Aktif:`; one chip per active value in this order — search, select, boolean, date; each chip is a `<button type="button" wire:click="$set('{property}', '')" aria-label="Hapus filter {label}">` with an `aria-hidden` `fas fa-times` icon; every value printed with `{{ }}`. Select chips show the option label, never the raw key. Chip styles reuse the tokens of the current audit-history chips.

- [ ] **Step 1: Write failing tests** — `test_toolbar_renders_labelled_date_inputs` (ids, `wire:model.live="dari"`, `sr-only` labels, the `–` separator), `test_toolbar_lists_active_filters_as_removable_chips` (set `search` `Budi`, `bidang` `KEU`, `dari` `2026-01-01` → `Filter Aktif:`, `Pencarian: Budi`, `Bidang: KEU`, `Dari Tanggal: 2026-01-01`, each with its `$set(..., '')` and `aria-label`), `test_chip_row_is_hidden_without_active_filters`, `test_chip_values_are_escaped` (search `<script>` appears escaped).
- [ ] **Step 2: Run** `php artisan test tests/Feature/DataTable/TableComponentsTest.php` — Expected: FAIL.
- [ ] **Step 3: Implement; pass `:search="$search"` from the fixture render and the three views; append to spec §9:** `9. The toolbar shows a "Filter Aktif" chip row listing every active search and filter value, each removable on its own (carried over from the audit history screen).`
- [ ] **Step 4: Run** `php artisan test tests/Feature/DataTable tests/Unit/ViewDesignGuardTest.php tests/Feature/TrainingTableTest.php tests/Feature/EvaluationTableTest.php tests/Feature/JobHistoryTableTest.php` — Expected: PASS.
- [ ] **Step 5: Pint + commit** — `feat(table): add date inputs and active-filter chips to the toolbar`

---

### Task 4: Migrate AuditHistory

**Files:**
- Modify: `app/Livewire/Admin/Master/AuditHistory.php`, `resources/views/livewire/admin/master/audit-history.blade.php`
- Test: `tests/Feature/MasterAuditLogTest.php` (existing class)

**Interfaces:**
- Consumes: Tasks 1–3.
- `AuditHistory` uses `WithDataTable`; keeps `boot()` (admin-only abort) and `#[Computed] admins()`; keeps filter properties with their attributes: `#[Url(as: 'entity_type', except: '')] $entityType`, `#[Url(as: 'user_id', except: '')] $userId`, `#[Url(except: '')] $from`, `#[Url(except: '')] $to`. Removes its own `search`, `sortField`, `sortDirection`, `perPage`, `PER_PAGE_OPTIONS`, `updatedPerPage`, `updated`, `sortBy`, `resetFilters`, `hasFilters`, `validDate` and the query in `render()` (the trait's versions take over; URL keys stay identical because the trait uses `search`/`sort`/`direction`/`per_page`).
- `baseQuery()` = `MasterAuditLog::query()->excludingViewAccess()`; `tableLabel()` = `Riwayat Perubahan Master`; `defaultSort()` = `['created_at', 'desc']`; default per-page options/default (10·25·50·100, 25); `syncsUrl()` true; no bulk actions; `exportName()` null.
- `columns()`: `created_at` `Waktu` (sortable), `user_name` `Admin` (sortable, searchable), `action` `Aksi` (sortable), `entity_label` `Data` (sortable, searchable), `perubahan` `Perubahan`, `reason` `Alasan` (searchable).
- `filters()`: `Filter::select('entityType', 'Jenis Data', MasterAuditLog::ENTITY_LABELS)` (apply `where('entity_type', …)`), `Filter::select('userId', 'Admin', [id => name of $this->admins])` (apply `where('user_id', (int) …)`), `Filter::dateFrom('from', 'Dari Tanggal', 'created_at')`, `Filter::dateTo('to', 'Sampai Tanggal', 'created_at')` — icons/all-labels per Global Constraints.
- View: `card` + `toolbar` (`search-placeholder="Cari unit, jabatan, admin, atau alasan..."`, shortcut `/`) + the kept `sr-only` live region + `<table>` with `head` + row cells moved unchanged + `skeleton` + `empty-state` + `x-admin.table.pagination` (noun `catatan`), wired exactly like the reference `FixtureTable::render()` using the Task 1 helpers. The old top loading bar is replaced by the shared skeleton.

- [ ] **Step 1: Write failing tests** (existing tests in the class must keep passing unchanged):
  - `test_history_keeps_its_url_keys` — the Review Focus 1 URL filters, sorts by `entity_label asc`, shows 50 per page and page 2.
  - `test_history_ignores_tampered_url_state` — `?sort=password&direction=x&per_page=999&entity_type=zzz&user_id=abc&from=2026-02-30` → 200, default order, 25 rows, no chip shown.
  - `test_history_date_range_includes_the_whole_end_day`.
  - `test_history_from_after_to_shows_the_filtered_empty_state` — `from=2020-02-01&to=2020-01-01` → `Tidak ada data yang sesuai dengan pencarian atau filter.` and `Reset filter`.
  - `test_history_search_treats_wildcards_literally` — labels `Seat_1`, `SeatX1`; search `Seat_1` shows only `Seat_1`.
  - `test_history_shows_active_filter_chips` — `entity_type=seat` → `Jenis Data: Formasi Jabatan`.
  - `test_history_announces_the_result_count` — `catatan ditemukan` with `role="status"`.
- [ ] **Step 2: Run** `php artisan test tests/Feature/MasterAuditLogTest.php` — Expected: new tests FAIL (e.g. no chip row).
- [ ] **Step 3: Migrate the component and view.**
- [ ] **Step 4: Run** `./vendor/bin/pint` then the full suite `php artisan test` (10-minute timeout; last full run 689 passed, 7 deprecated) — Expected: PASS.
- [ ] **Step 5: Manual check (human)** — `/admin/master/riwayat` at 375px and 1366px: filters, date range, chips, sort, paging, empty states.
- [ ] **Step 6: Commit** — `refactor(master): move audit history onto shared data table`
