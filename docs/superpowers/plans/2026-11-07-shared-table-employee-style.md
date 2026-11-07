# Shared Data Table — One Design Modelled on the Employees Directory Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make every shared table look and behave like the Employees directory table the user prefers — search row with "Filter Lanjutan (n)" and "Reset Filter", a labelled filter panel with clearable fields, a "Filter Aktif" row with "Hapus Filter Tambahan", and a table header bar with title, result-count badge, page range and "Ekspor Excel (n)" — while keeping the shared engine (instant filtering, validated URL state, audited export, accessibility).

**Architecture:** The shared `x-admin.table.toolbar` is restyled to the Employees layout and stops carrying the count and the export; a new `x-admin.table.header` component carries title, count badge, range and export (DESIGN.md §7.5: export top right of the table). `x-admin.filter-select` gains an icon-in-label style and a clear button. The trait gains `resetPanelFilters()` for "Hapus Filter Tambahan". The "X dari Y sesuai filter" summary bar and the `unfilteredCount` helper it needed are removed (the Employees pattern shows the result count only). Filtering stays instant (user decision).

**Tech Stack:** Laravel 13, Livewire 4.4.6, Alpine.js, Tailwind tokens from `DESIGN.md`, PHPUnit 11.

**Spec:** `docs/superpowers/specs/2026-11-06-shared-data-table-design.md` (§4.2, §5.3, §9). Guidelines: `DESIGN.md` §6, §7.5 ("one system for all tables", export top right), §8.4 (directory: main search with `/`, advanced filters in a collapsible panel), §10; `ENGINEERING.md` §2.3; `AGENTS.md` rule 6. Visual reference: `resources/views/admin/employees/index.blade.php` (toolbar row ~355–392, panel ~395–500, "Filter Aktif" row ~505–575, table header bar ~585–625). Previous plans: `2026-11-07-shared-table-toolbar-ux.md`, `2026-11-07-shared-table-filter-summary.md`.

**Branch:** create `feat/shared-table-employee-style` from `feat/shared-table-filter-summary` (parent branches are not merged yet).

**Out of scope:** migrating the Employees directory itself onto the shared layer (spec step 5, its own plan — it will then use exactly this design), the explicit "Terapkan Filter" apply step (user chose instant filtering), searchable selects, bulk-selection header state, pagination footer.

## Global Constraints

- PHP: composer.json declares `^8.3` (dev machine runs 8.5) — nothing newer than PHP 8.3.
- Code, docblocks, commit messages English; user-facing text Indonesian business wording; rendered output never contains `AGENTS.md`, `DOMAIN.md`, `DESIGN.md`, `ENGINEERING.md`, `SECURITY.md` or `SSOT`.
- Exact copy (mirrors the Employees directory; "Export Data" there is English, so the shared table keeps the guideline wording `Ekspor Excel`):
  - Panel button `Filter Lanjutan` with a count badge (active filters that have a field; none at 0); `Reset Filter` (shown only when search or any filter is active; clears everything).
  - Chips row heading `Filter Aktif:`; chips read `{label}: {value}` with the value in bold; search chip `Pencarian: "{term}"`; link `Hapus Filter Tambahan` (clears the panel filters only, keeps the search and filters driven from outside such as KPI cards; shown only when a panel filter is active).
  - Field clear button `aria-label` `Hapus pilihan {label}`.
  - Header bar: optional title (bold), badge `{total} {Noun}` (e.g. `319 Riwayat`), then (from `md`) `| Menampilkan {first} – {last} dari {total}` when there are rows, and on the right `Ekspor Excel` with a count badge `{total}` when the module exports. Numbers use the Indonesian thousands separator.
- Layout (classes mirror the Employees page): toolbar row `flex flex-col sm:flex-row items-stretch sm:items-center gap-2.5`; panel inside the toolbar area separated by `border-t border-surface-200/80` (not a separate bordered card), grid `grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3.5`, labels `block text-[11px] font-semibold text-surface-700 mb-1` with an `aria-hidden` icon `text-surface-400 text-[10px] mr-1`; chips row `flex flex-wrap items-center gap-2 pt-2 border-t border-surface-200`; header bar `px-5 py-3 border-b border-surface-200 bg-white flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3`.
- Accessibility: the header's count (badge + range) sits in one permanent `role="status" aria-live="polite" aria-atomic="true"` element; `Filter Lanjutan` keeps `aria-expanded`/`aria-controls`/Esc-returns-focus from the previous plan; every visible label is tied to its control; icons `aria-hidden`.
- Filtering is instant (`wire:model.live`), as today.
- Run `./vendor/bin/pint` before every commit; one test class per system under test (`WithDataTableTest`, `TableComponentsTest`, existing module test classes).
- Commit trailer: the one required by the attribution rule in force for the model that writes the commit.

## Review Focus

1. **Embedded tables** (employee profile tabs) show the header bar without a title and without export, with the right noun → Task 3 tests per employee table.
2. **"Hapus Filter Tambahan" vs "Reset Filter"** on the mutasi screen: the link keeps the KPI-driven status and the search; Reset clears all → Task 1 `test_reset_panel_filters_keeps_search_and_controlless_filters`, Task 3 mutasi test.
3. **Zero results:** header badge `0 {Noun}`, no range text, Export still shows `0` (and the shared export limit/gate still apply), empty state below → Task 2/3 tests.
4. **Clear "×" on a field** clears only that field and only appears when it has a value; keyboard reachable with its own label → Task 2 `test_filter_field_can_be_cleared_on_its_own`.
5. **One live region per table** after the summary bar is removed → Task 2 `test_header_announces_the_count_in_one_live_region`.

---

## File Map

| File | Responsibility |
|---|---|
| `app/Livewire/Concerns/WithDataTable.php` | + `resetPanelFilters()`; − `unfilteredCount`, `unfilteredQuery()` |
| `app/Livewire/Admin/MutasiHistory.php` | − `unfilteredQuery()` override |
| `resources/views/components/admin/table/header.blade.php` (new) | title, count badge, range, export (live region) |
| `resources/views/components/admin/table/toolbar.blade.php` | Employees-style row, panel, chips row; no summary bar, no export |
| `resources/views/components/admin/filter-select.blade.php` | label icon style + clear button (opt-in props) |
| five module views + `tests/Fixtures/DataTable/FixtureTable.php`, `FixtureToolbarOnly.php` | use `x-admin.table.header`; drop toolbar count/export props |
| `DESIGN.md` | §6 rows, §7.5 bullet, change-log row |

---

### Task 1: Trait — "Hapus Filter Tambahan", drop the unfiltered total

**Files:** Modify `app/Livewire/Concerns/WithDataTable.php`, `app/Livewire/Admin/MutasiHistory.php`; delete `tests/Fixtures/DataTable/FixtureTableScoped.php`, `FixtureTableCustomTotal.php`; Test `tests/Feature/DataTable/WithDataTableTest.php`, `tests/Feature/MutasiHistoryTest.php`.

**Interfaces — Produces:** public `resetPanelFilters(): void` — sets every filter property whose filter `hasControl()` to `''`, resets the page and clears the selection; leaves `search` and controlless filters untouched. Removes `unfilteredCount` (computed), `unfilteredQuery()` and the mutasi override, and the tests that pinned them (`test_unfiltered_count_*`, `test_summary_total_follows_the_source_scope_like_the_kpi_card`).

- [ ] **Step 1: Write failing tests** — `test_reset_panel_filters_keeps_search_and_controlless_filters` (fixture with one controlled + one controlless filter + search: after the call the controlled one is `''`, the other two keep their values, page is 1, selection empty).
- [ ] **Step 2: Run** `php artisan test tests/Feature/DataTable/WithDataTableTest.php` — Expected: FAIL (method missing).
- [ ] **Step 3: Implement; remove the unfiltered-total code, its fixtures and tests.**
- [ ] **Step 4: Run** `php artisan test tests/Feature/DataTable tests/Unit/DataTable tests/Feature/MutasiHistoryTest.php` — Expected: PASS (views still pass `total-count` → temporarily remove that prop from the views/fixture in this task so nothing references the removed property; the toolbar keeps accepting it until Task 2).
- [ ] **Step 5: Pint + commit** — `refactor(table): add panel-only filter reset, drop the unfiltered total`

---

### Task 2: Header component, Employees-style toolbar and fields

**Files:** Create `resources/views/components/admin/table/header.blade.php`; modify `toolbar.blade.php`, `filter-select.blade.php`, `tests/Fixtures/DataTable/FixtureTable.php` and `FixtureToolbarOnly.php` (render the header; drop toolbar count/export props); Test `tests/Feature/DataTable/TableComponentsTest.php`.

**Interfaces — Produces:**
- `x-admin.table.header` props: `title` (`?string`), `paginator` (LengthAwarePaginator), `noun` (string, capitalised, e.g. `Riwayat`), `export` (bool). Renders per Global Constraints; the export button is `<x-admin.button … wire:click="export" target="export">Ekspor Excel</x-admin.button>` plus the count badge.
- `x-admin.table.toolbar`: props `table-id`, `filters`, `filter-values`, `has-active-filters`, `search`, `search-placeholder`, `search-shortcut` (removed: `export`, `title` slot, `result-count`, `total-count`, `noun`). Row: search · `Filter Lanjutan (n)` (only when a filter has a field) · `Reset Filter` (when active, `wire:click="resetFilters"`). Panel below a `border-t`, grid of labelled fields (selects via `x-admin.filter-select` with `show-label`, `label-icon` and `clearable`; date pair; boolean toggles). Chips row below a `border-t` when active: `Filter Aktif:`, chips (`{label}: <strong>{value}</strong>`, search `Pencarian: "<strong>{term}</strong>"`), `Hapus Filter Tambahan` link (`wire:click="resetPanelFilters"`) when a panel filter is active.
- `x-admin.filter-select`: new optional props `label-icon` (renders the icon inside the visible label, Employees style; label classes per Global Constraints) and `clearable` (when the model has a value, a `×` button `wire:click="$set('{model}', '')"` with `aria-label="Hapus pilihan {label}"` sits inside the field before the chevron). Defaults keep today's output.

- [ ] **Step 1: Write failing tests** (replace the summary-bar tests from the previous plan — `test_summary_*` — with these; keep chips/escaping/accessibility tests, adapting selectors where the markup moved):
  - `test_header_shows_title_count_range_and_export` (FixtureTable: `Tabel Uji`, `30 Data`, `Menampilkan 1 – 25 dari 30`, `Ekspor Excel` with badge `30`).
  - `test_header_without_rows_shows_zero_and_no_range`.
  - `test_header_without_title_or_export` (FixtureTableNoExport variant / embedded case).
  - `test_header_announces_the_count_in_one_live_region` (exactly one `role="status"` in the table when no notice is shown).
  - `test_toolbar_row_has_filter_lanjutan_and_reset_on_the_right` (order: search, `Filter Lanjutan`, `Reset Filter`; Reset only when active; no `Ekspor Excel` in the toolbar).
  - `test_filter_fields_have_icon_labels` (label classes + `aria-hidden` icon + text).
  - `test_filter_field_can_be_cleared_on_its_own` (no `×` when empty; with `bidang=KEU` a button `aria-label="Hapus pilihan Bidang"` with `$set('bidang', '')`).
  - `test_active_filter_row_offers_hapus_filter_tambahan` (link present only when a panel filter is active, calls `resetPanelFilters`; chips show bold values).
- [ ] **Step 2: Run** `php artisan test tests/Feature/DataTable/TableComponentsTest.php` — Expected: new tests FAIL.
- [ ] **Step 3: Implement** (copy the Employees classes listed in Global Constraints; tokens only).
- [ ] **Step 4: Run** `php artisan test tests/Feature/DataTable tests/Unit/ViewDesignGuardTest.php tests/Feature/FormasiEditorTest.php` — Expected: PASS.
- [ ] **Step 5: Pint + commit** — `feat(table): employees-style toolbar, filter fields and table header`

---

### Task 3: Wire every table, docs, full suite

**Files:** Modify `resources/views/livewire/admin/master/audit-history.blade.php`, `resources/views/livewire/admin/partials/mutasi-history/history-table.blade.php`, `resources/views/livewire/tables/{training,evaluation,job-history}-table.blade.php`, `DESIGN.md`; Test the five module test classes.

- Each view: toolbar without count/export props; `<x-admin.table.header>` directly above the `<table>` wrapper with `:paginator="$rows"`, `noun` (`Catatan`, `Riwayat`, `Pelatihan`, `Evaluasi`, `Riwayat Jabatan`), `:export="$this->exportName() !== null"`, and `title` = `Riwayat Perubahan` (audit) / `Riwayat Mutasi` (mutasi) / none for the three embedded profile tables.
- `DESIGN.md`: §6 rows for `x-admin.table.toolbar`, `x-admin.table.header` (new), `x-admin.filter-select` (`label-icon`, `clearable`); §7.5 bullet replacing the summary-bar wording ("Pola tabel mengikuti Direktori Pegawai: baris pencarian + Filter Lanjutan + Reset Filter, panel berlabel, baris Filter Aktif dengan Hapus Filter Tambahan, bilah judul dengan jumlah data, rentang, dan Ekspor Excel di kanan; filter langsung diterapkan"); change-log row `v3.17` after `v3.16`.

- [ ] **Step 1: Update/write tests** — audit and mutasi: header shows `{n} Catatan` / `{n} Riwayat` and their titles, one live region, zero-result state (`0 Catatan`, empty state, `Reset Filter`); mutasi: `Hapus Filter Tambahan` keeps a KPI status chosen via `toggleStatusFilter`; employee tables: header `{n} Pelatihan|Evaluasi|Riwayat Jabatan`, no title, no export. Replace the previous plan's summary-count assertions accordingly.
- [ ] **Step 2: Run** the five module test files — Expected: FAIL.
- [ ] **Step 3: Wire the views and update `DESIGN.md`.**
- [ ] **Step 4: Run** `./vendor/bin/pint` then the full suite `php artisan test` (10-minute timeout; last full run 761 passed, 7 deprecated) — Expected: PASS.
- [ ] **Step 5: Manual check (human)** — mutasi history, Riwayat Perubahan, employee profile tabs, side by side with the Employees directory at 375px and 1366px: same look for the search row, panel, Filter Aktif row and header bar.
- [ ] **Step 6: Commit** — `feat(table): give every shared table the employees directory layout`
