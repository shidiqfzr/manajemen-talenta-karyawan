@extends('layouts.admin')

@section('content')
<div class="max-w-5xl mx-auto p-6 bg-white rounded-xl shadow-lg space-y-8">
    <h1 class="text-2xl font-bold text-center mb-6">Tambah Statistik Karyawan</h1>

        <form action="{{ route('admin.employees.statistics.store') }}" method="POST" class="space-y-8">
            @csrf

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label for="periode" class="block text-sm font-medium text-gray-700">Periode</label>
                    <input type="date" name="periode" id="periode" class="w-full border p-2 rounded" required>
                </div>

                <div>
                    <label for="jumlah_karpim" class="block text-sm font-medium text-gray-700">Jumlah Karpim</label>
                    <input type="number" name="jumlah_karpim" id="jumlah_karpim" class="w-full border p-2 rounded" required>
                </div>

                <div>
                    <label for="jumlah_karpel" class="block text-sm font-medium text-gray-700">Jumlah Karpel</label>
                    <input type="number" name="jumlah_karpel" id="jumlah_karpel" class="w-full border p-2 rounded" required>
                </div>

                <div>
                    <label for="total_karyawan" class="block text-sm font-medium text-gray-700">Total Karyawan</label>
                    <input type="number" name="total_karyawan" id="total_karyawan" class="w-full border p-2 rounded shadow-sm bg-gray-100 cursor-not-allowed" readonly>
                </div>
            </div>

            <div class="flex justify-end space-x-3">
                <a href="{{ route('admin.employees.statistics.index') }}"
                class="bg-gray-500 text-white px-4 py-2 rounded hover:bg-gray-600">Batal</a>
                <button type="submit"
                class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">Simpan</button>
            </div>
        </form>
    </div>
</div>

{{-- Auto-calculate total --}}
<script>
    const karpimInput = document.getElementById('jumlah_karpim');
    const karpelInput = document.getElementById('jumlah_karpel');
    const totalInput = document.getElementById('total_karyawan');

    function updateTotal() {
        const karpim = parseInt(karpimInput.value) || 0;
        const karpel = parseInt(karpelInput.value) || 0;
        totalInput.value = karpim + karpel;
    }

    karpimInput.addEventListener('input', updateTotal);
    karpelInput.addEventListener('input', updateTotal);
</script>
@endsection
