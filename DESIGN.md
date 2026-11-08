# DESIGN.md — Design System & UX Guidelines
## Sistem Informasi Manajemen Talenta & Formasi Karyawan — PTPN IV Regional V

| | |
|---|---|
| **Versi** | 3.4 (28/10/2026) |
| **Status** | Normatif. Satu-satunya acuan UI/UX; menggantikan seluruh addendum v1.1–v2.1. |
| **Stack** | Laravel 13 · Livewire 4 · Alpine.js · Tailwind CSS v4 |
| **Sumber nilai token** | `resources/css/app.css` (blok `@theme`). Bila dokumen ini dan `@theme` berbeda, `@theme` yang benar dan dokumen ini yang diperbaiki. |
| **Dokumen pendamping** | [DOMAIN.md](DOMAIN.md) (aturan bisnis, kamus data) · [ENGINEERING.md](ENGINEERING.md) (standar kode) · [SECURITY.md](SECURITY.md) (privasi & audit) |

**Cara membaca.** Kata **wajib** / **dilarang** adalah aturan yang diperiksa saat review. Kata **sebaiknya** adalah default yang boleh disimpangi dengan alasan tertulis di PR. Bagian 17 (Utang Desain) mencatat kode lama yang belum sesuai; jangan meniru pola dari sana. Bagian 18 (Roadmap) **tidak normatif**.

---

## 1. Prinsip Desain

1. **Jelas sebelum indah.** Data kepegawaian (formasi, mutasi, sanksi, masa pensiun) berdampak langsung pada karier orang. Status dan angka harus tidak ambigu.
2. **Warna dengan maksud.** Warna jenuh disisihkan untuk kondisi yang menuntut tindakan. Atribut normal tampil netral (Bagian 4).
3. **Konsisten lintas modul.** Satu aksi = satu label, satu warna, satu posisi di semua halaman.
4. **Arti tidak bergantung warna saja.** Setiap status punya label teks; warna hanya penguat (buta warna, cetak hitam-putih).
5. **Terasa responsif.** Setiap request Livewire punya umpan balik visual yang terarah, tidak ada layar yang "diam".
6. **Padat tapi terbaca.** Pengguna utama adalah admin SDM yang bekerja 6–8 jam/hari di laptop dinas 1366×768. Kepadatan data tinggi, ornamen rendah.

**Anti-pola yang dilarang:** kotak abu-abu di sekitar setiap field (*boxitis*), badge berwarna pada setiap atribut (*badge soup*), gradien dekoratif, judul ALL-CAPS, emoji sebagai ikon, shadow tebal pada semua kartu.

---

## 2. Pengguna & Cakupan

### 2.1 Persona aktif

| Persona | Perangkat | Kebutuhan kunci |
|---|---|---|
| **Admin SDM Regional** | Desktop 1366–1920px | Kelola data induk karyawan, impor Excel, proses mutasi & SK, monitoring formasi kosong |
| **Pimpinan SDM / Manajemen** | Desktop & tablet | MPP, ringkasan formasi, talent review 9-box, riwayat mutasi |
| **Karyawan (layout `user`)** | Mobile & desktop | Lihat data diri, pelatihan, evaluasi |

Implikasi: **desktop-first** untuk seluruh modul admin; **mobile-first** hanya untuk halaman karyawan.

### 2.2 Modul dalam cakupan

| Modul | Lokasi view |
|---|---|
| Direktori & Profil Karyawan (termasuk impor) | `admin/employees`, `livewire/admin/employees` |
| Mutasi & Penempatan (tunggal, rantai, riwayat, SK) | `admin/mutasi`, `livewire/admin/mutasi-*` |
| Monitoring Formasi Kosong | `admin/vacancies`, `livewire/tables/job-vacancy-table` |
| Man Power Planning (MPP) | `livewire/admin/man-power-planning` |
| Pelatihan | `admin/trainings` |
| Evaluasi & Talenta 9-box | `admin/evaluations` |
| Master Data / Master Formasi (unit, jabatan, kursi formasi, riwayat perubahan) | `admin/master`, `livewire/admin/master` |

Payroll, absensi, cuti, dan rekrutmen **di luar cakupan**. Jangan membangun UI untuk modul tersebut berdasarkan dokumen ini.

---

## 3. Design Tokens

### 3.1 Aturan umum

- Nilai token **hanya** didefinisikan di `@theme` pada `resources/css/app.css`. Tidak ada `tailwind.config.js`.
- **Dilarang** menulis hex atau `rgb()` manual di Blade/CSS (`bg-[#2F8250]`, `style="color:…"`).
- **Dilarang** memakai palet bawaan Tailwind untuk peran yang sudah punya token (dijaga otomatis, §16.1). Pengecualian terbatas pada hue kategoris di Tabel Status (Bagian 5). Pemetaan baku:

| Palet bawaan | Token | Aturan shade |
|---|---|---|
| `slate`, `gray`, `zinc` | `surface` | shade sama (50–950) |
| `emerald`, `green` | `primary` | shade sama (50–950) |
| `red`, `rose` | `danger` | 50/100/200 sama; 300→200; 400–600→DEFAULT (`bg-danger`); 700→700; ≥800→800 |
| `amber`, `yellow` | `warning` | seperti `danger`, kecuali teks dan latar ber-teks-putih pada 400–600 memakai `warning-700` (kontras §3.4) |
| `blue` | `info` | seperti `danger` |

### 3.2 Palet

**Primary — Hijau Daun** (brand, aksi utama, navigasi aktif)

| 50 | 100 | 200 | 300 | 400 | 500 | 600 | 700 | 800 | 900 | 950 |
|---|---|---|---|---|---|---|---|---|---|---|
| `#F3FAF6` | `#E7F5EC` | `#C9E8D5` | `#9BD1B0` | `#66B586` | `#3E9A63` | **`#2F8250`** | `#256B3E` | `#1B5330` | `#123A22` | `#0B2416` |

**Earth — Khaki** (aksen sekunder, kategori "operasional/pelaksana")

| 50 | 100 | 200 | 300 | 400 | 500 | 600 | 700 | 800 | 900 |
|---|---|---|---|---|---|---|---|---|---|
| `#FAF8F2` | `#F2ECDD` | `#E5DBC5` | `#D8C9A8` | `#C2AD84` | `#A8916A` | `#8A7550` | `#6B5B3A` | `#4D4129` | `#332B1B` |

**Surface — Netral hangat** (teks, border, latar)

| 50 | 100 | 200 | 300 | 400 | 500 | 600 | 700 | 800 | 900 | 950 |
|---|---|---|---|---|---|---|---|---|---|---|
| `#F8F9F6` | `#F1F3EE` | `#DEE3D8` | `#C3C9BE` | `#878E84` | `#6B7268` | `#555C53` | `#40473F` | `#2D332E` | `#1B1F1D` | `#111412` |

**Semantik** — `DEFAULT` untuk isian & ikon; `-700`/`-800` untuk teks; `-50`…`-200` untuk latar badge.

| Token | 50 | 100 | 200 | DEFAULT | 700 | 800 |
|---|---|---|---|---|---|---|
| `danger` | `#FDF1EF` | `#FCE4E1` | `#F8D2CC` | `#C0392B` | `#A02F23` | `#8A2A1E` |
| `warning` | `#FDF8EC` | `#FBEFD2` | `#F3DDA4` | `#B8860B` | `#8A6508` | `#6B4E05` |
| `info` | `#EFF5FA` | `#DCEAF4` | `#B9D4E8` | `#2E6E9E` | `#255A82` | `#1D4766` |
| `success` | — | — | — | memakai `primary-*` | `primary-700` | `primary-800` |

### 3.3 Peran warna (semantic roles)

Pakai tabel ini saat memilih kelas. Jangan memilih shade berdasarkan selera.

| Peran | Kelas |
|---|---|
| Latar halaman (canvas) | `bg-surface-50` |
| Latar kartu / panel / tabel | `bg-white` |
| Latar strip filter, header tabel, area tenang | `bg-surface-50` atau `bg-surface-100` |
| Border kartu | `border-surface-200` (boleh `/80`) |
| Garis pemisah baris | `divide-surface-200` / `border-surface-100` |
| Border input | `border-surface-300`, hover `border-surface-400` |
| Teks utama | `text-surface-900` |
| Teks sekunder (label `<dt>`, meta, caption) | `text-surface-500` atau `text-surface-600` |
| Teks tersier: placeholder, disabled, ikon dekoratif | `text-surface-400` (**bukan** untuk teks informatif) |
| Link / teks aksen brand | `text-primary-700` |
| Teks error di bawah field | `text-danger-700` |
| Teks peringatan | `text-warning-800` (**dilarang** `text-warning`) |

### 3.4 Kontras (terukur, WCAG 2.1)

Rasio diukur terhadap putih / `surface-50`. Ambang: teks normal 4.5:1, teks besar & elemen non-teks 3:1.

| Token | vs putih | vs surface-50 | Boleh dipakai untuk |
|---|---|---|---|
| `surface-900` | 16.6 | 15.7 | Semua teks |
| `surface-600` | 6.9 | 6.5 | Teks sekunder |
| `surface-500` | 5.0 | 4.7 | Teks sekunder (batas minimum) |
| `surface-400` | 3.4 | 3.2 | Ikon, border fokus, placeholder. **Bukan teks informatif** |
| `primary-700` | 6.5 | 6.1 | Teks & link brand |
| `primary-600` | 4.7 | 4.5 | Latar tombol dengan teks putih; teks hanya di atas putih |
| `primary-500`, `earth-500`, `earth-400` | ≤3.5 | ≤3.3 | Dekorasi, chart, ikon. **Bukan teks** |
| `earth-700` | 6.6 | 6.2 | Teks aksen earth |
| `danger` / `danger-700` | 5.4 / 7.2 | 5.2 / 6.8 | Teks & latar tombol teks putih |
| `warning` | 3.3 | 3.1 | Ikon & dot saja. **Dilarang** teks dan **dilarang** teks putih di atasnya |
| `warning-700` / `-800` | 5.3 / 7.7 | 5.0 / 7.3 | Teks peringatan |
| `info` / `info-700` | 5.5 / 7.3 | 5.2 / 6.9 | Teks & latar tombol teks putih |

Pasangan badge yang lolos AA: `danger-800` di `danger-100` (7.1), `warning-800` di `warning-100` (6.8), `info-800` di `info-100` (8.0), `primary-800` di `primary-100` (8.0), `surface-700` di `surface-200` (7.3).

### 3.5 Tipografi

- **Font UI:** `Plus Jakarta Sans`, fallback `system-ui` (token `--font-sans`).
- **Angka & kode:** `font-mono tabular-nums` **wajib** untuk NIK, golongan, angka di tabel, dan nilai KPI, supaya kolom rata vertikal.

| Kelas | Ukuran | Pemakaian |
|---|---|---|
| `text-[10px]` | 10px | Micro-chip, ikon di tombol kecil. **Dilarang** untuk kalimat |
| `text-xs` | 12px | Label `<dt>`, meta, badge, caption |
| `text-sm` | 14px | Default isi tabel & form admin |
| `text-base` | 16px | Default halaman karyawan & mobile |
| `text-lg` | 18px | Judul kartu |
| `text-xl` / `text-2xl` | 20 / 24px | Judul section / judul halaman |
| `text-2xl sm:text-3xl` | 24–30px | Nilai KPI |

Berat: `font-semibold`/`font-bold` judul, `font-medium` label, `font-normal` isi, `font-black` **hanya** nilai KPI. ALL-CAPS hanya untuk kode pendek (`RM-1`, `KEU`) dan header kolom tabel matriks MPP; **dilarang** untuk judul section.

### 3.6 Spacing, radius, elevasi

- **Spacing:** skala Tailwind 4px. Default padding kartu `p-4`/`p-5`, jarak antar section `gap-6`.
- **Radius** (mengikuti implementasi):

| Elemen | Radius |
|---|---|
| Badge kecil, micro-chip | `rounded` / `rounded-md` |
| Input, tombol `sm`, kotak ikon | `rounded-lg` (8px) |
| Tombol `md`/`lg`, kartu kecil, strip | `rounded-xl` (12px) |
| Kartu utama, KPI card, modal | `rounded-2xl` (16px) |
| Avatar, pill status | `rounded-full` |

  **Dilarang** `rounded-2xl` pada input teks.
- **Elevasi:** kartu statis `shadow-xs` atau tanpa shadow (border cukup). `shadow-lg`/`shadow-2xl` hanya untuk elemen mengambang: dropdown, modal, drawer, toast, floating action dock.

### 3.7 Motion & z-index

| Token | Durasi | Untuk |
|---|---|---|
| fast | 100ms | hover, toggle |
| base | 200ms | dropdown, tab |
| slow | 300ms | modal, drawer, toast |

Easing `ease-out` saat masuk, `ease-in` saat keluar. Di bawah `prefers-reduced-motion: reduce` hanya transisi opacity yang dipertahankan; `animate-pulse`/slide/scale **wajib** dimatikan (`motion-safe:` prefix).

| z | Lapisan |
|---|---|
| 10 | Header & kolom sticky tabel |
| 20 | Dropdown, combobox |
| 30 | Overlay sidebar mobile |
| 40 | Modal, drawer |
| 50 | Toast, floating action dock |

### 3.8 Breakpoint & grid

Default Tailwind: `sm` 640 · `md` 768 · `lg` 1024 (sidebar terbuka) · `xl` 1280 · `2xl` 1536. Kontainer konten maks. `1440px`; form panjang maks. `768px`. **Wajib** diuji di 375px, 1366px, dan 1920px. Tabel direktori **wajib** muat tanpa scroll horizontal pada 1366px.

### 3.9 Ikonografi

**Font Awesome 6 Free**, style solid (`fas fa-*`), dimuat di `layouts/admin.blade.php`. Satu-satunya set ikon: **dilarang** emoji, SVG ad-hoc, atau set lain. Ikon dekoratif **wajib** `aria-hidden="true"`; tombol ikon tanpa teks **wajib** `aria-label` pada elemen pembungkus. Ukuran ikon mengikuti teks di sekitarnya.

### 3.10 Mode gelap

**Di luar cakupan.** Tidak ada token gelap. **Dilarang** menambahkan kelas `dark:` sampai ada keputusan desain tersendiri.

---

## 4. Anggaran Warna (Color Budget)

### 4.1 Proporsi

- **~60% netral:** canvas `surface-50`, kartu putih.
- **~30% struktur brand halus:** navigasi aktif, tab aktif, tombol utama, header section.
- **≤10% aksen jenuh:** hanya alert Tier 1 dan indikator urgensi.

### 4.2 Empat tingkat warna di tabel padat

| Tier | Jenis data | Perlakuan | Contoh |
|---|---|---|---|
| **1 — Alert** | Pengecualian yang menuntut tindakan: sanksi, BUP/MBT, tugas rangkap | Pill berwarna kuat + ikon. Satu-satunya tempat warna jenuh | Lihat Tabel Status §5.1 |
| **2 — Jangkar struktural** | RM Band (eselon) | Chip pastel lembut, satu kolom saja | §5.2 |
| **3 — Kategori makro** | Strata Karpim/Karpel (≥90% baris seragam) | Teks saja, **dilarang** chip | `font-semibold text-surface-800` vs `font-medium text-surface-500` |
| **4 — Metadata** | Bidang (KEU/TAN/TEK/UMU) | Teks polos pada kolom tabel (`text-xs font-medium text-surface-700`); micro-chip netral hanya pada kartu/drawer yang tidak berkolom | `bg-surface-100 text-surface-600 border-surface-200 text-[10px] font-semibold px-1.5 py-0.5 rounded` |

Aturan: kolom "Status: Aktif" untuk setiap baris **dilarang**. Tampilkan status hanya bila menyimpang dari normal (*exception-driven*).

### 4.3 Persentase

- **Proporsi netral** (mis. komposisi Karpim vs Karpel) → warna netral `surface-700`.
- **Capaian terhadap target** (mis. realisasi formasi) → semantik dengan ambang tertulis: **≥90% primary, 70–89% warning, <70% danger**. Ambang lain harus didefinisikan di DOMAIN.md untuk modul terkait.

---

## 5. Tabel Status Induk

Satu-satunya acuan warna status. Status baru **wajib** ditambahkan di sini lebih dulu sebelum dipakai di view. Label pada kolom "Label" adalah teks yang tampil ke pengguna.

### 5.1 Status siklus & pengecualian

| Status | Label | Latar | Teks | Border / dot | Ikon | Tier |
|---|---|---|---|---|---|---|
| Sanksi disiplin aktif | Masa Sanksi | `danger-50` | `danger-700` | `danger-200` | `fa-gavel` | 1 |
| Sanksi akan berakhir | Sanksi Berakhir | `danger-50` | `danger-700` | `danger-200`, `motion-safe:animate-pulse` | `fa-gavel` | 1 |
| Mencapai BUP (56 th) | Pensiun BUP | `danger-100` | `danger-800` | `danger-200` | `fa-user-clock` | 1 |
| Masa Bebas Tugas (H-6 bulan) | MBT | `warning-100` | `warning-800` | `warning-200` | `fa-hourglass-half` | 1 |
| Menjelang MBT ≤6 bulan | Menjelang MBT | `warning-50` | `warning-800` | `warning-200` | `fa-hourglass-start` | 1 |
| Tugas rangkap (Plt/Pj/Diperbantukan) | Plt · Pj · Diperbantukan | `info-50` | `info-800` | `info-200` | `fa-layer-group` | 1 |
| Kursi formasi melebihi kuota | Melebihi kuota | `danger-50` | `danger-700` | `danger-200`, dot `danger` | — | 1 |
| Kursi formasi ada lowongan | Ada lowongan | `warning-50` | `warning-800` | `warning-200`, dot `warning` | — | 1 |
| Kursi formasi penuh | Penuh | `primary-100` | `primary-800` | dot `primary-600` | — | 3 |
| Kursi formasi belum terisi | Kosong | `surface-100` | `surface-600` | `surface-200`, dot `surface-400` | — | 3 |
| Formasi kosong >90 hari | Kritis | `danger-100` | `danger-800` | dot `danger` | — | 1 |
| Formasi kosong 30–90 hari | Perhatian | `warning-100` | `warning-800` | dot `warning` | — | 1 |
| Formasi kosong <30 hari | Baru Kosong | `primary-100` | `primary-800` | dot `primary-600` | — | 3 |
| Menunggu persetujuan / draft | Menunggu | `warning-100` | `warning-800` | — | `fa-clock` | 3 |
| Aktif menjabat | Aktif Menjabat | `primary-100` | `primary-800` | — | — | 3 |
| Selesai / riwayat | Selesai | `surface-200` | `surface-700` | — | — | 3 |
| Dibatalkan / ditolak | Dibatalkan | `surface-100` | `surface-600` | `surface-300` | `fa-ban` | 3 |
| Memenuhi syarat (compliance) | Memenuhi Syarat | `primary-50` | `primary-800` | `primary-200` | `fa-check` | 3 |
| Butuh dispensasi | Perlu Dispensasi | `warning-50` | `warning-800` | `warning-200` | `fa-triangle-exclamation` | 1 |
| Terblokir (aturan keras) | Tidak Dapat Diproses | `danger-50` | `danger-800` | `danger-200` | `fa-lock` | 1 |
| Impor karyawan menunggu persetujuan | Menunggu Persetujuan | `warning-100` | `warning-800` | — | `fa-clock` | 3 |
| Impor karyawan disetujui, sedang disimpan | Menyimpan | `info-50` | `info-800` | `info-200` | `fa-rotate` | 3 |
| Impor karyawan tersimpan | Tersimpan | `primary-100` | `primary-800` | — | `fa-check` | 3 |
| Impor tersimpan, sebagian baris dilewati | Tersimpan Sebagian | `warning-50` | `warning-800` | `warning-200` | `fa-triangle-exclamation` | 1 |
| Impor karyawan ditolak atau ditarik | Ditolak | `surface-100` | `surface-600` | `surface-300` | `fa-ban` | 3 |
| Impor karyawan dibatalkan (undo) | Dibatalkan | `surface-100` | `surface-600` | `surface-300` | `fa-rotate-left` | 3 |
| Pengajuan impor kedaluwarsa | Kedaluwarsa | `surface-100` | `surface-600` | `surface-300` | `fa-hourglass-end` | 3 |

### 5.2 Kategori (bukan status)

| Kategori | Perlakuan |
|---|---|
| RM-1 | `bg-orange-50 text-orange-800 border-orange-200` |
| RM-2 | `bg-sky-50 text-sky-800 border-sky-200` |
| RM-3 | `bg-purple-50 text-purple-800 border-purple-200` |
| RM-4 s.d. RM-7 (Karpel) | `bg-surface-100 text-surface-700 border-surface-200` |
| Karpim / Karpel | Teks saja (Tier 3) |
| Bidang KEU/TAN/TEK/UMU | Teks polos pada kolom tabel, micro-chip netral di luar kolom (Tier 4) |

**Kebijakan hue kategori.** Hue `orange`/`sky`/`purple` **hanya** untuk chip RM Band (RM-1/RM-2/RM-3) dan matriks MPP; hue lain (`indigo`, `pink`, `violet`, `cyan`, dst.) tidak dipakai. Kategori non-RM memakai token yang sudah ada, supaya jumlah hue tetap sedikit (anggaran warna §4):

| Kategori | Token | Contoh |
|---|---|---|
| Penampungan, pasokan, topologi campuran | `earth` | Formasi Penampungan (pool), kolom Pasokan MPP, topologi rantai campuran |
| Struktural, talenta, informasi | `info` | Kategori 9-Box, riwayat mutasi, catatan informasi |
| Metadata deskriptif (bidang, jam, kriteria nilai) | `surface` netral | Ubin detail pelatihan, kartu nilai per kriteria |
| Unit, entitas, lokasi | `teal` | KPI jumlah unit |

Tugas rangkap memakai satu perlakuan `info` agar tidak bertabrakan dengan RM-3; jenisnya (Plt/Pj/Diperbantukan) dibedakan oleh label teks. Bidang (KEU/TAN/TEK/UMU) tidak diberi warna per bidang (Tier 4). Gradien dekoratif tidak dipakai. Dijaga penjaga §16.1.

### 5.3 Tema KPI Card

`<x-admin.kpi-card theme="…">` dipilih berdasarkan **arti data**, bukan rotasi warna. Kategori yang sama memakai tema yang sama di semua halaman.

| Kategori data | `theme` | Contoh |
|---|---|---|
| Populasi, realisasi sesuai target | `primary` | Total Karyawan, Formasi Terisi |
| Kepemimpinan/struktural | `info` | Karpim, RM-1/RM-2 |
| Operasional/pelaksana; penampungan & pasokan | `earth` | Karpel, Formasi Penampungan |
| Unit/entitas/lokasi | `teal` | Jumlah Unit Kerja |
| Perlu ditinjau, belum kritis | `warning` | Baris impor berubah |
| Gap, defisit, kritis | `danger` | Kebutuhan Bersih > 0 |
| Netral: komposisi, biaya, anggaran | `neutral` | Komposisi (%), Total Biaya Investasi |

Alias lama (`sky`, `blue`, `rose`, `red`, `amber`, `slate`, `gray`, `emerald`) hanya untuk kompatibilitas; kode baru **wajib** memakai nama di tabel. Maksimal 4–5 kartu per baris dan maksimal satu kartu `danger` per kelompok.

---

## 6. Inventaris Komponen

Semua UI admin **wajib** dibangun dari komponen di bawah. Membuat markup sendiri untuk peran yang sudah ada komponennya **dilarang**. Komponen baru **wajib** punya docblock `{{-- --}}` berisi contoh pemakaian di baris atas file.

### 6.1 Dasar (`resources/views/components/admin/`)

| Komponen | Props kunci | Aturan |
|---|---|---|
| `x-admin.button` | `variant` primary·secondary·ghost·danger, `size` sm·md·lg, `type`, `icon`, `href`, `target`, `once` | Satu `primary` per area aksi. Aksi Livewire **wajib** `target="namaAction"` (disabled + spinner). Form biasa yang mengubah data **wajib** `once`. `danger` **wajib** didahului modal konfirmasi |
| `x-admin.kpi-card` | `title`, `value`, `icon`, `theme`, `subtitle`, `badge`, `href`, `tabKey`, `active`, `coloredValue` | Lihat §5.3. Nilai `surface-900` kecuali kartu berfungsi sebagai tab filter. Kartu statis tanpa efek hover |
| `x-admin.page-header` | `title`, `subtitle`, `icon`, `backUrl`, `breadcrumbs`, slot aksi opsional (di sebelah tombol kembali) | Wajib di setiap halaman admin; satu `<h1>` per halaman |
| `x-admin.module-header` | `title`, `subtitle`, `breadcrumbs` (daftar label; terakhir = halaman aktif), slot aksi | Kepala halaman modul Karyawan, Mutasi, dan Master Formasi; satu `<h1>` per halaman. Dilarang menyalin markup header ke halaman modul |
| `x-admin.flash` | — | Umpan balik `session('success')`/`session('error')` seragam |
| `x-admin.import-status` | `status`, `skipped` | Lencana status impor karyawan dari Tabel Status Induk (§5.1): label, warna, dan ikon. Impor tersimpan dengan baris dilewati tampil "Tersimpan Sebagian". Teks `text-xs`, tanpa huruf kapital |
| `x-admin.row-menu` | `label`, `text` | Menu kebab per baris (target 32×32px); `text` mengubahnya menjadi tombol berlabel untuk menu tingkat halaman. Pola ARIA menu button: Enter/Spasi/Panah membuka dan memfokuskan butir, Panah/Home/End berpindah, Esc menutup dan mengembalikan fokus, Tab menutup; butir menu `role="menuitem" tabindex="-1"`. Panel `fixed` (membuka ke atas bila ruang kurang, tidak terpotong tabel); isi memakai butir menu modul |
| `x-admin.filter-select` | `id`, `model`, `label`, `all-label`, `options`, `icon`, `show-label`, `label-icon`, `clearable`, `value` | Dropdown filter pada panel filter toolbar tabel Livewire. `show-label` menampilkan label terlihat di atas kolom (bawaan: label hanya untuk pembaca layar); `label-icon` memberi label gaya Daftar Karyawan (kecil, tebal, berikon); `clearable` menampilkan tombol "×" untuk menghapus pilihan itu saja (memerlukan `value`) |
| `x-admin.table.pagination` | `paginator`, `noun`, `per-page-options` | Footer tabel Livewire: baris per halaman, rentang, penomoran. Komponen Livewire memakai `WithPagination` dan properti `$perPage` (diklem ke pilihan yang diizinkan). Id pilihan jumlah baris diturunkan dari nama halaman paginator, sehingga beberapa tabel dapat berbagi satu halaman. **Dilarang** area gulir di dalam tabel (`max-h` + `overflow-y`): halaman yang panjang diselesaikan dengan paginasi, bukan gulir bersarang |
| `x-admin.form.input` | `name`, `label`, `type`, `required`, `icon`, `hint` | Label selalu terlihat, error inline di bawah field |
| `x-admin.form.select` | — | Untuk ≤15 opsi statis |
| `x-admin.searchable-select` | `options`, `model`, `placeholder`, `icon`, `hasAllOption` | Untuk opsi banyak (>15) |
| `x-admin.employee-combobox`, `x-admin.unit-combobox` | — | Pemilihan karyawan/unit; jangan membuat pencarian sendiri |
| `x-admin.search-input` | `model`, `placeholder`, `shortcut` (`/`), `debounce` (`300ms`) | Debounce **wajib** 300ms |
| `x-admin.file-upload` | `wireModel`, `accept`, `label`, `helper`, `errorKey` | `accept` **wajib** spesifik (MIME); progres & nama berkas terlihat |
| `x-admin.table.card` | `notice` | Cangkang kartu tabel data Livewire; menampilkan pemberitahuan hasil aksi (`tableNotice`) di bagian atas |
| `x-admin.table.toolbar` | `table-id`, `filters`, `filter-values`, `has-active-filters`, `search`, `search-placeholder`, `search-shortcut`, `search-model`, `reset-action` | Baris pencarian dan filter dalam kartu tabel (§7.5), mengikuti Daftar Karyawan: kolom pencarian selebar sisa baris (debounce 300ms, maks. 100 karakter), tombol **Filter Lanjutan** berhitungan, dan **Reset Filter** (hanya saat ada pencarian atau filter aktif); kedua tombol berukuran sama (`h-10`, `sm:w-40`). Tombol membuka panel dengan label terlihat dan ikon (pilihan dapat dihapus dengan "×", rentang `Dari Tanggal`–`Sampai Tanggal`, saklar ya-tidak); panel tertutup secara bawaan, `Esc` menutupnya dan mengembalikan fokus ke tombol. Baris "Filter Aktif": chip yang dapat dihapus satu per satu; menghapus semuanya cukup dengan **Reset Filter** (tidak ada tautan penghapus kedua). Filter `withoutControl()` (mis. status yang dikendalikan kartu KPI) hanya tampil sebagai chip; tabel tanpa filter hanya menampilkan pencarian |
| `x-admin.table.header` | `title`, `paginator`, `noun`, `export`, slot opsional `actions` | Bilah judul tabel di bawah toolbar (§7.5), mengikuti Daftar Karyawan: judul opsional (tabel sematan tanpa judul), lencana jumlah data ("319 Riwayat"), rentang ("Menampilkan 1 – 15 dari 319", mulai lebar `md`) dalam satu wilayah live permanen (`role="status"`), dan **Ekspor Excel** dengan lencana jumlah di kanan bila modul mengekspor; slot opsional `actions` untuk tombol tambahan milik modul di sisi kanan (sebelum Ekspor Excel; belum dipakai layar mana pun) |
| `x-admin.table.head` | `columns`, `sort-field`, `sort-direction`, `selectable`, `page-keys`, `selected`, `select-all-matching`, slot opsional `leading` | Baris header dari deklarasi `Column`: tombol urut dengan `aria-sort`, perataan, dan kotak centang tri-state "Pilih semua di halaman ini" |
| `x-admin.table.bulk-bar` | `actions`, `selected`, `select-all-matching`, `selected-count`, `matching-count`, `page-keys` | Bar aksi massal: "N terpilih" (`aria-live`), tautan "Pilih semua N data yang sesuai", "Batalkan pilihan". Aksi destruktif memakai konfirmasi yang menyebut jumlah baris |
| `x-admin.table.select-cell` | `key`, `label` | Sel kotak centang baris; `aria-label` memuat nama baris |
| `x-admin.table.skeleton` | `columns`, `target` | Kerangka pemuatan saat `wire:loading`; tabel tidak pernah kosong sesaat |
| `x-admin.table.notice` | `notice` | Pemberitahuan sukses (`role="status"`) atau gagal/peringatan (`role="alert"`) di dalam kartu tabel |
| `x-admin.table.th`, `.sort-th`, `.empty-state` | `empty-state`: `colspan`, `message`, `icon`, slot aksi | Tabel semantik `<table>` + `<th scope="col">`. `th` = header urut Livewire (tombol); `sort-th` = header berbasis tautan untuk halaman yang masih dirender controller, dipensiunkan saat modul itu dipindah ke Livewire |
| `x-pagination-ui` | `data` | Tampilkan "Menampilkan 1–20 dari 1.240 data" |
| `x-toast` | — | Satu instance di layout |
| `x-highlight` | `text`, `term` | Sorot kata kunci hasil pencarian |
| `x-admin.employee-profile-card` | — | Ringkasan identitas karyawan di alur mutasi & drawer |
| `x-admin.personnel-drawer` | `title`, `subtitle`, `close-action`, `employees`, `count`, `show-nik`, `show-placement`, `hide-list`; slot `badges`, `header`, `footer` | Drawer kanan rincian personil (§7.6) untuk drilldown MPP dan Master Formasi. NIK tidak tampil sebagai teks kecuali `show-nik` (hanya MPP, yang akses bukanya dicatat); tautan profil tetap memakai NIK. Daftar berupa kartu ramping: baris atas berisi identitas di kiri dan golongan di kanan; di bawah garis pemisah, jabatan dan unit di kiri sejajar dengan tanggal MBT dan pensiun di kanan; golongan (`font-mono tabular-nums`, bukan badge, §5) dan tanggal MBT/pensiun; nama karyawan adalah tautan ke profil (tab baru), tanpa tombol "Lihat Profil" terpisah; karyawan yang sudah tidak terdaftar tampil sebagai teks biasa. Pencarian di panel lokal (Alpine). |

### 6.2 Domain mutasi (`components/admin/mutasi/`)

| Komponen | Peran |
|---|---|
| `compliance-guard` | Menampilkan hasil cek aturan masa jabatan & promosi, serta input dispensasi. Lihat §7.2 |
| `employee-selector` | Pilih karyawan yang dimutasi |
| `node-card` | Satu simpul pada rantai mutasi |
| `talent-drawer` | Drawer kandidat pengganti |
| `review-modal` | Ringkasan sebelum eksekusi mutasi |

### 6.3 Matriks state

Setiap komponen interaktif **wajib** mendefinisikan keenam state:

| State | Tombol primary | Input |
|---|---|---|
| Default | `bg-primary-600 text-white` | `border-surface-300 bg-white` |
| Hover | `bg-primary-700` | `border-surface-400` |
| Focus (keyboard) | `focus-visible:ring-2 ring-primary-500 ring-offset-2` | `ring-2 ring-primary-500/20 border-primary-600` |
| Active | `bg-primary-800` | — |
| Disabled | `opacity-60 cursor-not-allowed` | `bg-surface-100 text-surface-400` |
| Error | — | `border-danger ring-danger/20` + pesan `text-danger-700 text-sm` |

Fokus memakai `focus-visible:` (bukan `focus:`) agar ring muncul untuk keyboard, tidak untuk klik mouse. **Dilarang** `outline-none` tanpa pengganti.

---

## 7. Pola UI

### 7.1 Form

- Form panjang dipecah menjadi 2–3 kartu bersegmen dengan judul bernomor + deskripsi satu baris; alur multi-langkah memakai stepper.
- Input tinggi `h-10`/`h-11`, `px-3.5`, `rounded-lg`, border `surface-300`.
- Field wajib diberi tanda `*` setelah label + `aria-required="true"`; legenda "* wajib diisi" sekali di atas form.
- `wire:model.blur` untuk field biasa; `wire:model.live.debounce.300ms` hanya untuk field yang memicu cek langsung (mis. NIK duplikat).
- **Read-only vs disabled:** nilai turunan yang tetap perlu dibaca (mis. RM hasil klasifikasi jabatan) memakai `readonly` dengan latar `surface-50`; `disabled` hanya untuk kontrol yang memang tidak berlaku.
- Footer aksi: Batal (`secondary`/`ghost`) di kiri, aksi utama (`primary`) di kanan; sticky di bawah pada form panjang.

### 7.2 Validasi aturan bisnis: blokir vs peringatan

Aturan kepegawaian (masa jabatan, kelayakan promosi, BUP/MBT, formasi terisi) dibagi tiga tingkat. Logika penentuannya berada di service layer (lihat DOMAIN.md); view hanya menampilkan.

| Tingkat | Kapan | Tampilan | Aksi utama |
|---|---|---|---|
| **Lolos** | Semua syarat terpenuhi | Strip ringkas `primary-50` + ikon centang, satu baris | Aktif |
| **Perlu dispensasi** | Syarat tidak terpenuhi tetapi boleh dikecualikan | Panel `warning-50`, centang dispensasi + alasan tertulis wajib | Aktif setelah dispensasi + alasan diisi |
| **Terblokir** | Aturan keras (mis. formasi tujuan masih terisi) | Panel `danger-50` + ikon kunci, kontrol dispensasi terkunci, penjelasan apa yang harus diselesaikan dulu | Nonaktif, dengan alasan terlihat di dekat tombol |

Aturan: tombol yang nonaktif karena blokir **wajib** menjelaskan alasannya di dekat tombol (bukan hanya tooltip) dan diumumkan lewat `aria-live="polite"`. Bila ada beberapa masalah, tampilkan ringkasan jumlah di atas sebelum rinciannya. Dispensasi dan alasannya **wajib** tercatat di `job_histories` (SECURITY.md).

### 7.3 Tampilan data (definition list)

Data detail ditampilkan sebagai `<dl>` dalam grid 2–3 kolom: `<dt>` `text-xs font-medium text-surface-500`, `<dd>` `text-sm font-semibold text-surface-900`, pemisah opsional `border-b border-surface-100`. **Dilarang** membungkus setiap field dengan kotak berlatar/border sendiri.

### 7.4 Halaman profil karyawan

1. **Hero:** avatar 80–96px (foto asli; tanpa foto → inisial), nama `<h1>`, strata, NIK `font-mono` + tombol salin, jabatan & unit. Aksi di kanan atas: Edit, Ajukan Mutasi, Cetak.
2. **Tab horizontal (Alpine):** Biodata · Kepegawaian & Formasi · Riwayat Jabatan · Pelatihan & Kinerja. Tab mengikuti pola ARIA tabs: `role="tablist"` berlabel, `role="tab"` dengan `aria-controls`, `aria-selected` dan roving `tabindex` (hanya tab aktif yang dapat di-Tab), panel `role="tabpanel"` + `aria-labelledby`; panah kiri/kanan, Home, dan End memindahkan sekaligus mengaktifkan tab. Dijaga penjaga §16.1.

### 7.5 Tabel data

- **Satu sistem untuk semua tabel.** Setiap tabel data Livewire dibangun dari trait `WithDataTable` (ENGINEERING.md §2.3) dan komponen `x-admin.table.*` (§6): pencarian, urutan, filter, ekspor, paginasi, dan aksi massal berperilaku sama di semua modul. **Dilarang** membuat markup toolbar, header, atau footer tabel sendiri pada tabel baru.
- **Pencarian dan filter.** Pola tabel mengikuti Daftar Karyawan supaya semua tabel terlihat dan terasa sama: baris pencarian dengan kolom pencarian ringkas, tombol **Filter Lanjutan** (hitungan filter aktif pada tombol) dan **Reset Filter** berukuran sama; panel filter berlabel terlihat dengan ikon, setiap pilihan dapat dihapus sendiri dengan "×"; baris **Filter Aktif** berisi chip yang dapat dihapus satu per satu (**Reset Filter** satu-satunya aksi hapus-semua; **dilarang** menambah tautan penghapus kedua seperti "Hapus Filter Tambahan"); lalu bilah judul tabel (`x-admin.table.header`) dengan lencana jumlah data, rentang yang tampil, dan Ekspor Excel di kanan. Filter langsung diterapkan saat kolom berubah (tanpa tombol terapkan). Modul tidak membuat wilayah pengumuman jumlah sendiri. **Dilarang** menampilkan keadaan yang sama di tiga tempat (kartu KPI, kolom filter, dan chip): filter yang dikendalikan kartu KPI hanya tampil sebagai chip.
- Daftar berbasis koleksi (mis. Lowongan Jabatan, Formasi) memakai komponen tampilan yang sama tanpa trait.
- Daftar Karyawan: kartu KPI berada di dalam komponen tabel (angka dan tautan selalu searah dengan tabel), dan hapus massal dibatasi 100 karyawan per aksi.
- Header sticky; kolom identitas sticky saat scroll horizontal.
- **Header kolom seragam.** Tipografi header (11px, tebal, huruf kapital, `tracking-wider`, `text-surface-700`, tanpa monospace) melekat pada `x-admin.table.th`; `<thead>`/`<tr>` **dilarang** mengatur huruf kapital atau ukuran teks, dan modul **dilarang** menulis `<th>` sendiri (kecuali matriks MPP berkelompok dan `scope="row"`). Tabel padat di modal tetap memakai komponen yang sama dengan padding sendiri. Dijaga penjaga §16.1.
- Urutan kolom makro ke mikro: **Karyawan → Jabatan & Unit → Strata → Eselon → Golongan → Aksi**, maksimal 6 kolom data. Data sekunder (NIK, unit) di sub-baris.
- **Pengecualian Daftar Karyawan (7 kolom data).** Urutan: Karyawan → Jabatan & Unit → **Bidang** → Strata → Eselon → Golongan → **Masa Kerja** → Aksi. Alasannya: (1) admin SDM bekerja per Bidang, jadi Bidang memerlukan kolom sendiri yang dapat dipindai (teks polos kode singkat, bukan chip, agar tidak menjadi "badge soup" dan chip RM Band tetap menjadi satu-satunya jangkar warna; nama lengkap pada tooltip), bukan sub-baris yang sudah memuat unit, sanksi, tugas rangkap, dan penanda pensiun; (2) Masa Kerja mendukung keputusan promosi dan mutasi. Bidang bukan kolom tersimpan sehingga **tidak dapat diurutkan**; penyaringan per Bidang memakai filter Bidang. Penanda status yang melekat pada orangnya ("Masa Pensiun (BUP)", "Masa Bebas Tugas (MBT)", "Menjelang MBT", dan sanksi) tampil pada sel Karyawan, hanya pada baris yang menyimpang (exception-driven): BUP, MBT, dan Menjelang MBT berupa chip kode singkat + ikon di samping NIK (label singkat dari §5.1; teks lengkap pada tooltip dan `sr-only`, **dilarang** hanya ikon karena arti tidak boleh bergantung pada warna/ikon saja), sedangkan sanksi tetap lencana terbaca di baris sendiri di bawah NIK; sel Jabatan & Unit hanya memuat penanda jabatan (Fungsional Pool, tugas rangkap). Tanggal pensiun lengkap ada pada tooltip lencana dan profil karyawan, bukan kolom sendiri. **Batas penambahan kolom adalah §3.8: tabel wajib muat tanpa scroll horizontal pada 1366px**; kolom baru hanya boleh ditambah bila batas itu tetap terpenuhi (padding sel `px-4`, teks unit boleh membungkus, tanpa `truncate` bermaksimum lebar besar). Tabel lain tetap maksimal 6 kolom data; tidak ada kolom yang nilainya sama di setiap baris (mis. "Status: Aktif").
- Sort per kolom dengan indikator arah + `aria-sort`.
- Skeleton saat `wire:loading` pada ganti filter/halaman. **Dilarang** tabel kosong sesaat tanpa indikasi.
- Empty state memakai `x-admin.table.empty-state` dengan kalimat yang mengarahkan tindakan.
- Export di kanan atas tabel, posisinya sama di semua modul.
- Aksi baris: satu menu kebab (`…`, `aria-label="Aksi untuk {nama}"`).
- Teks panjang dipotong `truncate` dengan `title` berisi teks penuh; nama karyawan dan jabatan **dilarang** dipotong pada 1366px.

**Seleksi & aksi massal**

- Kolom checkbox khusus di kiri (`w-10`), checkbox header untuk pilih semua di halaman (tri-state lewat `indeterminate`). Kotak centang **wajib** `<input type="checkbox">` sungguhan dengan `aria-label` berisi nama baris; **dilarang** `<div>` yang digayakan seperti kotak centang. Area klik sel boleh lebih lebar dari kotak untuk pengguna mouse.
- Tabel yang barisnya mewakili orang (Daftar Karyawan) memakai pola **avatar → kotak centang**: kolom pilihan menampilkan avatar 36px (foto asli, tanpa foto → 2 huruf inisial) dan berubah menjadi kotak centang bulat saat baris disorot (hover), menerima fokus keyboard, atau tercentang. Baris terpilih menampilkan lingkaran `primary-600` berisi centang. Pada layar sentuh avatar tampil dan ketukan pada avatar memilih baris (ketukan pada baris membuka profil). Kotak centang tetap `<input type="checkbox">` asli di atas avatar sehingga Spasi, Shift+Spasi, dan pembaca layar tidak terpengaruh. Pola ini adalah komponen bersama: `<x-admin.table.select-cell :employee="$model">` bersama `<x-admin.table.head avatar-leading>`; tabel baru yang barisnya orang **wajib** memakainya, bukan membuat sel sendiri. Saat ada pilihan, baris yang belum dipilih tetap menampilkan avatarnya (bukan lingkaran kosong); hanya baris terpilih berubah menjadi lingkaran `primary-600` berisi centang, dan hover menampilkan pratinjau centang hijau muda.
- Tabel non-orang (pelatihan, evaluasi, riwayat jabatan) memakai kotak centang yang tersamar (`opacity-0`) dan muncul dengan aturan pemicu yang sama; pada perangkat sentuh (`hover: none`) selalu tampak. Kotak centang header "pilih semua di halaman ini" selalu tampak sebagai pintu masuk seleksi.
- Shift+klik memilih rentang; Ctrl/Cmd+klik toggle per baris; Esc membatalkan seleksi. Setiap pintasan mouse **wajib** punya padanan keyboard: Spasi pada baris terfokus untuk toggle, Shift+Spasi untuk rentang.
- Baris terpilih: `bg-surface-50` + `border-l-4 border-l-primary-600`. Jangan membanjiri baris dengan hijau.
- Saat ≥1 baris terpilih, tampilkan action bar dengan jumlah terpilih ("12 terpilih"), diumumkan via `aria-live`. Aksi destruktif massal tetap lewat modal konfirmasi yang menyebut jumlahnya.

**Data padat (matriks)**

Kolom pertama sticky, baris ringkasan yang bisa di-expand, kontrol "Atur Kolom", highlight baris+kolom saat hover, dan toggle "Tampilkan selisih" untuk perbandingan.

### 7.6 Modal, drawer, konfirmasi

- Modal: `role="dialog"`, `aria-modal="true"`, `aria-labelledby`; fokus pindah ke modal saat dibuka, terkunci di dalamnya, kembali ke pemicu saat ditutup; Esc menutup.
- Konfirmasi destruktif: judul berupa pertanyaan spesifik, konsekuensi dalam bahasa manusia ("Data karyawan akan dihapus permanen."), tombol `danger` di kanan, Batal netral di kiri dan menjadi fokus awal.
- Drawer kanan (lebar 480–520px) untuk drilldown yang perlu tetap melihat tabel di belakangnya; overlay `bg-surface-950/30` tanpa blur.

### 7.7 Loading & umpan balik

- `wire:loading` **wajib** diberi `wire:target` agar hanya area terkait yang berubah.
- Tombol yang memicu request: disabled + spinner selama proses.
- Toast (kanan atas, `z-50`): sukses `primary`, gagal `danger`; auto-tutup 4–8 detik, bisa ditutup manual, `role="status"` (sukses) atau `role="alert"` (gagal). Pesan gagal yang perlu dibaca lengkap tidak boleh auto-tutup.
- `wire:poll` hanya untuk status yang benar-benar berjalan (mis. impor), **dilarang** pada tabel besar.

### 7.8 Pembagian Alpine vs Livewire

| Alpine.js | Livewire |
|---|---|
| Buka/tutup dropdown, tab, accordion, modal | Semua yang membaca/mengubah database |
| Transisi | Validasi server-side |
| Filter lokal atas data yang sudah dimuat (mis. cari di drawer) | Pencarian/filter yang query ke database |
| State sementara sebelum submit | Aksi approve, simpan, hapus, mutasi |

---

## 8. Pola Modul

Rincian kelas per piksel tinggal di view masing-masing. Bagian ini hanya memuat aturan perilaku.

### 8.1 Man Power Planning

- Satu sumber data: seluruh angka aktual berasal dari data karyawan; tidak ada sakelar "angka acuan korporat" vs "data karyawan".
- Strip filter memuat **Tahun** (horizon T dan T+1) dan **Posisi per** (bulan; Realisasi dihitung pada akhir bulan itu). Bawaan bulan: bulan berjalan untuk tahun berjalan, Desember untuk tahun lalu, Januari untuk tahun mendatang; berganti tahun mengembalikan bulan ke bawaan. Indikator "Menghitung…" tampil selama perubahan filter diproses.
- Label baris ikut tahun dan posisi (`Realisasi per {Bln T}`, `Pensiun sd Des {T}`, `Sisa RBB {T}`, `Kebutuhan {T+1}`), termasuk KPI dan nama berkas export (`mpp_perencanaan_formasi_{T}_{T+1}_posisi_{YYYY-MM}`). Nama bulan memakai singkatan Indonesia (Jan…Des) tanpa bergantung pada locale. Kartu personil drilldown menampilkan tanggal MBT dan tanggal pensiun.
- Toolbar tingkat 1 memuat tombol **Kelola Rencana Pasokan** yang membuka laci editor rencana pasokan tahun terpilih: grid per sumber (RM Band x bidang, input angka tanpa `.live`; untuk CKP Internal, Talent Scouting, dan RBB hanya baris RM-3 yang berupa isian, RM-1/RM-2 tampil "–" dengan keterangan "Hanya formasi RM-3; RM-1 dan RM-2 diisi lewat promosi"), editor dibuka pada tahun dan posisi yang sama dengan matriks dan menampilkan "terealisasi N" di bawah setiap isian RM-3 jalur rekrutmen (karyawan jalur itu yang sudah ditempatkan sebagai Karpim tahun ini s.d. posisi), dengan pemberitahuan "{sumber}: terealisasi melebihi rencana pada …" bila realisasi melebihi angka yang diketik; langkah **Tinjau Perubahan** yang menampilkan jumlah sel berubah dan total per sumber lama → baru, alasan wajib, serta state loading eksplisit pada tombol tinjau dan simpan. Isian yang tidak sah ditolak sudah pada langkah tinjau (aturan yang sama dengan saat menyimpan). Menutup laci (tombol X, Batal, atau klik latar) saat ada isian yang belum disimpan memunculkan konfirmasi "Buang perubahan yang belum disimpan?" dengan pilihan **Lanjut Mengedit** dan **Buang Perubahan**.
- Bila rencana pasokan T atau T+1 belum ada, pemberitahuan nada warning di atas tabel: "Rencana pasokan {tahun} belum diisi. Angka pasokan dihitung 0." dengan tautan pembuka editor.
- Baris Kebutuhan: sel JUMLAH dan TOTAL menampilkan angka bersih tebal (warning bila positif, primary bila negatif); baris kecil "kurang X · surplus Y" hanya muncul bila keduanya ada. Tidak pernah menampilkan "−0". KPI "Kekurangan Formasi {T}" bernilai total kekurangan dengan subjudul surplus dan kebutuhan bersih.
- Baris KPI memakai `x-admin.kpi-card` statis seperti modul lain (tanpa `bottom-bar`): Standar Formasi `info`, Realisasi `primary`, Proyeksi Pensiun `warning` dengan subjudul jendela proyeksi ("Kursi kosong sejak MBT · {bulan setelah posisi} – Des {T+1}"), Kekurangan Formasi `danger` bila > 0. Di bawah judul matriks tampil asal-usul rencana pasokan yang sudah diisi: "Rencana pasokan {tahun} diperbarui oleh {nama} · {tanggal jam}". Tahun tanpa rencana hanya memunculkan pemberitahuan "belum diisi".
- Label RM Band pada header matriks dan legenda berasal dari satu definisi (`ManPowerPlanningService::RM_BANDS`, sesuai DOMAIN.md Bab 4.2); dilarang menulis label RM langsung di view. Lencana jumlah formasi pada tab MPP dihitung dari kursi master, bukan angka tetap.
- Toolbar terintegrasi dua tingkat dalam kartu tabel: tingkat 1 judul + Export Excel (format ekspor tunggal; tanpa CSV dan tanpa Cetak pada layar analitik — lembar Excel sudah siap cetak A4 landscape; Cetak hanya untuk dokumen resmi, lihat §15), tingkat 2 strip filter. **Dilarang** kartu toolbar terpisah di atas tabel.
- Header grup RM tidak boleh patah baris (`whitespace-nowrap`).
- Sel angka bukan 0 hanya bisa diklik (`cursor-pointer`, garis bawah titik-titik) bila barisnya punya rincian (kunci `drill` dari service): baris berbasis data karyawan (Realisasi, Pensiun, Advisor, Diperban) membuka drawer berisi **persis** personil yang dihitung sel itu, dengan pencarian lokal, kartu personel, dan export subset; kartu rincian perhitungan pada drawer (rumus Kebutuhan maupun rencana vs realisasi pasokan) **wajib** memakai satu partial `livewire.admin.partials.mpp-breakdown-card` agar tampilannya seragam; operator matematika (−, +, =) berdiri sebagai kolom sendiri yang rata tengah di antara suku, bukan awalan pada label; baris Sisa CKP / Talent Scouting / RBB membuka rincian **Rencana − Terealisasi = Sisa** beserta daftar karyawan yang merealisasikannya (tanggal penempatan Karpim), dengan catatan "Terealisasi melebihi rencana: N" bila berlebih; sel pasokan bernilai 0 tetap dapat dibuka bila ada rencana atau realisasi; baris Kebutuhan membuka rincian rumus `Formasi − Realisasi + Pensiun − Pasokan = Kebutuhan Bersih` tanpa daftar personil. Sel standar formasi dan Jabatan Prioritas Diisi statis. Sel 0 lainnya statis.
- Kolom JUMLAH/TOTAL tebal `font-mono`, termasuk nilai 0. Teks indikator rata kiri tanpa ikon.
- **Status periode bulanan** (partial `livewire.admin.partials.mpp-period-banner`, tepat di bawah strip filter; status dari `MppPeriodService::stateFor()`): empat keadaan bulan "Posisi per".
  - **Ditutup** (`closed`): banner nada primary berikon gembok, "Periode {Bln YYYY} ditutup oleh {nama} · {tanggal jam} · Revisi {n}" plus baris "Catatan: …" bila ada; aksi **Buka Kembali**.
  - **Draf** (`draft`): banner nada warning, "Draf periode {Bln YYYY} direkam oleh {nama atau sistem} · {tanggal jam}. Belum ditutup."; aksi **Perbarui Draf** dan **Tutup Periode**.
  - **Belum ditutup** (`unclosed`, bulan lampau yang belum pernah direkam): banner nada warning, "Periode {Bln YYYY} belum pernah ditutup: angka dihitung dari data karyawan saat ini."; aksi **Rekam Draf**.
  - **Berjalan** (`live`, bulan berjalan atau mendatang): tanpa banner dan tanpa aksi; angka dihitung langsung dari data karyawan.
  - Tutup dan Buka Kembali memakai panel konfirmasi satu langkah di bawah banner (bukan modal): catatan penutupan opsional; alasan membuka kembali wajib (minimal 10 karakter, ditolak dengan pesan inline `role="alert"` dan panel tetap terbuka). Tombol aksi memakai state loading eksplisit (`wire:loading`); hasil tampil sebagai pesan umpan balik ("Periode {Bln YYYY} ditutup.", "Periode … dibuka kembali sebagai draf.").
  - Bulan ber-snapshot (Ditutup dan Draf) membaca matriks dari salinan tersimpan; drilldown sel personil membaca dari personil snapshot (nama dan status tetap dari data karyawan terkini, "tidak lagi terdaftar" bila NIK sudah tidak ada). Tombol **Kelola Rencana Pasokan** dan pemberitahuan "rencana pasokan belum diisi" disembunyikan karena angkanya sudah dibekukan. Aksi periode hanya dapat dijalankan admin (ditegakkan di server pada setiap aksi).
  - Nama berkas export bulan ber-snapshot disisipi status dan revisi sebelum stempel waktu: `_ditutup_rev{n}` atau `_draf_rev{n}` (mis. `mpp_perencanaan_formasi_{T}_{T+1}_posisi_{YYYY-MM}_ditutup_rev1_{stempel}.xlsx`); bulan tanpa snapshot tidak memakai akhiran.

### 8.2 Monitoring Formasi Kosong

- Filter & pencarian di dalam kartu tabel; strip status urgensi (Semua · Kritis · Perhatian · Baru Kosong) dengan jumlah per status, warna dari §5.1.
- Opsi pengisian per baris ditumpuk vertikal dengan lebar sama: **Mutasi Internal** (`primary`) di atas, **Rekrut Baru** (`secondary`) di bawah.

### 8.3 Mutasi & penempatan

- Alur memakai stepper; setiap langkah menampilkan dampak (formasi asal jadi kosong, rantai pengganti) sebelum eksekusi.
- Cek aturan memakai pola §7.2 lewat `x-admin.mutasi.compliance-guard`.
- Eksekusi selalu lewat `review-modal` yang merangkum siapa pindah ke mana, dispensasi yang dipakai, dan SK.

### 8.4 Direktori karyawan

- KPI card berfungsi sebagai tab filter Semua · Karpim · Karpel.
- Pencarian utama dengan pintasan `/`; filter lanjutan di panel yang bisa dibuka-tutup.
- Kolom mengikuti §7.5 dan tier warna §4.2. Avatar (foto asli bila tersedia, selain itu 2 huruf inisial) berada di kolom pilihan paling kiri dan berubah menjadi kotak centang (§7.5); kolom Karyawan tidak menampilkan foto lagi agar tidak ganda.

### 8.5 Master Data (Master Formasi)

Satu modul, satu entri sidebar ("Master Formasi"), dua bagian pada navigasi seksi di dalam halaman.

0. **Pola daftar → detail.** `Formasi` terdiri dari dua halaman ber-URL sendiri: daftar unit (`/admin/master/formasi`, KPI lintas unit + tabel unit) dan detail unit (`/admin/master/formasi/{unit}`, KPI unit + tabel formasi jabatan). Tombol Kelola adalah tautan ke halaman detail (bukan penggantian isi layar), sehingga URL dapat dibagikan, tombol Kembali peramban bekerja, dan setelah aksi pengguna kembali ke halaman unit yang sama. Halaman detail memuat breadcrumb bertautan (`Core HR › Master Formasi › {Unit}`), tombol Kembali ke Daftar Unit, tanpa pemilih unit (berpindah unit lewat Kembali ke Daftar Unit atau breadcrumb). Tautan lama `?unit=` dialihkan ke halaman detail.
1. **Satu layar berpusat pada unit.** Unit, jabatan, dan kursi adalah entitas data yang terpisah, tetapi pengguna mengelolanya dari satu layar **Formasi**: pilih unit kerja, lalu kelola kursinya. Jabatan tidak pernah dibuat tanpa unit; itu sebabnya tidak ada halaman daftar jabatan atau daftar unit yang berdiri sendiri.
2. **Navigasi seksi** (`x-admin.master.section-nav`): Formasi · Riwayat Perubahan (pekerjaan harian dahulu, tampilan audit terakhir). Tampilan mengikuti tab bergaris bawah modul Mutasi (§8.3). Setiap bagian adalah halaman dengan URL sendiri, maka semantiknya `<nav aria-label="Bagian Master Data">` dengan `aria-current="page"`, **bukan** `role="tab"` (pola §7.4 hanya untuk panel yang berganti dalam satu halaman).
3. **Pengelolaan unit**: Tambah Unit pada header daftar; Ubah / Aktifkan-Nonaktifkan / Hapus Unit tersedia pada menu kebab setiap baris daftar dan pada menu berlabel "Aksi Unit" di header halaman detail. Unit nonaktif tetap tampil (diberi penanda) agar dapat diaktifkan kembali.
4. **Kursi**: ringkasan (jumlah kursi, kuota, terisi, melebihi kuota) di atas tabel kursi. **Tambah Jabatan** adalah satu-satunya tombol penambahan pada tabel, dengan satu formulir: Nama Jabatan (kolom ketik dengan saran), RM Band, Bidang, Kuota. Saran muncul hanya setelah pengguna mengetik minimal dua huruf (bukan daftar yang dapat dijelajahi) dan hanya memuat jabatan Karpim aktif milik unit sejenis (kode unit sama: Bagian dengan Bagian, Kebun dengan Kebun) yang belum ada di unit ini; jabatan penampungan tidak pernah disarankan. Jabatan dari jenis unit lain tetap dapat dipakai dengan mengetik nama persisnya. Nama yang sudah terdaftar memakai jabatan itu (RM Band mengikuti jabatan, tidak diubah); nama baru membuat jabatan baru pada master dan menambahkannya ke unit dalam satu transaksi. Keduanya melalui pratinjau dampak dan tercatat pada riwayat. Dilarang menyajikan daftar seluruh jabatan perusahaan atau dua tombol berdampingan untuk satu tujuan. Menu baris: Ubah Kuota & Bidang, Ubah Jabatan (nama dan RM Band berlaku untuk seluruh unit pemakai), Aktifkan/Nonaktifkan Jabatan, Hapus Formasi Jabatan (satu nama aksi di seluruh layar; butir destruktif dipisah garis dan berwarna `danger`). **Ekspor** (Excel) berada di kanan atas tabel: pada daftar unit di ujung kanan strip filter, pada formasi jabatan di sebelah kiri Tambah Jabatan. Berkas mengikuti pencarian, filter, dan urutan aktif untuk seluruh halaman, memuat nama pengakses dan waktu unduh pada properti dokumen, menetralkan awalan rumus (`=`, `+`, `-`, `@`), dan tidak memuat data pribadi karyawan. Tabel formasi jabatan memakai toolbar dalam kartu yang sama dengan daftar unit: pencarian nama jabatan (`x-admin.search-input`, debounce 300ms), filter RM Band, Bidang, dan Status (Melebihi kuota · Ada lowongan · Kosong · Penuh · Jabatan nonaktif), tombol Reset, dan footer penomoran (`x-admin.table.pagination`: pilihan baris per halaman 10·25·50·100, bawaan 25, rentang "Menampilkan 1–25 dari Y", nomor halaman; pencarian, filter, atau urutan baru kembali ke halaman 1); kartu KPI Melebihi Kuota berfungsi sebagai pintasan filter. Kursi melebihi kuota memakai pill `danger` (§5.1).
5. **Dampak sebelum eksekusi** memakai pola §7.2: panel dampak pada dialog konfirmasi menampilkan **blokir** (`danger`, tombol terapkan nonaktif) dan **peringatan** (`warning`) beserta jumlah karyawan terdampak. Aturan yang sama dijalankan di server; layar hanya mencerminkannya.
6. **Alasan perubahan**: kolom alasan muncul dan wajib (minimal 10 karakter) untuk aksi berisiko (hapus kursi, nonaktifkan, hapus, ganti nama, ubah RM Band).
7. **Dialog bersama** (`x-admin.master.action-dialog`, §7.6) dibuka lewat event `master-action` untuk aksi unit dan jabatan; tombol hapus/nonaktifkan memakai nada `danger`. Setelah aksi, pengguna kembali ke layar Formasi dengan unit yang sama.
8. **Keseragaman dengan modul Karyawan dan Mutasi**: `x-admin.module-header` (breadcrumb "Core HR › Master Formasi", pada Riwayat "Core HR › Master Formasi › Riwayat Perubahan", judul `text-2xl lg:text-3xl font-bold`, subjudul `text-sm`), baris KPI `x-admin.kpi-card`, tabel dengan `x-admin.table.empty-state`, umpan balik lewat `x-admin.flash`, tombol `x-admin.button`, dan aksi baris dalam satu menu kebab (`x-admin.row-menu` + `x-admin.master.menu-item`), bukan deretan tombol.
9. **Riwayat Perubahan** bersifat baca-saja (komponen Livewire `AuditHistory`): toolbar dalam kartu tabel dengan pencarian (`x-admin.search-input`, debounce 300ms), filter jenis data, admin, rentang tanggal, chip filter aktif, tombol Reset, kolom dapat diurutkan, dan penomoran. Filter tersimpan pada URL. Perubahan ditulis dengan istilah bisnis ("Kuota: 1 → 3", "Status: Aktif → Nonaktif"), tanpa data karyawan.
10. **Daftar unit tanpa dropdown pemilih.** Memilih unit dilakukan pada daftar, bukan pada kotak pilihan: kartu tabel dengan toolbar pencarian nama (`x-admin.search-input`, debounce 300ms), filter Wilayah dan Status, tombol Reset, kolom terurut (Unit Kerja, Wilayah, Jabatan, Kuota, Terisi), dan KPI lintas unit di atasnya. **Tambah Unit** adalah aksi utama pada header daftar; Ubah/Nonaktifkan/Hapus Unit ada pada menu kebab setiap baris (bersama Kelola Formasi) dan pada menu "Aksi Unit" di header halaman detail. Bila ada karyawan yang unit kerjanya tidak cocok dengan Master Unit, daftar menampilkan peringatan berisi jumlah karyawan dan nama unit yang tidak dikenali (maksimal tiga, sisanya diringkas). Halaman detail tidak memuat pemilih unit; berpindah unit lewat Kembali ke Daftar Unit atau breadcrumb.
11. **Tabel kursi**: kolom Jabatan, RM Band, Bidang, Kuota, Terisi dapat diurutkan; chip RM Band mengikuti §5.2 (`Position::rmTheme`); status kursi mengikuti §5.1. Angka Terisi >0 dapat diklik dan membuka drawer kanan `x-admin.personnel-drawer` (komponen yang sama dengan drilldown MPP, §7.6) berisi pemegang kursi: nama (tautan ke profil), status karyawan, golongan, serta tanggal MBT dan pensiun; NIK tidak ditampilkan sebagai teks. Setiap pembukaan daftar dicatat pada log akses (SECURITY.md §Audit Trail butir 4a) dan tombol Terisi memakai state loading. Ubah Kuota & Bidang berupa dialog dengan pratinjau dampak, bukan sunting sebaris. Konfigurasi dialog aksi disusun oleh `FormasiDialogs`, bukan di Blade.

---

### 8.6 Riwayat & Persetujuan Impor Karyawan

Layar server-render di bawah modul Karyawan (`/admin/employees/import/batches`), memakai `x-admin.module-header`.

1. **Daftar**: tab status (Semua, Perlu Tindakan, Tersimpan, Ditutup) berisi jumlah, pencarian berkas atau pengaju, dan maksimal 5 kolom (Berkas, Pengaju, Hasil, Status, menu kebab). Hasil ditulis berlabel ("319 baru, 0 diperbarui, 65 dilewati"), bukan angka bergaris miring; baris dilewati memakai `danger-700`. Status memakai `x-admin.import-status`.
2. **Detail**: ringkasan KPI, lalu panel **Baris yang Dilewati** (alasan dikelompokkan, nomor baris, daftar lengkap yang dapat dibuka, tanpa data pribadi). Kolom kanan berisi linimasa keputusan (diajukan, disetujui/ditolak, disimpan, dibatalkan beserta pelaku, waktu, alasan) dan kode verifikasi berkas dengan tombol salin.
3. **Tindakan berisiko**: pembatalan impor berada pada kartu berbingkai `danger` terpisah dari ringkasan, dengan **pemeriksaan kesiapan** ("Siap dibatalkan: 319 dari 319 karyawan belum berubah" atau daftar NIK tersamar yang sudah berubah, tombol dinonaktifkan). Pembatalan yang menyentuh banyak karyawan (ambang `hris.import.typed_confirmation_threshold`) wajib mengetik kata `BATALKAN` pada dialog. Dialog konfirmasi mengikuti §7.6 (Batal menjadi fokus awal, tombol `danger` di kanan).
4. **Keputusan pengajuan**: tombol `primary` "Setujui & Simpan Data" hanya untuk penyetuju (bukan pengaju); menolak/menarik memakai tombol netral yang membuka dialog beralasan.

### 8.7 Pelatihan & Penilaian

Indeks modul Pelatihan dan Penilaian memakai pola yang sama dengan Daftar Karyawan (§8.4); halaman tidak boleh menulis ulang kepala halaman, kartu pembungkus, atau tombol utama.

1. **Kepala halaman**: `x-admin.module-header` dengan breadcrumb "Core HR › Manajemen Pelatihan" / "Core HR › Manajemen Penilaian", tombol utama `x-admin.button` primer ("Tambah Pelatihan" / "Tambah Penilaian"), dan umpan balik lewat `x-admin.flash`. Halaman tidak dibungkus kartu putih.
2. **Kartu KPI sebagai filter**: empat `x-admin.kpi-card` di dalam komponen tabel. Kartu pertama ("Total ...") menghapus filter kartu; tiga lainnya mengaktifkan filter yang dikendalikan kartu (`withoutControl`), ditandai `aria-current`, dan tetap punya `href` cadangan tanpa JS. Angka kartu bersifat tingkat halaman (tidak mengikuti pencarian).
   - Pelatihan: Total Pelatihan · Akan Datang · Sedang Berlangsung · Selesai (status dari tanggal mulai dan akhir terhadap hari ini). Baris kedua berisi ringkasan investasi statis yang mengikuti pencarian dan filter: Total Man Hours, Total Tiket Peserta, Total Biaya Investasi, Rata-rata JPL per Karyawan (dibagi jumlah karyawan terdaftar saat ini).
   - Penilaian: Total Penilaian (subjudul rata-rata nilai tertimbang) · Sangat Baik · Baik · Perlu Pembinaan (kategori Cukup dan Kurang).
3. **Aksi baris**: satu menu kebab (`x-admin.row-menu` + `x-admin.master.menu-item`): Lihat Detail, Ubah, lalu Hapus (danger, dipisah garis). Hapus membuka `x-admin.confirm-delete-modal`, lalu mengirim form DELETE ber-CSRF.

## 9. Visualisasi Data

- Urutan warna seri tetap: `primary-600 → earth-600 → info → primary-300 → earth-300 → surface-500`. **Dilarang** palet bawaan library.
- Satu kategori = satu warna di semua chart (mis. Karpim selalu `info`).
- Lebih dari 6 seri → gabungkan sisanya menjadi "Lainnya" atau ganti jenis chart.
- Setiap chart punya judul, satuan, dan alternatif tabel/teks ringkas untuk pembaca layar.
- **9-box:** sumbu Kinerja (X) × Potensi (Y), tiap sel berlabel nama kotak + jumlah. Gradasi `danger-100` (rendah-rendah) → `surface-100` (tengah) → `primary-200` (tinggi-tinggi). Jangan memakai merah-kuning-hijau jenuh, dan label tetap wajib karena warna saja tidak cukup.

---

## 10. Aksesibilitas (WCAG 2.1 AA)

- Kontras mengikuti §3.4. Pasangan warna baru **wajib** diukur sebelum dipakai.
- Seluruh fungsi bisa dioperasikan dengan keyboard; fokus selalu terlihat (`focus-visible:ring-2`).
- Satu `<h1>` per halaman, heading berurutan, landmark `<nav>`, `<main>`.
- Perubahan dinamis Livewire yang penting (hasil filter, jumlah terpilih, status blokir, toast) diumumkan via `aria-live`.
- Modal, drawer, tab, combobox mengikuti pola ARIA APG (fokus, role, atribut state).
- Target sentuh minimal 44×44px di halaman karyawan/mobile; 32×32px di tabel admin desktop.
- Tabel memakai `<table>`, `<th scope>`, `aria-sort`.
- `prefers-reduced-motion` dihormati (§3.7).

---

## 11. Privasi Data di UI

Selaras SECURITY.md dan UU No. 27/2022 (PDP):

- Kolom yang tidak berhak dilihat suatu peran **tidak dirender sama sekali** (bukan disembunyikan dengan CSS atau blur).
- Data keluarga dan skor evaluasi 9-box hanya tampil di halaman detail untuk peran berwenang, tidak di tabel daftar.
- Export dan cetak dokumen sensitif mencantumkan nama pengakses + waktu.
- Pesan error tidak membocorkan data orang lain (mis. jangan menyebut nama pemilik NIK yang bentrok kepada peran yang tidak berwenang).

---

## 12. Format Data & Lokalisasi

| Data | Format |
|---|---|
| NIK karyawan | **8 digit string**, `font-mono`, pertahankan nol di depan |
| NIK KTP | 16 digit, input dengan mask |
| Golongan | `[Gol]/[Berkala]`, `font-mono tabular-nums` |
| Tanggal | `DD/MM/YYYY`; tanggal panjang `24 September 2026` |
| Waktu | 24 jam `HH:mm` |
| Mata uang | `Rp 4.500.000`, rata kanan di tabel |
| Angka | Pemisah ribuan titik, desimal koma; rata kanan di tabel |
| Durasi | "3 tahun 2 bulan", bukan desimal tahun |

Bahasa antarmuka: Indonesia baku. Struktur `lang/` Laravel disiapkan untuk Inggris.

---

## 13. Microcopy

- Tombol memakai kata kerja spesifik: **Simpan Data**, **Ajukan Mutasi**, **Terbitkan SK**. Hindari Submit, Proses, OK.
- Satu aksi = satu kata di seluruh sistem.
- Pesan error menyebut masalah dan jalan keluar: "NIK sudah terdaftar. Periksa kembali atau buka data karyawan tersebut." Hindari "Error: duplicate entry".
- Pesan blokir menyebut **apa** yang menghalangi dan **apa yang harus dilakukan**, tanpa menyalahkan pengguna.
- **Dilarang** menampilkan nama file dokumentasi internal (`DESIGN.md`, `DOMAIN.md`, dst.) atau istilah internal pengembang di teks yang dilihat pengguna. Rujuk regulasi dengan istilah bisnis resmi: *Pedoman Tata Kelola SDM & Peraturan Kepegawaian*, *Aturan Batas Usia Kerja*, *Standar Grade Karpim/Karpel*, *Regulasi Masa Kerja/Tenure*.
- Istilah teknis kepegawaian (TMT, BUP, MBT, RM) sebaiknya diberi tooltip penjelasan saat pertama muncul di halaman.

---

## 14. Layout & Shell

**Unified Light Shell** (acuan: `layouts/admin.blade.php`):

- **Sidebar:** `bg-white`, `border-r border-surface-200`, collapsible ke mode ikon. Item aktif: `bg-primary-50 text-primary-800 font-semibold border-l-4 border-primary-600`, kotak ikon `bg-primary-100 text-primary-700`, plus `aria-current="page"`. Item non-aktif: `text-surface-600`, hover `bg-surface-100 text-surface-900`.
- **Satu entri sidebar per modul.** Bagian-bagian modul dijangkau lewat navigasi seksi atau tab di dalam halaman (contoh: Mutasi §8.3, Master Formasi §8.5), bukan dengan menambah entri sidebar. Entri aktif untuk seluruh halaman modul.
- **Topbar:** `bg-white/95 backdrop-blur-md border-b border-surface-200`; pencarian `bg-surface-50`, fokus `bg-white`.
- **Identitas:** "Talenta Hub" + "PTPN IV Regional V" dengan logo resmi.
- Brand hijau dibawa lewat aksen fungsional (tombol utama, navigasi aktif, stepper), bukan blok gelap pada chrome.
- **Layout karyawan** (`layouts/user.blade.php`): satu kolom, mobile-first, target sentuh 44px.

---

## 15. Halaman Sistem & Cetak

- **403 / 404 / 419 / 429 / 500 / 503** memakai `errors/layout.blade.php`: layout **mandiri** dengan identitas visual yang sama (logo, token, font) tetapi tanpa sesi, database, atau Livewire, supaya halaman galat tetap tampil ketika komponen itu yang bermasalah. Tiap halaman menjelaskan apa yang terjadi dalam bahasa pengguna, memberi satu jalan keluar (Kembali ke Beranda / Coba Lagi / Muat Ulang), dan menampilkan kode serta waktu kejadian untuk dilaporkan ke Admin SDM. Pesan pengecualian asli **tidak pernah** ditampilkan, dan pesan tidak boleh menjanjikan hal yang tidak dapat dijamin (mis. "data aman"). Diuji di `ErrorPagesTest`, termasuk tanpa database.
- **Sesi habis:** modal dengan opsi perpanjang agar isian form tidak hilang, bukan redirect mendadak.
- **Cetak (SK, surat tugas, profil):** `@media print` tersendiri: sembunyikan sidebar/topbar/tombol, layout A4 dengan kop resmi dan nomor halaman, font serif boleh dipakai, QR verifikasi untuk SK.

---

## 16. Checklist Review Desain (sebelum merge)

### 16.1 Penjaga otomatis

`tests/Unit/ViewDesignGuardTest.php` memindai seluruh `resources/views` (komentar diabaikan) dan string PHP di `app/`, dan berjalan bersama `php artisan test`. Semua aturan **ketat** (0 pelanggaran); kegagalan menampilkan daftar `berkas:baris`.

| Kelompok | Aturan |
|---|---|
| Aksesibilitas | Ikon FA dan SVG inline `aria-hidden`; `<th scope>`; `<nav aria-label>`; `<html lang="id">`; dialog = `role="dialog"` + `aria-modal` + `aria-labelledby` + `x-trap`; tab = `role="tab"` + `aria-controls` + `:aria-selected` + `:tabindex`, tablist berlabel, tabpanel `aria-labelledby`, tanpa `<nav aria-label="Tabs">`; elemen interaktif yang menghapus outline wajib punya ring pengganti |
| Token | Tanpa kelas palet bawaan Tailwind (view dan string PHP); hue `orange`/`sky`/`purple` hanya pada baris ber-`RM-n` dan matriks MPP, hue lain tidak dipakai; tanpa hex manual pada kelas; tanpa `dark:` |
| Tipografi & gerak | Tanpa teks <10px; `animate-pulse` hanya sebagai `motion-safe:animate-pulse` |
| Konten | Tanpa nama dokumen internal di teks view maupun string PHP |

Bila aturan baru belum bisa dipenuhi seluruh kode, bereskan dulu kodenya (catat di §17), jangan dilonggarkan dengan baseline atau daftar pengecualian.

### 16.2 Checklist manual

Hal yang belum dapat diperiksa otomatis:

- [ ] Hanya token dari `@theme`. Tidak ada hex manual dan tidak ada `slate`/`emerald`/`red`/`amber`/`blue` baru.
- [ ] Pasangan warna teks baru lolos §3.4; tidak ada `text-surface-400` / `text-warning` untuk teks informatif.
- [ ] Status memakai warna dan label dari Tabel Status §5; status baru sudah ditambahkan ke tabel.
- [ ] Memakai komponen dari §6, bukan markup tiruan.
- [ ] Tombol aksi: loading + disabled; aksi destruktif lewat modal konfirmasi.
- [ ] Tabel: empty state, skeleton, pagination, `<th scope>`, muat di 1366px.
- [ ] Form: label, tanda wajib, error inline, validasi server-side.
- [ ] Keyboard: semua bisa dicapai, fokus terlihat, modal mengunci & mengembalikan fokus.
- [ ] Diuji di 375px, 1366px, 1920px (otomatis lewat Playwright, lihat ENGINEERING.md §3.6) dan tanpa pelanggaran aksesibilitas `serious`/`critical` pada pindai axe-core.
- [ ] Microcopy sesuai §13, tanpa istilah internal pengembang.

---

## 17. Utang Desain (Kode Lama yang Belum Sesuai)

Daftar ini dipakai untuk perbaikan bertahap. **Jangan meniru pola di bawah** pada kode baru; saat menyentuh file terkait, perbaiki sekalian.

| # | Masalah | Lingkup (28/10/2026) | Perbaikan | Status |
|---|---|---|---|---|
| D1 | Palet bawaan Tailwind (`slate`, `gray`, `emerald`, `green`, `red`, `rose`, `amber`, `yellow`, `blue`) dipakai langsung | 2.707 kelas, 88 berkas (view + accessor model) | Dipetakan ke token dengan tabel §3.1; 99 teks informatif `surface-400` dinaikkan ke `surface-500`; kini dijaga ketat (0 pelanggaran) | Selesai 28/10/2026 |
| D2 | `text-surface-400` dipakai untuk teks informatif | 137 elemen, 21 view | Diganti ke `text-surface-500`; 400 tersisa hanya pada `<i>` ikon & placeholder | Selesai 28/10/2026 |
| D3 | `kpi-card` memetakan `info` → `blue-*`, `earth` → `amber-*`, default → `emerald-*` | 1 komponen + 17 pemanggil | Tema memakai token `info`/`earth`/`warning`/`danger`/`surface`/`primary`; pemanggil memakai nama tema baku | Selesai 28/10/2026 |
| D4 | Tugas rangkap memakai `purple`, sama dengan RM-3 | Direktori (chip filter + badge baris) | Pindah ke `info` sesuai §5.1 | Selesai 28/10/2026 |
| D5 | `compliance-guard` memakai `emerald-*`/`amber-*`/`rose-*` | 1 komponen | Diganti ke `primary-*`/`warning-*`/`danger-*` | Selesai 28/10/2026 |
| D6 | `x-admin.button` belum punya perlindungan klik ganda; 3 tombol aksi memakai markup sendiri | 1 komponen + 3 pemakai | Prop `target` (disabled + spinner selama action Livewire) dan `once` (disabled setelah form terkirim); 3 tombol dipindah ke komponen; diuji di `AdminButtonTest` | Selesai 28/10/2026 |
| D7 | Seleksi baris direktori hanya bisa lewat mouse: kotak centang berupa `<div>` | Direktori (baris + header) | Kotak centang sungguhan ber-`aria-label`; Spasi memilih, Shift+klik/Shift+Spasi memilih rentang, header tri-state; diuji di `EmployeeCrudTest` | Selesai 28/10/2026 |
| D8 | Halaman error 403/404/500 masih bawaan framework | Global | `errors/layout` mandiri + 403, 404, 419, 429, 500, 503; diuji di `ErrorPagesTest` (termasuk tanpa database) | Selesai 28/10/2026 |
| D9 | KPI "Total Biaya Investasi" pelatihan memakai `theme="purple"` | `admin/trainings/index` | Tema `neutral` (kategori "biaya/anggaran" ditambahkan di §5.3); tema `purple` dihapus dari `kpi-card` | Selesai 28/10/2026 |
| D10 | Ikon Font Awesome dan SVG dekoratif tanpa `aria-hidden`; tombol ikon tanpa nama aksesibel | 601 ikon + 3 SVG, 68 view; 21 tombol | `aria-hidden="true"` pada semua ikon/SVG dekoratif; `aria-label` (+ `aria-expanded` pada toggle dropdown) pada 21 tombol ikon | Selesai 28/10/2026 |
| D11 | `<th>` tanpa `scope`; urutan kolom tidak terbaca | 80 header; `th`, `sort-th` | `scope="col"`; `aria-sort` pada kolom aktif; header sortable Livewire memakai `<button>` (bisa keyboard) | Selesai 28/10/2026 |
| D12 | Navigasi tanpa penanda halaman aktif, `<nav>` tanpa label, menu seluler tanpa nama/state, dokumen berbahasa `en` | `layouts/admin`, `layouts/user`, `auth/login` | `aria-current="page"` pada link aktif; `aria-label` pada semua `<nav>`; toggle menu `aria-expanded`/`aria-label` dinamis; dropdown Data menutup dengan Esc; `<html lang="id">` di tiga shell | Selesai 28/10/2026 |
| D13 | Perubahan dinamis tidak diumumkan pembaca layar | Flash, toast, mutasi, direktori, tabel Livewire | `role="status"`/`role="alert"` pada 14 wadah notifikasi; alasan blokir mutasi dalam wilayah `aria-live` permanen; jumlah baris terpilih lewat wilayah `sr-only`; overlay impor async `role="status"`; jumlah hasil tabel Livewire (formasi lowong, riwayat mutasi) diumumkan. Direktori/pelatihan/evaluasi memuat ulang halaman sehingga tidak butuh wilayah live | Selesai 28/10/2026 |
| D14 | Modal tanpa focus trap, pengembalian fokus, atau semantik dialog | 13 modal | `x-trap.noscroll` + `tabindex="-1"` pada semua dialog; dialog konfirmasi mutasi dan dua modal tambah-cepat diberi `role="dialog"`/`aria-modal`/`aria-labelledby`; Esc pada konfirmasi mutasi; fokus awal pada Batal di dialog hapus; dijaga `test_every_dialog_is_modal_labelled_and_traps_focus` | Selesai 28/10/2026 |
| D15 | `focus:outline-none` tanpa ring `focus-visible` pengganti | 10 dari 65 elemen (sisanya sudah punya pengganti; 2 panel dropdown non-fokus dikecualikan) | Ring `focus-visible:ring-2 focus-visible:ring-primary-500`; dijaga penjaga §16.1 | Selesai 28/10/2026 |
| D16 | Tab profil & tab mutasi tanpa semantik tab, memakai `<nav>` | Profil karyawan (4 tab), mutasi (3 tab) | Pola ARIA tabs lengkap + navigasi keyboard (§7.4); dijaga penjaga §16.1 | Selesai 28/10/2026 |
| D17 | Teks di bawah 10px; `animate-pulse` tanpa `motion-safe:` | 99 teks + 10 pulse, 23 view | Dinaikkan ke `text-[10px]`; pulse memakai `motion-safe:`; dijaga penjaga §16.1 | Selesai 28/10/2026 |
| D18 | Hue `purple`/`orange`/`sky`/`indigo`/`pink` dipakai di luar RM Band | 12 view, 74 baris | Kebijakan hue kategori di §5.2: pool/pasokan → `earth`, struktural/talenta → `info`, metadata deskriptif → `surface`; gradien dekoratif dan warna per-bidang dihapus; tugas rangkap satu perlakuan `info`; dijaga penjaga §16.1 | Selesai 28/10/2026 |

---

## 18. Roadmap (Tidak Normatif)

Gagasan untuk fase berikutnya. Tidak boleh dijadikan dasar implementasi sebelum dipindahkan ke bagian normatif:

- Portal karyawan mobile lengkap (bottom navigation maks. 5 item).
- Mode offline untuk input lapangan (antrean sinkronisasi + label "data terakhir diperbarui").
- Notifikasi multi-kanal (email, WhatsApp) dengan preferensi per pengguna.
- Tur produk ringan per modul dan pusat bantuan in-app.
- Mode kepadatan tabel (ringkas/nyaman).
- Mode gelap.

---

## 19. Riwayat Revisi

| Versi | Tanggal | Perubahan |
|---|---|---|
| v1.0 | — | Struktur dasar: token, komponen, modul |
| v1.1 | — | Aturan pemakaian warna, matriks state, masking, grid/motion, halaman sistem, offline, cetak |
| v1.2 | — | Eksperimen sidebar terang + header gelap |
| v2.0 | 24/09/2026 | Anti-boxitis, standar profil, form tersegmentasi, Unified Light Shell |
| v2.1 | 26/10/2026 | Font Awesome 6, navigasi aktif tint + bar kiri, tema KPI per kategori |
| **v3.0** | **28/10/2026** | **Konsolidasi menjadi satu dokumen tanpa addendum.** Skala `surface` dilengkapi (400/600/800/950) dan `surface-500` digelapkan agar lolos AA; skala `danger`/`warning`/`info` ditambah; tabel kontras terukur menggantikan klaim "sudah divalidasi"; `tailwind.config.js` yang tidak dipakai dihapus (Tailwind v4 membaca `@theme`); NIK diselaraskan ke 8 digit dan debounce ke 300ms; ruang lingkup dibatasi ke modul yang ada; ditambah peran warna, Tabel Status Induk, inventaris komponen, pola blokir vs dispensasi, aksesibilitas modal/tab/seleksi, 9-box, privasi UI, utang desain, dan roadmap terpisah. Prompt khusus IDE dan istilah pemasaran dihapus. |
| v3.1 | 28/10/2026 | Penjaga otomatis `ViewDesignGuardTest` (§16.1) dengan aturan ketat + baseline ratchet; `rose` ditambahkan ke pemetaan `danger` (§3.1); utang D10–D12 diperluas (ikon/SVG, `lang`, navigasi) dan D13–D17 dicatat. |
| v3.2 | 28/10/2026 | Migrasi D1: 2.707 kelas palet bawaan Tailwind dipetakan ke token (tabel §3.1) dan kini dijaga ketat termasuk string PHP; D4, D6, D13 (sebagian), D14 selesai; `x-admin.button` mendapat `target` dan `once`; semua dialog memakai `x-trap`; penjaga bertambah aturan dialog; D18 (hue `purple` di luar RM Band) dicatat. |
| v3.3 | 28/10/2026 | Halaman galat mandiri (§15); kotak centang sungguhan + keyboard pada direktori (§7.5); pola ARIA tabs (§7.4); ring fokus pengganti, teks minimum 10px, dan pulse `motion-safe:` di seluruh view. D7, D8, D15, D16, D17 selesai. Penjaga §16.1 seluruhnya ketat: mekanisme baseline/ratchet dan berkasnya dihapus karena tidak lagi diperlukan. |
| v3.4 | 28/10/2026 | Kebijakan hue kategori (§5.2): `orange`/`sky`/`purple` hanya untuk RM Band, kategori non-RM memakai `earth`/`info`/`surface`/`teal`; kategori KPI netral diperluas ke biaya/anggaran; tema `purple` dihapus; penjaga bertambah aturan hue. Jumlah hasil tabel Livewire diumumkan (D13). D9, D13, D18 selesai: seluruh utang desain §17 tertutup. |
| v3.5 | 31/10/2026 | Modul Master Data/Master Formasi (§8.5): satu entri sidebar dengan navigasi seksi empat bagian, layar Formasi dengan panel dampak, dialog aksi bersama, dan layar Riwayat Perubahan; aturan satu entri sidebar per modul (§14); ruang lingkup §2.2 diperbarui. |
| v3.6 | 31/10/2026 | Penyelarasan antarmuka Master Formasi dengan modul Pegawai dan Mutasi (§8.5 butir 7): header/breadcrumb bersama, KPI, toolbar pencarian dan filter, kolom dapat diurutkan, menu kebab per baris, empty state, dan flash seragam. |
| v3.7 | 01/11/2026 | Master Formasi: toolbar pencarian dan filter pada tabel formasi jabatan, KPI Melebihi Kuota sebagai pintasan filter, menu kebab per baris pada daftar unit, menu "Aksi Unit" berlabel, butir destruktif dipisah garis, panel menu `fixed` yang tidak terpotong tabel, header tabel sticky; komponen `x-admin.filter-select`; kontradiksi pemilih unit dan nama "Hapus Kursi" dibereskan. |
| v3.8 | 01/11/2026 | Master Formasi: peringatan (`warning`) di atas daftar unit bila ada pegawai pada nama unit yang belum terdaftar di Master Unit (nama unit adalah kunci pencocokan Terisi); halaman login memakai stylesheet hasil build, bukan CDN Tailwind; aturan v4: transparansi memakai `bg-white/10` (bukan `bg-opacity-*`) dan ikon Font Awesome yang disembunyikan dibungkus elemen (`.fas` menimpa `.hidden`). |
| v3.9 | 01/11/2026 | Tabel Master Formasi (daftar unit, formasi jabatan) dan Riwayat Perubahan memakai paginasi dengan pilihan baris per halaman (`x-admin.table.pagination`); area gulir dalam tabel dan header sticky dicabut karena menimbulkan gulir bersarang. |
| v3.10 | 01/11/2026 | Master Formasi: Ekspor Excel pada kedua tabel (mengikuti filter, nama pengakses pada properti berkas, netralisasi rumus); menu aksi mengikuti pola ARIA menu button (fokus, panah, Home/End, Esc, Tab) dengan target 32px; subjudul header diringkas agar tabel tampil lebih tinggi; status formasi memakai satu sumber label untuk filter, tabel, dan ekspor. |
| v3.11 | 03/11/2026 | Halaman login: kartu putih mengambang di atas foto perkebunan yang digelapkan (hijau polos di bawah `lg`); panel foto di kiri dan form di kanan mengikuti §6.3 dan §7.1. Logo PTPN IV (48–64px mengikuti tinggi layar) di tengah, tepat di atas judul dan subjudul yang juga rata tengah; label field tetap rata kiri. Panel foto hanya memuat judul dan satu deskripsi. Logo Perkebunan Nusantara belum dipakai untuk sementara. Tanda `*` tidak dipakai karena kedua field login selalu wajib. Dijaga `tests/e2e/login.spec.js` pada 375px, 1366px, dan 1920px. |
| v3.12 | 04/11/2026 | Riwayat & Persetujuan Impor Pegawai (§8.6): status impor didaftarkan di §5.1, komponen `x-admin.import-status`, slot aksi opsional pada `x-admin.page-header`, tab status dan pencarian, panel baris dilewati, pemeriksaan kesiapan dan konfirmasi ketik kata untuk pembatalan impor besar, linimasa keputusan |
| v3.13 | 06/11/2026 | Drilldown pemegang kursi Master Formasi berpindah dari modal tengah ke drawer kanan sesuai §7.6; drawer MPP diekstrak menjadi komponen bersama `x-admin.personnel-drawer` (overlay `bg-surface-950/30` tanpa blur); nama pegawai menjadi tautan profil menggantikan tombol Lihat Profil; drawer Master Formasi menampilkan tanggal MBT/pensiun dengan pencatatan akses; daftar personil berupa kartu ramping dua-kolom tanpa badge golongan (prinsip "Padat tapi terbaca"). |
| v3.14 | 07/11/2026 | Lapisan tabel data bersama menjadi aturan (§6, §7.5): komponen `x-admin.table.card`, `.toolbar`, `.head`, `.bulk-bar`, `.select-cell`, `.skeleton`, `.notice` menggantikan `x-admin.table.wrapper`; toolbar memuat filter tanggal dan baris "Filter Aktif" berisi chip yang dapat dihapus; `x-admin.table.pagination` memberi id unik per tabel agar beberapa tabel dapat tampil di satu halaman. Tabel riwayat jabatan, evaluasi, pelatihan pada profil pegawai dan Riwayat Perubahan Master sudah memakainya. |
| v3.15 | 07/11/2026 | Pencarian dan filter toolbar tabel dirapikan (§6, §7.5): pencarian selebar baris, tombol "Filter" berhitungan yang membuka panel dengan label terlihat, filter aktif sebagai chip yang dapat dihapus saat panel tertutup, Ekspor Excel satu baris dengan pencarian; filter yang dikendalikan kartu KPI (status Riwayat Mutasi) tidak lagi diulang sebagai kolom; `x-admin.filter-select` mendapat prop `show-label`. |
| v3.16 | 07/11/2026 | Toolbar tabel mendapat bilah ringkasan (§6, §7.5): jumlah data ("339 riwayat", atau "12 dari 339 riwayat sesuai filter" saat ada filter) pada wilayah live permanen, chip filter aktif tetap terlihat saat panel terbuka, dan Reset filter pindah ke kanan bilah (tidak lagi di dalam panel); props `result-count`, `total-count`, `noun`; trait `WithDataTable` menyediakan `unfilteredCount`; Riwayat Perubahan, Riwayat Mutasi, dan tabel riwayat pegawai memakainya dan tidak lagi memiliki wilayah pengumuman sendiri. |
| v3.17 | 07/11/2026 | Semua tabel data bersama mengikuti rupa Direktori Pegawai (§6, §7.5): baris pencarian dengan "Filter Lanjutan" berhitungan dan "Reset Filter" di kanan, panel berlabel berikon dengan "×" pada tiap pilihan, baris "Filter Aktif" dengan "Hapus Filter Tambahan", dan komponen baru `x-admin.table.header` (judul, lencana jumlah data, rentang, Ekspor Excel di kanan). Bilah ringkasan "X dari Y sesuai filter" dari v3.16 dihapus; trait `WithDataTable` mendapat `resetPanelFilters()`. `x-admin.filter-select` mendapat prop `label-icon`, `clearable`, `value`. Filter tetap langsung diterapkan. |
| v3.18 | 08/11/2026 | Toolbar tabel dirampingkan (§6, §7.5): kolom pencarian ringkas, tombol "Filter Lanjutan" dan "Reset Filter" berukuran sama, dan tautan "Hapus Filter Tambahan" dihapus karena "Reset Filter" sudah menghapus semua filter; berlaku juga pada Direktori Pegawai. `WithDataTable::resetPanelFilters()` dihapus. |
| v3.19 | 08/11/2026 | Semua daftar data memakai lapisan tabel bersama (§6, §7.5): Penilaian, Pelatihan beserta ringkasannya, Peserta Pelatihan, Riwayat Impor, Lowongan Jabatan, Formasi, dan Direktori Pegawai (ekspor dan hapus massal lewat mesin tabel, dibatasi 100 pegawai per aksi, kartu KPI di dalam komponen). `x-admin.table.toolbar` mendapat prop `search-model` dan `reset-action`; `x-admin.table.head` dan `x-admin.table.header` mendapat slot opsional `leading` dan `actions`. Daftar berbasis koleksi memakai komponen tampilan tanpa trait. |
| v3.20 | 08/11/2026 | Header kolom tabel diseragamkan (§7.5): tipografi header dipindah ke `x-admin.table.th` (tanpa monospace pada kolom angka), seluruh `<th>` tulisan tangan (Statistik, review Mutasi, impor, riwayat impor) memakai komponen yang sama, dan dua penjaga baru §16.1 mencegah `<th>` mentah serta tipografi pada `<thead>`/`<tr>`. Tombol Ekspor Excel Formasi dipindah ke bilah judul. Halaman Statistik Karyawan yang tak terjangkau dihapus; kartu Total Karyawan dan Rata-rata JPL pada Manajemen Pelatihan kini memakai jumlah pegawai terkini (populasi Direktori Pegawai), tanpa syarat rentang tanggal. |
| v3.21 | 08/11/2026 | Pelatihan dan Penilaian disejajarkan dengan modul Pegawai (§8.7): `x-admin.module-header`, `x-admin.flash`, empat kartu KPI yang menjadi filter tabel, menu kebab per baris, dan komponen baru `x-admin.confirm-delete-modal`; penjaga memastikan indeks kedua modul memakai komponen bersama. |
| v3.22 | 08/11/2026 | Direktori Pegawai mendapat kolom Masa Kerja dan Bidang tetap sebagai kolom sendiri (§7.5, pengecualian 7 kolom data); lencana BUP/MBT tetap pada sel Jabatan & Unit; padding sel dirapatkan dan teks unit boleh membungkus agar tabel muat tanpa scroll horizontal pada 1366px. |
| v3.23 | 08/11/2026 | Aturan MBT berubah menjadi 6 bulan sebelum tanggal pensiun (sebelumnya 1 tahun) dan peringatan "Menjelang MBT" 6 bulan (§5.1; DOMAIN.md Bab 5.1); lencana sanksi dan BUP/MBT pindah dari sel Jabatan & Unit ke sel Pegawai (§7.5). |
| v3.24 | 08/11/2026 | Penanda BUP/MBT/Menjelang MBT pada Direktori Pegawai diringkas menjadi chip kode singkat + ikon di samping NIK (label singkat §5.1; teks lengkap pada tooltip dan `sr-only`), baris tidak lagi bertambah tinggi; sanksi tetap pada baris sendiri (§7.5). |
| v3.25 | 08/11/2026 | Kolom Bidang pada Direktori Pegawai menjadi teks polos (Tier 4, §4.2 dan §5.2) tanpa chip; nama lengkap bidang pada tooltip dari `Position::BIDANG_LIST`. |
| v3.7 | 31/10/2026 | Master Formasi digabung menjadi satu layar berpusat pada unit (§8.5): halaman Unit Kerja dan Jabatan & Posisi dihapus; unit dikelola dari pemilih unit, jabatan dari tabel kursi; Tambah Kursi dan Buat Jabatan Baru berupa dialog. Navigasi seksi menjadi Formasi dan Riwayat Perubahan. |
| v3.8 | 01/11/2026 | Master Formasi diselaraskan dengan modul Pegawai dan Mutasi: `x-admin.module-header`, `x-admin.flash`, `x-admin.row-menu` menjadi komponen bersama (dipakai juga oleh Pegawai dan Mutasi); Riwayat Perubahan menjadi komponen Livewire dengan toolbar pencarian/filter/chip dan kolom terurut; pemilih unit searchable dengan ringkasan unit; tabel kursi terurut dengan chip RM Band §5.2, status kursi pada §5.1, drill-down pemegang kursi, dan dialog Ubah Kuota & Bidang; label kolom riwayat memakai istilah bisnis. Istilah antarmuka "kursi" diganti "Formasi Jabatan" agar sejalan dengan modul Mutasi. Layar awal Formasi memakai baris KPI lintas unit (Unit Kerja, Formasi Jabatan, Total Kuota, Terisi) dan kolom Terisi per unit, sama seperti modul Pegawai dan Mutasi. Formasi dipecah menjadi halaman daftar unit dan halaman detail per unit (pola daftar → detail seperti modul Pegawai); breadcrumb mendukung tautan. Dropdown pemilih unit dihapus; daftar unit memakai toolbar pencarian, filter wilayah/status, dan kolom terurut. Tombol Buat Jabatan Baru dan Tambah Formasi Jabatan digabung menjadi satu tombol Tambah Jabatan dengan satu formulir: nama ketik dengan saran muncul setelah dua huruf, dibatasi pada unit sejenis (tanpa jabatan penampungan); nama terdaftar dipakai ulang, nama baru dibuat pada master. |
