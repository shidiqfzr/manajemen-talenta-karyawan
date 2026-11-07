# All Browsable Tables on the Shared Table Engine — Design

**Date:** 2026-11-08 · **Status:** draft for review · **Branch:** `feat/shared-table-employee-style`

## 1. Intent

The user wants every table in HRIS to look and behave the same, built from one set of components, following enterprise HRIS practice and the project guidelines (`AGENTS.md` → `DESIGN.md` §6/§7.5, `ENGINEERING.md` §2.3, `SECURITY.md`).
Five tables are already on the shared engine (Riwayat Mutasi, Riwayat Perubahan, and the Pelatihan / Evaluasi / Riwayat Jabatan tabs on the employee profile) and the Employees directory matches their look.

**Success:** every *browsable list* (a table people search, filter, sort, page through or export) is a Livewire component using `WithDataTable` + `x-admin.table.{toolbar,header,head,card,...}`, with the same search row, Filter Lanjutan panel, Filter Aktif chips, header bar (count, range, Ekspor Excel) and pagination. No hand-built toolbar/header/footer markup remains on those screens (`DESIGN.md` §7.5).

**Out of scope (agreed):** editing grids and modal previews — MPP supply plan editor, MPP planning, mutasi review modal, import preview/diff/column-guide modals, statistics, and `import-batches/partials/skipped-rows`. They keep their layout; they only get the shared table styling tokens where a trivial class swap suffices (no new behaviour).

Confirmed by the user (2026-11-08): the Employees directory is in scope for **behaviour** parity too (see §4, phase D), not only looks.

## 2. Inventory and target

| # | Screen (view) | Today | Target |
|---|---|---|---|
| 1 | Manajemen Penilaian `admin/evaluations/index` | Controller + `paginate(20)`, own card/header, no search/sort | New Livewire `Admin\EvaluationIndexTable` (search NIK/nama/lembaga, sort, filter periode, header "N Penilaian") |
| 2 | Manajemen Pelatihan `admin/trainings/index` | Controller, date-range GET form, KPI cards, `paginate(20)` | New Livewire `Admin\TrainingIndexTable`; date range becomes shared `Dari/Sampai Tanggal` filter; KPI totals computed from the same filtered query (one source) |
| 3 | Peserta pelatihan `admin/trainings/show` | Own table, pager | Livewire `Admin\TrainingParticipantTable` (embedded, no title/export unless existing export applies) |
| 4 | Riwayat impor `import-batches/index` | GET form, status tabs, `paginate(15)` | Livewire `Admin\ImportBatchTable`; status tabs stay as KPI-style controls feeding a `withoutControl()` filter |
| 5 | Detail impor `import-batches/show` | Own table, pager | Livewire `Admin\ImportBatchChangeTable` |
| 6 | Lowongan jabatan `livewire/tables/job-vacancy-table` | Livewire but **hand-rolled** (`WithPagination`, own search/selects) | Migrate to `WithDataTable` + shared toolbar/header (keeps service-built rows via a custom data source if `baseQuery()` cannot express it) |
| 7 | Formasi editor — unit list and seat table `livewire/admin/master/formasi-editor` | Livewire, own toolbar/pager, 21 `wire:` hooks | Use `x-admin.table.toolbar/header/head`; keep editor-specific row actions |
| 8 | Employees directory `admin/employees/index` | Controller + GET form, shared look but own markup | Phase D (see §4) |
| 9 | User-side training `user/trainings/show` | Own small table | Shared table markup only (read-only, no toolbar) |

**Revisions after reading the code (2026-11-08, while planning):**
- #5 and #9 show a paginated *approval preview* (`governance->review()` sample rows, an array) and a short read-only list. They have no search, sort or filter to share, so they get the shared card, header bar and column styling only. They do not get the engine.
- #6 (rows built by `JobVacancyService` from a query builder plus computed severity) and #7 (computed seat and unit statistics) are collection-backed. `WithDataTable` is Eloquent-builder-only, so these keep their in-memory data and adopt the **presentational** shared components (`toolbar`, `header`, `pagination`), with filters declared as `Filter` objects so the panel, chips and Reset Filter are identical. To support two lists in one component (#7), the toolbar gains `search-model` (default `search`) and `reset-action` (default `resetFilters`) props.
- URL keys stay as they are today wherever bookmarks exist: trainings `start_date`/`end_date`, employees `search`, `sort`, `direction` and every filter key. The import batches search moves from `q` to the shared `search` key, and `?q=` redirects to it.

## 3. Approach

Chosen: **one Livewire component per list, all on `WithDataTable`** — the pattern already proven by `EvaluationTable`/`TrainingTable`/`JobHistoryTable`. Reasons: single engine for search (300ms debounce), validated URL state, sort whitelist, export gate, loading states and accessibility; one test class per SUT.

Rejected: (a) a Blade-only shared partial for controller pages — duplicates query/sort/filter logic and breaks "one engine"; (b) forcing editing grids onto the engine — adds search/filter/export that make no sense there.

Cross-cutting rules (apply to every conversion):
- Controllers keep `authorize`/routes and render a thin view that mounts the component; no query logic left in controllers.
- Queries via `QueryPipeline` with parameter binding; sort fields whitelisted from `Column` declarations (SECURITY.md OWASP).
- Employee-identifying data (NIK, evaluation scores) stays server-side; locked props for embedded tables as in `EvaluationTable`.
- Exports use the shared audited export path and limit; none added where the module has no export today.
- UI text Indonesian business wording; no internal doc names or `SSOT` in rendered output (AGENTS.md rule 6). Code, comments, commits English.
- Any multi-table write stays inside `DB::transaction()` (none expected: all conversions are read paths; existing row actions are moved, not rewritten).
- Pint before every commit; one test class per component, plus existing `TableComponentsTest`/`WithDataTableTest` for shared behaviour. Existing controller feature tests are updated to assert via Livewire where the HTML moved.

## 4. Phasing (each phase ends green: Pint + full suite, own commits)

- **A — Straightforward list pages:** #1 Evaluations, #2 Trainings, #3 participants, #4 import batches, #5 batch detail, #9 user training.
- **B — Migrate hand-rolled Livewire:** #6 Job vacancy.
- **C — Formasi editor lists:** #7 (largest file, 787 lines; touch only toolbar/header/head markup).
- **D — Employees directory (#8):** highest risk (953-line view, GET-based filters, KPI cards, bulk/export, URL-shared filters, existing import/export tests). Port to a Livewire `EmployeeTable` only after A–C, behind the same URL query keys so bookmarks keep working; if parity cannot be proven by tests, stop and report instead of shipping a regression.
- **Docs:** `DESIGN.md` §6/§7.5 + change-log (v3.19+), `ENGINEERING.md` §2.3 list of table components, `DOMAIN.md` untouched.

## 5. Testing and verification

- RED→GREEN per component: renders header noun/count, search, sort whitelist (tampered field ignored), filter + chip + `Reset Filter`, zero-result state, pagination page-name isolation, row actions still authorised, one live region.
- Regression: existing `EvaluationTableTest`, `TrainingTableTest`, `JobHistoryTableTest`, `EmployeeCrudTest`, `EmployeeExportTest`, `EmployeeImportTest`, `EmployeePeriodFilterTest`, `FormasiEditorTest` stay green (updated only where markup moved).
- Manual (human): each screen at 375px and 1366px beside the Employees directory.

## 6. Risks and open points

- Trainings KPI totals depend on the date range and `EmployeeStatistic`; they must keep using one filtered query so cards and table never disagree.
- Job vacancy data comes from `JobVacancyService` (not an Eloquent builder); may need a collection-backed variant of the pipeline — decided in the plan after reading the service.
- Employees directory port is the one item that may be deferred if parity is unprovable (phase D gate above).
