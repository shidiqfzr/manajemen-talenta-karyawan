{{--
    Panel baris yang dilewati: alasan dikelompokkan agar pola kesalahan terlihat, lalu seluruh baris pada daftar
    yang dapat dibuka. Pesan hanya memuat nomor baris dan alasan (NIK disamarkan), tanpa data pribadi.

    @include('admin.employees.import-batches.partials.skipped-rows', ['groups' => $skippedGroups, 'rows' => $skippedRows, 'log' => $log, 'canDownloadFailed' => $canDownloadFailed, 'correctionExpiresAt' => $correctionExpiresAt])
--}}
@if (! empty($rows))
    <section class="bg-white rounded-2xl border border-surface-200/80 shadow-xs overflow-hidden" aria-labelledby="skipped-title">
        <div class="px-5 py-4 border-b border-surface-100 flex flex-wrap items-start justify-between gap-3">
            <div class="min-w-0">
                <h3 id="skipped-title" class="text-base font-bold text-surface-900">Baris yang Dilewati ({{ count($rows) }})</h3>
                <p class="text-sm text-surface-500 mt-0.5">Baris ini tidak ikut disimpan. Perbaiki di spreadsheet asal, lalu impor ulang baris tersebut.</p>
            </div>
            @if ($canDownloadFailed)
                <form method="POST" action="{{ route('admin.employees.import.downloadFailed') }}" target="_blank" class="shrink-0">
                    @csrf
                    <input type="hidden" name="token" value="{{ $log->token }}">
                    <x-admin.button type="submit" variant="secondary" size="sm" icon="fas fa-file-arrow-down">Unduh Berkas Koreksi</x-admin.button>
                </form>
            @endif
        </div>

        <ul class="divide-y divide-surface-100">
            @foreach ($groups as $group)
                <li class="px-5 py-3.5 flex items-start justify-between gap-4">
                    <div class="min-w-0">
                        <p class="text-sm font-semibold text-surface-900">{{ $group['reason'] }}</p>
                        <p class="text-xs text-surface-500 mt-1">
                            Baris <span class="font-mono tabular-nums">{{ implode(', ', array_slice($group['rows'], 0, 12)) }}</span>@if (count($group['rows']) > 12), dan {{ count($group['rows']) - 12 }} lainnya @endif
                        </p>
                        @if ($group['sample'] !== $group['reason'] && $group['sample'] !== $group['reason'].'.')
                            <p class="text-xs text-surface-600 mt-1">Contoh: {{ $group['sample'] }}</p>
                        @endif
                    </div>
                    <span class="shrink-0 inline-flex items-center px-2.5 py-1 rounded-full bg-danger-50 text-danger-700 border border-danger-200 text-xs font-semibold">
                        <span class="font-mono tabular-nums mr-1">{{ $group['count'] }}</span> baris
                    </span>
                </li>
            @endforeach
        </ul>

        <details class="border-t border-surface-100">
            <summary class="px-5 py-3 text-sm font-semibold text-primary-700 cursor-pointer hover:bg-surface-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-primary-500">
                Lihat seluruh baris beserta pesannya
            </summary>
            <div class="overflow-x-auto border-t border-surface-100">
                <table class="min-w-full text-left text-sm">
                    <thead class="bg-surface-50 text-xs font-semibold text-surface-600">
                        <tr>
                            <th scope="col" class="px-5 py-2.5 w-24">Baris</th>
                            <th scope="col" class="px-5 py-2.5">Alasan</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-surface-100">
                        @foreach ($rows as $row)
                            <tr>
                                <td class="px-5 py-2.5 font-mono tabular-nums text-surface-900">{{ $row['row'] ?? '-' }}</td>
                                <td class="px-5 py-2.5 text-surface-700">{{ implode(' ', $row['errors'] ?? []) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </details>

        <div class="px-5 py-3.5 border-t border-surface-100 bg-surface-50 flex flex-wrap items-center justify-between gap-3">
            @if ($canDownloadFailed)
                <p class="text-xs text-surface-600">Berkas koreksi berisi data pribadi sehingga tersedia sampai <span class="font-mono tabular-nums font-semibold">{{ $correctionExpiresAt->format('d/m/Y H:i') }}</span>. Unduh sebelum waktu tersebut.</p>
            @else
                <p class="text-xs text-surface-600">Berkas koreksi sudah tidak tersedia karena memuat data pribadi. Perbaiki baris di atas langsung pada spreadsheet asal, lalu impor ulang.</p>
            @endif
        </div>
    </section>
@endif
