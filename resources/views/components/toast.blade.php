<div
    x-data="{ 
        show: {{ session('import_summary') ? 'true' : 'false' }}, 
        timeout: null,
        showDetails: false,
        autoClose: true
    }"
    x-init="
        if (show && autoClose) { 
            timeout = setTimeout(() => show = false, 8000);
        }
    "
    x-show="show"
    x-transition:enter="transition ease-out duration-300 transform"
    x-transition:enter-start="translate-x-full opacity-0"
    x-transition:enter-end="translate-x-0 opacity-100"
    x-transition:leave="transition ease-in duration-200 transform"
    x-transition:leave-start="translate-x-0 opacity-100"
    x-transition:leave-end="translate-x-full opacity-0"
    role="status" aria-live="polite" class="fixed top-5 right-5 z-50 w-full max-w-md"
    style="display: none;"
    @mouseenter="if(timeout) { clearTimeout(timeout); timeout = null; }"
    @mouseleave="if(autoClose && !timeout) { timeout = setTimeout(() => show = false, 3000); }"
>
    @php
        $summary = session('import_summary') ?? [];
        $hasErrors = $summary['has_errors'] ?? false;
        $created = $summary['created'] ?? 0;
        $updated = $summary['updated'] ?? 0;
        $skipped = $summary['skipped'] ?? 0;
        $totalProcessed = $summary['total_processed'] ?? ($created + $updated + $skipped);
        $errors = $summary['errors'] ?? [];
    @endphp

    <div class="bg-white rounded-lg shadow-xl border {{ $hasErrors ? 'border-warning-200' : 'border-primary-200' }} overflow-hidden">
        {{-- Header --}}
        <div class="flex items-center justify-between p-4 {{ $hasErrors ? 'bg-warning-50 border-b border-warning-100' : 'bg-primary-50 border-b border-primary-100' }}">
            <div class="flex items-center space-x-3">
                <div class="flex-shrink-0">
                    @if($hasErrors)
                        <i class="fas fa-exclamation-triangle text-warning-700 text-xl" aria-hidden="true"></i>
                    @else
                        <i class="fas fa-check-circle text-primary-600 text-xl" aria-hidden="true"></i>
                    @endif
                </div>
                <div>
                    <h3 class="text-sm font-semibold {{ $hasErrors ? 'text-warning-800' : 'text-primary-800' }}">
                        {{ $hasErrors ? 'Import Selesai dengan Peringatan' : 'Import Berhasil' }}
                    </h3>
                </div>
            </div>
            <button 
                @click="show = false; if(timeout) clearTimeout(timeout);" 
                class="flex-shrink-0 ml-2 text-surface-500 hover:text-surface-600 transition-colors duration-200"
                aria-label="Tutup notifikasi"
            >
                <i class="fas fa-times text-lg" aria-hidden="true"></i>
            </button>
        </div>

        {{-- Summary Stats --}}
        <div class="p-4 bg-surface-50">
            <div class="grid grid-cols-4 gap-4 text-center">
                <div class="bg-white rounded-lg p-3 shadow-sm">
                    <div class="text-lg font-bold text-surface-700">{{ $totalProcessed }}</div>
                    <div class="text-xs text-surface-500">Diproses</div>
                </div>
                <div class="bg-white rounded-lg p-3 shadow-sm">
                    <div class="text-lg font-bold text-primary-600">{{ $created }}</div>
                    <div class="text-xs text-surface-500">Ditambahkan</div>
                </div>
                <div class="bg-white rounded-lg p-3 shadow-sm">
                    <div class="text-lg font-bold text-info">{{ $updated }}</div>
                    <div class="text-xs text-surface-500">Diperbarui</div>
                </div>
                <div class="bg-white rounded-lg p-3 shadow-sm">
                    <div class="text-lg font-bold text-warning-700">{{ $skipped }}</div>
                    <div class="text-xs text-surface-500">Dilewati</div>
                </div>
            </div>
        </div>

        {{-- Error Details --}}
        @if($hasErrors && count($errors) > 0)
            <div class="border-t border-surface-100">
                <button 
                    @click="showDetails = !showDetails; autoClose = false; if(timeout) { clearTimeout(timeout); timeout = null; }"
                    class="w-full px-4 py-3 text-left text-sm text-surface-600 hover:bg-surface-50 transition-colors duration-200 flex items-center justify-between"
                >
                    <span class="font-medium">Detail Error ({{ count($errors) }} baris)</span>
                    <i class="fas fa-chevron-down transform transition-transform duration-200" :class="showDetails ? 'rotate-180' : ''" aria-hidden="true"></i>
                </button>
                
                <div x-show="showDetails" x-collapse class="border-t border-surface-100">
                    <div class="max-h-48 overflow-y-auto">
                        @foreach(array_slice($errors, 0, 5) as $index => $error)
                            <div class="px-4 py-3 {{ $index % 2 == 0 ? 'bg-danger-50' : 'bg-white' }} border-b border-surface-100 last:border-b-0">
                                <div class="flex items-start space-x-2">
                                    <span class="inline-flex items-center justify-center w-5 h-5 text-xs font-medium text-danger bg-danger-100 rounded-full flex-shrink-0 mt-0.5">
                                        {{ $error['row'] }}
                                    </span>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-sm text-danger-700">
                                            {{ implode(', ', $error['errors']) }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                        @if(count($errors) > 5)
                            <div class="px-4 py-3 bg-surface-50 text-center">
                                <p class="text-sm text-surface-600">
                                    <span class="font-medium">+{{ count($errors) - 5 }}</span> 
                                    baris error lainnya
                                </p>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        @endif

        {{-- Progress Bar --}}
        <div class="h-1 bg-surface-100">
            <div 
                class="h-full {{ $hasErrors ? 'bg-warning' : 'bg-primary-400' }} transition-all duration-300 ease-out"
                x-show="autoClose && show"
                x-init="
                    if (autoClose && show) {
                        setTimeout(() => {
                            $el.style.width = '0%';
                        }, 100);
                    }
                "
                style="width: 100%; transition: width 8s linear;"
            ></div>
        </div>
    </div>
</div>