# MPP Monthly Period Close Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Freeze the MPP matrix per month (auto draft, admin close, reopen with reason) so past months show what was recorded, with drilldowns from the snapshot.

**Architecture:** `mpp_periods` stores one JSON copy of `getMppMatrix()` per month; `mpp_period_members` stores NIK, cell, jabatan and unit per counted employee. `MppPeriodService` is the only write path (capture, close, reopen) and audits to `master_audit_logs`. A monthly console command captures drafts. The `ManPowerPlanning` component picks snapshot or live per month and shows the state banner and actions.

**Tech Stack:** Laravel 11, Livewire 3, PHPUnit 11, Laravel Pint, Tailwind (assets rebuilt with `npm run build`).

**Spec:** `docs/superpowers/specs/2026-11-06-mpp-period-close-design.md`

## Global Constraints

- Statuses: `draft`, `closed`. Revision starts at 1, + 1 on each reopen. Unique (`year`, `month`).
- Capture and close only for months strictly before the current month; year within `ManPowerPlanningService::PLANNING_YEAR_MIN..MAX`.
- Reopen reason ≥ 10 characters (trimmed). Close note optional.
- Snapshot members: `employee_nik` string(8) without foreign key; `jabatan`, `unit_kerja`, `tanggal_penempatan`, `tanggal_mbt`; no name or birth date.
- Audit: `master_audit_logs`, entity type `mpp_period`, label `Periode MPP {Bln YYYY}` (Indonesian short month, e.g. `Periode MPP Okt 2026`), actions `mpp_period.captured|closed|reopened`, values `{status, revision}`.
- Exact UI copy: `Periode {Bln YYYY} ditutup oleh {nama} · {dd Bln YYYY HH:ii} · Revisi {n}`; `Draf periode {Bln YYYY} direkam oleh {nama|sistem} · {dd Bln YYYY HH:ii}. Belum ditutup.`; `Periode {Bln YYYY} belum pernah ditutup: angka dihitung dari data pegawai saat ini.`; buttons `Tutup Periode`, `Perbarui Draf`, `Rekam Draf`, `Buka Kembali`; missing member name `tidak lagi terdaftar`.
- Exception messages (exact): `Periode berjalan atau mendatang belum dapat direkam atau ditutup.`; `Periode {Bln YYYY} sudah ditutup. Buka kembali terlebih dulu.`; `Periode {Bln YYYY} belum memiliki draf.`; `Periode {Bln YYYY} belum ditutup.`; `Alasan membuka kembali wajib diisi minimal 10 karakter.`
- Multi-table writes in `DB::transaction()` (3 attempts); admin check inside every Livewire action; UI text in business terms (AGENTS.md rule 6); one test class per SUT; Pint; colours from existing tokens; `npm run build` after view changes.
- Commit only with the user's go-ahead; messages end with `Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>`.

## Review Focus

1. A draft captured while data is incomplete, then refreshed: refresh replaces members, never duplicates (Task 2 `test_recapture_replaces_members`).
2. An employee deleted after the month was closed: the closed-month drawer still lists them as `tidak lagi terdaftar` (Task 4 `test_closed_month_drawer_survives_deleted_employees`).
3. An employee transferred after close: the closed-month drawer shows the jabatan and unit recorded at capture (Task 4 `test_closed_month_shows_historical_jabatan`).
4. Reopen with an empty or short reason: refused, period stays closed, no audit row (Task 2 `test_reopen_requires_a_reason`).
5. Capturing a closed month or the current month: refused, snapshot unchanged (Task 2 `test_capture_is_refused_for_closed_current_and_future_months`).

---

### Task 1: Snapshot tables, models, member read, T+1 supply in the matrix

**Files:**
- Create: `database/migrations/2026_11_07_100000_create_mpp_periods_tables.php`
- Create: `app/Models/MppPeriod.php`, `app/Models/MppPeriodMember.php`
- Modify: `app/Services/ManPowerPlanningService.php`
- Modify: `app/Livewire/Admin/ManPowerPlanning.php` (formula T+1 supply from the matrix)
- Test: `tests/Feature/ManPowerPlanningTest.php`

**Interfaces:**
- Produces: `MppPeriod` (`STATUS_DRAFT='draft'`, `STATUS_CLOSED='closed'`, casts `snapshot` array, `closed_at`/`captured_at` datetime; relations `members()`, `closer()`, `capturer()`), `MppPeriodMember`.
- Produces: `ManPowerPlanningService::getPositionMembers(int $year, int $month): list<array{scope: string, rm_level: string, bidang: string, employee_nik: string, jabatan: ?string, unit_kerja: ?string, tanggal_penempatan: ?string, tanggal_mbt: ?string}>` (dates `Y-m-d`).
- Produces: `getMppMatrix()` result gains `supply_t1` (`[rm][bidang] => int`, planned CKP+TS+RBB for T+1).
- Produces: `ManPowerPlanningService::dateLabel(?Carbon $date): string` becomes public.

- [ ] **Step 1: Write the failing tests**
  - `test_position_members_match_every_personnel_cell`: employees as in `test_every_personnel_cell_opens_exactly_its_count` plus a CKP placement; for every row whose `drill` is a personnel or `supply_*` scope, the number of members with that scope/RM/bidang equals the cell value (for `supply_*`: equals `supply_breakdown[...]['terealisasi']`); members carry `jabatan` and `unit_kerja`.
  - `test_matrix_exposes_planned_supply_for_t1`: `savePlan(2027, ['ckp.RM-3.KEU' => 3])` → `getMppMatrix(2026, 11)['supply_t1']['RM-3']['KEU'] === 3`.
- [ ] **Step 2: Run** `php artisan test --filter=ManPowerPlanningTest` — Expected: the two tests FAIL (method/key missing).
- [ ] **Step 3: Implement.** Migration per spec §3 (both tables, unique (`year`,`month`), member index, `captured_by`/`closed_by` FK users nullOnDelete, members FK `mpp_period_id` cascade). Models. `getPositionMembers()` iterates the cached `presentEmployees()` once and emits one member per scope the employee belongs to, using the same predicates as `getDrilldownEmployees()` (extract those predicates into one private `scopesFor(array $row, int $year): list<string>` used by both). Add `supply_t1` to `getMppMatrix()`. Component formula T+1 uses `$mpp['supply_t1'][$rm][$bidang]` instead of `supplyFor()`.
- [ ] **Step 4: Run** `php artisan test --filter="ManPowerPlanningTest|MppSupplyPlan"` — Expected: PASS.
- [ ] **Step 5: Pint, commit** `feat(mpp): add period snapshot tables and per-cell member read`.

---

### Task 2: MppPeriodService (capture, close, reopen) with audit

**Files:**
- Create: `app/Exceptions/MppPeriodRuleViolationException.php` (same pattern as `MppPlanRuleViolationException`)
- Create: `app/Services/Mpp/MppPeriodService.php`
- Modify: `app/Models/MasterAuditLog.php` (labels from Global Constraints; `ENTITY_LABELS['mpp_period'] = 'Periode MPP'`; `FIELD_LABELS` `status` → `Status Periode`, `revision` → `Revisi`)
- Test: `tests/Feature/MppPeriodServiceTest.php`

**Interfaces:**
- Consumes: Task 1 models, `getMppMatrix()`, `getPositionMembers()`, `monthLabel()`.
- Produces: `find(int $year, int $month): ?MppPeriod`; `capture(int $year, int $month, ?User $user): MppPeriod`; `close(int $year, int $month, ?string $note, User $user): MppPeriod`; `reopen(int $year, int $month, ?string $reason, User $user): MppPeriod`; `membersFor(MppPeriod $period, string $scope, string $rm, string $bidang): list<array>` (drawer row shape of `getDrilldownEmployees()`: `id, nik, nama, jabatan, unit_kerja, golongan, tanggal_penempatan, tanggal_mbt, tanggal_pensiun, status_karyawan`; jabatan/unit/dates from the snapshot, name/golongan/pensiun/status from the employee master, `nama = 'tidak lagi terdaftar'` and other master fields `—` when the NIK no longer exists).

- [ ] **Step 1: Write the failing tests** (Carbon test now `2026-11-05`):
  - `test_capture_freezes_matrix_and_members`: capture Oct 2026 → `status draft`, `revision 1`, `snapshot` equals `getMppMatrix(2026, 10)` at capture, member count per cell equals the cells; then change an employee's jabatan, add a seat kuota and save a plan change → `find(2026, 10)->snapshot` unchanged.
  - `test_recapture_replaces_members` (Review Focus 1): capture, add an employee, capture again → members equal the new cells, no duplicates, still `revision 1`.
  - `test_capture_is_refused_for_closed_current_and_future_months` (Review Focus 5): Nov 2026 and Dec 2026 → exception `Periode berjalan atau mendatang belum dapat direkam atau ditutup.`; a closed Oct 2026 → `Periode Okt 2026 sudah ditutup. Buka kembali terlebih dulu.`; snapshot unchanged.
  - `test_close_and_reopen_lifecycle`: close with note → `closed`, `closed_by`, `close_note`; reopen with reason → `draft`, `revision 2`, close fields null; audit actions in order `captured, closed, reopened`, reopen row carries the reason and `{status: closed, revision: 1} → {status: draft, revision: 2}`.
  - `test_close_requires_a_draft` and `test_reopen_requires_a_closed_period`: exact messages.
  - `test_reopen_requires_a_reason` (Review Focus 4): `null`, `''`, `'singkat'` → `Alasan membuka kembali wajib diisi minimal 10 karakter.`; still closed; no `reopened` audit row.
  - `test_members_for_reads_snapshot_and_master`: drawer row shows snapshot jabatan after the master changed; deleted employee → `tidak lagi terdaftar`.
- [ ] **Step 2: Run** `php artisan test --filter=MppPeriodServiceTest` — Expected: FAIL, class not found.
- [ ] **Step 3: Implement** per spec §4: each write in `DB::transaction(fn, 3)` with `lockForUpdate` on the period row; capture `updateOrCreate` the period then delete and bulk-insert members; audit row in the same transaction.
- [ ] **Step 4: Run** — Expected: PASS.
- [ ] **Step 5: Pint, commit** `feat(mpp): capture, close and reopen MPP periods with audit`.

---

### Task 3: Monthly capture command

**Files:**
- Modify: `routes/console.php` (command `hris:mpp-capture-period {--year=} {--month=}`; `Schedule::command('hris:mpp-capture-period')->monthlyOn(1, '01:00')`)
- Test: `tests/Feature/MppCapturePeriodCommandTest.php`

**Interfaces:**
- Consumes: `MppPeriodService::capture()`, `find()`.

- [ ] **Step 1: Write the failing tests** (now `2026-11-01 01:00`):
  - `test_captures_the_previous_month_by_default`: exit 0, output contains `Draf periode MPP Okt 2026 direkam.`, `find(2026, 10)` is a draft with `captured_by` null.
  - `test_skips_a_closed_month`: Oct closed → output `Periode MPP Okt 2026 sudah ditutup; dilewati.`, revision unchanged.
  - `test_options_select_a_month`: `--year=2026 --month=9` captures Sep.
  - `test_january_captures_december_of_the_previous_year`: now `2027-01-01` → `find(2026, 12)` exists.
  - `test_command_is_scheduled_monthly`: the schedule contains `hris:mpp-capture-period` with expression `0 1 1 * *`.
- [ ] **Step 2: Run** — Expected: FAIL (command not defined).
- [ ] **Step 3: Implement** the closure command next to the other `hris:*` commands; invalid options print the exception message and exit 1.
- [ ] **Step 4: Run** — Expected: PASS.
- [ ] **Step 5: Pint, commit** `feat(mpp): schedule monthly MPP draft capture`.

---

### Task 4: MPP screen — snapshot source, banners, close and reopen

**Files:**
- Modify: `app/Livewire/Admin/ManPowerPlanning.php`
- Modify: `resources/views/livewire/admin/man-power-planning.blade.php`
- Create: `resources/views/livewire/admin/partials/mpp-period-banner.blade.php`
- Test: `tests/Feature/ManPowerPlanningTest.php`

**Interfaces:**
- Consumes: `MppPeriodService` (all methods), `getMppMatrix()`.
- Produces: component state `periodAction` (`''|'close'|'reopen'`), `periodNote`, `periodReason`; actions `capturePeriod()`, `requestPeriodAction(string $action)`, `confirmPeriodAction()`, `cancelPeriodAction()`; view data `periodState` (`closed|draft|unclosed|live`) and `period` (`?MppPeriod`).

- [ ] **Step 1: Write the failing tests**:
  - `test_month_state_banner_and_source`: Oct 2026 never captured → `unclosed` banner text and `Rekam Draf`; capture → draft banner (`oleh sistem` when captured by the scheduler) with `Tutup Periode` and `Perbarui Draf`; close → closed banner with `Revisi 1` and `Buka Kembali`; Nov 2026 → no banner.
  - `test_closed_month_renders_the_snapshot_after_data_changes`: close Oct, then add employees → Oct still shows the captured Realisasi cell value.
  - `test_closed_month_shows_historical_jabatan` (Review Focus 3) and `test_closed_month_drawer_survives_deleted_employees` (Review Focus 2): open the Realisasi drawer on the closed month.
  - `test_close_and_reopen_from_the_screen`: `requestPeriodAction('close')` → confirm → closed; `requestPeriodAction('reopen')` with reason `'Koreksi mutasi susulan'` → draft revision 2; short reason shows the exception message and stays closed.
  - `test_snapshot_months_hide_the_plan_editor`: closed month → no `Kelola Rencana Pasokan`, no missing-plan notice.
  - `test_snapshot_export_is_labelled`: closed Oct export filename contains `_posisi_2026-10_ditutup_rev1_`; log context has `periode` `ditutup` and `revisi` 1.
  - `test_non_admin_cannot_change_periods`: `capturePeriod`, `confirmPeriodAction` → 403.
- [ ] **Step 2: Run** `php artisan test --filter=ManPowerPlanningTest` — Expected: new tests FAIL.
- [ ] **Step 3: Implement.** One private `mppData(): array` returns the snapshot when a period exists, else `getMppMatrix()`; render, drilldown and export use it. Drilldown personnel rows come from `membersFor()` when a period exists. Banner partial renders the four states with existing warning/primary tokens; confirm panel in the drawer style with `wire:loading` on confirm. Hide the plan button, notice and editor mount for snapshot months. Rebuild assets.
- [ ] **Step 4: Run** `php artisan test --filter="ManPowerPlanningTest|ViewDesignGuardTest"` — Expected: PASS.
- [ ] **Step 5: Pint, `npm run build`, commit** `feat(mpp): show and manage closed MPP periods on the matrix`.

---

### Task 5: Documentation and verification

**Files:** `DOMAIN.md` §8 and §9.1a, `DESIGN.md` §8.1, `SECURITY.md` §5

- [ ] **Step 1: Update docs** per spec §9.
- [ ] **Step 2: Run** `./vendor/bin/pint --test` — Expected: passed.
- [ ] **Step 3: Run** `php artisan test` — Expected: all green; report deprecations and any failure by name.
- [ ] **Step 4: Ask the user to check the four month states in a browser** (ENGINEERING.md).
- [ ] **Step 5: Commit** `docs(mpp): document the monthly period close`.
