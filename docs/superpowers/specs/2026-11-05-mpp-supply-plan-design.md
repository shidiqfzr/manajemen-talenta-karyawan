# MPP Supply Plan & Shortage/Surplus Split — Design

Status: draft for review · Date: 2026-11-05 · Branch: `feat/employee-form-formasi-alignment`
Depends on: the MPP fixes of 2026-11-05 (planning year drives T, MBT vacancy, pure Kebutuhan formula, shared `presentEmployees()`).

## 1. Goal

The supply side of the Man Power Planning matrix (CKP Internal, Talent Scouting, RBB, Jabatan Prioritas Diisi) is today a set of constants in `ManPowerPlanningService::getBaselineData()`. Changing a number needs a developer and a deploy, and "RBB {T}" shows the 2026 figures for every year. In addition, the Kebutuhan totals net surplus against shortage, so a surplus in one bidang hides a real need in another.

Success criteria
- An admin maintains the supply plan per planning year from the MPP screen, without a developer.
- Every changed figure has an audit row (who, when, old and new value, reason), written in the same transaction as the change.
- Kebutuhan T+1 accounts for the supply planned for T+1.
- The Kebutuhan totals and KPI show total shortage and total surplus separately; the net stays visible as a secondary figure.
- On the day of release the matrix shows the same numbers as today (the current constants become the 2026 plan).

## 2. Decisions (from brainstorming)

| # | Decision |
|---|---|
| 1 | **Manual plan per year**, entered by an admin. Not derived from `jalur_masuk`, which only records people already hired. |
| 2 | **Direct edit, fully audited**, no second approver. The table leaves room for an approval status later. |
| 3 | **One row per cell** in a new table (approach A), not a JSON document per year. |
| 4 | **Split totals and KPI** for shortage and surplus; cells stay signed, no new matrix rows. |
| 5 | **Cumulative supply for T+1**: Kebutuhan T+1 subtracts the supply of T and of T+1. |
| 6 | **Audit reuses `master_audit_logs`**, one row per changed cell, so the existing history screen at `/admin/master/riwayat` shows plan changes without new UI. |

## 3. Data model

New table `mpp_supply_plans`:

| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `year` | unsigned smallint | planning year |
| `source` | string(20) | `ckp`, `talent_scouting`, `rbb`, `prioritas` |
| `rm_level` | string(5) | `RM-1`, `RM-2`, `RM-3` |
| `bidang` | string(3) | `KEU`, `TAN`, `TEK`, `UMU` |
| `jumlah` | unsigned smallint | 0 to 999 |
| `updated_by` | FK users, nullable, null on delete | last editor |
| timestamps | | |

Unique key on (`year`, `source`, `rm_level`, `bidang`); index on `year`.

A year "has a plan" when it has rows. Saving always writes all 48 cells of the year (4 sources × 3 RM × 4 bidang) through an upsert, so a year is either fully planned or not planned at all.

The migration inserts the 2026 plan from the current constants (CKP RM-3 2/7/2/1; Talent Scouting RM-3 0/4/2/0; RBB RM-3 8/6/1/6; Prioritas RM-2 UMU 1 and RM-3 1/4/0/8; all other cells 0). This is reference data the matrix depends on, so it ships with the schema rather than a separately run seeder.

New model `App\Models\MppSupplyPlan` with `SOURCES` (key to business label: `ckp` → "CKP Internal", `talent_scouting` → "Talent Scouting", `rbb` → "RBB", `prioritas` → "Jabatan Prioritas Diisi").

## 4. Service

New `App\Services\Mpp\MppSupplyPlanService`, the only write path for the plan.

| Method | Behavior |
|---|---|
| `matrixFor(int $year): array` | `[source => [rm => [bidang => int]]]`, zero-filled for every cell; zeros when the year has no plan. |
| `hasPlan(int $year): bool` | Whether the year has rows. |
| `save(int $year, array $cells, array $expected, ?string $reason, User $user): int` | Validates, then in one `DB::transaction()` upserts all 48 cells and writes one `master_audit_logs` row per changed cell. Returns the number of changed cells; a save with no change writes nothing and returns 0. |

`save()` rules (each violation throws `MppPlanRuleViolationException` with a safe message, like `FormasiRuleViolationException`):
- Year within the planning range (2024 to 2031; the range moves from the Livewire component into a constant on `ManPowerPlanningService` so both share it).
- Every cell present, a whole number from 0 to 999; unknown source, RM or bidang keys are refused.
- Reason required, at least 10 characters (same rule as `MasterFormasiService`).
- **Stale-edit guard**: `$expected` holds the values the admin saw when opening the editor. If the stored plan differs, the save is refused with "Rencana pasokan {tahun} telah diubah oleh pengguna lain. Muat ulang lalu ulangi perubahan." This stops one admin silently overwriting another.

Audit row per changed cell: action `mpp_supply.updated` (or `mpp_supply.created` for the first save of a year), entity type `mpp_supply`, entity id = the row id, label such as `Rencana Pasokan 2026 — CKP Internal RM-3 KEU`, old and new `{'jumlah': n}`, and the reason. `MasterAuditLog` gains the matching `ACTION_LABELS`, `ENTITY_LABELS` (`mpp_supply` → "Rencana Pasokan MPP") and `FIELD_LABELS` (`jumlah` → "Jumlah") entries.

`ManPowerPlanningService` changes:
- Injects `MppSupplyPlanService` (constructor, nullable with container fallback, like `FormasiCatalog`).
- `getBaselineData()` drops `prioritas_diisi`, `ckp_internal`, `talent_scouting` and `rbb_2026`; the matrix reads them from the plan for T and T+1.
- Kebutuhan, per cell:
  - T: `Formasi − Realisasi + Pensiun T − Pasokan T`
  - T+1: `Formasi − Realisasi + Pensiun T + Pensiun T+1 − (Pasokan T + Pasokan T+1)`
  - `Pasokan` = CKP + Talent Scouting + RBB. Prioritas Diisi stays informational and is not in the formula.
- Supply rows 6 to 8 show the plan for T. Row 3 shows Prioritas Diisi for T.
- Rows 9 and 10 gain `shortage_total` (sum of positive cells) and `surplus_total` (sum of the absolute values of negative cells); each RM level of those rows gains `shortage` and `surplus` the same way. `grand_total` and `subtotal` stay the net value, so existing consumers keep working.
- `stats` gains `total_kekurangan`, `total_surplus` and `supply_plan_missing` (`true` when T or T+1 has no plan). `total_kebutuhan` stays the net.
- The drilldown formula for Kebutuhan T+1 shows `pasokan` as the sum of T and T+1 supply, so the breakdown matches the cell.
- Day-one effect: only 2026 is seeded, so for T = 2026 the T+1 supply is 0 and Kebutuhan T+1 equals today's value. The missing-plan notice names 2027 until an admin fills it in, which is accurate rather than a regression.
- No other code reads the removed baseline keys; only `ManPowerPlanningTest::test_baseline_kebutuhan_follows_the_formula_and_totals_equal_the_cells` does, and it switches to `MppSupplyPlanService::matrixFor()`.

## 5. Screens

**MPP matrix** (existing `livewire/admin/man-power-planning`):
- Toolbar tier 1 gains a "Kelola Rencana Pasokan" button next to Export (DESIGN.md §8.1: no separate toolbar card).
- When `supply_plan_missing`, a warning notice above the table: "Rencana pasokan {tahun} belum diisi. Angka pasokan dihitung 0." with a link that opens the editor.
- Kebutuhan rows: the JUMLAH and TOTAL cells show the shortage (bold, warning tone) and the surplus (primary tone) as two lines, with the net in small text below. Cells stay signed as today.
- KPI "Kebutuhan": main value is the total shortage; subtitle shows the surplus and the net.
- CSV export adds `TOTAL KEKURANGAN` and `TOTAL SURPLUS` columns, filled for the Kebutuhan rows and blank for the others.

**Supply plan editor** (new Livewire component `App\Livewire\Admin\MppSupplyPlanEditor`, opened as a drawer from the MPP screen):
- Header: "Rencana Pasokan Talenta {tahun}", with the year taken from the MPP filter.
- Grid: one block per source; inside it RM-1 to RM-3 as rows and KEU, TAN, TEK, UMU as columns, with row and block totals. Number inputs bound without `.live` (the values are checked on save, not per keystroke).
- Save opens a confirmation step listing the number of changed cells, the old and new total per source, and a required reason field. Confirm runs `save()` with explicit `wire:loading` states; a rule violation shows the safe message inline and keeps the admin's input.
- After a successful save the editor closes, dispatches `mpp-supply-plan-saved`, and the matrix re-renders.
- All text uses business terms; no internal file names (AGENTS.md rule 6). Colors use existing tokens only (`ViewDesignGuardTest`).

Access: both components sit on the admin MPP page behind the `role:admin` route middleware; `save()` takes the acting `User` for the audit row.

## 6. Testing

One test class per system under test (AGENTS.md rule 5):
- `tests/Feature/MppSupplyPlanServiceTest.php`: zero-filled matrix for an unplanned year; the migration's 2026 plan equals the old constants; save upserts 48 cells; audit row per changed cell and none for an unchanged save; each rule (range, bounds, unknown keys, reason, stale edit) throws, and nothing is written.
- `tests/Feature/MppSupplyPlanEditorTest.php`: editor loads the year's values; save with reason persists and dispatches the event; a violation keeps the input and shows the message.
- `tests/Feature/ManPowerPlanningTest.php` (extended): supply rows follow the plan of T; Kebutuhan T+1 subtracts the T and T+1 supply; shortage and surplus totals per row and per RM; KPI values; the missing-plan notice; the CSV has the two new columns. The existing baseline figures (451, 344, 86) stay unchanged.

Pint on every changed file; full suite green.

## 7. Documentation

- DOMAIN.md §8 and §9.1: the plan table, the cumulative T+1 formula, shortage and surplus, the new service contract.
- DESIGN.md §8.1: the editor drawer, the missing-plan notice, the split totals.
- SECURITY.md §5: supply plan changes are audited in `master_audit_logs`.

## 8. Out of scope

- An approval flow (draft, approved, locked).
- Supply for Karpel bands (RM-4 to RM-7) and the Reguler channel.
- Importing the plan from Excel, plan scenarios or versions.
- Separating gross shortage into new matrix rows.
