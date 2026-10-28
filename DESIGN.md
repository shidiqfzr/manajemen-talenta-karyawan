# DESIGN.md — Design System & UX Guidelines
## Sistem Informasi Manajemen Talenta & Formasi Pegawai — PTPN IV Regional V

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
| **Admin SDM Regional** | Desktop 1366–1920px | Kelola data induk pegawai, impor Excel, proses mutasi & SK, monitoring formasi kosong |
| **Pimpinan SDM / Manajemen** | Desktop & tablet | MPP, ringkasan formasi, talent review 9-box, riwayat mutasi |
| **Pegawai (layout `user`)** | Mobile & desktop | Lihat data diri, pelatihan, evaluasi |

Implikasi: **desktop-first** untuk seluruh modul admin; **mobile-first** hanya untuk halaman pegawai.

### 2.2 Modul dalam cakupan

| Modul | Lokasi view |
|---|---|
| Direktori & Profil Pegawai (termasuk impor) | `admin/employees`, `livewire/admin/employees` |
| Mutasi & Penempatan (tunggal, rantai, riwayat, SK) | `admin/mutasi`, `livewire/admin/mutasi-*` |
| Monitoring Formasi Kosong | `admin/vacancies`, `livewire/tables/job-vacancy-table` |
| Man Power Planning (MPP) | `livewire/admin/man-power-planning` |
| Pelatihan | `admin/trainings` |
| Evaluasi & Talenta 9-box | `admin/evaluations` |
| Master Data (unit, jabatan) | `admin/master` |

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
| `text-base` | 16px | Default halaman pegawai & mobile |
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
| **4 — Metadata** | Bidang (KEU/TAN/TEK/UMU) | Micro-chip netral | `bg-surface-100 text-surface-600 border-surface-200 text-[10px] font-semibold px-1.5 py-0.5 rounded` |

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
| Masa Bebas Tugas (55 th) | MBT | `warning-100` | `warning-800` | `warning-200` | `fa-hourglass-half` | 1 |
| Menjelang MBT ≤12 bulan | Menjelang MBT | `warning-50` | `warning-800` | `warning-200` | `fa-hourglass-start` | 1 |
| Tugas rangkap (Plt/Pj/Diperbantukan) | Plt · Pj · Diperbantukan | `info-50` | `info-800` | `info-200` | `fa-layer-group` | 1 |
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

### 5.2 Kategori (bukan status)

| Kategori | Perlakuan |
|---|---|
| RM-1 | `bg-orange-50 text-orange-800 border-orange-200` |
| RM-2 | `bg-sky-50 text-sky-800 border-sky-200` |
| RM-3 | `bg-purple-50 text-purple-800 border-purple-200` |
| RM-4 (Karpel) | `bg-surface-100 text-surface-700 border-surface-200` |
| Karpim / Karpel | Teks saja (Tier 3) |
| Bidang KEU/TAN/TEK/UMU | Micro-chip netral (Tier 4) |

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
| Populasi, realisasi sesuai target | `primary` | Total Pegawai, Formasi Terisi |
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
| `x-admin.page-header` | `title`, `subtitle`, `icon`, `backUrl`, `breadcrumbs` | Wajib di setiap halaman admin; satu `<h1>` per halaman |
| `x-admin.form.input` | `name`, `label`, `type`, `required`, `icon`, `hint` | Label selalu terlihat, error inline di bawah field |
| `x-admin.form.select` | — | Untuk ≤15 opsi statis |
| `x-admin.searchable-select` | `options`, `model`, `placeholder`, `icon`, `hasAllOption` | Untuk opsi banyak (>15) |
| `x-admin.employee-combobox`, `x-admin.unit-combobox` | — | Pemilihan pegawai/unit; jangan membuat pencarian sendiri |
| `x-admin.search-input` | `model`, `placeholder`, `shortcut` (`/`), `debounce` (`300ms`) | Debounce **wajib** 300ms |
| `x-admin.file-upload` | `wireModel`, `accept`, `label`, `helper`, `errorKey` | `accept` **wajib** spesifik (MIME); progres & nama berkas terlihat |
| `x-admin.table.wrapper`, `.th`, `.sort-th`, `.empty-state` | `empty-state`: `colspan`, `message`, `icon` | Tabel semantik `<table>` + `<th scope="col">` |
| `x-pagination-ui` | `data` | Tampilkan "Menampilkan 1–20 dari 1.240 data" |
| `x-toast` | — | Satu instance di layout |
| `x-highlight` | `text`, `term` | Sorot kata kunci hasil pencarian |
| `x-admin.employee-profile-card` | — | Ringkasan identitas pegawai di alur mutasi & drawer |

### 6.2 Domain mutasi (`components/admin/mutasi/`)

| Komponen | Peran |
|---|---|
| `compliance-guard` | Menampilkan hasil cek aturan masa jabatan & promosi, serta input dispensasi. Lihat §7.2 |
| `employee-selector` | Pilih pegawai yang dimutasi |
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

### 7.4 Halaman profil pegawai

1. **Hero:** avatar 80–96px (foto asli; tanpa foto → inisial), nama `<h1>`, strata, NIK `font-mono` + tombol salin, jabatan & unit. Aksi di kanan atas: Edit, Ajukan Mutasi, Cetak.
2. **Tab horizontal (Alpine):** Biodata · Kepegawaian & Formasi · Riwayat Jabatan · Pelatihan & Kinerja. Tab mengikuti pola ARIA tabs: `role="tablist"` berlabel, `role="tab"` dengan `aria-controls`, `aria-selected` dan roving `tabindex` (hanya tab aktif yang dapat di-Tab), panel `role="tabpanel"` + `aria-labelledby`; panah kiri/kanan, Home, dan End memindahkan sekaligus mengaktifkan tab. Dijaga penjaga §16.1.

### 7.5 Tabel data

- Header sticky; kolom identitas sticky saat scroll horizontal.
- Urutan kolom makro ke mikro: **Pegawai → Jabatan & Unit → Strata → Eselon → Golongan → Aksi**, maksimal 6 kolom data. Data sekunder (NIK, unit, bidang) di sub-baris.
- Sort per kolom dengan indikator arah + `aria-sort`.
- Skeleton saat `wire:loading` pada ganti filter/halaman. **Dilarang** tabel kosong sesaat tanpa indikasi.
- Empty state memakai `x-admin.table.empty-state` dengan kalimat yang mengarahkan tindakan.
- Export di kanan atas tabel, posisinya sama di semua modul.
- Aksi baris: satu menu kebab (`…`, `aria-label="Aksi untuk {nama}"`).
- Teks panjang dipotong `truncate` dengan `title` berisi teks penuh; nama pegawai dan jabatan **dilarang** dipotong pada 1366px.

**Seleksi & aksi massal**

- Kolom checkbox khusus di kiri (`w-10`), checkbox header untuk pilih semua di halaman (tri-state lewat `indeterminate`). Kotak centang **wajib** `<input type="checkbox">` sungguhan dengan `aria-label` berisi nama baris; **dilarang** `<div>` yang digayakan seperti kotak centang. Area klik sel boleh lebih lebar dari kotak untuk pengguna mouse.
- Shift+klik memilih rentang; Ctrl/Cmd+klik toggle per baris; Esc membatalkan seleksi. Setiap pintasan mouse **wajib** punya padanan keyboard: Spasi pada baris terfokus untuk toggle, Shift+Spasi untuk rentang.
- Baris terpilih: `bg-surface-50` + `border-l-4 border-l-primary-600`. Jangan membanjiri baris dengan hijau.
- Saat ≥1 baris terpilih, tampilkan action bar dengan jumlah terpilih ("12 terpilih"), diumumkan via `aria-live`. Aksi destruktif massal tetap lewat modal konfirmasi yang menyebut jumlahnya.

**Data padat (matriks)**

Kolom pertama sticky, baris ringkasan yang bisa di-expand, kontrol "Atur Kolom", highlight baris+kolom saat hover, dan toggle "Tampilkan selisih" untuk perbandingan.

### 7.6 Modal, drawer, konfirmasi

- Modal: `role="dialog"`, `aria-modal="true"`, `aria-labelledby`; fokus pindah ke modal saat dibuka, terkunci di dalamnya, kembali ke pemicu saat ditutup; Esc menutup.
- Konfirmasi destruktif: judul berupa pertanyaan spesifik, konsekuensi dalam bahasa manusia ("Data pegawai akan dihapus permanen."), tombol `danger` di kanan, Batal netral di kiri dan menjadi fokus awal.
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

- Horizon dinamis **T** dan **T+1** dari filter tahun; label baris ikut tahun (`Pensiun sd Des {T}`, `Kebutuhan {T+1}`), termasuk KPI dan nama berkas export (`mpp_perencanaan_formasi_{T}_{T+1}`).
- Mode sumber data **Baseline** (formasi baku) vs **Live** (agregasi pegawai aktif) sebagai segmented control.
- Toolbar terintegrasi dua tingkat dalam kartu tabel: tingkat 1 judul + Export/Cetak, tingkat 2 strip filter. **Dilarang** kartu toolbar terpisah di atas tabel.
- Header grup RM tidak boleh patah baris (`whitespace-nowrap`).
- Sel angka >0 bisa diklik (`cursor-pointer`, garis bawah titik-titik) dan membuka drawer drilldown: rumus `Formasi − Realisasi + Pensiun − Pasokan = Kebutuhan Bersih`, pencarian lokal, kartu personel, export subset. Sel 0 statis.
- Kolom JUMLAH/TOTAL tebal `font-mono`, termasuk nilai 0. Teks indikator rata kiri tanpa ikon.

### 8.2 Monitoring Formasi Kosong

- Filter & pencarian di dalam kartu tabel; strip status urgensi (Semua · Kritis · Perhatian · Baru Kosong) dengan jumlah per status, warna dari §5.1.
- Opsi pengisian per baris ditumpuk vertikal dengan lebar sama: **Mutasi Internal** (`primary`) di atas, **Rekrut Baru** (`secondary`) di bawah.

### 8.3 Mutasi & penempatan

- Alur memakai stepper; setiap langkah menampilkan dampak (formasi asal jadi kosong, rantai pengganti) sebelum eksekusi.
- Cek aturan memakai pola §7.2 lewat `x-admin.mutasi.compliance-guard`.
- Eksekusi selalu lewat `review-modal` yang merangkum siapa pindah ke mana, dispensasi yang dipakai, dan SK.

### 8.4 Direktori pegawai

- KPI card berfungsi sebagai tab filter Semua · Karpim · Karpel.
- Pencarian utama dengan pintasan `/`; filter lanjutan di panel yang bisa dibuka-tutup.
- Kolom mengikuti §7.5 dan tier warna §4.2. Foto hanya bila berkas asli tersedia; tanpa lingkaran inisial di tabel.

---

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
- Target sentuh minimal 44×44px di halaman pegawai/mobile; 32×32px di tabel admin desktop.
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
| NIK pegawai | **8 digit string**, `font-mono`, pertahankan nol di depan |
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
- Pesan error menyebut masalah dan jalan keluar: "NIK sudah terdaftar. Periksa kembali atau buka data pegawai tersebut." Hindari "Error: duplicate entry".
- Pesan blokir menyebut **apa** yang menghalangi dan **apa yang harus dilakukan**, tanpa menyalahkan pengguna.
- **Dilarang** menampilkan nama file dokumentasi internal (`DESIGN.md`, `DOMAIN.md`, dst.) atau istilah internal pengembang di teks yang dilihat pengguna. Rujuk regulasi dengan istilah bisnis resmi: *Pedoman Tata Kelola SDM & Peraturan Kepegawaian*, *Aturan Batas Usia Kerja*, *Standar Grade Karpim/Karpel*, *Regulasi Masa Kerja/Tenure*.
- Istilah teknis kepegawaian (TMT, BUP, MBT, RM) sebaiknya diberi tooltip penjelasan saat pertama muncul di halaman.

---

## 14. Layout & Shell

**Unified Light Shell** (acuan: `layouts/admin.blade.php`):

- **Sidebar:** `bg-white`, `border-r border-surface-200`, collapsible ke mode ikon. Item aktif: `bg-primary-50 text-primary-800 font-semibold border-l-4 border-primary-600`, kotak ikon `bg-primary-100 text-primary-700`, plus `aria-current="page"`. Item non-aktif: `text-surface-600`, hover `bg-surface-100 text-surface-900`.
- **Topbar:** `bg-white/95 backdrop-blur-md border-b border-surface-200`; pencarian `bg-surface-50`, fokus `bg-white`.
- **Identitas:** "Talenta Hub" + "PTPN IV Regional V" dengan logo resmi.
- Brand hijau dibawa lewat aksen fungsional (tombol utama, navigasi aktif, stepper), bukan blok gelap pada chrome.
- **Layout pegawai** (`layouts/user.blade.php`): satu kolom, mobile-first, target sentuh 44px.

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
- [ ] Diuji di 375px, 1366px, 1920px.
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
| D16 | Tab profil & tab mutasi tanpa semantik tab, memakai `<nav>` | Profil pegawai (4 tab), mutasi (3 tab) | Pola ARIA tabs lengkap + navigasi keyboard (§7.4); dijaga penjaga §16.1 | Selesai 28/10/2026 |
| D17 | Teks di bawah 10px; `animate-pulse` tanpa `motion-safe:` | 99 teks + 10 pulse, 23 view | Dinaikkan ke `text-[10px]`; pulse memakai `motion-safe:`; dijaga penjaga §16.1 | Selesai 28/10/2026 |
| D18 | Hue `purple`/`orange`/`sky`/`indigo`/`pink` dipakai di luar RM Band | 12 view, 74 baris | Kebijakan hue kategori di §5.2: pool/pasokan → `earth`, struktural/talenta → `info`, metadata deskriptif → `surface`; gradien dekoratif dan warna per-bidang dihapus; tugas rangkap satu perlakuan `info`; dijaga penjaga §16.1 | Selesai 28/10/2026 |

---

## 18. Roadmap (Tidak Normatif)

Gagasan untuk fase berikutnya. Tidak boleh dijadikan dasar implementasi sebelum dipindahkan ke bagian normatif:

- Portal pegawai mobile lengkap (bottom navigation maks. 5 item).
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
