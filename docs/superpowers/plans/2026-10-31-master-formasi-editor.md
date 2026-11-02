# Master Formasi Editor (Phase 1) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Let an admin add and edit units, jabatan and seats (kuota, bidang) in the app, with every change audited in the same transaction and every employee-orphaning edit refused.

**Architecture:** One `MasterFormasiService` holds the rules, the impact preview and the audit writes. The existing Unit and Position controllers become thin callers of it. A new Livewire screen edits seats one unit at a time, and a read-only screen lists the audit history.

**Tech Stack:** Laravel 13, PHP, Livewire, PHPUnit 11 (sqlite in-memory), Laravel Pint.

**Spec:** `docs/superpowers/specs/2026-10-31-master-formasi-editor-design.md`. Builds on the seat model from `docs/superpowers/specs/2026-10-31-master-formasi-excel-design.md` (`position_unit` has `kuota` and `bidang`).

## Global Constraints

- Work on branch `feat/master-formasi-editor`. Commit only after the user has authorized execution.
- Every multi-table write inside `DB::transaction()`; parameter binding only; CSRF on all forms; Pint (`./vendor/bin/pint`) on every changed file; PHPUnit 11; one test class per system under test (no splitting a SUT's tests across files).
- User-facing text (Blade, validation, notifications, modals) uses official business terms in Indonesian and never names `AGENTS.md`, `DOMAIN.md`, `DESIGN.md`, `ENGINEERING.md`, `SECURITY.md` or "SSOT". Those names are allowed only in code comments.
- UI follows DESIGN.md: design tokens (`primary-*`, `surface-*`), explicit `wire:loading` states, `wire:model.live.debounce.300ms` on search, desktop-first, destructive actions behind a confirm modal showing the impact.
- Audit rows store counts of affected employees only, never NIK or names. The audit model refuses update and delete.
- "Filled" for a seat means `Employee::where('unit_kerja', $unit->nama)->where('jabatan', $position->nama)->count()`.
- Reason (min 10 characters after trim) is required for: remove seat, deactivate unit or jabatan, delete unit or jabatan, rename unit or jabatan, change a jabatan's `rm_level`.
- Bidang must be one of `KEU|TAN|TEK|UMU` and is required when the jabatan's `level` is `Karpim`; kuota is an integer of at least 1.
- Roles: only `admin` and `user` exist; all new routes go inside the existing `role:admin` group.
- Known unrelated failures: 5 date-dependent "retired employee" tests fail on the 29th to 31st of a month; they are not caused by this work.
- Deviation from the spec (ruled in the plan): units and positions have no per-unit page (their forms live in the index views), so the unit's "recent changes" strip appears on the Formasi screen for the selected unit, not on a unit page.
- Deviation from the spec (ruled in the plan): the service allows seats for Karpel jabatan (bidang optional) because the existing quick-add flow creates them and employee import validates Karpel jabatan against unit seats; the Formasi screen only offers Karpim jabatan when adding.

## Review Focus

1. If the audit write fails, the data change must roll back (Task 2).
2. An edit that changes nothing must succeed without writing an audit row (Task 2).
3. Adding a seat that already exists for the unit and jabatan must give a clear rule message, not a SQL unique-constraint error (Task 2).
4. Renaming a unit or jabatan to a name that already exists must be refused with a clear message, and must never touch `job_histories` text (Tasks 3 and 4).
5. A whitespace-only reason must count as missing (Task 2).

---

## File Structure

- Create `database/migrations/2026_10_31_110000_create_master_audit_logs_table.php`.
- Create `app/Models/MasterAuditLog.php`: append-only audit model with a `record()` factory.
- Create `app/Services/Formasi/ImpactResult.php`: immutable preview result.
- Create `app/Exceptions/FormasiRuleViolationException.php`.
- Create `app/Services/Formasi/MasterFormasiService.php`: rules, preview and audit writes.
- Modify `app/Http/Controllers/Admin/Master/UnitController.php` and `PositionController.php`: delegate to the service.
- Create `app/Livewire/Admin/Master/FormasiEditor.php`, `resources/views/livewire/admin/master/formasi-editor.blade.php`, `resources/views/admin/master/formasi/index.blade.php`.
- Create `app/Http/Controllers/Admin/Master/MasterAuditLogController.php`, `resources/views/admin/master/riwayat/index.blade.php`.
- Modify `routes/web.php` (master group) and `resources/views/layouts/admin.blade.php` (two sidebar entries).
- Modify `DOMAIN.md` (section 3.5 and section 9) and `SECURITY.md` (section 5).
- Tests: `tests/Feature/MasterAuditLogTest.php`, `tests/Feature/MasterFormasiServiceTest.php`, `tests/Feature/FormasiEditorTest.php`; extend `tests/Feature/MasterDataTest.php`.

---

### Task 1: Audit table, model and value objects

**Files:**
- Create: `database/migrations/2026_10_31_110000_create_master_audit_logs_table.php`, `app/Models/MasterAuditLog.php`, `app/Services/Formasi/ImpactResult.php`, `app/Exceptions/FormasiRuleViolationException.php`
- Test: `tests/Feature/MasterAuditLogTest.php`

**Interfaces:**
- Produces: table `master_audit_logs` with columns `id`, `user_id` (nullable FK users, `nullOnDelete`), `user_name` (string), `action` (string 40), `entity_type` (string 20), `entity_id` (nullable unsignedBigInteger), `entity_label` (string 255), `old_values` and `new_values` (json, nullable), `reason` (text, nullable), `created_at`; indexes on (`entity_type`,`entity_id`), `user_id`, `created_at`. No `updated_at`.
- Produces: `MasterAuditLog::record(User $user, string $action, string $entityType, ?int $entityId, string $entityLabel, ?array $old = null, ?array $new = null, ?string $reason = null): self`.
- Produces: `final readonly class ImpactResult { public function __construct(public bool $blocked = false, public array $blockers = [], public array $warnings = [], public int $affectedEmployees = 0) }` with `static ok(array $warnings = [], int $affected = 0): self` and `static blocked(array $blockers, int $affected = 0): self`.
- Produces: `class FormasiRuleViolationException extends \InvalidArgumentException {}`.

- [ ] **Step 1: Write the failing tests** in `MasterAuditLogTest` (RefreshDatabase):
  - `test_record_stores_the_audit_row_with_a_user_snapshot`: after `MasterAuditLog::record($admin, 'seat.added', 'seat', 7, 'Kebun Tajati — Manajer', null, ['kuota' => 1], null)`, the row has `user_name === $admin->name`, `entity_label` as given, `new_values === ['kuota' => 1]`, `old_values === null`.
  - `test_audit_rows_cannot_be_updated`: `$log->update(['reason' => 'x'])` throws `LogicException`.
  - `test_audit_rows_cannot_be_deleted`: `$log->delete()` throws `LogicException`, and the row still exists.
  - `test_deleting_the_user_keeps_the_row_with_its_name_snapshot`: after `$admin->delete()`, `user_id` is null and `user_name` is unchanged.
- [ ] **Step 2: Run to verify failure.** `php artisan test --filter=MasterAuditLogTest` → FAIL (class or table missing).
- [ ] **Step 3: Implement** the migration, the model (`UPDATED_AT = null`; `updating` and `deleting` model events throw `LogicException('Riwayat perubahan master bersifat append-only.')`; casts `old_values` and `new_values` to `array`), `ImpactResult` and the exception, at the signatures above.
- [ ] **Step 4: Run to verify pass.** Same command → PASS.
- [ ] **Step 5: Pint and commit.** `feat(master): append-only audit table for master formasi changes`.

---

### Task 2: Service core — audit writes, impact preview and seat operations

**Files:**
- Create: `app/Services/Formasi/MasterFormasiService.php`
- Test: `tests/Feature/MasterFormasiServiceTest.php`

**Interfaces:**
- Consumes: `MasterAuditLog::record`, `ImpactResult`, `FormasiRuleViolationException` (Task 1).
- Produces on `MasterFormasiService` (constructor takes no arguments):
  - `addSeat(Unit $unit, Position $position, int $kuota, ?string $bidang, User $user): int` returning the new `position_unit` id.
  - `updateSeat(int $positionUnitId, int $kuota, ?string $bidang, User $user): void`
  - `removeSeat(int $positionUnitId, ?string $reason, User $user): void`
  - `previewImpact(string $operation, array $params): ImpactResult` for operations `seat.add` (`unit_id`, `position_id`), `seat.update` (`seat_id`, `kuota`), `seat.remove` (`seat_id`); later tasks add the unit and position operations.
  - `requireReason(?string $reason): string` (protected-use helper, public for tests is not required): throws `FormasiRuleViolationException('Alasan perubahan wajib diisi (minimal 10 karakter).')` unless `mb_strlen(trim($reason)) >= 10`; returns the trimmed reason.
  - Audit actions written: `seat.added`, `seat.updated`, `seat.removed`, with `entity_type` `seat`, `entity_id` the `position_unit` id, `entity_label` `"{unit nama} — {jabatan nama}"`, `old_values` and `new_values` holding `kuota` and `bidang`, and a `filled` count in the removed or updated rows.

- [ ] **Step 1: Write the failing tests** in `MasterFormasiServiceTest` (RefreshDatabase; build units, positions and employees with `Unit::create`, `Position::create`, `Employee::factory()` for `unit_kerja` and `jabatan`):
  - `test_add_seat_creates_the_seat_and_one_audit_row`: seat has the kuota and bidang; one `MasterAuditLog` with action `seat.added` and the label above.
  - `test_add_seat_requires_bidang_for_karpim_but_not_for_karpel`: Karpim with null bidang throws `FormasiRuleViolationException`; Karpel with null bidang succeeds.
  - `test_add_seat_rejects_invalid_bidang_and_non_positive_kuota`: bidang `XYZ` and kuota `0` each throw.
  - `test_add_seat_rejects_a_duplicate_pair_with_a_clear_message` (Review Focus 3): second add for the same unit and jabatan throws `FormasiRuleViolationException` whose message contains `sudah memiliki`.
  - `test_add_seat_rejects_inactive_unit_or_jabatan`.
  - `test_update_seat_below_filled_is_allowed_and_flagged`: with 3 employees filled, `updateSeat(..., kuota: 1, ...)` succeeds; `previewImpact('seat.update', ['seat_id' => $id, 'kuota' => 1])` is not blocked and its warnings contain `melebihi kuota`.
  - `test_update_seat_with_no_change_writes_no_audit_row` (Review Focus 2).
  - `test_remove_seat_with_employees_is_blocked_with_a_count`: throws; the message contains the employee count and `Mutasi`; the seat still exists.
  - `test_remove_empty_seat_requires_a_reason_and_writes_an_audit_row`: without a reason throws; with `'Kursi dihapus karena restrukturisasi'` succeeds; the audit row has `reason` set and action `seat.removed`.
  - `test_whitespace_only_reason_counts_as_missing` (Review Focus 5): reason `'          '` (ten spaces) throws.
  - `test_audit_failure_rolls_back_the_change` (Review Focus 1): bind a `MasterFormasiService` subclass or use a DB listener that makes the audit insert fail (for example, drop the `master_audit_logs` table inside the test), call `addSeat`, expect an exception, and assert no `position_unit` row was added.
- [ ] **Step 2: Run to verify failure.** `php artisan test --filter=MasterFormasiServiceTest` → FAIL (class missing).
- [ ] **Step 3: Implement** the methods above. Each mutating method: `DB::transaction(function () {...})`; call `previewImpact()` first and throw `FormasiRuleViolationException` (first blocker as the message) when blocked; compare old and new values and return early without an audit row when nothing changed; write the audit row last, inside the transaction. Messages in Indonesian; the blocked-removal message names the count and says to move the employees through Mutasi first.
- [ ] **Step 4: Run to verify pass.** Same command → PASS.
- [ ] **Step 5: Pint and commit.** `feat(master): formasi service with seat rules, impact preview and audit`.

---

### Task 3: Service — unit operations

**Files:**
- Modify: `app/Services/Formasi/MasterFormasiService.php`
- Test: `tests/Feature/MasterFormasiServiceTest.php` (same class)

**Interfaces:**
- Consumes: Task 2 service and helpers.
- Produces on `MasterFormasiService`:
  - `createUnit(array $data, User $user): Unit` (keys `nama`, `kode`, `wilayah`, `urutan`; `wilayah` must be in `Unit::WILAYAH_LIST`; `nama` unique; new units are active; audit `unit.created`).
  - `updateUnit(Unit $unit, array $data, ?string $reason, User $user): Unit` (same keys; a changed `nama` is a rename: reason required, audit action `unit.renamed`, `employees.unit_kerja` rewritten to the new name in the same transaction, `job_histories` untouched; otherwise audit `unit.updated`; nothing changed means no audit row).
  - `setUnitActive(Unit $unit, bool $active, ?string $reason, User $user): void` (deactivating requires a reason and is blocked while `Employee::where('unit_kerja', $unit->nama)->count() > 0`; audit `unit.activated` or `unit.deactivated`).
  - `deleteUnit(Unit $unit, ?string $reason, User $user): void` (reason required; blocked while the unit has employees, seats, or `JobHistory` rows with its name; audit `unit.deleted`).
  - `previewImpact` gains `unit.deactivate`, `unit.delete`, `unit.rename` (each takes `unit_id`; rename also takes `nama`).

- [ ] **Step 1: Write the failing tests** (same class): `test_create_unit_writes_audit_and_validates_wilayah`; `test_rename_unit_updates_employees_text_but_not_job_histories` (employee `unit_kerja` changes, a `JobHistory` row keeps the old text, action `unit.renamed`); `test_rename_unit_to_an_existing_name_is_refused` (Review Focus 4); `test_rename_requires_a_reason`; `test_deactivate_unit_with_employees_is_blocked_and_message_has_the_count`; `test_deactivate_empty_unit_requires_reason_and_audits`; `test_delete_unit_is_blocked_by_seats_employees_or_history` (three sub-cases); `test_delete_unused_unit_succeeds_and_audits`; `test_update_unit_with_no_change_writes_no_audit_row`.
- [ ] **Step 2: Run to verify failure.**
- [ ] **Step 3: Implement** the four methods and the three preview operations, following the same transaction, preview, no-op and audit pattern as Task 2.
- [ ] **Step 4: Run to verify pass.** `php artisan test --filter=MasterFormasiServiceTest`.
- [ ] **Step 5: Pint and commit.** `feat(master): audited unit operations with employee-safe guards`.

---

### Task 4: Service — jabatan operations

**Files:**
- Modify: `app/Services/Formasi/MasterFormasiService.php`
- Test: `tests/Feature/MasterFormasiServiceTest.php` (same class)

**Interfaces:**
- Consumes: Tasks 2 and 3.
- Produces on `MasterFormasiService`:
  - `createPosition(array $data, User $user): Position` (keys `nama`, `bidang`, `level` in `Position::LEVEL_LIST`, `rm_level`; `nama` unique; Karpel forces `rm_level` null; audit `position.created`).
  - `updatePosition(Position $position, array $data, ?string $reason, User $user): Position` (a changed `nama` is a rename: reason required, action `position.renamed`, `employees.jabatan` rewritten in the same transaction, `job_histories` untouched; a changed `rm_level` requires a reason and the preview warns with the number of units that use the jabatan; otherwise `position.updated`; no change means no audit row).
  - `setPositionActive(Position $position, bool $active, ?string $reason, User $user): void` (deactivating requires a reason and is blocked while employees hold the jabatan; actions `position.activated` and `position.deactivated`).
  - `deletePosition(Position $position, ?string $reason, User $user): void` (reason required; blocked while employees hold it, it has seats, or `JobHistory` rows carry its name; action `position.deleted`).
  - `previewImpact` gains `position.deactivate`, `position.delete`, `position.rename` (also `nama`), `position.rm_change` (also `rm_level`).

- [ ] **Step 1: Write the failing tests** (same class): `test_create_position_validates_and_audits`; `test_rename_position_updates_employees_text_but_not_job_histories`; `test_rename_position_to_an_existing_name_is_refused` (Review Focus 4); `test_change_rm_requires_a_reason_and_warns_with_unit_count` (a jabatan used in 3 units → warning contains `3`); `test_deactivate_position_held_by_employees_is_blocked`; `test_delete_position_is_blocked_by_seats_employees_or_history`; `test_delete_unused_position_succeeds`; `test_update_position_with_no_change_writes_no_audit_row`.
- [ ] **Step 2: Run to verify failure.**
- [ ] **Step 3: Implement** the four methods and four preview operations in the same pattern.
- [ ] **Step 4: Run to verify pass.** `php artisan test --filter=MasterFormasiServiceTest`.
- [ ] **Step 5: Pint and commit.** `feat(master): audited jabatan operations with employee-safe guards`.

---

### Task 5: Controllers delegate to the service

**Files:**
- Modify: `app/Http/Controllers/Admin/Master/UnitController.php`, `app/Http/Controllers/Admin/Master/PositionController.php`
- Test: extend `tests/Feature/MasterDataTest.php`

**Interfaces:**
- Consumes: all `MasterFormasiService` methods (Tasks 2 to 4); inject the service into each controller method that mutates.
- Produces: unchanged route names, request fields, redirects and JSON shapes; `reason` becomes an accepted optional field on update, toggle and destroy requests.

- [ ] **Step 1: Write the failing tests** in `MasterDataTest`: `test_unit_crud_writes_audit_rows` (create, rename with reason, toggle, delete each produce the matching `MasterAuditLog` action); `test_position_crud_writes_audit_rows`; `test_blocked_unit_deactivation_redirects_back_with_an_error_message` (unit with an employee: session `error` contains the count, unit still active); `test_rename_without_reason_is_refused_with_a_message`; `test_creating_position_with_unit_and_without_bidang_for_karpim_is_refused`. Existing tests in this class must still pass.
- [ ] **Step 2: Run to verify failure.** `php artisan test --filter=MasterDataTest`.
- [ ] **Step 3: Refactor the controllers.** Keep validation in the controller. `store` calls `createUnit` or `createPosition` (and `addSeat` when `unit_nama` is given, with `kuota` 1 and the request's `bidang`). `update` calls `updateUnit` or `updatePosition`, then calls `setUnitActive` or `setPositionActive` when `is_active` differs. `toggleStatus` and `destroy` call the matching service methods with `request('reason')`. Catch `FormasiRuleViolationException` and return back with `error` (or JSON 422 for AJAX), mirroring the current style. Pass `$request->user()` as the acting user.
- [ ] **Step 4: Run to verify pass.** `php artisan test --filter=MasterDataTest`.
- [ ] **Step 5: Pint and commit.** `refactor(master): unit and jabatan controllers use the formasi service`.

---

### Task 6: Formasi editor screen

**Files:**
- Create: `app/Livewire/Admin/Master/FormasiEditor.php`, `resources/views/livewire/admin/master/formasi-editor.blade.php`, `resources/views/admin/master/formasi/index.blade.php`
- Modify: `routes/web.php`, `resources/views/layouts/admin.blade.php`
- Test: `tests/Feature/FormasiEditorTest.php`

**Interfaces:**
- Consumes: `MasterFormasiService::{addSeat,updateSeat,removeSeat,previewImpact}`, `MasterAuditLog`.
- Produces: route `admin.master.formasi.index` (`GET admin/master/formasi`) rendering a page view that mounts `<livewire:admin.master.formasi-editor />`; sidebar entry "Formasi" under Master Data.
- `FormasiEditor` public API: properties `?int $unitId`, `string $search`, `?string $pendingOperation`, `array $pendingParams`, `string $reason`; methods `selectUnit(int $unitId): void`, `startAddSeat(int $positionId, int $kuota, string $bidang): void`, `startUpdateSeat(int $seatId, int $kuota, string $bidang): void`, `startRemoveSeat(int $seatId): void`, `confirm(): void`, `cancel(): void`; a computed `impact` (an `ImpactResult` or null) and a `seats` list with `filled` and over-quota flags; the selected unit's last 5 audit rows.

- [ ] **Step 1: Write the failing tests** in `FormasiEditorTest` (Livewire::actingAs admin): `test_non_admin_cannot_open_the_formasi_page` (403 or redirect); `test_page_renders_for_admin_and_lists_seats_of_the_selected_unit` (seat rows show jabatan, RM, bidang, kuota, filled); `test_over_quota_seat_shows_the_badge`; `test_add_seat_flow_previews_then_confirms_and_audits` (`startAddSeat` sets pending state and an impact panel, `confirm` creates the seat and one audit row); `test_remove_seat_with_employees_shows_blockers_and_does_not_remove` (assertSee the blocker text, the seat remains after `confirm`); `test_remove_seat_requires_a_reason_and_shows_a_validation_message_when_missing`; `test_editing_kuota_below_filled_shows_the_over_quota_warning`; `test_add_seat_dropdown_only_offers_active_karpim_jabatan_not_yet_in_the_unit`; `test_recent_changes_for_the_selected_unit_are_listed`.
- [ ] **Step 2: Run to verify failure.** `php artisan test --filter=FormasiEditorTest`.
- [ ] **Step 3: Implement** the component, the two Blade views, the route inside the master group, and the sidebar entry modeled on the existing Unit Kerja entry (icon `fas fa-table-list`, title "Formasi", subtitle "Kursi &amp; Kuota"). The impact panel and confirm modal show blockers in a danger style, warnings in a warning style, and the affected count; the confirm button is disabled while `blocked`. Reason textarea appears only when the pending operation requires it. Use `wire:loading` on the confirm button and the unit switcher; the add-seat search uses `wire:model.live.debounce.300ms`. Text in official Indonesian terms.
- [ ] **Step 4: Run to verify pass.** `php artisan test --filter=FormasiEditorTest`, then `php artisan test --filter=ViewDesignGuardTest` (the design guard must stay green).
- [ ] **Step 5: Pint and commit.** `feat(master): formasi editor screen with impact preview`.

---

### Task 7: Audit history screen

**Files:**
- Create: `app/Http/Controllers/Admin/Master/MasterAuditLogController.php`, `resources/views/admin/master/riwayat/index.blade.php`
- Modify: `routes/web.php`, `resources/views/layouts/admin.blade.php`
- Test: extend `tests/Feature/MasterAuditLogTest.php`

**Interfaces:**
- Consumes: `MasterAuditLog`.
- Produces: route `admin.master.riwayat.index` (`GET admin/master/riwayat`) accepting query filters `entity_type` (`unit|position|seat`), `user_id`, `from` and `to` (dates); newest first, 25 per page; sidebar entry "Riwayat Perubahan".

- [ ] **Step 1: Write the failing tests** (same class as Task 1): `test_history_page_is_admin_only`; `test_history_lists_newest_first_and_paginates_by_25`; `test_history_filters_by_entity_type_user_and_date_range`; `test_history_shows_who_what_and_reason_but_no_employee_data`; `test_history_page_renders_old_and_new_values_in_readable_form`.
- [ ] **Step 2: Run to verify failure.** `php artisan test --filter=MasterAuditLogTest`.
- [ ] **Step 3: Implement** the controller, the view (table with time, admin, action label in Indonesian, entity, label, old to new summary, reason) and the sidebar entry modeled on the existing Master Data entries. Filter inputs use the existing form styling; the user filter lists admins who have at least one audit row.
- [ ] **Step 4: Run to verify pass.** `php artisan test --filter="MasterAuditLogTest|ViewDesignGuardTest"`.
- [ ] **Step 5: Pint and commit.** `feat(master): read-only audit history screen`.

---

### Task 8: Documentation and full verification

**Files:**
- Modify: `DOMAIN.md` (section 3.5 and section 9), `SECURITY.md` (section 5)

- [ ] **Step 1: Update DOMAIN.md.** In section 3.5 add the editing rules table (collisions, guards, reason requirement, rename sync, no employee moves outside Mutasi). In section 9 add `MasterFormasiService` with its public method list. Keep the existing style and the `$\rightarrow$` notation.
- [ ] **Step 2: Update SECURITY.md section 5** with a new numbered item: every change to master units, jabatan and seats is recorded in `master_audit_logs` (who, when, what, old and new value, reason), written in the same transaction, append-only, stores counts only.
- [ ] **Step 3: Run the whole suite in the background** (`php artisan test`) and read the tail. Expected: only the 5 known date-dependent failures.
- [ ] **Step 4: Run `./vendor/bin/pint --test`.** Expected: `passed`.
- [ ] **Step 5: Commit.** `docs: document the master formasi editor and its audit trail`.
