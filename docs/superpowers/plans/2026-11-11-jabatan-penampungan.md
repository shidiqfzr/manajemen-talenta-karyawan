# Jabatan Penampungan (Advisor and Diperbantukan) Implementation Plan

> Executed inline in the same session (TDD, one test class per component, Pint, independent review at the end).

**Goal:** Let an admin register Advisor and Diperbantukan Jabatan and open them in the units that use them, so employees in those positions can be added (manual and Excel import) without a formasi seat, a quota, or any effect on formasi and vacancy counts.

**Architecture:** Pool positions already exist as `positions` rows with `is_overflow` / `is_diperban`. The employee guard, the import validator, the Excel template and the unit-positions API all expect a pool position to be attached to a unit through `position_unit` (quota ignored). What was missing is any admin path to create and attach them, and a clean separation from formasi counts. This plan adds that path and keeps everything else unchanged.

**Ruling (deviation from the conversation's recommendation):** pool Jabatan are **opened per unit** (a `position_unit` row with quota 0), not valid in every unit. Reason: import, manual add and the template are already built that way; per-unit opening gives HR control over where a pool Jabatan may be used; no change to those paths is needed. Cost if wrong: HR must open a pool Jabatan in each unit that uses it.

**Tech Stack:** Laravel 11, Livewire 3, PHPUnit 11, Blade shared table components, Pint.

## Global Constraints
- UI text Indonesian; code, comments, commits English. No doc file names or "SSOT" in visible text; "Karyawan", never "Pegawai"; no "kursi"/"seat" in visible text.
- Pool Jabatan: `level` Karpim, RM-1 to RM-4, `bidang` UMU (as seeded), type Advisor (`is_overflow`) or Diperbantukan (`is_overflow` and `is_diperban`, as seeded). No quota: the `position_unit` row has `kuota` 0.
- Every master change goes through `MasterFormasiService` in one `DB::transaction()` with a `master_audit_logs` row; closing needs a reason of at least 10 characters and is blocked while employees hold the Jabatan in that unit.
- Pool attachments never appear in formasi counts, tables or KPIs (Formasi Unit Kerja, Sebaran Formasi, vacancies).
- Admin-only screens (`abort_unless(role === 'admin', 403)`).
- Open question for HR, not decided here: whether "Diperbantukan" is a disciplinary sanction (current DOMAIN.md text) or a secondment. Mutasi rules are untouched.

## Tasks
1. **Service** (`MasterFormasiService`): `createPoolPosition`, `openPoolPosition`, `closePoolPosition`, audit labels. Tests in `MasterFormasiServiceTest`.
2. **Formasi screens ignore pool attachments** (`FormasiEditor` seats and unit overview). Tests in `FormasiEditorTest`.
3. **Tab "Jabatan Penampungan"** (Livewire `PoolPositions`, route `admin.master.penampungan.index`, section nav): catalog of pool Jabatan with create, open in unit and close; read-only "Bezetting per Unit" view. Tests in `PoolPositionsTest` and `MasterDataTest`.
4. **Compatibility**: an opened pool Jabatan is accepted by manual add and import, an unopened one is not. Tests in `EmployeeCrudTest` and the import test class.
5. **Docs and verification**: DOMAIN.md (§3.6 item 6, §3.5, §9.7), DESIGN.md (§8.5 item, changelog), full suite, Pint, independent review.
