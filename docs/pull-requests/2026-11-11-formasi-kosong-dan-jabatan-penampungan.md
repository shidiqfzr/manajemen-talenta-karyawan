# Formasi kosong, Sebaran Formasi, Mutasi berkuota dan Jabatan Penampungan

> Deskripsi PR untuk peninjau manusia. Cabang `feat/sebaran-formasi` (dibuat dari `feat/shared-table-employee-style`). Aturan yang belum disetujui SDM dicatat pada DOMAIN.md §12.

## Apa yang berubah
1. **Satu sumber angka formasi kosong** (`JobVacancyService`): kuota dikurangi pejabat definitif, dihitung langsung dari `position_unit` dan karyawan. Dipakai Sebaran Formasi, Monitoring Formasi Kosong (Mutasi), dan kolom Kosong pada Formasi Unit Kerja.
2. **Tab Sebaran Formasi** (Master Formasi): tampilan per rumpun jabatan atau per unit, terisi dan kosong, filter, ekspor tanpa nama karyawan.
3. **Mutasi berkuota** (`FormationService`, `MutasiService`, `ChainTopologyAnalyzer`): formasi berkuota ganda yang belum penuh menerima penempatan langsung; yang penuh ditolak dengan pesan jelas; Pj/Plt (termasuk yang ditempatkan lewat Mutasi) menempati kuota; pemesanan hanya menutup formasi bila menghabiskan kuota kosong.
4. **Tab Jabatan Penampungan** (Advisor, Diperbantukan): buat, buka per unit, tutup, ubah nama, hapus, dan bezetting per unit; tidak dihitung sebagai formasi.
5. **Pengujian**: PHPUnit (lihat jumlah pada ringkasan CI), spesifikasi Playwright baru `sebaran-formasi` dan `jabatan-penampungan` (alur, papan ketik, axe WCAG 2.1 AA, overflow ponsel), dan verifikasi pada MySQL 8.4 dengan basis data terpisah.

## Yang perlu ditinjau manusia
- **Aturan sementara** pada DOMAIN.md §12: setiap baris butuh keputusan SDM.
- **Keamanan dan UU PDP**: layar baru hanya untuk admin, tidak menampilkan data pribadi selain jumlah, dan ekspor tidak memuat nama atau NIK. Tinjauan ini dilakukan pengembang dan peninjau otomatis, belum oleh peninjau manusia atau petugas pelindungan data.
- **Perilaku yang berubah**: jabatan di luar Master Formasi tidak lagi dihitung sebagai kekosongan; formasi berkuota ganda kini dapat diisi lewat Mutasi; jabatan penampungan tidak lagi muncul sebagai target mutasi biasa.
- **Data**: perubahan `database/data/master_formasi.json` (491 formasi, 619 kuota) dan penyesuaian angka pada tiga berkas uji.

## Cara memeriksa
```
php artisan test
./vendor/bin/pint --test
npx playwright test tests/e2e/sebaran-formasi.spec.js tests/e2e/jabatan-penampungan.spec.js
```
Uji MySQL (basis data terpisah, jangan arahkan ke basis data pengembangan):
```
DB_CONNECTION=mysql DB_DATABASE=<basis_data_uji_kosong> php artisan test --filter="Formasi|Mutasi|Pool|JobVacancy"
```

## Risiko dan pemulihan
- Perubahan hanya pada kode, uji, dan dokumen; tidak ada migrasi baru. Pembatalan cukup dengan `git revert` per komit.
- Tautan lama `/admin/master/lowongan` dialihkan permanen ke `/admin/master/sebaran`.
