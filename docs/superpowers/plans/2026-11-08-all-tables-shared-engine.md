# All Browsable Tables on the Shared Table Engine — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Every browsable list in HRIS uses the same table components and behaviour as Riwayat Mutasi and the Employees directory. Lists backed by Eloquent run on `WithDataTable`; collection-backed lists use the same presentational components.

**Architecture:** Each Eloquent-backed list page gets one Livewire component using `App\Livewire\Concerns\WithDataTable`, declaring `baseQuery()`, `columns()`, `filters()`, `defaultSort()` and `tableLabel()`. Its Blade view follows `resources/views/livewire/admin/master/audit-history.blade.php` exactly: card → toolbar → header → table with head/skeleton/empty-state → pagination. Controllers keep routes, authorisation and page chrome, and mount the component. Collection-backed lists (job vacancy, Formasi editor) keep their data code and swap their hand-built toolbar/header for `x-admin.table.toolbar` and `x-admin.table.header`.

**Tech Stack:** Laravel 13, Livewire 4.4.6, Alpine.js, Tailwind tokens (`DESIGN.md`), PHPUnit 11, Laravel Pint, maatwebsite/excel.

**Spec:** `docs/superpowers/specs/2026-11-08-all-tables-shared-engine-design.md` (including its "Revisions after reading the code" block). Guidelines: `AGENTS.md` (rules 1–6), `DESIGN.md` §6, §7.5, §8.4; `ENGINEERING.md` §2.3; `SECURITY.md`. Reference component and view: `app/Livewire/Admin/Master/AuditHistory.php` and `resources/views/livewire/admin/master/audit-history.blade.php`. Reference test: `tests/Feature/MasterAuditLogTest.php`.

## Global Constraints

- PHP `^8.3` features only. Code, docblocks and commits are in English. UI text uses Indonesian business wording, and rendered output never contains `AGENTS.md`, `DOMAIN.md`, `DESIGN.md`, `ENGINEERING.md`, `SECURITY.md` or `SSOT`.
- No hand-built toolbar, header or footer markup on a converted list (`DESIGN.md` §7.5). The only allowed pieces are `x-admin.table.card`, `x-admin.table.toolbar`, `x-admin.table.header`, `x-admin.table.head` (or `x-admin.table.th` in the collection-backed lists), `x-admin.table.empty-state`, `x-admin.table.skeleton` and `x-admin.table.pagination`.
- Search goes through the shared search input: 300ms debounce, max 100 characters. Filters apply instantly (`wire:model.live`). A filter driven by KPI cards or tabs is `withoutControl()`: it appears only as a chip.
- Queries use bound parameters only (`QueryPipeline::whereLikeAny`, `where`). Sorting only on declared `sortable()` columns. Embedded tables hold their parent key in a `#[Locked]` property.
- Exports go through the trait's `export()`, which handles the gate (`export-data`), the row limit and the audit entry. A module with a business export format overrides `buildExport()`; it never adds its own download route.
- Row actions that write (delete) keep their current authorisation and transaction behaviour. Moved code keeps `DB::transaction()` where it has it today.
- Existing URL keys keep working: `search`, `sort`, `direction`, `per_page`, trainings `start_date`/`end_date`, and employees `jabatan`, `level`, `unit_kerja`, `rm_level`, `golongan`, `status_penugasan`, `status_khusus`, `tahun_masuk`, `bulan_masuk`.
- Header nouns: `Penilaian`, `Pelatihan`, `Peserta`, `Impor`, `Lowongan`, `Jabatan`, `Unit Kerja`, `Pegawai`. Numbers use the Indonesian thousands separator (the header already does this).
- One test class per system under test. Run `./vendor/bin/pint` before every commit. Full suite: `php artisan test` (10-minute timeout; last run 757 passed, 7 deprecated).
- Commit trailer: `Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>`.

## Review Focus

1. **Old bookmarks:** `/admin/employees?unit_kerja=X&sort=jabatan&direction=desc&page=2` and `/admin/trainings?start_date=…&end_date=…` open with the same filters, sort and page as before → Task 8 `test_legacy_query_string_is_applied`, Task 3 `test_legacy_date_range_query_is_applied`.
2. **KPI totals agree with the table:** the Trainings cards (man hours, ticket cost, investment) are computed from the same filtered query as the rows, including search → Task 3 `test_summary_follows_search_and_date_filters`.
3. **Tampered client state:** an out-of-list value for a select filter (job vacancy `severityFilter=purple`, employees `status_khusus=drop`) is ignored, not passed to SQL → Task 6 `test_unknown_severity_is_ignored`, Task 8 `test_unknown_filter_values_are_ignored`.
4. **Bulk delete on Employees** deletes only rows that match the current filters and selection, removes stored photos, and runs inside one transaction → Task 9 `test_bulk_delete_is_limited_to_matching_selection`.
5. **Two toolbars on one screen** (Formasi editor seat and unit lists): clearing or resetting one does not touch the other's search or filters → Task 7 `test_seat_reset_keeps_unit_filters`.

---

## File Map

| File | Responsibility |
|---|---|
| `resources/views/components/admin/table/toolbar.blade.php` | + props `search-model`, `reset-action` |
| `app/Livewire/Admin/EvaluationIndexTable.php` + `resources/views/livewire/admin/evaluation-index-table.blade.php` (new) | Manajemen Penilaian list |
| `app/Livewire/Admin/TrainingIndexTable.php` + view (new) | Manajemen Pelatihan list + summary cards |
| `app/Livewire/Admin/TrainingParticipantTable.php` + view (new) | participants on a training's page |
| `app/Livewire/Admin/Employees/ImportBatchTable.php` + view (new) | import history list |
| `app/Livewire/Tables/JobVacancyTable.php` + view | shared presentational components on collection data |
| `resources/views/livewire/admin/master/formasi-editor.blade.php`, `app/Livewire/Admin/Master/FormasiEditor.php` | shared toolbar/header for both lists |
| `resources/views/admin/employees/import-batches/show.blade.php`, `resources/views/user/trainings/show.blade.php` | shared card/header/column styling only |
| `app/Livewire/Admin/Employees/EmployeeTable.php` + view (new), `resources/views/admin/employees/index.blade.php`, `app/Http/Controllers/Admin/EmployeeController.php` | Employees directory list |
| Controllers `EvaluationController`, `TrainingController`, `EmployeeImportBatchController` | thin `index`/`show` that mount the components |
| `DESIGN.md`, `ENGINEERING.md` | §6/§7.5 and §2.3 rows, change log `v3.19` |

---

### Task 1: Toolbar supports a custom search property and reset action

**Files:** Modify `resources/views/components/admin/table/toolbar.blade.php`; Test `tests/Feature/DataTable/TableComponentsTest.php`.

**Interfaces:** Produces the toolbar props `searchModel` (string, default `'search'`) and `resetAction` (string, default `'resetFilters'`). They are used for the search input `model`, the search chip's `$set('{searchModel}', '')`, and the Reset Filter `wire:click`. Consumed by Tasks 6 and 7.

- [ ] **Step 1: Write failing tests** `test_toolbar_uses_custom_search_model_and_reset_action` (render with `search-model="seatQuery"`, `reset-action="resetSeatFilters"`, `search="abc"`, `has-active-filters`; assert HTML contains `wire:model.live.debounce.300ms="seatQuery"`, `$set('seatQuery', '')` and `wire:click="resetSeatFilters"`, and does not contain `wire:click="resetFilters"`). Also `test_toolbar_defaults_to_search_and_reset_filters`.
- [ ] **Step 2: Run** `php artisan test --filter=TableComponentsTest`. Expected: the new test FAILS.
- [ ] **Step 3: Add the two props** and use them in the three places. Document them in the component's header comment block.
- [ ] **Step 4: Run** the same command. Expected: PASS.
- [ ] **Step 5: Commit** `feat(table): let the toolbar drive a custom search property and reset action`

### Task 2: Manajemen Penilaian on the shared engine

**Files:** Create `app/Livewire/Admin/EvaluationIndexTable.php` and `resources/views/livewire/admin/evaluation-index-table.blade.php`; Modify `app/Http/Controllers/Admin/EvaluationController.php` (`index`), `resources/views/admin/evaluations/index.blade.php`; Test `tests/Feature/EvaluationIndexTableTest.php` (new).

**Interfaces:** `EvaluationIndexTable` (no mount params). `baseQuery()` returns `Evaluation::query()->with('employee')`. `tableLabel()` returns `'Daftar Penilaian'`. `defaultSort()` returns `['tanggal_pelaksanaan_asesmen', 'desc']`. `defaultPerPage()` returns 20. `exportName()` returns `null`, because the module has no export today.

- Columns: `pegawai` "Pegawai" (NIK and name in one cell; searchable on `employee_nik` and the related `employee.nama` via `whereHas` + `whereLikeAny`), `jabatan_unit` "Jabatan & Unit" (searchable on the related `jabatan`, `unit_kerja`), `nilai_tertimbang` "Nilai Tertimbang" sortable mono, `hasil_skor` "Hasil Skor", `kategori_9box` "Kategori" sortable, `tanggal_pelaksanaan_asesmen` "Periode Asesmen" sortable, `aksi` "Aksi" centred. Keep the cell markup and the show/edit/delete actions from the current view; the delete form keeps `@csrf @method('DELETE')` and its confirmation.
- Filters: `Filter::select('kategori', 'Kategori 9-Box', <distinct kategori_9box values>)->icon('fas fa-table-cells')->allLabel('Semua Kategori')`, `Filter::select('bidang', 'Bidang Tugas', <distinct bidang_tugas>)->icon('fas fa-sitemap')`, `Filter::dateFrom('from', 'Dari Tanggal', 'tanggal_pelaksanaan_asesmen')`, `Filter::dateTo('to', 'Sampai Tanggal', 'tanggal_pelaksanaan_asesmen')`, each with `#[Url(except: '')]`.
- Header: `title="Daftar Penilaian"`, `noun="Penilaian"`. Empty states: filtered → "Tidak ada penilaian yang sesuai dengan pencarian atau filter." with Reset Filter; none → "Belum ada data penilaian." Search placeholder: "Cari NIK, nama, jabatan, atau unit kerja...".
- Page: keep the page header, the "Tambah Penilaian" button and the success flash in `index.blade.php`, and replace the card+table with `<livewire:admin.evaluation-index-table />`. The controller `index()` returns the view without querying.

- [ ] **Step 1: Write failing tests:** `test_index_page_mounts_the_table`, `test_lists_evaluations_newest_first_20_per_page`, `test_header_shows_title_and_count` (`3 Penilaian`, `Daftar Penilaian`), `test_search_matches_nik_name_and_unit`, `test_kategori_filter_and_chip`, `test_date_range_filter`, `test_tampered_sort_field_is_ignored`, `test_zero_results_show_reset`, `test_delete_action_still_works` (DELETE route, record gone), `test_no_internal_document_names_rendered`.
- [ ] **Step 2: Run** `php artisan test --filter=EvaluationIndexTableTest`. Expected: FAIL (class not found).
- [ ] **Step 3: Implement** the component, view, controller and page change.
- [ ] **Step 4: Run** the same command, then `php artisan test --filter=Evaluation`. Expected: PASS.
- [ ] **Step 5: Commit** `feat(table): put the evaluations list on the shared table engine`

### Task 3: Manajemen Pelatihan on the shared engine, with summary from the same query

**Files:** Create `app/Livewire/Admin/TrainingIndexTable.php` + `resources/views/livewire/admin/training-index-table.blade.php`; Modify `TrainingController::index`, `resources/views/admin/trainings/index.blade.php`; Test `tests/Feature/TrainingIndexTableTest.php` (new).

**Interfaces:** Properties `#[Url(as: 'start_date', except: '')] public string $startDate`, `#[Url(as: 'end_date', except: '')] public string $endDate`. Filters `Filter::dateFrom('startDate', 'Dari Tanggal', 'tanggal_mulai')` and `Filter::dateTo('endDate', 'Sampai Tanggal', 'tanggal_mulai')`, plus `Filter::select('jenis', 'Jenis', …)` and `Filter::select('metode', 'Metode', …)` from the distinct column values. `baseQuery()` returns `Training::query()->withCount('employees')`. Default sort `['tanggal_mulai', 'desc']`, 20 per page. A public method `summary(): array{man_hours: float, tiket: float, biaya: float, karyawan: ?int}`, computed from `(clone $this->filteredQuery())->reorder()` sums. `karyawan` sums `EmployeeStatistic.total_karyawan` between `startOfMonth(startDate)` and `endOfMonth(endDate)`, only when both dates are set (today's rule). `exportName()` returns `'data_pelatihan'`, and `buildExport()` returns `new TrainingsExport(['start_date' => $this->startDate, 'end_date' => $this->endDate])`, keeping the business format.
- If `TrainingsExport` cannot take the search term, make the ruling in the ledger: either extend `TrainingsExport` to accept an optional `Builder`, or hide the export while a search term is active. Choose the first unless it changes the export's columns.
- Columns: `judul` "Judul" sortable searchable, `jenis` "Jenis", `metode` "Metode", `bidang` "Bidang" searchable, `tanggal_mulai` "Tanggal Mulai" sortable, `jumlah_man_hours` "Man Hours" sortable mono, `employees_count` "Peserta" sortable mono, `aksi`.
- The summary cards (`x-admin.kpi-card`, the same three or four cards and wording as today) move into the component view above the card, so they rerender with the filters. Header: `title="Daftar Pelatihan"`, `noun="Pelatihan"`, export on. The old GET filter form and the `route('admin.trainings.export')` button are removed from the page. The route stays registered for existing links.

- [ ] **Step 1: Write failing tests:** `test_legacy_date_range_query_is_applied` (visit `?start_date=2026-01-01&end_date=2026-03-31` → only trainings in range, inputs show the dates), `test_summary_follows_search_and_date_filters`, `test_total_karyawan_only_with_both_dates`, `test_header_title_count_and_export`, `test_export_is_gated_and_audited` (non-admin gets the no-permission notice; admin download is named `data_pelatihan_*.xlsx`), `test_search_and_sort`, `test_zero_results_show_reset`.
- [ ] **Step 2: Run** `php artisan test --filter=TrainingIndexTableTest`. Expected: FAIL.
- [ ] **Step 3: Implement.**
- [ ] **Step 4: Run** the same command and `php artisan test --filter=Training`. Expected: PASS.
- [ ] **Step 5: Commit** `feat(table): put the trainings list and its summary on the shared table engine`

### Task 4: Training participants on the shared engine

**Files:** Create `app/Livewire/Admin/TrainingParticipantTable.php` + view; Modify `TrainingController::show`, `resources/views/admin/trainings/show.blade.php`; Test `tests/Feature/TrainingParticipantTableTest.php` (new).

**Interfaces:** `#[Locked] public int $trainingId` and `mount(int $trainingId)`. `baseQuery()` returns `Employee::query()->whereHas('trainings', fn ($q) => $q->whereKey($this->trainingId))` with the pivot columns the current view reads; check the `Training::employees()` relation for the pivot names. `rowKey()` is `nik`. `syncsUrl()` returns `false`, `paginationName()` returns `'peserta'`, 20 per page, default sort `['nama', 'asc']`. Columns: `pegawai` "Pegawai" (NIK + nama, searchable on both), `jabatan` "Jabatan" searchable, `unit_kerja` "Unit" searchable, `level` "Level" sortable, `sertifikat` "Sertifikat", `aksi`. Filter: `Filter::select('level', 'Level', …)`. Header: no title, `noun="Peserta"`, no export. Edit and delete actions keep their current routes and forms. Search shortcut: `null` (embedded table).

- [ ] **Step 1: Write failing tests:** `test_lists_only_this_trainings_participants`, `test_training_id_cannot_be_changed_from_the_client`, `test_header_noun_without_title_or_export`, `test_search_and_level_filter`, `test_remove_participant_still_works`.
- [ ] **Step 2: Run** `php artisan test --filter=TrainingParticipantTableTest`. Expected: FAIL.
- [ ] **Step 3: Implement.**
- [ ] **Step 4: Run** the same command and `--filter=Training`. Expected: PASS.
- [ ] **Step 5: Commit** `feat(table): put the training participants list on the shared table engine`

### Task 5: Import history on the shared engine; read-only lists get the shared styling

**Files:** Create `app/Livewire/Admin/Employees/ImportBatchTable.php` + `resources/views/livewire/admin/employees/import-batch-table.blade.php`; Modify `EmployeeImportBatchController::index`, `resources/views/admin/employees/import-batches/index.blade.php`, `resources/views/admin/employees/import-batches/show.blade.php`, `resources/views/user/trainings/show.blade.php`; Test `tests/Feature/ImportBatchTableTest.php` (new) and the existing import test class `tests/Feature/EmployeeImportTest.php`.

**Interfaces:** `#[Url(except: '')] public string $status` uses the controller's `TABS`/`TAB_STATUSES` keys (move both constants onto the component, and have the controller refer to them). `Filter::select('status', 'Status', <tab labels>)->withoutControl()`, applying `whereIn('status', TAB_STATUSES[$value])`. The tabs above the table call `$set('status', key)`, show the same counts as today, and use `aria-current` on the active tab. `baseQuery()` returns `EmployeeImportLog::query()->withCount('changes')`. Default sort `['id', 'desc']`, 15 per page. Columns: `file_name` "Berkas" searchable, `user_name` "Pengaju" searchable, `hasil` "Hasil", `status` "Status", `aksi`. Header: `title="Riwayat & Persetujuan"`, `noun="Impor"`, no export. The governance notes (`requiresApproval`, undo window, approval TTL) stay on the page.
- Controller: if the request has `q`, redirect to the same route with `search` set to it (and `status` kept), so old links work.
- `import-batches/show.blade.php`: wrap the review table in `x-admin.table.card`, put `<x-admin.table.header title="Pratinjau Perubahan" :paginator="$reviewRows" noun="Baris" />` above it, and use the shared header cell classes from `x-admin.table.th`. Keep its existing pagination links, because this page is not Livewire.
- `user/trainings/show.blade.php`: `x-admin.table.card` and shared `th`/`td` classes only.

- [ ] **Step 1: Write failing tests:** `test_status_tabs_filter_and_show_counts`, `test_status_is_a_chip_not_a_panel_field`, `test_legacy_q_parameter_redirects_to_search`, `test_unknown_status_is_ignored`, `test_header_title_and_count`, `test_search_file_or_submitter`. In `EmployeeImportTest`, update any assertion on the old markup (search for `batch-search` and `name="q"`).
- [ ] **Step 2: Run** `php artisan test --filter="ImportBatchTableTest|EmployeeImportTest"`. Expected: the new tests FAIL.
- [ ] **Step 3: Implement.**
- [ ] **Step 4: Run** the same command. Expected: PASS.
- [ ] **Step 5: Commit** `feat(table): put the import history on the shared table engine`

### Task 6: Job vacancy list uses the shared components

**Files:** Modify `app/Livewire/Tables/JobVacancyTable.php`, `resources/views/livewire/tables/job-vacancy-table.blade.php`; Test `tests/Feature/JobVacancyTableTest.php` (new).

**Interfaces:** Consumes Task 1's toolbar props (defaults). Keep `search`, `selectedUnit`, `severityFilter` and `perPage`. Add `#[Url]` with the keys `search`, `unit`, `severity` (the old keys were the property names: also accept `selectedUnit`/`severityFilter` via `mount()` for old links) and these methods:
- `filters(): array`: `Filter::select('selectedUnit', 'Unit Kerja', <vacant units>)->icon('fas fa-building')`, `Filter::select('severityFilter', 'Urgensi', ['red' => 'Kritis (> 90 hari)', 'yellow' => 'Sedang (30–90 hari)', 'green' => 'Baru (< 30 hari)'])->withoutControl()`, because the KPI cards drive severity.
- `activeFilterValues(): array` and `hasActiveFilters(): bool`.
- `updated()`, which resets an unknown `severityFilter` or `selectedUnit` to `''` (via `Filter::allows`), trims `search` to 100 characters and resets the page.
- `resetFilters()` (exists), and `perPageOptions()` returning `[10, 25, 50]`.
The view uses `x-admin.table.card` / `toolbar` / `header` (`title="Daftar Jabatan Lowong"`, `noun="Lowongan"`) / `x-admin.table.th` / `empty-state` / `pagination`. Keep the KPI cards and the "Opsi Pengisian" actions. Remove the hand-built search input, selects and `$vacancies->links()`.

- [ ] **Step 1: Write failing tests:** `test_renders_shared_toolbar_and_header`, `test_unit_filter_and_chip`, `test_kpi_card_sets_severity_chip_only`, `test_unknown_severity_is_ignored`, `test_reset_clears_search_unit_and_severity`, `test_legacy_query_keys_are_applied`.
- [ ] **Step 2: Run** `php artisan test --filter=JobVacancyTableTest`. Expected: FAIL.
- [ ] **Step 3: Implement.**
- [ ] **Step 4: Run** the same command. Expected: PASS.
- [ ] **Step 5: Commit** `feat(table): give the job vacancy list the shared table components`

### Task 7: Formasi editor lists use the shared toolbar and header

**Files:** Modify `resources/views/livewire/admin/master/formasi-editor.blade.php` (lines ~50–68 and ~184–200, the toolbars; above lines 69 and 201, the headers), `app/Livewire/Admin/Master/FormasiEditor.php`; Test `tests/Feature/FormasiEditorTest.php`.

**Interfaces:** Consumes Task 1's `search-model`/`reset-action`. Add these to `FormasiEditor`:
- `seatFilters(): array` (`Filter::select('rmFilter', 'RM Band', …)`, `('bidangFilter', 'Bidang', …)`, `('seatStatusFilter', 'Status', …)`, using the same option lists the view passes today), `unitFilters(): array` (`wilayahFilter`, `statusFilter`), and `seatFilterValues()` / `unitFilterValues()`.
- `resetSeatFilters()`, which clears `seatQuery`, `rmFilter`, `bidangFilter` and `seatStatusFilter` and resets the seat pagination, and `resetUnitFilters()`, which clears `unitQuery`, `wilayahFilter` and `statusFilter` and resets the unit pagination. Reuse the existing reset methods if they already do exactly this, and record that in the ledger.
Headers: seat list `title="Formasi Jabatan — {unit}"` and `noun="Jabatan"`, unit list `title="Daftar Unit Kerja"` and `noun="Unit Kerja"`. No export on either. Keep the existing `x-admin.table.th` sorting and `x-admin.table.pagination`.

- [ ] **Step 1: Write failing tests** in `FormasiEditorTest`: `test_unit_list_uses_shared_toolbar_and_header` (`N Unit Kerja`, `Filter Lanjutan`), `test_seat_list_uses_shared_toolbar_and_header` (`N Jabatan`), `test_seat_reset_keeps_unit_filters`.
- [ ] **Step 2: Run** `php artisan test --filter=FormasiEditorTest`. Expected: the new tests FAIL and the existing ones PASS.
- [ ] **Step 3: Implement.** Change the toolbar/header markup only; the modals and row actions are untouched.
- [ ] **Step 4: Run** the same command. Expected: PASS.
- [ ] **Step 5: Commit** `feat(table): give the formasi editor lists the shared table components`

### Task 8: Employees directory component — search, filters, sort, pagination

**Files:** Create `app/Livewire/Admin/Employees/EmployeeTable.php` + `resources/views/livewire/admin/employees/employee-table.blade.php`; Modify `EmployeeController::index` (keep only the global KPI metrics, `expiredSanksiEmployees` and the page view), `resources/views/admin/employees/index.blade.php` (replace the `<form>` toolbar, panel, Filter Aktif row, header bar, table and pager, roughly lines 355–end of the table block, with `<livewire:admin.employees.employee-table />`; keep the KPI cards and the expired-sanction notice); Test `tests/Feature/EmployeeTableTest.php` (new), `tests/Feature/EmployeePeriodFilterTest.php`.

**Interfaces:**
- Properties, all `#[Url(except: '')]`: `jabatan`, `level`, `unit_kerja` as `$unitKerja` (`as: 'unit_kerja'`), `rm_level` as `$rmLevel`, `golongan`, `status_penugasan` as `$statusPenugasan`, `status_khusus` as `$statusKhusus`, `tahun_masuk` as `$tahunMasuk`, `bulan_masuk` as `$bulanMasuk`.
- `baseQuery()` returns `Employee::query()->with('concurrentJobs')`. `rowKey()` is `nik`. Default sort `['nama', 'asc']`, 20 per page. Searching uses the existing `Employee::search()` scope through `Column::make('pegawai', …)->searchable(fn ($q, $t) => $q->search($t))`. Sortable columns are exactly `nama`, `jabatan`, `rm_level`, `level`, `golongan` (today's whitelist).
- Filters: the same option sources as the controller today (distinct values, `Position::RM_LEVEL_LIST`, available years, the months map), with `apply()` closures moved verbatim from `EmployeeController::index` lines 41–95. `level` is `withoutControl()`, because the KPI cards drive it, as the old "Hapus Filter Tambahan" link showed. The KPI cards on the page link to `?level=…`, which the component reads.
- Header: `title="Direktori Pegawai"` and `noun="Pegawai"`. Export is added in Task 10.
- Columns and cells: keep today's order and the domain badges (`test_employee_table_renders_correct_columns_order_and_domain_badges` must stay green unchanged except for selectors that moved).

- [ ] **Step 1: Write failing tests:** `test_legacy_query_string_is_applied` (`?unit_kerja=X&sort=jabatan&direction=desc&page=2`), `test_unknown_filter_values_are_ignored`, `test_tampered_sort_field_is_ignored`, `test_level_is_a_chip_not_a_panel_field`, `test_header_title_and_count`, `test_zero_results_show_reset`. Port each `EmployeePeriodFilterTest` filter test to assert through the page (`get(route(...))->assertSee`, which still works because the component renders on first load), and keep them in that class.
- [ ] **Step 2: Run** `php artisan test --filter="EmployeeTableTest|EmployeePeriodFilterTest"`. Expected: the new tests FAIL.
- [ ] **Step 3: Implement.**
- [ ] **Step 4: Run** the same command and `php artisan test --filter=Employee`. Expected: PASS.
- [ ] **Step 5: Commit** `feat(table): move the employees directory list onto the shared table engine`

### Task 9: Employees directory — export and bulk delete through the engine

**Files:** Modify `app/Livewire/Admin/Employees/EmployeeTable.php` + view, `app/Http/Controllers/Admin/EmployeeController.php` (`bulkDestroy`); Test `tests/Feature/EmployeeTableTest.php`, `tests/Feature/EmployeeExportTest.php`, `tests/Feature/EmployeeCrudTest.php`.

**Interfaces:**
- `exportName()` returns `'data_pegawai'`. `buildExport()` returns `new EmployeesExport($this->activeFilterValues() + ['search' => $this->search, 'sort' => $this->sortField, 'direction' => $this->sortDirection], $user)`; check the filter keys `EmployeeExportService::exportExcel()` passes today and use the same keys. The `admin.employees.export` route stays for existing links.
- `bulkActions()` returns `[BulkAction::make('destroy', 'Hapus')->icon('fas fa-trash')->ability(<the ability bulkDestroy uses today, or 'delete-employee' if none; ledger it>)->danger('Hapus {n} data pegawai terpilih? Tindakan ini tidak dapat dibatalkan.')->successMessage('{n} data pegawai terpilih berhasil dihapus.')]`. The `protected function bulkDestroy(Builder $targets): void` handler moves the photo-delete + delete loop from the controller inside `DB::transaction()`. The controller's `bulkDestroy` becomes a call to the same private service method, or is removed with its route if no other caller exists. Check with grep and ledger the result.

- [ ] **Step 1: Write failing tests:** `test_bulk_delete_is_limited_to_matching_selection` (3 employees, 2 match the filter, select all matching → exactly 2 deleted, their photos removed), `test_bulk_delete_requires_permission`, `test_export_uses_current_filters_and_is_audited`, `test_export_over_limit_shows_warning`.
- [ ] **Step 2: Run** `php artisan test --filter="EmployeeTableTest|EmployeeExportTest|EmployeeCrudTest"`. Expected: the new tests FAIL.
- [ ] **Step 3: Implement.**
- [ ] **Step 4: Run** the same command. Expected: PASS.
- [ ] **Step 5: Commit** `feat(table): run employee export and bulk delete through the shared table engine`

### Task 10: Docs, full suite, manual check

**Files:** `DESIGN.md` (§6: toolbar props `search-model`, `reset-action`; §7.5: add "Daftar berbasis koleksi (mis. Lowongan Jabatan, Formasi) memakai komponen tampilan yang sama tanpa trait"; change log `v3.19` dated 08/11/2026 listing the screens converted), `ENGINEERING.md` §2.3 (list of table components: the five existing ones plus `EvaluationIndexTable`, `TrainingIndexTable`, `TrainingParticipantTable`, `ImportBatchTable`, `EmployeeTable`; collection-backed lists use the presentational components only).

- [ ] **Step 1: Run** `grep -rn "<table" resources/views`. Expected: every hit is a converted list, a shared component, or a spec-listed out-of-scope grid/preview.
- [ ] **Step 2: Update the docs.**
- [ ] **Step 3: Run** `./vendor/bin/pint`, then `php artisan test`. Expected: PASS, 0 failures.
- [ ] **Step 4: Manual check (human):** every converted screen at 375px and 1366px beside Riwayat Mutasi. Check that the search row, panel, Filter Aktif row, header bar and pagination look identical.
- [ ] **Step 5: Commit** `docs(table): record every list on the shared table components`
