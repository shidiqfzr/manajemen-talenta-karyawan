@props([
    'training' => null,
    'action',
    'method' => 'POST'
])

<form action="{{ $action }}" method="POST" class="space-y-6">
    @csrf
    @if($method === 'PUT')
        @method('PUT')
    @endif

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div>
            <x-admin.form.input name="judul" label="Judul Pelatihan" :value="old('judul', $training->judul ?? '')" required />
        </div>
        <div>
            <x-admin.form.input name="jenis" label="Jenis Pelatihan" :value="old('jenis', $training->jenis ?? '')" required />
        </div>
        <div>
            <x-admin.form.input name="metode" label="Metode" :value="old('metode', $training->metode ?? '')" required />
        </div>
        <div>
            <x-admin.form.input type="date" name="tanggal_mulai" label="Tanggal Mulai" :value="old('tanggal_mulai', $training->tanggal_mulai ?? '')" required />
        </div>
        <div>
            <x-admin.form.input type="date" name="tanggal_akhir" label="Tanggal Akhir" :value="old('tanggal_akhir', $training->tanggal_akhir ?? '')" required />
        </div>
        <div>
            <x-admin.form.input type="number" name="biaya_tiket_peserta" label="Biaya Tiket Peserta (Rp)" :value="old('biaya_tiket_peserta', $training->biaya_tiket_peserta ?? '')" required />
        </div>
        <div>
            <x-admin.form.input type="number" name="biaya_pelatihan" label="Biaya Pelatihan (Rp)" :value="old('biaya_pelatihan', $training->biaya_pelatihan ?? '')" required />
        </div>
        <div>
            <x-admin.form.input name="penyelenggara" label="Penyelenggara" :value="old('penyelenggara', $training->penyelenggara ?? '')" required />
        </div>
        <div>
            <x-admin.form.input name="nomor_surat" label="Nomor Surat" :value="old('nomor_surat', $training->nomor_surat ?? '')" required />
        </div>
        <div>
            <x-admin.form.input type="date" name="tanggal_surat" label="Tanggal Surat" :value="old('tanggal_surat', $training->tanggal_surat ?? '')" required />
        </div>
        <div>
            <x-admin.form.input name="bidang_pelatihan" label="Bidang Pelatihan" :value="old('bidang_pelatihan', $training->bidang_pelatihan ?? '')" required />
        </div>
        <div>
            <x-admin.form.input type="number" name="jam_belajar_per_hari" label="Jam Belajar per Hari" :value="old('jam_belajar_per_hari', $training->jam_belajar_per_hari ?? '')" required />
        </div>
        
        <div class="md:col-span-2">
            <label class="block text-xs font-bold text-surface-700 mb-1.5">Keterangan</label>
            <textarea name="keterangan" class="w-full px-3.5 py-2.5 bg-surface-50/70 border border-surface-200 rounded-xl text-xs sm:text-sm font-medium text-surface-800 focus:bg-white focus:outline-none focus:ring-2 focus:ring-primary-500/30 focus:border-primary-500 transition" rows="3">{{ old('keterangan', $training->keterangan ?? '') }}</textarea>
            @error('keterangan') <p class="text-danger text-xs mt-1">{{ $message }}</p> @enderror
        </div>
    </div>

    <!-- Tombol Aksi -->
    <div class="flex justify-end space-x-3 mt-6">
        <x-admin.button href="{{ route('admin.trainings.index') }}" variant="secondary">Batal</x-admin.button>
        <x-admin.button type="submit" once>Simpan</x-admin.button>
    </div>
</form>
