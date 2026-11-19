# Change report — Struktur Organisasi PTPN IV Reg V (1 September 2026)

Source: `Struktur_Organisasi_PTPN_IV_Reg_V_1 September 2026.xlsx`, sheet "Struktur Organisasi".
Compared against: `database/data/master_formasi.json` and `unit_metadata.json` (HEAD of `feat/sebaran-formasi`).
Status: **analysis only. No code, data or docs were changed.**

## 1. Headline numbers

| Metric | Current baseline | New workbook |
|---|---|---|
| Units | 45 | 47 |
| Unit-position pairs | 491 | 492 |
| Total quota (kuota) | 619 | 619 |
| RM-1 / RM-2 / RM-3 / RM-5 positions | 45 / 68 / 288 / 90 | 45 / 68 / 289 / 90 |
| Karpim / Karpel | 401 / 90 | 401 / 90 (derived from RM band) |

The new workbook is a reorganisation, not a growth. Total quota is unchanged.
**RM-5 Karpel is already in the baseline (90 positions, same count).** My earlier statement that the workbook would add about 90 Karpel seats was wrong. There are no RM-4, RM-6 or RM-7 values in the workbook.

## 2. Real structural changes

### 2.1 Unit renames (positions and quotas unchanged)
| Old name | New name |
|---|---|
| Group Manager Kalbar | Unit Group Kalimantan Barat |
| Group Manager Kalselteng | Unit Group Kalimantan Selatan/Tengah |
| Group Manager Kaltim | Unit Group Kalimantan Timur |
| Bagian Sumber Daya Manusia & Sistem Manajemen | Bagian Sumber Daya Manusia (also loses seats, see 2.2) |

The one renamed position is "Kepala Bagian Sumber Daya Manusia & Sistem Manajemen", which becomes "Kepala Bagian Sumber Daya Manusia".

### 2.2 Sistem Manajemen seats leave the HR bagian (10 quota)
| Position | RM/Bidang | Kuota | From → To |
|---|---|---|---|
| Kepala Sub Bagian Sistem Manajemen & Sustainability | RM-2/UMU | 1 | HR → Bagian Teknik & Pengolahan |
| Asisten Sistem Manajemen & Sertifikasi | RM-3/UMU | 2 | HR → Bagian Teknik & Pengolahan |
| Asisten ESG & Sustainability | RM-3/UMU | 1 | HR → Bagian Teknik & Pengolahan |
| Krani Sertifikasi (RSPO/ISPO/ISO) | RM-5/UMU | 2 | HR → Bagian Teknik & Pengolahan |
| Krani ESG (PROPER & Pengelolaan Lingkungan) | RM-5/UMU | 2 | HR → Bagian Teknik & Pengolahan |
| Krani SMK3 & Admi Manajemen Sertifikasi | RM-5/UMU | 1 | HR → Bagian Teknik & Pengolahan |
| Asisten Manajemen Risiko | RM-3/KEU | 1 | HR → Bagian Akuntansi & Keuangan |

Quota per unit: HR 33→23, Teknik & Pengolahan 25→34, Akuntansi & Keuangan 41→42.

### 2.3 Kemitraan seats leave Distrik Petani Mitra (17 quota)
Distrik Petani Mitra goes from 32 to 15 quota. Two new units absorb the difference, with no net change in quota:

- **Kebun Kemitraan Area Kalbar (8):** Manajer Kebun Kemitraan Area Kalbar (RM-1), Asisten Kepala Kemitraan Area Kalbar (RM-2), Asisten Tata Usaha (1 of the previous 2), and Asisten Kemitraan Parindu, Kembayan, Ngabang, Rimba Belian and Gunung Emas (RM-3).
- **Kebun Kemitraan Area Kaltim-Sel (9):** Manajer Kebun Kemitraan Area Kaltim-Sel (RM-1), Asisten Kepala Kemitraan Area Kaltim and Area Kalsel (RM-2), Asisten Tata Usaha (1 of 2), and Asisten Kemitraan Longkali, Tabara, Tajati, Pelaihari and Pamukan (RM-3).

"Asisten Tata Usaha" used to have quota 2 in Distrik Petani Mitra. It is now 1 in each new unit.

### 2.4 Bidang reclassifications (8 positions, RM unchanged)

Correction: the original report listed 7. The eighth, Asisten Manajemen Risiko (UMU → KEU), changed bidang as it moved to Akuntansi & Keuangan. I only compared bidang for same-unit pairs the first time.

| Position | Unit | Old → new bidang |
|---|---|---|
| Kepala Bidang Kemitraan & Kelembagaan | Distrik Petani Mitra | TAN → UMU |
| Kepala Bidang Administrasi Plasma/KKPA & Pembelian Bahan Baku | Distrik Petani Mitra | TAN → UMU |
| Asisten Bidang Administrasi Plasma/KKPA & Pembelian Bahan Baku | Distrik Petani Mitra | TAN → KEU |
| Kepala Bidang Operasional Pembelian Bahan Baku | Distrik Petani Mitra | TAN → KEU |
| Asisten Bidang Operasional Pembelian Bahan Baku | Distrik Petani Mitra | TAN → KEU |
| Asisten Penggajian | HR | UMU → KEU |
| Coordinator Bisnis Batu Bara | PMO Batu Bara & Bisnis Lainnya | UMU → TEK |
| Asisten Manajemen Risiko | Akuntansi & Keuangan (moved from HR) | UMU → KEU |

### 2.5 No other changes
Every other unit has the same positions, RM bands, bidang and quotas as the baseline, once name case is ignored (see 3.1).

## 3. Issues to resolve before importing

> **Resolved:** 3.1 is fixed in `FormasiNormalizer` with a test, and the case-only differences against the baseline are now 0. For 3.2, Asisten Pengadaan stays **RM-3**; the note is a workbook annotation, not a band change. For 3.3, the two Kebun Kemitraan units belong to **Regional Office**.

### 3.1 `FormasiNormalizer` would mangle 13 position names
The normalizer title-cases words it does not recognise, so these names would differ from the baseline spelling. The result would be false added/removed seats.

| Baseline | Normalizer output | Fix |
|---|---|---|
| Ketua Tim Audit VC / VD, Asisten Auditor VC / VD | Vc / Vd | Roman-numeral pattern only allows an A/B suffix; allow A–D |
| PBB, PPN, ICOFR, PUMK, BPJS, PSR | Pbb, Ppn, Icofr, Pumk, Bpjs, Psr | add to `ACRONYMS` |
| PPh | Pph | needs a mixed-case exception |
| RSPO/ISPO/ISO, SMK3 (in seats moving to Teknik) | Rspo/Ispo/Iso, Smk3 | add to `ACRONYMS` |

### 3.2 Workbook note contradicts the Strata value (row 99)
"Asisten Pengadaan" (Bagian Pengadaan & TI) is listed as **RM 3**, but its Keterangan says "Dikoreksi 3 → 4: tumpukan 4 kotak di bagan SK". RM-4 is a Karpel band and appears nowhere else in the file. Please confirm which is correct. The baseline has RM-3.

### 3.3 Unit metadata gaps
These units are missing from `unit_metadata.json` and `formasi:build-seed` fails until they are added:
- Bagian Sumber Daya Manusia
- Kebun Kemitraan Area Kalbar
- Kebun Kemitraan Area Kaltim-Sel
- Unit Group Kalimantan Barat
- Unit Group Kalimantan Selatan/Tengah
- Unit Group Kalimantan Timur

Open questions:
- Which `wilayah` do the two Kebun Kemitraan units belong to? In the sheet they sit between Distrik Petani Mitra and the PMO units, which suggests Regional Office.
- What `kode` should the Unit Group units use? Currently "Group" (the unit-type label, not the unit name).
- The `urutan` values need renumbering after the insertion.

### 3.4 Parser format change
`FormasiExcelParser` expects 4 columns (`Bagian/Unit Kerja, Nama Jabatan, Level RM, Bidang`), `RM-n` values and one row per seat. The new sheet has these differences:
- 8 columns: No, Unit/Bagian, Sub Bagian, Jabatan, Strata RM, Bidang, Kuota, Keterangan.
- `RM n` with a space.
- Explicit Kuota.
- A "Sub Bagian" column the baseline does not model.
- Notes in Keterangan.

Rows are unique per unit and jabatan, and there are no RM conflicts across units, so the existing validation rules still hold.

"Sheet2" in the workbook is in the old 4-column format.

## 4. Data impact
- The database holds 345 employees and 45 units. I did not check how many employees sit in the renamed or moved units.
- Renamed units need `unit_aliases` entries so older employee files still map.
- Seats that moved between units (2.2, 2.3) are new unit-position pairs. Employees holding those positions need to be reassigned or re-validated against the new units.
- Quota shifts change the Standar Formasi / MPP counts for RM-1 to RM-3 in the affected units: Teknik & Pengolahan, Akuntansi & Keuangan, HR, Distrik Petani Mitra and the two new Kebun Kemitraan units.

## 5. Implementation status

Done (baseline and tooling): steps 1–5 below. `formasi:build-seed` now reads the new workbook directly (47 units, 492 unit-position pairs, quota 619; the 6 blank-Kuota norm seats are stored as 0, matching the existing baseline). `DOMAIN.md` §3 and §3.6 and the unit count in `AGENTS.md` are updated.

**Step 6 is not needed:** the development database can be wiped and reimported, so no data migration is planned. Reset with `php artisan migrate:fresh --seed`, then reimport employees. The note below only applies if this baseline is ever applied to a database that must be kept.

The seeder only creates and updates. It does not rename units, move seats or reclassify bidang on an existing database (345 employees at last check). Applying this to a running database needs a data migration or Master Data steps, and the employees on moved seats need reassigning.

## 6. Original implementation plan
1. Fix `FormasiNormalizer` (3.1) and add a test.
2. Add an 8-column format to `FormasiExcelParser`, with `RM n` → `RM-n` and a `Kuota` column.
3. Update `unit_metadata.json` with the new units, aliases for the old names, and renumbered `urutan`.
4. Regenerate `master_formasi.json` with `formasi:build-seed`.
5. Run the existing formasi tests, then update `DOMAIN.md` §11 and the unit list.
6. Add a seat-move migration or sync step for existing employees.
