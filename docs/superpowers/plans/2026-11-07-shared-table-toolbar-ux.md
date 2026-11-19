# Shared Data Table — Toolbar Search & Filter UX Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make search and filters in the shared table toolbar clear and easy to use for every table: a big search box, one "Filter" button with a count that opens a panel of labelled fields, removable chips for active filters, and one tidy row for search, filter and export.

**Architecture:** Only the shared `x-admin.table.toolbar` and `x-admin.filter-select` change, plus one small `Filter` option. Modules keep declaring filters exactly as today. The panel is client-side (Alpine `x-data`): closed by default, so the search box stays prominent and active filters show as chips. The mutasi history status filter keeps its KPI cards as its only control.

**Tech Stack:** Laravel 13, Livewire 4.4.6, Alpine.js, Tailwind tokens from `DESIGN.md`, PHPUnit 11.

**Spec:** `docs/superpowers/specs/2026-11-06-shared-data-table-design.md` (§4.2, §9). Guidelines that bind this work: `DESIGN.md` §6, §7.5, §8.4 ("Pencarian utama dengan pintasan `/`; filter lanjutan di panel yang bisa dibuka-tutup"), §10 (accessibility); `ENGINEERING.md` §2.3; `AGENTS.md` rule 6 (no internal file names on screen).

**Branch:** create `feat/shared-table-toolbar-ux` from `feat/shared-table-mutasi-history` (parent branches are not merged yet; start only after the mutasi history final fix wave is committed).

**Out of scope:** the Employees directory (its own advanced-filter panel moves to this toolbar in its own step), bulk-action bar, `FormasiEditor`/`ManPowerPlanning` toolbars, any new filter types.

## Global Constraints

- PHP: composer.json declares `^8.3` (dev machine runs 8.5) — nothing newer than PHP 8.3.
- Code, docblocks, commit messages English; user-facing text Indonesian business wording; rendered output never contains `AGENTS.md`, `DOMAIN.md`, `DESIGN.md`, `ENGINEERING.md`, `SECURITY.md` or `SSOT`.
- Exact copy: panel button `Filter` (badge = number of active filters that have a control in the panel; no badge at 0); search placeholder is passed by the module (unchanged); chips heading `Filter Aktif:`; chip remove `aria-label` `Hapus filter {label}`; reset button `Reset filter`; export button `Ekspor Excel`.
- Layout: row A holds search (takes all free width, never shrinks below its placeholder at 1366px), the `Filter` button (only when at least one filter has a control), and `Ekspor Excel` on the right when exporting and no title slot is given; when a title slot is given, the title + export stay on a first row (DESIGN.md §7.5 level 1). The panel is a responsive grid (`grid-cols-1 sm:grid-cols-2 lg:grid-cols-4`) of fields with a **visible** label above each control; `Reset filter` sits at the panel end. Chips row shows only while the panel is closed and something is active.
- Accessibility: the `Filter` button is a real `<button type="button">` with `aria-expanded` (kept in sync with Alpine) and `aria-controls` pointing at the panel id; `Esc` closes the panel; every field's visible `<label for>` matches its control id; icons `aria-hidden="true"`; tokens only (no arbitrary colours, no dark-mode classes).
- Tables with no filters (the employee history tables) show the search box only: no Filter button, no panel.
- Run `./vendor/bin/pint` before every commit; one test class per system under test (toolbar behaviour in `tests/Feature/DataTable/TableComponentsTest.php`, `Filter` in `tests/Unit/DataTable/FilterTest.php`, the mutasi screen in `tests/Feature/MutasiHistoryTest.php`).
- Commit trailer: the one required by the attribution rule in force for the model that writes the commit.

## Review Focus

1. **Tables without filters** (employee history tables) render search only — no empty panel or stray button → Task 3 `test_toolbar_without_filters_shows_only_search`.
2. **Active filters at page load:** panel closed, chips visible, badge count right (search is not counted) → Task 3 `test_filter_button_counts_active_filters`.
3. **Keyboard and screen readers:** `aria-expanded`/`aria-controls`, `Esc`, labels tied to controls → Task 3 `test_filter_panel_is_accessible`; manual check for the real toggle behaviour (Alpine is not covered by PHPUnit).
4. **A filter with no control** (mutasi status, driven by KPI cards) still validates its URL value, shows as a chip, counts for "Reset filter", but renders no field and no badge count → Task 1 + Task 3 + Task 4.
5. **Narrow screens (375px):** search full width, Filter and Export wrap cleanly, panel grid is one column → manual check.

---

## File Map

| File | Responsibility |
|---|---|
| `app/Support/DataTable/Filter.php` | + `withoutControl()` and `hasControl()` |
| `resources/views/components/admin/filter-select.blade.php` | + optional visible label (`show-label`), default unchanged |
| `resources/views/components/admin/table/toolbar.blade.php` | new layout: search row, Filter button + panel, chips row |
| `app/Livewire/Admin/MutasiHistory.php` | status filter declared `withoutControl()` |
| `DESIGN.md` | §6 toolbar and filter-select rows, §7.5 bullet, change-log row |

---

### Task 1: A filter can have no control

**Files:** Modify `app/Support/DataTable/Filter.php`; Test `tests/Unit/DataTable/FilterTest.php`.

**Interfaces — Produces:** `Filter::withoutControl(): self` (clone) and `Filter::hasControl(): bool` (true by default). Validation, URL state, chips, `hasActiveFilters()` and `activeFilterValues()` are unchanged for such a filter.

- [ ] **Step 1: Write failing tests** — `test_filters_have_a_control_by_default`, `test_without_control_keeps_the_filter_otherwise_intact` (still `allows`, `applyTo`, options) and the original stays untouched (clone).
- [ ] **Step 2: Run** `php artisan test tests/Unit/DataTable/FilterTest.php` — Expected: FAIL (method not found).
- [ ] **Step 3: Implement** (private promoted `bool $control = true` field, same clone helper).
- [ ] **Step 4: Run** `php artisan test tests/Unit/DataTable` — Expected: PASS.
- [ ] **Step 5: Pint + commit** — `feat(table): let a filter be driven from outside the toolbar`

---

### Task 2: Visible label on a filter select

**Files:** Modify `resources/views/components/admin/filter-select.blade.php`; Test `tests/Feature/DataTable/TableComponentsTest.php` (and the existing Formasi/audit tests that render `x-admin.filter-select` must stay green).

**Interfaces — Produces:** optional prop `show-label` (bool, default `false`). When true the component renders a visible `<label for="{id}">` (small, `text-xs font-medium text-surface-600`) above the control and drops the `sr-only` duplicate; when false its output is byte-identical to today.

- [ ] **Step 1: Write failing test** `test_filter_select_can_show_a_visible_label` (renders through a fixture/toolbar path added in Task 3, or directly via `Blade::render` of the component: a visible label with `for` = the select id, no `sr-only` copy) and `test_filter_select_default_output_is_unchanged` (still `sr-only`).
- [ ] **Step 2: Run** the new tests — Expected: FAIL.
- [ ] **Step 3: Implement.**
- [ ] **Step 4: Run** `php artisan test tests/Feature/DataTable tests/Feature/FormasiEditorTest.php tests/Feature/MasterAuditLogTest.php tests/Unit/ViewDesignGuardTest.php` — Expected: PASS.
- [ ] **Step 5: Pint + commit** — `feat(table): allow a visible label on filter selects`

---

### Task 3: The new toolbar layout

**Files:** Modify `resources/views/components/admin/table/toolbar.blade.php`; Test `tests/Feature/DataTable/TableComponentsTest.php`.

**Interfaces:** Consumes Tasks 1–2 and the existing toolbar props (`table-id`, `filters`, `filter-values`, `search`, `export`, `has-active-filters`, `search-placeholder`, `search-shortcut`, `title` slot) — no prop is added or renamed, so every caller keeps working.

Behaviour (see Global Constraints for the exact layout and copy):
- Root has Alpine state `open: false`; the `Filter` button toggles it; `@keydown.escape` closes the panel; the panel (`id="{tableId}-filter-panel"`, `x-show="open"`, `x-cloak`) holds, in this order, select filters (with `show-label`), the date range (labelled `Dari Tanggal` / `Sampai Tanggal`, `–` separator `aria-hidden`), boolean filters (labelled toggle chips) and `Reset filter` at the end (only when `has-active-filters`). Filters with `hasControl() === false` are not rendered in the panel.
- The `Filter` button is rendered only when at least one filter has a control; its badge shows the count of active filters that have a control (from `filter-values`; the search term is not counted).
- The chips row (search chip + every active filter incl. controlless ones, each removable, plus `Reset filter`) renders when something is active and is shown with `x-show="! open"`.
- Export: in row A on the right when there is no title slot; with a title slot the title + export stay on their own first row.

- [ ] **Step 1: Write failing tests** (use `FixtureTable`, `FixtureTableDateFilter`, `FixtureTableBooleanFilter`, `FixtureTableNoExport` and the real `TrainingTable` for the no-filter case; add a named fixture variant with a controlless filter): 
  - `test_toolbar_without_filters_shows_only_search` — `TrainingTable` renders the search input, no `Filter` button text/`aria-controls`, no panel id.
  - `test_filter_button_counts_active_filters` — `bidang=KEU` + `dari=2026-01-01` + search `Budi` → badge `2` (not 3); no badge at 0.
  - `test_filter_panel_is_accessible` — button has `type="button"`, `aria-controls="…-filter-panel"` equal to the panel id, `:aria-expanded`/`x-bind` present, `@keydown.escape`; every visible `<label for="X">` has a control `id="X"`.
  - `test_filter_panel_shows_visible_labels` — labels `Bidang`, `Dari Tanggal`, `Sampai Tanggal` are NOT `sr-only`.
  - `test_controlless_filter_shows_as_a_chip_but_has_no_field` — chip + `aria-label="Hapus filter …"` present, no `wire:model.live` for that property, not counted in the badge.
  - `test_export_sits_in_the_search_row_without_a_title` and `test_title_row_is_kept_when_a_title_is_given`.
  - Keep the existing chip/escaping/date-input tests green (adapt selectors only where the markup moved; assertions about behaviour stay).
- [ ] **Step 2: Run** `php artisan test tests/Feature/DataTable/TableComponentsTest.php` — Expected: new tests FAIL.
- [ ] **Step 3: Rebuild the toolbar** per the behaviour above (tokens only; mirror the existing icon/focus-ring classes; `search-input` component stays the search control).
- [ ] **Step 4: Run** `php artisan test tests/Feature/DataTable tests/Unit/ViewDesignGuardTest.php tests/Feature/TrainingTableTest.php tests/Feature/EvaluationTableTest.php tests/Feature/JobHistoryTableTest.php tests/Feature/MasterAuditLogTest.php tests/Feature/MutasiHistoryTest.php` — Expected: PASS.
- [ ] **Step 5: Pint + commit** — `feat(table): put filters in a labelled panel with a count and chips`

---

### Task 4: Mutasi status via KPI cards, docs, full suite

**Files:** Modify `app/Livewire/Admin/MutasiHistory.php`, `DESIGN.md`; Test `tests/Feature/MutasiHistoryTest.php`.

- [ ] **Step 1: Write failing tests** — `test_status_has_no_toolbar_field_because_the_kpi_cards_drive_it` (no `wire:model.live="statusFilter"` in the page; `toggleStatusFilter('due')` shows the chip `Status: Siap Diaktifkan` with its remove button and the KPI card active state), `test_unit_jenis_and_source_fields_have_visible_labels` (labels `Unit Kerja`, `Jenis Mutasi`, `Sumber Catatan`, in the panel).
- [ ] **Step 2: Run** `php artisan test tests/Feature/MutasiHistoryTest.php` — Expected: FAIL.
- [ ] **Step 3: Implement** — `->withoutControl()` on the `statusFilter` filter; update `DESIGN.md`: §6 `x-admin.table.toolbar` and `x-admin.filter-select` rows (panel, count badge, visible labels, controlless filters, `show-label`), a §7.5 bullet ("Pencarian utama selebar baris; filter di panel dengan label terlihat; filter aktif tampil sebagai chip saat panel tertutup"), and a `v3.15` change-log row after `v3.14`.
- [ ] **Step 4: Run** `./vendor/bin/pint` then the full suite `php artisan test` (10-minute timeout) — Expected: PASS.
- [ ] **Step 5: Manual check (human)** — mutasi history, Riwayat Perubahan and an employee profile at 375px and 1366px: search is fully visible, Filter opens/closes (mouse, keyboard, Esc), chips appear when closed, Export is on the search row.
- [ ] **Step 6: Commit** — `refactor(mutasi): drive the status filter from the KPI cards only`
