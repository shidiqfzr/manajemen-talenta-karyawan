# MPP Monthly Period Close ("Tutup Periode MPP") — Design

Status: draft for review · Date: 2026-11-06 · Branch: `feat/employee-form-formasi-alignment` (or a new branch cut from it)
Depends on: the single-source MPP (`c278fb0`, `807d227`): `ManPowerPlanningService::getMppMatrix(int $year, ?int $month)`, position-date reads, supply plan, shared drilldown.

## 1. Goal

The MPP matrix is computed live from today's employee records. For a past month the system can only rebuild the position from current data (start date, MBT, contract end), so people transferred since then are counted in their current RM band and bidang. Enterprise HRIS solves this with a monthly period close: the matrix is frozen at month-end, signed off by HR, and every later view of that month shows what was recorded then.

Success criteria
- Every past month after go-live has a draft captured automatically; HR closes it with one action.
- A closed month renders exactly as captured, including drilldowns, supply breakdown and Kebutuhan formula, regardless of later changes to employees, master seats or the supply plan.
- Closing and reopening are traceable (who, when, reason, revision) and run in one transaction with their audit row.
- The screen always says which kind of number the user is looking at: closed, draft, live, or a reconstruction of a month that was never closed.
- No personal data beyond what the drilldown needs is copied into snapshots (UU PDP data minimisation).

## 2. Decisions (from brainstorming)

| # | Decision |
|---|---|
| 1 | **Period close is designed before promotion supply.** |
| 2 | **Auto draft, admin confirms.** A scheduled job captures a draft after month-end; an admin closes it. |
| 3 | **Reopen with reason, re-close.** A closed month can be reopened with a required reason; it returns to draft at revision + 1. Only the latest revision is kept; history of closes and reopens lives in the audit trail. |
| 4 | **Numbers + employee references.** The snapshot stores the full matrix and one row per counted employee with NIK, the cell, and jabatan and unit at that time. No name, birth date or other personal data is copied. |
| 5 | **Approach A**: one period row with a JSON copy of the matrix, plus a normalised members table for drilldowns. |
| 6 | **No backfill** of months before go-live. |

## 3. Data model

`mpp_periods` (one row per month):

| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `year`, `month` | unsigned smallint, unsigned tinyint | planning year T and position month; unique (`year`, `month`) |
| `status` | string(10) | `draft` or `closed` |
| `revision` | unsigned smallint | starts at 1; + 1 on every reopen |
| `snapshot` | json | the `getMppMatrix($year, $month)` result: `matrix`, `stats`, `supply_breakdown`, `levels`, `fields` |
| `captured_at` | timestamp | last capture (automatic or "Perbarui Draf") |
| `captured_by` | FK users, nullable, null on delete | null when captured by the scheduler |
| `closed_at`, `closed_by` | timestamp / FK users, nullable | set on close, cleared on reopen |
| `close_note` | text, nullable | optional note on close |
| timestamps | | |

`mpp_period_members` (who stood behind each personnel cell):

| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `mpp_period_id` | FK `mpp_periods`, cascade on delete | |
| `scope` | string(30) | drill scope: `realisasi`, `pensiun_t`, `pensiun_t1`, `advisor`, `diperban`, `supply_ckp`, `supply_talent_scouting`, `supply_rbb` |
| `rm_level`, `bidang` | string(5), string(3) | the cell |
| `employee_nik` | string(8) | plain value, **no foreign key**: the snapshot must survive later changes to the employee master |
| `jabatan`, `unit_kerja` | string | as recorded at capture (job data; low sensitivity per SECURITY.md) |
| `tanggal_penempatan`, `tanggal_mbt` | date, nullable | dates shown in the drawer at capture |

Index (`mpp_period_id`, `scope`, `rm_level`, `bidang`). Capturing replaces all members of the period inside the same transaction.

Models: `App\Models\MppPeriod` (casts `snapshot` to array, `STATUS_DRAFT`, `STATUS_CLOSED`, relation `members()`), `App\Models\MppPeriodMember`.

## 4. Services

`ManPowerPlanningService` gains:
- `getPositionMembers(int $year, int $month): list<array{scope, rm_level, bidang, employee_nik, jabatan, unit_kerja, tanggal_penempatan, tanggal_mbt}>` — every personnel cell's members for the position date, computed from the same cached `presentEmployees()` read and the same scope rules as `getDrilldownEmployees()`, so snapshot members always equal the cell counts.

New `App\Services\Mpp\MppPeriodService`, the only write path for periods:

| Method | Behaviour |
|---|---|
| `find(int $year, int $month): ?MppPeriod` | Period for the month, if any. |
| `capture(int $year, int $month, ?User $user): MppPeriod` | Creates or refreshes a **draft**: stores `getMppMatrix($year, $month)` and `getPositionMembers()` in one transaction. Refused for a closed period, the current month, or a future month. Audit `mpp_period.captured`. |
| `close(int $year, int $month, ?string $note, User $user): MppPeriod` | Draft → closed with `closed_at`, `closed_by`, `close_note`. Closes what was captured (no silent recapture). Audit `mpp_period.closed`. |
| `reopen(int $year, int $month, ?string $reason, User $user): MppPeriod` | Closed → draft, `revision + 1`, clears close fields. Reason required (≥ 10 characters, same rule as the supply plan). Audit `mpp_period.reopened` with the reason. |

Rules (each violation throws `MppPeriodRuleViolationException`, safe message for admins):
- Only months strictly before the current month can be captured or closed.
- Year within `ManPowerPlanningService::PLANNING_YEAR_MIN..MAX`.
- Close requires an existing draft; reopen requires a closed period.
- Concurrent capture or close of the same month: unique key plus `lockForUpdate`, transaction retried on deadlock (same pattern as `MppSupplyPlanService::save`).

Audit rows go to `master_audit_logs` with entity type `mpp_period`, label `Periode MPP {Bln YYYY}`, old/new values `{status, revision}`. `MasterAuditLog` gains the action and entity labels (`Draf periode MPP direkam`, `Periode MPP ditutup`, `Periode MPP dibuka kembali`; `Periode MPP`).

## 5. Scheduler

Console command `hris:mpp-capture-period {--year=} {--month=}` in `routes/console.php`, next to the existing `hris:*` commands. Without options it captures the **previous month**. It skips a month that is already closed and prints the result. Scheduled `monthlyOn(1, '01:00')`. Captures by the scheduler have `captured_by = null` and show as "sistem" on screen.

## 6. Screens

MPP component (`ManPowerPlanning`) chooses the source for the selected Tahun + Posisi per; it does not compute anything itself:

| Month state | Matrix source | Banner (above the matrix) | Actions (admin) |
|---|---|---|---|
| Closed | snapshot | "Periode {Nov 2026} ditutup oleh {nama} · {tanggal jam} · Revisi {n}" (+ note) | **Buka Kembali** (reason required) |
| Draft | snapshot | "Draf periode {Nov 2026} direkam {oleh sistem/nama} · {tanggal}. Belum ditutup." | **Perbarui Draf**, **Tutup Periode** (optional note) |
| Past, never captured | live | "Periode {bulan} belum pernah ditutup: angka dihitung dari data pegawai saat ini." | **Rekam Draf** |
| Current or future month | live | none (as today) | none |

- Drilldowns in a snapshot month read `mpp_period_members` (names looked up from the employee master by NIK; "tidak lagi terdaftar" when missing) and the snapshot's supply breakdown and formula. Counts always equal the snapshot cells.
- The supply plan editor and the missing-plan notice are hidden for snapshot months; editing the plan never changes a closed month.
- CSV export from a snapshot month: `mpp_perencanaan_formasi_{T}_{T+1}_posisi_{YYYY-MM}_ditutup_rev{n}_….csv` (draft: `_draf`), and the export log records the period status and revision.
- Close and reopen use a confirmation panel in the existing drawer style with explicit loading states; reopen shows the required reason field. All text in business terms (AGENTS.md rule 6).

## 7. Security and governance

- Admin only: every action checks the role inside the component (Livewire does not re-run `role:admin`).
- Snapshot members contain NIK and job data only; no names, birth dates or family data are copied. Snapshots are kept for the life of the system as the official monthly record; deleting employees does not delete snapshot rows.
- All writes are transactional with their audit row; `snapshot` is never edited in place except by capture of a draft.

## 8. Testing

One test class per system under test (AGENTS.md rule 5):
- `tests/Feature/MppPeriodServiceTest.php`: capture stores matrix and members equal to the live matrix at capture; later changes to employees, seats and plan do not change a captured period; capture refused for the current or a future month and for a closed period; close and reopen transitions, revision increment, reason rule, audit rows; members per cell equal cell values.
- `tests/Feature/MppCapturePeriodCommandTest.php`: captures the previous month by default; skips a closed month; options select a month.
- `tests/Feature/ManPowerPlanningTest.php` (extended): banner and source per month state; closed month renders the snapshot after employee data changes; drilldown in a closed month lists snapshot members with their historical jabatan; plan editor hidden for snapshot months; export filename and log; non-admin cannot close or reopen.
- `ManPowerPlanningService::getPositionMembers()` counts equal the matrix cells for every personnel scope.

Pint on every changed file; full suite green; front-end assets rebuilt and checked in a real browser (ENGINEERING.md).

## 9. Documentation

- DOMAIN.md §8: period close lifecycle, what a snapshot holds, reconstruction warning; §9.1a: `MppPeriodService` contract.
- DESIGN.md §8.1: banners and actions per month state.
- SECURITY.md §5: period close audit; snapshot data minimisation and retention.

## 10. Out of scope

- Two-person approval of a close.
- Trend charts across periods.
- RM-1/RM-2 promotion supply (next design).
- Backfilling months before go-live.
- Keeping every revision side by side (only the latest revision is stored; the audit trail records each close and reopen).
