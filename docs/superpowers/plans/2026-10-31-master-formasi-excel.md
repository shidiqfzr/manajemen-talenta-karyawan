# Master Formasi from Excel Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make `Unit dan Jabatan Karyawan Pimpinan.xlsx` the single master of Karpim formasi so the seeded data, the MPP "Standar Formasi" and employee import validation all derive from it.

**Architecture:** An Artisan command converts the Excel into the existing `database/data/master_formasi.json` shape. The seeder loads it, with bidang stored on the `position_unit` seat row. A small `FormasiCatalog` service answers "bidang of this seat" and "standar formasi per RM and bidang" for `ManPowerPlanningService` and the employee import.

**Tech Stack:** Laravel 13, PHP, PhpSpreadsheet (already installed), Livewire, PHPUnit 11 (sqlite in-memory), Laravel Pint.

**Spec:** `docs/superpowers/specs/2026-10-31-master-formasi-excel-design.md`. Section 8a (amendments) overrides sections 3 and 4 where they conflict.

## Global Constraints

- Work on a new branch `feat/master-formasi-excel` (current branch is `feat/mutasi-blocking-ux`). Commit only when the user has authorized execution.
- NIK stays `string(8)`; multi-table writes inside `DB::transaction()`; Pint (`./vendor/bin/pint`) on every changed file.
- One test class per system under test; do not split a SUT's tests across files.
- User-facing text (Blade, validation, notifications) must use official business terms and never name `AGENTS.md`, `DOMAIN.md`, `DESIGN.md`, `ENGINEERING.md`, `SECURITY.md` or "SSOT". Those names are allowed only in code comments.
- Excel facts: 4 columns (`Bagian/Unit Kerja`, `Nama Jabatan`, `Level RM`, `Bidang`), one row per seat, 444 rows, 45 units, 392 distinct (unit, jabatan) keys, RM only `RM-1..RM-3`, bidang only `KEU|TAN|TEK|UMU`.
- Display names follow the existing capitalization style, for example `Bagian Sekretariat dan Hukum`, `Kebun-PKS Longkali`, `Asisten Humas & Protokoler`. Units replace `&` with `dan`; jabatan keep `&`.
- Typo fixes (both unit and jabatan): `AKUNTASI`→`AKUNTANSI`, `ADMINISTASI`→`ADMINISTRASI`, `REKRUITMEN`→`REKRUTMEN`, `PERIJINAN`→`PERIZINAN`, `GROUP MANAJER`→`GROUP MANAGER`.
- Acronyms stay uppercase: `PKS PKR PMO ERP SAP TI TJSL UMK HPS URTA KKPA T3M PSDM SDM`; roman numerals (`I`..`XII`, `IIA`) stay uppercase.
- `database/data/unit_metadata.json` (kode, wilayah, urutan per unit) is reviewed by the user before the reset.

## Review Focus

1. A seat repeated with a different RM or bidang in the same unit: parse error with row numbers, never silently merged (Task 1).
2. A unit in the Excel with no entry in `unit_metadata.json`: the command fails and lists the names instead of inventing a wilayah (Task 3).
3. Running the seeder twice: kuota is set, not incremented, so totals stay 444 (Task 4).
4. An employee on a pool position (Advisor, Diperbantukan) or a Karpel position that has no seat: `determineBidang` falls back to the heuristic with no exception (Task 5).
5. `UNIT_ALIASES` entries that point to units no longer in the database must not let an old name pass validation (Task 7).

---

## File Structure

- Create `app/Services/Formasi/FormasiNormalizer.php`: casing and typo rules for unit and jabatan names.
- Create `app/Services/Formasi/FormasiExcelParser.php`: file to seats and errors, no database access.
- Create `app/Services/Formasi/FormasiCatalog.php`: read model over `position_unit` for bidang and standar formasi.
- Create `app/Console/Commands/BuildFormasiSeed.php`: Excel to `master_formasi.json`.
- Create `database/data/unit_metadata.json`: kode, wilayah, urutan for the 45 units.
- Create `database/migrations/2026_10_31_100000_add_bidang_to_position_unit_table.php`.
- Modify `database/seeders/PositionUnitSeeder.php`, `app/Models/Position.php`, `app/Models/Unit.php`, `app/Services/ManPowerPlanningService.php`, `app/Http/Controllers/Admin/Master/PositionController.php` (plus its views), `app/Models/Employee.php`, `app/Imports/EmployeesImport.php`, `app/Services/Import/EmployeeImportRowValidator.php`, `app/Exports/Sheets/EmployeeTemplateReferenceSheet.php`.
- Tests: `tests/Unit/FormasiExcelParserTest.php`, `tests/Feature/BuildFormasiSeedCommandTest.php`, `tests/Feature/FormasiSeederTest.php`, `tests/Feature/FormasiCatalogTest.php`; extend `tests/Feature/ManPowerPlanningTest.php`, `MasterDataTest.php`, `EmployeeImportTest.php`.

---

### Task 1: Normalizer and Excel parser

**Files:**
- Create: `app/Services/Formasi/FormasiNormalizer.php`, `app/Services/Formasi/FormasiExcelParser.php`
- Test: `tests/Unit/FormasiExcelParserTest.php`

**Interfaces:**
- Produces: `FormasiNormalizer::unit(string $raw): string`, `FormasiNormalizer::jabatan(string $raw): string`
- Produces: `FormasiExcelParser::parse(string $path): array{seats: list<array{unit: string, jabatan: string, rm_level: string, bidang: string, kuota: int}>, errors: list<array{row: int, message: string}>}`. `unit` and `jabatan` are already normalized; seats keep the Excel's first-seen order.

- [ ] **Step 1: Write the failing tests.** The test builds small xlsx files in a temp dir with PhpSpreadsheet. Test names and assertions:
  - `test_normalizer_formats_units_like_existing_master`: `unit('BAGIAN SEKRETARIAT & HUKUM') === 'Bagian Sekretariat dan Hukum'`; `unit('KEBUN-PKS LONGKALI') === 'Kebun-PKS Longkali'`; `unit('GROUP MANAJER KALTIM') === 'Group Manager Kaltim'`; `unit('BAGIAN AKUNTASI DAN KEUANGAN') === 'Bagian Akuntansi dan Keuangan'`; `unit('KEBUN KEMITRAAN AREA KALTIM-SEL') === 'Kebun Kemitraan Area Kaltim-Sel'`.
  - `test_normalizer_formats_jabatan`: `jabatan('ASISTEN HUMAS & PROTOKOLER') === 'Asisten Humas & Protokoler'`; `jabatan('ASISTEN AFDELING VII DAN VIII') === 'Asisten Afdeling VII dan VIII'`; `jabatan('ASISTEN TJSL') === 'Asisten TJSL'`; `jabatan('ASISTEN ASESMEN DAN REKRUITMEN') === 'Asisten Asesmen dan Rekrutmen'`; `jabatan('Asisten Tajati') === 'Asisten Tajati'`.
  - `test_parser_counts_repeated_rows_as_kuota`: two identical rows give one seat with `kuota === 2`.
  - `test_parser_keeps_same_jabatan_with_different_bidang_in_different_units`: `MANAJER` TAN in unit A and TEK in unit B gives two seats.
  - `test_parser_reports_conflicting_duplicate_in_same_unit` (Review Focus 1): same unit and jabatan with `RM-3/TAN` and `RM-3/KEU` gives an error whose `row` is the second row's Excel row number and no seat for that key.
  - `test_parser_rejects_invalid_rm_and_bidang_with_row_numbers`: `RM-4` and `XYZ` each yield an error with the right row.
  - `test_parser_rejects_missing_or_renamed_headers`: a file whose first header is not `Bagian/Unit Kerja` returns one error with `row === 1` and no seats.
  - `test_parser_ignores_trailing_blank_rows_and_trims_cells`.
- [ ] **Step 2: Run to verify failure.** `php artisan test --filter=FormasiExcelParserTest` → FAIL (classes not found).
- [ ] **Step 3: Implement the two classes** at the signatures above. Normalizer: collapse whitespace, apply the typo map on the uppercase string, `&`→`dan` for units, Title Case with `dan` lowercase, keep the acronym list and roman numerals uppercase, preserve a hyphen with each side capitalized (`Kebun-PKS`, `Kaltim-Sel`). Parser: PhpSpreadsheet `IOFactory::load`, read the first sheet, validate the four headers by exact trimmed text, group by `unit|jabatan`.
- [ ] **Step 4: Run to verify pass.** Same command → PASS.
- [ ] **Step 5: Pint and commit.** `./vendor/bin/pint app/Services/Formasi tests/Unit/FormasiExcelParserTest.php`; commit `feat(formasi): parse and normalize the master formasi Excel`.

---

### Task 2: Schema — bidang on the seat row

**Files:**
- Create: `database/migrations/2026_10_31_100000_add_bidang_to_position_unit_table.php`
- Modify: `app/Models/Position.php` (`units()` relation), `app/Models/Unit.php` (`positions()` relation)
- Test: covered by Task 4's `FormasiSeederTest` (the migration has no behavior of its own).

**Interfaces:**
- Produces: nullable `position_unit.bidang` (`string(3)`); both relations use `->withPivot('kuota', 'bidang')`.

- [ ] **Step 1: Write the migration** adding the column; `down()` drops it. Keep `unique(['position_id','unit_id'])` (verified: no unit has the same jabatan with two bidang).
- [ ] **Step 2: Update both relations** to `withPivot('kuota', 'bidang')`. Do not touch `positions.bidang`.
- [ ] **Step 3: Run `php artisan migrate`** on a scratch sqlite file (`DB_DATABASE=<scratchpad>\t.sqlite`), expected `DONE`; then `php artisan test --filter=MasterDataTest` → PASS (nothing else changed yet).
- [ ] **Step 4: Commit** `feat(formasi): store bidang on the seat row`.

---

### Task 3: Seed builder command

**Files:**
- Create: `app/Console/Commands/BuildFormasiSeed.php`, `database/data/unit_metadata.json`
- Test: `tests/Feature/BuildFormasiSeedCommandTest.php`

**Interfaces:**
- Consumes: `FormasiExcelParser::parse()` (Task 1).
- Produces: command `formasi:build-seed {file} {--out=database/data/master_formasi.json}` writing the existing JSON shape: a list of `{nama, kode, wilayah, is_active: true, urutan, positions: [{nama, bidang, level: 'Karpim', rm_level, kuota}]}` in Excel unit order.
- `unit_metadata.json` maps normalized unit name → `{kode, wilayah, urutan}`.

- [ ] **Step 1: Write the failing tests.** `test_builds_json_in_seeder_shape` (small fixture workbook and fixture metadata path → unit order, `kuota`, `level` are as expected); `test_fails_and_lists_units_missing_from_metadata` (Review Focus 2: exit code non-zero, output contains the missing unit name, no file written); `test_fails_when_parser_reports_errors` (output shows row numbers).
- [ ] **Step 2: Run to verify failure.** `php artisan test --filter=BuildFormasiSeedCommandTest` → FAIL.
- [ ] **Step 3: Implement the command.** Add a `--metadata=` option defaulting to `database/data/unit_metadata.json` so the test can inject a fixture.
- [ ] **Step 4: Create `unit_metadata.json`.** Bootstrap kode, wilayah and urutan from the existing `master_formasi.json` for the 35 known units. For the 10 new units (PMO Investasi Tanaman Wilayah Kalbar, PMO Investasi Tanaman Wilayah Kaltim dan Kalsel-Teng, Group Manager Kalbar, Group Manager Kalselteng, Group Manager Kaltim, Kebun Kumai, Kebun Sintang, PKR Tambarangan, PMO Batu Bara dan Bisnis Lainnya, Proyek Batubara Danau Salak) propose kode and wilayah from the name and mark them in the final report for the user to review. Units retired under the Excel (SPI, Unit Group …, Project Management Office (PMO), Divisi Sistem Manajemen & Sustainability, Kebun PKR Sintang, Proyek Batubara Danau Salak & Buntok) are not included.
- [ ] **Step 5: Run to verify pass**, then run the command on the real file: `php artisan formasi:build-seed "Unit dan Jabatan Karyawan Pimpinan.xlsx"`. Expected output: 45 units, 392 seats keys, 444 seats. Fail the task if any number differs.
- [ ] **Step 6: Pint and commit** (include the regenerated `master_formasi.json`): `feat(formasi): build the formasi seed from the Excel`.

---

### Task 4: Seeder

**Files:**
- Modify: `database/seeders/PositionUnitSeeder.php`
- Test: `tests/Feature/FormasiSeederTest.php`

**Interfaces:**
- Consumes: regenerated `master_formasi.json` (Task 3), `position_unit.bidang` (Task 2).
- Produces: units, positions (name catalog, `rm_level` and `level` from the JSON), seats with `kuota` and `bidang`, plus the pool positions already created by the seeder.

- [ ] **Step 1: Write the failing tests.** `test_seeds_excel_totals`: after `$this->seed(PositionUnitSeeder::class)`, `Unit::count() === 45`, `DB::table('position_unit')->count() === 392`, `DB::table('position_unit')->sum('kuota') === 444`. `test_manajer_coexists_in_three_bidang`: distinct `bidang` of `position_unit` rows for the `Manajer` position equals `['TAN','TEK','UMU']` (sorted). `test_rm_and_bidang_totals_match_excel`: sum of `kuota` per `positions.rm_level` is 45/71/328, per bidang 70/211/84/79. `test_seeding_twice_does_not_change_totals` (Review Focus 3). `test_pool_positions_exist_without_seats`: `Advisor RM-1` and `Karyawan Diperbantukan RM-3` exist with no `position_unit` rows.
- [ ] **Step 2: Run to verify failure.**
- [ ] **Step 3: Update the seeder.** `Position::firstOrCreate(['nama'=>...], [level, rm_level, is_active, is_overflow, is_diperban])` without `bidang`; pivot rows carry `bidang`; the upsert's update columns become `['kuota','bidang']`; `kuota` is set from the JSON (accumulated per seat key inside the run, not across runs). Wrap the run in `DB::transaction()`. Leave the pool-position block as it is.
- [ ] **Step 4: Run to verify pass**, then `php artisan test --filter=FormasiSeederTest`.
- [ ] **Step 5: Pint and commit** `feat(formasi): seed units and seats from the Excel master`.

---

### Task 5: FormasiCatalog and MPP

**Files:**
- Create: `app/Services/Formasi/FormasiCatalog.php`
- Modify: `app/Services/ManPowerPlanningService.php` (`getBaselineData()` `standar_formasi`; `determineBidang()`)
- Test: `tests/Feature/FormasiCatalogTest.php`; extend `tests/Feature/ManPowerPlanningTest.php`

**Interfaces:**
- Produces: `FormasiCatalog::seatBidang(string $unitNama, string $jabatan): ?string` (null when there is no seat); `FormasiCatalog::standarFormasi(): array<string, array<string,int>>` shaped `['RM-1'=>['KEU'=>int,'TAN'=>int,'TEK'=>int,'UMU'=>int], 'RM-2'=>…, 'RM-3'=>…]`, summing `kuota` of seats whose position is not `is_overflow` and not `is_diperban`. Registered with `$this->app->scoped(FormasiCatalog::class)`; loads once per instance.

- [ ] **Step 1: Write the failing tests.**
  - `FormasiCatalogTest::test_standar_formasi_equals_corporate_mpp_matrix`: seed `PositionUnitSeeder`, assert `standarFormasi()` equals `['RM-1'=>['KEU'=>2,'TAN'=>27,'TEK'=>11,'UMU'=>5],'RM-2'=>['KEU'=>5,'TAN'=>38,'TEK'=>12,'UMU'=>16],'RM-3'=>['KEU'=>63,'TAN'=>146,'TEK'=>61,'UMU'=>58]]`.
  - `FormasiCatalogTest::test_seat_bidang_is_per_unit`: `seatBidang('Kebun Tajati','Manajer') === 'TAN'` and `seatBidang('PKS Kembayan','Manajer') === 'TEK'`; `seatBidang('Kebun Tajati','Advisor RM-1') === null`.
  - `ManPowerPlanningTest::test_baseline_standar_formasi_is_derived_from_seats`: `getBaselineData()['standar_formasi']` equals the matrix above.
  - `ManPowerPlanningTest::test_determine_bidang_prefers_the_seat` : an employee with `unit_kerja='Bagian Sumber Daya Manusia dan Sistem Manajemen'`, `jabatan='Asisten Penggajian'` returns `KEU`.
  - `ManPowerPlanningTest::test_determine_bidang_falls_back_to_heuristic_without_a_seat` (Review Focus 4): a pool employee and a Karpel employee return a bidang with no exception.
- [ ] **Step 2: Run to verify failure.**
- [ ] **Step 3: Implement `FormasiCatalog`** (one query joining `position_unit`, `positions`, `units`, keyed by `unit.nama|position.nama`) and wire it into `ManPowerPlanningService` by constructor injection. Replace only the `standar_formasi` literal and make `determineBidang()` ask the catalog first. Leave every other baseline matrix untouched.
- [ ] **Step 4: Run to verify pass**: `php artisan test --filter="FormasiCatalogTest|ManPowerPlanningTest"`.
- [ ] **Step 5: Pint and commit** `feat(mpp): derive standar formasi and bidang from the master seats`.

---

### Task 6: Position master screen

**Files:**
- Modify: `app/Http/Controllers/Admin/Master/PositionController.php`, `resources/views/admin/master/positions/index.blade.php`, `resources/views/components/admin/quick-add-position-modal.blade.php`
- Test: extend `tests/Feature/MasterDataTest.php`

**Interfaces:**
- Consumes: `position_unit.bidang` (Task 2). The `bidang` field stays in the request but is stored on the seat when a `unit_nama` is given.

- [ ] **Step 1: Write failing tests.** `test_creating_position_with_unit_stores_bidang_on_the_seat`: POST with `unit_nama`, `bidang='TAN'` → `positions.bidang` is null and the `position_unit` row has `bidang='TAN'`. `test_position_index_filters_by_seat_bidang`: positions seated in a TAN seat are returned for `?bidang=TAN`.
- [ ] **Step 2: Run to verify failure.**
- [ ] **Step 3: Update the controller and views.** `store`/`update` stop writing `positions.bidang`; the seat is attached with `['kuota'=>1,'bidang'=>…]`; the `bidang` filter becomes `whereHas('units', fn ($q) => $q->where('position_unit.bidang', $bidang))` and the `orderBy('bidang')` is removed. The index view shows the bidang values of the position's seats instead of `positions.bidang`. Read both views and keep the existing layout and design tokens.
- [ ] **Step 4: Run `php artisan test --filter=MasterDataTest`** → PASS.
- [ ] **Step 5: Pint and commit** `refactor(master): bidang follows the seat in the position screen`.

---

### Task 7: Employee import follows the new master

**Files:**
- Modify: `app/Models/Unit.php` (add `Unit::officialNames(): array<string>` cached per request), `app/Models/Employee.php` (remove `OFFICIAL_UNITS`), `app/Imports/EmployeesImport.php` (`normalizeUnitKerja`, `UNIT_ALIASES`), `app/Services/Import/EmployeeImportRowValidator.php:180`, `app/Exports/Sheets/EmployeeTemplateReferenceSheet.php:49`
- Test: extend `tests/Feature/EmployeeImportTest.php`

**Interfaces:**
- Produces: `Unit::officialNames(): array` returning the names of active units, excluding `is_system` units if that column exists (it does not in this plan, so all active units).

- [ ] **Step 1: Write failing tests.** `test_import_accepts_a_unit_that_is_new_in_the_excel` (a row for `Kebun Kumai` with a jabatan seated there passes); `test_import_matches_unit_names_case_insensitively` (`KEBUN TAJATI` resolves to `Kebun Tajati`); `test_import_rejects_unit_names_that_were_retired` (Review Focus 5: `Satuan Pengawasan Internal (SPI)` and `Unit Group Kalimantan Barat` are rejected with the "tidak terdaftar" message); `test_retired_unit_alias_does_not_resolve` (an old PMO alias no longer maps to `Project Management Office (PMO)`).
- [ ] **Step 2: Run to verify failure.**
- [ ] **Step 3: Implement.** Replace each `Employee::OFFICIAL_UNITS` usage with `Unit::officialNames()`. Audit `UNIT_ALIASES`: remove every alias whose target is not in `Unit::officialNames()` and retarget the PMO aliases to the new PMO units' names where the mapping is unambiguous. Do not add aliases for Unit Group or SPI.
- [ ] **Step 4: Run to verify pass**: `php artisan test --filter=EmployeeImportTest`.
- [ ] **Step 5: Pint and commit** `refactor(import): validate units against the master in the database`.

---

### Task 8: Full-suite check and reset runbook

**Files:**
- Modify: test fixtures or dev seeders only where they reference jabatan or units that no longer exist (`database/seeders/EmployeeSeeder.php`, `RealisticMutationSeeder.php`, individual tests).

- [ ] **Step 1: Run the whole suite.** `php artisan test` → expected all PASS. Fix failures caused by renamed units or jabatan in fixtures; do not weaken assertions.
- [ ] **Step 2: Run Pint** `./vendor/bin/pint --test` → expected no changes needed.
- [ ] **Step 3: Reset runbook (needs the user's explicit go-ahead; do not run unprompted).**
  1. User takes a dump: `mysqldump hris_ptpn4 > backup-2026-10-31.sql` (command to be confirmed against the actual DB driver in `.env`).
  2. `php artisan migrate:fresh`
  3. `php artisan db:seed --class=UserSeeder` then `php artisan db:seed --class=PositionUnitSeeder` (not the full `DatabaseSeeder`, which also loads development employees with old names).
  4. User re-imports the employee Excel through the import screen.
- [ ] **Step 4: Verify after the reset.** `php artisan tinker --execute="echo App\Models\Unit::count().' '.DB::table('position_unit')->sum('kuota');"` → `45 444`; open the Man Power Planning page and confirm Standar Formasi shows 45 / 71 / 328 by RM.
- [ ] **Step 5: Final commit** only for fixture fixes: `test: align fixtures with the Excel master`.
