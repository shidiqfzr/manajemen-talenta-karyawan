<?php

namespace App\Livewire\Admin\Master;

use App\Exceptions\StructureDecreeException;
use App\Models\MasterAuditLog;
use App\Models\StructureDecree;
use App\Services\Structure\StructureDecreeService;
use App\Services\Structure\StructureDiff;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

/**
 * Detail SK struktur organisasi: linimasa keputusan, selisih, dampak, dan keputusan (setujui, tolak, batalkan,
 * terapkan ulang). Aturan ada pada StructureDecreeService; layar hanya mencerminkannya (DESIGN.md §8.5a).
 */
class StructureDecreeDetail extends Component
{
    public StructureDecree $decree;

    public string $reason = '';

    public function mount(StructureDecree $decree): void
    {
        $this->authorizeAdmin();
        $this->decree = $decree;
    }

    public function approve(StructureDecreeService $service): void
    {
        $this->run(fn () => $service->approve($this->decree, auth()->user()), 'SK disetujui.');
    }

    public function reject(StructureDecreeService $service): void
    {
        $this->validateReason();
        $this->run(fn () => $service->reject($this->decree, auth()->user(), $this->reason), 'SK ditolak.');
    }

    public function cancel(StructureDecreeService $service): void
    {
        $this->validateReason();
        $this->run(fn () => $service->cancel($this->decree, auth()->user(), $this->reason), 'SK dibatalkan.');
    }

    public function reapply(StructureDecreeService $service): void
    {
        $this->run(fn () => $service->apply($this->decree), 'Penerapan ulang SK dijalankan.');
    }

    public function render(StructureDecreeService $service): View
    {
        $this->authorizeAdmin();
        $this->decree->refresh();
        $decree = $this->decree;
        $user = auth()->user();

        $fourEyesBlocksUser = (bool) config('hris.structure.require_approval') && $decree->submitted_by === $user->id;
        $isOpen = in_array($decree->status, StructureDecree::OPEN_STATUSES, true);

        return view('livewire.admin.master.structure-decree-detail', [
            'decree' => $decree,
            'diff' => StructureDiff::fromArray($decree->diff ?? []),
            'impact' => $isOpen && $decree->status !== StructureDecree::STATUS_FAILED ? $service->impact($decree) : null,
            'worklist' => $decree->status === StructureDecree::STATUS_APPLIED ? $service->worklist($decree) : collect(),
            'timeline' => MasterAuditLog::query()->where('decree_id', $decree->id)->where('entity_type', 'decree')->orderBy('id')->get(),
            'canApprove' => Gate::allows('struktur.approve') && $decree->status === StructureDecree::STATUS_SUBMITTED && ! $fourEyesBlocksUser,
            'blockedByFourEyes' => Gate::allows('struktur.approve') && $decree->status === StructureDecree::STATUS_SUBMITTED && $fourEyesBlocksUser,
            'canPrepare' => Gate::allows('struktur.prepare'),
            'isOpen' => $isOpen,
            'warnings' => ($decree->unit_mapping ?? [])['apply_warnings'] ?? [],
        ]);
    }

    private function validateReason(): void
    {
        $this->validate(
            ['reason' => 'required|string|min:10|max:500'],
            ['reason.required' => 'Alasan wajib diisi minimal 10 karakter.', 'reason.min' => 'Alasan wajib diisi minimal 10 karakter.', 'reason.max' => 'Alasan maksimal 500 karakter.'],
        );
    }

    private function run(callable $action, string $success): void
    {
        try {
            $this->decree = $action();
            session()->flash('success', $success);
        } catch (StructureDecreeException $e) {
            session()->flash('error', $e->getMessage());
        }

        $this->reason = '';
        $this->resetErrorBag();
        $this->decree->refresh();

        // Menutup dialog konfirmasi setelah aksi selesai (berhasil maupun ditolak aturan); pesan hasil tampil pada halaman.
        $this->dispatch('decree-action-finished');
    }

    private function authorizeAdmin(): void
    {
        abort_unless(Gate::allows('struktur.prepare'), 403, 'Unauthorized.');
    }
}
