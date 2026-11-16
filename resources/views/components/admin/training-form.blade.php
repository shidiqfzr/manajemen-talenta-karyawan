{{--
    Formulir Pelatihan (tambah dan ubah). Tiga segmen bernomor, bar aksi menempel di bawah (DESIGN.md §7.1).
    Daftar Jenis, Metode, dan Bidang berasal dari model Training; batas isian dari konstanta model yang sama
    dengan TrainingRequest, sehingga formulir dan validasi server tidak pernah berbeda.

    <x-admin.training-form action="{{ route('admin.trainings.store') }}" />
    <x-admin.training-form :training="$training" action="{{ route('admin.trainings.update', $training) }}" method="PUT" />
--}}
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

    <x-admin.form.error-summary />

    <x-admin.form.section number="1" title="Informasi Program Pelatihan" description="Judul, jenis, metode, dan bidang fungsional pelatihan" legend>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="md:col-span-2">
                <x-admin.form.input name="judul" label="Judul Pelatihan" maxlength="255" placeholder="Contoh: Pelatihan Kepemimpinan Asisten Kepala Angkatan I" :value="$training->judul ?? ''" required />
            </div>
            <x-admin.form.select name="jenis" label="Jenis Pelatihan" :options="\App\Models\Training::jenisOptions($training->jenis ?? null)" :selected="$training->jenis ?? ''" required />
            <x-admin.form.select name="metode" label="Metode" :options="\App\Models\Training::metodeOptions($training->metode ?? null)" :selected="$training->metode ?? ''" required />
            <x-admin.form.select name="bidang_pelatihan" label="Bidang Pelatihan" :options="\App\Models\Training::bidangOptions($training->bidang_pelatihan ?? null)" :selected="$training->bidang_pelatihan ?? ''" required />
        </div>
    </x-admin.form.section>

    <x-admin.form.section number="2" title="Jadwal, Durasi & Biaya" description="Periode pelaksanaan, jam belajar untuk perhitungan Man Hours, dan biaya pelatihan">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <x-admin.form.input type="date" name="tanggal_mulai" label="Tanggal Mulai" :value="$training?->tanggal_mulai?->format('Y-m-d') ?? ''" required />
            <x-admin.form.input type="date" name="tanggal_akhir" label="Tanggal Akhir" :value="$training?->tanggal_akhir?->format('Y-m-d') ?? ''" required hint="Tidak boleh lebih awal dari Tanggal Mulai" />
            <x-admin.form.input type="number" min="1" max="{{ \App\Models\Training::JAM_BELAJAR_MAX }}" name="jam_belajar_per_hari" label="Jam Belajar per Hari" placeholder="Contoh: 8" :value="$training->jam_belajar_per_hari ?? ''" required hint="Dasar perhitungan Man Hours" />
            <x-admin.form.input type="number" min="0" max="{{ \App\Models\Training::BIAYA_MAX }}" name="biaya_tiket_peserta" label="Biaya Tiket Peserta (Rp)" placeholder="Contoh: 1500000" :value="$training->biaya_tiket_peserta ?? ''" required />
            <x-admin.form.input type="number" min="0" max="{{ \App\Models\Training::BIAYA_MAX }}" name="biaya_pelatihan" label="Biaya Pelatihan (Rp)" placeholder="Contoh: 5000000" :value="$training->biaya_pelatihan ?? ''" required />
        </div>
    </x-admin.form.section>

    <x-admin.form.section number="3" title="Penyelenggara & Surat Tugas" description="Lembaga penyelenggara, dasar surat tugas, dan catatan tambahan">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="md:col-span-2">
                <x-admin.form.input name="penyelenggara" label="Penyelenggara" maxlength="255" placeholder="Contoh: Lembaga Pendidikan dan Pelatihan Perkebunan" :value="$training->penyelenggara ?? ''" required />
            </div>
            <x-admin.form.input name="nomor_surat" label="Nomor Surat" maxlength="255" placeholder="Contoh: SRT/001/DIR/2026" :value="$training->nomor_surat ?? ''" required />
            <x-admin.form.input type="date" name="tanggal_surat" label="Tanggal Surat" :value="$training->tanggal_surat ?? ''" required />
            <div class="md:col-span-2">
                <x-admin.form.textarea name="keterangan" label="Keterangan" maxlength="5000" placeholder="Catatan tambahan (opsional)" :value="$training->keterangan ?? ''" />
            </div>
        </div>
    </x-admin.form.section>

    <x-admin.form.footer
        :cancel-url="$training ? route('admin.trainings.show', $training) : route('admin.trainings.index')"
        :submit-label="$training ? 'Simpan Perubahan' : 'Simpan Pelatihan'"
        note="Penambahan dan perubahan data pelatihan tercatat pada riwayat perubahan." />
</form>
