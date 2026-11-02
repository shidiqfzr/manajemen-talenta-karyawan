# Master Formasi from Excel — Design (v2, reset scope)

Status: draft for review · Date: 2026-10-31 · Branch: `feat/mutasi-blocking-ux`
Source file: `Unit dan Jabatan Karyawan Pimpinan.xlsx` · Earlier analysis: `docs/formasi-comparison-excel-json-db.md`

v2 replaces the first draft, which planned a recurring importer, revisions and an employee migration. The application is still in development, so the database is reset and the employees are re-imported from their own Excel file.

## 1. Goal

The Excel is the single master of Karpim formasi (units, jabatan, RM level, bidang, kuota) and drives MPP, vacancy and mutasi. After the reset, the database holds exactly what the file says, and every imported employee points to a real seat.

Success criteria
- Seeded totals equal the file: 45 units, 392 distinct seats keys, 444 seats (a test asserts this).
- `MANAJER/TAN`, `MANAJER/TEK` and `MANAJER/UMU` can coexist (the name-collision bug is gone).
- Employee import resolves each row to a seat; rows that cannot be resolved are rejected with row numbers, not silently stored as free text.

## 2. Decisions

| # | Decision |
|---|---|
| 1 | The Excel is **absolute truth**. Nothing outside it exists after the reset. |
| 2 | **Full reset** of formasi and employee data, with a database dump taken first. Employees are re-imported from the employee Excel. |
| 3 | **Bidang and RM live on the seat row.** `positions` is a name catalog. |
| 4 | **Employees get an FK to their seat** (`employees.position_unit_id`). `jabatan` and `unit_kerja` text stay as a display cache. |
| 5 | **Unit aliases** map the file's ALL CAPS names to app units; wilayah for units the app lacked is assigned in the seed data. |
| 6 | Seed file is regenerated from the Excel by a one-off command. The recurring importer, revisions, diff UI, holding pool and migration maps are **deferred**. |

Verified in the file: 4 columns (`Bagian/Unit Kerja`, `Nama Jabatan`, `Level RM`, `Bidang`), one row per seat (kuota = repeated rows), no blank rows, no conflicting RM/bidang per (unit, jabatan).

## 3. Data model

- `positions`: catalog, unique on normalized `nama`. Drop `bidang`, `rm_level` (no data to keep).
- `position_unit` (the seat row): add `bidang`, `rm_level`; keep `kuota`; unique on (`position_id`, `unit_id`). Add nullable `effective_from`/`effective_to` now, so as-of reports remain possible later.
- `units`: add `unit_aliases` (`unit_id`, `alias_normalized` unique). `wilayah` stays required.
- `employees`: add `position_unit_id` FK (restrict on delete). `jabatan`/`unit_kerja` text kept in sync from the seat.
- Existing rules stay: NIK `string(8)`, `DB::transaction()` for multi-table writes, audit in `job_histories`.

## 4. Components

- **`FormasiExcelParser`** (`app/Services/Import/`): reads the file, validates headers and values (`RM-1..RM-3`, `KEU|TAN|TEK|UMU`), groups rows into seats, counts kuota. No database access.
- **`FormasiNormalizer`**: casing and typo dictionary (`AKUNTASI`→`AKUNTANSI`, `REKRUITMEN`→`REKRUTMEN`, `PERIJINAN`→`PERIZINAN`, `ADMINISTASI`→`ADMINISTRASI`, `GROUP MANAJER`→`GROUP MANAGER`, `MANAJER`/`MANAGER`, `&`/`DAN`). Display names follow the app's current capitalization.
- **Artisan command `formasi:build-seed`**: Excel → `database/data/master_formasi.json` (includes wilayah per unit, set once and committed).
- **Seeder** loads the JSON into units, aliases, positions and seats inside a transaction.
- **Readers moved to the seat row** (about 66 references in 26 files): `ManPowerPlanningService`, `MutasiService`, `FormationService`, `MutasiLookup`, `ChainTopologyAnalyzer`, `TalentProfileBuilder`, `PositionController`, exports, `EmployeeObserver`. RM/bidang calculation stays in `ManPowerPlanningService`.
- **Employee import** (`EmployeesImport`, `EmployeeImportDictionary`, row validator): resolves (unit, jabatan) through aliases and normalization to a seat, sets `position_unit_id`, and rejects unresolved rows with the row number and reason.

## 5. Reset procedure

1. Dump the database (the user keeps the file).
2. Migrate: schema changes above, then reset formasi and employee-related tables.
3. Run the seeder (formasi only).
4. Re-import employees from the employee Excel.
5. Verify: seat totals match the file; every employee has a seat; vacancy and MPP screens load.

## 6. Security and privacy

Admin-only import, CSRF, MIME/extension and size validation on upload. Error reports show row numbers and jabatan, not NIK. User-facing text uses official business terms; internal document names stay in code comments only.

## 7. Testing

One test class per system under test:
- `FormasiExcelParserTest` (unit): headers, grouping, kuota counting, normalization, invalid RM/bidang.
- `FormasiSeederTest` (feature): seeded totals equal the file (45 / 392 / 444), `MANAJER` coexists in three bidang, aliases resolve.
- `EmployeesImportTest` (feature, extend the existing class): seat resolution, rejected rows, FK set.
- Existing suites updated for the reader migration (MPP, Mutasi, FormationService). Pint on all changed files.

## 8. Assumptions to confirm

1. The employee Excel uses (or will be edited to use) the new jabatan and unit names. If it keeps old names such as `Asisten Inti I`, those rows are rejected until the names or an alias is fixed.
2. Over-quota (more employees than kuota) is flagged on screens but does not block an import.
3. Karpel formasi (RM-4) is out of scope; this master covers Karpim only.
4. `wilayah` for the 10 units new to the app is assigned in the seed data (to be reviewed by you).
5. Rayon distinctions (`Asisten Kepala Tanaman Rayon Utara/Selatan`) are merged by the file and not preserved.

## 8a. Amendments found while planning (supersede sections 3 to 4 where they conflict)

1. **`rm_level` stays on `positions`.** Verified in the file: 0 of 164 jabatan names have more than one RM. Only `bidang` varies by unit (`KEPALA BAGIAN`, `MANAJER`), so only `bidang` moves to `position_unit.bidang`. `positions.bidang` is kept but no longer read or written (drop deferred). This avoids migrating ~60 RM readers.
2. **No `employees.position_unit_id` FK for now.** Mutasi flows rewrite the `jabatan`/`unit_kerja` text in many places, so an FK would drift. The seat is found by (unit name, jabatan name) through `FormasiCatalog`. The FK returns with the recurring importer, which is the feature that needs it.
3. **No `unit_aliases` table.** Alias handling stays in `EmployeesImport::UNIT_ALIASES`; the hardcoded `Employee::OFFICIAL_UNITS` is replaced by the units in the database. Aliases that point to retired units are removed or retargeted.
4. **MPP `standar_formasi` is derived from the seats.** The hardcoded matrix in `ManPowerPlanningService::getBaselineData()` equals the Excel exactly (RM-1 2/27/11/5, RM-2 5/38/12/16, RM-3 63/146/61/58 for KEU/TAN/TEK/UMU), so it becomes the acceptance test for the new derivation. Realisasi bidang uses the employee's seat bidang, falling back to the keyword heuristic when the employee has no seat.
5. **Pool positions** (Advisor RMn, Karyawan Diperbantukan RMn, and the three office Diperbantukan titles) are system infrastructure and stay seeded outside the Excel.

## 9. Out of scope (deferred)

Recurring import with diff and confirm, revisions and scheduled apply, holding pool and employee mapping tables, maker-checker approval, per-seat IDs, Karpel formasi.
