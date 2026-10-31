# Master Formasi Editor (Phase 1) — Design

Status: draft for review · Date: 2026-10-31 · Branch: `feat/master-formasi-editor` (builds on `feat/master-formasi-excel`)
Depends on: the seat model from `docs/superpowers/specs/2026-10-31-master-formasi-excel-design.md` (`position_unit` carries `kuota` and `bidang`; `positions` is the name catalog with `rm_level`).

## 1. Goal

After the database reset, the database is the single master of Karpim formasi (the Excel was a one-off reference and is removed). An admin must be able to add and edit units, jabatan and seats (kuota, bidang, which jabatan sits in which unit) in the app, safely: every change is traceable, risky changes show their impact first, and no change can leave employees pointing at something that no longer exists.

Success criteria
- An admin can maintain the whole formasi from the app without a developer or a re-seed.
- Every change has an audit row (who, when, what, old and new value) written in the same transaction as the change.
- An edit that would orphan employees is refused with a message that says who is affected and what to do.
- The server enforces every rule; the screen only mirrors it.

## 2. Decisions (from brainstorming)

| # | Decision |
|---|---|
| 1 | **Direct edit, fully audited.** Any admin edits and it applies immediately; no second approver. The schema leaves room for an approval step later. |
| 2 | **Hybrid by risk** for collisions with employees (section 5). |
| 3 | **Dedicated audit table plus a read-only history screen.** |
| 4 | **A dedicated Formasi screen, one unit at a time.** Units and Positions screens stay as the catalog screens. |
| 5 | **One explicit `MasterFormasiService`** holds the rules and writes the audit; no observers or triggers. |

Facts about the current code that shape the design: only two roles exist (`admin`, `user`) and `/admin/*` is guarded by `role:admin`; `UnitController` and `PositionController` hold their logic inline; deletes are already blocked when employees reference the record; employees link to units and jabatan by text (`unit_kerja`, `jabatan`).

## 3. Data model

- New table `master_audit_logs` (append-only): `id`; `user_id` (nullable FK to users, null on delete); `user_name` (snapshot); `action` (string, section 4); `entity_type` (`unit|position|seat`); `entity_id` (nullable, the id at the time); `entity_label` (name at the time, e.g. `Kebun Tajati — Manajer` for a seat); `old_values` and `new_values` (json, nullable); `reason` (text, nullable); `created_at`. No `updated_at`. Indexes on (`entity_type`, `entity_id`), `user_id`, `created_at`.
- `App\Models\MasterAuditLog` refuses `update` and `delete` (throws), so the trail cannot be edited through the application.
- The audit stores counts of affected employees, never their NIK or names (UU PDP).
- No change to `units`, `positions` or `position_unit`. `positions.bidang` stays a non-authoritative hint and is not edited by this feature.

## 4. Service

`App\Services\Formasi\MasterFormasiService`. Every public method takes the acting `User`, runs inside one `DB::transaction()`, validates through `previewImpact()`, applies the change, writes the audit row, and returns the changed model. If the audit write fails, the change rolls back.

| Area | Methods (acting user always the last argument) |
|---|---|
| Unit | `createUnit(array)`, `updateUnit(Unit, array, ?string $reason)`, `setUnitActive(Unit, bool, ?string $reason)`, `deleteUnit(Unit, ?string $reason)` |
| Jabatan | `createPosition(array)`, `updatePosition(Position, array, ?string $reason)`, `setPositionActive(Position, bool, ?string $reason)`, `deletePosition(Position, ?string $reason)` |
| Seat | `addSeat(Unit, Position, int $kuota, string $bidang)`, `updateSeat(int $positionUnitId, int $kuota, string $bidang)`, `removeSeat(int $positionUnitId, ?string $reason)` |
| Preview | `previewImpact(string $operation, array $params): ImpactResult` |

`ImpactResult` is an immutable value object: `blocked` (bool), `blockers` (list of safe messages), `warnings` (list), `affectedEmployees` (int). The Livewire screen calls `previewImpact()` for its live panel; the mutating methods call it again and throw `FormasiRuleViolationException` (safe message, like `MutasiRuleViolationException`) when `blocked`.

`UnitController` and `PositionController` become thin: validation stays in the controller, the work goes to the service.

Audit actions: `unit.created|updated|renamed|activated|deactivated|deleted`, `position.created|updated|renamed|activated|deactivated|deleted`, `seat.added|updated|removed`. An edit that changes nothing writes no audit row.

## 5. Rules

"Filled" means employees whose `unit_kerja` and `jabatan` equal the seat's unit name and jabatan name (the same definition `FormationService` uses for occupancy).

| Situation | Behavior |
|---|---|
| Lower kuota below filled | **Allowed**, flagged "melebihi kuota" on the Formasi screen (MPP already shows a surplus as a negative kebutuhan) |
| Remove a seat that has employees or a pending scheduled mutation | **Blocked**; message states the count and sends the admin to Mutasi |
| Deactivate a unit with employees | **Blocked**, same message |
| Deactivate a jabatan held by employees | **Blocked**, same message |
| Delete a unit or jabatan | **Only when** no employees, no seats, and no `job_histories` rows reference it; otherwise deactivate |
| Rename a unit or jabatan | **Allowed**; `employees` text and pending (not yet applied) scheduled mutations are updated in the same transaction (DOMAIN.md section 3.5); applied `job_histories` rows keep the original text |
| Change a jabatan's RM | **Allowed with a warning** naming how many units use it |
| Add a seat | Jabatan and unit must be active; the pair must not already be a seat; kuota is a whole number of at least 1; **bidang is required** for a Karpim seat and must be one of KEU, TAN, TEK, UMU |
| Reason | **Required** (at least 10 characters) for: remove seat, deactivate, delete, rename, change RM |

The editor never moves people between seats. That stays in the Mutasi module so every placement change keeps its `job_histories` record (SECURITY.md section 5).

## 6. Screens

- **`/admin/master/formasi`** (Livewire `Admin\Master\FormasiEditor`): choose a unit (existing unit combobox); a table of its seats with jabatan, RM (read-only), bidang, kuota, filled, and an over-quota badge. Edit kuota and bidang in place; "Tambah Jabatan" lists active jabatan not yet in the unit; "Hapus Kursi". Each change opens the confirm modal with the live impact panel (blockers, warnings, affected count) and a reason field when required. Explicit loading states, a 300 ms debounce on search, desktop-first layout, design tokens from DESIGN.md.
- **`/admin/master/riwayat`** (controller and Blade view): read-only list, newest first, filters for entity type, user and date range, paginated. Each unit's page also shows its last few changes.
- All user-facing text uses official business terms and never names the developer documents. Navigation entries are added next to the existing Master Data links.

## 7. Security and privacy

Admin-only routes (existing `role:admin` group), CSRF on every form and Livewire action, validated input, parameter binding only. The history screen shows who changed what but no employee data. The audit table has no application path that edits or deletes rows.

## 8. Testing

One test class per system under test:
- `MasterFormasiServiceTest`: every rule in section 5, audit row content, rollback when the audit fails, no audit row for a no-op edit.
- `FormasiEditorTest` (Livewire): select a unit, edit and add and remove seats, impact panel states, blocked messages, reason requirement, authorization.
- `MasterAuditLogTest`: the model refuses update and delete; the history screen filters and paginates.
- `MasterDataTest` updated: unit and position screens still behave, and now produce audit rows.
- Pint on every changed file; the full suite must stay green apart from the 5 known date-dependent failures.

## 9. Documentation

DOMAIN.md section 3.5 (editing rules) and section 9 (new service contract); SECURITY.md section 5 (new item: master-change audit trail).

## 10. Assumptions to confirm

1. Retention of audit rows is indefinite (no purge job).
2. The history screen is visible to every admin; there is no auditor-only role.
3. A jabatan's RM and level are edited on the Positions screen, not the Formasi screen; the Formasi screen only shows RM.
4. Karpel jabatan are out of scope for seat editing, consistent with the rest of the formasi work.

## 11. Out of scope

Excel import, a second approver, effective dates and revisions, an employee-to-seat key, bulk editing, an auditor-only role, and moving employees (Mutasi).
