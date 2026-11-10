{{--
    Sel "Kosong Sejak" satu formasi jabatan: tanggal keluar pejabat terakhir dan lama kosong (JobVacancyService, `severity`).
    Formasi yang sudah penuh tidak menampilkan apa pun.

    <x-admin.master.seat-since :seat="$seat" />
--}}
@props(['seat'])

@if ($seat->vacant_slots <= 0)
    <span class="text-surface-400" aria-hidden="true">—</span>
@elseif ($seat->days_vacant === null)
    <span class="text-surface-500 italic">Belum pernah terisi</span>
@else
    <p class="font-medium text-surface-900 tabular-nums">{{ $seat->vacant_since_formatted }}</p>
    <span class="inline-flex items-center mt-1 px-2 py-0.5 rounded-full text-[11px] font-semibold tabular-nums border
        {{ match ($seat->severity) {
            'red' => 'bg-danger-50 text-danger-700 border-danger-200',
            'yellow' => 'bg-warning-50 text-warning-800 border-warning-200',
            default => 'bg-primary-100 text-primary-800 border-primary-200',
        } }}">{{ $seat->days_vacant }} hari</span>
@endif
