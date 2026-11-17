{{-- Formulir bertahap SK struktur organisasi: Data SK → Tinjau Perubahan → Ajukan (DESIGN.md §8.5a). --}}
<div class="space-y-6">
    {{-- Penanda tahap --}}
    <ol class="flex flex-wrap items-center gap-3 text-xs font-semibold" aria-label="Tahap pengajuan SK">
        @foreach ([1 => 'Data SK', 2 => 'Tinjau Perubahan', 3 => 'Ajukan'] as $number => $label)
            <li class="inline-flex items-center gap-2 {{ $step === $number ? 'text-primary-800' : 'text-surface-500' }}" @if ($step === $number) aria-current="step" @endif>
                <span class="w-6 h-6 rounded-full inline-flex items-center justify-center border {{ $step === $number ? 'bg-primary-600 text-white border-primary-600' : ($step > $number ? 'bg-primary-100 text-primary-800 border-primary-200' : 'bg-white border-surface-300') }}">{{ $number }}</span>
                {{ $label }}
            </li>
        @endforeach
    </ol>

    @error('form')
        <div role="alert" class="flex items-start gap-2.5 p-4 bg-danger-50 text-danger-700 border border-danger-200 rounded-xl text-sm font-medium">
            <i class="fas fa-triangle-exclamation mt-0.5" aria-hidden="true"></i>
            <span>{{ $message }}</span>
        </div>
    @enderror

    @if ($step === 1)
        {{-- Panduan: SK struktur diterbitkan Holding; Regional V mencatat dan menerapkannya (DESIGN.md §8.5a). --}}
        <section class="rounded-2xl border border-primary-200 bg-primary-50/60 p-5 sm:p-6 space-y-3" aria-labelledby="panduan-title">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 id="panduan-title" class="text-sm font-bold text-primary-900">SK struktur diterbitkan Holding</h2>
                    <p class="text-sm text-primary-900/80 mt-0.5">Regional V mencatat SK yang sudah diterima dan menerapkan perubahannya pada struktur organisasi di aplikasi. Isi formulir ini sesuai SK dari Holding.</p>
                </div>
                <x-admin.button href="{{ route('admin.master.sk.template') }}" variant="soft" icon="fas fa-download">Unduh Template Lampiran</x-admin.button>
            </div>
            <details class="text-sm text-primary-900/90">
                <summary class="cursor-pointer font-semibold">Cara menyiapkan lampiran</summary>
                <ol class="mt-2 list-decimal pl-5 space-y-1">
                    <li>Unduh template. Isinya struktur organisasi saat ini dalam format yang dibaca aplikasi.</li>
                    <li>Sesuaikan barisnya dengan Lampiran SK dari Holding: ubah kuota, bidang, atau strata; tambah baris untuk formasi jabatan baru; hapus baris untuk formasi yang dihilangkan.</li>
                    <li>Unit yang tidak diatur SK boleh dihapus dari berkas dan tidak akan diubah. Untuk unit yang tercantum, seluruh jabatannya harus ada.</li>
                    <li>Simpan sebagai .xlsx lalu unggah di bawah. Aplikasi menampilkan perubahan terhadap struktur saat ini untuk Anda tinjau sebelum SK diajukan.</li>
                </ol>
                <p class="mt-2 text-xs text-primary-900/70">Template yang diunggah tanpa perubahan tidak menghasilkan perubahan apa pun. Penjelasan lengkap ada pada lembar "Petunjuk" di dalam template.</p>
            </details>
        </section>

        <form wire:submit="createDraft" class="space-y-5 bg-white border border-surface-200 rounded-2xl p-5 sm:p-6">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <x-admin.form.input name="skNumber" label="Nomor SK" wire:model="skNumber" required hint="Contoh: DPSB/SKPTS/352/IX/2026" />
                <x-admin.form.input name="skDate" type="date" label="Tanggal SK" wire:model="skDate" required />
            </div>
            <x-admin.form.input name="title" label="Tentang" wire:model="title" required hint='Judul SK pada baris "Tentang".' />
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <x-admin.form.input name="effectiveDate" type="date" label="Tanggal Berlaku" wire:model="effectiveDate" hint="Kosongkan bila SK berlaku sejak tanggal ditetapkan (sama dengan Tanggal SK)." />
                <x-admin.form.input name="revokes" label="Mencabut / Menggantikan" wire:model="revokes" hint="SK sebelumnya yang dicabut atau digantikan (opsional)." />
            </div>
            <x-admin.form.textarea name="note" label="Catatan" wire:model="note" maxlength="2000" />

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="workbook" class="block text-xs font-bold text-surface-700 mb-1.5">Lampiran SK (.xlsx) <span class="text-danger">*</span></label>
                    <input id="workbook" type="file" wire:model="workbook" accept=".xlsx" @error('workbook') aria-invalid="true" aria-describedby="workbook-error" @enderror
                        class="block w-full text-sm text-surface-700 file:mr-3 file:rounded-lg file:border-0 file:bg-primary-50 file:px-3 file:py-2 file:text-xs file:font-semibold file:text-primary-700">
                    <p wire:loading wire:target="workbook" class="text-[11px] text-surface-500 mt-1"><i class="fas fa-spinner fa-spin" aria-hidden="true"></i> Mengunggah berkas...</p>
                    <p class="text-[10px] text-surface-500 mt-1">Gunakan template di atas dan sesuaikan dengan Lampiran SK dari Holding. Kolom: No, Unit/Bagian, Sub Bagian, Jabatan, Strata RM, Bidang, Kuota, Keterangan. Maksimal 10 MB.</p>
                    @error('workbook') <p id="workbook-error" class="text-danger-700 text-sm mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="pdf" class="block text-xs font-bold text-surface-700 mb-1.5">Salinan SK (.pdf)</label>
                    <input id="pdf" type="file" wire:model="pdf" accept=".pdf" @error('pdf') aria-invalid="true" aria-describedby="pdf-error" @enderror
                        class="block w-full text-sm text-surface-700 file:mr-3 file:rounded-lg file:border-0 file:bg-surface-100 file:px-3 file:py-2 file:text-xs file:font-semibold file:text-surface-700">
                    <p wire:loading wire:target="pdf" class="text-[11px] text-surface-500 mt-1"><i class="fas fa-spinner fa-spin" aria-hidden="true"></i> Mengunggah berkas...</p>
                    <p class="text-[10px] text-surface-500 mt-1">Opsional. Disimpan sebagai arsip SK.</p>
                    @error('pdf') <p id="pdf-error" class="text-danger-700 text-sm mt-1">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="flex justify-end gap-2.5 pt-2">
                <x-admin.button href="{{ route('admin.master.sk.index') }}" variant="secondary" wire:navigate>Batal</x-admin.button>
                <x-admin.button type="submit" icon="fas fa-arrow-right" target="createDraft">Lanjut ke Tinjauan</x-admin.button>
            </div>
        </form>
    @else
        <div class="bg-white border border-surface-200 rounded-2xl p-5 sm:p-6 space-y-1">
            <p class="text-xs font-bold uppercase tracking-wide text-surface-500">Tinjau Perubahan</p>
            <h2 class="text-lg font-bold text-surface-900">{{ $decree->sk_number }}</h2>
            <p class="text-sm text-surface-600">{{ $decree->title }}</p>
            <p class="text-xs text-surface-500">Tanggal SK {{ $decree->sk_date->translatedFormat('d M Y') }} · Berlaku {{ $decree->effective_date->translatedFormat('d M Y') }}</p>
        </div>

        {{-- Ringkasan total sebelum dan sesudah --}}
        @php
            $before = $diff->totals['before'] ?? [];
            $after = $diff->totals['after'] ?? [];
        @endphp
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
            @foreach (['units' => 'Unit', 'pairs' => 'Formasi Jabatan', 'kuota' => 'Total Kuota'] as $key => $label)
                <div class="bg-white border border-surface-200 rounded-xl p-4">
                    <p class="text-[11px] font-bold uppercase tracking-wide text-surface-500">{{ $label }}</p>
                    <p class="mt-1 text-lg font-bold text-surface-900 tabular-nums">{{ $before[$key] ?? 0 }} → {{ $after[$key] ?? 0 }}</p>
                </div>
            @endforeach
            <div class="bg-white border border-surface-200 rounded-xl p-4">
                <p class="text-[11px] font-bold uppercase tracking-wide text-surface-500">Jumlah Perubahan</p>
                <p class="mt-1 text-lg font-bold text-surface-900 tabular-nums">{{ count($diff->changes) }}</p>
            </div>
        </div>

        {{-- Pasangan unit dan jabatan, unit ditutup, atribut unit baru --}}
        @if ($unitPairs !== [] || $currentOnly !== [] || $positionPairs !== [])
            <section class="bg-white border border-surface-200 rounded-2xl p-5 sm:p-6 space-y-5" aria-labelledby="pasangan-title">
                <div>
                    <h3 id="pasangan-title" class="text-sm font-bold text-surface-900">Pasangkan Unit dan Jabatan</h3>
                    <p class="text-xs text-surface-500 mt-0.5">Bila sebuah unit atau jabatan pada lampiran hanya berganti nama, pasangkan dengan nama lamanya agar riwayat tetap utuh. Unit yang tidak tercantum pada lampiran tidak diubah, kecuali Anda menutupnya.</p>
                </div>

                @foreach ($unitPairs as $i => $pair)
                    <div wire:key="unit-pair-{{ $i }}" class="grid grid-cols-1 sm:grid-cols-2 gap-3 items-end">
                        <div>
                            <p class="text-xs font-bold text-surface-700">Unit pada lampiran</p>
                            <p class="text-sm text-surface-800">{{ $pair['new'] }}</p>
                        </div>
                        <x-admin.form.select name="unitPairs.{{ $i }}.old" label="Adalah penggantian nama dari" wire:model="unitPairs.{{ $i }}.old" :options="array_combine($currentOnly, $currentOnly) ?: []" placeholder="Unit baru (bukan pengganti nama)" />
                    </div>
                @endforeach

                @foreach ($newUnits as $i => $row)
                    <div wire:key="new-unit-{{ $i }}" class="rounded-xl border border-surface-200 bg-surface-50/60 p-4 space-y-3">
                        <p class="text-xs font-bold text-surface-700">Atribut unit baru: <span class="text-surface-900">{{ $row['unit'] }}</span></p>
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            <x-admin.form.input name="newUnits.{{ $i }}.kode" label="Kode" wire:model="newUnits.{{ $i }}.kode" hint="Contoh: Kebun, PKS, Bagian" />
                            <x-admin.form.select name="newUnits.{{ $i }}.wilayah" label="Wilayah" wire:model="newUnits.{{ $i }}.wilayah" :options="array_combine($wilayahList, $wilayahList)" />
                            <x-admin.form.input name="newUnits.{{ $i }}.urutan" type="number" label="Urutan" wire:model="newUnits.{{ $i }}.urutan" min="1" />
                        </div>
                    </div>
                @endforeach

                @php($closeable = array_values(array_diff($currentOnly, $chosenOld)))
                @if ($closeable !== [])
                    <fieldset class="space-y-2">
                        <legend class="text-xs font-bold text-surface-700">Tutup unit yang tidak lagi ada pada struktur</legend>
                        @foreach ($closeable as $name)
                            <label wire:key="close-{{ $name }}" class="flex items-center gap-2 text-sm text-surface-700">
                                <input type="checkbox" wire:model="closed" value="{{ $name }}" class="rounded border-surface-300 text-primary-600 focus-visible:ring-primary-500">
                                {{ $name }}
                            </label>
                        @endforeach
                        <p class="text-[11px] text-surface-500">Unit yang masih berpenghuni tetap aktif sampai karyawannya dipindahkan melalui Mutasi.</p>
                    </fieldset>
                @endif

                @foreach ($positionPairs as $i => $pair)
                    <div wire:key="position-pair-{{ $i }}" class="grid grid-cols-1 sm:grid-cols-2 gap-3 items-end">
                        <div>
                            <p class="text-xs font-bold text-surface-700">Jabatan pada lampiran ({{ $pair['unit'] }})</p>
                            <p class="text-sm text-surface-800">{{ $pair['new'] }}</p>
                        </div>
                        @php($options = $positionOptions[$pair['unit']] ?? [])
                        <x-admin.form.select name="positionPairs.{{ $i }}.old" label="Adalah penggantian nama dari" wire:model="positionPairs.{{ $i }}.old" :options="array_combine($options, $options) ?: []" placeholder="Jabatan baru (bukan pengganti nama)" />
                    </div>
                @endforeach
            </section>
        @endif

        {{-- Selisih --}}
        <section class="bg-white border border-surface-200 rounded-2xl p-5 sm:p-6" aria-labelledby="selisih-title">
            <h3 id="selisih-title" class="text-sm font-bold text-surface-900 mb-3">Perubahan terhadap Struktur Saat Ini</h3>
            <x-admin.structure.diff-list :diff="$diff" />
        </section>

        {{-- Dampak (pola §7.2: blokir dan peringatan) --}}
        <section class="space-y-3" aria-labelledby="dampak-title">
            <h3 id="dampak-title" class="text-sm font-bold text-surface-900">Dampak</h3>
            @if ($blockers !== [])
                <div role="alert" class="p-4 bg-danger-50 text-danger-700 border border-danger-200 rounded-xl text-sm space-y-1">
                    <p class="font-bold"><i class="fas fa-ban mr-1.5" aria-hidden="true"></i>SK belum dapat diajukan</p>
                    <ul class="list-disc pl-5">
                        @foreach ($blockers as $blocker)
                            <li>{{ $blocker }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
            <div class="p-4 bg-white border border-surface-200 rounded-xl text-sm text-surface-700 space-y-1">
                <p>Karyawan terdampak: <strong class="tabular-nums">{{ $impact['affected_employees'] }}</strong></p>
                <p>Formasi jabatan yang menjadi melebihi kuota: <strong class="tabular-nums">{{ $impact['over_quota_seats'] }}</strong></p>
                @if ($impact['scheduled_mutations'] !== [])
                    <div class="p-3 bg-warning-50 text-warning-800 border border-warning-200 rounded-lg">
                        <p class="font-semibold">Mutasi terjadwal yang terkena perubahan ini ({{ count($impact['scheduled_mutations']) }}):</p>
                        <ul class="list-disc pl-5">
                            @foreach ($impact['scheduled_mutations'] as $item)
                                <li>{{ $item }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
                <p class="text-xs text-surface-500">Penerapan SK tidak memindahkan karyawan. Karyawan pada formasi yang dihapus atau dikurangi dipindahkan melalui Mutasi.</p>
            </div>
        </section>

        <div class="flex flex-wrap justify-end gap-2.5">
            <x-admin.button href="{{ route('admin.master.sk.index') }}" variant="secondary" wire:navigate>Simpan sebagai Draf</x-admin.button>
            <x-admin.button variant="soft" icon="fas fa-calculator" wire:click="recalculate" target="recalculate">Hitung Ulang Perubahan</x-admin.button>
            <x-admin.button icon="fas fa-paper-plane" wire:click="submit" target="submit" :disabled="$blockers !== []">Ajukan untuk Persetujuan</x-admin.button>
        </div>
    @endif
</div>
