@php
    $fieldClass = 'w-full px-3 py-2 bg-surface-50/70 border border-surface-200 rounded-xl text-xs font-medium text-surface-800 focus:bg-white focus:outline-none focus:ring-2 focus:ring-primary-500/30 focus:border-primary-500 transition';
@endphp
<div class="space-y-6">
    @if ($notice)
        <div role="status" class="p-4 bg-primary-100 text-primary-800 border border-primary-200 rounded-lg shadow-sm text-xs font-semibold">
            {{ $notice }}
        </div>
    @endif

    <!-- Pemilih unit kerja -->
    <div class="bg-white rounded-2xl border border-surface-200/80 p-5 shadow-xs">
        <label for="formasi-unit" class="block text-xs font-bold text-surface-700 mb-1.5">Unit Kerja</label>
        <div class="flex items-center gap-3">
            <select id="formasi-unit" wire:change="selectUnit($event.target.value || 0)" class="{{ $fieldClass }} max-w-xl">
                <option value="">— Pilih unit kerja —</option>
                @foreach ($this->units->groupBy('wilayah') as $wilayah => $group)
                    <optgroup label="{{ $wilayah }}">
                        @foreach ($group as $option)
                            <option value="{{ $option->id }}" @selected($unitId === $option->id)>{{ $option->nama }}</option>
                        @endforeach
                    </optgroup>
                @endforeach
            </select>
            <span wire:loading wire:target="selectUnit" class="text-[11px] text-surface-500 font-semibold">
                <i class="fas fa-spinner fa-spin" aria-hidden="true"></i> Memuat kursi…
            </span>
        </div>
    </div>

    @if ($this->unit)
        @php
            $seats = $this->seats;
            $overQuota = $seats->where('over_quota', true)->count();
        @endphp

        <!-- Ringkasan -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
            <div class="bg-white rounded-xl border border-surface-200/80 p-4">
                <p class="text-[11px] text-surface-500 font-semibold">Jumlah Kursi</p>
                <p class="text-xl font-black text-surface-800">{{ $seats->count() }}</p>
            </div>
            <div class="bg-white rounded-xl border border-surface-200/80 p-4">
                <p class="text-[11px] text-surface-500 font-semibold">Total Kuota</p>
                <p class="text-xl font-black text-surface-800">{{ $seats->sum('kuota') }}</p>
            </div>
            <div class="bg-white rounded-xl border border-surface-200/80 p-4">
                <p class="text-[11px] text-surface-500 font-semibold">Terisi</p>
                <p class="text-xl font-black text-surface-800">{{ $seats->sum('filled') }}</p>
            </div>
            <div class="bg-white rounded-xl border border-surface-200/80 p-4">
                <p class="text-[11px] text-surface-500 font-semibold">Kursi Melebihi Kuota</p>
                <p class="text-xl font-black {{ $overQuota > 0 ? 'text-danger-700' : 'text-surface-800' }}">{{ $overQuota }}</p>
            </div>
        </div>

        <!-- Kursi formasi -->
        <div class="bg-white rounded-2xl border border-surface-200/80 p-6 shadow-xs">
            <h2 class="text-sm font-extrabold text-surface-900 mb-3">Kursi Formasi — {{ $this->unit->nama }}</h2>
            <div class="overflow-x-auto">
                <table class="w-full text-xs text-left">
                    <thead class="bg-surface-50 text-surface-700 font-bold uppercase text-[10px]">
                        <tr>
                            <th scope="col" class="p-3">Jabatan</th>
                            <th scope="col" class="p-3">RM Band</th>
                            <th scope="col" class="p-3">Bidang</th>
                            <th scope="col" class="p-3">Kuota</th>
                            <th scope="col" class="p-3">Terisi</th>
                            <th scope="col" class="p-3">Status</th>
                            <th scope="col" class="p-3 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-surface-100 font-medium">
                        @forelse ($seats as $seat)
                            <tr wire:key="seat-{{ $seat->id }}" x-data="{ editing: false, kuota: {{ $seat->kuota }}, bidang: '{{ $seat->bidang }}' }">
                                <td class="p-3 font-bold text-surface-800">{{ $seat->jabatan }}</td>
                                <td class="p-3">
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-warning-50 text-warning-800 border border-warning-200">{{ $seat->rm_level ?? '-' }}</span>
                                </td>
                                <td class="p-3 text-surface-600">
                                    <span x-show="!editing">{{ $seat->bidang ?? '-' }}</span>
                                    <select x-show="editing" x-cloak x-model="bidang" aria-label="Bidang {{ $seat->jabatan }}" class="{{ $fieldClass }} w-24">
                                        <option value="">-</option>
                                        @foreach ($bidangList as $code => $label)
                                            <option value="{{ $code }}">{{ $code }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td class="p-3 text-surface-800">
                                    <span x-show="!editing">{{ $seat->kuota }}</span>
                                    <input x-show="editing" x-cloak type="number" min="1" x-model.number="kuota" aria-label="Kuota {{ $seat->jabatan }}" class="{{ $fieldClass }} w-20">
                                </td>
                                <td class="p-3 text-surface-800">{{ $seat->filled }}</td>
                                <td class="p-3">
                                    @if ($seat->over_quota)
                                        <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-danger-50 text-danger-700 border border-danger-200">Melebihi kuota</span>
                                    @elseif ($seat->filled === 0)
                                        <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-surface-100 text-surface-600">Kosong</span>
                                    @elseif ($seat->filled < $seat->kuota)
                                        <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-warning-50 text-warning-800 border border-warning-200">Ada lowongan</span>
                                    @else
                                        <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-primary-100 text-primary-800">Penuh</span>
                                    @endif
                                </td>
                                <td class="p-3">
                                    <div class="flex items-center justify-end gap-1.5">
                                        <button type="button" x-show="!editing" @click="editing = true"
                                            class="px-2.5 py-1 border border-surface-200 text-surface-700 hover:bg-surface-100 rounded-lg text-[11px] font-semibold transition cursor-pointer">Ubah</button>
                                        <button type="button" x-show="editing" x-cloak @click="$wire.startUpdateSeat({{ $seat->id }}, kuota, bidang); editing = false"
                                            class="px-2.5 py-1 bg-primary-600 hover:bg-primary-700 text-white rounded-lg text-[11px] font-semibold transition cursor-pointer">Tinjau</button>
                                        <button type="button" x-show="editing" x-cloak @click="editing = false; kuota = {{ $seat->kuota }}; bidang = '{{ $seat->bidang }}'"
                                            class="px-2.5 py-1 border border-surface-200 text-surface-700 hover:bg-surface-100 rounded-lg text-[11px] font-semibold transition cursor-pointer">Batal</button>
                                        <button type="button" wire:click="startRemoveSeat({{ $seat->id }})"
                                            class="px-2.5 py-1 border border-danger-200 text-danger-700 hover:bg-danger-50 rounded-lg text-[11px] font-semibold transition cursor-pointer">Hapus</button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="p-4 text-center text-surface-500">Unit ini belum memiliki kursi formasi.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Tambah kursi -->
        <div class="bg-white rounded-2xl border border-surface-200/80 p-6 shadow-xs">
            <div class="flex items-center justify-between gap-3 mb-3">
                <h2 class="text-sm font-extrabold text-surface-900">Tambah Kursi dari Jabatan Karpim</h2>
                <div class="flex items-center gap-2">
                    <label for="formasi-cari-jabatan" class="sr-only">Cari jabatan</label>
                    <input id="formasi-cari-jabatan" type="search" wire:model.live.debounce.300ms="search" placeholder="Cari jabatan…" class="{{ $fieldClass }} w-56">
                    <span wire:loading wire:target="search" class="text-[11px] text-surface-500" aria-live="polite">Mencari…</span>
                </div>
            </div>
            <div class="divide-y divide-surface-100">
                @forelse ($this->addablePositions as $position)
                    <div wire:key="add-{{ $position->id }}" x-data="{ kuota: 1, bidang: '' }" class="py-2.5 flex flex-wrap items-center gap-3">
                        <div class="flex-1 min-w-48">
                            <p class="text-xs font-bold text-surface-800">{{ $position->nama }}</p>
                            <p class="text-[11px] text-surface-500">{{ $position->rm_level ?? '-' }}</p>
                        </div>
                        <select x-model="bidang" aria-label="Bidang {{ $position->nama }}" class="{{ $fieldClass }} w-32">
                            <option value="">Pilih bidang</option>
                            @foreach ($bidangList as $code => $label)
                                <option value="{{ $code }}">{{ $code }}</option>
                            @endforeach
                        </select>
                        <input type="number" min="1" x-model.number="kuota" aria-label="Kuota {{ $position->nama }}" class="{{ $fieldClass }} w-20">
                        <button type="button" @click="$wire.startAddSeat({{ $position->id }}, kuota, bidang)"
                            class="px-3 py-1.5 bg-primary-600 hover:bg-primary-700 text-white rounded-lg text-[11px] font-semibold transition cursor-pointer">Tinjau</button>
                    </div>
                @empty
                    <p class="py-3 text-xs text-surface-500">Tidak ada jabatan Karpim aktif yang dapat ditambahkan.</p>
                @endforelse
            </div>
        </div>

        <!-- Perubahan terakhir -->
        <div class="bg-white rounded-2xl border border-surface-200/80 p-6 shadow-xs">
            <h2 class="text-sm font-extrabold text-surface-900 mb-3">Perubahan Terakhir pada Unit Ini</h2>
            <ul class="divide-y divide-surface-100">
                @forelse ($this->recentChanges as $log)
                    <li wire:key="log-{{ $log->id }}" class="py-2.5 text-xs">
                        <p class="font-bold text-surface-800">{{ \App\Models\MasterAuditLog::actionLabel($log->action) }} — {{ $log->entity_label }}</p>
                        <p class="text-surface-500">{{ $log->user_name }} · {{ $log->created_at->diffForHumans() }}@if ($log->reason) · {{ $log->reason }}@endif</p>
                    </li>
                @empty
                    <li class="py-3 text-xs text-surface-500">Belum ada perubahan tercatat.</li>
                @endforelse
            </ul>
        </div>
    @else
        <div class="bg-white rounded-2xl border border-surface-200/80 p-10 text-center text-xs text-surface-500">
            Pilih unit kerja untuk melihat dan mengelola kursi formasinya.
        </div>
    @endif

    <!-- Konfirmasi perubahan + panel dampak -->
    @if ($pendingOperation)
        @php($impact = $this->impact)
        <div x-data="{ open: true }" @keydown.escape.window="$wire.cancel()"
            class="fixed inset-0 z-50 overflow-y-auto bg-surface-900/60 backdrop-blur-xs flex items-center justify-center p-4"
            role="dialog" aria-modal="true" aria-labelledby="formasi-confirm-title" x-trap.noscroll="open" tabindex="-1">
            <div class="bg-white rounded-2xl border border-surface-200 shadow-2xl max-w-lg w-full overflow-hidden">
                <div class="px-6 py-4 border-b border-surface-100 bg-surface-50/50">
                    <h3 id="formasi-confirm-title" class="text-sm font-extrabold text-surface-900">{{ $this->operationTitle() }}</h3>
                    <p class="text-[11px] text-surface-500">Tinjau dampak perubahan sebelum diterapkan.</p>
                </div>

                <div class="p-6 space-y-4">
                    @if ($impact)
                        @if ($impact->blockers)
                            <div role="alert" class="p-3 bg-danger-50 border border-danger-200 rounded-xl text-xs text-danger-700 font-semibold space-y-1">
                                @foreach ($impact->blockers as $blocker)
                                    <p>{{ $blocker }}</p>
                                @endforeach
                            </div>
                        @endif
                        @if ($impact->warnings)
                            <div class="p-3 bg-warning-50 border border-warning-200 rounded-xl text-xs text-warning-800 font-semibold space-y-1">
                                @foreach ($impact->warnings as $warning)
                                    <p>{{ $warning }}</p>
                                @endforeach
                            </div>
                        @endif
                        @if (! $impact->blockers && ! $impact->warnings)
                            <p class="text-xs text-surface-600">Tidak ada dampak pada pegawai. Perubahan siap diterapkan.</p>
                        @endif
                        @if ($impact->affectedEmployees > 0)
                            <p class="text-[11px] text-surface-500">Pegawai terkait: {{ $impact->affectedEmployees }}</p>
                        @endif
                    @endif

                    @if ($this->requiresReason())
                        <div>
                            <label for="formasi-alasan" class="block text-xs font-bold text-surface-700 mb-1.5">Alasan Perubahan <span class="text-danger-700" aria-hidden="true">*</span></label>
                            <textarea id="formasi-alasan" wire:model="reason" rows="3" placeholder="Jelaskan alasan perubahan ini (minimal 10 karakter). Alasan dicatat pada riwayat perubahan master." class="{{ $fieldClass }}"></textarea>
                        </div>
                    @endif

                    @error('confirm')
                        <div role="alert" class="p-3 bg-danger-50 border border-danger-200 rounded-xl text-xs text-danger-700 font-semibold">{{ $message }}</div>
                    @enderror
                </div>

                <div class="px-6 py-4 bg-surface-50/80 border-t border-surface-100 flex items-center justify-end gap-2.5">
                    <button type="button" wire:click="cancel" wire:loading.attr="disabled" wire:target="confirm"
                        class="px-4 py-2 border border-surface-200 text-surface-600 hover:bg-surface-100 text-xs font-semibold rounded-xl transition cursor-pointer">Batal</button>
                    <button type="button" wire:click="confirm" wire:loading.attr="disabled" wire:target="confirm" @disabled($impact && $impact->blocked)
                        class="inline-flex items-center gap-2 px-5 py-2 bg-primary-600 hover:bg-primary-700 text-white text-xs font-bold rounded-xl shadow-md transition cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed">
                        <i class="fas fa-spinner fa-spin text-xs" wire:loading wire:target="confirm" aria-hidden="true"></i>
                        <span>Terapkan Perubahan</span>
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
