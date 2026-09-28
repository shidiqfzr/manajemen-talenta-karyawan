# DESIGN.md — Design System & UX Guidelines
## Human Resources Information System (HRIS) PT. Perkebunan Nusantara

> Dokumen ini adalah **acuan desain tunggal (single source of truth for UI/UX)** untuk pengembangan HRIS PTPN menggunakan **TALL Stack** (Tailwind CSS, Alpine.js, Laravel, Livewire) di Antigravity IDE. Gunakan dokumen ini berdampingan dengan [DOMAIN.md](file:///c:/Users/achma/.gemini/antigravity-ide/scratch/manajemen-talenta-karyawan/DOMAIN.md) (single source of truth untuk aturan bisnis, data dictionary, dan struktur organisasi) sebagai konteks/prompt dasar setiap kali melakukan vibe coding agar hasil UI dan logika sistem konsisten, tidak generik, dan setara standar industri enterprise (Workday, SAP SuccessFactors, Mekari Talenta).

---

## 1. Tujuan & Filosofi Desain

HRIS ini digunakan oleh populasi pengguna yang sangat beragam — dari **pekerja lapangan di kebun/afdeling** yang mengakses lewat HP dengan koneksi terbatas, hingga **direksi holding** yang membutuhkan dashboard analitik strategis. Desain harus:

1. **Jelas sebelum indah (clarity over decoration).** Data HR (gaji, cuti, kinerja) adalah data sensitif dan berdampak langsung ke penghidupan karyawan — hierarki visual, status, dan angka harus tidak ambigu.
2. **Konsisten lintas modul.** Satu tombol "Ajukan" harus terlihat & berperilaku sama di modul Cuti, Reimbursement, maupun Rekrutmen.
3. **Berperan ganda: enterprise & lapangan.** Bukan sekadar "SaaS dashboard generik" — harus mengakomodasi konteks perkebunan (struktur Kebun/Afdeling/Mandor, kategori pekerja PKWT/harian lepas/musiman, absensi berbasis lokasi).
4. **Dapat diakses (accessible).** Kontras cukup, target sentuh besar untuk pengguna lapangan, dan dapat dioperasikan dengan keyboard.
5. **Cepat dirasakan (performant by design).** Karena berbasis Livewire, UI harus dirancang dengan status loading/skeleton yang eksplisit, bukan halaman yang terasa "diam" saat request server berjalan.

Hindari default tampilan "generated AI dashboard" (kartu rounded seragam dengan shadow abu-abu yang sama, gradient dekoratif, label ALL-CAPS bertaburan, badge emoji berlebihan). Setiap elemen visual harus punya alasan fungsional.

---

## 2. Pengguna & Konteks Penggunaan

| Persona | Perangkat Utama | Kebutuhan Kunci |
|---|---|---|
| **Pekerja Lapangan** (harian lepas, musiman, karyawan afdeling) | Mobile (Android low-end), sering offline-prone | Absensi cepat (GPS/foto), lihat slip gaji, ajukan cuti, 1 tangan, teks besar |
| **Karyawan Kantor (ESS)** | Desktop & Mobile | Self-service: profil, cuti, absensi, payslip, training |
| **Atasan Langsung / Mandor / Asisten Kebun (MSS)** | Mobile & Desktop | Approval cepat, lihat tim, absensi anak buah |
| **HR Admin / HRBP (per Kebun/Unit)** | Desktop | Kelola data induk, proses payroll, rekrutmen, administrasi |
| **HR Corporate / Kantor Pusat** | Desktop | Analitik lintas unit kebun, kebijakan, konsolidasi |
| **Direksi / Manajemen Puncak** | Desktop & Tablet | Dashboard eksekutif, ringkasan strategis, bukan input data |
| **Super Admin / IT** | Desktop | Konfigurasi sistem, hak akses, integrasi |

**Implikasi desain:** setiap layar harus dirancang **mobile-first untuk alur transaksional** (absensi, approval, self-service) dan **desktop-first untuk alur analitik & administratif** (payroll processing, laporan, konfigurasi master data).

---

## 3. Arsitektur Informasi — Modul (Setara Workday/SuccessFactors)

Struktur navigasi utama berbasis modul, dengan penyesuaian struktur organisasi perkebunan (**Holding → Wilayah/Direktorat → Kebun/Unit → Afdeling → Mandor → Pekerja**):

1. **Core HR (Employee Central)** — data induk karyawan, struktur organisasi multi-level (Holding/Kebun/Afdeling), riwayat jabatan & mutasi, dokumen kepegawaian.
2. **Recruitment & Onboarding (ATS)** — permintaan tenaga kerja (PKWT/tetap/musiman), pipeline kandidat (kanban), seleksi, offer letter, onboarding checklist.
3. **Time & Attendance** — absensi berbasis GPS/geofence untuk lokasi kebun, shift kerja, lembur, rekap kehadiran per afdeling.
4. **Leave Management** — pengajuan & approval cuti berjenjang, saldo cuti, kalender tim.
5. **Payroll & Benefits** — komponen gaji (termasuk premi panen/borongan bila relevan), BPJS, PPh 21, slip gaji digital, simulasi.
6. **Performance Management** — goal setting (OKR/KPI), penilaian berjenjang, kalibrasi, 360 feedback.
7. **Learning & Development** — katalog pelatihan, sertifikasi (K3, kompetensi teknis kebun), tracking progress.
8. **Succession & Career Planning** — talent pool, matriks 9-box, rencana suksesi posisi kritis (Manajer Kebun, Asisten Kepala).
9. **Employee/Manager Self-Service (ESS/MSS)** — hub personal: profil, payslip, cuti, absensi, approval inbox.
10. **Analytics & Reporting** — dashboard headcount, turnover, biaya tenaga kerja per kebun, produktivitas.
11. **Administration & Settings** — manajemen hak akses (RBAC per Kebun/Unit), workflow approval, master data (jabatan, grade, lokasi kebun).

> Saat vibe coding satu modul, selalu rujuk ke **nomor modul di atas** agar Antigravity IDE memahami konteks & hierarki data (mis. "Modul 3 harus tahu struktur Afdeling dari Modul 1").

---

## 4. Design Tokens

### 4.1 Palet Warna — "Perkebunan Modern" (Hijau & Earth Tone)

Warna dipilih agar terasa korporat-institusional (bukan startup playful), terinspirasi kanopi daun, tanah, dan hasil bumi — namun tetap memenuhi kontras WCAG AA untuk teks & UI enterprise.

```
Primary (Hijau Daun — aksi utama, brand, navigasi aktif)
  primary-950: #0B2416
  primary-900: #123A22
  primary-800: #1B5330      <- utama untuk header/sidebar gelap
  primary-700: #256B3E
  primary-600: #2F8250      <- primary button default
  primary-500: #3E9A63
  primary-400: #66B586
  primary-300: #9BD1B0
  primary-200: #C9E8D5
  primary-100: #E7F5EC
  primary-50 : #F3FAF6

Secondary (Earth / Khaki — aksen sekunder, kartu highlight, ikon sekunder)
  earth-700: #6B5B3A
  earth-600: #8A7550
  earth-500: #A8916A   <- aksen sekunder / highlight
  earth-300: #D8C9A8
  earth-100: #F2ECDD

Neutral (UI chrome — teks, border, background)
  neutral-900: #1B1F1D   <- teks utama (bukan hitam pekat #000)
  neutral-700: #40473F
  neutral-500: #737A70
  neutral-300: #C3C9BE
  neutral-200: #DEE3D8
  neutral-100: #F1F3EE
  neutral-50 : #F8F9F6   <- background halaman
  white      : #FFFFFF   <- background kartu/panel

Semantic
  success: #2F8250 (pakai primary-600)
  warning: #B8860B  (amber earth, bukan kuning terang generik)
  danger : #C0392B  (merah bata, selaras earth tone)
  info   : #2E6E9E
```

**Konfigurasi Tailwind (`tailwind.config.js`):**
```js
theme: {
  extend: {
    colors: {
      primary: {
        50:'#F3FAF6',100:'#E7F5EC',200:'#C9E8D5',300:'#9BD1B0',
        400:'#66B586',500:'#3E9A63',600:'#2F8250',700:'#256B3E',
        800:'#1B5330',900:'#123A22',950:'#0B2416',
      },
      earth: { 100:'#F2ECDD',300:'#D8C9A8',500:'#A8916A',600:'#8A7550',700:'#6B5B3A' },
      surface: { 50:'#F8F9F6',100:'#F1F3EE',200:'#DEE3D8',300:'#C3C9BE',500:'#737A70',700:'#40473F',900:'#1B1F1D' },
    },
    fontFamily: {
      sans: ['"Plus Jakarta Sans"', 'ui-sans-serif', 'system-ui'],
    },
  }
}
```

**Aturan pemakaian warna:**
- Sidebar & top bar: `primary-800` (bukan hitam), teks putih.
- Aksi utama (Simpan, Ajukan, Setujui): `primary-600`, hover `primary-700`.
- Aksi destruktif (Hapus, Tolak): `danger`, selalu minta konfirmasi modal.
- Status "Menunggu Persetujuan": `warning` (badge earth-amber, bukan kuning saturasi tinggi).
- Jangan pernah pakai warna primary untuk body text panjang — gunakan `surface-900` di atas `surface-50`.

### 4.2 Tipografi

- **Font UI utama:** `Plus Jakarta Sans` (geometris, hangat, cocok nuansa institusional-modern; muat via Google Fonts/self-host, fallback `system-ui`).
- **Font data/tabular (opsional):** `Inter` untuk kolom angka/tabel besar (payroll, laporan) karena tabular figures-nya rapi — gunakan `font-variant-numeric: tabular-nums`.
- **Skala tipe** (berbasis rem, rasio ~1.2):

| Token | Ukuran | Pemakaian |
|---|---|---|
| `text-xs` | 12px | Label meta, caption, timestamp |
| `text-sm` | 14px | Body default di tabel & form padat |
| `text-base` | 16px | Body default halaman & ESS mobile |
| `text-lg` | 18px | Sub-judul kartu |
| `text-xl` | 20px | Judul section |
| `text-2xl` | 24px | Judul halaman |
| `text-3xl` | 30px | Angka besar dashboard (KPI) |

Berat: `font-semibold` untuk judul, `font-medium` untuk label, `font-normal` untuk body. **Hindari** all-caps untuk label kecuali kode singkat (mis. status "AKTIF" pada badge memang lazim, tapi jangan all-caps judul section).

### 4.3 Spacing, Radius, Shadow

- Spacing: skala default Tailwind (4px base) — konsisten `gap-4`/`p-4` sebagai default kartu, `gap-6` antar section.
- Radius: `rounded-lg` (8px) untuk kartu & input, `rounded-full` hanya untuk avatar/badge status, `rounded-md` (6px) untuk tombol. **Jangan** samakan radius kartu dengan radius tombol — beri perbedaan halus untuk hierarki.
- Shadow: gunakan elevasi tipis & jarang. `shadow-sm` untuk kartu di atas `surface-50`. Hindari shadow abu-abu tebal generik di semua elemen — cukup pada elemen yang benar "mengambang" (dropdown, modal, toast).

### 4.4 Ikonografi

Gunakan **Heroicons** (via package `blade-ui-kit/blade-heroicons`, native cocok dengan Blade/Livewire). Outline style untuk ikon navigasi/aksi umum, solid style untuk status/state aktif. Konsisten 1 set ikon di seluruh aplikasi — jangan campur dengan emoji atau icon set lain.

---

## 5. Layout & App Shell

```
Desktop (≥1024px)
┌─────────────────────────────────────────────┐
│ Topbar: Logo | Global Search | 🔔 | Avatar   │
├───────────┬─────────────────────────────────┤
│ Sidebar   │  Breadcrumb                      │
│ (per      │  ─────────────────────────────   │
│  modul,   │  Konten Halaman                  │
│  collaps- │  (Card / Table / Form)           │
│  ible)    │                                  │
│           │                                  │
└───────────┴─────────────────────────────────┘

Mobile (<768px) — prioritas ESS/absensi lapangan
┌─────────────────────┐
│ Topbar ringkas + 🔔 │
├─────────────────────┤
│ Konten (1 kolom)     │
│ Kartu aksi cepat     │
│ (Absen, Cuti, Slip)  │
├─────────────────────┤
│ Bottom Nav (4-5 item)│
└─────────────────────┘
```

- **Sidebar** collapsible (icon-only saat collapsed), grouping modul sesuai daftar Bagian 3, dengan indikator modul aktif memakai `primary-100` background + `primary-700` text + left-border 3px `primary-600`.
- **Topbar** menampung: global search (Alpine `x-data` untuk command palette ringan), notification bell dengan badge count (via Livewire polling tiap 30–60 detik), avatar dropdown (profil, ganti unit kerja bila multi-kebun, logout).
- **Container** max-width `1440px` untuk layar analitik/tabel lebar; `768px` untuk form panjang agar mudah dibaca (line length wajar).
- **Bottom navigation** khusus mobile ESS: maksimal 5 item (Beranda, Absen, Cuti, Slip Gaji, Profil) — ini yang paling sering dipakai pekerja lapangan.

---

## 6. Pola Komponen (Blade/Livewire Component Library)

Bangun sebagai **Blade Component** reusable di `resources/views/components/` dan **Livewire Component** untuk yang stateful, agar konsisten dan tidak duplikasi markup di setiap modul.

### 6.1 Tombol
```
<x-button variant="primary|secondary|ghost|danger" size="sm|md|lg" :loading="false">
```
- `primary`: solid `primary-600`, teks putih.
- `secondary`: outline `primary-600` di atas putih.
- `ghost`: tanpa border, untuk aksi tersier di dalam tabel.
- `danger`: solid `danger`, selalu memicu `x-modal` konfirmasi sebelum Livewire action dijalankan.
- State loading: tampilkan spinner inline + disable tombol via `wire:loading.attr="disabled"` — **jangan biarkan tombol bisa diklik ganda** (penting untuk aksi approval/payroll agar tidak duplikat transaksi).

### 6.2 Form & Input
- Semua input punya: label jelas, helper text opsional, pesan error inline (bukan alert generik di atas form), dan state fokus terlihat jelas (ring `primary-300`).
- Gunakan `wire:model.live.debounce.400ms` untuk field yang memicu pencarian/validasi real-time (mis. cek NIK duplikat), `wire:model.blur` untuk field form standar agar tidak membebani server tiap ketikan.
- Date picker, select searchable (mis. pilih Kebun/Afdeling dari ratusan opsi) → gunakan Alpine.js + Livewire combo (Alpine untuk UI dropdown lokal, Livewire untuk fetch data saat query berubah).
- Upload dokumen (KTP, ijazah, sertifikat K3) memakai pola **Livewire temporary file upload** dengan preview & progress bar eksplisit.
- Form panjang (data karyawan baru, rekrutmen) → pecah jadi **wizard multi-step** (lihat 6.6), jangan satu form raksasa.

### 6.3 Tabel Data
Tabel adalah komponen paling sering dipakai (daftar karyawan, riwayat absensi, payroll run). Standar wajib:
- Header sticky saat scroll vertikal.
- Kolom kunci (Nama, NIK, Status) sticky saat scroll horizontal pada tabel lebar.
- Sorting per kolom (indikator panah), filter per kolom di baris kedua header atau panel filter samping.
- Pagination Livewire bawaan (`WithPagination`), tampilkan "Menampilkan 1–20 dari 1.240 data".
- Bulk action (checkbox baris + action bar muncul saat ada yang dipilih — mis. "Setujui 12 pengajuan cuti").
- Status pakai **badge berwarna** (bukan teks polos): Aktif=primary, Menunggu=warning, Ditolak=danger, Selesai=neutral.
- Skeleton loading (baris abu-abu berdenyut) saat `wire:loading` pada perubahan filter/halaman — jangan biarkan tabel "kosong sekejap" tanpa indikasi.
- Tombol "Export" (Excel/PDF) konsisten posisinya di kanan-atas tabel di semua modul.

### 6.4 Kartu (Card)
- Kartu statistik dashboard: angka besar (`text-3xl font-semibold`) + label kecil + indikator tren (naik/turun dengan ikon panah & warna semantic, bukan hanya angka telanjang).
- Kartu aksi cepat (mobile ESS): ikon besar + label, grid 2–3 kolom, target sentuh minimal 44×44px.

### 6.5 Modal & Konfirmasi
- Modal dikontrol Alpine (`x-show`, `x-transition`) untuk UI lokal (buka/tutup cepat tanpa round-trip server), isi konten via Livewire component di dalamnya untuk yang butuh data server.
- Modal konfirmasi aksi kritis (hapus data, submit payroll run, tolak pengajuan) wajib: judul jelas, deskripsi konsekuensi dalam bahasa manusia ("Data karyawan akan dihapus permanen dan tidak dapat dikembalikan"), tombol destruktif diberi warna `danger` dan diletakkan di kanan dengan tombol batal netral di kirinya.

### 6.6 Wizard / Stepper
Dipakai untuk: onboarding karyawan baru, proses rekrutmen (tahap seleksi), pengajuan resign/offboarding, payroll run (kalkulasi → review → approve → disburse).
- Tampilkan progress stepper horizontal di desktop, vertikal ringkas di mobile.
- Setiap step harus bisa disimpan sebagai draft (Livewire persist state) — pekerja lapangan dengan koneksi tidak stabil tidak boleh kehilangan input.

### 6.7 Alur Approval Berjenjang
Pola khas HRIS enterprise (cuti, reimbursement, rekrutmen) yang butuh visual **timeline persetujuan**:
```
[✓] Diajukan — Budi (Pekerja)         12 Jan
[✓] Disetujui — Mandor Afdeling 3     13 Jan
[●] Menunggu — Asisten Kebun          (sedang diproses)
[ ] Persetujuan HR Kebun
```
Ikon centang hijau (selesai), lingkaran terisi kuning/earth (sedang berjalan), lingkaran outline abu (belum). Ini dipakai konsisten di semua modul yang punya approval chain.

### 6.8 Struktur Organisasi & Talent Pool
Untuk Modul Core HR & Succession Planning: komponen **org chart** interaktif (collapsible per level Kebun/Afdeling) dan **9-box grid** (Performance vs Potential) untuk talent review — render sebagai SVG/HTML ringan, bukan library berat, agar tetap performan di koneksi lapangan.

### 6.9 Notifikasi
- Toast (Livewire `dispatch` → Alpine listener global di layout) untuk konfirmasi aksi: sukses (`primary`), gagal (`danger`), muncul kanan-bawah, auto-dismiss 4 detik, bisa ditutup manual.
- Notification center (bell icon) untuk item aktif yang perlu tindakan (approval pending) — beda dari toast yang sifatnya sekilas.

### 6.10 Empty State
Setiap tabel/list kosong wajib punya empty state: ilustrasi ringan/ikon + kalimat aktif ("Belum ada pengajuan cuti. Ajukan cuti pertama Anda.") + tombol aksi terkait jika relevan. Jangan biarkan tabel kosong tanpa penjelasan.

---

## 7. Pola Interaksi Alpine.js ↔ Livewire

Prinsip pembagian tanggung jawab agar aplikasi terasa cepat:

| Gunakan **Alpine.js** untuk... | Gunakan **Livewire** untuk... |
|---|---|
| Toggle UI murni lokal (buka/tutup dropdown, tab, accordion) | Apapun yang butuh data dari database |
| Animasi/transisi (`x-transition`) | Validasi form server-side |
| State sementara sebelum submit (mis. preview upload) | Aksi yang mengubah data (approve, submit, delete) |
| Client-side filter kecil pada data yang sudah dimuat | Search/filter yang query ke database |

- Gunakan `wire:loading` + target spesifik (`wire:target="submit"`) agar hanya elemen relevan yang menunjukkan loading, bukan seluruh halaman memutih.
- Polling (`wire:poll.30s`) dipakai secukupnya (badge notifikasi, status payroll run yang sedang diproses) — jangan polling di halaman berat/tabel besar.
- Debounce wajib pada semua input pencarian (`debounce.400ms`) agar tidak membanjiri server.

---

## 8. Dashboard per Peran

- **ESS (Karyawan):** kartu ringkas Absensi Hari Ini, Sisa Cuti, Slip Gaji Terbaru, Pengumuman Perusahaan. Fokus mobile.
- **MSS (Atasan/Mandor):** Inbox Approval (prioritas #1, di paling atas), Ringkasan Kehadiran Tim hari ini, Kinerja Tim (jika periode review aktif).
- **HR Admin/HRBP unit:** Headcount unit, status proses rekrutmen berjalan, payroll run status, alert dokumen kepegawaian akan kedaluwarsa (kontrak PKWT, sertifikasi K3).
- **HR Corporate/Direksi:** Analitik lintas kebun/wilayah — turnover rate, biaya tenaga kerja vs anggaran, distribusi tenaga kerja per kategori (tetap/PKWT/harian lepas/musiman), peta sebaran (opsional, bila ada modul geospasial). Visualisasi chart (bar/line) memakai palet primary+earth secara konsisten, hindari warna chart acak dari library default.

---

## 9. Aksesibilitas

- Kontras teks minimal **4.5:1** (AA) — semua kombinasi warna di Bagian 4.1 sudah divalidasi untuk teks di atas latar putih/`surface-50`.
- Fokus keyboard selalu terlihat (`focus:ring-2 focus:ring-primary-400`), jangan pernah `outline-none` tanpa pengganti.
- Semua ikon aksi (tanpa label teks) wajib `aria-label`.
- Tabel data memakai elemen `<table>` semantik dengan `<th scope="col">`, bukan `<div>` yang disusun seperti tabel.
- Target sentuh minimal 44×44px pada semua tombol/ikon di tampilan mobile lapangan.
- Hormati `prefers-reduced-motion` untuk animasi non-esensial.

---

## 10. Lokalisasi & Format Data

- Bahasa default: **Indonesia**, siapkan struktur i18n Laravel agar bisa ditambah Bahasa Inggris untuk direksi/investor asing.
- Format tanggal: `DD/MM/YYYY` (mis. 24/09/2026), format waktu 24 jam.
- Format mata uang: `Rp` + pemisah ribuan titik (`Rp 4.500.000`), selalu rata kanan di kolom tabel.
- Format NIK (16 digit), NPWP, dan nomor kepesertaan BPJS mengikuti pola resmi dengan validasi input & mask.

---

## 11. Konten & Nada Bahasa (Microcopy)

- Tombol memakai kata kerja aktif spesifik: **"Ajukan Cuti"**, bukan "Submit"; **"Setujui"** / **"Tolak"**, bukan "Proses".
- Konsistensi istilah di seluruh sistem: sekali dipakai "Ajukan", jangan di modul lain berubah jadi "Kirim" untuk aksi yang sama.
- Pesan error dalam nada sistem yang jelas dan solutif: "Nomor KTP sudah terdaftar atas nama lain. Periksa kembali atau hubungi HR unit Anda." — bukan "Error: duplicate entry".
- Empty state & pesan sukses ditulis mengundang tindakan, bukan sekadar informatif pasif.

---

## 12. Konvensi Struktur Proyek (untuk konsistensi saat vibe coding)

```
resources/
  views/
    components/          → Blade component reusable (button, card, badge, modal, table)
    layouts/
      app.blade.php       → shell utama (sidebar + topbar)
      guest.blade.php
  css/app.css             → import Tailwind + font
app/
  Livewire/
    CoreHr/
    Attendance/
    Leave/
    Payroll/
    Recruitment/
    Performance/
    Learning/
    Succession/
    Reports/
    Admin/
```
- Penamaan Livewire component: `Modul/AksiSubjek` (mis. `Leave/RequestForm`, `Payroll/RunReview`).
- Setiap komponen Blade reusable didokumentasikan dengan contoh pemakaian singkat di komentar atas file.
- Satu file `resources/views/components/design-tokens.blade.php` (atau dokumentasi terpisah) berisi swatch warna & tipografi hidup sebagai referensi visual tim.

---

## 13. Checklist QA Desain Sebelum Merge

- [ ] Konsisten memakai token warna Bagian 4.1 (tidak ada hex color baru ditulis manual di Blade).
- [ ] Semua tombol aksi punya state loading & disabled saat proses berjalan.
- [ ] Tabel punya empty state, pagination, dan skeleton loading.
- [ ] Form tervalidasi server-side dengan pesan error inline berbahasa manusia.
- [ ] Responsif diuji minimal di 3 breakpoint: mobile (375px), tablet (768px), desktop (1440px).
- [ ] Kontras warna & fokus keyboard diperiksa.
- [ ] Istilah/microcopy konsisten dengan modul lain.
- [ ] Aksi destruktif memakai modal konfirmasi.

---

## 14. Prompt Pendamping untuk Antigravity IDE

Saat meminta Antigravity IDE membangun satu layar/komponen, sertakan potongan konteks berikut agar hasil selalu selaras dokumen ini:

> "Ikuti DESIGN.md: gunakan token warna primary (hijau #2F8250) & earth tone, font Plus Jakarta Sans, komponen Blade/Livewire reusable dari `resources/views/components`, sertakan state loading, empty state, dan validasi sesuai standar di Bagian 6 & 9."

---

---
---

# ADDENDUM v1.1 — Penyempurnaan Best Practice Industri HRIS

> Ditambahkan setelah review implementasi awal (modul Core HR & Mutasi/Penempatan). Addendum ini **wajib dibaca bersama Bagian 1–14** dan menggantikan bagian yang bertentangan (khususnya soal pemakaian warna di Bagian 4.1).

## 15. Aturan Pemakaian Warna — "Confident, Not Pale"

Temuan review: token warna sudah benar secara nilai hex, tetapi di implementasi hampir seluruhnya memakai varian paling pucat (`-50`/`-100`), sehingga UI terasa datar/suram dan kartu KPI sulit dipindai cepat. Perbaiki dengan aturan berikut:

### 15.1 Rasio Pemakaian (60/30/10)
- **60% netral** (`surface-50/100`, putih) — latar halaman & kartu.
- **30% warna brand di elemen struktural** — sidebar aktif, header tabel, garis pembatas section, tab aktif (boleh solid, bukan cuma garis tipis 2px).
- **10% aksen jenuh (saturated)** — KPI icon, badge status, indikator urgensi, tombol utama. Justru di 10% inilah warna **wajib solid/jenuh**, bukan pucat — karena fungsinya menarik perhatian, bukan sekadar dekorasi.

### 15.2 Diferensiasi Warna Kartu KPI/Statistik
Setiap kartu KPI di dashboard **wajib** memakai salah satu dari 4 warna aksen berikut secara bergilir sesuai kategori data — **dilarang** seluruh kartu memakai warna ikon yang sama:

| Kategori Data | Warna Ikon (background solid + icon putih/gelap kontras) |
|---|---|
| Populasi/Headcount (netral kuantitatif) | `primary-600` (hijau) |
| Kepemimpinan/Struktural | `info` (`#2E6E9E`, biru) |
| Operasional/Pelaksana | `earth-600` (`#8A7550`, khaki solid) |
| Unit/Entitas/Lokasi | teal aksen baru: `#1F7A6C` |

Icon container memakai background **solid** dari warna di atas (bukan tint 100/50), ikon berwarna putih di atasnya. Ini standar di Workday/SuccessFactors — setiap kategori KPI langsung teridentifikasi lewat warna tanpa membaca label.

### 15.3 Badge & Status — Filled, Bukan Dot
Badge status (Aktif, Menunggu, Kritis, Perhatian, dll.) **wajib** berupa pill dengan **background solid tint kuat** (bukan `-50`) + teks warna gelap kontras dari keluarga warna yang sama, contoh:

```
Kritis (>90 hari)   → bg #FCE4E1 → ganti ke bg #F8D2CC, teks #8A2A1E, dot solid #C0392B
Perhatian (30-90)   → bg #FBEFD2, teks #7A5B06, dot solid #B8860B
Baru Kosong (<30)   → bg #DCF0E4, teks #1B5330, dot solid #2F8250
Aktif Menjabat       → bg #E7F5EC, teks #1B5330
Selesai (Mutasi)     → bg surface-200, teks surface-700
```
Dot indikator boleh tetap ada di dalam badge (sebagai penguat), tapi **badge itu sendiri harus punya warna latar yang cukup jenuh untuk terbaca dari jarak scan**, bukan sekadar teks abu-abu + dot kecil.

### 15.4 Persentase/Progress Indicator — Netral vs Semantik
Pisahkan dua jenis angka persen agar tidak salah baca:
- **Persentase proporsi netral** (mis. "75% Level Pimpinan dari total pegawai") → gunakan warna **netral** (`surface-700` teks, badge abu-abu), karena ini bukan indikator baik/buruk.
- **Persentase performa/progress terhadap target** (mis. "Realisasi Formasi 77.5%") → boleh semantik (hijau jika mendekati/mencapai target, amber jika di bawah ambang, merah jika kritis) — **dengan threshold yang didefinisikan eksplisit** di dokumentasi modul terkait (mis. ≥90% hijau, 70–89% amber, <70% merah), bukan hijau default untuk semua persen.

### 15.5 Sidebar & Header — KEPUTUSAN FINAL

**Ditetapkan: Sidebar terang + Header/Topbar gelap.** Ini bukan lagi opsi A/B — seluruh halaman wajib konsisten memakai pola ini, alasan keputusan didokumentasikan di Bagian 26 (Riwayat Revisi).

Spesifikasi wajib:
- **Sidebar**: background putih/`surface-50` (dipertahankan seperti implementasi saat ini, karena lebih nyaman untuk sesi kerja panjang HR Admin/HRBP dan selaras pola Workday/SuccessFactors/Talenta yang dominan terang).
- **Item navigasi aktif**: background **solid** `primary-600`, teks **putih**, ikon putih. **Dilarang** memakai tint pucat (`primary-50`) + garis kiri tipis sebagai satu-satunya penanda — itu kontrasnya terlalu lemah untuk scan cepat.
- **Header/Topbar**: background solid `primary-800` (bukan putih), teks & ikon putih/`primary-50`, search bar memakai `primary-700` sebagai background input agar tetap kontras di atas header gelap. Ini adalah elemen yang membawa identitas hijau PTPN paling konsisten di setiap layar.
- **Alasan tidak memakai sidebar gelap penuh**: mayoritas pengguna desktop (HR Admin, Manajer, Direksi) menghadapi layar 6–8 jam/hari dengan tabel data padat — panel navigasi gelap besar menambah beban visual berkepanjangan. Pengguna lapangan mengakses lewat bottom navigation mobile (Bagian 5), sehingga warna sidebar desktop tidak berdampak ke persona tersebut. Identitas brand tetap terjaga lewat header solid + konsistensi aksen di Bagian 15.2/15.3.

### 15.6 Grafik & Visualisasi Data
Chart (bar/line/pie) di modul Analytics wajib memakai urutan warna tetap dari palet brand: `primary-600 → earth-600 → info → primary-300 → earth-300`, **bukan** warna default library (biru-oranye-hijau acak dari Chart.js/ApexCharts). Satu kategori data = satu warna konsisten di seluruh dashboard (mis. "Karyawan Tetap" selalu hijau di chart manapun ia muncul).

---

## 16. Matriks State Komponen (Wajib per Komponen Interaktif)

Setiap komponen interaktif (tombol, input, tab, checkbox, row tabel) harus punya definisi eksplisit untuk 6 state berikut — tidak boleh hanya default+hover:

| State | Contoh Tombol Primary | Contoh Input |
|---|---|---|
| Default | `bg-primary-600 text-white` | `border-surface-300` |
| Hover | `bg-primary-700` | `border-surface-400` |
| Focus (keyboard) | `ring-2 ring-primary-300 ring-offset-2` | `ring-2 ring-primary-300 border-primary-500` |
| Active/Pressed | `bg-primary-800 scale-[0.98]` | — |
| Disabled | `bg-surface-200 text-surface-500 cursor-not-allowed` | `bg-surface-100 text-surface-400` |
| Error/Invalid | — | `border-danger ring-danger/20`, teks pesan error `text-danger text-sm` di bawah field |

Baris tabel tambahan: `hover:bg-primary-50`, `selected:bg-primary-100` (untuk bulk action checkbox aktif).

---

## 17. Data Sensitif — Privacy by Design di UI

Karena HRIS menyimpan data gaji & pribadi, UI wajib menerapkan:
- **Masking default** untuk data finansial di tampilan list/dashboard non-payroll-officer: `Rp ••••.•••` dengan ikon mata untuk toggle tampil, log akses tercatat.
- **Redaksi berbasis role** — kolom yang tidak berwenang dilihat role tertentu (mis. Mandor tidak boleh lihat gaji pokok bawahannya) tidak ditampilkan sama sekali di tabel (bukan cuma di-disable/blur), agar tidak bocor lewat inspect element.
- **Watermark dinamis** pada dokumen sensitif yang di-preview/print (slip gaji, kontrak) berisi nama pengakses + timestamp, mencegah screenshot disebar tanpa jejak.
- **Session timeout otomatis** untuk halaman payroll (mis. 10 menit idle) dengan modal re-autentikasi, lebih ketat dari halaman umum lain.

---

## 18. Grid & Breakpoint Eksplisit

```
Grid: 12 kolom, gutter 24px (desktop), 16px (tablet/mobile)
Breakpoint (selaras default Tailwind):
  sm:  640px   → mobile besar / mulai 2 kolom form
  md:  768px   → tablet, sidebar mulai collapsible
  lg:  1024px  → desktop, sidebar expanded default
  xl:  1280px  → tabel data lebar penuh
  2xl: 1536px  → dashboard analitik multi-panel
Container max-width: 1440px (2xl:mx-auto)
```

---

## 19. Token Motion & Elevasi

```
Durasi transisi:
  fast:   100ms  → hover state, toggle kecil
  base:   200ms  → dropdown, tab switch
  slow:   300ms  → modal open/close, page transition
Easing: ease-out (masuk), ease-in (keluar)

Skala z-index:
  10  → sticky header tabel
  20  → dropdown/select menu
  30  → sidebar mobile overlay
  40  → modal/dialog
  50  → toast/notification (selalu paling atas)
```
Hormati `prefers-reduced-motion: reduce` — nonaktifkan transisi non-esensial (skala/slide), pertahankan hanya perubahan opacity untuk pengguna dengan preferensi ini.

---

## 20. Halaman Sistem (System Pages)

Wajib dirancang dengan identitas visual sama seperti halaman utama (bukan halaman error generik framework):
- **403 (Tidak Berwenang)** — jelaskan alasan singkat + tombol "Kembali ke Beranda" / "Hubungi Admin Unit".
- **404** — untuk data spesifik (mis. NIK tidak ditemukan) beri saran pencarian ulang.
- **500 / Terjadi Kesalahan** — nada tenang, tombol "Coba Lagi", info kontak IT support jika berulang.
- **Session Timeout** — modal (bukan redirect mendadak) dengan opsi "Perpanjang Sesi" agar input form tidak hilang.
- **Maintenance Mode** — halaman terjadwal dengan estimasi waktu selesai, penting saat periode payroll run.
- **Offline State** (mobile ESS/absensi) — banner persisten "Anda sedang offline, data akan tersinkron otomatis" — lihat Bagian 22.

---

## 21. Progressive Disclosure untuk Data Padat

Untuk tabel/matriks kompleks seperti Formasi MPP (multi-header per bidang KEU/TAN/TEK/UMU):
- **Sticky first column** (nama indikator/karyawan) saat scroll horizontal.
- **Expandable row** — baris ringkasan bisa expand untuk detail per bidang, daripada memaksa semua kolom tampil sekaligus di layar sempit.
- **Comparison toggle** — untuk data periode (mis. Realisasi vs Standar Formasi), sediakan toggle "Tampilkan selisih (gap)" agar user tidak menghitung manual.
- **Column visibility control** — ikon "Atur Kolom" di kanan atas tabel lebar, user bisa sembunyikan kolom tidak relevan bagi tugasnya.
- **Hover row+column highlight** pada tabel matriks besar agar mata tidak tersesat membaca perpotongan baris-kolom.

---

## 22. Pola Offline-First (Absensi & ESS Lapangan)

Krusial untuk pekerja di area kebun dengan sinyal terbatas:
- Form absensi (check-in/out, GPS, foto) disimpan dulu ke **local storage/IndexedDB** saat submit, ditandai status "Menunggu Sinkronisasi" dengan ikon jam, baru dikirim ke server saat koneksi kembali (background sync).
- Indikator status koneksi persisten di topbar mobile (Online/Offline/Menyinkronkan).
- Data referensi yang sering dipakai offline (jadwal shift, saldo cuti terakhir diketahui) di-cache lokal dengan label "Data terakhir diperbarui: [waktu]" agar user tahu ini bukan data real-time.
- **Tidak ada aksi kritis** (approval, submit payroll) yang boleh dilakukan dalam mode offline — batasi offline hanya untuk input transaksional milik sendiri (absensi, draft form).

---

## 23. Onboarding & Bantuan Kontekstual

- **Tooltip kontekstual** (ikon "?" kecil) pada istilah teknis HR yang mungkin asing bagi pekerja baru (mis. "TMT", "Golongan", "BUP").
- **Product tour ringan** (Alpine-driven spotlight, 3–5 langkah) saat pertama kali membuka modul baru — bisa di-skip, tidak muncul ulang kecuali direset admin.
- **Help center in-app** dapat diakses dari ikon "?" di topbar, berisi FAQ per modul & kontak HR unit — bukan mengarahkan keluar aplikasi.

---

## 24. Notifikasi Multi-Channel

Selain bell icon in-app (Bagian 6.9), definisikan preferensi channel per jenis notifikasi (dikonfigurasi user di halaman Profil):
- **Push/in-app**: semua notifikasi.
- **Email**: ringkasan approval pending harian, slip gaji terbit, pengumuman resmi.
- **WhatsApp/SMS** (opsional, untuk pekerja tanpa akses rutin ke aplikasi): reminder absensi, status cuti disetujui/ditolak.
Setiap notifikasi transaksional harus konsisten kata kerjanya dengan aksi di UI (lihat Bagian 11).

---

## 25. Print-Friendly Layout

Dokumen yang lazim dicetak (slip gaji, surat keputusan/SK, kontrak kerja, kartu identitas pegawai) **wajib** punya CSS `@media print` terpisah dari layout web:
- Hilangkan sidebar, topbar, tombol aksi saat print/export PDF.
- Layout A4 dengan margin standar, kop surat resmi PTPN, nomor halaman.
- Font untuk dokumen resmi boleh lebih formal (mis. serif) berbeda dari font UI aplikasi — dokumen cetak punya konvensi berbeda dari layar.
- Watermark "SALINAN" atau QR verifikasi keaslian dokumen untuk SK/kontrak.

---

## 26. Riwayat Revisi Dokumen

| Versi | Tanggal | Perubahan |
|---|---|---|
| v1.0 | Rilis awal | Struktur dasar design system: token, komponen, modul |
| v1.1 | Rilis lanjutan | Aturan pemakaian warna (anti-pucat), state komponen, data masking, grid/motion token, system pages, offline pattern, onboarding, notifikasi multi-channel, print layout |
| v1.2 | Revisi shell | Eksperimen sidebar terang + header gelap |
| v2.0 | 24/09/2026 | **Overhaul Standar Industri HRIS (Workday & Mekari Talenta Standard)**: Penghapusan boxitis/wireframe kaku, standarisasi Profil Karyawan (Hero Banner + Horizontal Tabs + Definition Lists), form enterprise tersegmentasi, filter direktori modern, dan penyelarasan Unified Modern Light Shell. |

---

# ADDENDUM v2.0 — Standar Industri HRIS & Anti-Boxitis (Workday & Mekari Talenta Benchmark)

> Addendum ini wajib menjadi acuan implementasi UI di seluruh aplikasi. Segala bentuk pembungkusan field individual ke dalam kotak abu-abu/border terisolasi dilarang keras.

## 27. Aturan Anti-"Boxitis" & Surface Layering

### 27.1 Masalah "Boxitis" (Wireframe Syndrome)
Pada implementasi sebelumnya, setiap data kecil (seperti NIK, Tanggal Lahir, Agama, Tempat Lahir) dibungkus dalam kotak persegi tersendiri ber-border tebal (`bg-gray-50 border rounded-lg`). Ini adalah anti-pattern yang membuat antarmuka terasa seperti *wireframe mockup* mentah atau grid formulir kuno.

### 27.2 Prinsip Surface Layering Bersih
1. **Canvas (`bg-slate-50` / `#F8FAFC`)**: Latar belakang dasar halaman yang bersih dan lapang.
2. **Container Card (`bg-white` + `border border-slate-200/80` + `shadow-xs`)**: Hanya kartu pembungkus kelompok logis besar (mis. Kartu Biodata, Kartu Riwayat Jabatan).
3. **Data Rows / Definition List (`<dl>`)**:
   - Di dalam kartu, data ditampilkan sebagai pasangan Label (`<dt>`) dan Nilai (`<dd>`) yang bersih.
   - Gunakan layout grid 2 atau 3 kolom dengan garis pemisah bawah tipis (`border-b border-slate-100 pb-3`) jika perlu pemisah visual, **bukan** membuat kotak abu-abu di sekeliling tiap item.
   - Label: `text-xs font-medium text-slate-500` (atau `text-surface-500`).
   - Nilai: `text-sm font-semibold text-slate-900` (atau `text-surface-900`).

---

## 28. Standar Halaman Profil & Detail Karyawan (`show.blade.php`)

Format standar industri (Workday, SuccessFactors, Mekari Talenta) untuk halaman detail karyawan wajib mengikuti struktur 2 bagian utama:

### 28.1 Profile Hero Banner (Header Profil)
Terletak di bagian paling atas dengan latar putih berborder halus atau aksen gradasi hijau korporat tipis:
- **Avatar Foto**: Berukuran proporsional (80x80px hingga 96x96px), `rounded-2xl` atau `rounded-full` dengan border halus putih & cincin status.
- **Identitas Utama**:
  - Nama Lengkap (h1, `text-2xl font-bold text-slate-900`).
  - Badge Status Kepegawaian (mis. `Pimpinan` / `Pelaksana`, status `Aktif` hijau pastel).
  - NIK dengan tombol copy interaktif.
  - Jabatan & Unit Kerja saat ini.
- **Action Toolbar (Kanan Atas)**:
  - Tombol aksi cepat: *Kembali ke Direktori*, *Edit Profil*, *Ajukan Mutasi*, *Cetak Profil*.

### 28.2 Horizontal Navigation Tabs
Tepat di bawah Hero Banner, sediakan navigasi tab berbasis Alpine.js (`x-data="{ activeTab: 'biodata' }"`):
1. **Tab 1: Biodata & Data Pribadi**: Informasi tempat/tgl lahir, usia, agama, susunan keluarga, pendidikan terakhir, institusi pendidikan, alamat & kontak. Ditampilkan dalam format clean definition list.
2. **Tab 2: Kepegawaian & Formasi**: Level (Karpim/Pelaksana), Golongan, Job Grade, Person Grade, TMT Bekerja, Tanggal Dalam Jabatan, Tanggal MBT.
3. **Tab 3: Riwayat Karir & Jabatan**: Tabel atau timeline vertikal mutasi/promosi/rotasi.
4. **Tab 4: Pelatihan & Kinerja**: Riwayat pelatihan yang diikuti, sertifikasi, serta hasil evaluasi kinerja tahunan.

---

## 29. Standar Form Input Enterprise (`add-employee.blade.php` & `edit-employee.blade.php`)

1. **Card Segmentasi Logis**:
   - Pisahkan form panjang menjadi 2-3 card putih bersih (mis. "1. Data Identitas Pribadi" dan "2. Formasi Jabatan & Penempatan").
   - Header tiap section memiliki ikon penanda dan deskripsi singkat 1 baris.
2. **Input Fields**:
   - Border halus netral: `border border-slate-300 rounded-lg`.
   - Tinggi standar: `h-10` (40px) atau `h-11` (44px) untuk desktop, padding horizontal `px-3.5`.
   - Focus ring: `focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600`.
   - Hindari radius gelembung yang terlalu membulat (`rounded-2xl` pada input teks biasa membuat form terlihat kekanak-kanakan).
3. **File Upload Interaktif**:
   - Area drag & drop modern dengan preview langsung gambar profil saat file dipilih.
4. **Action Footer Sticky**:
   - Baris tombol di bawah card: tombol Batal (`text-slate-600 hover:bg-slate-100`) di kiri, tombol Simpan Data (`bg-emerald-600 hover:bg-emerald-700 text-white`) di kanan.

---

## 30. Standar Direktori & Tabel Karyawan (`index.blade.php`)

1. **KPI Metric Cards**:
   - Gunakan container putih dengan border `border-slate-200/80` dan shadow mikro (`shadow-xs`).
   - Ikon metrik menggunakan container bulat atau rounded-xl dengan warna pastel lembut (Emerald-50, Amber-50, Blue-50) dan teks angka yang tegas (`font-bold font-mono text-2xl`).
2. **Filter Bar**:
   - Filter cepat (Quick Tabs): Tab pill instan untuk beralih antara "Semua Pegawai", "Karyawan Pimpinan", dan "Karyawan Pelaksana".
   - Search input utama dengan ikon kaca pembesar dan shortcut `Ctrl + K`.
   - Tombol "Filter Lanjutan" (Toggle drawer/accordion) untuk menyembunyikan 5-6 dropdown filter agar antarmuka tidak sesak.
3. **Tabel Data Karyawan**:
   - Baris tabel berjarak rapi (`py-3.5`), hover efek `hover:bg-slate-50/80`.
   - Kolom Nama & NIK dilengkapi avatar inisial/foto bergaris halus.
   - Badge Level & Golongan memakai warna pastel modern (bukan warna mentah/neon).
   - Tombol Aksi per baris (Detail, Edit, Hapus) dalam tombol ikon minimalis dengan tooltip.

---

## 31. Unified Modern Light Shell

Sesuai konsensus evaluasi UI terbaru, seluruh aplikasi distandarisasi menggunakan **Unified Modern Light Shell**:
- **Sidebar**: Latar putih bersih (`bg-white`), border kanan halus `border-slate-200`.
- **Active Navigation Item**: Latar hijau lembut `bg-emerald-50`, teks hijau tua `text-emerald-800 font-semibold`, dan indikator bar aktif di kiri `border-l-4 border-emerald-600`.
- **Topbar**: Latar putih bersih (`bg-white/95 backdrop-blur-md`), border bawah `border-slate-200`.
- **Header Identitas Perusahaan**: Tulisan "Talenta Hub" dan "PTPN IV Regional V" dengan logo resmi.

---

## 32. Standar Man Power Planning (MPP) & Dynamic Planning Horizon

Untuk perencanaan kebutuhan formasi tenaga kerja (MPP):
1. **Dynamic Rolling 2-Year Horizon ($T$ & $T+1$)**:
   - Kolom indikator perencanaan formasi tidak boleh dikunci secara statis pada tahun tertentu (mis. hanya 2026–2027).
   - Pengguna (HR / Manajemen) dapat memilih **Tahun Perencanaan ($T$)** melalui dropdown filter di toolbar (mis. 2024–2031).
   - Baris indikator pensiun otomatis berlabel: `Pensiun sd Des {T}` dan `Pensiun sd Des {T+1}`.
   - Baris pasokan & kebutuhan otomatis berlabel: `RBB {T}`, `Kebutuhan {T}`, dan `Kebutuhan {T+1}`.
   - Kartu KPI Ringkasan Eksekutif (Card 3 BUP & Card 4 Kebutuhan Bersih) otomatis sinkron dengan tahun yang dipilih.
2. **Sinkronisasi Mode Data (Baseline vs. Live Database)**:
   - **Mode Baseline (Formasi Baku Korporasi 444)**: Menjaga konsistensi data referensi korporasi dengan formula dinamis per horizon.
   - **Mode Live (Sync Karyawan Database)**: Mengagregasi data real time karyawan aktif berdasarkan BUP pensiun $\le T$ dan $= T+1$, dipetakan ke tingkat golongan RM-1, RM-2, dan RM-3 serta bidang fungsional (KEU, TAN, TEK, UMU).
3. **Export Fleksibel**:
   - Fitur unduh Excel / CSV otomatis menyertakan label tahun perencanaan yang aktif dan menghasilkan nama berkas dinamis (mis. `mpp_perencanaan_formasi_2026_2027.csv`).
4. **Integrated Table Header Toolbar (Anti-Whitespace & Zero Awkward Wrapping)**:
   - Kontrol toolbar tidak boleh dibungkus dalam kartu mengambang terpisah di atas tabel yang menyisakan ruang kosong besar dan menyebabkan wrapping tombol.
   - Gunakan layout **2-Tier Integrated Header**:
     - *Tier 1 (Atas)*: Judul Matriks Formasi bersih tanpa redundant badge (konteks enterprise sudah berada di Topbar utama), Subtitle deskripsi, dan Action Buttons (Export Excel & Cetak) di kanan.
     - *Tier 2 (Bawah)*: Sub-Header Filter Strip berlatar tipis (`bg-surface-50 border-b border-surface-200`) yang menyeimbangkan Mode Sumber Data (Segmented Tab) di kiri serta Filter Tahun & Bidang di kanan.
5. **Standar Header Grup RM pada Filter Bidang (Single-Line Layout)**:
   - Saat memfilter berdasarkan bidang fungsional (KEU, TAN, TEK, UMU), header grup manajerial (RM-1, RM-2, RM-3) wajib mempertahankan teks dalam **1 baris lurus utuh** (`whitespace-nowrap`).
   - Berikan `min-w-[220px]` untuk RM-1 & RM-2, serta `min-w-[240px]` untuk RM-3 guna mencegah kata patah (seperti `"RM-"` dan `"1"` terpisah secara vertikal).
6. **Standar Interaksi Drilldown Sel Angka (Slide-Over Drawer & Rich Employee Cards)**:
   - Klik pada sel angka bernilai $> 0$ memicu **Slide-Over Drawer (Panel Samping Kanan)** selebar `480px–520px` tanpa mengaburkan tabel (`bg-slate-900/30` tanpa CSS blur), sehingga tabel MPP di sebelah kiri tetap terlihat dan dapat dibandingkan secara langsung.
   - **Affordance Bersih**: Hanya sel yang memiliki data personil $> 0$ yang dapat diklik (`cursor-pointer`, hover highlight emerald halus, dan garis bawah titik-titik `underline decoration-dotted`). Sel yang bernilai `0` atau `-` bersifat statis (`cursor-default`).
   - **Formula Breakdown Header**: Khusus saat mengklik sel baris hasil perhitungan (seperti *Kebutuhan 2026/2027*), drawer menampilkan kartu persamaan matematika: `Formasi - Realisasi + Pensiun - Pasokan = Kebutuhan Bersih` lengkap dengan badge *Surplus Pasokan* / *Defisit Formasi*, diikuti daftar pemangku jabatan aktif saat ini (*incumbents*).
   - **Instant Personnel Search**: Input pencarian cepat di bagian atas drawer (`x-data="{ search: '' }"`) untuk memfilter nama, NIK, atau jabatan seketika tanpa jeda jaringan (sangat krusial untuk sel dengan puluhan hingga ratusan personil).
   - **Rich Profile Cards**: Personil disajikan dalam kartu profil terstruktur: avatar inisial, Nama Lengkap tebal, NIK, Status Karyawan, Jabatan, Unit Kerja, Golongan badge, Tanggal Pensiun, dan tombol aksi `"Lihat Profil ↗"` menuju halaman detail karyawan (`admin.employees.show`).
   - **Subset Export**: Tombol `"Export CSV"` di footer drawer untuk mengunduh daftar personil yang sedang difilter.

7. **Standar Tipografi & Perataan Tabel Matriks MPP (HRIS Industry Best Practice)**:
   - **Header `No.` & `URAIAN INDIKATOR`**: Judul kolom header (`th`) keduanya dibuat rata tengah (`text-center`) agar seimbang dengan grup-grup kolom di bawahnya.
   - **Teks Baris Indikator (Clean Enterprise Scannability)**: Isi teks baris indikator (`td`) dipertahankan **rata kiri (`text-left px-4`)** dan **tanpa ikon dekoratif** (seperti ikon kalkulator, lapisan, atau centang) guna menciptakan garis baca vertikal yang lurus, rapi, dan profesional setara software enterprise (SAP SuccessFactors, Workday).
   - **Penekanan Visual Kolom Agregat (JUMLAH & TOTAL)**: Seluruh angka pada kolom Subtotal per golongan (`JUMLAH`) dan Kolom Akumulasi Akhir (`TOTAL`) dicetak **tebal (`font-bold` / `font-extrabold font-mono text-surface-900`)**, termasuk ketika nilainya `0`, untuk membedakan secara tegas antara sel data masukan bidang dengan sel agregasi ringkasan.

---

## 33. Standar Monitoring Formasi & Opsi Pengisian Lowongan (Fulfillment Actions)

Untuk menjaga konsistensi operasional pada modul Monitoring Formasi Kosong:
1. **Integrated Header Toolbar**:
   - Filter dan pencarian disatukan langsung ke dalam kartu tabel (`rounded-xl overflow-hidden shadow-sm border border-surface-200`) untuk menghilangkan kartu mengambang yang terpisah dan menghemat ruang vertikal.
   - **Tier 1 (Main Controls)**: Search input fleksibel dengan tombol *clear* cepat, dropdown filter Unit Kerja, dan tombol *Reset* yang muncul dinamis saat ada filter aktif.
   - **Tier 2 (Urgency Status Strip)**: Sub-header berlatar tipis (`bg-surface-50 border-t border-b border-surface-200`) yang menyajikan filter pil status durasi kekosongan (Semua, Kritis >90 hari, Perhatian 30–90 hari, Baru Kosong <30 hari) dengan badge kuantitas yang halus dan modern, disertai counter total formasi lowong di sisi kanan.
2. **Standar Tombol Opsi Pengisian (Equal-Width Vertical Stack)**:
   - Tombol aksi pengisian formasi disusun **bertumpuk vertikal dengan lebar seragam (`w-32 items-stretch ml-auto`)** agar lebar kolom aksi menjadi ringkas (~140px) dan menghemat ~100px+ ruang horizontal untuk kolom *Unit Kerja* dan *Jabatan Lowong* sehingga nama panjang tidak terpotong/terhimpit.
   - Karena kolom lain (Unit, Jabatan, Pejabat Terakhir, Kosong Sejak) secara alami memiliki tinggi 2–3 baris (~65px), susunan 2 tombol bertumpuk ini mengisi ruang vertikal secara presisi tanpa menambah ketinggian baris tabel.
   - **Dimensi & Lebar Identik**: Kedua tombol mengisi lebar penuh kontainer (`w-full py-1.5 px-2.5 rounded-lg text-xs font-semibold`).
   - **Mutasi Internal (Primary Talent Mobility Path)**: Berada di atas dengan gaya emerald solid (`bg-emerald-600 hover:bg-emerald-700 text-white`) dan ikon mutasi `fas fa-exchange-alt text-[10px]`.
   - **Rekrut Baru (Secondary Fulfillment Path)**: Berada di bawah dengan gaya outline bersih (`bg-white hover:bg-surface-50 text-surface-700 border border-surface-300`) dan ikon `fas fa-user-plus text-[10px] text-surface-400`.

---

*Dokumen ini hidup (living document) — perbarui seiring modul baru dibangun agar Antigravity IDE selalu punya konteks desain terbaru.*
