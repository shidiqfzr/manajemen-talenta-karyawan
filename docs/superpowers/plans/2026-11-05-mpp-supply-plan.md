# MPP Supply Plan & Shortage/Surplus Split Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the hard-coded MPP supply constants with an audited, per-year plan that admins edit on the MPP screen, and split Kebutuhan totals into shortage and surplus.

**Architecture:** A new `mpp_supply_plans` table (one row per year × source × RM × bidang) is read and written only through `App\Services\Mpp\MppSupplyPlanService`, which audits every changed cell in `master_audit_logs`. `ManPowerPlanningService` reads supply from that service for T and T+1. A new Livewire drawer `MppSupplyPlanEditor` edits the plan; the existing `ManPowerPlanning` component shows the split totals and a missing-plan notice.

**Tech Stack:** Laravel 11, Livewire 3, PHPUnit 11, Laravel Pint, Tailwind tokens from DESIGN.md.

**Spec:** `docs/superpowers/specs/2026-11-05-mpp-supply-plan-design.md`

## Global Constraints

- Sources (keys → labels): `ckp` → "CKP Internal", `talent_scouting` → "Talent Scouting", `rbb` → "RBB", `prioritas` → "Jabatan Prioritas Diisi". Pasokan = `ckp + talent_scouting + rbb`; `prioritas` is never in the formula.
- Grid: RM `RM-1`, `RM-2`, `RM-3`; bidang `KEU`, `TAN`, `TEK`, `UMU`; 48 cells per year.
- `jumlah` whole number 0–999. Planning range 2024–2031. Reason at least 10 characters.
- 2026 seed: CKP RM-3 KEU 2 / TAN 7 / TEK 2 / UMU 1; Talent Scouting RM-3 0/4/2/0; RBB RM-3 8/6/1/6; Prioritas RM-2 UMU 1 and RM-3 1/4/0/8; every other cell 0.
- Stale-edit message (exact): `Rencana pasokan {tahun} telah diubah oleh pengguna lain. Muat ulang lalu ulangi perubahan.`
- Missing-plan notice (exact): `Rencana pasokan {tahun} belum diisi. Angka pasokan dihitung 0.`
- Multi-table writes inside `DB::transaction()`. UI text uses business terms only; no internal doc names or "SSOT" in views (AGENTS.md rule 6). Colors only from existing tokens (`ViewDesignGuardTest` must stay green).
- One test class per system under test (AGENTS.md rule 5). Pint on every changed file. Commit only with the user's go-ahead; messages end with `Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>`.

## Review Focus

1. Two admins editing the same year: the second save must be refused with the stale-edit message, not overwrite (Task 2 test `test_save_refuses_a_stale_edit`).
2. A planning year with no plan rows (e.g. 2028): matrix supply is 0, no exception, notice shown (Task 3 `test_unplanned_year_counts_supply_as_zero_and_flags_it`, Task 4 `test_missing_plan_notice_is_shown`).
3. Non-numeric or negative input in the editor (`""`, `"-1"`, `"abc"`, `1000`): refused with a safe message, input kept, nothing written (Task 5 `test_invalid_input_keeps_values_and_writes_nothing`).
4. A save that changes nothing: no audit rows, no event, editor reports nothing changed (Task 2 `test_unchanged_save_writes_no_audit`).
5. A Kebutuhan row where every cell is a surplus: shortage total 0, surplus total positive, net negative (Task 3 `test_shortage_and_surplus_totals_are_split`).

---

### Task 1: Plan table, model, and read side

**Files:**
- Create: `database/migrations/2026_11_06_100000_create_mpp_supply_plans_table.php`
- Create: `app/Models/MppSupplyPlan.php`
- Create: `app/Services/Mpp/MppSupplyPlanService.php`
- Modify: `app/Models/MasterAuditLog.php` (labels)
- Test: `tests/Feature/MppSupplyPlanServiceTest.php`

**Interfaces:**
- Produces: `MppSupplyPlan::SOURCES` (array key → label, order `ckp, talent_scouting, rbb, prioritas`), `MppSupplyPlan::LEVELS`, `MppSupplyPlan::BIDANG`, `MppSupplyPlan::MAX_JUMLAH = 999`.
- Produces: `MppSupplyPlanService::matrixFor(int $year): array<string, array<string, array<string, int>>>` (source → rm → bidang → int, zero-filled), `MppSupplyPlanService::hasPlan(int $year): bool`.

- [ ] **Step 1: Write the failing tests**

```php
public function test_unplanned_year_returns_a_zero_filled_matrix(): void
{
    $matrix = app(MppSupplyPlanService::class)->matrixFor(2030);
    $this->assertSame(array_keys(MppSupplyPlan::SOURCES), array_keys($matrix));
    $this->assertSame(['KEU' => 0, 'TAN' => 0, 'TEK' => 0, 'UMU' => 0], $matrix['rbb']['RM-2']);
    $this->assertFalse(app(MppSupplyPlanService::class)->hasPlan(2030));
}

public function test_migration_seeds_the_2026_plan_from_the_former_constants(): void
{
    $m = app(MppSupplyPlanService::class)->matrixFor(2026);
    $this->assertTrue(app(MppSupplyPlanService::class)->hasPlan(2026));
    $this->assertSame(48, MppSupplyPlan::where('year', 2026)->count());
    $this->assertSame(['KEU' => 2, 'TAN' => 7, 'TEK' => 2, 'UMU' => 1], $m['ckp']['RM-3']);
    $this->assertSame(['KEU' => 0, 'TAN' => 4, 'TEK' => 2, 'UMU' => 0], $m['talent_scouting']['RM-3']);
    $this->assertSame(['KEU' => 8, 'TAN' => 6, 'TEK' => 1, 'UMU' => 6], $m['rbb']['RM-3']);
    $this->assertSame(['KEU' => 0, 'TAN' => 0, 'TEK' => 0, 'UMU' => 1], $m['prioritas']['RM-2']);
    $this->assertSame(['KEU' => 1, 'TAN' => 4, 'TEK' => 0, 'UMU' => 8], $m['prioritas']['RM-3']);
    $this->assertSame(0, array_sum($m['ckp']['RM-1']));
}
```

- [ ] **Step 2: Run** `php artisan test --filter=MppSupplyPlanServiceTest` — Expected: FAIL, class `MppSupplyPlanService` not found.

- [ ] **Step 3: Implement.** Migration per spec §3 (columns, unique `(year, source, rm_level, bidang)`, index `year`, `updated_by` FK nullOnDelete); `up()` inserts the 48 rows for 2026 from Global Constraints with `DB::table()->insert()` and timestamps. Model with `$fillable` and the constants above. Service `matrixFor()` = one query for the year, overlay on a zero-filled grid. `MasterAuditLog`: add `ACTION_LABELS` `'mpp_supply.created' => 'Rencana pasokan MPP dibuat'`, `'mpp_supply.updated' => 'Rencana pasokan MPP diubah'`; `ENTITY_LABELS` `'mpp_supply' => 'Rencana Pasokan MPP'`; `FIELD_LABELS` `'jumlah' => 'Jumlah'`.

- [ ] **Step 4: Run** the same filter — Expected: PASS.

- [ ] **Step 5: Pint and commit** `feat(mpp): add the per-year supply plan table seeded with the 2026 plan`.

---

### Task 2: Audited save with rules

**Files:**
- Create: `app/Exceptions/MppPlanRuleViolationException.php` (extends `InvalidArgumentException`, same docblock style as `FormasiRuleViolationException`)
- Modify: `app/Services/Mpp/MppSupplyPlanService.php`
- Modify: `app/Services/ManPowerPlanningService.php` (add `public const PLANNING_YEAR_MIN = 2024; public const PLANNING_YEAR_MAX = 2031;`)
- Test: `tests/Feature/MppSupplyPlanServiceTest.php`

**Interfaces:**
- Consumes: Task 1 constants and `matrixFor()`.
- Produces: `MppSupplyPlanService::save(int $year, array $cells, array $expected, ?string $reason, User $user): int` — `$cells` and `$expected` have the `matrixFor()` shape; returns the number of changed cells; throws `MppPlanRuleViolationException`.

- [ ] **Step 1: Write the failing tests** (helper `$grid = fn () => $service->matrixFor(2026)`):
  - `test_save_updates_changed_cells_and_audits_each_one`: set `ckp.RM-3.KEU` to 5 and `rbb.RM-1.TEK` to 3, save with reason `'Penyesuaian hasil rapat SDM'`; returns 2; `matrixFor(2026)` reflects both; `MasterAuditLog::where('entity_type','mpp_supply')->count() === 2`; the row for KEU has `action 'mpp_supply.updated'`, `entity_label 'Rencana Pasokan 2026 — CKP Internal RM-3 KEU'`, `old_values ['jumlah'=>2]`, `new_values ['jumlah'=>5]`, the reason.
  - `test_first_save_of_a_year_creates_48_rows_and_audits_as_created`: year 2027 from the zero grid with one cell 4; `MppSupplyPlan::where('year',2027)->count() === 48`; one audit row, action `mpp_supply.created`.
  - `test_unchanged_save_writes_no_audit`: save the grid as loaded; returns 0; no audit rows.
  - `test_save_refuses_a_stale_edit`: load grid as `$expected`; change a cell directly in DB; save → `MppPlanRuleViolationException` with message `Rencana pasokan 2026 telah diubah oleh pengguna lain. Muat ulang lalu ulangi perubahan.`; stored value is the direct change.
  - `test_save_rejects_invalid_input` (data provider): year 2023; year 2032; a cell `-1`; a cell `1000`; a cell `'abc'`; an unknown source `'reguler'`; a missing bidang; reason `'singkat'` (7 chars); reason `null`. Each throws and leaves `MppSupplyPlan::count()` and the audit count unchanged.

- [ ] **Step 2: Run** `php artisan test --filter=MppSupplyPlanServiceTest` — Expected: FAIL, method `save` not found.

- [ ] **Step 3: Implement `save()`.** Validate shape (exact key sets, each value `is_int` or a digit string cast to int, within 0–999), year range from `ManPowerPlanningService` constants, and reason (trimmed, ≥ 10) before opening the transaction. Inside `DB::transaction()`: lock the year's rows (`lockForUpdate`), compare stored `matrixFor()` with `$expected` (stale guard), upsert all 48 cells with `updated_by`, then one `MasterAuditLog::record()` per changed cell. Action is `mpp_supply.created` when the year had no rows before the save.

- [ ] **Step 4: Run** — Expected: PASS.

- [ ] **Step 5: Pint and commit** `feat(mpp): save the supply plan with validation, stale-edit guard and audit`.

---

### Task 3: MPP matrix reads the plan; cumulative T+1; shortage/surplus

**Files:**
- Modify: `app/Services/ManPowerPlanningService.php`
- Modify: `app/Livewire/Admin/ManPowerPlanning.php` (use the range constants; drilldown `pasokan` for T+1)
- Test: `tests/Feature/ManPowerPlanningTest.php`

**Interfaces:**
- Consumes: `MppSupplyPlanService::matrixFor()`, `hasPlan()`, `ManPowerPlanningService::PLANNING_YEAR_MIN/MAX`.
- Produces: rows 9 and 10 carry `shortage_total`, `surplus_total` (non-negative ints) and `levels[rm]['shortage']`, `levels[rm]['surplus']`; `stats` adds `total_kekurangan`, `total_surplus`, `supply_plan_missing` (bool) and `supply_plan_missing_years` (list<int>). `grand_total`, `subtotal`, `total_kebutuhan` stay net.

- [ ] **Step 1: Write the failing tests**
  - Update `test_baseline_kebutuhan_follows_the_formula_and_totals_equal_the_cells` to take supply from `app(MppSupplyPlanService::class)->matrixFor(2026)` (and `matrixFor(2027)` for T+1: subtract `S_T + S_T1`).
  - `test_supply_rows_follow_the_plan_of_the_planning_year`: save a 2028 plan with `rbb.RM-3.TAN = 9`; `getMppMatrix('live', 2028)['matrix'][8]['levels']['RM-3']['fields']['TAN'] === 9` and row 8 label `'RBB 2028'`.
  - `test_kebutuhan_t1_subtracts_t_and_t1_supply`: 2026 plan seeded; save 2027 with `ckp.RM-3.KEU = 3`; row 10 RM-3 KEU equals `F − R + P_T + P_T1 − (S_T + 3)` using the matrix's own rows 1, 2, 4, 5 and the two plans; row 9 unchanged.
  - `test_shortage_and_surplus_totals_are_split`: for baseline 2026, `shortage_total === sum of positive cells`, `surplus_total === sum of |negative cells|`, `shortage_total − surplus_total === grand_total`, same per RM level; `stats['total_kekurangan']` and `stats['total_surplus']` equal row 9's values. Add a second case: save a 2028 plan with every RBB RM-1..RM-3 cell at 999 → row 9 `shortage_total === 0`, `surplus_total > 0`, `grand_total < 0`.
  - `test_unplanned_year_counts_supply_as_zero_and_flags_it`: `getMppMatrix('live', 2029)`: rows 6–8 all zero, `stats['supply_plan_missing'] === true`, `supply_plan_missing_years === [2029, 2030]`; for 2026 it is `[2027]`.
  - `test_drilldown_formula_for_t1_uses_cumulative_supply`: with the 2027 plan above, `openDrilldown('RM-3','KEU','Kebutuhan 2027')` sets `drilldownFormula['pasokan']` to `S_T + S_T1` for that cell.

- [ ] **Step 2: Run** `php artisan test --filter=ManPowerPlanningTest` — Expected: the new tests FAIL.

- [ ] **Step 3: Implement.** Inject `MppSupplyPlanService` like `FormasiCatalog` (nullable constructor arg, container fallback). Remove `prioritas_diisi`, `ckp_internal`, `talent_scouting`, `rbb_2026` from `getBaselineData()`. In `getMppMatrix()` read `$planT = matrixFor($yearT)` and `$planT1 = matrixFor($yearT1)`; rows 3, 6, 7, 8 use `$planT`; Kebutuhan T+1 subtracts both years' pasokan. Compute the split per RM and per row for rows 9 and 10. Component: replace `MIN_YEAR`/`MAX_YEAR` with the service constants; in `openDrilldown()` compute `pasokan` for T+1 rows from rows 6–8 plus the T+1 plan via the service (add `ManPowerPlanningService::supplyFor(int $year, string $rm, string $bidang): int`).

- [ ] **Step 4: Run** `php artisan test --filter="ManPowerPlanningTest|MppSupplyPlanServiceTest"` — Expected: PASS, existing figures 451 / 344 / 86 unchanged.

- [ ] **Step 5: Pint and commit** `feat(mpp): read supply from the plan, cumulative T+1 supply, split shortage and surplus`.

---

### Task 4: MPP screen — split totals, KPI, notice, CSV

**Files:**
- Modify: `resources/views/livewire/admin/man-power-planning.blade.php`
- Modify: `app/Livewire/Admin/ManPowerPlanning.php` (`#[On('mpp-supply-plan-saved')]` refresh; CSV columns)
- Test: `tests/Feature/ManPowerPlanningTest.php`

**Interfaces:**
- Consumes: Task 3 `stats` and row keys.
- Produces: the matrix listens for `mpp-supply-plan-saved`; a "Kelola Rencana Pasokan" button that dispatches `open-mpp-supply-plan-editor` with `year`.

- [ ] **Step 1: Write the failing tests**
  - `test_kebutuhan_kpi_shows_shortage_surplus_and_net`: render baseline; `assertSee` the formatted `total_kekurangan`, `'Surplus '.total_surplus`, and `'Bersih '.total_kebutuhan`.
  - `test_missing_plan_notice_is_shown`: `planningYear` 2026 → `assertSee('Rencana pasokan 2027 belum diisi. Angka pasokan dihitung 0.')`; after saving a 2027 plan the text is gone.
  - `test_mpp_export_csv_has_shortage_and_surplus_columns`: capture the streamed CSV (`$component->effects['download']` or call the response callback) and assert header contains `TOTAL KEKURANGAN` and `TOTAL SURPLUS`; the `Kebutuhan 2026` line carries row 9's values; the `Realisasi` line has those two columns empty.
  - `test_matrix_refreshes_when_the_plan_is_saved`: dispatch `mpp-supply-plan-saved` after changing the plan in DB → new RBB value visible.

- [ ] **Step 2: Run** — Expected: FAIL.

- [ ] **Step 3: Implement.** Kebutuhan rows: JUMLAH and TOTAL cells render shortage (`text-warning-800 font-bold`), surplus (`text-primary-700`) and net (`text-[10px] text-surface-500`), each labelled for screen readers. KPI card "Kebutuhan" value = `total_kekurangan`, subtitle `Surplus {x} · Bersih {y}`. Notice (warning tone, existing alert pattern) above the table when `supply_plan_missing`, naming `supply_plan_missing_years` and holding a button that dispatches the open event. Button in toolbar tier 1 next to Export (DESIGN.md §8.1).

- [ ] **Step 4: Run** `php artisan test --filter="ManPowerPlanningTest|ViewDesignGuardTest"` — Expected: PASS.

- [ ] **Step 5: Pint and commit** `feat(mpp): show shortage, surplus and missing-plan notice on the matrix`.

---

### Task 5: Supply plan editor drawer

**Files:**
- Create: `app/Livewire/Admin/MppSupplyPlanEditor.php`
- Create: `resources/views/livewire/admin/mpp-supply-plan-editor.blade.php`
- Modify: `resources/views/livewire/admin/man-power-planning.blade.php` (mount `<livewire:admin.mpp-supply-plan-editor />` once)
- Test: `tests/Feature/MppSupplyPlanEditorTest.php`

**Interfaces:**
- Consumes: `matrixFor()`, `save()`, `MppPlanRuleViolationException`, event `open-mpp-supply-plan-editor` (`year`).
- Produces: public state `bool $open`, `int $year`, `array $cells`, `array $expected`, `bool $confirming`, `string $reason`; actions `openEditor(int $year)` (`#[On]`), `review()`, `save()`, `close()`; dispatches `mpp-supply-plan-saved` and a `toast`.

- [ ] **Step 1: Write the failing tests**
  - `test_editor_loads_the_plan_of_the_requested_year`: dispatch open for 2026 → `cells['ckp']['RM-3']['TAN'] === 7`, `open === true`, sees `Rencana Pasokan Talenta 2026`.
  - `test_review_summarises_changes_before_saving`: change one cell, call `review` → `confirming === true`, sees `1 sel berubah` and the CKP total old → new.
  - `test_save_with_reason_persists_and_dispatches_the_event`: set reason `'Penyesuaian hasil rapat SDM'`, call `save` → value stored, `assertDispatched('mpp-supply-plan-saved')`, `open === false`.
  - `test_invalid_input_keeps_values_and_writes_nothing`: set a cell to `'abc'`, then `-1`, then `1000` → `save` shows the safe error, cell keeps the typed value, `MasterAuditLog` count unchanged.
  - `test_stale_edit_shows_the_message`: change DB after open → `save` shows `Rencana pasokan 2026 telah diubah oleh pengguna lain. Muat ulang lalu ulangi perubahan.`
  - `test_editor_ignores_out_of_range_years`: open 1999 → `open === false`.

- [ ] **Step 2: Run** `php artisan test --filter=MppSupplyPlanEditorTest` — Expected: FAIL, component not found.

- [ ] **Step 3: Implement.** Drawer matches the existing drilldown drawer markup; one block per source with an RM × bidang grid of `<input type="number" min="0" max="999" wire:model="cells.{source}.{rm}.{bidang}">` (no `.live`), row and block totals; `review()` computes changed-cell count and per-source totals; confirm panel with required reason textarea; `save()` calls the service inside `try/catch (MppPlanRuleViolationException)` and adds the message to an error bag. `wire:loading` on review and save buttons. Copy in Indonesian business terms.

- [ ] **Step 4: Run** `php artisan test --filter="MppSupplyPlanEditorTest|ViewDesignGuardTest"` — Expected: PASS.

- [ ] **Step 5: Pint and commit** `feat(mpp): add the supply plan editor drawer`.

---

### Task 6: Documentation and full verification

**Files:**
- Modify: `DOMAIN.md` §8 and §9.1, `DESIGN.md` §8.1, `SECURITY.md` §5

- [ ] **Step 1: Update docs** per spec §7: the plan table and sources, cumulative T+1 formula, shortage/surplus split, `MppSupplyPlanService` contract (`matrixFor`, `hasPlan`, `save`), the editor drawer and notice, and the audit of plan changes in `master_audit_logs`. Replace the §9.1 sentence that supply "tetap berupa baseline".
- [ ] **Step 2: Run** `./vendor/bin/pint --test` — Expected: no files need fixing.
- [ ] **Step 3: Run** `php artisan test` — Expected: all green; report the deprecation count as before (7) and any failure by name.
- [ ] **Step 4: Commit** `docs(mpp): document the supply plan and shortage/surplus split`.
