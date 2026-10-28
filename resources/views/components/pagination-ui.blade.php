@props(['data'])

@if ($data instanceof \Illuminate\Pagination\LengthAwarePaginator && $data->hasPages())
    <div class="px-4 py-4 border-t border-surface-200 bg-surface-50">
        {{ $data->appends(request()->query())->links('pagination::tailwind') }}
    </div>
@endif
