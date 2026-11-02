# Formasi Data Comparison: Excel vs JSON vs Database

_Generated 2026-10-30 from `Unit dan Jabatan Karyawan Pimpinan.xlsx`, `database/data/master_formasi.json` and the local database (`hris_ptpn4`). Read-only analysis; nothing was changed._

Names are matched ignoring case, punctuation, `&` vs `dan`, and the spellings `Akuntasi`/`Akuntansi` and `Manajer`/`Manager`. Employee data appears only as counts (no NIK or names).

## 1. Summary

| | Excel | JSON | Database |
|---|---:|---:|---:|
| Units | 45 | 43 | 43 |
| Distinct jabatan names | 164 | 154 | 166 (table `positions`) |
| Jabatan-unit rows | 392 | 342 | 342 (`position_unit`) |
| Total seats (kuota) | 444 | 371 | 371 |
| Seats at RM-1 | 45 | 32 | 33 |
| Seats at RM-2 | 71 | 47 | 47 |
| Seats at RM-3 | 328 | 259 | 259 |
| Seats at RM-4 | 0 | 33 | 32 |
| Seats in bidang KEU | 70 | 30 | 30 |
| Seats in bidang TAN | 211 | 52 | 46 |
| Seats in bidang TEK | 84 | 47 | 47 |
| Seats in bidang UMU | 79 | 241 | 246 |
| Employees assigned | - | - | 360 |

**JSON vs Database:** the database is seeded from the JSON. Seat totals match per unit (no unit differs). The one systematic difference is the name collision in section 2.1: the database has one row per jabatan name, so bidang/RM can differ from the JSON for shared names.

## 2. Structural findings

### 2.1 `positions` is keyed by name only, so shared jabatan lose their bidang

The seeder uses `firstOrCreate(['nama' => ...])` and `Position::getAllCached()` does `keyBy('nama')`. One name means one row and one bidang/RM. The Excel has the same jabatan name with different bidang across units:

| Jabatan | RM / bidang in Excel | Units |
|---|---|---:|
| KEPALA BAGIAN | RM-1 / KEU | 1 |
| KEPALA BAGIAN | RM-1 / TAN | 1 |
| KEPALA BAGIAN | RM-1 / TEK | 1 |
| KEPALA BAGIAN | RM-1 / UMU | 3 |
| MANAJER | RM-1 / TAN | 18 |
| MANAJER | RM-1 / TEK | 10 |
| MANAJER | RM-1 / UMU | 1 |

- Database `Manajer`: RM-1 / UMU (one row shared by every unit using that name)
- Database `Kepala Bagian`: RM-1 / UMU (one row shared by every unit using that name)
- Database `Asisten Afdeling I`: RM-3 / UMU (one row shared by every unit using that name)
- Database `Masinis Kepala`: RM-2 / UMU (one row shared by every unit using that name)

### 2.2 RM-4 on leadership seats

DOMAIN.md defines RM-4 as Karpel (operational staff). The JSON has 33 RM-4 seats and the database 32, all in Karpim formasi. The Excel has none (RM-1 to RM-3 only). Positions at RM-4 in the database: 26.

### 2.3 Bidang defaulting

Seats flagged `UMU`: Excel 79, JSON 241, database 246. Most of the extra JSON/database `UMU` seats in kebun, PKS and the Tanaman/Teknik/Keuangan bagian are placeholders that the Excel corrects (section 4).

### 2.4 Casing and spelling

Excel jabatan names in ALL CAPS: 391 of 392; every unit name is ALL CAPS. JSON and database use Capitalize (`Bagian Sekretariat dan Hukum`). Spelling issues in the Excel to normalize:

- `AKUNTASI`: 1 occurrence(s)
- `GROUP MANAJER`: 1 occurrence(s)
- `ADMINISTASI`: 1 occurrence(s)
- `REKRUITMEN`: 1 occurrence(s)
- `PERIJINAN`: 3 occurrence(s)
- `Asisten Tajati`: 1 occurrence(s) (Kebun Tajati / Asisten Tajati, the only mixed-case jabatan)

## 3. Unit-level comparison

| Excel unit | JSON unit | Database unit | Seats Excel / JSON / DB | Employees |
|---|---|---|---:|---:|
| BAGIAN SEKRETARIAT & HUKUM | Bagian Sekretariat dan Hukum | Bagian Sekretariat dan Hukum | 21 / 16 / 16 | 15 |
| BAGIAN SUMBER DAYA MANUSIA & SISTEM MANAJEMEN | Bagian Sumber Daya Manusia dan Sistem Manajemen | Bagian Sumber Daya Manusia dan Sistem Manajemen | 19 / 14 / 14 | 14 |
| BAGIAN AKUNTASI DAN KEUANGAN | Bagian Akuntansi dan Keuangan | Bagian Akuntansi dan Keuangan | 20 / 17 / 17 | 17 |
| BAGIAN PENGADAAN DAN TEKNOLOGI INFORMASI | Bagian Pengadaan dan Teknologi Informasi | Bagian Pengadaan dan Teknologi Informasi | 15 / 12 / 12 | 12 |
| BAGIAN TANAMAN | Bagian Tanaman | Bagian Tanaman | 17 / 17 / 17 | 16 |
| BAGIAN TEKNIK DAN PENGOLAHAN | Bagian Teknik dan Pengolahan | Bagian Teknik dan Pengolahan | 11 / 12 / 12 | 12 |
| DISTRIK PETANI MITRA | Distrik Petani Mitra | Distrik Petani Mitra | 10 / 15 / 15 | 8 |
| PMO INVESTASI TANAMAN WILAYAH KALBAR | - | - | 4 / - / - | - |
| PMO INVESTASI TANAMAN WILAYAH KALTIM & KALSEL-TENG | - | - | 5 / - / - | - |
| GROUP MANAGER KALBAR | - | - | 7 / - / - | - |
| GROUP MANAGER KALSELTENG | - | - | 7 / - / - | - |
| GROUP MANAJER KALTIM | - | - | 8 / - / - | - |
| KEBUN BATULICIN | Kebun Batulicin | Kebun Batulicin | 8 / 6 / 6 | 6 |
| KEBUN DANAU SALAK | Kebun Danau Salak | Kebun Danau Salak | 17 / 14 / 14 | 14 |
| KEBUN GUNUNG EMAS | Kebun Gunung Emas | Kebun Gunung Emas | 11 / 11 / 11 | 11 |
| KEBUN INTI GUNUNG MELIAU | Kebun Inti Gunung Meliau | Kebun Inti Gunung Meliau | 14 / 14 / 14 | 13 |
| KEBUN INTI PANDAWA | Kebun Inti Pandawa | Kebun Inti Pandawa | 9 / 9 / 9 | 9 |
| KEBUN INTI SUNGAI DEKAN | Kebun Inti Sungai Dekan | Kebun Inti Sungai Dekan | 13 / 12 / 12 | 12 |
| KEBUN KEMBAYAN | Kebun Kembayan | Kebun Kembayan | 11 / 11 / 11 | 11 |
| KEBUN KEMITRAAN AREA KALBAR | Kebun Kemitraan Area Kalbar | Kebun Kemitraan Area Kalbar | 8 / 7 / 7 | 7 |
| KEBUN KEMITRAAN AREA KALTIM-SEL | Kebun Kemitraan Area Kaltim-Sel | Kebun Kemitraan Area Kaltim-Sel | 9 / 7 / 7 | 7 |
| KEBUN KUMAI | - | - | 9 / - / - | - |
| KEBUN NGABANG | Kebun Ngabang | Kebun Ngabang | 10 / 9 / 9 | 9 |
| KEBUN PAMUKAN | Kebun Pamukan | Kebun Pamukan | 8 / 7 / 7 | 7 |
| KEBUN PARINDU | Kebun Parindu | Kebun Parindu | 11 / 9 / 9 | 9 |
| KEBUN PELAIHARI | Kebun Pelaihari | Kebun Pelaihari | 8 / 8 / 8 | 8 |
| KEBUN RAREN BATUAH | Kebun Raren Batuah | Kebun Raren Batuah | 8 / 2 / 2 | 2 |
| KEBUN RIMBA BELIAN | Kebun Rimba Belian | Kebun Rimba Belian | 11 / 11 / 11 | 11 |
| KEBUN SINTANG | - | - | 5 / - / - | - |
| KEBUN TABARA | Kebun Tabara | Kebun Tabara | 14 / 14 / 14 | 14 |
| KEBUN TAJATI | Kebun Tajati | Kebun Tajati | 13 / 10 / 10 | 10 |
| KEBUN-PKS LONGKALI | Kebun-PKS Longkali | Kebun-PKS Longkali | 13 / 0 / 0 | 11 |
| PKR TAMBARANGAN | - | - | 6 / - / - | - |
| PKS GUNUNG MELIAU | PKS Gunung Meliau | PKS Gunung Meliau | 7 / 6 / 6 | 6 |
| PKS KEMBAYAN | PKS Kembayan | PKS Kembayan | 7 / 7 / 7 | 7 |
| PKS LONGPINANG | PKS Longpinang | PKS Longpinang | 8 / 7 / 7 | 7 |
| PKS NGABANG | PKS Ngabang | PKS Ngabang | 7 / 7 / 7 | 7 |
| PKS PAMUKAN | PKS Pamukan | PKS Pamukan | 7 / 1 / 1 | 1 |
| PKS PARINDU | PKS Parindu | PKS Parindu | 8 / 17 / 17 | 6 |
| PKS PELAIHARI | PKS Pelaihari | PKS Pelaihari | 7 / 6 / 6 | 6 |
| PKS RIMBA BELIAN | PKS Rimba Belian | PKS Rimba Belian | 7 / 7 / 7 | 7 |
| PKS SAMUNTAI | PKS Samuntai | PKS Samuntai | 7 / 1 / 1 | 1 |
| PMO BATU BARA DAN BISNIS LAINNYA | - | - | 4 / - / - | - |
| PROYEK BATUBARA DANAU SALAK | - | - | 3 / - / - | - |
| TIM PENGUATAN ERP SAP | Tim Penguatan ERP SAP | Tim Penguatan ERP SAP | 12 / 7 / 7 | 6 |
| - | Divisi Sistem Manajemen & Sustainability | Divisi Sistem Manajemen & Sustainability | - / 1 / 1 | 1 |
| - | Project Management Office (PMO) | Project Management Office (PMO) | - / 5 / 5 | 5 |
| - | Satuan Pengawasan Internal (SPI) | Satuan Pengawasan Internal (SPI) | - / 9 / 9 | 9 |
| - | Unit Group Kalimantan Barat | Unit Group Kalimantan Barat | - / 9 / 9 | 9 |
| - | Kebun PKR Sintang | Kebun PKR Sintang | - / 2 / 2 | 2 |
| - | Unit Group Kalimantan Selatan/Tengah | Unit Group Kalimantan Selatan/Tengah | - / 6 / 6 | 6 |
| - | Proyek Batubara Danau Salak & Buntok | Proyek Batubara Danau Salak & Buntok | - / 3 / 3 | 3 |
| - | Unit Group Wilayah Kalimantan Timur | Unit Group Wilayah Kalimantan Timur | - / 6 / 6 | 6 |

- **Only in Excel (10):** PMO INVESTASI TANAMAN WILAYAH KALBAR; PMO INVESTASI TANAMAN WILAYAH KALTIM & KALSEL-TENG; GROUP MANAGER KALBAR; GROUP MANAGER KALSELTENG; GROUP MANAJER KALTIM; KEBUN KUMAI; KEBUN SINTANG; PKR TAMBARANGAN; PMO BATU BARA DAN BISNIS LAINNYA; PROYEK BATUBARA DANAU SALAK
- **Only in JSON/database (8):** Divisi Sistem Manajemen & Sustainability (1 employees); Project Management Office (PMO) (5 employees); Satuan Pengawasan Internal (SPI) (9 employees); Unit Group Kalimantan Barat (9 employees); Kebun PKR Sintang (2 employees); Unit Group Kalimantan Selatan/Tengah (6 employees); Proyek Batubara Danau Salak & Buntok (3 employees); Unit Group Wilayah Kalimantan Timur (6 employees)

## 4. Jabatan-level differences: Database vs Excel

Units present on both sides. Left column is the database (same as JSON, bar the collision above), right column is the Excel. Units with no difference are omitted. A rename appears as one "removed" row plus one "new" row.

### Bagian Sekretariat & Hukum: Excel 21 seats, database 16 seats

| Status | Jabatan | Database (RM/bidang, kuota) | Change | Excel (RM/bidang, kuota) |
|---|---|---|---|---|
| changed | ASISTEN KESEKRETARIATAN | RM-3/UMU x1 | kuota 1 to 2 | RM-3/UMU x2 |
| changed | ASISTEN HUMAS & PROTOKOLER | RM-3/UMU x3 | kuota 3 to 2 | RM-3/UMU x2 |
| changed | ASISTEN TJSL | RM-3/UMU x1 | kuota 1 to 2 | RM-3/UMU x2 |
| changed | ASISTEN PENDANAAN UMK | RM-3/UMU x1 | kuota 1 to 2 | RM-3/UMU x2 |
| removed in Excel | Kepala Pengamanan Perusahaan | RM-4/UMU x1 | | |
| new in Excel | KEPALA SUB BAGIAN HUKUM & PERIZINAN | | | RM-2/UMU x1 |
| new in Excel | KEPALA SUB BAGIAN PERTANAHAN & KEAMANAN | | | RM-2/UMU x1 |
| new in Excel | ASISTEN URTA & UMUM | | | RM-3/UMU x2 |

### Bagian Sumber Daya Manusia & Sistem Manajemen: Excel 19 seats, database 14 seats

| Status | Jabatan | Database (RM/bidang, kuota) | Change | Excel (RM/bidang, kuota) |
|---|---|---|---|---|
| removed in Excel | Kepala Sub Bagian Personalia & Manajemen Kinerja | RM-2/UMU x1 | | |
| removed in Excel | Asisten Manajemen Kinerja SDM | RM-3/UMU x1 | | |
| removed in Excel | Asisten Jaminan Sosial & Admi Pensiun | RM-3/UMU x1 | | |
| changed | ASISTEN PENGGAJIAN | RM-3/UMU x1 | bidang UMU to KEU | RM-3/KEU x1 |
| removed in Excel | Asisten Assessment & Rekrutment | RM-3/UMU x1 | | |
| changed | ASISTEN SISTEM MANAJEMEN DAN SERTIFIKASI | RM-3/UMU x1 | kuota 1 to 2 | RM-3/UMU x2 |
| removed in Excel | Asisten Manajemen Risiko | RM-3/UMU x1 | | |
| new in Excel | KEPALA SUB BAGIAN PERSONALIA DAN MANAJEMEN KINERJA SDM | | | RM-2/UMU x1 |
| new in Excel | MANAGEMENT REPRESENTATIVE LEAD | | | RM-2/UMU x1 |
| new in Excel | ASISTEN MANAJEMEN KINERJA | | | RM-3/UMU x1 |
| new in Excel | ASISTEN ASESMEN DAN REKRUITMEN | | | RM-3/UMU x1 |
| new in Excel | ASISTEN PSDM DAN BUDAYA | | | RM-3/UMU x2 |
| new in Excel | ASISTEN JAMINAN SOSIAL DAN ADM PENSIUN | | | RM-3/UMU x1 |
| new in Excel | ASISTEN MANAJEMEN RESIKO | | | RM-3/UMU x1 |
| new in Excel | ASISTEN MANAGEMENT REPRESENTATIVE | | | RM-3/UMU x1 |

### Bagian Akuntasi Dan Keuangan: Excel 20 seats, database 17 seats

| Status | Jabatan | Database (RM/bidang, kuota) | Change | Excel (RM/bidang, kuota) |
|---|---|---|---|---|
| changed | KEPALA BAGIAN | RM-1/UMU x1 | bidang UMU to KEU | RM-1/KEU x1 |
| changed | KEPALA SUB BAGIAN PAJAK & ASURANSI | RM-2/UMU x1 | bidang UMU to KEU | RM-2/KEU x1 |
| changed | KEPALA SUB BAGIAN ANGGARAN DAN VERIFIKASI | RM-2/UMU x1 | bidang UMU to KEU | RM-2/KEU x1 |
| changed | ASISTEN PAJAK | RM-3/UMU x1 | kuota 1 to 2, bidang UMU to KEU | RM-3/KEU x2 |
| changed | ASISTEN KAS DAN BANK | RM-3/UMU x2 | bidang UMU to KEU | RM-3/KEU x2 |
| changed | ASISTEN HPS & INFORMASI HARGA | RM-3/UMU x3 | bidang UMU to KEU | RM-3/KEU x3 |
| changed | ASISTEN ANGGARAN | RM-3/UMU x2 | bidang UMU to KEU | RM-3/KEU x2 |
| changed | ASISTEN VERIFIKASI DAN ASET | RM-3/UMU x2 | bidang UMU to KEU | RM-3/KEU x2 |
| changed | ASISTEN LAPORAN DAN ANALISA KEUANGAN | RM-3/KEU x1 | kuota 1 to 2 | RM-3/KEU x2 |
| new in Excel | ASISTEN ASURANSI | | | RM-3/KEU x1 |

### Bagian Pengadaan Dan Teknologi Informasi: Excel 15 seats, database 12 seats

| Status | Jabatan | Database (RM/bidang, kuota) | Change | Excel (RM/bidang, kuota) |
|---|---|---|---|---|
| changed | KETUA TIM PENGADAAN | RM-4/UMU x1 | kuota 1 to 2, RM RM-4 to RM-2 | RM-2/UMU x2 |
| changed | ASISTEN PENGADAAN | RM-3/UMU x2 | kuota 2 to 4 | RM-3/UMU x4 |

### Bagian Tanaman: Excel 17 seats, database 17 seats

| Status | Jabatan | Database (RM/bidang, kuota) | Change | Excel (RM/bidang, kuota) |
|---|---|---|---|---|
| changed | KEPALA BAGIAN | RM-1/UMU x1 | bidang UMU to TAN | RM-1/TAN x1 |
| removed in Excel | Asisten Perencanaan Produksi Tanaman Sawit | RM-3/TAN x2 | | |
| removed in Excel | Asisten Investasi Tanaman | RM-3/TAN x1 | | |
| changed | ASISTEN PEMETAAN | RM-3/UMU x1 | bidang UMU to TAN | RM-3/TAN x1 |
| removed in Excel | Head of PMO Investasi Tanamanan Wilayah Kaltim & Kalsel-Teng | RM-1/TAN x1 | | |
| new in Excel | ASISTEN PERENCANAAN PRODUKSI KELAPA SAWIT | | | RM-3/TAN x2 |
| new in Excel | ASISTEN PRODUKSI TANAMAN KARET | | | RM-3/TAN x1 |
| new in Excel | ASISTEN INVESTASI TANAMAN KARET | | | RM-3/TAN x1 |

### Bagian Teknik Dan Pengolahan: Excel 11 seats, database 12 seats

| Status | Jabatan | Database (RM/bidang, kuota) | Change | Excel (RM/bidang, kuota) |
|---|---|---|---|---|
| changed | KEPALA BAGIAN | RM-1/UMU x1 | bidang UMU to TEK | RM-1/TEK x1 |
| removed in Excel | Manajer Diperbantukan | RM-1/UMU x1 | | |
| removed in Excel | Kepala Sub Bagian Teknik dan Infrastruktur | RM-2/TEK x1 | | |
| changed | ASISTEN MESIN & INSTALASI PABRIK | RM-3/UMU x2 | kuota 2 to 1, bidang UMU to TEK | RM-3/TEK x1 |
| changed | ASISTEN SIPIL & INFRASTRUKTUR PABRIK | RM-3/UMU x1 | bidang UMU to TEK | RM-3/TEK x1 |
| changed | ASISTEN SIPIL & INFRASTRUKTUR KEBUN | RM-3/UMU x1 | bidang UMU to TEK | RM-3/TEK x1 |
| changed | ASISTEN TRAKSI & ALAT BERAT KEBUN | RM-3/UMU x1 | bidang UMU to TEK | RM-3/TEK x1 |
| new in Excel | KEPALA SUB BAGIAN INVESTASI & EKSPLOITASI PABRIK | | | RM-2/TEK x1 |
| new in Excel | KEPALA SUB BAGIAN TEKNIK & INFRASTRUKTUR KEBUN | | | RM-2/TEK x1 |

### Distrik Petani Mitra: Excel 10 seats, database 15 seats

| Status | Jabatan | Database (RM/bidang, kuota) | Change | Excel (RM/bidang, kuota) |
|---|---|---|---|---|
| changed | GROUP MANAGER | RM-4/UMU x1 | RM RM-4 to RM-1, bidang UMU to TAN | RM-1/TAN x1 |
| changed | KEPALA BIDANG TANAMAN PLASMA/KKPA | RM-4/TAN x1 | RM RM-4 to RM-2 | RM-2/TAN x1 |
| changed | KEPALA BIDANG OPERASIONAL PEMBELIAN BAHAN BAKU | RM-4/UMU x1 | RM RM-4 to RM-2, bidang UMU to KEU | RM-2/KEU x1 |
| changed | KEPALA BIDANG KEMITRAAN DAN KELEMBAGAAN | RM-4/UMU x1 | RM RM-4 to RM-2 | RM-2/UMU x1 |
| changed | ASISTEN BIDANG KEMITRAAN DAN KELEMBAGAAN | RM-3/UMU x1 | bidang UMU to TAN | RM-3/TAN x1 |
| changed | ASISTEN BIDANG ADMINISTRASI PLASMA/KKPA DAN PEMBELIAN BAHAN BAKU | RM-3/UMU x1 | bidang UMU to KEU | RM-3/KEU x1 |
| removed in Excel | Asisten Bidang Operasional & Pembelian Bahan Baku | RM-3/UMU x1 | | |
| removed in Excel | Asisten Bidang Operasional Pembelian Bahan Baku | RM-3/UMU x1 | | |
| removed in Excel | Manajer | RM-1/UMU x1 | | |
| removed in Excel | Asisten Tata Usaha & Keuangan | RM-3/KEU x1 | | |
| removed in Excel | Asisten Kemitraan Parindu | RM-3/UMU x1 | | |
| removed in Excel | Asisten Kemitraan Rimba Belian | RM-3/UMU x1 | | |
| removed in Excel | Asisten Kemitraan Gunung Emas | RM-3/UMU x1 | | |
| removed in Excel | Asisten Kemitraan Ngabang | RM-3/UMU x1 | | |
| removed in Excel | Asisten Kemitraan Kembayan | RM-3/UMU x1 | | |
| new in Excel | KEPALA BIDANG ADMINISTRASI PLASMA/KKPA DAN PEMBELIAN BAHAN BAKU | | | RM-2/UMU x1 |
| new in Excel | ASISTEN BIDANG TANAMAN PLASMA/KKPA DAN PEREMAJAAN SAWIT RAKYAT | | | RM-3/TAN x2 |
| new in Excel | ASISTEN OPERASIONAL PEMBELIAN BAHAN BAKU | | | RM-3/KEU x1 |

### Kebun Batulicin: Excel 8 seats, database 6 seats

| Status | Jabatan | Database (RM/bidang, kuota) | Change | Excel (RM/bidang, kuota) |
|---|---|---|---|---|
| changed | MANAJER | RM-1/UMU x1 | bidang UMU to TAN | RM-1/TAN x1 |
| changed | ASISTEN PERSONALIA DAN TEKNIK KEBUN | RM-3/TEK x1 | bidang TEK to UMU | RM-3/UMU x1 |
| changed | ASISTEN AFDELING I | RM-3/UMU x1 | bidang UMU to TAN | RM-3/TAN x1 |
| removed in Excel | Asisten Inti II | RM-3/UMU x1 | | |
| changed | ASISTEN AFDELING IV | RM-3/UMU x1 | bidang UMU to TAN | RM-3/TAN x1 |
| new in Excel | ASISTEN KEPALA TANAMAN | | | RM-2/TAN x1 |
| new in Excel | ASISTEN AFDELING III | | | RM-3/TAN x1 |
| new in Excel | ASISTEN AFDELING II | | | RM-3/TAN x1 |

### Kebun Danau Salak: Excel 17 seats, database 14 seats

| Status | Jabatan | Database (RM/bidang, kuota) | Change | Excel (RM/bidang, kuota) |
|---|---|---|---|---|
| changed | MANAJER | RM-1/UMU x1 | bidang UMU to TAN | RM-1/TAN x1 |
| changed | ASISTEN KEPALA TANAMAN | RM-2/TAN x1 | kuota 1 to 2 | RM-2/TAN x2 |
| removed in Excel | Asisten Kepala | RM-2/UMU x1 | | |
| changed | ASISTEN PERSONALIA DAN TEKNIK KEBUN | RM-3/TEK x1 | bidang TEK to UMU | RM-3/UMU x1 |
| changed | ASISTEN AFDELING V | RM-3/UMU x1 | bidang UMU to TAN | RM-3/TAN x1 |
| changed | ASISTEN AFDELING VII | RM-3/UMU x1 | bidang UMU to TAN | RM-3/TAN x1 |
| changed | ASISTEN AFDELING VIII | RM-3/UMU x1 | bidang UMU to TAN | RM-3/TAN x1 |
| removed in Excel | Asisten Inti XII | RM-3/TAN x1 | | |
| new in Excel | ASISTEN AFDELING IV | | | RM-3/TAN x1 |
| new in Excel | ASISTEN AFDELING III | | | RM-3/TAN x1 |
| new in Excel | ASISTEN AFDELING XII | | | RM-3/TAN x1 |
| new in Excel | ASISTEN SIPIL, TRAKSI DAN ALAT BERAT | | | RM-3/TEK x1 |

### Kebun Gunung Emas: Excel 11 seats, database 11 seats

| Status | Jabatan | Database (RM/bidang, kuota) | Change | Excel (RM/bidang, kuota) |
|---|---|---|---|---|
| changed | MANAJER | RM-1/UMU x1 | bidang UMU to TAN | RM-1/TAN x1 |
| changed | ASISTEN PERSONALIA DAN TEKNIK KEBUN | RM-3/TEK x1 | bidang TEK to UMU | RM-3/UMU x1 |
| changed | ASISTEN AFDELING I | RM-3/UMU x1 | bidang UMU to TAN | RM-3/TAN x1 |
| changed | ASISTEN AFDELING II | RM-3/UMU x1 | bidang UMU to TAN | RM-3/TAN x1 |
| changed | ASISTEN AFDELING III | RM-3/UMU x1 | bidang UMU to TAN | RM-3/TAN x1 |
| changed | ASISTEN AFDELING IV | RM-3/UMU x1 | bidang UMU to TAN | RM-3/TAN x1 |
| changed | ASISTEN AFDELING V | RM-3/UMU x1 | bidang UMU to TAN | RM-3/TAN x1 |
| changed | ASISTEN AFDELING VI | RM-3/UMU x1 | bidang UMU to TAN | RM-3/TAN x1 |
| changed | ASISTEN AFDELING VII DAN VIII | RM-3/UMU x1 | bidang UMU to TAN | RM-3/TAN x1 |

### Kebun Inti Gunung Meliau: Excel 14 seats, database 14 seats

| Status | Jabatan | Database (RM/bidang, kuota) | Change | Excel (RM/bidang, kuota) |
|---|---|---|---|---|
| changed | MANAJER | RM-1/UMU x1 | bidang UMU to TAN | RM-1/TAN x1 |
| removed in Excel | Asisten Kepala Tanaman Inti Rayon Selatan | RM-2/TAN x1 | | |
| removed in Excel | Asisten Kepala Tanaman Rayon Utara | RM-2/TAN x1 | | |
| changed | ASISTEN PERSONALIA DAN TEKNIK KEBUN | RM-3/TEK x1 | bidang TEK to UMU | RM-3/UMU x1 |
| changed | ASISTEN AFDELING I | RM-3/UMU x1 | bidang UMU to TAN | RM-3/TAN x1 |
| changed | ASISTEN AFDELING II | RM-3/UMU x1 | bidang UMU to TAN | RM-3/TAN x1 |
| changed | ASISTEN AFDELING III | RM-3/UMU x1 | bidang UMU to TAN | RM-3/TAN x1 |
| changed | ASISTEN AFDELING IV | RM-3/UMU x1 | bidang UMU to TAN | RM-3/TAN x1 |
| changed | ASISTEN AFDELING V | RM-3/UMU x1 | bidang UMU to TAN | RM-3/TAN x1 |
| changed | ASISTEN AFDELING VI | RM-3/UMU x1 | bidang UMU to TAN | RM-3/TAN x1 |
| changed | ASISTEN AFDELING VII | RM-3/UMU x1 | bidang UMU to TAN | RM-3/TAN x1 |
| changed | ASISTEN AFDELING VIII | RM-3/UMU x1 | bidang UMU to TAN | RM-3/TAN x1 |
| removed in Excel | Asisten Afdeling | RM-3/None x1 | | |
| new in Excel | ASISTEN KEPALA TANAMAN | | | RM-2/TAN x1 |
| new in Excel | ASISTEN KEPALA TANAMAN INTI | | | RM-2/TAN x1 |
| new in Excel | ASISTEN SIPIL, TRAKSI DAN ALAT BERAT | | | RM-3/TEK x1 |

### Kebun Inti Pandawa: Excel 9 seats, database 9 seats

| Status | Jabatan | Database (RM/bidang, kuota) | Change | Excel (RM/bidang, kuota) |
|---|---|---|---|---|
| changed | MANAJER | RM-1/UMU x1 | bidang UMU to TAN | RM-1/TAN x1 |
| changed | ASISTEN PERSONALIA DAN TEKNIK KEBUN | RM-3/TEK x1 | bidang TEK to UMU | RM-3/UMU x1 |
| changed | ASISTEN AFDELING I | RM-3/UMU x1 | bidang UMU to TAN | RM-3/TAN x1 |
| changed | ASISTEN AFDELING II | RM-3/UMU x1 | bidang UMU to TAN | RM-3/TAN x1 |
| changed | ASISTEN AFDELING III | RM-3/UMU x1 | bidang UMU to TAN | RM-3/TAN x1 |
| changed | ASISTEN AFDELING IV | RM-3/UMU x1 | bidang UMU to TAN | RM-3/TAN x1 |
| changed | ASISTEN AFDELING V | RM-3/UMU x1 | bidang UMU to TAN | RM-3/TAN x1 |

### Kebun Inti Sungai Dekan: Excel 13 seats, database 12 seats

| Status | Jabatan | Database (RM/bidang, kuota) | Change | Excel (RM/bidang, kuota) |
|---|---|---|---|---|
| changed | MANAJER | RM-1/UMU x1 | bidang UMU to TAN | RM-1/TAN x1 |
| removed in Excel | Asisten Kepala Tanaman Rayon Timur | RM-2/TAN x1 | | |
| removed in Excel | Asisten Kepala Tanaman Rayon Barat | RM-2/TAN x1 | | |
| removed in Excel | Asisten Tata Usaha | RM-3/UMU x1 | | |
| changed | ASISTEN PERSONALIA DAN TEKNIK KEBUN | RM-3/TEK x1 | bidang TEK to UMU | RM-3/UMU x1 |
| changed | ASISTEN AFDELING I | RM-3/UMU x1 | bidang UMU to TAN | RM-3/TAN x1 |
| changed | ASISTEN AFDELING II | RM-3/UMU x1 | bidang UMU to TAN | RM-3/TAN x1 |
| changed | ASISTEN AFDELING III | RM-3/UMU x1 | bidang UMU to TAN | RM-3/TAN x1 |
| changed | ASISTEN AFDELING IV | RM-3/UMU x1 | bidang UMU to TAN | RM-3/TAN x1 |
| changed | ASISTEN AFDELING V | RM-3/UMU x1 | bidang UMU to TAN | RM-3/TAN x1 |
| changed | ASISTEN AFDELING VI | RM-3/UMU x1 | bidang UMU to TAN | RM-3/TAN x1 |
| changed | ASISTEN AFDELING VII | RM-3/UMU x1 | bidang UMU to TAN | RM-3/TAN x1 |
| new in Excel | ASISTEN KEPALA TANAMAN | | | RM-2/TAN x2 |
| new in Excel | ASISTEN TATA USAHA DAN KEUANGAN | | | RM-3/KEU x1 |
| new in Excel | ASISTEN SIPIL, TRAKSI DAN ALAT BERAT | | | RM-3/TEK x1 |

### Kebun Kembayan: Excel 11 seats, database 11 seats

| Status | Jabatan | Database (RM/bidang, kuota) | Change | Excel (RM/bidang, kuota) |
|---|---|---|---|---|
| changed | MANAJER | RM-1/UMU x1 | bidang UMU to TAN | RM-1/TAN x1 |
| removed in Excel | Asisten Tata Usaha Keuangan | RM-3/KEU x1 | | |
| changed | ASISTEN PERSONALIA DAN TEKNIK KEBUN | RM-3/TEK x1 | bidang TEK to UMU | RM-3/UMU x1 |
| removed in Excel | Asisten Inti I | RM-3/UMU x1 | | |
| changed | ASISTEN AFDELING II | RM-3/UMU x1 | bidang UMU to TAN | RM-3/TAN x1 |
| changed | ASISTEN AFDELING III | RM-3/UMU x1 | bidang UMU to TAN | RM-3/TAN x1 |
| removed in Excel | Asisten Inti IV | RM-3/UMU x1 | | |
| changed | ASISTEN AFDELING V | RM-3/UMU x1 | bidang UMU to TAN | RM-3/TAN x1 |
| changed | ASISTEN AFDELING VI | RM-3/UMU x1 | bidang UMU to TAN | RM-3/TAN x1 |
| changed | ASISTEN AFDELING VII | RM-3/UMU x1 | bidang UMU to TAN | RM-3/TAN x1 |
| new in Excel | ASISTEN AFDELING IV | | | RM-3/TAN x1 |
| new in Excel | ASISTEN TATA USAHA DAN KEUANGAN | | | RM-3/KEU x1 |
| new in Excel | ASISTEN AFDELING I | | | RM-3/TAN x1 |

### Kebun Kemitraan Area Kalbar: Excel 8 seats, database 7 seats

| Status | Jabatan | Database (RM/bidang, kuota) | Change | Excel (RM/bidang, kuota) |
|---|---|---|---|---|
| removed in Excel | Manajer | RM-1/UMU x1 | | |
| removed in Excel | Asisten Tata Usaha & Keuangan | RM-3/KEU x1 | | |
| changed | ASISTEN KEMITRAAN PARINDU | RM-3/UMU x1 | bidang UMU to TAN | RM-3/TAN x1 |
| changed | ASISTEN KEMITRAAN RIMBA BELIAN | RM-3/UMU x1 | bidang UMU to TAN | RM-3/TAN x1 |
| changed | ASISTEN KEMITRAAN GUNUNG EMAS | RM-3/UMU x1 | bidang UMU to TAN | RM-3/TAN x1 |
| changed | ASISTEN KEMITRAAN NGABANG | RM-3/UMU x1 | bidang UMU to TAN | RM-3/TAN x1 |
| changed | ASISTEN KEMITRAAN KEMBAYAN | RM-3/UMU x1 | bidang UMU to TAN | RM-3/TAN x1 |
| new in Excel | MANAJER KEBUN KEMITRAAN AREA KALBAR | | | RM-1/TAN x1 |
| new in Excel | ASISTEN KEPALA KEMITRAAN AREA KALBAR | | | RM-2/TAN x1 |
| new in Excel | ASISTEN TATA USAHA | | | RM-3/KEU x1 |

### Kebun Kemitraan Area Kaltim-Sel: Excel 9 seats, database 7 seats

| Status | Jabatan | Database (RM/bidang, kuota) | Change | Excel (RM/bidang, kuota) |
|---|---|---|---|---|
| removed in Excel | Manajer | RM-1/UMU x1 | | |
| changed | ASISTEN KEPALA KEMITRAAN AREA KALSEL | RM-2/UMU x1 | bidang UMU to TAN | RM-2/TAN x1 |
| changed | ASISTEN KEMITRAAN LONGKALI | RM-3/UMU x1 | bidang UMU to TAN | RM-3/TAN x1 |
| changed | ASISTEN KEMITRAAN TABARA | RM-3/UMU x1 | bidang UMU to TAN | RM-3/TAN x1 |
| changed | ASISTEN KEMITRAAN TAJATI | RM-3/UMU x1 | bidang UMU to TAN | RM-3/TAN x1 |
| changed | ASISTEN KEMITRAAN PELAIHARI | RM-3/UMU x1 | bidang UMU to TAN | RM-3/TAN x1 |
| changed | ASISTEN KEMITRAAN PAMUKAN | RM-3/UMU x1 | bidang UMU to TAN | RM-3/TAN x1 |
| new in Excel | MANAJER KEBUN KEMITRAAN AREA KALTIM-SEL | | | RM-1/TAN x1 |
| new in Excel | ASISTEN KEPALA KEMITRAAN AREA KALTIM | | | RM-2/TAN x1 |
| new in Excel | ASISTEN TATA USAHA | | | RM-3/KEU x1 |

### Kebun Ngabang: Excel 10 seats, database 9 seats

| Status | Jabatan | Database (RM/bidang, kuota) | Change | Excel (RM/bidang, kuota) |
|---|---|---|---|---|
| changed | MANAJER | RM-1/UMU x1 | bidang UMU to TAN | RM-1/TAN x1 |
| changed | ASISTEN PERSONALIA DAN TEKNIK KEBUN | RM-3/TEK x1 | bidang TEK to UMU | RM-3/UMU x1 |
| changed | ASISTEN AFDELING I | RM-3/UMU x1 | bidang UMU to TAN | RM-3/TAN x1 |
| changed | ASISTEN AFDELING II | RM-3/UMU x1 | bidang UMU to TAN | RM-3/TAN x1 |
| changed | ASISTEN AFDELING III | RM-3/UMU x1 | bidang UMU to TAN | RM-3/TAN x1 |
| changed | ASISTEN AFDELING IV | RM-3/UMU x1 | bidang UMU to TAN | RM-3/TAN x1 |
| changed | ASISTEN AFDELING V | RM-3/UMU x1 | bidang UMU to TAN | RM-3/TAN x1 |
| new in Excel | ASISTEN SIPIL, TRAKSI DAN ALAT BERAT | | | RM-3/TEK x1 |

### Kebun Pamukan: Excel 8 seats, database 7 seats

| Status | Jabatan | Database (RM/bidang, kuota) | Change | Excel (RM/bidang, kuota) |
|---|---|---|---|---|
| changed | MANAJER | RM-1/UMU x1 | bidang UMU to TAN | RM-1/TAN x1 |
| removed in Excel | Asisten Kepala | RM-2/UMU x1 | | |
| removed in Excel | Asisten Personalia & Teknik | RM-3/TEK x1 | | |
| changed | ASISTEN AFDELING I | RM-3/UMU x1 | bidang UMU to TAN | RM-3/TAN x1 |
| changed | ASISTEN AFDELING II | RM-3/UMU x1 | bidang UMU to TAN | RM-3/TAN x1 |
| changed | ASISTEN AFDELING III | RM-3/UMU x1 | bidang UMU to TAN | RM-3/TAN x1 |
| new in Excel | ASISTEN KEPALA TANAMAN | | | RM-2/TAN x1 |
| new in Excel | ASISTEN AFDELING IIA | | | RM-3/TAN x1 |
| new in Excel | ASISTEN PERSONALIA DAN TEKNIK KEBUN | | | RM-3/UMU x1 |

### Kebun Parindu: Excel 11 seats, database 9 seats

| Status | Jabatan | Database (RM/bidang, kuota) | Change | Excel (RM/bidang, kuota) |
|---|---|---|---|---|
| changed | MANAJER | RM-1/UMU x1 | bidang UMU to TAN | RM-1/TAN x1 |
| changed | ASISTEN PERSONALIA DAN TEKNIK KEBUN | RM-3/TEK x1 | bidang TEK to UMU | RM-3/UMU x1 |
| changed | ASISTEN AFDELING I | RM-3/UMU x1 | bidang UMU to TAN | RM-3/TAN x1 |
| changed | ASISTEN AFDELING II | RM-3/UMU x1 | bidang UMU to TAN | RM-3/TAN x1 |
| changed | ASISTEN AFDELING III | RM-3/UMU x1 | bidang UMU to TAN | RM-3/TAN x1 |
| changed | ASISTEN AFDELING IV | RM-3/UMU x1 | bidang UMU to TAN | RM-3/TAN x1 |
| changed | ASISTEN AFDELING V | RM-3/UMU x1 | bidang UMU to TAN | RM-3/TAN x1 |
| new in Excel | ASISTEN AFDELING IIA | | | RM-3/TAN x1 |
| new in Excel | ASISTEN SIPIL, TRAKSI DAN ALAT BERAT | | | RM-3/TEK x1 |

### Kebun Pelaihari: Excel 8 seats, database 8 seats

| Status | Jabatan | Database (RM/bidang, kuota) | Change | Excel (RM/bidang, kuota) |
|---|---|---|---|---|
| changed | MANAJER | RM-1/UMU x1 | bidang UMU to TAN | RM-1/TAN x1 |
| changed | ASISTEN PERSONALIA DAN TEKNIK KEBUN | RM-3/TEK x1 | bidang TEK to UMU | RM-3/UMU x1 |
| changed | ASISTEN AFDELING I | RM-3/UMU x1 | bidang UMU to TAN | RM-3/TAN x1 |
| changed | ASISTEN AFDELING II | RM-3/UMU x1 | bidang UMU to TAN | RM-3/TAN x1 |
| changed | ASISTEN AFDELING III | RM-3/UMU x1 | bidang UMU to TAN | RM-3/TAN x1 |
| changed | ASISTEN AFDELING IV | RM-3/UMU x1 | bidang UMU to TAN | RM-3/TAN x1 |

### Kebun Raren Batuah: Excel 8 seats, database 2 seats

| Status | Jabatan | Database (RM/bidang, kuota) | Change | Excel (RM/bidang, kuota) |
|---|---|---|---|---|
| changed | MANAJER | RM-1/UMU x1 | bidang UMU to TAN | RM-1/TAN x1 |
| new in Excel | ASISTEN KEPALA TANAMAN | | | RM-2/TAN x1 |
| new in Excel | ASISTEN AFDELING | | | RM-3/TAN x4 |
| new in Excel | ASISTEN PERSONALIA DAN TEKNIK KEBUN | | | RM-3/UMU x1 |

### Kebun Rimba Belian: Excel 11 seats, database 11 seats

| Status | Jabatan | Database (RM/bidang, kuota) | Change | Excel (RM/bidang, kuota) |
|---|---|---|---|---|
| changed | MANAJER | RM-1/UMU x1 | bidang UMU to TAN | RM-1/TAN x1 |
| removed in Excel | Asisten Kepala Tanaman Rayon Barat | RM-2/TAN x1 | | |
| removed in Excel | Asisten Kepala Tanaman Rayon Timur | RM-2/TAN x1 | | |
| changed | ASISTEN PERSONALIA DAN TEKNIK KEBUN | RM-3/TEK x1 | bidang TEK to UMU | RM-3/UMU x1 |
| changed | ASISTEN AFDELING I | RM-3/UMU x1 | bidang UMU to TAN | RM-3/TAN x1 |
| changed | ASISTEN AFDELING II | RM-3/UMU x1 | bidang UMU to TAN | RM-3/TAN x1 |
| changed | ASISTEN AFDELING III | RM-3/UMU x1 | bidang UMU to TAN | RM-3/TAN x1 |
| changed | ASISTEN AFDELING IV | RM-3/UMU x1 | bidang UMU to TAN | RM-3/TAN x1 |
| changed | ASISTEN AFDELING V | RM-3/UMU x1 | bidang UMU to TAN | RM-3/TAN x1 |
| changed | ASISTEN AFDELING VI | RM-3/UMU x1 | bidang UMU to TAN | RM-3/TAN x1 |
| new in Excel | ASISTEN KEPALA TANAMAN | | | RM-2/TAN x2 |

### Kebun Tabara: Excel 14 seats, database 14 seats

| Status | Jabatan | Database (RM/bidang, kuota) | Change | Excel (RM/bidang, kuota) |
|---|---|---|---|---|
| changed | MANAJER | RM-1/UMU x1 | bidang UMU to TAN | RM-1/TAN x1 |
| removed in Excel | Asisten Kepala Tanaman (Rayon Utara) | RM-2/TAN x1 | | |
| removed in Excel | Asisten Kepala Tanaman (Rayon Selatan) | RM-2/TAN x1 | | |
| changed | ASISTEN PERSONALIA DAN TEKNIK KEBUN | RM-3/TEK x1 | bidang TEK to UMU | RM-3/UMU x1 |
| changed | ASISTEN AFDELING I | RM-3/UMU x1 | bidang UMU to TAN | RM-3/TAN x1 |
| changed | ASISTEN AFDELING II | RM-3/UMU x1 | bidang UMU to TAN | RM-3/TAN x1 |
| changed | ASISTEN AFDELING III | RM-3/UMU x1 | bidang UMU to TAN | RM-3/TAN x1 |
| changed | ASISTEN AFDELING IV | RM-3/UMU x1 | bidang UMU to TAN | RM-3/TAN x1 |
| changed | ASISTEN AFDELING VI | RM-3/UMU x1 | bidang UMU to TAN | RM-3/TAN x1 |
| changed | ASISTEN AFDELING VII | RM-3/UMU x1 | bidang UMU to TAN | RM-3/TAN x1 |
| changed | ASISTEN AFDELING VIII | RM-3/UMU x1 | bidang UMU to TAN | RM-3/TAN x1 |
| changed | ASISTEN AFDELING V | RM-3/UMU x1 | bidang UMU to TAN | RM-3/TAN x1 |
| new in Excel | ASISTEN KEPALA TANAMAN | | | RM-2/TAN x2 |

### Kebun Tajati: Excel 13 seats, database 10 seats

| Status | Jabatan | Database (RM/bidang, kuota) | Change | Excel (RM/bidang, kuota) |
|---|---|---|---|---|
| changed | MANAJER | RM-1/UMU x1 | bidang UMU to TAN | RM-1/TAN x1 |
| removed in Excel | Asisten Kepala (Rayon A) | RM-2/UMU x1 | | |
| removed in Excel | Penugasan sebagai Asisten Kepala (Rayon B) | RM-2/UMU x1 | | |
| changed | ASISTEN PERSONALIA DAN TEKNIK KEBUN | RM-3/TEK x1 | bidang TEK to UMU | RM-3/UMU x1 |
| changed | ASISTEN AFDELING I | RM-3/UMU x1 | bidang UMU to TAN | RM-3/TAN x1 |
| changed | ASISTEN AFDELING II | RM-3/UMU x1 | bidang UMU to TAN | RM-3/TAN x1 |
| removed in Excel | Asisten Afdeling Inti III | RM-3/UMU x1 | | |
| changed | ASISTEN AFDELING IV | RM-3/UMU x1 | bidang UMU to TAN | RM-3/TAN x1 |
| changed | ASISTEN AFDELING V | RM-3/UMU x1 | bidang UMU to TAN | RM-3/TAN x1 |
| new in Excel | ASISTEN KEPALA TANAMAN | | | RM-2/TAN x2 |
| new in Excel | Asisten Tajati | | | RM-3/TAN x1 |
| new in Excel | ASISTEN AFDELING VI DAN VII | | | RM-3/TAN x1 |
| new in Excel | ASISTEN AFDELING III | | | RM-3/TAN x1 |
| new in Excel | ASISTEN SIPIL, TRAKSI DAN ALAT BERAT | | | RM-3/TEK x1 |

### Kebun-Pks Longkali: Excel 13 seats, database 0 seats

| Status | Jabatan | Database (RM/bidang, kuota) | Change | Excel (RM/bidang, kuota) |
|---|---|---|---|---|
| new in Excel | MANAJER | | | RM-1/TAN x1 |
| new in Excel | ASISTEN KEPALA TANAMAN | | | RM-2/TAN x2 |
| new in Excel | ASISTEN AFDELING I | | | RM-3/TAN x1 |
| new in Excel | ASISTEN AFDELING II | | | RM-3/TAN x1 |
| new in Excel | ASISTEN AFDELING III | | | RM-3/TAN x1 |
| new in Excel | ASISTEN AFDELING TANJUNG SANTAN | | | RM-3/TAN x1 |
| new in Excel | ASISTEN AFDELING IV | | | RM-3/TAN x1 |
| new in Excel | ASISTEN AFDELING V | | | RM-3/TAN x1 |
| new in Excel | ASISTEN AFDELING VI | | | RM-3/TAN x1 |
| new in Excel | ASISTEN TATA USAHA DAN KEUANGAN | | | RM-3/KEU x1 |
| new in Excel | ASISTEN SIPIL, TRAKSI DAN ALAT BERAT | | | RM-3/TEK x1 |
| new in Excel | ASISTEN PERSONALIA DAN TEKNIK KEBUN | | | RM-3/UMU x1 |

### Pks Gunung Meliau: Excel 7 seats, database 6 seats

| Status | Jabatan | Database (RM/bidang, kuota) | Change | Excel (RM/bidang, kuota) |
|---|---|---|---|---|
| changed | MASINIS KEPALA | RM-2/UMU x1 | bidang UMU to TEK | RM-2/TEK x1 |
| changed | ASISTEN PENGENDALIAN MUTU | RM-3/UMU x1 | bidang UMU to TEK | RM-3/TEK x1 |
| changed | ASISTEN PENGOLAHAN | RM-3/TEK x3 | kuota 3 to 2 | RM-3/TEK x2 |
| new in Excel | MANAJER | | | RM-1/TEK x1 |
| new in Excel | ASISTEN TATA USAHA DAN KEUANGAN | | | RM-3/KEU x1 |

### Pks Kembayan: Excel 7 seats, database 7 seats

| Status | Jabatan | Database (RM/bidang, kuota) | Change | Excel (RM/bidang, kuota) |
|---|---|---|---|---|
| changed | MANAJER | RM-1/UMU x1 | bidang UMU to TEK | RM-1/TEK x1 |
| changed | MASINIS KEPALA | RM-2/UMU x1 | bidang UMU to TEK | RM-2/TEK x1 |
| changed | ASISTEN PENGENDALIAN MUTU | RM-3/UMU x1 | bidang UMU to TEK | RM-3/TEK x1 |

### Pks Longpinang: Excel 8 seats, database 7 seats

| Status | Jabatan | Database (RM/bidang, kuota) | Change | Excel (RM/bidang, kuota) |
|---|---|---|---|---|
| changed | MANAJER | RM-1/UMU x1 | bidang UMU to TEK | RM-1/TEK x1 |
| changed | MASINIS KEPALA | RM-2/UMU x1 | bidang UMU to TEK | RM-2/TEK x1 |
| changed | ASISTEN PENGENDALIAN MUTU | RM-3/UMU x1 | bidang UMU to TEK | RM-3/TEK x1 |
| new in Excel | ASISTEN TEKNIK DAN PENGOLAHAN INSTALASI TANGKI TIMBUN TANAH MERAH (T3M) | | | RM-3/TEK x1 |

### Pks Ngabang: Excel 7 seats, database 7 seats

| Status | Jabatan | Database (RM/bidang, kuota) | Change | Excel (RM/bidang, kuota) |
|---|---|---|---|---|
| changed | MANAJER | RM-1/UMU x1 | bidang UMU to TEK | RM-1/TEK x1 |
| changed | MASINIS KEPALA | RM-2/UMU x1 | bidang UMU to TEK | RM-2/TEK x1 |
| changed | ASISTEN PENGENDALIAN MUTU | RM-3/UMU x1 | bidang UMU to TEK | RM-3/TEK x1 |

### Pks Pamukan: Excel 7 seats, database 1 seats

| Status | Jabatan | Database (RM/bidang, kuota) | Change | Excel (RM/bidang, kuota) |
|---|---|---|---|---|
| changed | ASISTEN PENGENDALIAN MUTU | RM-3/UMU x1 | bidang UMU to TEK | RM-3/TEK x1 |
| new in Excel | MANAJER | | | RM-1/TEK x1 |
| new in Excel | MASINIS KEPALA | | | RM-2/TEK x1 |
| new in Excel | ASISTEN PENGOLAHAN | | | RM-3/TEK x2 |
| new in Excel | ASISTEN TEKNIK REPARASI DAN BENGKEL UMUM | | | RM-3/TEK x1 |
| new in Excel | ASISTEN TATA USAHA DAN KEUANGAN | | | RM-3/KEU x1 |

### Pks Parindu: Excel 8 seats, database 17 seats

| Status | Jabatan | Database (RM/bidang, kuota) | Change | Excel (RM/bidang, kuota) |
|---|---|---|---|---|
| changed | MANAJER | RM-1/UMU x1 | bidang UMU to TEK | RM-1/TEK x1 |
| changed | MASINIS KEPALA | RM-2/UMU x1 | bidang UMU to TEK | RM-2/TEK x1 |
| changed | ASISTEN PENGENDALIAN MUTU | RM-3/UMU x1 | bidang UMU to TEK | RM-3/TEK x1 |
| removed in Excel | Asisten Kepala Tanaman | RM-2/TAN x1 | | |
| removed in Excel | Asisten Tata Usaha Keuangan | RM-3/KEU x1 | | |
| removed in Excel | Asisten Personalia & Teknik Kebun | RM-3/TEK x1 | | |
| removed in Excel | Asisten Inti I | RM-3/UMU x1 | | |
| removed in Excel | Asisten Afdeling II | RM-3/UMU x1 | | |
| removed in Excel | Asisten Afdeling III | RM-3/UMU x1 | | |
| removed in Excel | Asisten Inti IV | RM-3/UMU x1 | | |
| removed in Excel | Asisten Afdeling V | RM-3/UMU x1 | | |
| removed in Excel | Asisten Afdeling VI | RM-3/UMU x1 | | |
| removed in Excel | Asisten Afdeling VII | RM-3/UMU x1 | | |
| new in Excel | ASISTEN INSTALASI PEMBINAAN DAN PENGAPALAN | | | RM-3/TEK x1 |

### Pks Pelaihari: Excel 7 seats, database 6 seats

| Status | Jabatan | Database (RM/bidang, kuota) | Change | Excel (RM/bidang, kuota) |
|---|---|---|---|---|
| changed | MASINIS KEPALA | RM-2/UMU x1 | bidang UMU to TEK | RM-2/TEK x1 |
| changed | ASISTEN PENGENDALIAN MUTU | RM-3/UMU x1 | bidang UMU to TEK | RM-3/TEK x1 |
| new in Excel | MANAJER | | | RM-1/TEK x1 |

### Pks Rimba Belian: Excel 7 seats, database 7 seats

| Status | Jabatan | Database (RM/bidang, kuota) | Change | Excel (RM/bidang, kuota) |
|---|---|---|---|---|
| changed | MANAJER | RM-1/UMU x1 | bidang UMU to TEK | RM-1/TEK x1 |
| changed | MASINIS KEPALA | RM-2/UMU x1 | bidang UMU to TEK | RM-2/TEK x1 |
| changed | ASISTEN PENGENDALIAN MUTU | RM-3/UMU x1 | bidang UMU to TEK | RM-3/TEK x1 |

### Pks Samuntai: Excel 7 seats, database 1 seats

| Status | Jabatan | Database (RM/bidang, kuota) | Change | Excel (RM/bidang, kuota) |
|---|---|---|---|---|
| changed | ASISTEN PENGOLAHAN | RM-3/TEK x1 | kuota 1 to 2 | RM-3/TEK x2 |
| new in Excel | MANAJER | | | RM-1/TEK x1 |
| new in Excel | MASINIS KEPALA | | | RM-2/TEK x1 |
| new in Excel | ASISTEN TEKNIK REPARASI DAN BENGKEL UMUM | | | RM-3/TEK x1 |
| new in Excel | ASISTEN PENGENDALIAN MUTU | | | RM-3/TEK x1 |
| new in Excel | ASISTEN TATA USAHA DAN KEUANGAN | | | RM-3/KEU x1 |

### Tim Penguatan Erp Sap: Excel 12 seats, database 7 seats

| Status | Jabatan | Database (RM/bidang, kuota) | Change | Excel (RM/bidang, kuota) |
|---|---|---|---|---|
| changed | LEAD OF ERP SAP | RM-4/UMU x1 | RM RM-4 to RM-1, bidang UMU to KEU | RM-1/KEU x1 |
| changed | KEY USER MODUL MM | RM-4/UMU x2 | RM RM-4 to RM-3, bidang UMU to KEU | RM-3/KEU x2 |
| changed | KEY USER MODUL HCM | RM-4/UMU x1 | RM RM-4 to RM-3, bidang UMU to KEU | RM-3/KEU x1 |
| changed | KEY USER MODUL SD | RM-4/UMU x1 | RM RM-4 to RM-3, bidang UMU to KEU | RM-3/KEU x1 |
| changed | KEY USER MODUL PP, QM, DAN JEMBATAN TIMBANG | RM-4/UMU x1 | RM RM-4 to RM-3, bidang UMU to KEU | RM-3/KEU x1 |
| changed | KEY USER MODUL FI | RM-4/UMU x1 | RM RM-4 to RM-3, bidang UMU to KEU | RM-3/KEU x1 |
| new in Excel | KEY USER MODUL FIXED ASSET | | | RM-3/KEU x1 |
| new in Excel | KEY USER MODUL CO | | | RM-3/KEU x1 |
| new in Excel | KEY USER MODUL FM,PS,BKM,SPB, DAN D-FARM | | | RM-3/KEU x2 |
| new in Excel | KEY USER MODUL PM | | | RM-3/KEU x1 |

## 5. Units that exist on one side only

### 5.1 Only in Excel

**PMO INVESTASI TANAMAN WILAYAH KALBAR** (4 seats)

- HEAD OF PMO INVESTASI TANAMAN WILAYAH KALBAR: RM-1/TAN x1
- LEAD INVESTASI TANAMAN WILAYAH KALBAR: RM-2/TAN x1
- COORDINATOR MONITORING INVESTASI TANAMAN WILAYAH KALBAR: RM-3/TAN x1
- COORDINATOR ADMINISTRASI INVESTASI TANAMAN WILAYAH KALBAR: RM-3/TAN x1

**PMO INVESTASI TANAMAN WILAYAH KALTIM & KALSEL-TENG** (5 seats)

- HEAD OF PMO INVESTASI TANAMAN WILAYAH KALTIM & KALSEL-TENG: RM-1/TAN x1
- LEAD INVESTASI TANAMAN WILAYAH KALTIM: RM-2/TAN x1
- LEAD INVESTASI TANAMAN WILAYAH KALSEL-TENG: RM-2/TAN x1
- COORDINATOR INVESTASI TANAMAN WILAYAH KALTIM: RM-3/TAN x1
- COORDINATOR INVESTASI TANAMAN WILAYAH KALSEL-TENG: RM-3/TAN x1

**GROUP MANAGER KALBAR** (7 seats)

- GROUP MANAGER: RM-1/TAN x1
- KEPALA BIDANG OPERASIONAL: RM-2/TAN x1
- ASISTEN BIDANG TANAMAN: RM-3/TAN x3
- ASISTEN BIDANG KEUANGAN, PENGADAAN, UMUM, HUMAS DAN PERIJINAN: RM-3/KEU x1
- ASISTEN BIDANG TEKNIK DAN PENGOLAHAN: RM-3/TEK x1

**GROUP MANAGER KALSELTENG** (7 seats)

- GROUP MANAGER: RM-1/TAN x1
- KEPALA BIDANG OPERASIONAL: RM-2/TAN x1
- ASISTEN BIDANG KEUANGAN, PENGADAAN, UMUM, HUMAS DAN PERIJINAN: RM-3/KEU x1
- ASISTEN BIDANG TANAMAN: RM-3/TAN x3
- ASISTEN BIDANG TEKNIK DAN PENGOLAHAN: RM-3/TEK x1

**GROUP MANAJER KALTIM** (8 seats)

- GROUP MANAGER: RM-1/TAN x1
- KEPALA BIDANG OPERASIONAL: RM-2/TAN x1
- ASISTEN BIDANG TANAMAN DAN PLASMA: RM-3/TAN x3
- ASISTEN BIDANG TEKNIK DAN PENGOLAHAN: RM-3/TEK x1
- ASISTEN KANTOR PERWAKILAN BALIKPAPAN: RM-3/UMU x1
- ASISTEN BIDANG KEUANGAN, PENGADAAN, UMUM, HUMAS DAN PERIJINAN: RM-3/KEU x1

**KEBUN KUMAI** (9 seats)

- MANAJER: RM-1/TAN x1
- ASISTEN KEPALA TANAMAN: RM-2/TAN x1
- ASISTEN AFDELING I: RM-3/TAN x1
- ASISTEN AFDELING II: RM-3/TAN x1
- ASISTEN AFDELING III: RM-3/TAN x1
- ASISTEN AFDELING IV: RM-3/TAN x1
- ASISTEN AFDELING V: RM-3/TAN x1
- ASISTEN TATA USAHA DAN KEUANGAN: RM-3/KEU x1
- ASISTEN PERSONALIA DAN TEKNIK KEBUN: RM-3/UMU x1

**KEBUN SINTANG** (5 seats)

- MANAJER: RM-1/TAN x1
- ASISTEN AFDELING: RM-3/TAN x2
- ASISTEN TATA USAHA DAN KEUANGAN: RM-3/KEU x1
- ASISTEN PERSONALIA DAN TEKNIK KEBUN: RM-3/UMU x1

**PKR TAMBARANGAN** (6 seats)

- MANAJER: RM-1/TEK x1
- ASISTEN PENGOLAHAN: RM-3/TEK x2
- ASISTEN PENERIMAAN BOKAR DAN LABORATORIUM: RM-3/TEK x1
- ASISTEN TEKNIK REPARASI DAN BENGKEL UMUM: RM-3/TEK x1
- ASISTEN TATA USAHA DAN KEUANGAN: RM-3/KEU x1

**PMO BATU BARA DAN BISNIS LAINNYA** (4 seats)

- HEAD OF PMO BATUBARA DAN BISNIS LAINNYA: RM-1/UMU x1
- LEAD BATUBARA DAN BISNIS LAINNYA: RM-2/UMU x1
- COORDINATOR BISNIS BATUBARA: RM-3/TEK x1
- COORDINATOR PENGEMBANGAN BISNIS LAINNYA: RM-3/UMU x1

**PROYEK BATUBARA DANAU SALAK** (3 seats)

- MANAJER: RM-1/UMU x1
- STAF BIDANG OPERASIONAL: RM-3/TEK x1
- STAF BIDANG ADMINISTASI, KEUANGAN, DAN UMUM: RM-3/KEU x1

### 5.2 Only in JSON/database

**Divisi Sistem Manajemen & Sustainability** (1 seats, 1 employees)

- Management Representative Lead: RM-4/UMU x1

**Project Management Office (PMO)** (5 seats, 5 employees)

- Head of PMO Batu Bara dan Bisnis Lainnya: RM-4/UMU x1
- Lead Batu Bara & Bisnis Lainnya: RM-4/UMU x1
- Coordinator Bisnis Batu Bara: RM-4/UMU x1
- Coordinator Pengembangan Bisnis Lainnya: RM-4/UMU x1
- Head of PMO Investasi Tanamanan Wilayah Kaltim & Kalsel-Teng: RM-1/TAN x1

**Satuan Pengawasan Internal (SPI)** (9 seats, 9 employees)

- Ketua Tim Audit VA: RM-4/UMU x1
- Ketua Tim Audit VB): RM-4/UMU x1
- Ketua Tim Audit VC: RM-4/UMU x1
- Asisten Auditor VA: RM-3/UMU x2
- Asisten Auditor VB: RM-3/UMU x2
- Asisten Auditor VC: RM-3/UMU x2

**Unit Group Kalimantan Barat** (9 seats, 9 employees)

- Group Manager: RM-4/UMU x1
- Kepala Bidang Operasional: RM-4/UMU x1
- Advisor: RM-4/UMU x2
- Asisten Bidang Tanaman: RM-3/TAN x2
- Asisten Bidang Teknik & Pengolahan: RM-3/TEK x1
- Asisten Diperbantukan: RM-3/UMU x1
- Staf Inspektorat Bidang Keuangan, Pengadaan, Umum, Humas dan Perijinan: RM-4/KEU x1

**Kebun PKR Sintang** (2 seats, 2 employees)

- Asisten Personalia & Teknik Kebun: RM-3/TEK x1
- Asisten Afdeling: RM-3/None x1

**Unit Group Kalimantan Selatan/Tengah** (6 seats, 6 employees)

- Group Manager: RM-4/UMU x1
- Kepala Bidang Operasional: RM-4/UMU x1
- Asisten Kepala Diperbantukan: RM-2/TAN x1
- Advisor: RM-4/UMU x1
- Asisten Bidang Tanaman & Plasma: RM-3/TAN x1
- Asisten Bidang Teknik & Pengolahan: RM-3/TEK x1

**Proyek Batubara Danau Salak & Buntok** (3 seats, 3 employees)

- Manajer: RM-1/UMU x1
- Staf Operasional: RM-4/UMU x1
- Asisten Tata Usaha & Keuangan: RM-3/KEU x1

**Unit Group Wilayah Kalimantan Timur** (6 seats, 6 employees)

- Group Manager: RM-4/UMU x1
- Kepala Bidang Operasional: RM-4/UMU x1
- Asisten Bidang Tanaman: RM-3/TAN x2
- Asisten Bidang Teknik & Pengolahan: RM-3/TEK x1
- Asisten Bidang Keuangan, Pengadaan, Umum, Humas & Perijinan: RM-3/KEU x1

## 6. Existing employees vs Excel

- Employees: 360. Matching unit and jabatan in the Excel: **281**. Not matching: **79**.
- Status penugasan: Definitif 353, Pejabat Sementara (Pjs) 4, Diperbantukan 3

### 6.1 Unmatched (unit, jabatan) pairs

| Unit | Jabatan | Employees | Unit exists in Excel? |
|---|---|---:|---|
| Bagian Sekretariat dan Hukum | Kepala Pengamanan Perusahaan | 1 | yes |
| Bagian Sumber Daya Manusia dan Sistem Manajemen | Asisten Assessment & Rekrutment | 1 | yes |
| Bagian Sumber Daya Manusia dan Sistem Manajemen | Asisten Jaminan Sosial & Admi Pensiun | 1 | yes |
| Bagian Sumber Daya Manusia dan Sistem Manajemen | Asisten Manajemen Kinerja SDM | 1 | yes |
| Bagian Sumber Daya Manusia dan Sistem Manajemen | Asisten Manajemen Risiko | 1 | yes |
| Bagian Sumber Daya Manusia dan Sistem Manajemen | Kepala Sub Bagian Personalia & Manajemen Kinerja | 1 | yes |
| Bagian Tanaman | Asisten Investasi Tanaman | 1 | yes |
| Bagian Tanaman | Asisten Perencanaan Produksi Tanaman Sawit | 2 | yes |
| Bagian Teknik dan Pengolahan | Kepala Sub Bagian Teknik dan Infrastruktur | 1 | yes |
| Bagian Teknik dan Pengolahan | Manajer Diperbantukan | 1 | yes |
| Distrik Petani Mitra | Asisten Bidang Operasional Pembelian Bahan Baku | 2 | yes |
| Divisi Sistem Manajemen & Sustainability | Management Representative Lead | 1 | no |
| Kebun Batulicin | Asisten Inti II | 1 | yes |
| Kebun Danau Salak | Asisten Inti XII | 1 | yes |
| Kebun Danau Salak | Asisten Kepala | 1 | yes |
| Kebun Inti Gunung Meliau | Asisten Kepala Tanaman Inti Rayon Selatan | 1 | yes |
| Kebun Inti Gunung Meliau | Asisten Kepala Tanaman Rayon Utara | 1 | yes |
| Kebun Inti Sungai Dekan | Asisten Kepala Tanaman Rayon Barat | 1 | yes |
| Kebun Inti Sungai Dekan | Asisten Kepala Tanaman Rayon Timur | 1 | yes |
| Kebun Inti Sungai Dekan | Asisten Tata Usaha | 1 | yes |
| Kebun Kembayan | Asisten Inti I | 1 | yes |
| Kebun Kembayan | Asisten Inti IV | 1 | yes |
| Kebun Kembayan | Asisten Tata Usaha Keuangan | 1 | yes |
| Kebun Kemitraan Area Kalbar | Asisten Tata Usaha & Keuangan | 1 | yes |
| Kebun Kemitraan Area Kalbar | Manajer | 1 | yes |
| Kebun Kemitraan Area Kaltim-Sel | Manajer | 1 | yes |
| Kebun PKR Sintang | Asisten Afdeling | 1 | no |
| Kebun PKR Sintang | Asisten Personalia & Teknik Kebun | 1 | no |
| Kebun Pamukan | Asisten Kepala | 1 | yes |
| Kebun Pamukan | Asisten Personalia & Teknik | 1 | yes |
| Kebun Rimba Belian | Asisten Kepala Tanaman Rayon Barat | 1 | yes |
| Kebun Rimba Belian | Asisten Kepala Tanaman Rayon Timur | 1 | yes |
| Kebun Tabara | Asisten Kepala Tanaman (Rayon Selatan) | 1 | yes |
| Kebun Tabara | Asisten Kepala Tanaman (Rayon Utara) | 1 | yes |
| Kebun Tajati | Asisten Afdeling Inti III | 1 | yes |
| Kebun Tajati | Asisten Kepala (Rayon A) | 1 | yes |
| Kebun Tajati | Penugasan sebagai Asisten Kepala (Rayon B) | 1 | yes |
| Kebun-PKS Longkali | Asisten Tata Usaha Keuangan | 1 | yes |
| PKS Parindu | Asisten Tata Usaha Keuangan | 1 | yes |
| Project Management Office (PMO) | Coordinator Bisnis Batu Bara | 1 | no |
| Project Management Office (PMO) | Coordinator Pengembangan Bisnis Lainnya | 1 | no |
| Project Management Office (PMO) | Head of PMO Batu Bara dan Bisnis Lainnya | 1 | no |
| Project Management Office (PMO) | Head of PMO Investasi Tanamanan Wilayah Kaltim & Kalsel-Teng | 1 | no |
| Project Management Office (PMO) | Lead Batu Bara & Bisnis Lainnya | 1 | no |
| Proyek Batubara Danau Salak & Buntok | Asisten Tata Usaha & Keuangan | 1 | no |
| Proyek Batubara Danau Salak & Buntok | Manajer | 1 | no |
| Proyek Batubara Danau Salak & Buntok | Staf Operasional | 1 | no |
| Satuan Pengawasan Internal (SPI) | Asisten Auditor VA | 2 | no |
| Satuan Pengawasan Internal (SPI) | Asisten Auditor VB | 2 | no |
| Satuan Pengawasan Internal (SPI) | Asisten Auditor VC | 2 | no |
| Satuan Pengawasan Internal (SPI) | Ketua Tim Audit VA | 1 | no |
| Satuan Pengawasan Internal (SPI) | Ketua Tim Audit VB) | 1 | no |
| Satuan Pengawasan Internal (SPI) | Ketua Tim Audit VC | 1 | no |
| Unit Group Kalimantan Barat | Advisor | 2 | no |
| Unit Group Kalimantan Barat | Asisten Bidang Tanaman | 2 | no |
| Unit Group Kalimantan Barat | Asisten Bidang Teknik & Pengolahan | 1 | no |
| Unit Group Kalimantan Barat | Asisten Diperbantukan | 1 | no |
| Unit Group Kalimantan Barat | Group Manager | 1 | no |
| Unit Group Kalimantan Barat | Kepala Bidang Operasional | 1 | no |
| Unit Group Kalimantan Barat | Staf Inspektorat Bidang Keuangan, Pengadaan, Umum, Humas dan Perijinan | 1 | no |
| Unit Group Kalimantan Selatan/Tengah | Advisor | 1 | no |
| Unit Group Kalimantan Selatan/Tengah | Asisten Bidang Tanaman & Plasma | 1 | no |
| Unit Group Kalimantan Selatan/Tengah | Asisten Bidang Teknik & Pengolahan | 1 | no |
| Unit Group Kalimantan Selatan/Tengah | Asisten Kepala Diperbantukan | 1 | no |
| Unit Group Kalimantan Selatan/Tengah | Group Manager | 1 | no |
| Unit Group Kalimantan Selatan/Tengah | Kepala Bidang Operasional | 1 | no |
| Unit Group Wilayah Kalimantan Timur | Asisten Bidang Keuangan, Pengadaan, Umum, Humas & Perijinan | 1 | no |
| Unit Group Wilayah Kalimantan Timur | Asisten Bidang Tanaman | 2 | no |
| Unit Group Wilayah Kalimantan Timur | Asisten Bidang Teknik & Pengolahan | 1 | no |
| Unit Group Wilayah Kalimantan Timur | Group Manager | 1 | no |
| Unit Group Wilayah Kalimantan Timur | Kepala Bidang Operasional | 1 | no |

### 6.2 Over-quota after switching to the Excel

| Unit | Jabatan | Employees | Excel kuota |
|---|---|---:|---:|
| Bagian Sekretariat dan Hukum | Asisten Humas & Protokoler | 3 | 2 |
| PKS Gunung Meliau | Asisten Pengolahan | 3 | 2 |
| Bagian Teknik dan Pengolahan | Asisten Mesin & Instalasi Pabrik | 2 | 1 |

## 7. Open items for the design

- Decide, per unmatched pair in 6.1, where each employee goes (mapping file reviewed before any migration runs).
- SPI is absent from the Excel; Advisor/Diperbantukan pool positions are created by the seeder, not by the master file.
- `positions` needs uniqueness per (unit, jabatan, bidang) rather than per name to represent the Excel correctly.
- `job_histories` keeps the original text; the alias mapping is decided separately.
