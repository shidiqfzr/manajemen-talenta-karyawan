# Shared Data Table — Mutasi History Implementation Plan (Spec step 3b)

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Move the Riwayat Mutasi screen (`App\Livewire\Admin\MutasiHistory`) onto the shared data table layer without duplicating its domain rules, and route its compliance Excel export through the shared permission check, row limit and audit log.

**Architecture:** Filter and sort rules come from the shared pipeline; domain meaning stays in the model/service layer (`JobHistory` scopes, `MutasiService`). The screen's export keeps its own 22-column compliance workbook (`MutasiHistoryExport`) but is built from the same filtered query the screen shows, through two new trait hooks. KPI cards, the due-activation banner, row actions, batch and detail modals are unchanged.

**Tech Stack:** Laravel 13, Livewire 4.4.6, PHPUnit 11, maatwebsite/excel 3.1, Tailwind tokens from `DESIGN.md`, SQLite (tests) / MySQL (prod).

**Spec:** `docs/superpowers/specs/2026-11-06-shared-data-table-design.md` (§4–§6, §7 step 3, §9). Guidelines that bind this work: `DOMAIN.md` (mutasi rules, service layer — no domain calculation in views/components), `ENGINEERING.md` §2.3, `SECURITY.md` (UU PDP: NIK; audit trail), `DESIGN.md` §6/§7.5. Previous plans: `2026-11-06-shared-data-table-foundation.md`, `2026-11-07-shared-table-audit-history.md`.

**Branch:** create `feat/shared-table-mutasi-history` from `feat/shared-table-audit-history` (the parent branches are not merged yet).

**Out of scope:** the detail/batch modals, KPI cards, banner, `applyNow`/`applyAllDue`/`cancelScheduled` logic and their `wire:confirm` dialogs (the shared destructive-confirm modal comes with the bulk-actions step), numeric export values, formula-neutraliser extraction, vacancies/Employees/domain screens.

## Global Constraints

- PHP: composer.json declares `^8.3` (dev machine runs 8.5) — no syntax or functions newer than PHP 8.3.
- Code, docblocks, commit messages English; user-facing text Indonesian business wording; rendered output never contains `AGENTS.md`, `DOMAIN.md`, `DESIGN.md`, `ENGINEERING.md`, `SECURITY.md` or `SSOT`.
- Domain rules live in the model/service layer, not in the Livewire component or Blade: the meaning of "due", "scheduled", "applied" and "sumber" is defined only by `JobHistory` scopes (`bySumber`, `applied`, `scheduled`, `dueForActivation`).
- Public Livewire property names of the screen stay: `search`, `unitFilter`, `mutasiFilter`, `statusFilter`, `sumberFilter`, `sortField`, `sortDirection`, `perPage`, `selectedBatchId`, `batchItems`, `selectedJobId`, `detailJob`.
- URL keys of the screen after the migration: `search`, `unitFilter`, `mutasiFilter`, `statusFilter`, `sumberFilter`, `sort`, `direction`, `per_page`, `page` (old `sortField`/`sortDirection` URL keys are dropped; `sumberFilter=resmi` keeps working because "Mutasi Resmi" is the empty/default state).
- Source filter: default (`sumberFilter === ''`) = Mutasi Resmi; select options `semua` = `Semua Catatan` plus every `JobHistory::SUMBER_LABELS` entry except `SUMBER_MUTASI`; the empty option is labelled `Mutasi Resmi`.
- Status filter options: `applied` = `Aktif Menjabat`, `scheduled` = `Terjadwal`, `due` = `Siap Diaktifkan`.
- Search: `jabatan`, `unit_kerja`, employee `nama` and employee `nik`; LIKE with `!` escape (literal `%`/`_`); max 100 characters; debounce 300ms (shared toolbar).
- Exact copy: toolbar placeholder `Cari nama karyawan, NIK, jabatan, atau unit...`; live region `{n} riwayat ditemukan`; empty states: no data `Belum ada mutasi atau alih tugas yang tercatat dalam sistem.`, filtered `Tidak ada data yang sesuai dengan pencarian atau filter.` + `Reset filter`; export file name `Audit_Log_Mutasi_PTPN4_Reg5_{Ymd_His}.xlsx`; audit table label `Riwayat Mutasi`.
- Export: gate `export-data`, limit 10000 rows, audit entry `data_table.export` (no search term), same rows as the screen.
- Run `./vendor/bin/pint` before every commit; one test class per system under test (screen + export tests stay in `tests/Feature/MutasiHistoryTest.php`).
- Commit trailer: `Co-Authored-By: Claude Sonnet 5.5 <noreply@anthropic.com>`.

## Review Focus

1. **Bookmarked URLs:** `?sumberFilter=resmi`, `?sumberFilter=semua`, `?statusFilter=due`, `?unitFilter=<unit>` still filter; the old `sortField`/`sortDirection` keys are ignored and fall back to the default order, never an error → Task 5 `test_history_keeps_filter_urls_and_ignores_old_sort_keys`.
2. **Tampered state** (`sumberFilter=zzz`, `statusFilter=zzz`, `unitFilter=<unknown unit>`, `per_page=999`, `sort=password`) shows the default official list, no error → Task 5 `test_history_ignores_tampered_url_state`.
3. **Export equals screen:** with a search, unit, status and source filter active, the export contains exactly the rows on screen; a non-admin gets the permission notice and no download; an admin download writes a `data_table.export` audit row without the search term → Task 3 `test_export_uses_a_given_query...`, Task 5 `test_export_matches_the_screen_and_is_audited`, `test_export_requires_the_export_permission`.
4. **Literal search:** `%`/`_` match literally; NIK and employee name are searched → Task 5 `test_search_matches_name_nik_jabatan_unit_literally`.
5. **Double-click on Aktivasi / Batalkan:** while the action runs the rows show the skeleton and the buttons are disabled → Task 5 `test_row_actions_show_loading_state`.

---

## File Map

| File | Responsibility |
|---|---|
| `app/Support/DataTable/QueryPipeline.php` | + public `whereLikeAny` (single LIKE-with-escape helper) |
| `app/Livewire/Tables/{Training,Evaluation}Table.php` | use `QueryPipeline::whereLikeAny` |
| `app/Support/DataTable/Column.php`, `resources/views/components/admin/table/head.blade.php` | + `Column::width()` header classes |
| `app/Livewire/Concerns/WithDataTable.php` | + `exportFileName()`, `buildExport()` hooks |
| `app/Exports/MutasiHistoryExport.php` | optional prebuilt query |
| `app/Models/JobHistory.php`, `app/Services/Mutasi/MutasiHistoryReader.php` | + `scopeByStatus`; reader uses `dueForActivation()` |
| `app/Livewire/Admin/MutasiHistory.php` | migrated onto `WithDataTable` |
| `resources/views/livewire/admin/partials/mutasi-history/history-table.blade.php` | rebuilt from shared components |
| `docs/superpowers/specs/2026-11-06-shared-data-table-design.md` | §9 deltas 10–12 |

---

### Task 1: One LIKE helper

**Files:** Modify `app/Support/DataTable/QueryPipeline.php`, `app/Livewire/Tables/TrainingTable.php`, `app/Livewire/Tables/EvaluationTable.php`; Test `tests/Unit/DataTable/QueryPipelineTest.php`.

**Interfaces — Produces:** `QueryPipeline::whereLikeAny(Builder $query, array $columns, string $term): void` — adds one grouped `OR` of `column LIKE %term% ESCAPE '!'` over the model-qualified, grammar-wrapped columns, term escaped with `escapeLike` and bound. `applySearch` uses it; the two tables' private `whereLikeAny` copies are deleted and their closures call the shared one.

- [ ] **Step 1: Write failing test** `test_where_like_any_groups_columns_and_escapes_the_term` — columns `['nama','kode']`, term `SK_01`: returns only rows matching literally in either column, and does not leak an unrelated `orWhere` (apply on a query that already has `where('bidang','KEU')`, expect the filter still ANDed).
- [ ] **Step 2: Run** `php artisan test tests/Unit/DataTable/QueryPipelineTest.php` — Expected: FAIL (method not found).
- [ ] **Step 3: Implement the public helper; make `applySearch` and both tables use it; delete the duplicated private methods.**
- [ ] **Step 4: Run** `php artisan test tests/Unit/DataTable tests/Feature/TrainingTableTest.php tests/Feature/EvaluationTableTest.php` — Expected: PASS.
- [ ] **Step 5: Pint + commit** — `refactor(table): share one escaped LIKE helper`

---

### Task 2: Column width

**Files:** Modify `app/Support/DataTable/Column.php`, `resources/views/components/admin/table/head.blade.php`, `docs/superpowers/specs/2026-11-06-shared-data-table-design.md` (§9 delta 10); Test `tests/Unit/DataTable/ColumnTest.php`, `tests/Feature/DataTable/TableComponentsTest.php`.

**Interfaces — Produces:** `Column::width(string $classes): self` (clone; public readable property `width`, default `null`); `head` adds the classes to that column's `<th>` (e.g. `w-[18%]`).

- [ ] **Step 1: Write failing tests** — ColumnTest `test_width_is_kept_on_a_clone` (original unchanged); TableComponentsTest `test_header_applies_column_width_classes` (fixture variant or the Date fixture with a width on `nama`: header contains `w-[30%]`).
- [ ] **Step 2: Run** the two files — Expected: FAIL.
- [ ] **Step 3: Implement; append spec §9:** `10. Column::width() sets header width classes so a module can use a fixed-layout table (the mutasi history keeps its proportional columns).`
- [ ] **Step 4: Run** `php artisan test tests/Unit/DataTable tests/Feature/DataTable tests/Unit/ViewDesignGuardTest.php` — Expected: PASS.
- [ ] **Step 5: Pint + commit** — `feat(table): allow a width class on table columns`

---

### Task 3: Export hooks and a prebuilt query for the compliance workbook

**Files:** Modify `app/Livewire/Concerns/WithDataTable.php`, `app/Exports/MutasiHistoryExport.php`, `tests/Fixtures/DataTable/` (variant), spec §9 delta 11; Test `tests/Feature/DataTable/WithDataTableTest.php`, `tests/Feature/MutasiHistoryTest.php`.

**Interfaces — Produces:**
- Trait: `protected function exportFileName(): string` (default `exportName().'_'.now()->format('Y-m-d_His').'.xlsx'`) and `protected function buildExport(Builder $query, ?User $user): object` (default `new DataTableExport($query, $this->columns(), $this->tableLabel(), $user)`); `export()` calls both instead of hard-coding them. Gate, limit, selection narrowing, audit are unchanged.
- `MutasiHistoryExport::__construct(array $filters = [], ?User $user = null, ?Builder $query = null)`; `query()` returns the given query when present, else builds from `$filters` exactly as today.

- [ ] **Step 1: Write failing tests** — WithDataTableTest `test_export_uses_the_module_file_name_and_export_class` (fixture variant `FixtureTableCustomExport` overriding both hooks: downloaded file name equals the override and the export object is the custom class; permission, limit and audit still apply — audit row written). MutasiHistoryTest `test_export_uses_a_given_query_instead_of_rebuilding_filters` (pass a query restricted to one `nomor_sk`; `query()->count()` is 1 even though `filters` would match more) and `test_export_without_a_query_still_builds_from_filters` (existing behaviour).
- [ ] **Step 2: Run** `php artisan test tests/Feature/DataTable/WithDataTableTest.php tests/Feature/MutasiHistoryTest.php` — Expected: new tests FAIL.
- [ ] **Step 3: Implement; append spec §9:** `11. The trait's buildExport() and exportFileName() hooks let a module keep its own workbook format while using the shared permission check, row limit, audit entry and filtered query.`
- [ ] **Step 4: Run** `php artisan test tests/Feature/DataTable tests/Feature/MutasiHistoryTest.php` — Expected: PASS.
- [ ] **Step 5: Pint + commit** — `feat(table): let a module supply its own export workbook`

---

### Task 4: One definition of the mutasi status

**Files:** Modify `app/Models/JobHistory.php`, `app/Services/Mutasi/MutasiHistoryReader.php`; Test `tests/Feature/MutasiHistoryTest.php` (the model scope is tested here, with the screen it serves).

**Interfaces — Produces:** `JobHistory::scopeByStatus($query, ?string $status)`: `applied` → `applied()`, `scheduled` → `scheduled()`, `due` → `dueForActivation()`, anything else → unchanged query. The reader's `onlyDue` branch and the `due` KPI count call `dueForActivation()` instead of repeating `is_applied=false AND tmt_awal<=today` (same result).

- [ ] **Step 1: Write failing test** `test_by_status_scope_uses_the_domain_definitions` — rows: applied, scheduled future, scheduled due (TMT yesterday): `byStatus('applied')`, `'scheduled'`, `'due'` return the right ids, `byStatus('zzz')` and `null` return all.
- [ ] **Step 2: Run** `php artisan test tests/Feature/MutasiHistoryTest.php --filter=by_status_scope` — Expected: FAIL (scope missing).
- [ ] **Step 3: Implement the scope; replace the reader's two raw "due" conditions with `dueForActivation()`.**
- [ ] **Step 4: Run** `php artisan test tests/Feature/MutasiHistoryTest.php tests/Feature/MutasiServiceTest.php` — Expected: PASS (the KPI and due tests guard the reader change).
- [ ] **Step 5: Pint + commit** — `refactor(mutasi): define due, scheduled and applied once on JobHistory`

---

### Task 5: Migrate the screen

**Files:** Modify `app/Livewire/Admin/MutasiHistory.php`, `resources/views/livewire/admin/partials/mutasi-history/history-table.blade.php`, `resources/views/livewire/admin/mutasi-history.blade.php` (only if the KPI/status wiring needs it); Test `tests/Feature/MutasiHistoryTest.php`.

**Interfaces — Consumes:** Tasks 1–4. **Produces:** `MutasiHistory` uses `WithDataTable`.
- Properties `unitFilter`, `mutasiFilter`, `statusFilter`, `sumberFilter` each `#[Url(except: '')]`, default `''`; `$perPage` default via `defaultPerPage()` 15, `perPageOptions()` `[10, 15, 25, 50, 100]`; remove `queryString`, `updating*` hooks, `sortBy`, `resetFilters`, `exportAuditLog`, the old `render` query. Keep `toggleStatusFilter`, `showDetail`, `closeDetailModal`, `viewBatch`, `closeBatchModal`, `applyNow`, `applyAllDue`, `cancelScheduled` unchanged (each `toggleStatusFilter` also `resetPage`s through the paginator name).
- `#[Computed] units(): array` = `MutasiService::getKnownUnits()` (cached per request; `filters()` is built several times per request).
- `baseQuery()` = `JobHistory::with(['employee', 'creator'])` plus `bySumber('resmi')` when `$sumberFilter === ''`.
- `columns()`: `employee_nik` `Karyawan` (sortable; searchable via closure `whereHas('employee')` over `nama`,`nik` with `QueryPipeline::whereLikeAny`), `jabatan` `Jabatan & Unit Kerja` (sortable; searchable over `jabatan`,`unit_kerja`), `jenis_mutasi` `Jenis & Regulasi` (sortable, centre), `tmt_awal` `TMT Efektif` (sortable), `status` `Status` (centre), `nomor_sk` `Dasar SK` (sortable), `aksi` `Aksi` (centre); widths `w-[18%]`, `w-[24%]`, `w-[12%]`, `w-[12%]`, `w-[12%]`, `w-[14%]`, `w-[8%]`; the table has `table-fixed`.
- `filters()`: `unitFilter` select (options `[unit => unit]` from `units`, icon `fas fa-building`, all-label `Semua Unit Kerja`, apply `where('unit_kerja', …)`), `mutasiFilter` select (`JobHistory::MUTASI_TYPES` → `JobHistory::labelFor`, icon `fas fa-tag`, apply `where('jenis_mutasi', …)`), `statusFilter` select (Global Constraints options, icon `fas fa-flag`, apply `byStatus`), `sumberFilter` select (Global Constraints options, icon `fas fa-layer-group`, all-label `Mutasi Resmi`, apply `bySumber`).
- `defaultSort()` `['tmt_awal', 'desc']`; `tableLabel()` `Riwayat Mutasi`; `exportName()` `riwayat-mutasi`; `exportFileName()` the audit-log name; `buildExport()` returns `new MutasiHistoryExport(['sumber' => $this->sumber()], $user, $query)` where `sumber()` is `$sumberFilter === '' ? 'resmi' : $sumberFilter`.
- `render(MutasiService $service)` keeps the KPI counts from `getMutasiHistoryKpis($this->sumber())` and passes them to the view.
- View: `history-table` partial becomes `card` + `toolbar` (placeholder per Global Constraints, export on) + the kept `sr-only` live region + `table` (`table-fixed`) with `head`, the existing row cells moved unchanged (row tint classes kept), `skeleton`, `empty-state` (two states per Global Constraints), `pagination` (noun `riwayat`), wired like the audit-history view; loading targets `tableLoadingTargets('applyNow', 'cancelScheduled', 'applyAllDue')`; the old inline status pill and old controls are removed (the chip row replaces them). Update the KPI active state to `$statusFilter`.

- [ ] **Step 1: Update/write tests in `MutasiHistoryTest.php`** (existing assertions that name old behaviour change as stated):
  - Update `test_history_screen_shows_official_mutations_by_default_and_filters_by_source`: after `resetFilters`, `assertSet('sumberFilter', '')` (default state is now empty = Mutasi Resmi); `set('sumberFilter', 'nilai-asing')` still shows only the official row.
  - Update `test_can_export_audit_log_excel`: call `export` instead of `exportAuditLog`; file name regex unchanged; the callback still receives a `MutasiHistoryExport`.
  - New: `test_history_keeps_filter_urls_and_ignores_old_sort_keys`, `test_history_ignores_tampered_url_state`, `test_search_matches_name_nik_jabatan_unit_literally`, `test_karyawan_header_sorts_by_nik`, `test_status_and_source_filters_show_as_chips`, `test_export_matches_the_screen_and_is_audited` (rows of `instance()->rows` equal the exported query ids under search + unit + status + source; one `data_table.export` audit row; its detail has no search term), `test_export_requires_the_export_permission` (non-admin: notice `Anda tidak memiliki akses untuk tindakan ini.`, no audit row), `test_row_actions_show_loading_state` (`applyNow` and `cancelScheduled` in the table `wire:target`, and the `skeleton`/`aria-busy` markers), `test_empty_state_offers_reset_when_filtered`.
  - Existing tests for KPI toggle, apply/cancel, detail and batch modals, live-region announcement must pass unchanged except where they read the changed sumber default.
- [ ] **Step 2: Run** `php artisan test tests/Feature/MutasiHistoryTest.php` — Expected: new and updated tests FAIL (old component), unchanged ones PASS.
- [ ] **Step 3: Migrate the component and view.**
- [ ] **Step 4: Run** `./vendor/bin/pint` then the full suite `php artisan test` (10-minute timeout; last full run 709 passed, 7 deprecated) — Expected: PASS.
- [ ] **Step 5: Manual check (human)** — `/admin/mutasi/history` (and the mutasi index page that embeds it) at 375px and 1366px: KPI cards, filters, chips, sort, paging, export button, activate/cancel buttons, detail and batch modals.
- [ ] **Step 6: Commit** — `refactor(mutasi): move mutasi history onto shared data table`
