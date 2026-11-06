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
   // Maksimal 5 percobaan gagal per menit; login berhasil tidak dihitung
   RateLimiter::for('login', function (Request $request) {
       return Limit::perMinute(5)->by($request->ip())->after(fn () => Auth::guest());
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

Impor pegawai tambahan: komit bersifat atomik (data dan jejak audit dalam satu transaksi), berkas yang dikomit wajib identik dengan berkas yang dipratinjau (SHA-256), satu token hanya dapat dikomit sekali, dan galat internal tidak diteruskan ke peramban. Berkas yang tidak dapat dibaca ditolak dengan pesan validasi dan dihapus dari disk privat.
Tata kelola impor: bila kebijakan persetujuan empat mata aktif, penyimpanan data pegawai memerlukan persetujuan admin lain (bukan pengaju) dan seluruh jalur simpan lain ditolak; pembatalan impor wajib beralasan dan tidak pernah menghapus `job_histories`. Rekaman untuk pembatalan (`employee_import_changes`: NIK, kondisi sebelum impor, tugas rangkap) berisi data pribadi sehingga seluruhnya terenkripsi, hanya dapat dibaca oleh proses pembatalan, dan dihapus setelah masa pembatalan berakhir (minimisasi data, UU PDP No. 27/2022). Berkas pengajuan tersimpan di disk privat dan dihapus saat keputusan, kedaluwarsa, atau penyimpanan. Pesan galat per baris pada ringkasan impor yang tersimpan permanen disamarkan (NIK menjadi `12****78`) sehingga riwayat impor tidak membocorkan NIK.

### 4.5 Mass-Assignment Protection
- Seluruh Model Eloquent wajib mendefinisikan array `$fillable` secara eksplisit guna mencegah penulisan kolom tidak sah melalui manipulasi request body.

---

## 5. Audit Trail & Pencatatan Log (Auditability)

Untuk menjamin akuntabilitas tata kelola BUMN:
1. **Pencatatan Riwayat Mutasi**: Setiap perpindahan jabatan, promosi, atau demosi wajib mencatat histori di tabel `job_histories` dengan atribut `nomor_sk`, `tanggal_sk`, `tmt_awal`, dan user pelaksana. Penempatan awal dan pembaruan data pegawai (termasuk hasil impor) tetap tercatat pada tabel yang sama dengan `sumber` `data_awal`/`pembaruan_data`/`impor`, tanpa nomor atau tanggal SK karena bukan SK Direksi; nomor SK tidak boleh dibuat oleh sistem (integritas audit BPK/Inspektorat).
2. **Riwayat Perubahan Master Formasi**: Setiap perubahan pada unit kerja, jabatan, dan kursi formasi dicatat pada tabel `master_audit_logs` (siapa, kapan, aksi, nama entitas saat itu, nilai lama dan baru, serta alasan). Pencatatan dilakukan dalam transaksi yang sama dengan perubahannya, bersifat *append-only* (model menolak ubah dan hapus), dan hanya menyimpan jumlah pegawai terdampak, bukan NIK atau nama pegawai (UU PDP). Riwayat dapat dibaca admin pada `/admin/master/riwayat`.
3. **Riwayat Rencana Pasokan MPP**: Setiap perubahan angka rencana pasokan (CKP Internal, Talent Scouting, RBB, Jabatan Prioritas Diisi) dicatat per sel pada `master_audit_logs` (aksi `mpp_supply.created`/`mpp_supply.updated`, nilai lama dan baru, alasan wajib) dalam transaksi yang sama dengan perubahannya, dan dapat dibaca pada layar riwayat master. Simpanan yang akan menimpa perubahan admin lain ditolak.
4. **Log Ekspor Data MPP**: Setiap unduhan Excel (.xlsx) matriks MPP dan rincian personil (memuat NIK dan nama) dicatat di log aplikasi dan pada `master_audit_logs` (aksi `mpp_access.export_matrix`/`mpp_access.export_personnel`) dengan pengguna, RM Band, bidang, indikator, tahun, posisi, dan jumlah baris. Setiap pembukaan rincian personil pada layar MPP juga dicatat (`mpp_access.view_personnel`) karena menampilkan NIK dan nama. Catatan pembukaan (`*_access.view_*`) tetap tersimpan sebagai jejak akses tetapi tidak ditampilkan pada layar Riwayat Perubahan, yang hanya memuat perubahan data dan pengunduhan. Isi berkas dan isi rincian (NIK, nama) tidak ikut dicatat (UU PDP).
4a. **Log Akses Pemegang Jabatan Master Formasi**: Setiap pembukaan daftar pemegang jabatan pada Master Formasi (memuat nama dan tanggal MBT/pensiun) dicatat pada `master_audit_logs` (aksi `seat_access.view_occupants`) dengan pengguna, unit, jabatan, RM Band, bidang, dan jumlah baris. Pembukaan pada jabatan tanpa pemegang tidak dicatat. Nama dan NIK pegawai tidak ikut dicatat (UU PDP); NIK tetap tidak tampil sebagai teks pada daftar.
5. **Riwayat Penutupan Periode MPP**: Perekaman draf, penutupan, dan pembukaan kembali periode bulanan dicatat pada `master_audit_logs` (aksi `mpp_period.captured`/`mpp_period.closed`/`mpp_period.reopened`, status dan revisi lama dan baru, catatan penutupan atau alasan) dalam transaksi yang sama dengan perubahannya. Alasan membuka kembali wajib (minimal 10 karakter). Perekaman otomatis oleh penjadwal (`hris:mpp-capture-period`) tercatat atas nama "Sistem". Ketiga aksi hanya tersedia bagi admin (`authorizeAdmin()` pada komponen MPP). Setiap unduhan CSV bulan ber-snapshot juga mencatat status periode dan revisi pada log ekspor (butir 4).
6. **Minimisasi Data Snapshot Periode MPP**: Salinan periode (`mpp_periods`, `mpp_period_members`) hanya menyimpan angka matriks serta NIK, jabatan, unit kerja, golongan, jalur masuk, tanggal penempatan, dan tanggal MBT per sel; nama, tanggal lahir, dan data pribadi lain tidak disalin. Nama, tanggal pensiun, dan status pada drilldown diambil dari data pegawai saat ini sehingga mengikuti kontrol akses dan penghapusan data pegawai. Snapshot dipertahankan sebagai arsip resmi bulanan (tidak ada pembersihan otomatis untuk tabel ini) dan hanya berubah lewat buka kembali yang tercatat.
7. **Log Kesalahan & Exception**:
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
