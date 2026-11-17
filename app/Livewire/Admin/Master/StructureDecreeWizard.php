<?php

namespace App\Livewire\Admin\Master;

use App\Exceptions\StructureDecreeException;
use App\Models\Position;
use App\Models\StructureDecree;
use App\Models\Unit;
use App\Services\Structure\StructureDecreeService;
use App\Services\Structure\StructureReader;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Formulir bertahap SK struktur organisasi: "Data SK" lalu "Tinjau Perubahan" (DESIGN.md §8.5a).
 * Pilihan admin (pasangan unit dan jabatan, unit ditutup, atribut unit baru) disimpan pada SK lewat
 * StructureDecreeService::updateReview; aturan dan blokir tetap dari service.
 */
class StructureDecreeWizard extends Component
{
    use WithFileUploads;

    public int $step = 1;

    public ?int $decreeId = null;

    public string $skNumber = '';

    public string $title = '';

    public string $skDate = '';

    public string $effectiveDate = '';

    public string $revokes = '';

    public string $note = '';

    public $workbook = null;

    public $pdf = null;

    /** @var list<array{new: string, old: string}> unit pada lampiran yang belum ada pada struktur saat ini */
    public array $unitPairs = [];

    /** @var list<string> nama unit saat ini yang ditutup */
    public array $closed = [];

    /** @var list<array{unit: string, kode: string, wilayah: string, urutan: int|string|null}> */
    public array $newUnits = [];

    /** @var list<array{unit: string, new: string, old: string}> jabatan yang namanya berubah */
    public array $positionPairs = [];

    public function mount(?StructureDecree $decree = null): void
    {
        $this->authorizeAdmin();

        if ($decree !== null) {
            abort_unless(in_array($decree->status, [StructureDecree::STATUS_DRAFT, StructureDecree::STATUS_STALE], true), 403, 'SK tidak dapat ditinjau lagi.');
            $this->decreeId = $decree->id;
            $this->loadReview();
            $this->step = 2;
        }
    }

    public function createDraft(StructureDecreeService $service): void
    {
        $this->authorizeAdmin();
        $this->resetErrorBag();

        if ($this->workbook === null) {
            $this->addError('workbook', 'Lampiran SK (.xlsx) wajib diunggah.');

            return;
        }

        try {
            $decree = $service->createDraft([
                'sk_number' => $this->skNumber,
                'title' => $this->title,
                'sk_date' => $this->skDate,
                'effective_date' => $this->effectiveDate !== '' ? $this->effectiveDate : null,
                'revokes' => $this->revokes !== '' ? $this->revokes : null,
                'note' => $this->note !== '' ? $this->note : null,
            ], $this->workbook, $this->pdf, auth()->user());
        } catch (StructureDecreeException $e) {
            $this->addError('form', $e->getMessage());

            return;
        }

        $this->decreeId = $decree->id;
        $this->loadReview();
        $this->step = 2;
    }

    /** Simpan pilihan tinjauan dan hitung ulang selisihnya. */
    public function recalculate(StructureDecreeService $service): void
    {
        $this->authorizeAdmin();
        $this->resetErrorBag();

        if ($this->saveChoices($service)) {
            $this->loadReview();
        }
    }

    public function submit(StructureDecreeService $service): mixed
    {
        $this->authorizeAdmin();
        $this->resetErrorBag();

        if (! $this->saveChoices($service)) {
            return null;
        }

        $decree = $this->decree();

        try {
            $service->submit($decree, auth()->user());
        } catch (StructureDecreeException $e) {
            $this->addError('form', $e->getMessage());
            $this->loadReview();

            return null;
        }

        session()->flash('success', 'SK diajukan untuk persetujuan.');

        return $this->redirectRoute('admin.master.sk.show', $decree, navigate: true);
    }

    public function render(StructureDecreeService $service, StructureReader $reader): View
    {
        $this->authorizeAdmin();

        $data = ['wilayahList' => Unit::WILAYAH_LIST, 'decree' => null];

        if ($this->step === 2 && $this->decreeId !== null) {
            $decree = $this->decree();
            $current = $reader->current()['units'];
            $target = ($decree->target ?? [])['units'] ?? [];
            $chosenOld = array_filter(array_column($this->unitPairs, 'old'));
            $inactive = array_keys(array_filter($current, fn (array $unit) => ($unit['is_active'] ?? true) === false));

            $data = array_merge($data, [
                'decree' => $decree,
                'diff' => $service->preview($decree),
                'blockers' => $service->blockers($decree),
                'impact' => $service->impact($decree),
                // Unit saat ini yang tidak tercantum pada lampiran: kandidat nama lama atau unit yang ditutup.
                'currentOnly' => array_values(array_diff(array_keys($current), array_keys($target), $inactive)),
                'chosenOld' => $chosenOld,
                'positionOptions' => $this->positionOptions($current, $target),
            ]);
        }

        return view('livewire.admin.master.structure-decree-wizard', $data);
    }

    private function decree(): StructureDecree
    {
        return StructureDecree::findOrFail($this->decreeId);
    }

    /** Muat pilihan tersimpan dan daftar unit serta jabatan yang perlu dipasangkan. */
    private function loadReview(): void
    {
        $decree = $this->decree();
        $saved = $decree->unit_mapping ?? [];
        $current = app(StructureReader::class)->current()['units'];
        $target = ($decree->target ?? [])['units'] ?? [];
        $oldByNew = array_flip($saved['mapping'] ?? []);

        $this->unitPairs = array_map(fn (string $name): array => ['new' => $name, 'old' => $oldByNew[$name] ?? ''], array_values(array_diff(array_keys($target), array_keys($current))));
        $this->closed = array_values($saved['closed'] ?? []);

        $nextUrutan = (int) Unit::max('urutan') + 1;
        $this->newUnits = [];
        foreach ($this->unitPairs as $pair) {
            if ($pair['old'] !== '') {
                continue;
            }
            $attributes = ($saved['new_units'] ?? [])[$pair['new']] ?? [];
            $this->newUnits[] = [
                'unit' => $pair['new'],
                'kode' => (string) ($attributes['kode'] ?? ''),
                'wilayah' => (string) ($attributes['wilayah'] ?? ''),
                'urutan' => $attributes['urutan'] ?? $nextUrutan++,
            ];
        }

        $newByOld = $saved['positions'] ?? [];
        $oldByNewPosition = array_flip($newByOld);
        $this->positionPairs = [];
        foreach ($this->positionCandidates($current, $target) as $row) {
            $this->positionPairs[] = ['unit' => $row['unit'], 'new' => $row['new'], 'old' => $oldByNewPosition[$row['new']] ?? ''];
        }
    }

    /** @return bool true bila pilihan tersimpan */
    private function saveChoices(StructureDecreeService $service): bool
    {
        $unitMapping = [];
        foreach ($this->unitPairs as $pair) {
            if ($pair['old'] !== '') {
                $unitMapping[$pair['old']] = $pair['new'];
            }
        }

        // Unit yang dipasangkan sebagai pengganti nama tidak memerlukan atribut unit baru.
        $newUnits = [];
        foreach ($this->newUnits as $row) {
            if (! in_array($row['unit'], array_values($unitMapping), true)) {
                $newUnits[$row['unit']] = ['kode' => trim((string) $row['kode']), 'wilayah' => (string) $row['wilayah'], 'urutan' => $row['urutan'] === '' || $row['urutan'] === null ? null : (int) $row['urutan']];
            }
        }

        $positionMapping = [];
        foreach ($this->positionPairs as $pair) {
            if ($pair['old'] !== '') {
                $positionMapping[$pair['old']] = $pair['new'];
            }
        }

        try {
            $service->updateReview($this->decree(), $unitMapping, array_values(array_diff($this->closed, array_keys($unitMapping))), $newUnits, $positionMapping, auth()->user());
        } catch (StructureDecreeException $e) {
            $this->addError('form', $e->getMessage());

            return false;
        }

        return true;
    }

    /**
     * Jabatan pada lampiran yang belum dikenal master dan dapat menggantikan jabatan lama pada unit yang sama.
     *
     * @return list<array{unit: string, new: string}>
     */
    private function positionCandidates(array $current, array $target): array
    {
        $oldNameByNewUnit = array_flip(($this->decreeId ? ($this->decree()->unit_mapping['mapping'] ?? []) : []));
        $rows = [];

        foreach ($target as $unitName => $unit) {
            $currentName = $oldNameByNewUnit[$unitName] ?? $unitName;
            if (! isset($current[$currentName])) {
                continue;
            }

            $currentSeats = array_keys($current[$currentName]['seats']);
            $targetSeats = array_keys($unit['seats']);
            $hasOld = array_diff($currentSeats, $targetSeats) !== [];

            foreach (array_diff($targetSeats, $currentSeats) as $jabatan) {
                if ($hasOld && ! Position::where('nama', $jabatan)->exists()) {
                    $rows[] = ['unit' => $unitName, 'new' => $jabatan];
                }
            }
        }

        return $rows;
    }

    /**
     * Nama jabatan lama yang dapat dipilih per unit: jabatan pada unit saat ini yang tidak ada pada lampiran.
     *
     * @return array<string, list<string>>
     */
    private function positionOptions(array $current, array $target): array
    {
        $oldNameByNewUnit = array_flip(($this->decree()->unit_mapping['mapping'] ?? []));
        $options = [];

        foreach ($target as $unitName => $unit) {
            $currentName = $oldNameByNewUnit[$unitName] ?? $unitName;
            if (isset($current[$currentName])) {
                $options[$unitName] = array_values(array_diff(array_keys($current[$currentName]['seats']), array_keys($unit['seats'])));
            }
        }

        return $options;
    }

    private function authorizeAdmin(): void
    {
        abort_unless(Gate::allows('struktur.prepare'), 403, 'Unauthorized.');
    }
}
