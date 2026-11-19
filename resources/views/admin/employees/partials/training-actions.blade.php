<div class="inline-flex items-center gap-2">
    <a href="{{ route('admin.trainings.show', $row->id) }}"
        class="w-8 h-8 flex items-center justify-center rounded-md bg-info text-white hover:bg-info transition"
        title="Lihat Detail">
        <i class="fas fa-eye" aria-hidden="true"></i>
    </a>

    <button wire:click="detachTraining({{ $row->id }})" wire:loading.attr="disabled" wire:target="detachTraining"
        onclick="confirm('Yakin ingin menghapus pelatihan ini dari karyawan?') || event.stopImmediatePropagation()"
        class="w-8 h-8 flex items-center justify-center rounded-md bg-danger text-white hover:bg-danger transition"
        title="Hapus">
        <i class="fas fa-trash" aria-hidden="true"></i>
    </button>
</div>
