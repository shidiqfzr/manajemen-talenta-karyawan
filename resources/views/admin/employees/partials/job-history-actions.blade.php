<div class="inline-flex items-center gap-2">
  <a href="{{ route('admin.employees.job-history.edit', ['employee' => $row->employee_nik, 'job_history' => $row->id]) }}"
     class="px-3 py-1.5 rounded-md bg-yellow-400 text-white hover:bg-yellow-500 transition"
     title="Edit">
    <i class="fas fa-edit"></i>
  </a>

  <button wire:click="deleteRow({{ $row->id }})"
          onclick="return confirm('Hapus riwayat jabatan ini?')"
          class="px-3 py-1.5 rounded-md bg-red-500 text-white hover:bg-red-600 transition"
          title="Hapus">
    <i class="fas fa-trash"></i>
  </button>
</div>
