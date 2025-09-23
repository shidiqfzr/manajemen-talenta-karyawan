<div class="inline-flex items-center gap-2">
    <a href="{{ route('admin.trainings.show', $row->id) }}"
        class="w-8 h-8 flex items-center justify-center rounded-md bg-blue-400 text-white hover:bg-blue-500 transition"
        title="Lihat Detail">
        <i class="fas fa-eye"></i>
    </a>

    <button wire:click="detachTraining({{ $row->id }})"
        onclick="confirm('Yakin ingin menghapus pelatihan ini dari karyawan?') || event.stopImmediatePropagation()"
        class="w-8 h-8 flex items-center justify-center rounded-md bg-red-500 text-white hover:bg-red-600 transition"
        title="Hapus">
        <i class="fas fa-trash"></i>
    </button>
</div>
