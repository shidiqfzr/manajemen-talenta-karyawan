<?php

namespace App\Livewire\Admin\Employees;

use App\Models\Employee;
use App\Models\JobHistory;
use App\Models\Position;
use App\Models\Unit;
use App\Services\PenugasanSementaraService;
use Illuminate\Support\Facades\Log;
use Livewire\Component;

class PenugasanSementaraManager extends Component
{
    public Employee $employee;

    // Modal state
    public bool $showModal = false;

    // Form properties
    public $unitKerja = '';

    public $jabatan = '';

    public $statusPenugasan = 'Plt'; // Default Plt

    public $tmtAwal = '';

    public $nomorSk = '';

    public $tanggalSk = '';

    public $catatan = '';

    // Collections
    public $units = [];

    public $positions = [];

    public $penugasanAktif = [];

    protected $rules = [
        'unitKerja' => 'required|string',
        'jabatan' => 'required|string',
        'statusPenugasan' => 'required|in:Plt,Pejabat Sementara (Pjs),Diperbantukan',
        'tmtAwal' => 'required|date',
        'nomorSk' => 'nullable|string',
        'tanggalSk' => 'nullable|date',
        'catatan' => 'nullable|string',
    ];

    public function mount(Employee $employee)
    {
        $this->employee = $employee;
        $this->tmtAwal = date('Y-m-d');
        $this->loadData();
    }

    public function loadData()
    {
        $service = app(PenugasanSementaraService::class);
        $this->penugasanAktif = $service->getPenugasanAktif($this->employee->nik);

        // Cache units for dropdown
        $this->units = Unit::where('is_active', true)->orderBy('nama')->get();
    }

    public function updatedUnitKerja($value)
    {
        if ($value) {
            $unit = Unit::where('nama', $value)->first();
            if ($unit) {
                $this->positions = Position::where('unit_id', $unit->id)
                    ->where('is_active', true)
                    ->orderBy('nama')
                    ->get();
            } else {
                $this->positions = [];
            }
        } else {
            $this->positions = [];
        }
        $this->jabatan = ''; // reset jabatan when unit changes
    }

    public function openModal()
    {
        $this->resetValidation();
        $this->unitKerja = '';
        $this->jabatan = '';
        $this->statusPenugasan = 'Plt';
        $this->tmtAwal = date('Y-m-d');
        $this->nomorSk = '';
        $this->tanggalSk = '';
        $this->catatan = '';
        $this->showModal = true;
    }

    public function closeModal()
    {
        $this->showModal = false;
    }

    public function simpanPenugasan(PenugasanSementaraService $service)
    {
        $this->validate();

        try {
            $service->tambahPenugasan($this->employee, [
                'unit_kerja' => $this->unitKerja,
                'jabatan' => $this->jabatan,
                'status_penugasan' => $this->statusPenugasan,
                'tmt_awal' => $this->tmtAwal,
                'nomor_sk' => $this->nomorSk,
                'tanggal_sk' => $this->tanggalSk,
                'catatan' => $this->catatan,
                'level' => $this->employee->level,
                'golongan' => $this->employee->golongan,
            ]);

            $this->closeModal();
            $this->loadData();

            // Dispatch browser event to show toast
            $this->dispatch('toast', ['message' => 'Penugasan sementara berhasil ditambahkan.', 'type' => 'success']);
        } catch (\Exception $e) {
            Log::error('Error tambah penugasan: '.$e->getMessage());
            $this->dispatch('toast', ['message' => 'Terjadi kesalahan: '.$e->getMessage(), 'type' => 'error']);
        }
    }

    public function cabutPenugasan($historyId, PenugasanSementaraService $service)
    {
        $history = JobHistory::findOrFail($historyId);

        try {
            $service->cabutPenugasan($history, date('Y-m-d'));
            $this->loadData();
            // Emit event so the job history table can refresh if needed
            $this->dispatch('job-history-updated');

            $this->dispatch('toast', ['message' => 'Penugasan sementara berhasil dicabut.', 'type' => 'success']);
        } catch (\Exception $e) {
            Log::error('Error cabut penugasan: '.$e->getMessage());
            $this->dispatch('toast', ['message' => 'Terjadi kesalahan: '.$e->getMessage(), 'type' => 'error']);
        }
    }

    public function render()
    {
        return view('livewire.admin.employees.penugasan-sementara-manager');
    }
}
