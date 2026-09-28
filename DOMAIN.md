# DOMAIN.md — Domain Knowledge, Business Rules & Enterprise Architecture
## PT Perkebunan Nusantara IV Regional V (Kalimantan)
### Sistem Informasi Manajemen Talenta & Formasi Pegawai

> Dokumen ini adalah **acuan domain tunggal (Single Source of Truth for Business Domain & Enterprise Rules)** mengenai tata kelola kepegawaian, struktur organisasi perkebunan, hierarki jabatan manajerial, matriks talenta 9-box, serta aturan bisnis HR di lingkungan **PT Perkebunan Nusantara IV Regional V**.
>
> Dokumen ini berpasangan langsung dengan [DESIGN.md](file:///c:/Users/achma/.gemini/antigravity-ide/scratch/manajemen-talenta-karyawan/DESIGN.md) (Single Source of Truth untuk UI/UX & Design System). Setiap AI Model (vibe coding) dan developer wajib membaca dokumen ini sebelum mengimplementasikan model, migrasi, validasi form, service, query scope, atau alur transaksi bisnis HR.

---

## Daftar Isi
1. [Tujuan & Filosofi Domain](#1-tujuan--filosofi-domain)
2. [Terminologi & Kamus Data Domain (Data Dictionary)](#2-terminologi--kamus-data-domain-data-dictionary)
3. [Struktur Wilayah & Master Data 43 Unit Kerja](#3-struktur-wilayah--master-data-43-unit-kerja)
4. [Strata Pegawai, RM Band, & 4 Bidang Fungsional](#4-strata-pegawai-rm-band--4-bidang-fungsional)
5. [Aturan Bisnis HR & Rumus Perhitungan](#5-aturan-bisnis-hr--rumus-perhitungan)
6. [Manajemen Kinerja & Matriks Talenta (9-Box Grid)](#6-manajemen-kinerja--matriks-talenta-9-box-grid)
7. [Pengembangan Kompetensi & Pelatihan (L&D)](#7-pengembangan-kompetensi--pelatihan-ld)
8. [Perencanaan Tenaga Kerja (Man Power Planning / MPP)](#8-perencanaan-tenaga-kerja-man-power-planning--mpp)
9. [Arsitektur Layanan & Kontrak Kode (Service Contracts)](#9-arsitektur-layanan--kontrak-kode-service-contracts)
10. [Sinkronisasi Domain ke UI ([DESIGN.md](file:///c:/Users/achma/.gemini/antigravity-ide/scratch/manajemen-talenta-karyawan/DESIGN.md))](#10-sinkronisasi-domain-ke-ui-designmd)
11. [Panduan Vibe Coding AI (Agent Prompts & Contracts)](#11-panduan-vibe-coding-ai-agent-prompts--contracts)

---

## 1. Tujuan & Filosofi Domain

Pengelolaan SDM di BUMN Perkebunan (PTPN IV Regional V) memiliki karakteristik unik yang membedakannya dari SaaS HR konvensional:
1. **Agro-Industrial Dualism**: Membawahi dua domain operasional sekaligus — perkebunan (on-farm: panen kelapa sawit & getah karet) dan pabrik pengolahan (off-farm: PKS/Pabrik Kelapa Sawit & Pengolahan Karet).
2. **Keterikatan Wilayah yang Luas**: Wilayah kerja terbentang di seluruh penjuru Kalimantan (Regional Office di Pontianak, kebun dan pabrik di Kalbar, Kalsel, Kalteng, hingga Kaltim). Kebijakan mutasi dan rotasi harus memperhitungkan masa dinas unit agar stabilitas produksi terjaga.
3. **Hierarki Ketat & Rantai Formasi (Zero Leakage)**: Mutasi pimpinan tidak pernah berdiri sendiri; pemindahan satu manajer memicu rantai lowongan (*chain vacancy*) yang harus dipantau secara transparan sampai terisi (*closed*).
4. **Kepastian Masa Pensiun & Regenerasi**: Batas Usia Pensiun (56 tahun) dan Masa Bebas Tugas/MBT (55 tahun) adalah angka pasti yang mengikat perhitungan kebutuhan formasi (MPP) hingga beberapa tahun ke depan ($T, T+1, T+2$).

---

## 2. Terminologi & Kamus Data Domain (Data Dictionary)

### 2.1 Entitas Pegawai (`Employee` / `employees`)

| Field / Atribut | Tipe Data | Deskripsi & Validasi | Nilai Valid / Format |
|---|---|---|---|
| `nik` | `string(8)` | **Primary Key**. Nomor Induk Karyawan unik 8 digit numerik. Regex: `/^[0-9]{8}$/`. | `13004521`, `13004837` |
| `nama` | `string` | Nama resmi sesuai SK Pengangkatan/KTP. Disimpan dalam format UPPERCASE. | `DONNY USMAN`, `HERRY WAHYUDI` |
| `level` | `string` | Kategori strata pegawai: Karyawan Pimpinan atau Pelaksana. | `Karpim`, `Karpel` |
| `rm_level` | `string/enum` | Eselon kepemimpinan manajerial: RM-1, RM-2, RM-3 (atau non-RM untuk pelaksana). | `RM-1`, `RM-2`, `RM-3` |
| `bidang` | `string` | Bidang fungsional operasional pegawai. | `KEU`, `TAN`, `TEK`, `UMU` |
| `jabatan` | `string` | Nomenklatur jabatan dinas yang sedang diemban saat ini. | `Manajer Kebun`, `Askep Tanaman`, `Asisten Afdeling` |
| `unit_kerja` | `string` | Nama unit kerja definitif dari 43 unit resmi perusahaan. | `Kebun Inti Gunung Meliau`, `PKS Samuntai` |
| `golongan` | `string` | Tingkat golongan kepangkatan beserta berkala genap (`[Gol]/[Berkala]`). | `IIIA/00`, `IIID/05`, `IVA/05` |
| `job_grade` | `integer` | Bobot kompleksitas jabatan (1 s.d. 16). | Karpel: 1-10; Karpim: 11-16 |
| `person_grade` | `integer` | Tingkat kompetensi individual pegawai (1 s.d. 16). | Karpel: 1-10; Karpim: 11-16 |
| `tanggal_lahir` | `date` | Tanggal lahir pegawai (dasar perhitungan pensiun & MBT). | `1980-08-15` |
| `tmt_bekerja` | `date` | Terhitung Mulai Tanggal pertama kali diangkat di PTPN. | `2005-01-01` |
| `tanggal_diangkat_staf`| `date` | Tanggal resmi diangkat menjadi staf pimpinan (Karpim). Kosong jika Karpel. | `2012-06-01` |
| `tmt_unit_kerja` | `date` | Tanggal mulai bertugas di unit kerja saat ini (dasar rotasi dinas). | `2022-03-01` |
| `tanggal_dalam_jabatan` | `date` | Tanggal SK pengangkatan posisi jabatan saat ini. | `2023-01-01` |
| `tanggal_pensiun` | `date` | Tanggal jatuh tempo pensiun (tepat 56 tahun). | Ditentukan otomatis oleh sistem |
| `tanggal_mbt` | `date` | Tanggal Masa Bebas Tugas (tepat 1 tahun sebelum pensiun / 55 tahun). | Ditentukan otomatis oleh sistem |
| `susunan_keluarga` | `string` | Status tanggungan perkawinan & anak. | `L`, `TK`, `K/0`, `K/1`, `K/2`, `K/3` |

### 2.2 Entitas Riwayat Jabatan & Mutasi (`JobHistory` / `job_histories`)

| Field / Atribut | Tipe Data | Deskripsi |
|---|---|---|
| `id` | `bigint (PK)` | Auto-increment identifier. |
| `employee_nik` | `string(8)` | FK ke `employees.nik`. |
| `jabatan` | `string` | Jabatan yang diemban pada periode tersebut. |
| `unit_kerja` | `string` | Unit kerja penempatan pada periode tersebut. |
| `nomor_sk` | `string?` | Nomor Surat Keputusan Direksi terkait penempatan. |
| `tanggal_sk` | `date?` | Tanggal diterbitkannya SK Direksi. |
| `tmt_awal` | `date` | Tanggal mulai efektif menjabat. |
| `tmt_akhir` | `date?` | Tanggal berakhir menjabat (NULL jika masih aktif). |
| `status_jabatan` | `string` | Status jabatan: `Definitif`, `Pj (Pejabat Sementara)`, `Plt (Pelaksana Tugas)`. |
| `jenis_mutasi` | `string` | `MUTASI_UNIT`, `PROMOSI`, `DEMOSI`, `ROTASI`. |

### 2.3 Entitas Evaluasi Kinerja & 9-Box (`Evaluation` / `evaluations`)

| Field / Atribut | Tipe Data | Deskripsi |
|---|---|---|
| `employee_nik` | `string(8)` | FK ke `employees.nik`. |
| `skor_smkbk_9box` | `float/decimal` | Skor Sistem Manajemen Kinerja Berbasis Kompetensi (Sumbu X: Performance). |
| `skor_cli_9box` | `float/decimal` | Skor Capacity / Leadership Index (Sumbu Y: Potential). |
| `kategori_9box` | `integer (1-9)` | Hasil klasifikasi kuadran 9-box talent matrix (1 s.d. 9). |
| `nilai_kepemimpinan` | `decimal` | Komponen penilaian pilar kepemimpinan. |
| `nilai_perilaku_budaya`| `decimal` | Komponen penilaian budaya kerja AKHLAK BUMN. |
| `nilai_pengalaman_teknis`| `decimal` | Komponen keahlian teknis agro/pabrik/keuangan. |
| `nilai_tertimbang` | `decimal` | Nilai agregat akhir evaluasi kinerja tahunan. |
| `lembaga_asesmen` | `string?` | Lembaga penguji eksternal (Assessment Center Holding BUMN). |
| `tanggal_pelaksanaan_asesmen` | `date?` | Waktu asesmen dilaksanakan. |
| `expired_asesmen` | `date?` | Tanggal berakhir masa berlaku asesmen (umumnya 2 tahun sejak asesmen). |

### 2.4 Entitas Pelatihan & Sertifikasi (`Training` / `trainings` & `employee_training`)

| Field / Atribut | Tipe Data | Deskripsi |
|---|---|---|
| `judul` | `string` | Nama program pengembangan / kursus teknis. |
| `jenis` | `string` | `Leadership`, `Teknis Kelapa Sawit`, `Teknis Pabrik`, `Finance/Akuntansi`, `K3 & Sustainability`. |
| `metode` | `string` | `Klasikal (Tatap Muka)`, `In-House Training`, `E-Learning`, `Workshop Praktik`. |
| `jumlah_man_hours` | `integer` | Akumulasi durasi pelatihan untuk pelaporan Man Hours Learning BUMN. |
| `pivot.sertifikat` | `string?` | Path/URL bukti sertifikat kelulusan pegawai. |

---

## 3. Struktur Wilayah & Master Data 43 Unit Kerja

Operasional PTPN IV Regional V mencakup Kantor Direksi di Pontianak serta unit-unit kebun dan pabrik (PKS) yang tersebar di seluruh Pulau Kalimantan (total 43 unit kerja aktif):

### 3.1 Regional Office (Kantor Direksi / Head Office Pontianak)
*Kode Wilayah: `REG`*

1. **Bagian Sekretariat dan Hukum** *(Tipe: KANDIR | Bidang: UMU)*
2. **Bagian Sumber Daya Manusia dan Sistem Manajemen** *(Tipe: KANDIR | Bidang: UMU)*
3. **Bagian Tanaman** *(Tipe: KANDIR | Bidang: TAN)*
4. **Bagian Teknik dan Pengolahan** *(Tipe: KANDIR | Bidang: TEK)*
5. **Bagian Akuntansi dan Keuangan** *(Tipe: KANDIR | Bidang: KEU)*
6. **Tim Penguatan ERP SAP** *(Tipe: KANDIR | Bidang: UMU)*
7. **Bagian Pengadaan dan Teknologi Informasi** *(Tipe: KANDIR | Bidang: UMU)*
8. **Divisi Sistem Manajemen & Sustainability** *(Tipe: KANDIR | Bidang: UMU)*
9. **Project Management Office (PMO)** *(Tipe: KANDIR | Bidang: UMU)*
10. **Satuan Pengawasan Internal (SPI)** *(Tipe: KANDIR | Bidang: UMU/KEU)*
11. **Distrik Petani Mitra** *(Tipe: DISTRIK | Bidang: TAN)*
12. **Kebun Kemitraan Area Kalbar** *(Tipe: KEBUN | Bidang: TAN)*
13. **Kebun Kemitraan Area Kaltim-Sel** *(Tipe: KEBUN | Bidang: TAN)*

### 3.2 Wilayah Kalimantan Barat
*Kode Wilayah: `KALBAR`*

14. **Unit Group Kalimantan Barat** *(Tipe: GROUP | Bidang: TAN/TEK)*
15. **Kebun Inti Gunung Meliau** *(Tipe: KEBUN | Bidang: TAN | Kelapa Sawit)*
16. **PKS Gunung Meliau** *(Tipe: PKS | Bidang: TEK)*
17. **Kebun Gunung Emas** *(Tipe: KEBUN | Bidang: TAN | Kelapa Sawit)*
18. **Kebun Inti Sungai Dekan** *(Tipe: KEBUN | Bidang: TAN | Kelapa Sawit)*
19. **Kebun Rimba Belian** *(Tipe: KEBUN | Bidang: TAN | Kelapa Sawit)*
20. **PKS Rimba Belian** *(Tipe: PKS | Bidang: TEK)*
21. **Kebun PKR Sintang** *(Tipe: KEBUN | Bidang: TAN | Karet & Sawit)*
22. **Kebun Ngabang** *(Tipe: KEBUN | Bidang: TAN | Kelapa Sawit)*
23. **PKS Ngabang** *(Tipe: PKS | Bidang: TEK)*
24. **Kebun Parindu** *(Tipe: KEBUN | Bidang: TAN | Kelapa Sawit)*
25. **PKS Parindu** *(Tipe: PKS | Bidang: TEK)*
26. **Kebun Kembayan** *(Tipe: KEBUN | Bidang: TAN | Kelapa Sawit)*
27. **PKS Kembayan** *(Tipe: PKS | Bidang: TEK)*

### 3.3 Wilayah Kalimantan Selatan & Kalimantan Tengah
*Kode Wilayah: `KALSELTENG`*

28. **Unit Group Kalimantan Selatan/Tengah** *(Tipe: GROUP | Bidang: TAN/TEK)*
29. **Kebun Danau Salak** *(Tipe: KEBUN | Bidang: TAN | Karet & Sawit)*
30. **Kebun Pamukan** *(Tipe: KEBUN | Bidang: TAN | Kelapa Sawit)*
31. **PKS Pamukan** *(Tipe: PKS | Bidang: TEK)*
32. **Kebun Batulicin** *(Tipe: KEBUN | Bidang: TAN | Karet & Sawit)*
33. **Kebun Pelaihari** *(Tipe: KEBUN | Bidang: TAN | Kelapa Sawit)*
34. **PKS Pelaihari** *(Tipe: PKS | Bidang: TEK)*
35. **Proyek Batubara Danau Salak & Buntok** *(Tipe: PROYEK | Bidang: UMU/TEK)*
36. **Kebun Raren Batuah** *(Tipe: KEBUN | Bidang: TAN | Karet & Sawit)*

### 3.4 Wilayah Kalimantan Timur
*Kode Wilayah: `KALTIM`*

37. **Unit Group Wilayah Kalimantan Timur** *(Tipe: GROUP | Bidang: TAN/TEK)*
38. **Kebun Tabara** *(Tipe: KEBUN | Bidang: TAN | Kelapa Sawit)*
39. **Kebun Tajati** *(Tipe: KEBUN | Bidang: TAN | Kelapa Sawit)*
40. **Kebun Inti Pandawa** *(Tipe: KEBUN | Bidang: TAN | Kelapa Sawit)*
41. **PKS Longpinang** *(Tipe: PKS | Bidang: TEK)*
42. **PKS Samuntai** *(Tipe: PKS | Bidang: TEK)*
43. **Kebun-PKS Longkali** *(Tipe: KEBUN & PKS Terpadu | Bidang: TAN/TEK)*

---

## 4. Strata Pegawai, RM Band, & 4 Bidang Fungsional

### 4.1 Strata Pegawai
1. **Karyawan Pimpinan (Karpim)**:
   - Staf pimpinan/manajerial pemegang keputusan strategis & operasional.
   - Golongan: **Golongan III** (`IIIA`, `IIIB`, `IIIC`, `IIID`) & **Golongan IV** (`IVA`, `IVB`, `IVC`, `IVD`).
   - Grade: **Job Grade 11 s.d. 16**, **Person Grade 11 s.d. 16**.
2. **Karyawan Pelaksana (Karpel)**:
   - Tenaga operasional non-staf lini depan.
   - Golongan: **Golongan I** (`IA` - `ID`) & **Golongan II** (`IIA` - `IID`).
   - Grade: **Job Grade 1 s.d. 10**, **Person Grade 1 s.d. 10**.

### 4.2 Resource Management (RM) Band

```
┌────────────────────────────────────────────────────────────────────────┐
│                        4 BIDANG FUNGSIONAL                             │
│   [KEU] Keuangan    [TAN] Tanaman    [TEK] Teknik    [UMU] Umum & SDM  │
└────────────────────────────────────┬───────────────────────────────────┘
                                     │
          ┌──────────────────────────┼──────────────────────────┐
          ▼                          ▼                          ▼
   ┌─────────────┐            ┌─────────────┐            ┌─────────────┐
   │    RM-1     │            │    RM-2     │            │    RM-3     │
   │  PIMPINAN   │            │    MADYA    │            │   PRATAMA   │
   │ Job Gr: 15-16            │ Job Gr: 13-14            │ Job Gr: 11-12
   │ Gol: IV                  │ Gol: IIIC - IIID         │ Gol: IIIA - IIIB
   └─────────────┘            └─────────────┘            └─────────────┘
```

1. **RM-1 (Senior Leadership / Pimpinan Puncak Unit)**:
   - Standar Golongan: `IVA` s.d. `IVD`
   - Standar Job Grade: `15` – `16`
   - Posisi Kunci: Kepala Bagian Regional Office, Manajer Kebun, Manajer Pabrik (PKS), General Manager Unit Group, Koordinator SPI.
2. **RM-2 (Middle Management / Madya)**:
   - Standar Golongan: `IIIC` s.d. `IIID` (dan promosi awal `IVA`)
   - Standar Job Grade: `13` – `14`
   - Posisi Kunci: Kepala Sub Bagian (Kasubag), Askep Tanaman, Askep Pengolahan/PKS (Masinis Kepala).
3. **RM-3 (First-Line Management / Pratama)**:
   - Standar Golongan: `IIIA` s.d. `IIIB` (dan `IIIC`)
   - Standar Job Grade: `11` – `12`
   - Posisi Kunci: Asisten Afdeling/Kebun, Asisten Pengolahan, Asisten Bengkel/Maintenance, Asisten QC/Laboratorium, Asisten Tata Usaha (KTU), Asisten SDM/Humas.

### 4.3 4 Bidang Fungsional
Klasifikasi bidang ditentukan secara deterministik oleh `ManPowerPlanningService@determineBidang`:
- **KEU (Keuangan & Akuntansi)**: mendeteksi kata kunci `keu`, `akuntan`, `anggaran`, `pajak`, `kas`, `perbendaharaan`, `tata usaha`.
- **TAN (Tanaman & Agronomi)**: mendeteksi kata kunci `tanaman`, `agronomi`, `afdeling`, `rayon`, `panen`, `kebun`, `pemeliharaan`, `tbs`.
- **TEK (Teknik & Pengolahan)**: mendeteksi kata kunci `teknik`, `pabrik`, `pks`, `pengolahan`, `bengkel`, `instalasi`, `mesin`, `mill`, `laboratorium`.
- **UMU (Umum, SDM, & Tata Kelola)**: mendeteksi kata kunci `sdm`, `sistem manajemen`, `umum`, `hukum`, `sekretariat`, `pengadaan`, `it`, `ti`, `humas`, `keamanan`, `pmo`, `spi`.

---

## 5. Aturan Bisnis HR & Rumus Perhitungan

### 5.1 Perhitungan Batas Usia Pensiun (BUP) & MBT
1. **Batas Usia Pensiun (56 Tahun)**:
   - Jatuh tepat pada hari pertama bulan kelahiran pada saat pegawai berusia 56 tahun.
   - **Formula Baku (PHP/Carbon)**:
     ```php
     $tanggalPensiun = Carbon::parse($tanggalLahir)->addYears(56)->startOfMonth();
     ```
   - Contoh: Lahir `1979-05-14` -> Pensiun pada `2035-06-01`.
2. **Masa Bebas Tugas / MBT (55 Tahun)**:
   - Diberikan T-1 tahun sebelum tanggal pensiun resmi untuk masa persiapan pensiun.
   - **Formula Baku**:
     ```php
     $tanggalMbt = Carbon::parse($tanggalPensiun)->subYear();
     ```
   - Contoh: Pensiun `2035-06-01` -> MBT pada `2034-06-01`.
3. **Penyimpanan Database**:
   - Jika saat create/import pegawai form tidak mengisi `tanggal_pensiun` dan `tanggal_mbt`, sistem **wajib** mengisi secara otomatis menggunakan rumus di atas.

### 5.2 Regulasi Mutasi & Chain Vacancy (`MutasiService`)
1. **Masa Dinas Minimal di Unit Kerja**:
   - Pegawai minimal menjabat **2 hingga 3 tahun** (`tmt_unit_kerja`) sebelum diperbolehkan rotasi unit, demi menjaga stabilitas rotasi panen dan olah pabrik.
2. **Dua Mode Transaksi Mutasi**:
   - **Straight Transfer (Target Posisi Kosong)**:
     - Pegawai dipindahkan ke posisi jabatan/unit baru yang sedang lowong.
     - Posisi lama pegawai yang ditinggalkan **otomatis menjadi formasi kosong baru (1 chain vacancy)**.
     - Sistem mencatat riwayat jabatan baru di `job_histories` dengan `tmt_awal` mutasi dan menutup posisi sebelumnya dengan `tmt_akhir`.
   - **Swap Transfer (Target Posisi Terisi)**:
     - Pegawai A dipindahkan ke posisi Pegawai B, dan Pegawai B secara atomik ditukar menempati posisi Pegawai A.
     - Dijalankan dalam satu DB Transaction atomik (`DB::transaction`).
     - **Net zero vacancy** (tidak memunculkan formasi kosong baru).
3. **Jalur Karir & Promosi**:
   - **RM-3 $\rightarrow$ RM-2**: Syarat minimal Golongan `IIIC` atau masa dinas Asisten $\ge 4$ tahun dengan evaluasi kinerja minimal kategori "Baik".
   - **RM-2 $\rightarrow$ RM-1**: Syarat minimal Golongan `IIID` / `IVA`, telah menjabat Askep minimal 3 tahun, dan mengantongi sertifikat asesmen kompetensi holding.

### 5.3 Validasi Integritas Data Pegawai
1. `nik` harus 8 digit numerik dan unik.
2. `tanggal_lahir` < `tmt_bekerja` < `tanggal_diangkat_staf` $\le$ `tanggal_dalam_jabatan`.
3. `tmt_bekerja` minimal 18 tahun setelah `tanggal_lahir`.
4. Jika `level == 'Karpim'`, `job_grade` dan `person_grade` wajib $\ge 11$.

---

## 6. Manajemen Kinerja & Matriks Talenta (9-Box Grid)

Pemetaan talenta merujuk pada standar Kementerian BUMN & Holding Perkebunan menggunakan Matriks 9-Box:

```
POTENSI (CLI)
  ▲
  │ [Box 7: Diamond]      [Box 8: High Potential]  [Box 9: Star]
H │ Potensi Tinggi /      Potensi Tinggi /         Potensi Tinggi /
  │ Kinerja Rendah        Kinerja Sedang           Kinerja Tinggi
  │ ─────────────────────────────────────────────────────────────
  │ [Box 4: Dilemma]      [Box 5: Core Talent]     [Box 6: High Performer]
M │ Potensi Sedang /      Potensi Sedang /         Potensi Sedang /
  │ Kinerja Rendah        Kinerja Sedang           Kinerja Tinggi
  │ ─────────────────────────────────────────────────────────────
  │ [Box 1: Risk]         [Box 2: Effective]       [Box 3: Specialist]
L │ Potensi Rendah /      Potensi Rendah /         Potensi Rendah /
  │ Kinerja Rendah        Kinerja Sedang           Kinerja Tinggi
  └─────────────────────────────────────────────────────────────► KINERJA (SMKBK)
               LOW (L)           MEDIUM (M)             HIGH (H)
```

- **Sumbu X**: Kinerja individual berdasarkan SMKBK (Key Performance Indicators tahunan).
- **Sumbu Y**: Potensi & kapabilitas kepemimpinan berdasarkan Capacity & Leadership Index (CLI).
- **Asesmen Eksternal**: Pegawai di Box 8 & Box 9 adalah kandidat utama suksesi RM-1 dan RM-2, dengan syarat `expired_asesmen` belum kedaluwarsa.

---

## 7. Pengembangan Kompetensi & Pelatihan (L&D)

Modul Pelatihan (`Trainings`) berfungsi memantau investasi kompetensi talenta:
1. **Perhitungan Man Hours**:
   - `jumlah_man_hours = jam_belajar_per_hari * durasi_hari * jumlah_peserta`.
   - Menjadi indikator capaian KPI pembelajaran tahunan unit kerja.
2. **Surat Tugas & Sertifikasi**:
   - Setiap partisipasi dihubungkan melalui tabel relasi `employee_training` dengan nomor surat tugas dan berkas upload digital `sertifikat`.
   - Riwayat sertifikat ini menjadi portofolio resmi saat proses mutasi atau promosi eselon.

---

## 8. Perencanaan Tenaga Kerja (Man Power Planning / MPP)

Service `ManPowerPlanningService` memuat data acuan baseline perusahaan:
1. **Formasi Standar vs Realisasi**:
   - Menghitung rasio pemenuhan formasi per RM Band (RM-1, RM-2, RM-3) pada setiap bidang (KEU, TAN, TEK, UMU).
2. **Pipa Talenta Suksesi**:
   - **CKP (Calon Karyawan Pimpinan)**: Karpel berprestasi yang dipersiapkan promosi staf RM-3.
   - **Talent Scouting**: Penjaringan talenta muda unggul afdeling & pabrik.
   - **RBB (Rencana Bisnis & Penambahan Formasi)**: Kebutuhan formasi baru akibat perluasan areal kebun atau revitalisasi pabrik.
3. **Proyeksi Pensiun Multitahun**:
   - Sistem memproyeksikan data pensiun berjalan ($T$) dan tahun depan ($T+1$) untuk mengantisipasi regenerasi jabatan strategis tanpa jeda operasional.

---

## 9. Arsitektur Layanan & Kontrak Kode (Service Contracts)

AI dan pengembang harus memanfaatkan Service Layer yang ada:

### 9.1 `ManPowerPlanningService`
- `determineRmLevel(Employee $emp): string` $\rightarrow$ `'RM-1'`, `'RM-2'`, `'RM-3'`
- `determineBidang(Employee $emp): string` $\rightarrow$ `'KEU'`, `'TAN'`, `'TEK'`, `'UMU'`
- `getBaselineData(): array` $\rightarrow$ Data standar formasi BUMN perkebunan.

### 9.2 `MutasiService`
- `analyzeTarget(string $jabatan, string $unitKerja): array` $\rightarrow$ Cek ketersediaan formasi (vacant vs occupied) untuk preview live impact panel UI.
- `processMutasi(...): array` $\rightarrow$ Transaksi atomik perubahan jabatan, pembentukan chain vacancy, dan update riwayat jabatan.

### 9.3 `JobVacancyService`
- Mendeteksi formasi kosong manajerial aktif berdasarkan standar formasi unit vs pejabat definitif yang bertugas.

---

## 10. Sinkronisasi Domain ke UI ([DESIGN.md](file:///c:/Users/achma/.gemini/antigravity-ide/scratch/manajemen-talenta-karyawan/DESIGN.md))

Tampilan UI wajib mencerminkan warna semantik domain yang telah disepakati di [DESIGN.md](file:///c:/Users/achma/.gemini/antigravity-ide/scratch/manajemen-talenta-karyawan/DESIGN.md):

| Domain Entity | Kategori | Tailwind Classes (DESIGN.md) |
|---|---|---|
| **Bidang KEU** | Keuangan & Kas | `bg-emerald-50 text-emerald-700 border-emerald-200` |
| **Bidang TAN** | Tanaman & Agronomi | `bg-lime-50 text-lime-700 border-lime-200` |
| **Bidang TEK** | Teknik & Pabrik PKS | `bg-amber-50 text-amber-700 border-amber-200` |
| **Bidang UMU** | Umum, SDM, Legal | `bg-sky-50 text-sky-700 border-sky-200` |
| **RM-1** | Pimpinan Puncak | `bg-orange-100 text-orange-800 border-orange-200` |
| **RM-2** | Pimpinan Madya | `bg-sky-100 text-sky-800 border-sky-200` |
| **RM-3** | Pimpinan Pratama | `bg-purple-100 text-purple-800 border-purple-200` |
| **Karpim** | Staf Pimpinan | `bg-primary/10 text-primary border-primary/20` |
| **Karpel** | Pelaksana Lapangan | `bg-slate-100 text-slate-700 border-slate-200` |

---

## 11. Panduan Vibe Coding AI (Agent Prompts & Contracts)

Saat AI Model menulis kode untuk repositori ini, patuhi aturan mutlak berikut:

1. **Gunakan Service Layer, Jangan Duplikasi Logika**:
   - Selalu gunakan `ManPowerPlanningService` untuk menentukan RM level dan bidang fungsional. Jangan menulis `if/else` manual penentu RM di Blade template.
2. **Kalkulasi Tanggal Pensiun & MBT Selalu Otomatis**:
   - Dalam seeder, form create, ataupun import Excel pegawai, jika `tanggal_pensiun` tidak diberikan, kalkulasikan secara otomatis dari `tanggal_lahir` (+56 tahun dan -1 tahun untuk MBT).
3. **Penyimpanan NIK & Tanggal**:
   - NIK selalu diperlakukan sebagai string (jangan cast ke int agar leading zero tidak hilang).
   - Format tanggal input selalu divalidasi `Y-m-d`.
4. **Patuhi Pasangan [DESIGN.md](file:///c:/Users/achma/.gemini/antigravity-ide/scratch/manajemen-talenta-karyawan/DESIGN.md)**:
   - Jika mengubah tampilan atau membuat komponen baru, rujuk [DESIGN.md](file:///c:/Users/achma/.gemini/antigravity-ide/scratch/manajemen-talenta-karyawan/DESIGN.md) untuk struktur token CSS, modal dialog, formulir input, dan layout kartu enterprise.

---
*Dokumen ini dirawat sebagai Single Source of Truth Domain PTPN IV Regional V berdampingan dengan [DESIGN.md](file:///c:/Users/achma/.gemini/antigravity-ide/scratch/manajemen-talenta-karyawan/DESIGN.md).*
