<div class="inline-flex items-center gap-2">
    <a href="{{ route('admin.evaluations.show', $row->id) }}"
       class="w-8 h-8 flex items-center justify-center rounded-md bg-info text-white hover:bg-info transition"
       title="Lihat Detail">
        <i class="fas fa-eye" aria-hidden="true"></i>
    </a>

    <button wire:click="deleteRow({{ $row->id }})"
            onclick="confirm('Yakin ingin menghapus data evaluasi ini?') || event.stopImmediatePropagation()"
            class="w-8 h-8 flex items-center justify-center rounded-md bg-danger text-white hover:bg-danger transition"
            title="Hapus">
        <i class="fas fa-trash" aria-hidden="true"></i>
    </button>
</div>
