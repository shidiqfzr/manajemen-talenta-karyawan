{{--
    Pemberitahuan hasil aksi tabel (DESIGN.md §7.7), dirender oleh x-admin.table.card.

    <x-admin.table.notice :notice="$tableNotice" />
    notice = ['type' => 'success'|'error'|'warning', 'message' => string] atau null.

    $tableNotice dapat diubah dari sisi klien: tipe selain success/warning diperlakukan sebagai error,
    pesan bukan string tidak dirender, dan pesan selalu dicetak lewat {{ }} (escape).
    Sukses = role="status", error/peringatan = role="alert"; warna mengikuti x-admin.flash.
--}}
@props(['notice' => null])

@php
    $message = is_array($notice) && is_string($notice['message'] ?? null) ? trim($notice['message']) : '';
    $type = is_array($notice) && in_array($notice['type'] ?? null, ['success', 'error', 'warning'], true) ? $notice['type'] : 'error';

    $styles = [
        'success' => ['role' => 'status', 'box' => 'bg-primary-50 text-primary-800 border-primary-200', 'icon' => 'fas fa-circle-check', 'close' => 'text-primary-700 hover:text-primary-900'],
        'warning' => ['role' => 'alert', 'box' => 'bg-warning-50 text-warning-800 border-warning-200', 'icon' => 'fas fa-triangle-exclamation', 'close' => 'text-warning-700 hover:text-warning-800'],
        'error' => ['role' => 'alert', 'box' => 'bg-danger-50 text-danger-700 border-danger-200', 'icon' => 'fas fa-triangle-exclamation', 'close' => 'text-danger-700 hover:text-danger-800'],
    ][$type];
@endphp

@if ($message !== '')
    <div class="px-3 pt-3 sm:px-4 sm:pt-4">
        <div role="{{ $styles['role'] }}" class="flex items-start justify-between gap-3 p-4 border rounded-xl shadow-xs text-sm font-medium {{ $styles['box'] }}">
            <div class="flex items-start gap-2.5">
                <i class="{{ $styles['icon'] }} mt-0.5" aria-hidden="true"></i>
                <span>{{ $message }}</span>
            </div>
            <button type="button" wire:click="$set('tableNotice', null)" aria-label="Tutup pemberitahuan"
                class="{{ $styles['close'] }} cursor-pointer rounded focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500">
                <i class="fas fa-times text-xs" aria-hidden="true"></i>
            </button>
        </div>
    </div>
@endif
