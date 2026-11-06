<div class="inline-flex items-center gap-2">
  <a href="{{ route('admin.employees.job-history.edit', ['employee' => $row->employee_nik, 'job_history' => $row->id]) }}"
     class="w-8 h-8 flex items-center justify-center rounded-md bg-warning-700 text-white hover:bg-warning-700 transition"
     title="Edit">
    <i class="fas fa-edit" aria-hidden="true"></i>
  </a>

  <button wire:click="deleteRow({{ $row->id }})" wire:loading.attr="disabled" wire:target="deleteRow"
          onclick="confirm('Hapus riwayat jabatan ini?') || event.stopImmediatePropagation()"
          class="w-8 h-8 flex items-center justify-center rounded-md bg-danger text-white hover:bg-danger transition"
          title="Hapus">
    <i class="fas fa-trash" aria-hidden="true"></i>
  </button>
</div>
