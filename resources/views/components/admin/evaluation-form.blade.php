@props([
    'evaluation' => null,
    'employee' => null,
    'employees' => [],
    'action',
    'method' => 'POST'
])

<form action="{{ $action }}" method="POST" class="space-y-6">
    @csrf
    @if($method === 'PUT')
        @method('PUT')
    @endif

    {{-- Pilih Karyawan --}}
    @if ($employee)
        <div>
            <label class="block text-xs font-bold text-slate-700 mb-1.5">Karyawan</label>
            <input type="text" value="{{ $employee->nik }} - {{ $employee->nama }}" class="w-full px-3.5 py-2.5 bg-slate-100 border border-slate-200 rounded-xl text-xs sm:text-sm font-medium text-slate-600 cursor-not-allowed" readonly>
            <input type="hidden" name="employee_nik" value="{{ $employee->nik }}">
        </div>
    @elseif ($evaluation)
        {{-- When editing, employee is fixed --}}
        <div>
            <label class="block text-xs font-bold text-slate-700 mb-1.5">Karyawan</label>
            <input type="text" value="{{ $evaluation->employee->nik }} - {{ $evaluation->employee->nama }}" class="w-full px-3.5 py-2.5 bg-slate-100 border border-slate-200 rounded-xl text-xs sm:text-sm font-medium text-slate-600 cursor-not-allowed" readonly>
        </div>
    @else
        <div>
            <x-admin.form.select 
                name="employee_nik" 
                label="Pilih Karyawan" 
                :options="collect($employees)->mapWithKeys(fn($emp) => [$emp->nik => $emp->nik . ' - ' . $emp->nama])->toArray()" 
                required 
            />
        </div>
    @endif

    {{-- Bidang Tugas --}}
    <div>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div>
                <x-admin.form.input name="bidang_tugas" label="Bidang Tugas" :value="old('bidang_tugas', $evaluation->bidang_tugas ?? '')" />
            </div>
        </div>
    </div>

    {{-- Nilai Per Kriteria --}}
    <div>
        <h3 class="text-sm font-bold mb-3 border-b border-slate-200 pb-2 text-slate-800">Nilai Per Kriteria</h3>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div><x-admin.form.input type="number" step="0.01" min="0" max="10" name="nilai_kepemimpinan" label="Nilai Kepemimpinan" :value="old('nilai_kepemimpinan', $evaluation->nilai_kepemimpinan ?? '')" /></div>
            <div><x-admin.form.input type="number" step="0.01" min="0" max="10" name="nilai_perilaku_budaya" label="Nilai Perilaku Budaya" :value="old('nilai_perilaku_budaya', $evaluation->nilai_perilaku_budaya ?? '')" /></div>
            <div><x-admin.form.input type="number" step="0.01" min="0" max="10" name="nilai_pengalaman_teknis" label="Nilai Pengalaman Teknis" :value="old('nilai_pengalaman_teknis', $evaluation->nilai_pengalaman_teknis ?? '')" /></div>
            <div><x-admin.form.input type="number" step="0.01" min="0" max="10" name="nilai_kematangan_pribadi" label="Nilai Kematangan Pribadi" :value="old('nilai_kematangan_pribadi', $evaluation->nilai_kematangan_pribadi ?? '')" /></div>
            <div><x-admin.form.input type="number" step="0.01" min="0" max="10" name="nilai_tertimbang" label="Nilai Tertimbang (Auto)" :value="old('nilai_tertimbang', $evaluation->nilai_tertimbang ?? '')" disabled hint="Dihitung otomatis saat disimpan" /></div>
        </div>
    </div>

    {{-- Skor 9 Box --}}
    <div>
        <h3 class="text-sm font-bold mb-3 border-b border-slate-200 pb-2 text-slate-800">Skor 9 Box</h3>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div><x-admin.form.input type="number" step="0.01" min="0" max="100" name="skor_smkbk_9box" label="Skor SMK-BK" :value="old('skor_smkbk_9box', $evaluation->skor_smkbk_9box ?? '')" /></div>
            <div><x-admin.form.input type="number" step="0.01" min="0" max="100" name="skor_cli_9box" label="Skor CLI" :value="old('skor_cli_9box', $evaluation->skor_cli_9box ?? '')" /></div>
            <div><x-admin.form.input name="kategori_9box" label="Kategori" :value="old('kategori_9box', $evaluation->kategori_9box ?? '')" /></div>
        </div>
    </div>

    {{-- Asesmen --}}
    <div>
        <h3 class="text-sm font-bold mb-3 border-b border-slate-200 pb-2 text-slate-800">Asesmen</h3>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div><x-admin.form.input name="lembaga_asesmen" label="Lembaga Asesmen" :value="old('lembaga_asesmen', $evaluation->lembaga_asesmen ?? '')" /></div>
            <div><x-admin.form.input type="date" name="tanggal_pelaksanaan_asesmen" label="Tanggal Pelaksanaan" :value="old('tanggal_pelaksanaan_asesmen', $evaluation ? \Carbon\Carbon::parse($evaluation->tanggal_pelaksanaan_asesmen)->format('Y-m-d') : '')" /></div>
            <div><x-admin.form.input type="number" step="0.01" min="0" max="10" name="hasil_skor_asesmen" label="Hasil Skor" :value="old('hasil_skor_asesmen', $evaluation->hasil_skor_asesmen ?? '')" /></div>
            <div><x-admin.form.input name="kategori_asesmen" label="Kategori Asesmen" :value="old('kategori_asesmen', $evaluation->kategori_asesmen ?? '')" /></div>
            <div><x-admin.form.input name="keterangan_asesmen" label="Keterangan Asesmen" :value="old('keterangan_asesmen', $evaluation->keterangan_asesmen ?? '')" /></div>
            <div><x-admin.form.input type="date" name="expired_asesmen" label="Expired Asesmen" :value="old('expired_asesmen', $evaluation && $evaluation->expired_asesmen ? \Carbon\Carbon::parse($evaluation->expired_asesmen)->format('Y-m-d') : '')" /></div>
        </div>
    </div>

    <!-- Actions -->
    <div class="flex justify-end space-x-3 mt-8 pt-4 border-t border-slate-100">
        <a href="{{ route('admin.evaluations.index') }}" class="px-5 py-2.5 rounded-xl border border-slate-200 bg-white text-slate-700 hover:bg-slate-50 font-semibold transition">Batal</a>
        <button type="submit" class="px-5 py-2.5 rounded-xl bg-emerald-600 text-white hover:bg-emerald-700 font-semibold transition shadow-md">Simpan</button>
    </div>
</form>
