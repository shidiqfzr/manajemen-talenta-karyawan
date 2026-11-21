# Formasi Navigation and DRY Consolidation Plan

> Steps use TDD (RED then GREEN); commit per task.

**Goal:** Make the Formasi feature (all Master Data sections) follow enterprise HRIS information architecture and the project's DRY principle (ENGINEERING.md §2.4), without changing any business rule.

**Problem (measured):** six flat tabs mixing four mental models (viewing the structure, exceptions, change workflow, history); the same logic copied across components: NIK masking x4, per-page/paginate x4, `setView` x2, hand-built segmented toggles x4.

**Architecture:** (1) shared building blocks replace the copies; (2) navigation becomes two-level: three primary sections (Formasi, Surat Keputusan, Riwayat Perubahan) and, inside Formasi only, a view switch between its four read/manage views (Unit Kerja, Sebaran Jabatan, Penampungan, Versi Struktur). URLs and components of each view stay as they are, so no business behaviour moves.

**Spec / SSOT:** DESIGN.md §6, §8.5, §8.5a, §8.5b; ENGINEERING.md §2.4; DOMAIN.md §3.5-3.7; AGENTS.md rules 5-7.

## Global Constraints
- No business-rule change; existing tests and e2e stay green after every task.
- UI text uses official terms; no internal document names on screen (AGENTS.md rule 6).
- One test class per SUT (AGENTS.md rule 5). Pint clean. Rebuild assets after view changes.

## Tasks
1. **NikMasker** (`App\Support\NikMasker::mask`): one masking rule; replace the copies in `StructureAsOfService`, `EmployeePositionTimelineService`, `StructureEmployeeTimeline`. Guard: no other `str_repeat('*', max(strlen` in `app/`.
2. **`x-admin.segmented`**: the keyboard-operable radiogroup toggle (arrow keys, `aria-checked`, roving tabindex) extracted from Sebaran; adopt in Sebaran, Penampungan, Versi Struktur (replaces the `aria-pressed` copy). Guard: `role="radiogroup"` only inside the component (employee form excepted).
3. **`WithCollectionTable`** concern: `$perPage`, `perPageOptions()`, `defaultPerPage()`, `updatedPerPage()`, `paginateCollection()`; adopt in `FormasiDistribution`, `PoolPositions`, `StructureVersionsPage`, `FormasiEditor`.
4. **Two-level Master Data navigation**: `section-nav` renders three primary sections plus the Formasi view switch; update nav tests, e2e specs and DESIGN.md §8.5.
5. **Shared test fixtures**: `tests/Concerns/CreatesStructureVersions.php` for decree/employee/history setup used by the four structure test classes.
6. **SSOT update**: DESIGN.md §8.5/§8.5b, ENGINEERING.md §2.4 (list the shared pieces), guard tests.
7. **Verification**: full PHP suite, Pint, all e2e specs, screenshots of every Formasi view at 1366px and 390px.

## Review Focus
- Keyboard behaviour of the segmented control must be identical to Sebaran's current one (existing e2e covers it).
- URL query names of each view must not change (shared, bookmarked links).
- The primary tab "Formasi" must stay current on all four views and on a unit detail page.

## Out of scope (decided, deferred)
- A single "as of" control inside the live Formasi editor (needs a snapshot-backed editor).
- Moving the employee timeline to the employee profile (Karyawan module).
