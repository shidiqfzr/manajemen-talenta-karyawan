# Security Policy & Data Governance
## PT Perkebunan Nusantara IV Regional V (Kalimantan)
### Sistem Informasi Manajemen Talenta & Formasi Pegawai

Dokumen ini mendefinisikan kebijakan keamanan informasi, tata kelola perlindungan data pribadi (UU PDP), serta prosedur mitigasi risiko keamanan siber pada aplikasi Sistem Manajemen Talenta & Formasi Pegawai PTPN IV Regional V.

---

## 1. Versi yang Didukung (Supported Versions)

Pembaruan keamanan hanya diberikan untuk versi aktif berikut:

| Versi Aplikasi | Laravel Core | PHP Runtime | Status Keamanan |
|---|---|---|---|
| **v1.x (Main Branch)** | `11.x` / `13.x` | `8.2+` / `8.3+` / `8.5+` | :white_check_mark: Didukung Penuh (Active Patches) |
| **< v1.0.0 (Legacy)** | `<= 10.x` | `<= 8.1` | :x: Tidak Didukung |

---

## 2. Klasifikasi & Perlindungan Data Pegawai (UU PDP & BUMN Compliance)

Merujuk pada **Undang-Undang No. 27 Tahun 2022 tentang Perlindungan Data Pribadi (UU PDP)** serta regulasi privasi data Kementerian BUMN:

### 2.1 Kategori Data Sangat Rahasia (Confidential)
1. **Data Identitas Pribadi**: NIK (Nomor Induk Karyawan), NIK KTP, tanggal lahir, alamat tinggal, susunan keluarga, dan data rekening/finansial.
2. **Data Evaluasi & Potensi Eksekutif**: Skor SMKBK (Kinerja), Skor CLI (Potensi), Kategori 9-Box Grid, dan hasil asesmen kompetensi holding.
   - *Kebijakan*: Hanya dapat diakses oleh Admin SDM Regional Office dan Direksi yang berwenang. Tidak boleh ditampilkan pada portal umum pegawai tanpa enkripsi hak akses.

### 2.2 Kategori Data Operasional Terbatas (Restricted)
1. **Data Formasi & Jabatan**: Nama, jabatan, unit kerja penempatan, golongan, masa kerja, dan riwayat mutasi.
   - *Kebijakan*: Dapat diakses oleh HR Unit/Kebun dan atasan langsung untuk kebutuhan operasional harian.

---

## 3. Kontrol Akses & Autentikasi (RBAC & Tenant Isolation)

1. **Role-Based Access Control (RBAC)**:
   - Akses rute `/admin/*` diisolasi ketat oleh middleware `auth` dan `role:admin`.
   - Modul modifikasi data (`store`, `update`, `destroy`) wajib memiliki verifikasi peran ganda untuk aksi berisiko tinggi.
2. **Pencegahan Brute-Force Login**:
   - Rute otentikasi login wajib dilindungi oleh rate-limiter:
   ```php
   // Maksimal 5 percobaan gagal per menit
   RateLimiter::for('login', function (Request $request) {
       return Limit::perMinute(5)->by($request->ip());
   });
   ```
3. **Penyimpanan Kredensial**:
   - Password disimpan menggunakan algoritma hashing standar industri (`bcrypt` dengan work factor minimal 12).

---

## 4. Mitigasi Risiko Aplikasi (OWASP Top 10 Standards)

Sistem menerapkan proteksi berlapis sesuai standar keamanan OWASP:

### 4.1 SQL Injection Prevention
- Seluruh interaksi database **wajib menggunakan Eloquent ORM atau Query Builder dengan parameter binding**:
  ```php
  // Aman (Parameter Binding)
  Employee::where('nik', $request->nik)->first();

  // DILARANG KERAS (Raw SQL Concatenation)
  DB::select("SELECT * FROM employees WHERE nik = '$request->nik'");
  ```

### 4.2 Cross-Site Request Forgery (CSRF)
- Seluruh formulir HTTP POST, PUT, PATCH, dan DELETE wajib menyertakan token validasi `@csrf` atau token Livewire session.
- Akses API atau webhook harus menggunakan autentikasi token bearer bertanda tangan kriptografis.

### 4.3 Cross-Site Scripting (XSS)
- Seluruh output data pengguna pada Blade template wajib menggunakan syntax auto-escaping default `{{ $data }}`.
- Penggunaan unescaped raw syntax `{!! $data !!}` **dilarang keras** kecuali untuk konten statis internal yang telah disanitasi secara eksplisit.

### 4.4 Proteksi Unggah Berkas (Excel & Sertifikat)
- Setiap unggahan file (impor template Excel pegawai atau unggah sertifikat pelatihan) wajib divalidasi MIME-type dan ukurannya:
  ```php
  $request->validate([
      'file' => 'required|file|mimes:xlsx,xls|max:10240', // Max 10MB
      'sertifikat' => 'nullable|file|mimes:pdf,jpg,png|max:5120', // Max 5MB
  ]);
  ```
- File yang diunggah disimpan di disk privat (`storage/app/private/`) dan hanya dapat diunduh melalui stream controller berotorisasi, bukan direktori publik langsung.

### 4.5 Mass-Assignment Protection
- Seluruh Model Eloquent wajib mendefinisikan array `$fillable` secara eksplisit guna mencegah penulisan kolom tidak sah melalui manipulasi request body.

---

## 5. Audit Trail & Pencatatan Log (Auditability)

Untuk menjamin akuntabilitas tata kelola BUMN:
1. **Pencatatan Riwayat Mutasi**: Setiap perpindahan jabatan, promosi, atau demosi wajib mencatat histori di tabel `job_histories` dengan atribut `nomor_sk`, `tanggal_sk`, `tmt_awal`, dan user pelaksana.
2. **Log Kesalahan & Exception**:
   - Konfigurasi `APP_DEBUG=false` wajib aktif di lingkungan staging dan production agar detail stack trace tidak terekspos ke publik.
   - Seluruh kegagalan transaksi dan anomali sistem dicatat dalam rotasi file log di `storage/logs/laravel.log`.

---

## 6. Prosedur Pelaporan Kerentanan (Reporting a Vulnerability)

Jika Anda menemukan potensi celah keamanan (vulnerability) pada repositori ini:

1. **JANGAN** membuat *Public Issue* atau mempublikasikannya secara terbuka di forum publik.
2. Kirimkan laporan rinci melalui email ke:
   - **Tim Keamanan Informasi**: `security@ptpn4.co.id` / `sdm.regional5@ptpn4.co.id`
3. Sertakan informasi berikut dalam laporan Anda:
   - Deskripsi kerentanan dan potensi dampaknya.
   - Langkah-langkah reproduksi (Proof of Concept / PoC).
   - Saran perbaikan (jika ada).
4. **SLA Respons & Tindakan**:
   - Tim akan mengonfirmasi penerimaan laporan dalam kurun waktu **24–48 jam kerja**.
   - Tim akan memverifikasi temuan dan menerbitkan patch perbaikan dalam **7–14 hari kerja**.
   - Mohon memberikan waktu bagi tim untuk merilis patch perbaikan sebelum melakukan pengungkapan terkoordinasi (*coordinated disclosure*).
