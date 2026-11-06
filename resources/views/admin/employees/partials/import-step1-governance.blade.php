@php
    $guideColumns = collect($dictionaryCategories)->flatMap(fn ($category) => $category['columns']);
    $guideTotal = $guideColumns->count();
    $guideMandatory = $guideColumns->where('is_mandatory', true)->count();
@endphp
{{-- KOLOM KANAN: SPESIFIKASI & TATA KELOLA HR (Tahap 1) --}}
<div class="lg:col-span-5 bg-white rounded-2xl border border-surface-200/80 p-6 shadow-xs flex flex-col justify-between space-y-4 h-full">
    <div>
        <!-- Header -->
        <div class="border-b border-surface-100 pb-3.5">
            <h3 class="text-base font-extrabold text-surface-900 flex items-center gap-2">
                <i class="fas fa-shield-halved text-primary-600" aria-hidden="true"></i>
                <span>Tata Kelola HR &amp; Spesifikasi</span>
            </h3>
            <p class="text-xs text-surface-500 mt-0.5 leading-relaxed">
                Pedoman Tata Kelola SDM &amp; Peraturan Kepegawaian PTPN IV Regional V.
            </p>
        </div>

        <!-- Ringkasan Spesifikasi Kolom (Pills / Badges) -->
        <div class="grid grid-cols-2 gap-3 my-3.5">
            <div class="p-3 bg-surface-50 border border-surface-200/70 rounded-xl">
                <span class="text-[10px] uppercase font-bold text-surface-500 tracking-wider block">Total Atribut</span>
                <div class="flex items-baseline gap-1 mt-0.5">
                    <span class="text-lg font-black text-surface-900">{{ $guideTotal }}</span>
                    <span class="text-xs font-bold text-surface-500">Kolom</span>
                </div>
                <span class="text-[11px] text-surface-500 mt-0.5 block">{{ $guideMandatory }} wajib &bull; {{ $guideTotal - $guideMandatory }} opsional</span>
            </div>
            <div class="p-3 bg-primary-50/60 border border-primary-200/60 rounded-xl">
                <span class="text-[10px] uppercase font-bold text-primary-700 tracking-wider block">Kategori Data</span>
                <div class="flex items-baseline gap-1 mt-0.5">
                    <span class="text-lg font-black text-primary-950">{{ count($dictionaryCategories) }}</span>
                    <span class="text-xs font-bold text-primary-700">Dimensi</span>
                </div>
                <span class="text-[11px] text-primary-700 mt-0.5 block">Identitas, Jabatan, Karir, Pensiun</span>
            </div>
        </div>

        <!-- 4 Butir Kepatuhan Tata Kelola -->
        <ul class="text-xs text-surface-600 space-y-2.5 leading-relaxed">
            <li class="flex items-start gap-2.5">
                <div class="w-5 h-5 rounded-full bg-primary-100 text-primary-700 flex items-center justify-center text-[10px] shrink-0 mt-0.5 font-bold">1</div>
                <div class="min-w-0">
                    <strong class="text-surface-800 font-bold">Proteksi NIK &amp; Auto-Padding:</strong>
                    <span class="text-surface-500 block text-[11px] mt-0.5">NIK wajib 8 digit. Sistem secara otomatis menyisipkan angka nol di depan jika terpotong format numerik Excel.</span>
                </div>
            </li>
            <li class="flex items-start gap-2.5">
                <div class="w-5 h-5 rounded-full bg-primary-100 text-primary-700 flex items-center justify-center text-[10px] shrink-0 mt-0.5 font-bold">2</div>
                <div class="min-w-0">
                    <strong class="text-surface-800 font-bold">Otomasi Pensiun &amp; MBT:</strong>
                    <span class="text-surface-500 block text-[11px] mt-0.5">Tanggal Pensiun (56 tahun) dan MBT (55 tahun) otomatis dihitung sistem jika tidak diisi secara manual.</span>
                </div>
            </li>
            <li class="flex items-start gap-2.5">
                <div class="w-5 h-5 rounded-full bg-primary-100 text-primary-700 flex items-center justify-center text-[10px] shrink-0 mt-0.5 font-bold">3</div>
                <div class="min-w-0">
                    <strong class="text-surface-800 font-bold">Integritas Strata &amp; Golongan:</strong>
                    <span class="text-surface-500 block text-[11px] mt-0.5">Validasi strata Karpim vs Karpel, 7 RM Band (RM-1 s.d. RM-7), dan format golongan baku [Gol]/[Berkala].</span>
                </div>
            </li>
            <li class="flex items-start gap-2.5">
                <div class="w-5 h-5 rounded-full bg-primary-100 text-primary-700 flex items-center justify-center text-[10px] shrink-0 mt-0.5 font-bold">4</div>
                <div class="min-w-0">
                    <strong class="text-surface-800 font-bold">Audit Trail UU PDP No. 27/2022:</strong>
                    <span class="text-surface-500 block text-[11px] mt-0.5">Seluruh riwayat perubahan atribut dan mutasi tercatat secara permanen di riwayat penugasan.</span>
                </div>
            </li>
        </ul>
    </div>
</div>
