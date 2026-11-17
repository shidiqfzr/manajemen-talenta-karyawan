{{--
    Riwayat Jabatan Karyawan: cari karyawan, lalu linimasa penempatannya terhadap versi struktur (read-only).
    NIK selalu tersamar; pilihan memakai token terenkripsi (tidak ada NIK pada HTML maupun snapshot Livewire).
--}}
@php
    $employee = $selected !== '' ? $this->employee : null;
    $timeline = $employee ? $this->timeline : [];
    $date = fn (?string $value) => $value ? \Illuminate\Support\Carbon::parse($value)->locale('id')->translatedFormat('d M Y') : null;
@endphp
<div class="space-y-6">
    @if ($employee === null)
        <section class="bg-white rounded-2xl border border-surface-200 shadow-xs" aria-labelledby="employee-search-title">
            <div class="px-5 py-4 border-b border-surface-200">
                <h2 id="employee-search-title" class="text-base font-bold text-surface-900">Cari karyawan</h2>
                <p class="text-sm text-surface-600">Lihat riwayat jabatan seorang karyawan dan SK struktur yang berlaku pada setiap penempatannya.</p>
            </div>
            <div class="p-4 sm:p-5 space-y-4">
                <div class="flex">
                    <label for="employee-term" class="sr-only">Nama atau NIK karyawan</label>
                    <x-admin.search-input model="term" id="employee-term" placeholder="Ketik nama atau NIK karyawan (minimal 3 karakter)..." shortcut="" maxlength="100" containerClass="relative w-full" />
                </div>

                @if ($notFound)
                    <p class="rounded-xl border border-danger-200 bg-danger-50 px-4 py-3 text-sm text-danger-800" role="alert">Karyawan tidak ditemukan. Cari ulang lalu pilih dari daftar.</p>
                @endif

                @if (mb_strlen($term) < \App\Services\Structure\EmployeePositionTimelineService::MIN_TERM_LENGTH)
                    <p class="text-sm text-surface-600" role="status">Ketik minimal 3 karakter nama, atau NIK lengkap.</p>
                @elseif ($this->results === [])
                    <p class="text-sm text-surface-600" role="status">Tidak ada karyawan yang cocok dengan "{{ $term }}".</p>
                @else
                    <ul class="divide-y divide-surface-100 rounded-xl border border-surface-200" wire:loading.class="opacity-60" wire:target="term">
                        @foreach ($this->results as $result)
                            <li wire:key="employee-{{ $loop->index }}">
                                <button type="button" wire:click="select('{{ $result['token'] }}')"
                                    class="w-full flex flex-col sm:flex-row sm:items-center gap-1 sm:gap-4 px-4 py-3 text-left hover:bg-surface-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 cursor-pointer">
                                    <span class="font-semibold text-surface-900">{{ $result['nama'] }}</span>
                                    <span class="font-mono text-xs text-surface-500">{{ $result['nik_masked'] }}</span>
                                    <span class="text-sm text-surface-600 sm:ml-auto">{{ $result['jabatan'] ?? '-' }} · {{ $result['unit_kerja'] ?? '-' }}</span>
                                </button>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </section>
    @else
        <section class="bg-white rounded-2xl border border-surface-200 shadow-xs overflow-hidden" aria-labelledby="timeline-title">
            <div class="px-5 py-4 border-b border-surface-200 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                <div>
                    <h2 id="timeline-title" class="text-base font-bold text-surface-900">{{ $employee['nama'] }}</h2>
                    <p class="text-sm text-surface-600">
                        <span class="font-mono text-xs text-surface-500">{{ $employee['nik_masked'] }}</span>
                        · Saat ini {{ $employee['jabatan'] ?? '-' }}, {{ $employee['unit_kerja'] ?? '-' }}
                    </p>
                </div>
                <x-admin.button variant="secondary" size="sm" icon="fas fa-user-magnifying-glass" wire:click="clear" target="clear">Ganti karyawan</x-admin.button>
            </div>

            <div class="relative overflow-x-auto">
                <table class="w-full text-left border-collapse text-xs">
                    <thead>
                        <tr class="bg-surface-50 border-b border-surface-200">
                            <x-admin.table.th class="py-3.5 px-5">Periode</x-admin.table.th>
                            <x-admin.table.th class="py-3.5 px-5">Jabatan</x-admin.table.th>
                            <x-admin.table.th class="py-3.5 px-5">Unit Kerja</x-admin.table.th>
                            <x-admin.table.th class="py-3.5 px-5">Dasar Penempatan</x-admin.table.th>
                            <x-admin.table.th class="py-3.5 px-5">Struktur yang Berlaku</x-admin.table.th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-surface-100 text-surface-800">
                        @forelse ($timeline as $row)
                            <tr wire:key="placement-{{ $loop->index }}" class="align-top">
                                <td class="px-5 py-3 whitespace-nowrap tabular-nums">
                                    {{ $date($row['tmt_awal']) }} – {{ $date($row['tmt_akhir']) ?? 'sekarang' }}
                                </td>
                                <td class="px-5 py-3">
                                    <span class="font-semibold text-surface-900">{{ $row['jabatan'] }}</span>
                                    @if ($row['status_penugasan'] && $row['status_penugasan'] !== 'Definitif')
                                        <span class="ml-1 inline-flex items-center px-1.5 py-0.5 rounded-full text-[10px] font-semibold bg-surface-100 text-surface-700">{{ $row['status_penugasan'] }}</span>
                                    @endif
                                </td>
                                <td class="px-5 py-3">{{ $row['unit_kerja'] }}</td>
                                <td class="px-5 py-3">
                                    {{ $row['sumber_label'] }}
                                    @if ($row['nomor_sk'])
                                        <span class="block text-surface-500">{{ $row['nomor_sk'] }}</span>
                                    @endif
                                </td>
                                <td class="px-5 py-3">
                                    @if ($row['structure_sk'] === null)
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold bg-surface-100 text-surface-700">Sebelum SK struktur pertama</span>
                                    @else
                                        <span class="block text-surface-900">{{ $row['structure_sk'] }}</span>
                                        @if ($row['in_structure'] === false)
                                            <span class="mt-1 inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-semibold bg-warning-100 text-warning-900"><i class="fas fa-triangle-exclamation text-[10px]" aria-hidden="true"></i> Tidak tercantum pada struktur SK</span>
                                        @endif
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <x-admin.table.empty-state :colspan="5" icon="fas fa-clock-rotate-left" message="Belum ada riwayat jabatan untuk karyawan ini." />
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    @endif
</div>
