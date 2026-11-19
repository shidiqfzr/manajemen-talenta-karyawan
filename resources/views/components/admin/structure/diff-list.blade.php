{{--
    Daftar perubahan struktur organisasi hasil perbandingan lampiran SK dengan struktur saat ini, dikelompokkan per jenis
    dengan istilah bisnis (DESIGN.md §8.5a). Dipakai pada tahap tinjau dan halaman detail SK.

    <x-admin.structure.diff-list :diff="$diff" />
--}}
@props(['diff'])

@php
    $groups = [
        'Unit' => ['unit.add' => 'Unit baru', 'unit.rename' => 'Ganti nama unit', 'unit.reactivate' => 'Aktifkan kembali unit', 'unit.close' => 'Tutup unit'],
        'Jabatan' => ['position.rename' => 'Ganti nama jabatan', 'position.rm_change' => 'Ubah RM Band jabatan'],
        'Formasi Jabatan' => ['seat.add' => 'Formasi jabatan baru', 'seat.update' => 'Ubah kuota atau bidang', 'seat.remove' => 'Hapus formasi jabatan', 'seat.move' => 'Pemindahan formasi (informasi)'],
    ];

    $line = function (array $c): string {
        $changes = fn (array $old, array $new): string => collect($new)->map(fn ($v, $k) => ($k === 'kuota' ? 'Kuota' : 'Bidang').': '.($old[$k] ?? '-').' → '.$v)->implode('; ');

        return match ($c['type']) {
            'unit.add', 'unit.reactivate', 'unit.close' => $c['unit'],
            'unit.rename', 'position.rename' => $c['from'].' → '.$c['to'],
            'position.rm_change' => $c['jabatan'].': '.$c['from'].' → '.$c['to'],
            'seat.add' => "{$c['jabatan']} di {$c['unit']}: RM Band {$c['rm_level']}, Bidang {$c['bidang']}, Kuota {$c['kuota']}",
            'seat.update' => "{$c['jabatan']} di {$c['unit']}: ".$changes($c['old'], $c['new']),
            'seat.remove' => "{$c['jabatan']} di {$c['unit']}: kuota {$c['old']['kuota']} dihapus",
            'seat.move' => "{$c['jabatan']}: {$c['from_unit']} → {$c['to_unit']}",
            default => $c['type'],
        };
    };
@endphp

@if ($diff->isEmpty())
    <p class="text-sm text-surface-600">Tidak ada perubahan dibandingkan dengan struktur saat ini.</p>
@else
    <div class="space-y-5">
        @foreach ($groups as $groupLabel => $types)
            @php($present = collect($types)->filter(fn ($label, $type) => $diff->ofType($type) !== []))
            @if ($present->isNotEmpty())
                <section aria-label="{{ $groupLabel }}">
                    <h4 class="text-xs font-bold uppercase tracking-wide text-surface-500 mb-2">{{ $groupLabel }}</h4>
                    <div class="space-y-3">
                        @foreach ($present as $type => $label)
                            <div>
                                <p class="text-sm font-semibold text-surface-800">{{ $label }} <span class="text-surface-500 font-medium">({{ count($diff->ofType($type)) }})</span></p>
                                <ul class="mt-1 space-y-0.5 text-sm text-surface-600">
                                    @foreach ($diff->ofType($type) as $change)
                                        <li class="pl-3 border-l-2 border-surface-200">{{ $line($change) }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endforeach
                    </div>
                </section>
            @endif
        @endforeach
    </div>
@endif
