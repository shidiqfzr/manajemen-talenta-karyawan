# Organisation Structure Decree (SK) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Let admins adopt a new organisation structure from an SK workbook on a live database: review a diff, approve (four-eyes), stage until the effective date, apply atomically with an audit trail.

**Architecture:** A `StructureDecree` record stores the SK data, workbook hash, computed diff, unit mapping and a snapshot. A pure `StructureDiffCalculator` compares the parsed workbook with the current database. `StructureDecreeService` runs the lifecycle and applies changes by calling the existing `MasterFormasiService` operations inside one `DB::transaction()`. A daily command applies due approved decrees.

**Tech Stack:** Laravel 11, Livewire, PhpSpreadsheet (existing `FormasiExcelParser`), PHPUnit 11, Pint.

**Spec:** `docs/superpowers/specs/2026-11-16-structure-decree-design.md`

## Global Constraints

- Multi-table writes in `DB::transaction()`; Livewire search debounce `300ms`; format with `./vendor/bin/pint`.
- One test class per system under test (`AGENTS.md` rule 5). Do not split one SUT across files.
- UI text in Indonesian business terms; never show `AGENTS.md`, `DOMAIN.md`, `DESIGN.md`, `ENGINEERING.md`, `SECURITY.md` or `SSOT` to end users (rule 6). Use "Karyawan", "Formasi Jabatan" (rule 7). "kursi" and `seat` stay internal.
- New code, commits and chat in English; UI strings and SSOT prose in Indonesian.
- Authorization by permission, never by role name: `Gate`/`HrisPermission` with new abilities `struktur.prepare` and `struktur.approve` (both `['admin']` in `config('hris.permissions')`).
- Approval switch: `config('hris.structure.require_approval')`, env `HRIS_STRUCTURE_REQUIRE_APPROVAL`, default `false`, same semantics as `hris.import.require_approval`.
- SK number stored normalised: whitespace around `/` removed and trimmed, e.g. `DPSB/SKPTS/ 352 /IX/2026` becomes `DPSB/SKPTS/352/IX/2026`; unique; no strict format check.
- Effective date defaults to the SK date. Only one open decree (draft, submitted, approved or scheduled) at a time. Apply never moves employees. A unit absent from the workbook is never deactivated implicitly. Existing units keep `kode`, `wilayah`, `urutan`; the admin supplies them only for new units.
- Apply schedule: daily at 00:00, before `hr:apply-effective-mutations` (00:01). The app runs in `APP_TIMEZONE=UTC`, like every existing scheduled job, so 00:00 is 07:00 WIB and "today" is the UTC date; keep that consistency and do not change the timezone in this project.
- No undo of an applied SK. A wrong SK is corrected by a new SK (enterprise practice: decrees are amended, not erased). Cancelling is possible only before apply. Reason text on audit entries: `SK <number>` (at least 10 characters, as `MasterFormasiService` requires).
- Do not run `php artisan migrate:fresh` or the seeder against the user's own database; tests use `RefreshDatabase`.

## Review Focus

- Re-uploading a workbook identical to the current structure: empty diff, submit blocked with a clear message.
- SK number differing only by spaces (`DPSB/SKPTS/ 352 /IX/2026`) is rejected as a duplicate.
- A wrong file (not `.xlsx`, wrong headers, oversize) shows a validation error, never a 500.
- Seat removed or shrunk while employees hold it: quota set (to 0 if removed), employees untouched, listed on the worklist.
- Current structure edited by hand after approval: apply refuses (stale) and applies nothing.
- A failure halfway through apply (for example a unit name clash) rolls everything back and marks the decree failed.
- Effective date earlier than the latest applied decree's effective date is rejected.
- Jabatan Penampungan (Advisor, Diperbantukan) opened on units are not formasi: they never appear in the diff and survive apply.
- A workbook listing a unit that exists but is inactive reactivates it rather than failing on a duplicate name.
- Same admin submits and approves: rejected when approval is required, allowed when it is not.
- MPP: an SK effective on the 1st must not leak its structure into the previous month's captured period (Task 6b); closed periods stay unchanged.

## File Structure

| File | Responsibility |
|---|---|
| `database/migrations/2026_11_16_100000_create_structure_decrees_table.php` | `structure_decrees` table and `master_audit_logs.decree_id` |
| `app/Models/StructureDecree.php` | Statuses, labels, number normaliser, relations |
| `app/Services/Structure/StructureReader.php` | Reads the current structure from the database in the shared array shape |
| `app/Services/Structure/StructureDiff.php` | Readonly diff value object (changes, totals, digest) |
| `app/Services/Structure/StructureDiffCalculator.php` | Pure comparison, no database access |
| `app/Services/Structure/StructureDecreeService.php` | Lifecycle and apply |
| `app/Console/Commands/ApplyStructureDecreesCommand.php` | `hr:apply-structure-decrees` |
| `app/Livewire/Admin/Master/StructureDecreeIndex.php`, `StructureDecreeWizard.php`, `StructureDecreeDetail.php` | Screens |
| `resources/views/admin/master/sk/*.blade.php`, `resources/views/livewire/admin/master/structure-decree-*.blade.php` | Views |

Shared array shape ("structure"): `['units' => [unitName => ['kode' => ?string, 'wilayah' => ?string, 'urutan' => ?int, 'seats' => [jabatan => ['rm_level' => string, 'level' => string, 'bidang' => string, 'kuota' => int]]]]]`.

---

### Task 1: SSOT rules and documentation (Phase 1)

**Files:**
- Modify: `DOMAIN.md` (new §3.7, cross-reference from §3.6 butir 2 and 12), `AGENTS.md`, `SECURITY.md`, `ENGINEERING.md`, `DESIGN.md`

**Interfaces:**
- Produces: the rule text later tasks cite. Rule names must match the code: statuses `Draf`, `Diajukan`, `Disetujui`, `Terjadwal`, `Diterapkan`, `Ditolak`, `Dibatalkan`, `Perlu Tinjau Ulang`, `Gagal`.

- [ ] **Step 1: Back up the five files** to the scratchpad directory before editing (a script once truncated `DOMAIN.md`).
- [ ] **Step 2: Add §3.7 "Tata Kelola Revisi Struktur Organisasi (SK)" to `DOMAIN.md`** in Indonesian with the five rules from spec §5.9, the lifecycle, the SK 352 baseline (`DPSB/SKPTS/352/IX/2026`, 1 September 2026, effective the same day, all 47 units adopted), the one-open-decree rule and the staleness rule. State the MPP behaviour too: captured periods keep their snapshot; the previous month is frozen before an SK applies; a past month that was never captured is still reconstructed from the current structure (existing limitation, no backfill); the live MPP and Standar Formasi change on the effective date. Update the MPP section of `DOMAIN.md` with a cross-reference. Add one-line cross-references in §3.6 butir 2 (seeder and `formasi:build-seed` are for fresh installs only) and butir 12.
- [ ] **Step 3: `AGENTS.md`:** add one bullet under pillar 1 pointing to §3.7 and a new "Aturan Perilaku" item 8: structure changes only via an SK through the SK service; the database is the source of truth; never edit `master_formasi.json` or rerun the seeder on a kept database. Keep every existing item intact.
- [ ] **Step 4: `SECURITY.md`** (SK workbook and PDF upload: MIME and size validation, retained as evidence, no personal data; four-eyes; audit link via `decree_id`), **`ENGINEERING.md`** (`hr:apply-structure-decrees` at 00:00 before mutations; apply inside `DB::transaction()`; the two permissions), **`DESIGN.md`** (status badges and the diff and impact panels).
- [ ] **Step 5: Verify.** Run `Select-String` for `352/IX/2026` and `3.7` across the five files; confirm none of the new UI-facing terms name an internal file. Expected: matches in `DOMAIN.md`; no leaked file names in any Blade view (none exist yet).
- [ ] **Step 6: Commit** `docs: add SK structure governance rules to the SSOT files`.

---

### Task 2: Schema, model, permissions, audit context

**Files:**
- Create: `database/migrations/2026_11_16_100000_create_structure_decrees_table.php`, `app/Models/StructureDecree.php`
- Modify: `app/Models/MasterAuditLog.php`, `config/hris.php`, `app/Providers/AppServiceProvider.php` only if permissions need registration (check where `HrisPermission::register()` is called)
- Test: `tests/Feature/StructureDecreeTest.php`

**Interfaces:**
- Produces:
  - `StructureDecree` constants `STATUS_DRAFT='draft'`, `STATUS_SUBMITTED='submitted'`, `STATUS_APPROVED='approved'` (staged; shown as "Terjadwal" when `effective_date` is in the future), `STATUS_APPLIED='applied'`, `STATUS_REJECTED='rejected'`, `STATUS_CANCELLED='cancelled'`, `STATUS_STALE='stale'`, `STATUS_FAILED='failed'`; `OPEN_STATUSES` = draft, submitted, approved, stale; `STATUS_LABELS` (Indonesian).
  - `StructureDecree::normalizeNumber(string $number): string`.
  - Casts: `sk_date`, `effective_date`, `submitted_at`, `decided_at`, `applied_at` as date or datetime; `diff`, `unit_mapping`, `snapshot` as array. Relations `submitter()`, `decider()` to `User`.
  - `MasterAuditLog::forDecree(int $decreeId, callable $callback): mixed` sets an ambient decree id for the callback; `MasterAuditLog::record()` writes it to `decree_id` when set. Add action labels `decree.submitted|approved|rejected|cancelled|applied|failed` and entity label `decree` => `Surat Keputusan Struktur`.
  - Config `hris.structure` = `['require_approval' => ...]`; permissions `struktur.prepare`, `struktur.approve` in `hris.permissions`.
- Columns per spec §5.7: `sk_number` (unique), `title`, `revokes` (nullable text), `note` (nullable text), `sk_date`, `effective_date`, `status`, `source_path`, `source_sha256`, `pdf_path` nullable, `target` json nullable, `diff` json nullable, `unit_mapping` json nullable, `review_digest` nullable, `snapshot` json nullable, `submitted_by`/`decided_by` nullable foreign ids to `users`, `submitted_at`, `decided_at`, `decision_reason` nullable, `applied_at` nullable, `failure_message` nullable, timestamps. `master_audit_logs.decree_id` nullable foreign id with `nullOnDelete`.

- [ ] **Step 1: Write failing tests** in `StructureDecreeTest`:
  - `test_normalize_number_removes_spaces_around_slashes`: `StructureDecree::normalizeNumber(' DPSB/SKPTS/ 352 /IX/2026 ')` equals `'DPSB/SKPTS/352/IX/2026'`.
  - `test_for_decree_links_master_audit_entries`: inside `MasterAuditLog::forDecree($id, fn () => MasterAuditLog::record(...))` the row has `decree_id === $id`; a record outside the callback has `null`; the ambient id is cleared even if the callback throws.
  - `test_struktur_permissions_belong_to_admin_only`: an admin user passes `Gate::allows('struktur.prepare')` and `'struktur.approve'`; a `user` role fails both.
- [ ] **Step 2: Run** `php artisan test --filter=StructureDecreeTest` and confirm failure.
- [ ] **Step 3: Implement** the migration, model, `MasterAuditLog` change and config as specified. The ambient id is a static property reset in a `finally`.
- [ ] **Step 4: Run** the filter, then `php artisan test --filter=MasterFormasiServiceTest` (audit regression). Expected: all pass.
- [ ] **Step 5: Pint and commit** `feat(structure): add decree schema, model, permissions and audit link`.

---

### Task 3: Structure reader and diff calculator

**Files:**
- Create: `app/Services/Structure/StructureReader.php`, `StructureDiff.php`, `StructureDiffCalculator.php`
- Modify: `app/Services/Formasi/FormasiExcelParser.php` (add `public static function levelForRm(string $rm): string` returning `Karpim` for RM-1 to RM-3, else `Karpel`), `app/Console/Commands/BuildFormasiSeed.php` (use it)
- Test: `tests/Unit/StructureDiffCalculatorTest.php`; add a `levelForRm` test to `tests/Unit/FormasiExcelParserTest.php`; `StructureReader` is covered in Task 4's feature test (it reads the database)

**Interfaces:**
- Consumes: shared array shape (File Structure); parser seats `['unit','jabatan','rm_level','bidang','kuota']`.
- Produces:
  - `StructureReader::current(): array` (units with their seats from `units`, `position_unit`, `positions`). Excludes Jabatan Penampungan rows (`positions.is_overflow` or `positions.is_diperban`), which are quota-0 link markers and not formasi. Includes norm-based seats with quota 0 on ordinary positions. Includes inactive units with an `is_active => false` flag in the unit entry, so the calculator can emit `unit.reactivate` instead of `unit.add` when the workbook lists an inactive unit's name.
  - `StructureReader::fromParsedSeats(array $seats, array $unitAttributes = []): array` builds the target shape (level via `levelForRm`).
  - `StructureDiffCalculator::calculate(array $target, array $current, array $unitMapping = [], array $closedUnits = [], array $positionMapping = []): StructureDiff` where `$unitMapping` is `[oldUnitName => newUnitName]`, `$closedUnits` is a list of current unit names to deactivate, and `$positionMapping` is `[oldJabatan => newJabatan]` (position renames, e.g. `'Kepala Bagian Sumber Daya Manusia & Sistem Manajemen' => 'Kepala Bagian Sumber Daya Manusia'`). A mapped position yields one `position.rename` change instead of `seat.remove` plus `seat.add`.
  - `StructureDiff` (readonly): `array $changes`, `array $totals`, `bool $isEmpty()`, `string $digest()` (sha256 of canonical JSON of changes), `toArray()`. Each change is `['type' => ..., ...]` with types `unit.add`, `unit.reactivate`, `unit.rename` (`from`,`to`), `unit.close`, `position.rename` (`from`,`to`), `seat.add`, `seat.remove`, `seat.update` (`unit`,`jabatan`,`old`,`new` with only changed of `kuota`/`bidang`), `position.rm_change` (`jabatan`,`from`,`to`, `level`), `seat.move` (informational: `jabatan`,`from_unit`,`to_unit`). `totals` has `before` and `after` with `units`, `pairs`, `kuota`, plus `kuota_by_rm` and `kuota_by_bidang`.
  - A unit only in `$current` and not mapped or closed is ignored entirely (its seats are not compared).

- [ ] **Step 1: Write failing tests** in `StructureDiffCalculatorTest` with small arrays:
  - identical structures: `isEmpty()` true.
  - rename via mapping (`'Group Manager Kalbar' => 'Unit Group Kalimantan Barat'`) with same seats yields one `unit.rename` and no seat changes.
  - seat quota change and bidang change yield one `seat.update` listing both.
  - a seat missing from a unit present in the workbook yields `seat.remove`; a unit absent from the workbook and not closed yields no changes.
  - the same jabatan removed from unit A and added to unit B yields `seat.remove`, `seat.add` and one informational `seat.move`.
  - an `rm_level` difference for one jabatan yields exactly one `position.rm_change` even if it appears in two units.
  - `unit.close` only for names passed in `$closedUnits`.
  - a position mapping yields one `position.rename` and no remove or add for that jabatan.
  - a workbook unit whose name matches an inactive current unit yields `unit.reactivate`, not `unit.add`; inactive units not in the workbook yield nothing.
  - an RM change across the Karpim/Karpel boundary (e.g. RM-3 to RM-5) yields `position.rm_change` carrying the new `level` too.
- In the Task 4 feature test (needs the database): `StructureReader::current()` excludes an Advisor row opened on a unit and includes a quota-0 norm seat such as Supir Pool.
  - `digest()` is stable across calls and changes when a quota changes.
  - totals: before 619 and after 619 for a move, `kuota_by_bidang` reflects a reclassification.
- [ ] **Step 2: Run, confirm failure. Step 3: Implement** the three classes plus `levelForRm`; the calculator is pure (no `DB`, no models). **Step 4: Run** the test file and `FormasiExcelParserTest`, `BuildFormasiSeedCommandTest`. **Step 5: Pint and commit** `feat(structure): add structure diff calculator`.

---

### Task 4: Decree draft and review (service part 1)

**Files:**
- Create: `app/Services/Structure/StructureDecreeService.php`, `app/Exceptions/StructureDecreeException.php` (message is Indonesian, shown to the user)
- Test: `tests/Feature/StructureDecreeServiceTest.php` (this class covers Tasks 4 to 6; add tests in order)

**Interfaces:**
- Consumes: `StructureReader`, `StructureDiffCalculator`, `FormasiExcelParser::parse`, `StructureDecree`.
- Produces:
  - `createDraft(array $data, UploadedFile $workbook, ?UploadedFile $pdf, User $user): StructureDecree` where `$data` has `sk_number`, `title`, `sk_date`, `effective_date` (defaults to `sk_date`), `revokes?`, `note?`. Stores files on a private disk under `structure-decrees/<id>/`, saves `source_sha256`, parses, computes the diff, saves `diff`.
  - `updateReview(StructureDecree $decree, array $unitMapping, array $closedUnits, array $newUnitAttributes, array $positionMapping, User $user): StructureDecree` where `$newUnitAttributes` is `[unitName => ['kode','wilayah','urutan']]`; recomputes the diff; allowed only for draft and stale decrees. `unit_mapping` json holds `mapping`, `closed`, `new_units`, `positions`.
  - `blockers(StructureDecree $decree): list<string>` (empty diff; new units missing `kode`/`wilayah`; unmatched units neither mapped, added nor closed is allowed so no blocker; `wilayah` not in `Unit::WILAYAH_LIST`; a unit or position rename whose new name already belongs to another unit or position; any `ImpactResult` blocker from the current structure).
  - The parsed target structure is stored on the decree (new json column `target`, add it in Task 2) so staleness checks and apply never re-parse the workbook; the workbook file stays as evidence only.
  - `impact(StructureDecree $decree): array` with `affected_employees`, `over_quota_seats`, `scheduled_mutations` (future-dated Mutasi, `job_histories.is_applied = false`, whose target seat is removed or reduced, or whose target unit is closed; listed by name so the admin can fix them before the effective date).
  - `worklist(StructureDecree $decree): Collection` employees whose unit-jabatan pair has no active seat or a seat whose employees exceed its quota.

- [ ] **Step 1: Write failing tests** (use `Storage::fake`, a workbook built with `Spreadsheet` in the 8-column format as `BuildFormasiSeedCommandTest` does, and `PositionUnitSeeder` as the current structure):
  - `test_create_draft_stores_files_hash_and_diff` (diff not empty after changing one quota in the workbook; `source_sha256` equals the file hash; effective date defaults to SK date).
  - `test_duplicate_sk_number_is_rejected_even_with_different_spacing` (`StructureDecreeException`).
  - `test_second_open_decree_is_rejected`.
  - `test_effective_date_before_latest_applied_decree_is_rejected`.
  - `test_wrong_headers_or_non_xlsx_are_rejected_with_a_message` (no 500).
  - `test_identical_workbook_blocks_submission_with_no_changes_message`.
  - `test_update_review_applies_mapping_and_closing_to_the_diff`.
  - `test_position_rename_is_blocked_when_the_position_is_used_outside_the_workbook`.
  - `test_impact_lists_scheduled_mutations_into_a_removed_seat`.
- [ ] **Step 2: Run, confirm failure. Step 3: Implement** the methods; new-unit attributes persist on the decree (`unit_mapping` json holds `mapping`, `closed`, `new_units`). **Step 4: Run** `--filter=StructureDecreeServiceTest`. **Step 5: Pint and commit** `feat(structure): add decree draft and review`.

---

### Task 5: Submit, approve, reject, cancel, staleness (service part 2)

**Files:**
- Modify: `app/Services/Structure/StructureDecreeService.php`
- Test: `tests/Feature/StructureDecreeServiceTest.php`

**Interfaces:**
- Produces: `submit(StructureDecree $decree, User $user): StructureDecree` (sets `review_digest` = diff digest, status submitted, audit `decree.submitted`); `approve(StructureDecree $decree, User $checker): StructureDecree`; `reject(StructureDecree $decree, User $checker, string $reason): StructureDecree` (reason at least 10 characters); `cancel(StructureDecree $decree, User $user, string $reason): StructureDecree`; `isStale(StructureDecree $decree): bool` (recomputes the diff from the stored `target` and mapping against the current database, compares digests). `approve` for a decree whose effective date is today or past calls `apply` (Task 6) in the same call.
- Consumes: `config('hris.structure.require_approval')`, `Gate` abilities `struktur.prepare` and `struktur.approve`.

- [ ] **Step 1: Write failing tests:**
  - `test_submit_is_blocked_while_blockers_exist` and `test_submit_freezes_the_review_digest`.
  - `test_approval_required_blocks_the_submitter_from_approving` (config on) and `test_approval_not_required_lets_the_submitter_approve` (config off).
  - `test_reject_and_cancel_require_a_reason_of_ten_characters` and move the decree to the terminal status, freeing the open slot.
  - `test_a_decree_goes_stale_when_the_structure_changes_after_submission` (edit a quota by hand, then `isStale` is true and `approve` throws `StructureDecreeException`).
  - `test_each_transition_writes_a_master_audit_entry_with_the_decree_id`.
  - `test_a_user_without_the_permission_cannot_transition` (Gate denies).
- [ ] **Step 2 to 5:** run to fail, implement, run to pass, Pint, commit `feat(structure): add decree workflow with four-eyes and staleness check`.

---

### Task 6: Apply

**Files:**
- Modify: `app/Services/Structure/StructureDecreeService.php`
- Test: `tests/Feature/StructureDecreeServiceTest.php`

**Interfaces:**
- Produces: `apply(StructureDecree $decree, ?User $actor = null): StructureDecree`. Requires status approved and effective date not in the future; locks the decree row; re-checks staleness (if stale, set status stale, notify, return without changes); runs everything inside `DB::transaction()` within `MasterAuditLog::forDecree()`; stores `snapshot` (the resulting `StructureReader::current()`), `applied_at`, status applied. On any exception: roll back, then (outside the transaction) set status failed with `failure_message`, audit `decree.failed`, and rethrow nothing to the scheduler (return the decree).
- Order inside apply (each via `MasterFormasiService`, reason `SK <number>`): `createUnit` for new units (attributes from `unit_mapping.new_units`); `updateUnit` for renames; `createPosition` or `updatePosition` for new jabatan or RM changes; `addSeat` / `updateSeat` for seats; seats removed: `removeSeat` when no holders, otherwise `updateSeat` with `kuota` 0; `setUnitActive(false)` for closed units that have no employees (a closed unit that still has employees stays active and its name is listed in the returned warnings).
- Verified 2026-11-16: `MasterFormasiService::assertSeatInput` rejects a quota below 1 (used by both `addSeat` and `updateSeat`) and `removeSeat` is blocked while a seat has holders. Add `bool $allowZero = false` to `addSeat` and `updateSeat` (passed through to `assertSeatInput`), used only by apply, so norm-based seats with a blank Kuota can be created and occupied seats can drop to 0. Test in `MasterFormasiServiceTest` that the editor still rejects 0 for both.
- Order additions: `setUnitActive(true)` for `unit.reactivate` before seat changes; `updatePosition` with both `rm_level` and `level` for an RM change across the Karpim/Karpel boundary.
- Actor: every `MasterFormasiService` method requires a non-null `User`. Apply always acts as the approving admin (`$decree->decider`); when the scheduler runs it, the decree audit entry says it ran automatically on the effective date. `apply(StructureDecree $decree)` therefore takes no actor parameter.
- Position renames: for each entry in `unit_mapping.positions` (`[oldJabatan => newJabatan]`), call `updatePosition` with the new name before seat changes, so holders keep their seat and their `jabatan` text is synced. Refuse (blocker in Task 4) when the old position is also used by a unit that is not covered by the workbook, because the rename is position-wide.

- [ ] **Step 1: Write failing tests:**
  - `test_apply_reproduces_the_workbook_structure` (current from the seeder with one rename, one move, one bidang change, one new unit; after apply `StructureReader::current()` equals the target for covered units; units outside the workbook untouched).
  - `test_apply_records_aliases_and_audit_entries_linked_to_the_decree` (old name resolves through `UnitAlias`).
  - `test_seat_removed_with_holders_gets_quota_zero_and_employees_stay` and the worklist lists them.
  - `test_closed_unit_with_employees_stays_active_and_is_reported`.
  - `test_failure_rolls_everything_back_and_marks_the_decree_failed` (force a name clash on the second unit; assert no partial change and status failed).
  - `test_apply_stores_a_snapshot_and_sets_applied_at`.
  - `test_future_effective_date_is_not_applied_by_approve_and_stays_scheduled`.
  - `test_position_rename_keeps_holders_on_their_seat_and_syncs_their_jabatan`.
  - `test_scheduled_apply_acts_as_the_approving_admin`.
  - `test_apply_creates_a_new_norm_seat_with_quota_zero`.
  - `test_apply_reactivates_an_inactive_unit_listed_in_the_workbook`.
  - `test_apply_leaves_jabatan_penampungan_rows_untouched` (an Advisor opened on a covered unit is still there after apply).
- [ ] **Step 2 to 5:** run to fail, implement, run to pass (also run `MasterFormasiServiceTest`, `FormasiSeederTest`), Pint, commit `feat(structure): apply approved decrees atomically`.

---

### Task 6b: Keep MPP history correct across a structure change

Why: `getMppMatrix()` (including Standar Formasi) reads the live structure. A captured period stores a JSON snapshot, so closed and draft periods are frozen, but `hris:mpp-capture-period` runs at 01:00 on the 1st and would capture the previous month after the 00:00 apply, using the new structure.

**Files:**
- Modify: `app/Services/Mpp/MppPeriodService.php`, `app/Services/Structure/StructureDecreeService.php` (`apply`), `routes/console.php` (the `hris:mpp-capture-period` command body)
- Test: add to `tests/Feature/MppPeriodServiceTest.php`, `tests/Feature/MppCapturePeriodCommandTest.php` and `tests/Feature/StructureDecreeServiceTest.php`

**Interfaces:**
- Produces: `MppPeriodService::freezeBefore(CarbonInterface $effectiveDate, ?User $user): ?MppPeriod` captures the calendar month before `$effectiveDate` unless that period is closed or already a draft captured on the same date; returns the period or `null`. `apply()` calls it before opening its transaction. The monthly command skips a period that already has a draft captured today.
- Verified 2026-11-16: `close()` closes the stored draft without recapturing, and `capture()` always recomputes and overwrites an existing draft. Hence the command-skip rule is required.
- Documented limits (write them in `DOMAIN.md` §3.7 in Task 1, no code): MPP is monthly, so for an SK effective mid-month the month's captured period reflects the structure at capture time; a back-dated SK (approved after its effective date) does not rewrite closed periods, because closed periods are immutable; an admin may reopen and recapture through the existing reopen flow.

- [ ] **Step 1: Write failing tests:**
  - `MppPeriodServiceTest::test_freeze_before_captures_the_previous_month_with_the_old_structure` (seed old structure, call `freezeBefore(Carbon::parse('2026-09-01'))`, change a quota, assert the stored snapshot's Standar Formasi equals the old totals).
  - `MppPeriodServiceTest::test_freeze_before_leaves_a_closed_period_untouched`.
  - `MppCapturePeriodCommandTest::test_command_skips_a_draft_captured_today`.
  - `StructureDecreeServiceTest::test_apply_freezes_the_previous_mpp_month_before_changing_the_structure` (decree effective 1 September; after apply and then running `hris:mpp-capture-period` for August, the August snapshot still shows the old Standar Formasi and bidang totals, while the live matrix shows the new ones).
  - `StructureDecreeServiceTest::test_a_closed_mpp_period_is_unchanged_by_apply`.
- [ ] **Step 2 to 5:** run to fail, implement, run to pass (also the full MPP test classes and `ManPowerPlanningTest`), Pint, commit `fix(mpp): freeze the previous period before a structure change`.

---

### Task 7: Scheduler command, notifications, baseline seeder

**Files:**
- Create: `app/Console/Commands/ApplyStructureDecreesCommand.php`, `database/data/structure_decree.json`
- Modify: `routes/console.php` (schedule `hr:apply-structure-decrees` `dailyAt('00:00')`), `app/Services/NotificationCenterService.php` (items for decrees awaiting approval and for stale or failed decrees, visible with `struktur.approve`), `database/seeders/PositionUnitSeeder.php` (create the baseline decree after seeding, idempotent)
- Test: `tests/Feature/ApplyStructureDecreesCommandTest.php`; add one assertion block to `tests/Feature/FormasiSeederTest.php` for the baseline decree; add notification assertions to the existing notification test class if one exists (otherwise to `StructureDecreeServiceTest`)

**Interfaces:**
- Produces: command `hr:apply-structure-decrees` applying approved decrees with `effective_date <= today`, oldest first, returning exit 0; baseline decree data: number `DPSB/SKPTS/352/IX/2026`, title `Perubahan Struktur Organisasi Region Office PT Perkebunan Nusantara IV`, SK date and effective date `2026-09-01`, status applied, `note` stating the kebun, PKS and Unit Group rows were adopted from the previous structure, snapshot equal to the seeded structure, `revokes` text for the earlier Region Office structure rules.

- [ ] **Step 1: Write failing tests:** command applies a due approved decree and skips a future one and a submitted one; running it twice changes nothing; seeding creates exactly one baseline decree (seed twice, still one) with `sk_number` `DPSB/SKPTS/352/IX/2026`; a pending decree shows in `NotificationCenterService::itemsFor` for an admin and not for a `user`.
- [ ] **Step 2 to 5:** run to fail, implement, run to pass, Pint, commit `feat(structure): schedule decree apply and record the baseline SK`.

---

### Task 8: Admin screens (Phase 3)

**Files:**
- Create: `app/Livewire/Admin/Master/StructureDecreeIndex.php`, `StructureDecreeWizard.php`, `StructureDecreeDetail.php`, the Blade views listed in File Structure
- Modify: `routes/web.php` (admin master group: `sk` index, `sk/baru`, `sk/{decree}`), the master section navigation view (find it with `Grep` for `riwayat.index`), `resources/lang` or inline strings as the project does
- Test: `tests/Feature/StructureDecreeScreensTest.php` (Livewire tests)

**Interfaces:**
- Consumes: all `StructureDecreeService` methods; `Gate` abilities; `FormasiDialogs` for confirmation modals if suitable.
- Produces: routes `admin.master.sk.index`, `admin.master.sk.create`, `admin.master.sk.show`.

UI requirements: stepper "Data SK" (number, "Tentang", SK date, effective date defaulting to SK date, "Mencabut/Menggantikan", note, workbook and optional PDF upload) → "Tinjau Perubahan" (diff grouped by type, totals before and after, unit pairing for unmatched units, jabatan pairing for removed and added jabatan within a unit (renames), list of scheduled Mutasi affected, "Tutup unit" toggle, `kode`/`wilayah`/`urutan` fields for new units, impact panel, blockers) → "Ajukan". Detail page: timeline, decision buttons with destructive confirmation modal (approve, reject, cancel), worklist table linking to the Mutasi module, applied snapshot download if time permits (not required). Status badges use `DESIGN.md` semantic colours; explicit Livewire loading states; mobile-first layout for the list, desktop-first for review. Build assets after view changes (`npm run build`; the project ships prebuilt CSS).

- [ ] **Step 1: Write failing Livewire tests:** a non-admin is forbidden on all three routes; an admin creates a draft through the wizard and reaches the review step showing the diff counts; blockers disable the submit button; a different admin sees the approve action and the submitter does not (approval switch on); the detail page shows the worklist after apply; no rendered page contains the strings `AGENTS.md`, `DOMAIN.md`, `DESIGN.md`, `ENGINEERING.md`, `SECURITY.md` or `SSOT`.
- [ ] **Step 2 to 4:** run to fail, implement, run to pass.
- [ ] **Step 5: Manual check in the browser** with the real workbook (`Struktur_Organisasi_PTPN_IV_Reg_V_1 September 2026.xlsx`) against a database seeded from the old baseline (use a copy, for example the backups in the scratchpad, never the user's data): the diff should list the renames, 17 Kemitraan seats moved, 10 Sistem Manajemen seats moved, 8 bidang changes, totals 619 to 619.
- [ ] **Step 6: Pint, `npm run build`, full `php artisan test`, commit** `feat(structure): add SK management screens`.

---

## Self-review notes

- Spec coverage: lifecycle (T4 to T6), diff (T3), unit identity mapping (T3, T4, T8), impact and worklist (T4, T6, T8), staleness (T5), atomic apply and snapshot (T6), data model (T2), command, seeder baseline, notifications (T7), UI (T8), SSOT updates (T1), tests per SUT (T2 to T8). Non-goals (full effective-dating, auto reassignment) have no tasks by design.
- The attribute rule (existing units keep `kode`/`wilayah`/`urutan`; admin supplies them for new units) is a gap in the spec that this plan decides; the spec has been updated to match.
- Task order keeps each deliverable independently testable: T1 ships alone; T2 to T7 (including T6b) are backend with no UI; T8 needs them all.
- Added after review (2026-11-16): Task 6b for MPP period correctness across a structure change.
- Second verification pass (2026-11-16) folded in: Jabatan Penampungan rows excluded from the reader; `addSeat` also needs `allowZero`; inactive units reactivate; Karpim/Karpel level follows an RM change; renames to an existing name are blockers; the target structure is stored (`target` column) instead of re-parsing; UTC timezone stated; no undo (correct by a new SK). Task 1 must also write the no-undo rule and the timezone note into `DOMAIN.md` §3.7 and `ENGINEERING.md`.
- Verified against the code (2026-11-16) and folded in: quota 0 is rejected by the editor (explicit `allowZero` path); service methods need a `User` (apply acts as the approving admin); position renames need a mapping (otherwise holders lose their seat); scheduled Mutasi into removed seats are listed in the impact; MPP `close()` keeps the stored draft and `capture()` overwrites it.
