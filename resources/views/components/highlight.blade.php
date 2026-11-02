@props([
    'text' => '',
    'term' => null,
    'class' => 'bg-warning-100 text-warning-800 font-semibold px-0.5 rounded',
])

@php
    $searchTerm = trim((string) ($term ?? request('search', '')));
    $safeText = e($text ?? '');
@endphp

@if(empty($searchTerm) || $safeText === '')
    {!! $safeText !!}
@else
    @php
        $pattern = '/' . preg_quote($searchTerm, '/') . '/i';
        $highlighted = preg_replace_callback($pattern, function ($matches) use ($class) {
            return '<mark class="' . $class . '">' . $matches[0] . '</mark>';
        }, $safeText);
    @endphp
    {!! $highlighted !!}
@endif
