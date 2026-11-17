<?php

namespace App\Livewire\Admin\Master;

use App\Exceptions\StructureDecreeException;
use App\Models\StructureDecree;
use App\Services\Structure\StructureDecreeService;
use App\Services\Structure\StructureDiff;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

/**
 * Detail SK struktur organisasi: ringkasan, jejak keputusan, selisih, dampak, dan keputusan (setujui, tolak,
 * batalkan, terapkan ulang). Aturan ada pada StructureDecreeService; layar hanya mencerminkannya dan mengikuti pola
 * layar Riwayat & Persetujuan Impor (DESIGN.md §8.5a dan §8.6).
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
            'summary' => $this->summary($decree),
            'steps' => $this->timelineSteps($decree),
            'isBaseline' => str_starts_with((string) $decree->source_path, 'baseline:'),
            'impact' => $isOpen && $decree->status !== StructureDecree::STATUS_FAILED ? $service->impact($decree) : null,
            'worklist' => $decree->status === StructureDecree::STATUS_APPLIED ? $service->worklist($decree) : collect(),
            'canApprove' => Gate::allows('struktur.approve') && $decree->status === StructureDecree::STATUS_SUBMITTED && ! $fourEyesBlocksUser,
            'blockedByFourEyes' => Gate::allows('struktur.approve') && $decree->status === StructureDecree::STATUS_SUBMITTED && $fourEyesBlocksUser,
            'canPrepare' => Gate::allows('struktur.prepare'),
            'isOpen' => $isOpen,
            'warnings' => ($decree->unit_mapping ?? [])['apply_warnings'] ?? [],
        ]);
    }

    /**
     * Angka ringkasan: dari selisih tersimpan (sebelum dan sesudah SK) atau, untuk SK dasar instalasi yang tidak
     * memiliki selisih, dari snapshot struktur hasil penerapan.
     *
     * @return array{units: int, pairs: int, kuota: int, before: ?array<string, int>, changes: int, baseline: bool}
     */
    private function summary(StructureDecree $decree): array
    {
        $baseline = str_starts_with((string) $decree->source_path, 'baseline:');
        $totals = ($decree->diff ?? [])['totals'] ?? null;

        if (! $baseline && is_array($totals) && isset($totals['after']['units'])) {
            return [
                'units' => (int) $totals['after']['units'],
                'pairs' => (int) $totals['after']['pairs'],
                'kuota' => (int) $totals['after']['kuota'],
                'before' => ['units' => (int) $totals['before']['units'], 'pairs' => (int) $totals['before']['pairs'], 'kuota' => (int) $totals['before']['kuota']],
                'changes' => count(($decree->diff ?? [])['changes'] ?? []),
                'baseline' => false,
            ];
        }

        $units = ($decree->snapshot ?? [])['units'] ?? [];
        $active = array_filter($units, fn (array $unit): bool => ($unit['is_active'] ?? true) !== false);
        $pairs = 0;
        $kuota = 0;
        foreach ($active as $unit) {
            foreach ($unit['seats'] ?? [] as $seat) {
                $pairs++;
                $kuota += (int) $seat['kuota'];
            }
        }

        return [
            'units' => count($active),
            'pairs' => $pairs,
            'kuota' => $kuota,
            'before' => null,
            'changes' => 0,
            'baseline' => $baseline,
        ];
    }

    /**
     * Langkah linimasa keputusan untuk x-admin.decision-timeline, diturunkan dari kolom SK (bukan dari log audit).
     *
     * @return list<array{icon: string, tone: string, title: string, who: ?string, at: mixed, note: ?string}>
     */
    private function timelineSteps(StructureDecree $decree): array
    {
        $steps = [['icon' => 'fa-file-circle-plus', 'tone' => 'done', 'title' => 'Dicatat', 'who' => null, 'at' => $decree->created_at, 'note' => null]];

        if ($decree->submitted_at) {
            $steps[] = ['icon' => 'fa-paper-plane', 'tone' => 'done', 'title' => 'Diajukan', 'who' => $decree->submitter?->name, 'at' => $decree->submitted_at, 'note' => null];
        }

        if ($decree->status === StructureDecree::STATUS_SUBMITTED) {
            $steps[] = ['icon' => 'fa-clock', 'tone' => 'current', 'title' => 'Menunggu persetujuan admin lain', 'who' => null, 'at' => null, 'note' => null];
        }

        if ($decree->status === StructureDecree::STATUS_STALE) {
            $steps[] = ['icon' => 'fa-triangle-exclamation', 'tone' => 'current', 'title' => 'Perlu ditinjau ulang', 'who' => null, 'at' => null, 'note' => 'Struktur organisasi berubah sejak SK diajukan.'];
        }

        $decider = $decree->decider?->name;
        $decision = match ($decree->status) {
            StructureDecree::STATUS_REJECTED => ['icon' => 'fa-ban', 'tone' => 'closed', 'title' => 'Ditolak', 'note' => $decree->decision_reason],
            StructureDecree::STATUS_CANCELLED => ['icon' => 'fa-rotate-left', 'tone' => 'closed', 'title' => 'Dibatalkan', 'note' => $decree->decision_reason],
            StructureDecree::STATUS_APPROVED, StructureDecree::STATUS_APPLIED, StructureDecree::STATUS_FAILED => ['icon' => 'fa-user-check', 'tone' => 'done', 'title' => 'Disetujui', 'note' => null],
            default => null,
        };
        if ($decision !== null && $decree->decided_at) {
            $steps[] = $decision + ['who' => $decider, 'at' => $decree->decided_at];
        }

        if ($decree->status === StructureDecree::STATUS_APPROVED && $decree->effective_date->toDateString() > now()->toDateString()) {
            $steps[] = ['icon' => 'fa-calendar-check', 'tone' => 'current', 'title' => 'Menunggu tanggal berlaku', 'who' => null, 'at' => null, 'note' => 'Diterapkan otomatis pada '.$decree->effective_date->format('d/m/Y').'.'];
        }

        if ($decree->status === StructureDecree::STATUS_FAILED) {
            $steps[] = ['icon' => 'fa-circle-xmark', 'tone' => 'closed', 'title' => 'Gagal diterapkan', 'who' => null, 'at' => $decree->updated_at, 'note' => $decree->failure_message];
        }

        if ($decree->applied_at) {
            $steps[] = ['icon' => 'fa-check', 'tone' => 'done', 'title' => 'Diterapkan', 'who' => null, 'at' => $decree->applied_at, 'note' => null];
        }

        return $steps;
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
