# Agent Instructions & Project Guidelines
## PTPN IV Regional V — Sistem Informasi Manajemen Talenta & Formasi Pegawai

Repositori ini berpedoman pada **4 Pilar Single Source of Truth (SSOT)** yang **wajib dipatuhi** oleh setiap AI agent atau asisten coding sebelum melakukan analisis, modifikasi, atau penambahan fitur:

### 1. [DOMAIN.md](file:///c:/Users/achma/.gemini/antigravity-ide/scratch/manajemen-talenta-karyawan/DOMAIN.md) — Business Domain & Enterprise Logic
- **Kamus Data & Validasi**: Format baku NIK (8 digit string), format golongan (`[Gol]/[Berkala]`), relasi model Eloquent (`Employee`, `JobHistory`, `Evaluation`, `Training`).
- **Hierarki Organisasi & Unit**: 43 unit kerja PTPN IV Regional V (Regional Office, Kalbar, Kalselteng, Kaltim).
- **Klasifikasi Pegawai & Eselon**: Pembedaan Karpim vs Karpel, 4 RM Band (`RM-1`, `RM-2`, `RM-3`, dan `RM-4` untuk Karpel), dan 4 Bidang Fungsional (`KEU`, `TAN`, `TEK`, `UMU`).
- **Formula & Aturan Bisnis HR**: Perhitungan otomatis Batas Usia Pensiun (56 tahun) dan MBT (55 tahun), siklus mutasi (straight vs swap transfer, auto chain vacancy), serta matriks talenta 9-box.
- **Service Layer**: Wajib menggunakan `ManPowerPlanningService`, `MutasiService`, dan `JobVacancyService`. Dilarang melakukan hardcode kalkulasi RM/bidang di Blade views atau controllers.

### 2. [DESIGN.md](file:///c:/Users/achma/.gemini/antigravity-ide/scratch/manajemen-talenta-karyawan/DESIGN.md) — Design System & UI/UX
- **Design Tokens**: Palet warna perkebunan modern (primary hijau `#2F8250`, earth tones, semantic badges).
- **Tipografi & Komponen**: Plus Jakarta Sans, card enterprise, form inputs, modal konfirmasi destruktif, live impact panels.
- **Micro-interactions & Loading**: State loading Livewire eksplisit, skeleton screens, responsivitas mobile-first untuk lapangan dan desktop-first untuk admin/analitik.

### 3. [ENGINEERING.md](file:///c:/Users/achma/.gemini/antigravity-ide/scratch/manajemen-talenta-karyawan/ENGINEERING.md) — Engineering Standards & Testing Strategy
- **SDLC 5-Tahap**: Domain/Design Alignment $\rightarrow$ Service/Model Layer $\rightarrow$ UI/Livewire Integration $\rightarrow$ Automated Tests & Pint $\rightarrow$ Security/Audit.
- **Standar Kode**: Operasi multi-tabel wajib dibungkus dalam `DB::transaction()`. Penggunaan debounce pada Livewire search (`300ms`).
- **Testing & QA**: PHPUnit 11 untuk formula bisnis (Unit Test) dan alur mutasi/Livewire (Feature Test). Formatting wajib menggunakan Laravel Pint (`./vendor/bin/pint`).

### 4. [SECURITY.md](file:///c:/Users/achma/.gemini/antigravity-ide/scratch/manajemen-talenta-karyawan/SECURITY.md) — Security Policy & Data Governance
- **Privasi Data Pegawai**: Kepatuhan terhadap UU No. 27/2022 (UU PDP). Perlindungan kerahasiaan NIK, data keluarga, dan skor evaluasi 9-box.
- **Mitigasi OWASP**: Parameter binding wajib (anti-SQLi), CSRF token pada setiap form, auto-escaping `{{ }}` pada Blade (anti-XSS), validasi MIME-type pada upload Excel/sertifikat.
- **Audit Trail**: Seluruh transaksi mutasi dan pengangkatan jabatan wajib tercatat di tabel `job_histories`.

---

### Aturan Perilaku AI Agent (Coding Behavior)
1. **Verifikasi SSOT**: Rujuk dokumen yang relevan sebelum menulis kode.
2. **Integritas Kode**: Pertahankan dokumentasi, docblock, dan komentar struktural yang ada.
3. **Database & Migrations**: Pastikan integritas tipe data (misal NIK sebagai `string(8)` bukan integer).
4. **Kerapihan & Validasi**: Jalankan formatting Laravel Pint dan pastikan pengujian passing jika menambahkan fitur baru.
