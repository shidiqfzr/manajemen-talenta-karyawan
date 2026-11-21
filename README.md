# Sistem Manajemen Talenta & Formasi Karyawan
## PT Perkebunan Nusantara IV Regional V (Kalimantan)

Aplikasi Human Resources Information System (HRIS) dan perencanaan formasi talenta (*Man Power Planning*) berbasis web yang dirancang khusus untuk mengelola data kepegawaian, formasi jabatan pimpinan (RM Band), riwayat mutasi, evaluasi kinerja 9-box, serta monitoring pensiun di lingkungan PT Perkebunan Nusantara IV Regional V.

---

## 📚 Dokumen Acuan Tunggal (4 Pilar SSOT)

Proyek ini dipandu oleh empat pilar dokumentasi arsitektur utama:

1. **[DOMAIN.md](DOMAIN.md)** — **Business Domain & Enterprise Rules**
   - Kamus data dan spesifikasi entitas (`Employee`, `JobHistory`, `Evaluation`, `Training`, `JobVacancy`).
   - Struktur 47 unit kerja (Regional Office, Kalbar, Kalselteng, Kaltim).
   - Regulasi SDM BUMN: Strata Karpim & Karpel, RM Band (`RM-1`, `RM-2`, `RM-3`), 4 Bidang Fungsional (`KEU`, `TAN`, `TEK`, `UMU`).
   - Rumus otomatis Pensiun (56 tahun) & Masa Bebas Tugas (MBT, 6 bulan sebelum tanggal pensiun).
   - Aturan transaksi mutasi (straight vs swap transfer, auto-chain vacancy).
   - Service layer: `ManPowerPlanningService`, `MutasiService`, `JobVacancyService`.

2. **[DESIGN.md](DESIGN.md)** — **Design System & UI/UX Guidelines**
   - Panduan UI/UX berbasis TALL Stack (Tailwind CSS, Alpine.js, Laravel, Livewire).
   - Design tokens: Palet warna "Perkebunan Modern" (hijau `#2F8250`, earth tones, semantic badges).
   - Komponen antarmuka enterprise, dialog konfirmasi modal, dan live impact analysis panel.

3. **[ENGINEERING.md](ENGINEERING.md)** — **Engineering Standards, SDLC & Testing Strategy**
   - Alur Agile Feature-Driven SDLC 5 tahap.
   - Pola Service Layer, transaksi atomik database (`DB::transaction()`), dan standar Livewire components.
   - Strategi pengujian otomatis: PHPUnit 11 (Unit Tests formula HR & Feature Tests alur mutasi) dan linter Laravel Pint.

4. **[SECURITY.md](SECURITY.md)** — **Security Policy & Data Governance**
   - Standar kepatuhan privasi data karyawan (UU No. 27/2022 UU PDP).
   - Mitigasi risiko keamanan OWASP Top 10 (SQL Injection, CSRF, XSS, proteksi unggah Excel/sertifikat).
   - Kontrol akses berbasis peran (RBAC) dan prosedur pelaporan kerentanan keamanan siber.

> **Catatan Pengembang & AI**: Selalu rujuk **[AGENTS.md](AGENTS.md)** sebagai panduan kontekstual saat melakukan vibe coding.

---

## 🚀 Tech Stack

- **Framework**: Laravel 13.x (PHP 8.4+)
- **Frontend / Styling**: Tailwind CSS, Alpine.js, Blade Components
- **Interactivity**: Laravel Livewire
- **Database**: SQLite (pengembangan lokal) dan MySQL (produksi)

---

## 🛠️ Menjalankan Aplikasi Secara Lokal

### Prasyarat

- **PHP 8.4** dengan ekstensi `mbstring`, `intl`, `gd`, `zip`, `bcmath`, `xml`, `fileinfo`, `pdo_sqlite`, dan `sqlite3` (periksa dengan `php -m`).
- **Composer 2**, **Node.js 22** (versi di `.nvmrc`), dan **Git**.
- **Windows**: aktifkan ekstensi dengan menghapus tanda `;` di depan baris `extension=...` pada `php.ini`. Bila memakai Laragon, pilih PHP 8.4 dan pastikan `php -v` menampilkan 8.4.

### Mulai cepat

```bash
git clone https://github.com/shidiqfzr/palm-hris.git
cd palm-hris
composer setup
composer dev
```

`composer setup` memasang dependensi PHP dan Node, menyalin `.env.example` ke `.env`, membuat kunci aplikasi (hanya bila belum ada), membuat berkas SQLite, menjalankan `migrate --seed` (akun demo serta master unit dan formasi), lalu membangun aset. Aman dijalankan ulang, dan **menolak berjalan** bila `.env` berisi `APP_ENV=production`: perintah ini hanya untuk mesin pengembangan.

`composer dev` menjalankan server, antrean, log, dan Vite sekaligus. Di Windows panel log berhenti dengan galat karena Pail memerlukan ekstensi `pcntl`; bila `composer dev` ikut berhenti, jalankan tiga perintah ini di terminal terpisah (antrean wajib untuk menguji impor Excel):

```bash
php artisan serve
php artisan queue:listen --tries=1
npm run dev
```

Aplikasi berjalan di `http://127.0.0.1:8000`.

### Data contoh dan akun demo

`composer demo` mengisi basis data lokal yang masih kosong dengan sekitar 60 karyawan sintetis ("Karyawan Contoh NN") dan penilaian 9-box agar layar formasi, mutasi, pensiun, dan talenta terisi. Perintah ini menolak lingkungan produksi dan basis data yang sudah berisi karyawan (ENGINEERING.md §3.8).

Akun demo (hanya lokal; kredensialnya ada di kode sehingga dianggap publik): `admin` dan `admin2` dengan kata sandi `ptpn4reg5`, serta `user` dengan kata sandi `password`. Dua administrator memungkinkan persetujuan empat mata diuji secara lokal.

### Basis data

Lokal memakai SQLite (`database/database.sqlite`, dibuat otomatis). Produksi memakai MySQL. Untuk mencoba MySQL lokal, ubah isian `DB_*` pada `.env`.

### Pemeriksaan sebelum pull request

```bash
composer verify
```

Perintah ini menjalankan gerbang yang sama dengan CI: Pint, `composer audit`, PHPUnit, `npm audit`, dan uji JavaScript. Perubahan layar juga diuji di peramban: jalankan `npm run e2e:install` sekali, lalu `npm run e2e` (ENGINEERING.md §3.6).

### Produksi

Instalasi dan pembaruan produksi dilakukan oleh pemelihara (ENGINEERING.md §4). Akun awal produksi: isi `HRIS_ADMIN_*` pada `.env`, jalankan `php artisan db:seed --class=UserSeeder --force` (membuat satu administrator tanpa akun demo), lalu hapus `HRIS_ADMIN_PASSWORD` dari `.env`. Jangan menjalankan `composer setup`, `db:seed` penuh, atau `migrate:fresh` pada basis data produksi.

### Berkontribusi

Baca [CONTRIBUTING.md](CONTRIBUTING.md): cara membuat cabang dan commit, standar kode, aturan privasi data karyawan, dan cara melaporkan kerentanan.
