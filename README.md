# Sistem Manajemen Talenta & Formasi Pegawai
## PT Perkebunan Nusantara IV Regional V (Kalimantan)

Aplikasi Human Resources Information System (HRIS) dan perencanaan formasi talenta (*Man Power Planning*) berbasis web yang dirancang khusus untuk mengelola data kepegawaian, formasi jabatan pimpinan (RM Band), riwayat mutasi, evaluasi kinerja 9-box, serta monitoring pensiun di lingkungan PT Perkebunan Nusantara IV Regional V.

---

## 📚 Dokumen Acuan Tunggal (4 Pilar SSOT)

Proyek ini dipandu oleh empat pilar dokumentasi arsitektur utama:

1. **[DOMAIN.md](file:///c:/Users/achma/.gemini/antigravity-ide/scratch/manajemen-talenta-karyawan/DOMAIN.md)** — **Business Domain & Enterprise Rules**
   - Kamus data dan spesifikasi entitas (`Employee`, `JobHistory`, `Evaluation`, `Training`, `JobVacancy`).
   - Struktur 43 unit kerja (Regional Office, Kalbar, Kalselteng, Kaltim).
   - Regulasi SDM BUMN: Strata Karpim & Karpel, RM Band (`RM-1`, `RM-2`, `RM-3`), 4 Bidang Fungsional (`KEU`, `TAN`, `TEK`, `UMU`).
   - Rumus otomatis Pensiun (56 tahun) & Masa Bebas Tugas (MBT 55 tahun).
   - Aturan transaksi mutasi (straight vs swap transfer, auto-chain vacancy).
   - Service layer: `ManPowerPlanningService`, `MutasiService`, `JobVacancyService`.

2. **[DESIGN.md](file:///c:/Users/achma/.gemini/antigravity-ide/scratch/manajemen-talenta-karyawan/DESIGN.md)** — **Design System & UI/UX Guidelines**
   - Panduan UI/UX berbasis TALL Stack (Tailwind CSS, Alpine.js, Laravel, Livewire).
   - Design tokens: Palet warna "Perkebunan Modern" (hijau `#2F8250`, earth tones, semantic badges).
   - Komponen antarmuka enterprise, dialog konfirmasi modal, dan live impact analysis panel.

3. **[ENGINEERING.md](file:///c:/Users/achma/.gemini/antigravity-ide/scratch/manajemen-talenta-karyawan/ENGINEERING.md)** — **Engineering Standards, SDLC & Testing Strategy**
   - Alur Agile Feature-Driven SDLC 5 tahap.
   - Pola Service Layer, transaksi atomik database (`DB::transaction()`), dan standar Livewire components.
   - Strategi pengujian otomatis: PHPUnit 11 (Unit Tests formula HR & Feature Tests alur mutasi) dan linter Laravel Pint.

4. **[SECURITY.md](file:///c:/Users/achma/.gemini/antigravity-ide/scratch/manajemen-talenta-karyawan/SECURITY.md)** — **Security Policy & Data Governance**
   - Standar kepatuhan privasi data pegawai (UU No. 27/2022 UU PDP).
   - Mitigasi risiko keamanan OWASP Top 10 (SQL Injection, CSRF, XSS, proteksi unggah Excel/sertifikat).
   - Kontrol akses berbasis peran (RBAC) dan prosedur pelaporan kerentanan keamanan siber.

> **Catatan Pengembang & AI**: Selalu rujuk **[AGENTS.md](file:///c:/Users/achma/.gemini/antigravity-ide/scratch/manajemen-talenta-karyawan/AGENTS.md)** sebagai panduan kontekstual saat melakukan vibe coding.

---

## 🚀 Tech Stack

- **Framework**: Laravel 11.x (PHP 8.2+)
- **Frontend / Styling**: Tailwind CSS, Alpine.js, Blade Components
- **Interactivity**: Laravel Livewire
- **Database**: MySQL / MariaDB

---

## 🛠️ Menjalankan Aplikasi Secara Lokal

1. Salin file konfigurasi environment:
   ```bash
   cp .env.example .env
   ```
2. Pasang dependensi PHP dan Node:
   ```bash
   composer install
   npm install
   ```
3. Generate application key & migrasi database:
   ```bash
   php artisan key:generate
   php artisan migrate --seed
   ```
4. Jalankan server pengembangan:
   ```bash
   npm run dev
   php artisan serve
   ```
