    <!-- ============================================================ -->
    <!-- SLIDE-OVER DRAWER: KAMUS FORMAT SPREADSHEET (26 KOLOM)       -->
    <!-- ============================================================ -->
    <div x-show="showDictionaryDrawer" x-cloak class="relative z-50" aria-labelledby="slide-over-title" role="dialog" aria-modal="true" @keydown.window.escape="showDictionaryDrawer = false">
        <!-- Backdrop -->
        <div x-show="showDictionaryDrawer"
             x-transition:enter="ease-in-out duration-300"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="ease-in-out duration-300"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             @click="showDictionaryDrawer = false"
             class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs transition-opacity"></div>

        <div class="fixed inset-0 overflow-hidden pointer-events-none">
            <div class="absolute inset-0 overflow-hidden pointer-events-none">
                <div class="pointer-events-none fixed inset-y-0 right-0 flex max-w-full pl-6 sm:pl-10">
                    <div x-show="showDictionaryDrawer"
                         x-transition:enter="transform transition ease-in-out duration-300 sm:duration-400"
                         x-transition:enter-start="translate-x-full"
                         x-transition:enter-end="translate-x-0"
                         x-transition:leave="transform transition ease-in-out duration-300 sm:duration-400"
                         x-transition:leave-start="translate-x-0"
                         x-transition:leave-end="translate-x-full"
                         style="pointer-events: auto;"
                         class="pointer-events-auto w-screen max-w-2xl bg-white shadow-2xl flex flex-col h-full border-l border-slate-200">
                        
                        <!-- Drawer Header with Search & Tabs -->
                        <div class="p-5 border-b border-slate-200 bg-slate-50/90 space-y-3.5 shrink-0">
                            <div class="flex items-center justify-between gap-3">
                                <div>
                                    <h3 class="text-base font-extrabold text-slate-900 flex items-center gap-2" id="slide-over-title">
                                        <i class="fas fa-book-bookmark text-emerald-600"></i>
                                        <span>Kamus Format Spreadsheet</span>
                                    </h3>
                                    <p class="text-xs text-slate-500 mt-0.5">
                                        Panduan header baris 1 &bull; 26 kolom terstandarisasi (<span class="font-semibold text-slate-700">6 wajib</span>, <span class="text-slate-500">20 opsional</span>)
                                    </p>
                                </div>
                                <div class="flex items-center gap-2">
                                    <div class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-white border border-slate-200 text-slate-600 text-[11px] font-medium shadow-2xs">
                                        <span class="text-rose-500 font-bold">*</span>
                                        <span>= Kolom wajib</span>
                                    </div>
                                    <button type="button" @click="showDictionaryDrawer = false" class="p-2 rounded-xl text-slate-400 hover:text-slate-600 hover:bg-slate-100 transition cursor-pointer" title="Tutup Kamus (Esc)">
                                        <i class="fas fa-xmark text-lg"></i>
                                    </button>
                                </div>
                            </div>

                            <!-- Search Input Kolom -->
                            <div class="relative">
                                <i class="fas fa-magnifying-glass absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                                <input type="text" x-model="searchColumn" x-ref="searchColInput"
                                    placeholder="Cari nama kolom, tipe dropdown, atau aturan bisnis (lintas 26 kolom)..."
                                    class="w-full pl-9 pr-8 py-2 bg-white border border-slate-200 rounded-xl text-xs text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 transition shadow-2xs">
                                <button type="button" x-show="searchColumn" @click="searchColumn = ''; $refs.searchColInput?.focus()" class="absolute right-2.5 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 text-xs cursor-pointer">
                                    <i class="fas fa-times-circle"></i>
                                </button>
                            </div>

                            <!-- Search Active Indicator -->
                            <template x-if="searchColumn.trim() !== ''">
                                <div class="px-3 py-1.5 bg-emerald-50 border border-emerald-200 rounded-lg flex items-center justify-between text-xs text-emerald-800">
                                    <span class="flex items-center gap-1.5">
                                        <i class="fas fa-filter text-emerald-600"></i>
                                        <span>Menampilkan hasil pencarian di seluruh 26 kolom</span>
                                    </span>
                                    <button type="button" @click="searchColumn = ''" class="font-bold underline hover:text-emerald-950 cursor-pointer">
                                        Reset Pencarian
                                    </button>
                                </div>
                            </template>
                        </div>

                        <!-- Table Area dengan Scroll Penuh Drawer -->
                        <div class="overflow-y-auto flex-1 p-0">
                            <table class="w-full text-left border-collapse text-xs">
                                <thead class="sticky top-0 bg-slate-100/95 backdrop-blur-xs border-b border-slate-200 text-slate-600 text-[11px] uppercase tracking-wider font-bold z-10 shadow-2xs">
                                    <tr>
                                        <th class="py-2.5 px-3.5 font-bold w-[26%] bg-slate-100">Kolom &amp; Header</th>
                                        <th class="py-2.5 px-3.5 font-bold w-[26%] bg-slate-100">Tipe &amp; Validasi</th>
                                        <th class="py-2.5 px-3.5 font-bold w-[48%] bg-slate-100">Aturan &amp; Contoh Nilai</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 text-[11px] bg-white">
                                    @php
                                        $categories = $dictionaryCategories ?? \App\Services\Import\EmployeeImportDictionary::getCategories();
                                    @endphp

                                    @foreach($categories as $category)
                                        <!-- Kategori: {{ $category['title'] }} -->
                                        <tr x-show="isCategoryBannerVisible('{{ $category['id'] }}')" class="bg-slate-50/80 border-y border-slate-200/80">
                                            <td colspan="3" class="py-2 px-3.5 font-bold text-[11px] text-slate-700 uppercase tracking-wider">
                                                <div class="flex items-center justify-between">
                                                    <span class="flex items-center gap-2">
                                                        <i class="{{ $category['icon'] }} text-emerald-600 text-xs"></i>
                                                        <span>{{ $category['title'] }}</span>
                                                    </span>
                                                    <span class="text-[10px] text-slate-400 font-medium font-mono">{{ $category['count_label'] }}</span>
                                                </div>
                                            </td>
                                        </tr>

                                        @foreach($category['columns'] as $col)
                                            @php
                                                $colSearchName = ($col['short_title'] ?? '') . ' ' . $col['key'];
                                                $isMandatory = $col['is_mandatory'];
                                            @endphp
                                            <tr x-show="isColumnVisibleInTab('{{ addslashes($colSearchName) }}', '{{ $category['id'] }}', {{ $isMandatory ? 'true' : 'false' }}, '{{ addslashes($col['search_tags']) }}')" class="odd:bg-white even:bg-slate-50/50 hover:bg-emerald-50/30 transition-colors">
                                                <td class="py-2.5 px-3.5 align-top">
                                                    <div class="flex items-center gap-2">
                                                        <span class="w-5 h-5 rounded bg-slate-100 text-slate-500 font-mono text-[10px] font-semibold flex items-center justify-center shrink-0">{{ $col['letter'] }}</span>
                                                        <span class="font-mono font-bold text-slate-900 text-xs">{{ $col['key'] }}@if($isMandatory)<span class="text-rose-500 font-bold ml-0.5" title="Wajib Diisi">*</span>@endif</span>
                                                    </div>
                                                </td>
                                                <td class="py-2.5 px-3.5 align-top">
                                                    <span class="font-semibold text-slate-800 text-[11px]">{{ $col['type_label'] }}</span>
                                                    <span class="text-[10px] text-slate-400 block mt-0.5">{{ $col['type_sub'] }}</span>
                                                </td>
                                                <td class="py-2.5 px-3.5 align-top text-slate-600 leading-relaxed text-[11px]">
                                                    {{ $col['description'] }}
                                                    @if(!empty($col['example']))
                                                        Contoh: <code class="px-1.5 py-0.5 rounded bg-slate-100 text-slate-700 font-mono text-[10px]">{{ $col['example'] }}</code>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    @endforeach

                                    <!-- Empty State saat Pencarian Kolom Tidak Ditemukan -->
                                    <tr x-show="!hasAnyVisibleColumn()">
                                        <td colspan="3" class="py-12 px-4 text-center">
                                            <div class="max-w-xs mx-auto space-y-3">
                                                <div class="w-12 h-12 mx-auto rounded-2xl bg-slate-100 text-slate-400 flex items-center justify-center text-lg">
                                                    <i class="fas fa-magnifying-glass"></i>
                                                </div>
                                                <div class="space-y-1">
                                                    <h4 class="text-xs font-bold text-slate-800">Tidak Ditemukan Kolom yang Cocok</h4>
                                                    <p class="text-[11px] text-slate-500 leading-relaxed">
                                                        Kata kunci <span class="font-mono font-bold text-emerald-800" x-text="'&ldquo;' + searchColumn + '&rdquo;'"></span> tidak sesuai dengan 26 kolom kamus resmi.
                                                    </p>
                                                </div>
                                                <button type="button" @click="searchColumn = ''; $refs.searchColInput?.focus()" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-emerald-50 hover:bg-emerald-100 text-emerald-700 text-xs font-bold transition cursor-pointer">
                                                    <i class="fas fa-rotate-left text-[10px]"></i>
                                                    <span>Reset Pencarian</span>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- Drawer Footer -->
                        <div class="p-4 bg-slate-50 border-t border-slate-200 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 text-xs text-slate-500 shrink-0">
                            <span class="flex items-center gap-1.5 leading-relaxed">
                                <i class="fas fa-circle-info text-emerald-600 shrink-0"></i>
                                <span>Pastikan menggunakan Template Resmi.</span>
                            </span>
                            <div class="flex items-center gap-2 w-full sm:w-auto shrink-0 justify-end">
                                <a href="{{ route('admin.employees.downloadTemplate') }}" class="flex-1 sm:flex-initial px-3 py-1.5 rounded-lg border border-slate-200 bg-white hover:bg-slate-50 text-slate-700 font-bold transition flex items-center justify-center gap-1.5 shadow-2xs whitespace-nowrap">
                                    <i class="fas fa-file-arrow-down text-emerald-600"></i>
                                    <span>Unduh Template (.xlsx)</span>
                                </a>
                                <button type="button" @click="showDictionaryDrawer = false" class="px-3.5 py-1.5 rounded-lg bg-slate-800 hover:bg-slate-900 text-white font-bold transition cursor-pointer shadow-xs whitespace-nowrap">
                                    Tutup
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
