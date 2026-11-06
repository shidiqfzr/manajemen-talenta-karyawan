# Shared Data Table — Foundation Implementation Plan (Spec steps 1–2)

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build the shared table layer (`Column`, `Filter`, `BulkAction`, `TableState`, `QueryPipeline`, `WithDataTable`, `DataTableExport`, `x-admin.table.*` components) and move the three per-employee tables (`TrainingTable`, `EvaluationTable`, `JobHistoryTable`) onto it.

**Architecture:** Modules declare columns/filters/bulk actions in PHP; a pure `QueryPipeline` turns base query + declarations + state into one query used by screen, export and bulk actions; the `WithDataTable` Livewire trait owns and validates state; Blade components render toolbar, header, bulk bar, skeleton and notice while row cells stay module Blade.

**Tech Stack:** Laravel 13, Livewire 4.4, PHPUnit 11, maatwebsite/excel 3.1, Tailwind tokens from `DESIGN.md`, SQLite (tests) / MySQL (prod).

**Spec:** `docs/superpowers/specs/2026-11-06-shared-data-table-design.md` (read §4–§8; §9 lists the deltas this plan applies).

**Out of scope here (later plans, one per spec step):** AuditHistory/MutasiHistory (step 3), JobVacancyTable + Shift/Ctrl range selection (step 4), Employees directory (step 5), domain screens (step 6), guideline docs + guard test (step 7).

## Global Constraints

- All user-visible text is Indonesian business wording; never mention `AGENTS.md`, `DOMAIN.md`, `DESIGN.md`, `ENGINEERING.md`, `SECURITY.md` or "SSOT" in rendered output (internal names only in `{{-- --}}` / `//` comments).
- New code, comments in PHP classes, commit messages: English. Existing Indonesian docblocks are kept.
- Search input: `wire:model.live.debounce.300ms="search"`, max 100 characters after trim.
- Per-page options default `[10, 25, 50, 100]`, default per page `25`.
- Export limit default `10000` rows; Excel only (`.xlsx`); filename `{slug}_{Y-m-d_His}.xlsx`.
- LIKE search uses escape character `!` (escape `!`, `%`, `_`) via `LIKE ? ESCAPE '!'` — portable across SQLite and MySQL.
- Every bulk handler runs inside `DB::transaction()`.
- Audit via `MasterAuditLog::record($user, $action, 'data_table', null, $tableLabel, null, $detail, null)`; `$detail` never contains the raw search term or row values.
- Exact copy:
  - No permission: `Anda tidak memiliki akses untuk tindakan ini.`
  - Handler failed: `Tindakan gagal, tidak ada data yang berubah.`
  - Nothing selected: `Pilih minimal satu data.`
  - Export over limit: `Data terlalu besar untuk diekspor. Persempit filter terlebih dahulu.`
  - Bulk success default: `:count data berhasil diproses.`
  - Bulk bar: `:count terpilih` · link `Pilih semua :total data yang sesuai` · all-matching state `Semua :total data yang sesuai terpilih.` · `Batalkan pilihan`
  - Filtered empty state: `Tidak ada data yang sesuai dengan pencarian atau filter.` + button `Reset filter`
- Run `./vendor/bin/pint` before every commit; full `php artisan test` must pass at the end of each task.
- One test class per system under test.

## Review Focus

1. **Three tables on one page** (employee profile): paging/sorting one table must not move the others → Task 7 test `test_page_name_is_isolated` per table.
2. **Search for literal `%` or `_`** (e.g. SK numbers like `SK_01/2026`) must match literally, not as wildcards → Task 2 `test_search_escapes_like_wildcards`.
3. **Tampered state on load** (`?sort=password&direction=sideways&per_page=9999&bidang=XYZ`) must render the default table, never error → Task 3 `test_tampered_url_state_falls_back_to_defaults`.
4. **Excel formula injection** — a cell value starting with `=`, `+`, `-`, `@`, tab or CR must be exported prefixed with `'` → Task 5 `test_formula_prefixes_are_neutralised`.
5. **Search term leaking into the audit log** (often a name or NIK) — audit detail records only `'pencarian' => true|false` → Task 5 `test_export_audit_excludes_search_term`.

---

## File Map

| File | Responsibility |
|---|---|
| `app/Support/DataTable/Column.php` | Column value object |
| `app/Support/DataTable/Filter.php` | Filter value object + allowed-value check |
| `app/Support/DataTable/BulkAction.php` | Bulk action value object |
| `app/Support/DataTable/TableState.php` | State DTO passed to the pipeline |
| `app/Support/DataTable/QueryPipeline.php` | search → filters → sort → tiebreak |
| `app/Livewire/Concerns/WithDataTable.php` | Livewire state, validation, selection, bulk, export |
| `app/Exports/DataTableExport.php` | Generic Excel export |
| `app/Providers/AppServiceProvider.php` | `export-data` gate |
| `app/Models/MasterAuditLog.php` | labels for `data_table.*` |
| `resources/views/components/admin/table/{card,toolbar,head,bulk-bar,select-cell,skeleton,notice}.blade.php` | Shared UI |
| `resources/views/components/admin/table/sort-th.blade.php` | add Livewire mode |
| `tests/Fixtures/DataTable/{FixtureRow,FixtureTable}.php` | Test-only model + component |
| `app/Livewire/Tables/{Training,Evaluation,JobHistory}Table.php` + views | migrated |
| `resources/views/components/admin/table/wrapper.blade.php` | deleted once unused |

---

### Task 1: Value objects

**Files:**
- Create: `app/Support/DataTable/Column.php`, `Filter.php`, `BulkAction.php`, `TableState.php`
- Test: `tests/Unit/DataTable/ColumnTest.php`, `tests/Unit/DataTable/FilterTest.php`

**Interfaces — Produces:**
- `final class Column` — `static make(string $key, string $label): self`; fluent (each returns a modified clone): `sortable(?Closure $using = null)`, `searchable(?Closure $using = null)`, `align(string $align)` (`left|center|right`, else `InvalidArgumentException`), `mono()`, `exportable(?string $heading = null, ?Closure $value = null)`, `exportOnly()`; readonly getters `key`, `label`, `isSortable(): bool`, `isSearchable(): bool`, `isExportable(): bool`, `isExportOnly(): bool`, `align(): string` → use property `alignment`, `isMono(): bool`, `sortUsing(): ?Closure` (`fn(Builder $q, string $direction)`), `searchUsing(): ?Closure` (`fn(Builder $q, string $term)`), `exportHeading(): string` (defaults to label), `exportValue(Model $row): mixed` (closure or `data_get($row, key)`).
- `final class Filter` — `static select(string $property, string $label, array $options)` (`[value => label]`), `static boolean(string $property, string $label)` (allowed values `['1']`); fluent `icon(string)`, `allLabel(string)`, `apply(Closure $fn)` (`fn(Builder $q, string $value)`); getters `property`, `label`, `type`, `options(): array`, `allows(mixed $value): bool` (true for `''`/`null`, otherwise strict string membership in option keys), `applyTo(Builder $q, string $value): void` (closure or `where(property, value)`).
- `final class BulkAction` — `static make(string $name, string $label)`; fluent `icon(string)`, `ability(string)`, `danger(?string $confirm = null)`, `successMessage(string $template)`; getters `name`, `label`, `ability(): ?string` → property `requiredAbility`, `isDanger(): bool`, `confirmText(): ?string`, `handlerMethod(): string` (`'bulk'.Str::studly($name)`), `successText(int $count): string` (default template `:count data berhasil diproses.`).
- `final readonly class TableState` — `__construct(string $search, array $filterValues, string $sortField, string $sortDirection)`.

`dateRange` filters are deferred to the step-3 plan (first consumer: AuditHistory); do not build them here.

- [ ] **Step 1: Write failing tests**

`ColumnTest`: `test_fluent_calls_return_new_instances` (original unchanged after `->sortable()`), `test_export_heading_defaults_to_label`, `test_export_value_uses_closure_or_data_get`, `test_invalid_alignment_throws`.
`FilterTest`: `test_select_allows_only_option_keys` (`allows('KEU')` true, `allows('XYZ')` false, `allows('')` true, `allows(['KEU'])` false), `test_boolean_allows_only_one`, `test_apply_to_defaults_to_where_equals` (assert SQL via `toSql()` contains `"bidang" = ?`), `test_apply_to_uses_custom_closure`.

- [ ] **Step 2: Run** `php artisan test --filter='ColumnTest|FilterTest'` — Expected: FAIL, classes not found.
- [ ] **Step 3: Implement the four classes** per the interfaces above.
- [ ] **Step 4: Run** same command — Expected: PASS.
- [ ] **Step 5: Pint + commit** — `feat(table): add data table value objects`

---

### Task 2: QueryPipeline

**Files:**
- Create: `app/Support/DataTable/QueryPipeline.php`, `tests/Fixtures/DataTable/FixtureRow.php`
- Test: `tests/Unit/DataTable/QueryPipelineTest.php` (extends `Tests\TestCase`, `RefreshDatabase`)

**Interfaces:**
- Consumes: Task 1.
- Produces: `final class QueryPipeline` — `static apply(Builder $base, array $columns, array $filters, TableState $state, array $defaultSort): Builder`; `static escapeLike(string $term): string`.
- Produces: `Tests\Fixtures\DataTable\FixtureRow` — Eloquent model, table `data_table_fixture_rows`, guarded `[]`, no timestamps; static `createTable(): void` creating columns `id` (increments), `nama` string, `kode` string nullable, `bidang` string, `aktif` boolean default true, `nilai` integer.

Algorithm (order matters):
1. Search: if `$state->search !== ''`, `where(function)` with one `OR` clause per searchable column: `searchUsing` closure, or `whereRaw($grammar->wrap($q->qualifyColumn($key)).' LIKE ? ESCAPE \'!\'', ['%'.escapeLike($term).'%'])`. `escapeLike` replaces `!`→`!!`, `%`→`!%`, `_`→`!_`.
2. Filters: for each filter whose value is non-empty and `allows()` it, `applyTo()`.
3. Sort: field = `$state->sortField` if it is a sortable column key, else `$defaultSort[0]`; direction = `$state->sortDirection` if in `['asc','desc']`, else `$defaultSort[1]`. Use `sortUsing` or `orderBy(qualifyColumn(field), direction)`.
4. Tiebreak: `orderBy($base->getModel()->getQualifiedKeyName(), direction)`.

- [ ] **Step 1: Write failing tests** (seed fixture rows in `setUp` after `FixtureRow::createTable()`):
  - `test_search_matches_only_searchable_columns` — `nama` searchable, `kode` not: term matching only `kode` returns 0 rows.
  - `test_search_escapes_like_wildcards` — rows `SK_01`, `SKX01`; term `SK_01` returns exactly `SK_01`; term `100%` matches `Nilai 100%` only, not `Nilai 1000`.
  - `test_search_uses_custom_closure`.
  - `test_non_sortable_field_falls_back_to_default_sort` — state sort `kode` (not sortable) → ordered by default `nama asc`.
  - `test_invalid_direction_falls_back_to_default_direction`.
  - `test_primary_key_tiebreak_is_appended` — equal `bidang`, sort by `bidang` → ids ascending.
  - `test_filters_apply_only_allowed_values` — `bidang = 'XYZ'` ignored (all rows), `bidang = 'KEU'` filtered.
- [ ] **Step 2: Run** `php artisan test --filter=QueryPipelineTest` — Expected: FAIL.
- [ ] **Step 3: Implement** `QueryPipeline` and `FixtureRow`.
- [ ] **Step 4: Run** — Expected: PASS.
- [ ] **Step 5: Pint + commit** — `feat(table): add query pipeline with sort whitelist and escaped search`

---

### Task 3: WithDataTable — state, sort, search, filters, paging, URL

**Files:**
- Create: `app/Livewire/Concerns/WithDataTable.php`, `tests/Fixtures/DataTable/FixtureTable.php`
- Test: `tests/Feature/DataTable/WithDataTableTest.php`

**Interfaces:**
- Consumes: Tasks 1–2.
- Produces (trait, `use WithPagination` inside):
  - Public props: `string $search = ''`, `string $sortField = ''`, `string $sortDirection = ''`, `int $perPage = 0`, `array $selected = []`, `bool $selectAllMatching = false`, `?array $tableNotice = null` (`['type' => 'success'|'error'|'warning', 'message' => string]`).
  - Module contract: `abstract protected function baseQuery(): Builder`, `abstract protected function columns(): array`, `abstract protected function defaultSort(): array`, `abstract protected function tableLabel(): string`; overridable `filters(): array` (`[]`), `bulkActions(): array` (`[]`), `perPageOptions(): array` (`[10,25,50,100]`), `defaultPerPage(): int` (`25`), `exportName(): ?string` (`null`), `exportAbility(): string` (`'export-data'`), `exportLimit(): int` (`10000`), `syncsUrl(): bool` (`true`), `paginationName(): string` (`'page'`), `rowKey(): string` (model key name).
  - Methods: `mountWithDataTable(): void`, `updatedWithDataTable(string $property): void`, `queryStringWithDataTable(): array`, `sortBy(string $field): void`, `resetFilters(): void`, `filterProperties(): string` (comma-joined filter property names), `tableState(): TableState`, `filteredQuery(): Builder` (pipeline result, unpaginated), `#[Computed] rows(): LengthAwarePaginator` (`filteredQuery()->paginate($perPage, pageName: paginationName())`), `hasActiveFilters(): bool` (search or any filter non-empty).
- Produces: `Tests\Fixtures\DataTable\FixtureTable` — Livewire component using the trait over `FixtureRow`; columns `nama` (sortable, searchable, exportable), `kode` (exportable), `nilai` (sortable, mono, exportable); filter `Filter::select('bidang', 'Bidang', ['KEU'=>'KEU','TAN'=>'TAN'])` on public `#[Url(except: '')] string $bidang = ''`; default sort `['nama','asc']`; `tableLabel()` `'Tabel Uji'`. `render()` returns an inline Blade string using the Task 6 components (until Task 6 lands, a minimal `<div>@foreach($this->rows as $r)<span>{{ $r->nama }}</span>@endforeach</div>`).

Behaviour:
- `mountWithDataTable`: if `perPage` not in options → `defaultPerPage()`; if `sortField`/`sortDirection` invalid → `defaultSort()`; each filter property whose value fails `allows()` → `''`.
- `updatedWithDataTable($p)`: `search` → `mb_substr(trim(...), 0, 100)`, reset page, clear selection; filter property → validate as above, reset page, clear selection; `perPage` → clamp, reset page.
- `sortBy($field)`: ignore non-sortable; same field toggles direction, new field `asc`; reset page; clear selection.
- `queryStringWithDataTable()`: when `syncsUrl()` false return `[]`; else `['search' => ['except' => ''], 'sortField' => ['as' => 'sort'], 'sortDirection' => ['as' => 'direction'], 'perPage' => ['as' => 'per_page']]`.
- All page resets use `$this->resetPage($this->paginationName())`.

- [ ] **Step 1: Write failing tests** (`FixtureRow::createTable()` + 30 rows in `setUp`; admin user `User::factory()->create(['role' => 'admin'])`):
  - `test_defaults_applied_on_mount` — `sortField` `nama`, `sortDirection` `asc`, `perPage` 25, 25 rows rendered.
  - `test_tampered_url_state_falls_back_to_defaults` — `Livewire::withQueryParams(['sort'=>'password','direction'=>'sideways','per_page'=>9999,'bidang'=>'XYZ'])->test(FixtureTable::class)` → `assertSet('sortField','nama')`, `assertSet('sortDirection','asc')`, `assertSet('perPage',25)`, `assertSet('bidang','')`, `assertOk()`.
  - `test_sort_by_ignores_non_sortable_columns` — `call('sortBy','kode')` leaves `nama`.
  - `test_sort_by_toggles_direction_and_resets_page`.
  - `test_search_is_trimmed_capped_and_resets_page` — set 120-char padded string → length 100; page back to 1.
  - `test_invalid_filter_value_is_cleared` — `set('bidang','XYZ')` → `''`.
  - `test_per_page_is_clamped`.
  - `test_reset_filters_clears_search_and_filters_and_returns_to_page_one`.
  - `test_url_sync_can_be_disabled` — subclass fixture with `syncsUrl()` false: `queryStringWithDataTable()` returns `[]`.
- [ ] **Step 2: Run** `php artisan test --filter=WithDataTableTest` — Expected: FAIL.
- [ ] **Step 3: Implement** trait + fixture component.
- [ ] **Step 4: Run** — Expected: PASS.
- [ ] **Step 5: Pint + commit** — `feat(table): add WithDataTable trait for validated table state`

---

### Task 4: Selection and bulk actions

**Files:**
- Modify: `app/Livewire/Concerns/WithDataTable.php`, `tests/Fixtures/DataTable/FixtureTable.php`, `app/Models/MasterAuditLog.php` (labels), `app/Providers/AppServiceProvider.php`
- Test: `tests/Feature/DataTable/WithDataTableTest.php` (same class)

**Interfaces:**
- Produces (trait): `toggleSelectPage(): void` (selects current page keys as strings, or clears them if all already selected), `selectAllMatching(): void` → name the method `selectAllMatchingRows()` (property `selectAllMatching` already exists), `clearSelection(): void`, `#[Computed] matchingCount(): int`, `selectedCount(): int` (`matchingCount` when all-matching else `count($selected)`), `runBulkAction(string $name): void`, `bulkTargets(): Builder`.
- Fixture adds `BulkAction::make('nonaktifkan','Nonaktifkan')->ability('manage-fixture')` handler `bulkNonaktifkan(Builder $targets): int` (updates `aktif = false`, returns count) and `BulkAction::make('gagal','Gagal')` whose handler updates then throws `RuntimeException`.
- `AppServiceProvider::boot` defines `Gate::define('export-data', fn (User $u) => $u->role === 'admin')`. Tests define `manage-fixture` themselves.
- `MasterAuditLog::ACTION_LABELS` add `'data_table.bulk' => 'Aksi massal pada tabel data'`, `'data_table.export' => 'Tabel data diunduh'`; `ENTITY_LABELS` add `'data_table' => 'Tabel Data'`.

`runBulkAction($name)` flow (exact order):
1. Action not found in `bulkActions()` → `abort(403)`.
2. Ability set and `Gate::denies()` → notice error `Anda tidak memiliki akses untuk tindakan ini.`, return.
3. `selectedCount() === 0` → notice warning `Pilih minimal satu data.`, return.
4. `bulkTargets()` = `filteredQuery()` plus, unless `selectAllMatching`, `whereIn($model->qualifyColumn(rowKey()), $selected)`.
5. `try { $count = DB::transaction(fn () => $this->{$action->handlerMethod()}($targets)); } catch (Throwable $e) { report($e); notice error 'Tindakan gagal, tidak ada data yang berubah.'; return; }`
6. Audit `MasterAuditLog::record(auth()->user(), 'data_table.bulk', 'data_table', null, tableLabel(), null, ['aksi' => name, 'jumlah_baris' => $count, 'filter' => activeFilterValues, 'pencarian' => search !== ''], null)`.
7. Notice success `successText($count)`, `clearSelection()`, `unset($this->rows, $this->matchingCount)`.

- [ ] **Step 1: Write failing tests:**
  - `test_toggle_select_page_selects_and_clears_current_page_keys`.
  - `test_selection_survives_page_change_and_clears_on_search_filter_or_sort`.
  - `test_unknown_bulk_action_is_forbidden` — `call('runBulkAction','hapus')->assertForbidden()`.
  - `test_bulk_action_without_permission_shows_notice_and_changes_nothing`.
  - `test_bulk_action_with_empty_selection_warns`.
  - `test_bulk_action_on_selected_rows_updates_only_those_and_audits` — `MasterAuditLog` row with action `data_table.bulk`, `jumlah_baris` 2.
  - `test_bulk_action_ignores_keys_outside_current_filter` — filter `bidang=KEU`, selected includes a TAN id → TAN row unchanged.
  - `test_select_all_matching_targets_every_filtered_row` — 30 rows, 20 KEU, select all matching under KEU → 20 updated.
  - `test_failed_bulk_handler_rolls_back` — `gagal` → all rows still `aktif`, error notice, no audit row.
- [ ] **Step 2: Run** `php artisan test --filter=WithDataTableTest` — Expected: new tests FAIL.
- [ ] **Step 3: Implement.**
- [ ] **Step 4: Run** — Expected: PASS.
- [ ] **Step 5: Pint + commit** — `feat(table): add selection and audited bulk actions`

---

### Task 5: Export

**Files:**
- Create: `app/Exports/DataTableExport.php`
- Modify: `app/Livewire/Concerns/WithDataTable.php`, fixture (`exportName()` → `'tabel-uji'`)
- Test: `tests/Feature/DataTable/DataTableExportTest.php`, `tests/Feature/DataTable/WithDataTableTest.php`

**Interfaces:**
- Produces: `final class DataTableExport implements FromQuery, WithHeadings, WithMapping, WithChunkReading, WithProperties, WithEvents` — `__construct(Builder $query, array $columns, string $title, ?User $user)`; uses only `isExportable()` columns (including `exportOnly`); `headings()` = export headings; `map($row)` = `exportValue()` per column, each string passed through `neutralise(string): string` (prefix `'` when first char in `['=', '+', '-', '@', "\t", "\r"]`); `chunkSize()` 500; `properties()` sets `creator` = user name, `title`, `description` `Dokumen rahasia — diunduh oleh {nama} pada {d-m-Y H:i}`; `AfterSheet` sets the same text as print footer (mirror `EmployeesExport`).
- Produces (trait): `export(): ?BinaryFileResponse`.

`export()` flow: `exportName()` null → `abort(404)`; `Gate::denies(exportAbility())` → notice error, return null; query = `filteredQuery()` (+ `whereIn` selection when `selected` non-empty and not all-matching); `count() > exportLimit()` → notice warning `Data terlalu besar untuk diekspor. Persempit filter terlebih dahulu.`, return null; audit `data_table.export` with `['jumlah_baris', 'filter', 'pencarian' => bool]`; `return Excel::download(new DataTableExport(...), exportName().'_'.now()->format('Y-m-d_His').'.xlsx')`.

- [ ] **Step 1: Write failing tests**
  `DataTableExportTest`: `test_headings_and_rows_follow_exportable_columns`, `test_formula_prefixes_are_neutralised` (row `nama` `=SUM(A1)` → `'=SUM(A1)`), `test_chunk_size_is_500`, `test_properties_name_the_downloader`.
  `WithDataTableTest` (with `Excel::fake()`, `$this->travelTo('2026-11-06 10:00:00')`): `test_export_downloads_the_filtered_rows` (`Excel::assertDownloaded('tabel-uji_2026-11-06_100000.xlsx', fn (DataTableExport $e) => $e->query()->count() === 20)` under `bidang=KEU`), `test_export_respects_selection`, `test_export_requires_permission` (non-admin → notice, `Excel::assertNothingDownloaded()` — if unavailable, assert no audit row), `test_export_over_limit_is_refused` (fixture subclass `exportLimit()` 10), `test_export_audit_excludes_search_term` (search `Budi` → audit detail has `pencarian` true and no value equal to `Budi`), `test_export_disabled_returns_not_found`.
- [ ] **Step 2: Run** `php artisan test --filter='DataTableExportTest|WithDataTableTest'` — Expected: FAIL.
- [ ] **Step 3: Implement.**
- [ ] **Step 4: Run** — Expected: PASS.
- [ ] **Step 5: Pint + commit** — `feat(table): add audited Excel export for data tables`

---

### Task 6: Blade components

**Files:**
- Create: `resources/views/components/admin/table/card.blade.php`, `toolbar.blade.php`, `head.blade.php`, `bulk-bar.blade.php`, `select-cell.blade.php`, `skeleton.blade.php`, `notice.blade.php`
- Modify: `resources/views/components/admin/table/sort-th.blade.php`, fixture `render()` to the full markup
- Test: `tests/Feature/DataTable/TableComponentsTest.php`; existing `tests/Unit/ViewDesignGuardTest.php` must stay green

**Interfaces (props):**
- `card` — slot only; `bg-white` rounded card shell; renders `<x-admin.table.notice />` at top.
- `notice` — reads `$tableNotice` from the parent component (`$this->tableNotice` via `@props(['notice' => null])`, passed by `card`); success `role="status"`, error/warning `role="alert"`, tokens as `x-admin.flash`; dismiss button `wire:click="$set('tableNotice', null)"` with `aria-label="Tutup pemberitahuan"`.
- `toolbar` — `searchPlaceholder` (default `Cari...`), `filters` (list<Filter>), `export` (bool). Level 1: optional `title` slot + export button top-right (`wire:click="export"`, label `Ekspor Excel`, `wire:loading.attr="disabled"`). Level 2: `x-admin.search-input`-styled input with `wire:model.live.debounce.300ms="search"` and `maxlength="100"`, one `x-admin.filter-select` per select filter (`id` = `{component-id}-filter-{property}`), boolean filters as chips, `Reset filter` button shown when `hasActiveFilters()`.
- `head` — `columns`, `sortField`, `sortDirection`, `selectable` (bool), `pageKeys` (array of current page keys as strings). Renders `<thead class="bg-surface-50 sticky top-0 z-10">` with `<th scope="col">`; selectable adds a `w-10` header checkbox (`aria-label="Pilih semua di halaman ini"`, `x-bind:indeterminate` when some but not all page keys selected, `wire:click="toggleSelectPage"`); sortable columns use `sort-th` Livewire mode; non-sortable use `th`; `mono` columns get `font-mono tabular-nums`; alignment classes from `Column::alignment`.
- `sort-th` — new optional prop `livewire` (bool, default false). When true: `<button type="button" wire:click="sortBy('{{ $column }}')">` with `aria-sort` on the `<th>` from props `active`/`direction`; when false, existing link behaviour unchanged.
- `bulk-bar` — `actions` (list<BulkAction>). Hidden when `selectedCount() === 0`; `aria-live="polite"` region with `:count terpilih`; when all page rows selected and not all-matching and `matchingCount > count(selected)`: link `Pilih semua :total data yang sesuai`; all-matching state text `Semua :total data yang sesuai terpilih.`; `Batalkan pilihan` (`wire:click="clearSelection"`); one `x-admin.button` per action, danger ones with `wire:confirm="{{ $action->confirmText() }}"` (replaced by the shared confirm modal in the step-4 plan).
- `select-cell` — `key`, `label`. `<td class="w-10">` with real `<input type="checkbox" wire:model.live="selected" value="{{ $key }}" aria-label="Pilih {{ $label }}">`.
- `skeleton` — `columns` (int), `rows` (int, default 5). `<tbody wire:loading.delay>` of `animate-pulse` bars using `bg-surface-100`; module `<tbody>` carries `wire:loading.remove` with the same targets.

Selected row style (module rows): `bg-surface-50 border-l-4 border-l-primary-600` when key in `$selected`.

- [ ] **Step 1: Write failing tests** (`TableComponentsTest`, render through `Livewire::test(FixtureTable::class)` with a bulk-enabled fixture):
  - `test_header_marks_active_sort_with_aria_sort` — `aria-sort="ascending"` on Nama.
  - `test_sortable_header_uses_livewire_sort` — `wire:click="sortBy('nama')"` present, not for `kode`.
  - `test_search_input_is_debounced_and_capped` — `wire:model.live.debounce.300ms="search"`, `maxlength="100"`.
  - `test_export_button_only_when_enabled`.
  - `test_bulk_bar_shows_count_and_select_all_link` — select page → `25 terpilih`, `Pilih semua 30 data yang sesuai`.
  - `test_filtered_empty_state_offers_reset` — search `zzz` → `Tidak ada data yang sesuai dengan pencarian atau filter.` and `Reset filter`.
  - `test_notice_roles` — error → `role="alert"`, success → `role="status"`.
  - `test_no_internal_document_names_rendered` — rendered HTML has none of `AGENTS.md`, `DOMAIN.md`, `DESIGN.md`, `ENGINEERING.md`, `SECURITY.md`, `SSOT`.
- [ ] **Step 2: Run** `php artisan test --filter=TableComponentsTest` — Expected: FAIL.
- [ ] **Step 3: Implement components; update fixture render.**
- [ ] **Step 4: Run** `php artisan test --filter='TableComponentsTest|ViewDesignGuardTest|WithDataTableTest'` — Expected: PASS.
- [ ] **Step 5: Pint + commit** — `feat(table): add shared table toolbar, header, bulk bar and states`

---

### Task 7: Migrate the per-employee tables

**Files:**
- Modify: `app/Livewire/Tables/TrainingTable.php`, `EvaluationTable.php`, `JobHistoryTable.php`; `resources/views/livewire/tables/training-table.blade.php`, `evaluation-table.blade.php`, `job-history-table.blade.php`
- Delete: `resources/views/components/admin/table/wrapper.blade.php` (after `grep -r "x-admin.table.wrapper" resources/views` returns nothing)
- Test: `tests/Feature/TrainingTableTest.php`, `tests/Feature/EvaluationTableTest.php`, `tests/Feature/JobHistoryTableTest.php` (new, one per component)

**Interfaces:**
- Consumes: Tasks 1–6.
- Each component keeps `public string $employeeId` + `mount(string $employeeId)` (call site `admin/employees/show.blade.php` unchanged) and its existing row action (`detachTraining`, `deleteRow`, JobHistory's existing action) unchanged, including its flash message.
- Shared overrides for all three: `syncsUrl()` false; `perPageOptions()` `[5, 10, 25, 50]`; `defaultPerPage()` 5; `exportName()` null; `paginationName()` `'pelatihan_page'` / `'evaluasi_page'` / `'riwayat_jabatan_page'`.
- Declarations (keep today's searchable columns and sortable headers):
  - **TrainingTable** — `tableLabel` `Riwayat Pelatihan`; default sort `['tanggal_mulai','desc']`; searchable `judul`, `penyelenggara`, `jenis`, `metode`; sortable = the columns sortable in today's view.
  - **EvaluationTable** — `Riwayat Evaluasi`; default `['tanggal_pelaksanaan_asesmen','desc']`; sortable `tanggal_pelaksanaan_asesmen` (Periode Asesmen), `nilai_tertimbang` (mono), `bidang_tugas`; searchable = today's search columns; non-sortable `9-Box Performance`, `Lembaga & Tanggal`, `Aksi` (center).
  - **JobHistoryTable** — `Riwayat Jabatan`; default = today's `$sortField`/`$sortDirection`; searchable `jabatan`, `unit_kerja`, `jenis_mutasi`, `nomor_sk`.
- Views: replace `x-admin.table.wrapper` with `card` + `toolbar` (no export) + `<table>` + `head` + module rows + `skeleton` + `x-admin.table.pagination` (noun `pelatihan` / `evaluasi` / `riwayat jabatan`). Row cell markup is moved unchanged.

- [ ] **Step 1: Write failing tests** (each class, employee via factory + 7 related records):
  - `test_lists_only_this_employees_records`.
  - `test_default_sort_and_five_per_page`.
  - `test_search_matches_declared_columns`.
  - `test_tampered_sort_field_is_ignored` — `set('sortField','employee_nik; drop')` → still renders, default order.
  - `test_page_name_is_isolated` — `call('gotoPage', 2, '<pageName>')` then assert paginator `pageName` is the table's name (`$component->viewData` / `$this->rows->getPageName()`).
  - `test_row_action_still_works` — existing detach/delete behaviour + flash message.
  - Plus one page-level test in `EmployeeCrudTest` (existing class for the profile page): `test_profile_renders_three_history_tables_with_separate_pagination` — GET profile → sees `pelatihan`, `evaluasi`, `riwayat jabatan` footers.
- [ ] **Step 2: Run** `php artisan test --filter='TrainingTableTest|EvaluationTableTest|JobHistoryTableTest|EmployeeCrudTest'` — Expected: new tests FAIL.
- [ ] **Step 3: Migrate the three components and views; delete `wrapper.blade.php`.**
- [ ] **Step 4: Run** `./vendor/bin/pint` then `php artisan test` — Expected: full suite PASS.
- [ ] **Step 5: Manual check** — `php artisan serve`, open an employee profile at 375px and 1366px: three tables page/sort independently, skeleton shows on change, no horizontal page scroll.
- [ ] **Step 6: Commit** — `refactor(tables): move employee history tables onto shared data table`
