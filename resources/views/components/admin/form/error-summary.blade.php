{{--
    Ringkasan kesalahan di atas formulir: menerima fokus saat halaman dimuat ulang dengan galat dan
    menautkan tiap pesan ke isiannya (DESIGN.md §7.1, WCAG 3.3.1). Tidak tampil bila tidak ada galat.

    <x-admin.form.error-summary />
--}}
@if($errors->any())
    <div id="errorSummary" tabindex="-1" role="alert" x-data x-init="$nextTick(() => $el.focus())"
         class="rounded-xl border border-danger-200 bg-danger-50 p-4 sm:p-5 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500">
        <p class="text-sm font-bold text-danger-700">
            <i class="fas fa-circle-exclamation mr-1.5" aria-hidden="true"></i>
            {{ $errors->count() }} isian perlu diperbaiki sebelum data dapat disimpan
        </p>
        <ul class="mt-2 list-disc pl-5 space-y-1 text-sm text-danger-700">
            @foreach($errors->messages() as $field => $messages)
                <li><a href="#{{ $field }}" class="underline font-medium rounded focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500">{{ $messages[0] }}</a></li>
            @endforeach
        </ul>
    </div>
@endif
