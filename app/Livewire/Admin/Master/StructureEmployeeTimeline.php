<?php

namespace App\Livewire\Admin\Master;

use App\Models\Employee;
use App\Services\Structure\EmployeePositionTimelineService;
use App\Support\NikMasker;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Riwayat Jabatan Karyawan (tab pada Versi Struktur, DOMAIN.md §3.7): cari karyawan, lalu lihat penempatannya dari waktu ke
 * waktu terhadap versi struktur yang berlaku. Baca-saja. NIK tidak pernah tampil atau tersimpan utuh pada halaman:
 * pilihan diwakili token terenkripsi yang hanya dapat diterjemahkan server.
 */
class StructureEmployeeTimeline extends Component
{
    private const TERM_MAX_LENGTH = 100;

    public string $term = '';

    /** Token terenkripsi karyawan terpilih ('' bila belum memilih). */
    public string $selected = '';

    public bool $notFound = false;

    /** Rute middleware tidak diterapkan ulang pada permintaan update Livewire, jadi tolak non-admin di setiap permintaan. */
    public function boot(): void
    {
        abort_unless(Gate::allows('struktur.prepare'), 403, 'Unauthorized.');
    }

    public function updatedTerm(): void
    {
        $this->term = mb_substr(trim($this->term), 0, self::TERM_MAX_LENGTH);
        $this->notFound = false;
    }

    public function select(string $token): void
    {
        $valid = app(EmployeePositionTimelineService::class)->resolve($token) !== null;

        $this->selected = $valid ? $token : '';
        $this->notFound = ! $valid;
    }

    public function clear(): void
    {
        $this->selected = '';
        $this->notFound = false;
    }

    /** @return list<array{token: string, nama: string, nik_masked: string, jabatan: ?string, unit_kerja: ?string}> */
    #[Computed]
    public function results(): array
    {
        return app(EmployeePositionTimelineService::class)->search($this->term);
    }

    /** @return array{nama: string, nik_masked: string, jabatan: ?string, unit_kerja: ?string}|null */
    #[Computed]
    public function employee(): ?array
    {
        $nik = app(EmployeePositionTimelineService::class)->resolve($this->selected);
        $employee = $nik === null ? null : Employee::query()->find($nik, ['nik', 'nama', 'jabatan', 'unit_kerja']);

        return $employee === null ? null : [
            'nama' => $employee->nama,
            'nik_masked' => NikMasker::mask($employee->nik),
            'jabatan' => $employee->jabatan,
            'unit_kerja' => $employee->unit_kerja,
        ];
    }

    /** @return list<array<string, mixed>> */
    #[Computed]
    public function timeline(): array
    {
        $service = app(EmployeePositionTimelineService::class);
        $nik = $service->resolve($this->selected);

        return $nik === null ? [] : $service->timeline($nik);
    }

    public function render(): View
    {
        return view('livewire.admin.master.structure-employee-timeline');
    }
}
