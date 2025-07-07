@props(['data'])

@if ($data instanceof \Illuminate\Pagination\LengthAwarePaginator && $data->hasPages())
    <div class="px-4 py-4 border-t border-gray-200 bg-gray-50">
        {{ $data->appends(request()->query())->links('pagination::tailwind') }}
    </div>
@endif
