{{-- Formulir bertahap SK struktur: Data SK → Tinjau Perubahan → Ajukan, dari komponen bersama (DESIGN.md §7.1, §8.5a). --}}
<div class="space-y-6">
    {{-- Komponen yang sama dengan Impor Data Karyawan (DESIGN.md §6.1). --}}
    <x-admin.stepper :current="$step" label="Tahap pencatatan SK" :steps="[
        ['title' => 'Data SK', 'description' => 'Nomor, tanggal, dan lampiran'],
        ['title' => 'Tinjau Perubahan', 'description' => 'Selisih, dampak, dan pasangan unit'],
        ['title' => 'Ajukan', 'description' => 'Persetujuan dan penerapan'],
    ]" />

    @if ($step === 1)
        <div class="flex items-start gap-3 rounded-xl border border-primary-200 bg-primary-50 px-4 py-3 text-sm leading-relaxed text-primary-900" role="status">
            <i class="fas fa-circle-info mt-1" aria-hidden="true"></i>
            <p>
                <span class="font-semibold">SK struktur diterbitkan Holding.</span>
                Regional V mencatat SK yang sudah diterima dan menerapkan perubahannya pada struktur organisasi di aplikasi. Isi formulir ini sesuai SK dari Holding.
            </p>
        </div>

        <form wire:submit="createDraft" class="space-y-6" novalidate>
            <x-admin.form.error-summary />

            <x-admin.form.section number="1" title="Data SK" description="Nomor, tanggal, dan keterangan sesuai SK dari Holding" legend>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <x-admin.form.input name="skNumber" label="Nomor SK" wire:model.blur="skNumber" required hint="Contoh: DPSB/SKPTS/352/IX/2026" />
                    <x-admin.form.input name="skDate" type="date" label="Tanggal SK" wire:model.blur="skDate" required />
                </div>
                <x-admin.form.input name="title" label="Tentang" wire:model.blur="title" required hint='Judul SK pada baris "Tentang".' />
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <x-admin.form.input name="effectiveDate" type="date" label="Tanggal Berlaku" wire:model.blur="effectiveDate" hint="Kosongkan bila SK berlaku sejak tanggal ditetapkan (sama dengan Tanggal SK)." />
                    <x-admin.form.input name="revokes" label="Mencabut / Menggantikan" wire:model.blur="revokes" hint="SK sebelumnya yang dicabut atau digantikan (opsional)." />
                </div>
                <x-admin.form.textarea name="note" label="Catatan" wire:model.blur="note" maxlength="2000" hint="Opsional." />
            </x-admin.form.section>

            <x-admin.form.section number="2" title="Lampiran" description="Lampiran SK berformat Excel dan salinan SK berformat PDF">
                <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-surface-200 bg-surface-50 px-4 py-3">
                    <p class="flex-1 min-w-[16rem] text-sm text-surface-700 leading-relaxed">Mulai dari template berisi struktur organisasi saat ini, lalu sesuaikan dengan Lampiran SK dari Holding.</p>
                    <x-admin.button href="{{ route('admin.master.sk.template') }}" variant="soft" icon="fas fa-download">Unduh Template Lampiran</x-admin.button>
                </div>
                <details class="text-sm text-surface-700">
                    <summary class="cursor-pointer font-semibold text-surface-800 rounded focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500">Cara menyiapkan lampiran</summary>
                    <ol class="mt-2 list-decimal pl-5 space-y-1">
                        <li>Unduh template. Isinya struktur organisasi saat ini dalam format yang dibaca aplikasi.</li>
                        <li>Sesuaikan barisnya dengan Lampiran SK dari Holding: ubah kuota, bidang, atau strata; tambah baris untuk formasi jabatan baru; hapus baris untuk formasi yang dihilangkan.</li>
                        <li>Unit yang tidak diatur SK boleh dihapus dari berkas dan tidak akan diubah. Untuk unit yang tercantum, seluruh jabatannya harus ada.</li>
                        <li>Simpan sebagai .xlsx lalu unggah di bawah. Aplikasi menampilkan perubahan terhadap struktur saat ini untuk Anda tinjau sebelum SK diajukan.</li>
                    </ol>
                    <p class="mt-2 text-xs text-surface-500">Template yang diunggah tanpa perubahan tidak menghasilkan perubahan apa pun. Penjelasan lengkap ada pada lembar "Petunjuk" di dalam template.</p>
                </details>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <x-admin.file-upload wire-model="workbook" id="workbook" :file="$workbook" label="Lampiran SK (.xlsx)" helper="(Wajib, maks. 10 MB)" accept=".xlsx" error-key="workbook" icon="fas fa-file-excel" icon-active="text-primary-600" empty-text="Belum ada lampiran dipilih" button-text="Pilih Lampiran" change-button-text="Ganti Lampiran" />
                    <x-admin.file-upload wire-model="pdf" id="pdf" :file="$pdf" label="Salinan SK (.pdf)" helper="(Opsional, maks. 10 MB)" accept=".pdf,application/pdf" error-key="pdf" empty-text="Belum ada salinan dipilih" button-text="Pilih Salinan" change-button-text="Ganti Salinan" />
                </div>
            </x-admin.form.section>

            <x-admin.form.footer :cancel-url="route('admin.master.sk.index')" submit-label="Lanjut ke Tinjauan" icon="fas fa-arrow-right" :once="false" target="createDraft"
                note="Pencatatan SK tercatat pada riwayat perubahan master." />
        </form>
    @else
        {{-- Tahap Tinjau Perubahan --}}
        @php
            $before = $diff->totals['before'] ?? [];
            $after = $diff->totals['after'] ?? [];
            $changeCount = count($diff->changes);
            $blockerCount = count($blockers);
        @endphp

        <section class="bg-white rounded-2xl border border-surface-200/80 p-5 shadow-xs space-y-1" aria-labelledby="review-title">
            <p class="text-xs font-bold uppercase tracking-wide text-surface-500">Tinjau Perubahan</p>
            <h2 id="review-title" class="text-lg font-bold text-surface-900">{{ $decree->sk_number }}</h2>
            <p class="text-sm text-surface-600">{{ $decree->title }}</p>
            <p class="text-xs text-surface-500">Tanggal SK {{ $decree->sk_date->locale('id')->translatedFormat('d M Y') }} · Berlaku {{ $decree->effective_date->locale('id')->translatedFormat('d M Y') }}</p>
        </section>

        {{-- Kartu bernilai tetap dibuat netral agar perhatian tertuju pada angka yang berubah (DESIGN.md §5.3). --}}
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
            <x-admin.kpi-card title="Unit" :value="$after['units'] ?? 0" :subtitle="'Sebelumnya '.($before['units'] ?? 0)" icon="fas fa-building" :theme="($after['units'] ?? 0) !== ($before['units'] ?? 0) ? 'primary' : 'neutral'" />
            <x-admin.kpi-card title="Formasi" :value="$after['pairs'] ?? 0" :subtitle="'Sebelumnya '.($before['pairs'] ?? 0)" icon="fas fa-table-list" :theme="($after['pairs'] ?? 0) !== ($before['pairs'] ?? 0) ? 'primary' : 'neutral'" />
            <x-admin.kpi-card title="Total Kuota" :value="$after['kuota'] ?? 0" :subtitle="'Sebelumnya '.($before['kuota'] ?? 0)" icon="fas fa-users" :theme="($after['kuota'] ?? 0) !== ($before['kuota'] ?? 0) ? 'primary' : 'neutral'" />
            <x-admin.kpi-card title="Perubahan" :value="$changeCount" subtitle="Dari struktur lama" icon="fas fa-list-check" :theme="$changeCount > 0 ? 'warning' : 'neutral'" />
        </div>

        <form wire:submit="submit" class="space-y-6">
            <x-admin.form.error-summary />

            @php $sectionNumber = 0; @endphp

            {{-- Pasangan unit dan jabatan, unit ditutup, atribut unit baru --}}
            @if ($unitPairs !== [] || $currentOnly !== [] || $positionPairs !== [])
                <x-admin.form.section :number="++$sectionNumber" title="Pasangkan Unit dan Jabatan" description="Pasangkan nama baru dengan nama lama agar riwayat tetap utuh; unit yang tidak tercantum pada lampiran tidak diubah, kecuali ditutup">
                    @foreach ($unitPairs as $i => $pair)
                        <div wire:key="unit-pair-{{ $i }}" class="grid grid-cols-1 md:grid-cols-2 gap-6 items-end">
                            <div>
                                <p class="text-xs font-bold text-surface-700 mb-1.5">Unit pada lampiran</p>
                                <p class="text-sm text-surface-900">{{ $pair['new'] }}</p>
                            </div>
                            <x-admin.form.select name="unitPairs.{{ $i }}.old" label="Adalah penggantian nama dari" wire:model="unitPairs.{{ $i }}.old" :options="array_combine($currentOnly, $currentOnly) ?: []" placeholder="Unit baru (bukan pengganti nama)" />
                        </div>
                    @endforeach

                    @foreach ($newUnits as $i => $row)
                        <div wire:key="new-unit-{{ $i }}" class="rounded-xl border border-surface-200 bg-surface-50/60 p-4 space-y-4">
                            <p class="text-xs font-bold text-surface-700">Atribut unit baru: <span class="text-surface-900">{{ $row['unit'] }}</span></p>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                                <x-admin.form.input name="newUnits.{{ $i }}.kode" label="Kode" wire:model="newUnits.{{ $i }}.kode" required hint="Contoh: Kebun, PKS, Bagian" />
                                <x-admin.form.select name="newUnits.{{ $i }}.wilayah" label="Wilayah" wire:model="newUnits.{{ $i }}.wilayah" :options="array_combine($wilayahList, $wilayahList)" required />
                                <x-admin.form.input name="newUnits.{{ $i }}.urutan" type="number" label="Urutan" wire:model="newUnits.{{ $i }}.urutan" min="1" />
                            </div>
                        </div>
                    @endforeach

                    @php($closeable = array_values(array_diff($currentOnly, $chosenOld)))
                    @if ($closeable !== [])
                        {{-- Opsional dan tertutup bawaannya: SK dapat mengatur sebagian struktur, jadi unit yang tidak tercantum tidak diubah. --}}
                        <details wire:key="close-units" class="rounded-xl border border-surface-200 px-4 py-3" @if ($closed !== []) open @endif>
                            <summary class="cursor-pointer text-sm font-semibold text-surface-800 rounded focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500">
                                Tutup unit (opsional)
                                <span class="font-normal text-surface-500">· {{ count($closeable) }} unit tidak tercantum pada lampiran dan tidak diubah</span>
                            </summary>
                            <fieldset class="mt-3 space-y-2">
                                <legend class="sr-only">Unit yang ditutup</legend>
                                <p class="text-xs text-surface-600">Centang hanya unit yang ditutup oleh SK ini. Unit yang masih berpenghuni tetap aktif sampai karyawannya dipindahkan melalui Mutasi.</p>
                                @foreach ($closeable as $name)
                                    <label wire:key="close-{{ $name }}" class="flex items-center gap-2 text-sm text-surface-700">
                                        <input type="checkbox" wire:model="closed" value="{{ $name }}" class="rounded border-surface-300 text-primary-600 focus-visible:ring-primary-500">
                                        {{ $name }}
                                    </label>
                                @endforeach
                            </fieldset>
                        </details>
                    @endif

                    @foreach ($positionPairs as $i => $pair)
                        <div wire:key="position-pair-{{ $i }}" class="grid grid-cols-1 md:grid-cols-2 gap-6 items-end">
                            <div>
                                <p class="text-xs font-bold text-surface-700 mb-1.5">Jabatan pada lampiran ({{ $pair['unit'] }})</p>
                                <p class="text-sm text-surface-900">{{ $pair['new'] }}</p>
                            </div>
                            @php($options = $positionOptions[$pair['unit']] ?? [])
                            <x-admin.form.select name="positionPairs.{{ $i }}.old" label="Adalah penggantian nama dari" wire:model="positionPairs.{{ $i }}.old" :options="array_combine($options, $options) ?: []" placeholder="Jabatan baru (bukan pengganti nama)" />
                        </div>
                    @endforeach
                </x-admin.form.section>
            @endif

            <x-admin.form.section :number="++$sectionNumber" title="Perubahan terhadap Struktur Saat Ini" description="Hasil perbandingan lampiran SK dengan struktur organisasi di aplikasi">
                <x-admin.structure.diff-list :diff="$diff" />
            </x-admin.form.section>

            {{-- Dampak: blokir dan peringatan (DESIGN.md §7.2) --}}
            <x-admin.form.section :number="++$sectionNumber" title="Dampak" description="Pemeriksaan aturan dan dampak pada karyawan sebelum SK diajukan">
                @if ($blockerCount > 0)
                    <div role="alert" aria-live="polite" class="rounded-xl border border-danger-200 bg-danger-50 px-4 py-3 text-sm text-danger-800 space-y-1">
                        <p class="font-bold"><i class="fas fa-lock mr-1.5" aria-hidden="true"></i>{{ $blockerCount }} hal perlu diselesaikan sebelum SK dapat diajukan:</p>
                        <ul class="list-disc pl-5">
                            @foreach ($blockers as $blocker)
                                <li>{{ $blocker }}</li>
                            @endforeach
                        </ul>
                    </div>
                @else
                    <div role="status" class="flex items-center gap-2 rounded-xl border border-primary-200 bg-primary-50 px-4 py-2.5 text-sm text-primary-900">
                        <i class="fas fa-circle-check" aria-hidden="true"></i>
                        <span>Semua syarat terpenuhi. SK dapat diajukan.</span>
                    </div>
                @endif

                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-2 text-sm">
                    <div class="flex items-baseline justify-between gap-3"><dt class="text-surface-600">Karyawan terdampak</dt><dd class="font-mono tabular-nums font-semibold text-surface-900">{{ $impact['affected_employees'] }}</dd></div>
                    <div class="flex items-baseline justify-between gap-3"><dt class="text-surface-600">Formasi jabatan melebihi kuota</dt><dd class="font-mono tabular-nums font-semibold {{ $impact['over_quota_seats'] > 0 ? 'text-warning-800' : 'text-surface-900' }}">{{ $impact['over_quota_seats'] }}</dd></div>
                </dl>

                @if ($impact['scheduled_mutations'] !== [])
                    <div role="status" class="rounded-xl border border-warning-200 bg-warning-50 px-4 py-3 text-sm text-warning-800 space-y-1">
                        <p class="font-semibold"><i class="fas fa-triangle-exclamation mr-1.5" aria-hidden="true"></i>{{ count($impact['scheduled_mutations']) }} mutasi terjadwal terkena perubahan ini:</p>
                        <ul class="list-disc pl-5">
                            @foreach ($impact['scheduled_mutations'] as $item)
                                <li>{{ $item }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <p class="text-xs text-surface-500">Penerapan SK tidak memindahkan karyawan. Karyawan pada formasi yang dihapus atau dikurangi dipindahkan melalui Mutasi.</p>
            </x-admin.form.section>

            <x-admin.form.footer :cancel-url="route('admin.master.sk.index')" cancel-label="Simpan sebagai Draf" submit-label="Ajukan untuk Persetujuan" icon="fas fa-paper-plane" :once="false" target="submit" :disabled="$blockerCount > 0"
                :note="$blockerCount > 0 ? 'Ajukan nonaktif sampai '.$blockerCount.' hal di atas selesai.' : 'Pengajuan SK tercatat pada riwayat perubahan master.'">
                <x-slot:actions>
                    <x-admin.button variant="soft" icon="fas fa-calculator" wire:click="recalculate" target="recalculate">Hitung Ulang Perubahan</x-admin.button>
                </x-slot:actions>
            </x-admin.form.footer>
        </form>
    @endif
</div>
