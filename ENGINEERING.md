# ENGINEERING.md — Engineering Standards, SDLC & Testing Strategy
## PT Perkebunan Nusantara IV Regional V (Kalimantan)
### Sistem Informasi Manajemen Talenta & Formasi Pegawai

> Dokumen ini adalah **acuan standar rekayasa perangkat lunak (Single Source of Truth for Engineering & QA)** yang memandu arsitektur teknis, standar implementasi kode, alur perencanaan (planning), dan strategi pengujian (testing) pada aplikasi HRIS & Formasi Talenta PTPN IV Regional V.
>
> Dokumen ini melengkapi:
> - [DOMAIN.md](file:///c:/Users/achma/.gemini/antigravity-ide/scratch/manajemen-talenta-karyawan/DOMAIN.md) (Logika Bisnis & Kamus Data)
> - [DESIGN.md](file:///c:/Users/achma/.gemini/antigravity-ide/scratch/manajemen-talenta-karyawan/DESIGN.md) (Design System & UI/UX Guidelines)
> - [SECURITY.md](file:///c:/Users/achma/.gemini/antigravity-ide/scratch/manajemen-talenta-karyawan/SECURITY.md) (Kebijakan Keamanan & Kepatuhan Data)

---

## 1. Alur Perencanaan & Siklus Pengembangan (SDLC Workflow)

Pengembangan modul atau penambahan fitur di repositori ini mengikuti alur **Agile Feature-Driven SDLC** 5 tahap:

```
┌────────────────────────────────────────────────────────────────────────┐
│                        AGILE FEATURE-DRIVEN SDLC                       │
└───────────────────────────────────┬────────────────────────────────────┘
                                    │
    ┌───────────────────────────────┼───────────────────────────────┐
    ▼                               ▼                               ▼
┌──────────────┐            ┌──────────────┐            ┌──────────────┐
│  TAHAP 1     │            │  TAHAP 2     │            │  TAHAP 3     │
│ Domain &     │───►───►───►│ Service &    │───►───►───►│ UI &         │
│ Design Check │            │ Model Layer  │            │ Livewire Int │
└──────────────┘            └──────────────┘            └──────────────┘
                                                                │
                                    ┌───────────────────────────┘
                                    ▼
                            ┌──────────────┐            ┌──────────────┐
                            │  TAHAP 4     │            │  TAHAP 5     │
                            │ Automated    │───►───►───►│ Security &   │
                            │ Test & Pint  │            │ Audit Verify │
                            └──────────────┘            └──────────────┘
```

### Tahap 1: Verifikasi Domain & Desain (Pre-Development Alignment)
- Konsultasikan kebutuhan dengan [DOMAIN.md](file:///c:/Users/achma/.gemini/antigravity-ide/scratch/manajemen-talenta-karyawan/DOMAIN.md) (apakah melibatkan formula pensiun, RM Band, bidang fungsional, atau alur mutasi).
- Periksa kesesuaian komponen visual dengan [DESIGN.md](file:///c:/Users/achma/.gemini/antigravity-ide/scratch/manajemen-talenta-karyawan/DESIGN.md) (palet warna token, card styling, loading indicator).

### Tahap 2: Implementasi Service & Model (Business Logic First)
- Bangun logika bisnis murni di dalam `App\Services\` (bukan di dalam Controller atau Blade template).
- Pastikan relasi Eloquent terdefinisi dengan foreign key yang tepat, casting tipe data (`date`, `integer`), dan guarded/fillable attributes.
- Semua operasi multi-tabel (seperti proses mutasi yang mengubah unit dan mencatat riwayat) **wajib dibungkus dalam `DB::transaction()`**.

### Tahap 3: Integrasi UI & Komponen Livewire
- Bangun Controller atau komponen Livewire (`App\Livewire\`).
- Sertakan feedback interaksi pengguna: status loading eksplisit (`wire:loading`), skeleton loader, serta toast notification sukses/gagal.
- Pastikan validasi form dilakukan di Form Request atau metode `rules()` Livewire dengan pesan Bahasa Indonesia yang komunikatif.

### Tahap 4: Pengujian Otomatis & Standarisasi Kode (QA & Linting)
- Tulis Unit Test untuk setiap service kalkulasi bisnis baru.
- Tulis Feature Test untuk endpoint controller atau interaksi Livewire kritis.
- Jalankan formatting kode otomatis dengan Laravel Pint:
  ```bash
  ./vendor/bin/pint
  ```
- Jalankan seluruh test suite dan pastikan hijau (passing):
  ```bash
  php artisan test
  ```

### Tahap 5: Verifikasi Keamanan & Audit Trail
- Pastikan otorisasi akses (middleware `auth`, `role:admin` atau Policy) sudah aktif.
- Pastikan input disanitasi dari potensi XSS/SQL Injection.
- Cek apakah aksi manipulasi data telah dicatat dalam log audit riwayat.

---

## 2. Standar Arsitektur & Implementasi Kode (Coding Standards)

### 2.1 Service Layer Pattern (Mandatory)
Dilarang menempatkan kalkulasi matematika, klasifikasi data kepegawaian, atau mutasi state yang kompleks langsung di Controller.
- **Service Utama**:
  - `App\Services\ManPowerPlanningService`: Kalkulasi formasi standar vs realisasi, determinasi RM Level, dan penentuan Bidang Fungsional.
  - `App\Services\MutasiService`: Simulasi dampak mutasi (`analyzeTarget`), eksekusi straight/swap mutasi, dan pencatatan `job_histories`.
  - `App\Services\JobVacancyService`: Deteksi formasi kosong manajerial aktif.
  - `App\Services\TrainingExportService`: Ekspor laporan dan surat tugas.
  - `App\Services\EmployeeExportService`: Orkestrasi ekspor data kepegawaian berstandar UU PDP No. 27/2022, sanitasi parameter filter, penyematan metadata/print footer rahasia, dan pencatatan audit trail.
  - `App\Services\EmployeeImportService`: Validasi pra-impor, preview dry-run, dan jejak audit impor tanpa data pribadi.
  - `App\Services\EmployeeImportGovernanceService`: Persetujuan empat mata (maker-checker), pembatalan (undo) impor, dan pembersihan pengajuan/rekaman kedaluwarsa; kebijakan diatur pada `config/hris.php`.
  - `App\Services\KepegawaianPolicy`: Satu-satunya sumber Batas Usia Pensiun (56), MBT (T-1), dan rentang grade per strata Karpim/Karpel. Dilarang menulis ulang angka-angka ini di model, validator, atau layar.

### 2.2 Eloquent Models & Database Practices
1. **Format NIK**:
   - Selalu diperlakukan sebagai `string` 8 karakter. Jangan pernah menggunakan cast `integer` pada NIK untuk menghindari terpotongnya angka 0 di depan.
   ```php
   // Benar
   $table->string('nik', 8)->primary();
   // Hindari
   $table->integer('nik')->primary();
   ```
2. **Kesesuaian Tipe Tanggal**:
   - Selalu gunakan format standar ISO `Y-m-d` dan tambahkan ke properti `$casts`:
   ```php
   protected $casts = [
       'tanggal_lahir'    => 'date',
       'tanggal_pensiun'  => 'date',
       'tanggal_mbt'      => 'date',
       'tmt_bekerja'      => 'date',
       'tmt_unit_kerja'   => 'date',
   ];
   ```
3. **Atomic Operations**:
   - Setiap operasi yang mengubah lebih dari satu tabel wajib memakai transaksi database:
   ```php
   use Illuminate\Support\Facades\DB;

   DB::transaction(function () use ($data) {
       // Operasi mutasi pegawai + pencatatan riwayat + penyesuaian vacancy
   });
   ```

### 2.3 Livewire Component Standards
- Setiap komponen tabel data Livewire **wajib** memakai trait `App\Livewire\Concerns\WithDataTable` dan komponen `x-admin.table.*` (DESIGN.md §6, §7.5). Modul hanya mendeklarasikan `baseQuery()`, `columns()` (`Column`), `filters()` (`Filter`), `bulkActions()` (`BulkAction`), `defaultSort()`, dan `tableLabel()`. **Dilarang** menulis ulang `sortBy`, `updatingSearch`, `updatingPerPage`, atau `orderBy($this->sortField)` di modul: urutan, pencarian, filter, dan jumlah baris per halaman divalidasi di trait.
  - Debounce pencarian 300ms dan batas 100 karakter disediakan toolbar (`wire:model.live.debounce.300ms="search"`); jangan membuat kolom pencarian sendiri.
  - URL query string: `search`, `sort`, `direction`, `per_page` dikelola trait; properti filter modul memakai `#[Url(as: '...', except: '')]`. Tabel sematan (beberapa tabel dalam satu halaman) menonaktifkan sinkronisasi URL (`syncsUrl()` false) dan memakai `paginationName()` yang unik.
  - Aksi massal dan ekspor: izin Gate diperiksa pada setiap panggilan sebelum ada query, aksi massal dijalankan dalam `DB::transaction()` bersama catatan audit, dan keduanya tercatat lewat `MasterAuditLog` tanpa kata kunci pencarian. Ekspor hanya Excel dengan batas 10.000 baris; sel berawalan `=`, `+`, `-`, `@` dinetralkan.
  - Properti publik Livewire dapat diubah klien: ID induk tabel (mis. `employeeId`) **wajib** `#[Locked]`, dan metode yang mengembalikan Builder atau penangan aksi massal (`bulk<Nama>`) **wajib** `protected`.
  - Pengujian: perilaku trait di `WithDataTableTest`, komponen di `TableComponentsTest`, dan satu kelas uji per tabel modul.

### 2.4 Prinsip DRY (Don't Repeat Yourself) & Reusability
Prinsip DRY adalah fondasi utama arsitektur aplikasi ini. Dilarang keras melakukan penyalinan (copy-paste) kode secara masif baik di Backend maupun Frontend.
- **Backend (Single Source of Truth):** Segala bentuk kalkulasi (Pensiun, MBT, RM Level) wajib dipusatkan di **Service Layer**. Tidak boleh ada kalkulasi duplikat di Controller, Command, atau Livewire.
- **Frontend (UI Components):** Elemen visual yang digunakan lebih dari satu kali (seperti *Page Header*, *Card*, *Combobox*, *Modal*) **wajib** diabstraksi menjadi **Blade Components** (contoh: `<x-admin.page-header>`) di `resources/views/components/`. Hal ini menjamin keseragaman UI dan mempermudah perubahan tema secara terpusat sesuai `DESIGN.md`.

---

## 3. Strategi Pengujian (Testing & QA Standards)

Proyek ini menggunakan **PHPUnit 11** untuk memastikan keandalan sistem enterprise.

```
tests/
├── Unit/             # Pengujian logika murni formula HR, Carbon math, mapping bidang
└── Feature/          # Pengujian integrasi endpoint, Livewire tables, alur mutasi, auth
```

### 3.1 Unit Testing (Formula & Business Logic)
Setiap perubahan pada aturan bisnis HR wajib diverifikasi dengan Unit Test:
1. **Uji Formula Pensiun & MBT**:
   - Pastikan pegawai yang lahir pada tanggal tertentu memiliki `tanggal_pensiun` tepat pada awal bulan usia 56 tahun.
   - Pastikan `tanggal_mbt` jatuh tepat 1 tahun sebelum pensiun.
2. **Uji Penentuan RM Band & Bidang**:
   - Memastikan klasifikasi `determineRmLevel()` dan `determineBidang()` menghasilkan nilai yang deterministik untuk berbagai kombinasi jabatan dan unit kerja.

Contoh Unit Test:
```php
public function test_pensiun_dan_mbt_calculation(): void
{
    $tanggalLahir = Carbon::parse('1980-05-15');
    $pensiun = Carbon::parse($tanggalLahir)->addYears(56)->startOfMonth();
    $mbt = Carbon::parse($pensiun)->subYear();

    $this->assertEquals('2036-06-01', $pensiun->toDateString());
    $this->assertEquals('2035-06-01', $mbt->toDateString());
}
```

### 3.2 Feature & Integration Testing
Pengujian alur kerja mencakup:
1. **Otorisasi & Role Access**:
   - Tamu (guest) diarahkan ke `/login`.
   - User biasa tidak dapat mengakses modul admin formasi/mutasi (`/admin/*`).
   - Admin dapat mengakses direktori dan dashboard formasi.
2. **Alur Mutasi Atomik (`MutasiServiceTest`)**:
   - Pengujian **Straight Transfer**: Memastikan lowongan terbuka di posisi lama.
   - Pengujian **Swap Transfer**: Memastikan dua pegawai bertukar jabatan secara simultan tanpa meninggalkan formasi bocor (*zero net vacancy*).
3. **Import & Export Excel**:
   - Pengujian unggah template Excel data pegawai dengan validasi baris gagal/berhasil.

### 3.3 Menjalankan Pengujian
```bash
# Menjalankan seluruh test suite
php artisan test

# Menjalankan pengujian fitur spesifik
php artisan test --filter=ManPowerPlanningTest

# Menjalankan pengujian dengan rincian stop-on-failure
php artisan test --stop-on-failure
```

### 3.4 Standar Kerapihan Kode (Code Quality Gate)
Sebelum mengajukan pull request atau menggabungkan kode:
```bash
# Cek dan perbaiki style kode otomatis sesuai standar PSR-12 & Laravel
./vendor/bin/pint
```

### 3.5 SUT Testing Principle
Patuhi prinsip **One Test Class per System Under Test (SUT)**. Dilarang memecah pengujian satu class/komponen ke beberapa file terpisah tanpa alasan yang sangat kuat. Semua skenario uji (termasuk edge-cases dan integrasi) untuk satu fitur/komponen wajib disatukan dalam satu file uji utama miliknya (contoh: seluruh pengujian komponen `MutasiProcess` harus berada utuh di dalam `MutasiProcessTest.php`).

### 3.6 Evaluasi UI/UX di Browser (Playwright E2E)
Perubahan pada form atau layar yang dilihat pengguna wajib diperiksa di browser nyata, bukan hanya lewat uji PHP. Uji PHP membaca HTML, sedangkan perilaku Alpine/Livewire, penjajaran, fokus keyboard, dan aksesibilitas hanya terlihat di browser.

```bash
npm install
npm run e2e:install   # sekali: mengunduh Chromium
npm run build         # aset yang diuji adalah hasil build
npm run e2e           # menjalankan seluruh pengujian E2E
```

- **Basis data terisolasi**: `tests/e2e/support/serve.mjs` membuat SQLite sementara (`tests/e2e/.tmp/`), menjalankan `migrate:fresh --seed`, lalu menyalakan server pada port 8765. Basis data pengembangan tidak tersentuh.
- **Sesi**: `tests/e2e/auth.setup.js` login satu kali; seluruh pengujian memakai sesi tersebut.
- **Cakupan minimum per fitur** (mengacu DESIGN.md §16): kosakata dan nilai baku dari SSOT, penjajaran/hierarki, nilai turunan dari Master Formasi, state error, tidak ada scroll horizontal pada 375px, 1366px, dan 1920px, serta pindai aksesibilitas WCAG 2.1 A/AA (axe-core) tanpa pelanggaran `serious`/`critical`.
- **Prinsip SUT (§3.5)** berlaku: satu berkas `*.spec.js` per layar/fitur, misalnya `employee-create.spec.js`.
- **Jangan** memakai `waitForLoadState('networkidle')`; tunggu elemen atau kondisi yang relevan.
- Server pengembangan PHP bersifat satu-utas sehingga suite berjalan beberapa menit; hasil, tangkapan layar, dan trace kegagalan ada di `test-results/` dan `playwright-report/` (tidak di-commit).

---

## 4. Checklist Rilis & Deployment (Release Verification)

Sebelum merilis perubahan ke lingkungan produksi:
- [ ] Semua migrasi database baru berjalan lancar (`php artisan migrate --dry-run`).
- [ ] Semua test suite lolos (`php artisan test` 100% green).
- [ ] Format kode lolos linter (`./vendor/bin/pint --test`).
- [ ] Tidak ada hardcoded credentials atau debug helper (`dd()`, `dump()`, `ray()`).
- [ ] Konfigurasi cache diperbarui (`php artisan config:cache`, `php artisan route:cache`, `php artisan view:cache`).
- [ ] File log audit mencatat aktivitas dengan benar tanpa error exception.
