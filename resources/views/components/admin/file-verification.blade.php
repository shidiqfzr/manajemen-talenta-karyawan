{{--
    Kartu verifikasi berkas: ukuran dan kode unik (SHA-256) yang dapat disalin, untuk memastikan berkas yang diterapkan
    sama dengan yang ditinjau (SECURITY.md §4.4). Dipakai oleh Riwayat & Persetujuan Impor dan Surat Keputusan Struktur.

    <x-admin.file-verification :hash="$log->file_sha256" :size="$log->file_size" />
    <x-admin.file-verification :hash="$decree->source_sha256" />   (ukuran tidak diketahui: baris Ukuran disembunyikan)

    size: byte (opsional). Tanpa hash tampil "-" dan tombol salin tidak dirender.
--}}
@props(['hash' => null, 'size' => null, 'title' => 'Verifikasi Berkas'])

<section class="bg-white rounded-2xl border border-surface-200/80 p-5 shadow-xs" aria-labelledby="verify-title">
    <h3 id="verify-title" class="text-base font-bold text-surface-900 mb-3">{{ $title }}</h3>
    <dl class="space-y-3">
        @if ($size !== null)
            <div>
                <dt class="text-xs font-medium text-surface-500">Ukuran</dt>
                <dd class="text-sm font-semibold text-surface-900">{{ number_format($size / 1024, 1, ',', '.') }} KB</dd>
            </div>
        @endif
        <div x-data="{ copied: false }">
            <dt class="text-xs font-medium text-surface-500" title="Kode unik berkas (SHA-256) untuk memastikan berkas yang disimpan sama dengan yang ditinjau.">Kode Verifikasi Berkas</dt>
            <dd class="mt-1 flex items-start gap-2">
                <span class="flex-1 font-mono text-xs text-surface-700 break-all">{{ $hash ?? '-' }}</span>
                @if ($hash)
                    <button type="button" @click="navigator.clipboard.writeText('{{ $hash }}'); copied = true; setTimeout(() => copied = false, 2000)"
                        aria-label="Salin kode verifikasi berkas"
                        class="shrink-0 w-8 h-8 rounded-lg border border-surface-200 text-surface-600 hover:bg-surface-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500">
                        <i class="fas" :class="copied ? 'fa-check text-primary-700' : 'fa-copy'" aria-hidden="true"></i>
                    </button>
                @endif
            </dd>
        </div>
    </dl>
</section>
