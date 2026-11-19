{{-- Modal inspeksi perubahan data (diff): cangkang x-admin.modal, hanya baca (DESIGN.md §7.6) --}}
<x-admin.modal open="showDiffModal" close="closeDiffModal()" tone="warning" icon="fas fa-code-compare" size="xl" title-id="import-diff-title" cancel="Tutup" scroll>
    <x-slot:heading>
        <span class="inline-flex items-center gap-2">
            <span>Inspeksi Perubahan Atribut</span>
            <span class="px-2 py-0.5 rounded-full text-[10px] font-extrabold bg-warning-100 text-warning-800 border border-warning-200">UPDATE</span>
        </span>
    </x-slot:heading>
    <x-slot:subtitle>
        Karyawan: <strong class="text-surface-800" x-text="activeDiffRow?.nama"></strong> &bull; NIK: <strong class="font-mono text-surface-800" x-text="activeDiffRow?.nik"></strong>
    </x-slot:subtitle>

    <div class="border border-surface-200 rounded-xl overflow-hidden shadow-2xs">
        <table class="w-full text-left border-collapse text-xs">
            <thead class="bg-surface-100 border-b border-surface-200">
                <tr>
                    <x-admin.table.th class="py-2.5 px-3.5 w-1/3">Atribut Kepegawaian</x-admin.table.th>
                    <x-admin.table.th class="py-2.5 px-3.5 w-1/3 bg-danger-50/50">Nilai Saat Ini (Database)</x-admin.table.th>
                    <x-admin.table.th class="py-2.5 px-3.5 w-1/3 bg-primary-50/50">Nilai Baru (Spreadsheet)</x-admin.table.th>
                </tr>
            </thead>
            <tbody class="divide-y divide-surface-100 text-[11px] bg-white">
                <template x-for="(item, idx) in (activeDiffRow?.diff || [])" :key="'diff-' + idx">
                    <tr class="hover:bg-surface-50 transition">
                        <td class="py-2.5 px-3.5 font-bold text-surface-800" x-text="item.label || item.field"></td>
                        <td class="py-2.5 px-3.5 text-danger-700 bg-danger-50/20 font-mono">
                            <span class="line-through opacity-80" x-text="item.old"></span>
                        </td>
                        <td class="py-2.5 px-3.5 text-primary-800 bg-primary-50/20 font-mono font-bold">
                            <span class="inline-flex items-center gap-1">
                                <i class="fas fa-arrow-right text-[10px] text-primary-600" aria-hidden="true"></i>
                                <span x-text="item.new"></span>
                            </span>
                        </td>
                    </tr>
                </template>
                <template x-if="!activeDiffRow?.diff || activeDiffRow?.diff.length === 0">
                    <tr>
                        <td colspan="3" class="py-4 text-center text-surface-500 italic">
                            Tidak ada perbedaan atribut spesifik yang terdeteksi.
                        </td>
                    </tr>
                </template>
            </tbody>
        </table>
    </div>

    <x-slot:footerStart>
        <span class="text-[11px] text-surface-500">
            Total <strong class="text-warning-800" x-text="(activeDiffRow?.diff || []).length"></strong> atribut yang akan disesuaikan.
        </span>
    </x-slot:footerStart>
</x-admin.modal>
