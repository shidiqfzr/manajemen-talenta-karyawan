{{-- KOLOM KANAN: JAMINAN TATA KELOLA SDM & ACTION DOCK (Tahap 3) --}}
<div class="lg:col-span-4 bg-white rounded-2xl border border-slate-200/80 p-5 sm:p-6 shadow-xs flex flex-col justify-between space-y-5 h-full">
    <div>
        <!-- Header Kolom Kanan -->
        <div class="border-b border-slate-100 pb-4">
            <h3 class="text-base font-extrabold text-slate-900 flex items-center gap-2">
                <i class="fas fa-shield-halved text-emerald-600"></i>
                <span>Jaminan Integritas &amp; Tata Kelola</span>
            </h3>
            <p class="text-xs text-slate-500 mt-1 leading-relaxed">
                Standar kepatuhan tata kelola SDM terintegrasi PTPN IV Regional V.
            </p>
        </div>

        <!-- 4 Butir Jaminan Integritas & Tata Kelola (Format Multi-Baris Lengkap) -->
        <ul class="text-xs text-slate-600 space-y-3.5 leading-relaxed mt-4">
            <li class="flex items-start gap-2.5">
                <div class="w-5 h-5 rounded-full bg-emerald-100 text-emerald-700 flex items-center justify-center text-[10px] shrink-0 mt-0.5 font-bold">
                    <i class="fas fa-check text-[9px]"></i>
                </div>
                <div class="min-w-0">
                    <strong class="text-slate-800 font-bold block text-xs">Penyimpanan Transaksional Atomik</strong>
                    <span class="text-slate-500 text-[11px] block mt-0.5 leading-snug">
                        Seluruh pembaruan transaksi basis data aman, menjamin tidak ada inkonsistensi saat terjadi kendala koneksi.
                    </span>
                </div>
            </li>
            <li class="flex items-start gap-2.5">
                <div class="w-5 h-5 rounded-full bg-emerald-100 text-emerald-700 flex items-center justify-center text-[10px] shrink-0 mt-0.5 font-bold">
                    <i class="fas fa-check text-[9px]"></i>
                </div>
                <div class="min-w-0">
                    <strong class="text-slate-800 font-bold block text-xs">Sinkronisasi Riwayat Penugasan</strong>
                    <span class="text-slate-500 text-[11px] block mt-0.5 leading-snug">
                        Penugasan sementara (Plt/Pjs) pada Kolom O otomatis diverifikasi dan dicatat pada riwayat penugasan resmi.
                    </span>
                </div>
            </li>
            <li class="flex items-start gap-2.5">
                <div class="w-5 h-5 rounded-full bg-emerald-100 text-emerald-700 flex items-center justify-center text-[10px] shrink-0 mt-0.5 font-bold">
                    <i class="fas fa-check text-[9px]"></i>
                </div>
                <div class="min-w-0">
                    <strong class="text-slate-800 font-bold block text-xs">Kepatuhan Batas Usia Kerja</strong>
                    <span class="text-slate-500 text-[11px] block mt-0.5 leading-snug">
                        Perhitungan otomatis Batas Usia Pensiun (56 tahun) dan MBT (55 tahun) aktif sesuai regulasi kepegawaian.
                    </span>
                </div>
            </li>
            <li class="flex items-start gap-2.5">
                <div class="w-5 h-5 rounded-full bg-emerald-100 text-emerald-700 flex items-center justify-center text-[10px] shrink-0 mt-0.5 font-bold">
                    <i class="fas fa-check text-[9px]"></i>
                </div>
                <div class="min-w-0">
                    <strong class="text-slate-800 font-bold block text-xs">Audit Trail UU PDP No. 27/2022</strong>
                    <span class="text-slate-500 text-[11px] block mt-0.5 leading-snug">
                        Perubahan atribut terlindungi secara permanen untuk kebutuhan audit kepegawaian dan kepatuhan hukum.
                    </span>
                </div>
            </li>
        </ul>
    </div>

    <!-- TOMBOL TINDAKAN OPERASIONAL UTAMA -->
    <div class="pt-4 border-t border-slate-100 space-y-2.5">
        <!-- 1. Primary CTA: Buka Direktori Pegawai -->
        <a href="{{ route('admin.employees.index') }}"
            class="w-full py-3 px-4 rounded-xl bg-emerald-600 hover:bg-emerald-700 active:scale-[0.99] text-white text-xs sm:text-sm font-extrabold transition shadow-md shadow-emerald-600/20 flex items-center justify-center gap-2 cursor-pointer">
            <i class="fas fa-users text-sm"></i>
            <span>Buka Direktori Pegawai</span>
            <i class="fas fa-arrow-right text-xs"></i>
        </a>

        <!-- 2. Secondary CTA: Unduh Laporan Rekap Mutasi (.xlsx) -->
        <form action="{{ route('admin.employees.import.downloadReport') }}" method="POST" target="_blank" class="w-full">
            @csrf
            <input type="hidden" name="token" :value="importToken">
            <button type="submit"
                class="w-full py-2.5 px-4 rounded-xl border border-slate-200 bg-white hover:bg-slate-50 text-slate-700 text-xs font-bold transition flex items-center justify-center gap-2 shadow-2xs cursor-pointer">
                <i class="fas fa-file-excel text-emerald-600 text-sm"></i>
                <span>Unduh Laporan Rekapitulasi (.xlsx)</span>
            </button>
        </form>
    </div>
</div>
