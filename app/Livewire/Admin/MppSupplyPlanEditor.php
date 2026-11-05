<?php

namespace App\Livewire\Admin;

use App\Exceptions\MppPlanRuleViolationException;
use App\Models\MppSupplyPlan;
use App\Services\ManPowerPlanningService;
use App\Services\Mpp\MppSupplyPlanService;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Laci editor rencana pasokan talenta MPP per tahun. Seluruh aturan dan pencatatan riwayat ada di
 * MppSupplyPlanService; komponen ini hanya menampung input, meringkas perubahan, dan meneruskannya.
 */
class MppSupplyPlanEditor extends Component
{
    public bool $open = false;

    public int $year = 0;

    /** Nilai yang sedang diedit (sumber => RM => bidang => nilai ketikan). */
    public array $cells = [];

    /** Nilai saat editor dibuka; dipakai service untuk menolak simpanan yang menimpa admin lain. */
    public array $expected = [];

    public bool $confirming = false;

    /** Bulan posisi untuk angka "terealisasi" (mengikuti matriks MPP). */
    public int $asOfMonth = 0;

    /** Pasokan yang sudah terealisasi tahun ini s.d. posisi (sumber => RM => bidang => jumlah). */
    public array $realised = [];

    public string $reason = '';

    /** Ringkasan untuk langkah konfirmasi: jumlah sel berubah dan total per sumber lama → baru. */
    public array $summary = [];

    #[On('open-mpp-supply-plan-editor')]
    public function openEditor(int $year, ?int $month = null): void
    {
        $this->authorizeAdmin();

        if ($year < ManPowerPlanningService::PLANNING_YEAR_MIN || $year > ManPowerPlanningService::PLANNING_YEAR_MAX) {
            return;
        }

        $this->resetErrorBag();
        $this->year = $year;
        $this->expected = app(MppSupplyPlanService::class)->matrixFor($year);
        $mpp = app(ManPowerPlanningService::class);
        $this->asOfMonth = ($month !== null && $month >= 1 && $month <= 12) ? $month : $mpp->defaultAsOfMonth($year);
        $this->realised = $mpp->getPositionCounts($year, $this->asOfMonth)['hired_supply'];
        $this->cells = $this->expected;
        $this->confirming = false;
        $this->reason = '';
        $this->summary = [];
        $this->open = true;
    }

    public function review(): void
    {
        $this->authorizeAdmin();
        $this->resetErrorBag();
        $changed = 0;
        $totals = [];

        foreach (MppSupplyPlan::SOURCES as $source => $label) {
            $old = 0;
            $new = 0;
            foreach ($this->expected[$source] as $rm => $fields) {
                foreach ($fields as $bidang => $value) {
                    $typed = $this->cells[$source][$rm][$bidang] ?? null;
                    $old += $value;
                    $new += is_numeric($typed) ? (int) $typed : 0;
                    if ((string) $typed !== (string) $value) {
                        $changed++;
                    }
                }
            }
            $totals[$label] = ['old' => $old, 'new' => $new];
        }

        if ($changed === 0) {
            $this->addError('plan', 'Tidak ada perubahan pada rencana pasokan.');

            return;
        }

        $this->summary = ['changed' => $changed, 'totals' => $totals];
        $this->confirming = true;
    }

    public function cancelReview(): void
    {
        $this->confirming = false;
    }

    public function save(MppSupplyPlanService $service): void
    {
        $this->authorizeAdmin();
        $this->resetErrorBag();

        try {
            $recorded = $service->save($this->year, $this->cells, $this->expected, $this->reason, auth()->user());
        } catch (MppPlanRuleViolationException $e) {
            $this->addError('plan', $e->getMessage());

            return;
        }

        // Nilai seperti "07" dinormalisasi menjadi 7 oleh service: tidak ada yang berubah.
        if ($recorded === 0) {
            $this->confirming = false;
            $this->addError('plan', 'Tidak ada perubahan pada rencana pasokan.');

            return;
        }

        $this->dispatch('mpp-supply-plan-saved', year: $this->year);
        $this->close();
    }

    public function close(): void
    {
        $this->open = false;
        $this->confirming = false;
        $this->reason = '';
        $this->summary = [];
    }

    /** Livewire tidak menerapkan ulang middleware role:admin pada permintaan pembaruan komponen. */
    private function authorizeAdmin(): void
    {
        abort_unless(auth()->user()?->role === 'admin', 403, 'Unauthorized.');
    }

    public function render()
    {
        return view('livewire.admin.mpp-supply-plan-editor', [
            'sources' => MppSupplyPlan::SOURCES,
            'levels' => MppSupplyPlan::LEVELS,
            'bidangList' => MppSupplyPlan::BIDANG,
            'maxJumlah' => MppSupplyPlan::MAX_JUMLAH,
            'asOfLabel' => $this->asOfMonth > 0 ? app(ManPowerPlanningService::class)->monthLabel($this->asOfMonth, $this->year) : '',
            'overPlan' => $this->overPlanSources(),
        ]);
    }

    /**
     * Sumber yang realisasinya sudah melebihi angka rencana yang sedang diketik, beserta selnya.
     *
     * @return array<string, list<string>> label sumber => daftar "RM bidang"
     */
    private function overPlanSources(): array
    {
        $over = [];
        foreach ($this->realised as $source => $levels) {
            foreach ($levels as $rm => $fields) {
                foreach ($fields as $bidang => $count) {
                    $typed = $this->cells[$source][$rm][$bidang] ?? 0;
                    if ($count > 0 && $count > (is_numeric($typed) ? (int) $typed : 0)) {
                        $over[MppSupplyPlan::SOURCES[$source]][] = "{$rm} {$bidang}";
                    }
                }
            }
        }

        return $over;
    }
}
