# Shared Data Table — Filter Summary Bar Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Under the toolbar of every shared table, show one always-visible summary bar: how many records match ("**12** dari 339 riwayat sesuai filter", or "**339** riwayat" when nothing is filtered), the active-filter chips (also while the filter panel is open), and "Reset filter" on the right — and remove the wasted Reset row inside the panel.

**Architecture:** The trait gets one cached helper for the unfiltered total; the shared toolbar gets three props (`result-count`, `total-count`, `noun`) and renders the summary bar with the result announcement for screen readers. Module views pass the numbers they already have (`$rows->total()`) and drop their own hidden "N … ditemukan" regions, which the bar replaces.

**Tech Stack:** Laravel 13, Livewire 4.4.6, Alpine.js, Tailwind tokens from `DESIGN.md`, PHPUnit 11.

**Spec:** `docs/superpowers/specs/2026-11-06-shared-data-table-design.md` (§4.2, §5.3, §9). Guidelines: `DESIGN.md` §6, §7.5 (explicit filter, empty and loading state; export top right), §10 (accessibility); `ENGINEERING.md` §2.3; `AGENTS.md` rule 6. Previous plan: `2026-11-07-shared-table-toolbar-ux.md` (filter panel, count badge, chips).

**Branch:** create `feat/shared-table-filter-summary` from `feat/shared-table-toolbar-ux` (parent branches are not merged yet).

**Out of scope:** the Employees directory, bulk bar, pagination footer (it keeps its "Menampilkan 1–25 dari Y" range), filter types, closing the panel on outside click.

## Global Constraints

- PHP: composer.json declares `^8.3` (dev machine runs 8.5) — nothing newer than PHP 8.3.
- Code, docblocks, commit messages English; user-facing text Indonesian business wording; rendered output never contains `AGENTS.md`, `DOMAIN.md`, `DESIGN.md`, `ENGINEERING.md`, `SECURITY.md` or `SSOT`.
- Exact copy (numbers use the Indonesian thousands separator, e.g. `1.240`):
  - Nothing filtered: `{total} {noun}` with the number bold, e.g. **339** riwayat.
  - Search or filters active: `{result} dari {total} {noun} sesuai filter`, result bold, e.g. **12** dari 339 riwayat sesuai filter.
  - Chips heading `Filter Aktif:`; chip remove `aria-label` `Hapus filter {label}` (unchanged); `Reset filter` (unchanged).
- Layout: the summary bar is one row directly under the search row (and under the panel when it is open): count + chips on the left (wrapping), `Reset filter` aligned right (`ml-auto`), shown only when something is active. The bar is visible whether the panel is open or closed. The panel no longer contains a Reset button.
- Accessibility: the count text sits in a permanent `role="status" aria-live="polite" aria-atomic="true"` element, so screen readers hear the new count after each change; modules must not keep a second announcement region.
- Performance: the unfiltered total is one extra `COUNT` only while something is active (`total-count` is null otherwise and the bar shows `result-count` as the total); it is cached per request.
- Run `./vendor/bin/pint` before every commit; one test class per system under test (`WithDataTableTest`, `TableComponentsTest`, and the existing module test classes).
- Commit trailer: the one required by the attribution rule in force for the model that writes the commit.

## Review Focus

1. **Nothing active:** the bar shows only `{total} {noun}`, no chips, no Reset → Task 2 `test_summary_shows_the_total_when_nothing_is_filtered`.
2. **Search only / controlless filter only / panel filters:** count reads `{result} dari {total} …`, chips and Reset appear, the badge rules from the previous plan still hold → Task 2 `test_summary_counts_results_against_the_unfiltered_total`, `test_summary_works_for_search_only_and_controlless_filters`.
3. **Zero results:** `0 dari 339 … sesuai filter` above the empty state, Reset still reachable on the right → Task 3 tests on the audit and mutasi screens.
4. **Panel open:** chips and count stay visible (no `x-show="! open"`); the panel has no Reset button → Task 2 `test_summary_bar_stays_visible_and_panel_has_no_reset`.
5. **Exactly one announcement region per table** (no duplicate live regions after modules drop theirs) → Task 3 `test_*_announces_the_result_count` updated to count regions.

---

## File Map

| File | Responsibility |
|---|---|
| `app/Livewire/Concerns/WithDataTable.php` | + `unfilteredCount()` (computed, cached) |
| `resources/views/components/admin/table/toolbar.blade.php` | summary bar; Reset moves out of the panel |
| `resources/views/livewire/admin/master/audit-history.blade.php`, `resources/views/livewire/admin/partials/mutasi-history/history-table.blade.php`, `resources/views/livewire/tables/{training,evaluation,job-history}-table.blade.php`, `tests/Fixtures/DataTable/FixtureTable.php` | pass the new props; drop module live regions |
| `DESIGN.md` | §6 toolbar row, §7.5 bullet, change-log row |

---

### Task 1: Unfiltered total

**Files:** Modify `app/Livewire/Concerns/WithDataTable.php`; Test `tests/Feature/DataTable/WithDataTableTest.php`.

**Interfaces — Produces:** `#[Computed] unfilteredCount(): int` = `baseQuery()` count (no search, no filters, no selection, ordering removed). Public computed so module views read `$this->unfilteredCount`; computed properties cannot be called from the browser.

- [ ] **Step 1: Write failing test** `test_unfiltered_count_ignores_search_and_filters` — 30 fixture rows; with `bidang=KEU` and search set, `unfilteredCount` is still 30 while `rows->total()` is smaller; a module whose `baseQuery()` already scopes (use a fixture variant with a scoped `baseQuery`) returns the scoped count.
- [ ] **Step 2: Run** `php artisan test tests/Feature/DataTable/WithDataTableTest.php` — Expected: FAIL (property missing).
- [ ] **Step 3: Implement.**
- [ ] **Step 4: Run** `php artisan test tests/Feature/DataTable tests/Unit/DataTable` — Expected: PASS.
- [ ] **Step 5: Pint + commit** — `feat(table): expose the unfiltered row total`

---

### Task 2: Summary bar in the toolbar

**Files:** Modify `resources/views/components/admin/table/toolbar.blade.php`; Test `tests/Feature/DataTable/TableComponentsTest.php`.

**Interfaces — Produces:** toolbar props `result-count` (int, required for the bar; when absent the bar is not rendered so old callers keep working), `total-count` (`?int`, the unfiltered total; when null the bar treats `result-count` as the total), `noun` (string, default `data`).

Behaviour (see Global Constraints for copy and layout):
- The bar always renders when `result-count` is given: count text (inside the live region) on the left; then `Filter Aktif:` + chips when something is active; then `Reset filter` with `ml-auto` when `has-active-filters`.
- Remove `x-show="! open"` from the chips (they show with the panel open too) and remove the Reset block from the panel.
- Numbers formatted with `number_format($n, 0, ',', '.')`.

- [ ] **Step 1: Write failing tests** (render through `FixtureTable`/`FixtureTableDateFilter`/`FixtureTableControlless`, which pass the new props after Task 3's fixture change — for this task update the fixture render first, as part of the test setup):
  - `test_summary_shows_the_total_when_nothing_is_filtered` — `30 data` with `30` bold; no `Filter Aktif:`; no `Reset filter`.
  - `test_summary_counts_results_against_the_unfiltered_total` — `bidang=KEU` → `15 dari 30 data sesuai filter` (15 bold).
  - `test_summary_works_for_search_only_and_controlless_filters` — search only → `… dari 30 data sesuai filter`, chip `Pencarian: …`, Reset shown; controlless `bidang` → chip + Reset, no badge.
  - `test_summary_bar_stays_visible_and_panel_has_no_reset` — no `x-show="! open"` in the markup; the panel element (`id="…-filter-panel"`) does not contain `Reset filter`; `Reset filter` appears exactly once and after the count text.
  - `test_summary_announces_the_count_in_one_live_region` — exactly one `role="status"` inside the toolbar, containing the count text.
  - `test_summary_formats_thousands_with_a_dot` — a fixture/Blade case with `result-count` 1240 → `1.240`.
- [ ] **Step 2: Run** `php artisan test tests/Feature/DataTable/TableComponentsTest.php` — Expected: new tests FAIL.
- [ ] **Step 3: Implement.**
- [ ] **Step 4: Run** `php artisan test tests/Feature/DataTable tests/Unit/ViewDesignGuardTest.php` — Expected: PASS.
- [ ] **Step 5: Pint + commit** — `feat(table): show a result summary with chips and reset under the toolbar`

---

### Task 3: Wire every table, docs, full suite

**Files:** Modify the five module views and the fixture render listed in the File Map, `DESIGN.md`; Test `tests/Feature/MasterAuditLogTest.php`, `tests/Feature/MutasiHistoryTest.php`, `tests/Feature/TrainingTableTest.php`, `tests/Feature/EvaluationTableTest.php`, `tests/Feature/JobHistoryTableTest.php`.

- Each view passes `:result-count="$rows->total()"`, `:total-count="$this->hasActiveFilters() ? $this->unfilteredCount : null"`, and `noun` = the same noun its pagination footer uses (`catatan`, `riwayat`, `pelatihan`, `evaluasi`, `riwayat jabatan`; fixture `data`).
- Audit history and mutasi history delete their own `sr-only` "N catatan ditemukan" / "N riwayat ditemukan" regions (the toolbar's live region replaces them).
- `DESIGN.md`: §6 toolbar row (summary bar, props `result-count`, `total-count`, `noun`, Reset on the right, chips visible with the panel open), §7.5 bullet ("Di bawah pencarian selalu tampil jumlah data dan, bila ada filter, '{hasil} dari {total} … sesuai filter', chip filter aktif, dan Reset filter di kanan"), change-log row `v3.16` after `v3.15`.

- [ ] **Step 1: Update/write tests** — update `test_history_announces_the_result_count` (audit) and `test_result_count_is_announced_through_a_permanent_live_region` (mutasi) to the new copy and to exactly ONE `role="status"` element in the table; add `test_history_shows_the_filtered_count_above_the_empty_state` (audit: `entity_type=seat` with no seat rows → `0 dari N catatan sesuai filter` before the empty-state message, Reset present) and the mutasi equivalent; one assertion per employee table that the bar shows `{n} {noun}`.
- [ ] **Step 2: Run** the five module test files — Expected: FAIL (old copy / two regions).
- [ ] **Step 3: Wire the views and update `DESIGN.md`.**
- [ ] **Step 4: Run** `./vendor/bin/pint` then the full suite `php artisan test` (10-minute timeout; last full run 744 passed, 7 deprecated) — Expected: PASS.
- [ ] **Step 5: Manual check (human)** — mutasi history, Riwayat Perubahan and an employee profile at 375px and 1366px: count updates while typing/filtering, chips stay visible with the panel open, Reset sits on the right, zero-result state reads well.
- [ ] **Step 6: Commit** — `feat(table): show result counts on every shared table`
