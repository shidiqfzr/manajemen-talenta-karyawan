{{--
    Formulir Penilaian Karyawan (tambah dan ubah). Empat segmen bernomor, bar aksi menempel di bawah (DESIGN.md §7.1).
    Skala nilai dari konstanta model Evaluation dan bobot kriteria dari EvaluationService, sumber yang sama
    dengan EvaluationRequest dan rumus nilai tertimbang, sehingga angka pada formulir tidak dapat menyimpang.

    <x-admin.evaluation-form :employees="$employees" action="{{ route('admin.evaluations.store') }}" />
    <x-admin.evaluation-form :employee="$employee" action="{{ route('admin.evaluations.store') }}" />
    <x-admin.evaluation-form :evaluation="$evaluation" action="{{ route('admin.evaluations.update', $evaluation) }}" method="PUT" />
--}}
@props([
    'evaluation' => null,
    'employee' => null,
    'employees' => [],
    'action',
    'method' => 'POST'
])

@php
    use App\Models\Evaluation;
    use App\Services\EvaluationService;

    // Karyawan yang sudah ditentukan (dari profil karyawan atau penilaian yang sedang diubah) tidak dapat diganti.
    $fixedEmployee = $employee ?? $evaluation?->employee;
    $nilaiHint = fn (string $kriteria) => 'Skala 0 sampai '.Evaluation::NILAI_MAX.', bobot '.EvaluationService::bobotPersen($kriteria).'%';
    $skorHint = 'Skala 0 sampai '.Evaluation::SKOR_MAX;
    $tanggal = fn (string $field) => $evaluation?->{$field}?->format('Y-m-d') ?? '';
@endphp

<form action="{{ $action }}" method="POST" class="space-y-6">
    @csrf
    @if($method === 'PUT')
        @method('PUT')
    @endif

    <x-admin.form.error-summary />

    <x-admin.form.section number="1" title="Karyawan yang Dinilai" description="Karyawan aktif dan bidang tugas pada saat penilaian" legend>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            @if ($fixedEmployee)
                <div>
                    <label for="employee_display" class="block text-xs font-bold text-surface-700 mb-1.5">Karyawan</label>
                    <input id="employee_display" type="text" value="{{ $fixedEmployee->nik }} - {{ $fixedEmployee->nama }}" class="w-full px-3.5 py-2.5 bg-surface-100 border border-surface-200 rounded-lg text-xs sm:text-sm font-medium text-surface-600 cursor-not-allowed" readonly>
                    @unless ($evaluation)
                        <input type="hidden" name="employee_nik" value="{{ $fixedEmployee->nik }}">
                    @endunless
                </div>
            @else
                <div>
                    <label for="employee_nik" class="block text-xs font-bold text-surface-700 mb-1.5">Pilih Karyawan <span class="text-danger">*</span></label>
                    <x-admin.searchable-select
                        name="employee_nik"
                        :selected="old('employee_nik')"
                        :options="collect($employees)->map(fn ($emp) => ['value' => $emp->nik, 'label' => $emp->nik.' - '.$emp->nama])->values()->all()"
                        placeholder="-- Pilih Karyawan --"
                        searchPlaceholder="Cari nama atau NIK..."
                        icon="fas fa-user"
                        size="form"
                    />
                    @error('employee_nik') <p id="employee_nik-error" class="text-danger-700 text-sm mt-1">{{ $message }}</p> @enderror
                </div>
            @endif

            <x-admin.form.input name="bidang_tugas" label="Bidang Tugas" maxlength="255" placeholder="Contoh: Tanaman, Keuangan, Pengolahan" :value="$evaluation->bidang_tugas ?? ''" />
        </div>
    </x-admin.form.section>

    <x-admin.form.section number="2" title="Nilai Per Kriteria" description="Penilaian tiap pilar kompetensi; nilai tertimbang dihitung otomatis dari bobot kriteria">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            @foreach ([
                'nilai_kepemimpinan' => 'Nilai Kepemimpinan',
                'nilai_perilaku_budaya' => 'Nilai Perilaku Budaya',
                'nilai_pengalaman_teknis' => 'Nilai Pengalaman Teknis',
                'nilai_kematangan_pribadi' => 'Nilai Kematangan Pribadi',
            ] as $field => $label)
                <x-admin.form.input type="number" step="0.01" min="0" max="{{ Evaluation::NILAI_MAX }}" :name="$field" :label="$label" :hint="$nilaiHint($field)" :value="$evaluation->{$field} ?? ''" />
            @endforeach
            <x-admin.form.input name="nilai_tertimbang" label="Nilai Tertimbang" :value="$evaluation->nilai_tertimbang ?? ''" disabled placeholder="Terisi otomatis" hint="Dihitung otomatis saat disimpan" />
        </div>
    </x-admin.form.section>

    <x-admin.form.section number="3" title="Skor Matriks Talenta (9-Box)" description="Sumbu kinerja (SMK-BK) dan potensi (CLI) untuk pemetaan talenta">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <x-admin.form.input type="number" step="0.01" min="0" max="{{ Evaluation::SKOR_MAX }}" name="skor_smkbk_9box" label="Skor SMK-BK" :hint="$skorHint" :value="$evaluation->skor_smkbk_9box ?? ''" />
            <x-admin.form.input type="number" step="0.01" min="0" max="{{ Evaluation::SKOR_MAX }}" name="skor_cli_9box" label="Skor CLI" :hint="$skorHint" :value="$evaluation->skor_cli_9box ?? ''" />
            <x-admin.form.select name="kategori_9box" label="Kategori 9-Box" placeholder="-- Belum Dikategorikan --" hint="Kuadran matriks talenta: potensi (CLI) terhadap kinerja (SMK-BK)" :options="Evaluation::boxOptions($evaluation->kategori_9box ?? null)" :selected="$evaluation->kategori_9box ?? ''" />
        </div>
    </x-admin.form.section>

    <x-admin.form.section number="4" title="Asesmen & Catatan" description="Hasil asesmen eksternal, masa berlaku, dan catatan penilaian">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <x-admin.form.input name="lembaga_asesmen" label="Lembaga Asesmen" maxlength="255" placeholder="Contoh: Assessment Center Holding Perkebunan" :value="$evaluation->lembaga_asesmen ?? ''" />
            <x-admin.form.input type="date" name="tanggal_pelaksanaan_asesmen" label="Tanggal Pelaksanaan" :value="$tanggal('tanggal_pelaksanaan_asesmen')" />
            <x-admin.form.input type="date" name="expired_asesmen" label="Masa Berlaku Asesmen" hint="Tidak boleh lebih awal dari Tanggal Pelaksanaan" :value="$tanggal('expired_asesmen')" />
            <x-admin.form.input type="number" step="0.01" min="0" max="{{ Evaluation::NILAI_MAX }}" name="hasil_skor_asesmen" label="Hasil Skor Asesmen" :hint="'Skala 0 sampai '.Evaluation::NILAI_MAX" :value="$evaluation->hasil_skor_asesmen ?? ''" />
            <x-admin.form.input name="kategori_asesmen" label="Kategori Asesmen" maxlength="255" placeholder="Contoh: Siap Promosi" :value="$evaluation->kategori_asesmen ?? ''" />
            <x-admin.form.input name="keterangan_asesmen" label="Keterangan Asesmen" maxlength="255" :value="$evaluation->keterangan_asesmen ?? ''" />
            <div class="md:col-span-3">
                <x-admin.form.textarea name="keterangan" label="Catatan Penilaian" maxlength="5000" placeholder="Catatan tambahan (opsional)" :value="$evaluation->keterangan ?? ''" />
            </div>
        </div>
    </x-admin.form.section>

    <x-admin.form.footer
        :cancel-url="route('admin.evaluations.index')"
        :submit-label="$evaluation ? 'Simpan Perubahan' : 'Simpan Penilaian'"
        note="Skor penilaian bersifat rahasia; perubahan tercatat pada riwayat perubahan tanpa memuat nilainya." />
</form>
