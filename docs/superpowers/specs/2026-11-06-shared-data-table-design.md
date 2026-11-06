# Shared Data Table — Design

- **Date:** 2026-11-06
- **Status:** Draft, awaiting review
- **Scope:** Every admin data table (Livewire and the controller-rendered Employees directory)

## 1. Problem

Tables are built three different ways today, so behaviour and security differ per module:

| Pattern | Where | Gaps |
|---|---|---|
| Controller + GET form | `admin/employees`, import batches | No shared component; hand-built filter chips; different sort component (`sort-th` uses `request()` links) |
| Copy-pasted Livewire classes | `Tables/TrainingTable`, `EvaluationTable`, `JobHistoryTable` | Near-identical code; `orderBy($this->sortField)` with **no sort whitelist**; no URL state, no filters, no export |
| Custom Livewire per screen | `MutasiHistory`, `Master/AuditHistory`, `Master/FormasiEditor`, `Tables/JobVacancyTable`, `ManPowerPlanning`, `MutasiProcess`, `MutasiChainProcess` | Each re-implements search/sort/per-page/selection/export differently |

Export auditing is also inconsistent: `EmployeeExportService` writes only `Log::info`, while `ManPowerPlanning` writes `MasterAuditLog::record` (visible on the audit history screen).

## 2. Goal and success criteria

One shared table layer that every module uses, with identical sorting, search, filters, export, pagination and bulk actions.

Success means:

1. A new module table only declares its base query, columns, filters and (optionally) bulk actions; toolbar, header, pagination, empty/loading states and export come from the shared layer.
2. Every sort, filter and per-page value is validated server-side against what the component declares. Tampered client state or URLs never reach SQL.
3. Screen, export and bulk actions always operate on the same filtered rows.
4. Every export and bulk action is permission-checked and recorded in `MasterAuditLog`.
5. All admin tables, including the Employees directory, run on the shared layer, and existing Employees URLs (`?level=Karpim&status_khusus=rangkap` …) keep working.

Non-goals (YAGNI): queued exports, saved views, user-configurable column visibility, CSV/print output (`DESIGN.md` §7.5: Excel is the single export format), a fully auto-rendered table body.

## 3. Decisions

| Decision | Choice | Reason |
|---|---|---|
| Approach | **Hybrid:** columns declared in PHP drive the header, sort whitelist, search and export; row cells stay hand-written Blade | Consistency where it must be identical; free-form domain cells (badges, 9-box pills, row menus) stay readable |
| Third-party table package | Not used | Would clash with `DESIGN.md` tokens and poorly fit domain screens |
| Employees directory | Migrated to Livewire on the shared layer, last | One stack for all tables; legacy query strings preserved |
| Bulk "select all" | Current page, plus an explicit "Pilih semua N data yang sesuai" link | Gmail pattern; "all matching" re-runs the filtered query server-side instead of holding keys in state |
| Filter state | One real public property per filter with `#[Url(as: ...)]`, not a generic `$filters` array | Keeps clean, backwards-compatible URLs |
| Export audit | `MasterAuditLog::record` for all modules | Queryable, shown on the audit history screen; replaces file-only `Log::info` |

## 4. Architecture

### 4.1 Units

| Unit | Location | Responsibility | Depends on |
|---|---|---|---|
| `Column` | `app/Support/DataTable/Column.php` | Immutable value object for one column: `make(key, label)`, `sortable()`, `searchable()`, `align('left'\|'center'\|'right')`, `mono()`, `exportable(?label, ?fn)`, `sortUsing(fn)`, `searchUsing(fn)`, `hidden()` (export-only column) | — |
| `Filter` | `app/Support/DataTable/Filter.php` | Value object for one filter bound to a component property: `select(property, label, options)`, `boolean(property, label)` (chip), `dateRange(fromProperty, toProperty, label)`; `icon()`, `allLabel()`, `apply(fn(Builder, value))`. Knows its allowed values | — |
| `BulkAction` | `app/Support/DataTable/BulkAction.php` | Value object: `make(name, label)`, `icon()`, `ability(string)`, `danger()`, `confirm(string)`, `handler(string method)` (defaults to `bulk` + StudlyName) | — |
| `TableState` | `app/Support/DataTable/TableState.php` | Plain DTO of the current search, filter values, sort field/direction | — |
| `QueryPipeline` | `app/Support/DataTable/QueryPipeline.php` | Pure: `apply(Builder $base, array $columns, array $filters, TableState $state): Builder`. Applies search → filters → sort (whitelisted) → primary-key tiebreak | `Column`, `Filter`, `TableState` |
| `WithDataTable` | `app/Livewire/Concerns/WithDataTable.php` | Livewire trait (uses `WithPagination`). Properties: `search`, `sortField`, `sortDirection`, `perPage`, `selected`, `selectAllMatching`. Methods: `sortBy()`, `filterProperties()` (comma list for `wire:target`), `toggleSelectPage()`, `selectAllMatching()`, `clearSelection()`, `runBulkAction()`, `export()`, `resetFilters()`; computed `rows`, `matchingCount`. Lifecycle via `mountWithDataTable`, `updatedWithDataTable` | `QueryPipeline`, `DataTableExport` |
| `DataTableExport` | `app/Exports/DataTableExport.php` | Generic `FromQuery` + `WithChunkReading` + headings/mapping from `exportable()` columns; confidentiality footer and metadata as in `EmployeesExport` | `Column`, maatwebsite/excel |
| Blade components | `resources/views/components/admin/table/` | New: `card` (table card shell, `DESIGN.md` §7.5 integrated toolbar), `toolbar`, `head`, `bulk-bar`, `select-cell`, `skeleton`. Reused: `pagination`, `empty-state`, `x-admin.filter-select`. `sort-th` gains a Livewire mode (`wire:click="sortBy(...)"`) | `DESIGN.md` tokens |

### 4.2 Module contract

A component using the trait implements:

```php
abstract protected function baseQuery(): Builder;     // already scoped to what the user may see
abstract protected function columns(): array;         // list<Column>
protected function filters(): array { return []; }   // list<Filter>
protected function bulkActions(): array { return []; } // list<BulkAction>
protected function defaultSort(): array;              // [field, 'asc'|'desc']
protected function perPageOptions(): array { return [10, 25, 50, 100]; }
protected function exportName(): ?string { return null; } // null = export disabled
protected function exportAbility(): string { return 'export-data'; } // Gate ability checked before export
protected function exportLimit(): int { return 10000; }
protected function rowKey(): string { return $this->baseQuery()->getModel()->getKeyName(); }
```

Example:

```php
class EvaluationTable extends Component
{
    use WithDataTable;

    #[Url(as: 'bidang', except: '')]
    public string $bidang = '';

    public string $employeeId;

    protected function baseQuery(): Builder
    {
        return Evaluation::query()->where('employee_nik', $this->employeeId);
    }

    protected function columns(): array
    {
        return [
            Column::make('tanggal_pelaksanaan_asesmen', 'Tanggal')->sortable()->mono()->exportable(),
            Column::make('bidang_tugas', 'Bidang Tugas')->sortable()->searchable()->exportable(),
        ];
    }

    protected function filters(): array
    {
        return [Filter::select('bidang', 'Bidang', ['KEU' => 'KEU', 'TAN' => 'TAN', 'TEK' => 'TEK', 'UMU' => 'UMU'])];
    }

    protected function defaultSort(): array
    {
        return ['tanggal_pelaksanaan_asesmen', 'desc'];
    }
}
```

View:

```blade
<x-admin.table.card>
    <x-admin.table.toolbar :filters="$this->filters()" :export="$this->exportName() !== null" search-placeholder="Cari bidang tugas..." />
    <x-admin.table.bulk-bar :actions="$this->bulkActions()" />
    <table class="...">
        <x-admin.table.head :columns="$this->columns()" :sort-field="$sortField" :sort-direction="$sortDirection" :selectable="$this->bulkActions() !== []" />
        <tbody wire:loading.remove wire:target="search, sortBy, perPage, gotoPage, nextPage, previousPage, {{ $this->filterProperties() }}">
            @forelse ($this->rows as $row)
                <tr wire:key="row-{{ $row->getKey() }}"> ...module cells... </tr>
            @empty
                <x-admin.table.empty-state ... />
            @endforelse
        </tbody>
        <x-admin.table.skeleton :columns="count($this->columns())" />
    </table>
    <x-admin.table.pagination :paginator="$this->rows" noun="evaluasi" :per-page-options="$this->perPageOptions()" />
</x-admin.table.card>
```

Domain rules stay out of the table layer: filters such as BUP/MBT, tugas rangkap or RM band call existing model scopes and services through `Filter::apply()` (`DOMAIN.md`: no RM/bidang calculation in views or controllers).

## 5. Data flow

### 5.1 State in

- `mountWithDataTable` applies `defaultSort()` when no valid sort arrived via URL.
- `updatedWithDataTable($property)`:
  - `search`: trim, cap at 100 chars, reset page, clear selection.
  - a filter property: if the value is not in the filter's allowed values, reset it to `''`; reset page, clear selection.
  - `perPage`: clamp to `perPageOptions()` (fallback: first option ≥ 25, else first), reset page.
  - `sortField` / `sortDirection`: validated in `sortBy()`; the pipeline validates again because values can arrive through `#[Url]` on page load.
- Selection survives page changes and clears on search, filter or sort change.

### 5.2 Query

`QueryPipeline::apply()` in order:

1. **Search** — if non-empty: grouped `where(fn)` of `OR` clauses over `searchable()` columns, `LIKE` with `%`/`_`/`\` escaped and values bound; `searchUsing` overrides a column (e.g. delegate to `Employee::search` scope).
2. **Filters** — for each filter with a valid non-empty value, call `apply()`; default `apply` is `where(property, value)`.
3. **Sort** — if `sortField` is a `sortable()` column: `sortUsing` or `orderBy(column, direction)`; otherwise `defaultSort()`. Direction is coerced to `asc`/`desc`.
4. **Tiebreak** — `orderBy(primaryKey)` for stable paging.

`rows` = pipeline result `->paginate($perPage)`; `matchingCount` = pipeline result `->count()` (only computed when the select-all banner shows).

### 5.3 Screen

The view renders `$this->rows`. `wire:loading` on filter/page/sort/search shows `skeleton` (`DESIGN.md` §7.5: no momentarily empty table). Empty results show `empty-state` with distinct wording for "no data yet" vs "no match for this filter" (the latter offers "Reset filter").

### 5.4 Bulk action

`runBulkAction(string $name)`:

1. Look up `$name` in `bulkActions()`; unknown → 403.
2. `Gate::authorize($action->ability)`.
3. Empty selection and not `selectAllMatching` → warning toast, stop.
4. Targets = pipeline query; if not `selectAllMatching`, add `whereIn(rowKey(), $selected)` (keys the user cannot currently see are dropped).
5. `DB::transaction(fn () => $this->{$action->handler}($targets))`.
6. `MasterAuditLog::record(...)` with action name, affected count and active filters (no personal data values).
7. Success toast, clear selection, reset computed rows.

Danger actions open the existing destructive confirm modal first, stating the row count ("N data akan …").

### 5.5 Export

`export()`:

1. Abort if `exportName()` is null; `Gate::authorize($this->exportAbility())` (modules handling personal data override the ability, e.g. `export-employees`).
2. Query = pipeline result without paging; restricted to selection when rows are selected.
3. Refuse when count > `exportLimit()` (default 10,000).
4. `MasterAuditLog::record(...)` with module, row count and active filters.
5. `Excel::download(new DataTableExport(...), $filename)` — filename `{slug}_{Y-m-d_His}.xlsx`.

Modules with special needs override `export()`. The Employees directory keeps `EmployeeExportService` (UU PDP filter sanitising, footer) and switches its audit from `Log::info` to `MasterAuditLog::record`.

## 6. Error handling

| Case | Behaviour | User sees |
|---|---|---|
| Invalid sort / filter / per-page value (tampered URL or state) | Silent fallback to default; never reaches SQL | Normal table |
| Unknown bulk action or missing permission | 403 via `Gate::authorize` | Toast "Anda tidak memiliki akses untuk tindakan ini." |
| Bulk handler throws | Transaction rolls back; exception reported | Danger toast "Tindakan gagal, tidak ada data yang berubah." |
| No rows selected | Action refused | Warning toast "Pilih minimal satu data." |
| Export over limit | Refused before the query runs | "Data terlalu besar untuk diekspor. Persempit filter terlebih dahulu." |
| Destructive bulk action | Confirm modal with row count | "N data akan …" + confirm |

All user-facing text uses PTPN IV Regional V business terms and never references internal documentation names (`AGENTS.md` rule 6).

## 7. Migration plan (one PR per step)

1. **Foundation** — `Column`, `Filter`, `BulkAction`, `TableState`, `QueryPipeline`, `WithDataTable`, `DataTableExport`, Blade components; tested via a test-only fixture component.
2. **`TrainingTable`, `EvaluationTable`, `JobHistoryTable`** — closes the sort-whitelist gap.
3. **`Master/AuditHistory`, then `MutasiHistory`** — URL state, filters, export.
4. **`JobVacancyTable`** — first real bulk selection.
5. **Employees directory → Livewire** — preserves query strings `search`, `level`, `jabatan`, `unit_kerja`, `rm_level`, `golongan`, `status_penugasan`, `status_khusus`, `tahun_masuk`, `bulan_masuk`, `sort`, `direction`; export via `EmployeeExportService` with `MasterAuditLog` audit. Controller `index` becomes a thin page hosting the component.
6. **Domain screens** (`FormasiEditor`, `ManPowerPlanning`, `MutasiProcess`, `MutasiChainProcess`, import batches) — adopt toolbar/head/pagination/selection pieces where they fit; no logic rewrite.
7. **Guidelines** — update `ENGINEERING.md` (Livewire table requirements) and `DESIGN.md` §6/§7.5 to make `WithDataTable` + `x-admin.table.*` mandatory; extend `ViewDesignGuardTest` to flag admin `<table>` markup not built on `x-admin.table.head`.

## 8. Testing

One test class per system under test (`AGENTS.md` rule 5):

- `tests/Unit/DataTable/QueryPipelineTest.php` — search escapes `%`/`_`; search covers only `searchable()` columns; non-whitelisted sort ignored; direction coerced; tiebreak applied; filter `apply` called only for valid values.
- `tests/Feature/DataTable/WithDataTableTest.php` (fixture component + fixture model/table) — page reset on search/filter/per-page change; per-page clamp; tampered `sortField`/filter via URL ignored; selection: page, all-matching, cleared on filter change, kept across pages; bulk: unknown action 403, missing ability 403, rollback on exception, audit row written, foreign keys dropped; export: same rows as screen, selection respected, limit enforced, audit row written.
- `tests/Feature/DataTable/DataTableExportTest.php` — headings/mapping from `exportable()` columns, footer metadata, chunked query.
- Migrated modules — each existing test class gains the cases it needs; Employees gets `EmployeeDirectoryTest` asserting legacy URLs still filter identically.
- Every PR: `./vendor/bin/pint` and the full PHPUnit suite pass.

## 9. Deltas found while planning

1. **Feedback is an in-card notice, not a toast.** No global listener exists for the `toast` event, so the trait exposes `$tableNotice` and `x-admin.table.notice` renders it inside the table card (`role="status"` for success, `role="alert"` for error/warning), matching `x-admin.flash`. All copy in §6 is unchanged.
2. **Export neutralises formula prefixes** (`=`, `+`, `-`, `@`, tab, CR → prefixed with `'`), as the existing exports already do.
3. **Embedded tables opt out of URL sync and use their own page name** (`syncsUrl()`, `paginationName()`), because three history tables share the employee profile page.
4. **Audit detail records `pencarian` as a boolean**, never the search term (it is often a name or NIK).
5. **LIKE escaping uses `!` with `ESCAPE '!'`** so the same SQL works on SQLite (tests) and MySQL (production).
6. **`dateRange` filters and Shift/Ctrl range selection** are built in the step-3 and step-4 plans respectively, with their first consumers.
