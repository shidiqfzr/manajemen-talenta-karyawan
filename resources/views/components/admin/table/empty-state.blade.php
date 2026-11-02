@props(['colspan' => 1, 'message' => 'Data tidak ditemukan.', 'icon' => 'fas fa-clipboard-check'])

<tr>
    <td colspan="{{ $colspan }}" class="px-4 py-8 text-center text-surface-500">
        <i class="{{ $icon }} text-2xl mb-2"></i>
        <p>{{ $message }}</p>
    </td>
</tr>
