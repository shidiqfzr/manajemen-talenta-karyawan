# Organisation Structure Decree (SK) Management — Design

Date: 2026-11-16 · Status: approved by the user (open questions answered 2026-11-16) · Size: project (three phases)

## 1. Intent and why

PTPN IV Regional V changes its organisation structure by decree (Surat Keputusan, SK) with an effective date. The first SK in this system is dated 1 September 2026 and later SKs will follow. Today the only way to adopt a new structure is to regenerate `master_formasi.json` and reseed, which works only on a database that can be wiped. `DOMAIN.md` §3.6 already says the database is the source of truth after the baseline and the seeder must never run on production, so there is no sanctioned path for the next SK.

Goal: a controlled, auditable way to apply each SK to a live database, aligned with enterprise HRIS practice and with the SSOT files (`AGENTS.md`, `DOMAIN.md`, `DESIGN.md`, `ENGINEERING.md`, `SECURITY.md`).

## 2. Goals, non-goals, anti-goals

**Goals**
- Every structure change is tied to an SK record: number, SK date, effective date, source workbook (hash), who submitted, who approved, when applied.
- Review before apply: a grouped diff against the current structure plus an employee impact panel.
- Four-eyes approval: the submitter cannot approve.
- Stage then apply: an approved SK applies itself on its effective date (immediately if that date is today or past).
- Apply is atomic (`DB::transaction()`), reuses `MasterFormasiService` rules, and is fully audit-logged.
- A snapshot of the resulting structure is stored per applied SK, so "what was the structure under SK X" can always be answered.
- The SSOT files carry the rules so future agents follow them.

**Non-goals (this project)**
- Full effective-dating of `units`, `positions`, `position_unit` and "as of date" MPP or Standar Formasi reports. The snapshot keeps this door open; a separate project would use it.
- Automatic employee reassignment. Employees move only through Mutasi, as `DOMAIN.md` already requires.
- Forbidding all manual Master Data edits (see §6, my call).
- Parsing SK PDF text. The structured source is the workbook.

**Anti-goals (failure even if it technically works)**
- Editing `master_formasi.json` or rerunning the seeder to apply an SK to a database that must be kept.
- Silent history loss: renames or removals that make old `job_histories` or audit entries unreadable.
- A path that skips the preview or the second approver.

## 3. Constraints

- Laravel 11, Livewire, PHPUnit 11, Pint. Multi-table writes in `DB::transaction()`. Livewire search debounce 300 ms.
- One test class per system under test (`AGENTS.md` rule 5).
- UI text uses official business terms and never names internal documents (rule 6) or `SSOT`. File names appear only in code comments. Chat and new code in English; UI strings and SSOT prose in Indonesian.
- Terminology per `DOMAIN.md` §2.0 ("Karyawan", "Formasi Jabatan"; "kursi" and `seat` stay internal).
- UU PDP: the workbook has no personal data, so it is kept as SK evidence and is not subject to the 2-hour import purge. Uploads validate MIME type and size (`SECURITY.md`).
- Existing building blocks to reuse: `FormasiExcelParser` and `FormasiNormalizer`; `MasterFormasiService` (rules, `ImpactResult`, `master_audit_logs`, unit aliases); the maker-checker pattern of `EmployeeImportGovernanceService` (file hash, review digest, approval switch); the scheduler in `routes/console.php`; `NotificationCenterService`.

## 4. Approaches considered

1. **Extend the seed command** (diff and apply via CLI, seeder-style). Rejected: no approval step, no UI, no audit link, and the seeder's attribute-overwrite behaviour is unsafe on live data.
2. **SK record as an orchestrator over the existing Master Data service** (chosen). A decree stores the target structure and its diff. Applying it calls the same `MasterFormasiService` operations a human would, in one transaction, so there is a single rule path and the existing audit and alias behaviour is kept.
3. **Full temporal tables** (valid-from/valid-to on every master table). The most complete but touches every query and report. Deferred; the per-SK snapshot is the cheap step toward it.
4. **Event sourcing of structure changes.** Overbuilt for this domain.

Timing, picked in conversation: **stage, then apply on the effective date** (option B), reusing the scheduled-apply pattern already used for Mutasi.

## 5. Design

### 5.1 Lifecycle

`Draft → Diajukan (submitted) → Disetujui (approved) → Terjadwal (scheduled) / Diterapkan (applied)`; terminal alternatives: `Ditolak` (rejected), `Dibatalkan` (cancelled), `Perlu Tinjau Ulang` (stale, see §5.5).

1. **Draft:** admin enters SK number, SK title, SK date, effective date, uploads the workbook (required) and optionally the SK PDF. The system parses it with the shared parser and validates it.
2. **Review:** the system computes the diff against the current structure (§5.2) and the impact (§5.4). The admin resolves unit mapping (§5.3). Blockers prevent submission.
3. **Diajukan:** the draft is frozen with a review digest (hash of file plus diff).
4. **Disetujui:** a different admin reviews the same diff and approves, or rejects with a reason. Approval follows the same switch that governs import approval.
5. **Apply:** effective date ≤ today applies immediately on approval. Otherwise status is `Terjadwal` and the daily command applies it on the date.
6. **Diterapkan:** the transaction commits, the snapshot is stored, admins are notified.

Only **one open decree** (draft through scheduled) may exist at a time, so diffs never stack on each other (my call).

### 5.2 Diff

A pure calculator (`StructureDiffCalculator`, no database writes) compares the parsed target structure with the current database and returns typed changes:

- Unit: renamed (via mapping), added. The workbook carries no `kode`, `wilayah` or `urutan`, so existing units keep theirs; the admin supplies these only for new units in the review step (my call). A unit missing from the workbook is **never** deactivated implicitly: an SK can cover only part of the structure (the 1 September 2026 SK is titled "Perubahan Struktur Organisasi Region Office"). Deactivation happens only when the admin marks a specific unit as closed in the review step.
- Formasi Jabatan (unit + position): added, removed, quota change, bidang change, RM band change (position-wide), moved between units (a removed and an added pair with the same position name and attributes, shown as a suggested move; applied as remove plus add).
- Totals before and after (units, pairs, quota by RM and bidang) for the sanity check.

### 5.3 Unit identity

A rename cannot be told apart from remove plus add automatically. Units match by exact name or alias first. Remaining unmatched old and new units appear in a mapping step; the admin pairs them (old → new) or leaves them as removal and addition. The mapping is stored on the decree. Applying a mapped rename uses `updateUnit`, which records the old name as an alias and syncs employee text; `job_histories` keep the name that applied at the time.

### 5.4 Employee impact and removals

Apply never moves employees. Rules, consistent with `DOMAIN.md` §3.5:

- A Formasi Jabatan with no holders that disappears is removed.
- A Formasi Jabatan with holders that disappears or shrinks is set to its new quota (0 if removed). It shows as over quota, which the system already supports, and is listed on a "Karyawan Perlu Penempatan Ulang" worklist for that decree, linked to Mutasi.
- A unit the admin marks as closed is deactivated (never deleted); if it still has employees it stays active and flagged until they are placed elsewhere. Formasi Jabatan are compared only for units that appear in the workbook, so units outside the SK's scope are untouched.
- The impact panel before submission shows counts: affected employees, over-quota seats, scheduled Mutasi that reference changed units.

### 5.5 Staleness

The diff is computed at submission and again at apply time. If the current structure changed in between (a manual edit or other change), the digests differ: the decree becomes `Perlu Tinjau Ulang`, nothing is applied, and admins are notified. A new review (re-submit and re-approve) resets it. Cancelling is always allowed before apply.

### 5.6 Apply

`StructureDecreeService::apply()` runs inside one `DB::transaction()`, locks the decree row, verifies status and digest, then calls `MasterFormasiService` operations in a fixed order (units, positions and RM, seats and bidang, deactivations). Each audit entry gets the decree id (new nullable `decree_id` on `master_audit_logs`) and the reason `SK <number>`. Any failure rolls everything back and marks the decree `Gagal`, with the error recorded and admins notified. The snapshot of the resulting structure is stored as JSON on the decree.

### 5.7 Data model

New tables (English identifiers):
- `structure_decrees`: id, sk_number (unique; stored normalised, e.g. `DPSB/SKPTS/ 352 /IX/2026` becomes `DPSB/SKPTS/352/IX/2026`; no strict format check, since numbering schemes may change), title (the SK's "Tentang" line), revokes (nullable text: earlier SK(s) replaced), note (nullable text), sk_date, effective_date, status, source_path, source_sha256, pdf_path (nullable), diff (json), unit_mapping (json), review_digest, snapshot (json, set on apply), submitted_by/at, decided_by/at, decision_reason, applied_at, failure_message, timestamps.
- `master_audit_logs.decree_id` nullable foreign key.

No other master table changes.

### 5.8 Interfaces

- Admin Master menu entry "Surat Keputusan Struktur": list, create (stepper: Data SK → Tinjau Perubahan → Ajukan), detail with timeline, decision and the worklist. Statuses use semantic badges from `DESIGN.md`; the diff and impact use the live impact panel pattern; explicit Livewire loading states; destructive confirmation modal for approve, reject and cancel.
- Command `hr:apply-structure-decrees`, scheduled daily at 00:00 so it runs before `hr:apply-effective-mutations` (00:01).
- Seeder: records one baseline decree (status applied, SK date 1 September 2026) so the history on a fresh install starts from a known version. Its details come from a small data file: number `DPSB/SKPTS/352/IX/2026`, title "Perubahan Struktur Organisasi Region Office PT Perkebunan Nusantara IV", SK date and effective date 1 September 2026 (confirmed by the user).
- `formasi:build-seed` stays for fresh-install baselines only; its docs say so.

### 5.9 SSOT and doc updates (Phase 1, ships alone)

- `DOMAIN.md`: new §3.7 "Tata Kelola Revisi Struktur Organisasi (SK)" with the rules below, and a cross-reference from §3.6.
- `AGENTS.md`: one line under pillar 1 and a new behaviour rule: structure changes only via an SK; the database is the source of truth; no reseed on kept databases; code, not the JSON, changes structure.
- `SECURITY.md`: SK upload validation, four-eyes, audit link. `ENGINEERING.md`: the apply command, its schedule and the transaction rule. `DESIGN.md`: status badges and the diff panel.

Rules for §3.7 (Indonesian in the file):
1. Database adalah sumber kebenaran struktur; berkas Excel dan JSON hanya masukan.
2. Setiap perubahan struktur wajib merujuk satu SK (nomor, tanggal SK, tanggal berlaku).
3. Perubahan tidak menyunting riwayat: nama lama menjadi alias, kursi berpenghuni tidak dihapus, unit dinonaktifkan bukan dihapus.
4. Penerapan hanya melalui layanan SK (pratinjau, persetujuan empat mata, transaksi, jejak audit); tidak dengan menyunting JSON atau menjalankan ulang seeder pada basis data yang dipertahankan.
5. Karyawan terdampak dipindahkan hanya melalui Mutasi.

### 5.10 Tests (one class per SUT)

- `StructureDiffCalculatorTest` (unit): renames via mapping, moves, quota/bidang/RM changes, totals, no-change case.
- `StructureDecreeServiceTest` (feature): lifecycle and statuses, four-eyes, immediate vs scheduled apply, stale detection, atomic rollback on failure, removals with holders, audit entries carry the decree id, snapshot stored.
- `ApplyStructureDecreesCommandTest`: applies only due approved decrees; skips others; idempotent.
- A Livewire test for the review and approval screens, and Pint on all new code.
- The existing parser and normalizer tests stay the contract for workbook reading.

## 6. Calls I made (please check)

1. Only one open decree at a time.
2. Manual Master Data edits stay available (with the existing reason and audit rules) rather than being blocked; the decree check in §5.5 catches conflicts. Revisit if admins bypass SKs in practice.
3. Over-quota plus a worklist, instead of blocking an SK that removes occupied seats.
4. The snapshot (JSON per applied SK) instead of temporal tables.
5. Apply schedule 00:00, before scheduled Mutasi.
6. The workbook is kept as evidence with its hash and is not purged.

## 7. Open for the user

Resolved by the user (2026-11-16):
- SK number is `DPSB/SKPTS/352/IX/2026`.
- Effective date is 1 September 2026, the same day as the SK date. Basis: diktum KETIGA ("Keputusan ini berlaku sejak tanggal ditetapkan"). The effective-date field therefore **defaults to the SK date** in the form, and stays editable for SKs that state another date.
- Scope: all 47 units in the workbook follow this SK, including kebun, PKS and Unit Group units whose rows are carried over from the previous structure. An SK governs exactly the units listed in its workbook (see §5.2). The decree note records that those rows were adopted as the current structure, not re-decided by this SK.
- Diktum KEEMPAT revokes the earlier Region Office structure rules (Regional I–VII) from the effective date. The decree gets an optional "mencabut/menggantikan" text field for the earlier SK(s) it replaces, shown on the detail page and kept in history.

Still open (non-blocking): whether a single admin may approve their own SK when only one admin exists (I would follow the existing import-approval switch).
- Whether a single admin may approve their own SK when only one admin exists (the import approval has a switch for this; I would follow it).

## 8. Left to the builder

Exact column types and indexes, Livewire component layout within the `DESIGN.md` patterns, diff wording, notification text, and the order of operations inside apply as long as the rules above hold.

## 9. Phasing

1. **Docs and SSOT rules** (no code risk).
2. **Data model, diff calculator, service, command, seeder baseline** with tests.
3. **Admin screens** (list, stepper, review, approval, worklist) with tests.
