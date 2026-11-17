<?php

namespace App\Livewire\Admin\Master;

use App\Models\StructureDecree;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

/**
 * Daftar SK struktur organisasi (DESIGN.md §8.5a). Baca-saja; pembuatan dan keputusan ada pada layar lain.
 */
class StructureDecreeIndex extends Component
{
    public function mount(): void
    {
        $this->authorizeAdmin();
    }

    public function render(): View
    {
        $this->authorizeAdmin();

        return view('livewire.admin.master.structure-decree-index', [
            'decrees' => StructureDecree::query()->orderByDesc('sk_date')->orderByDesc('id')->get(),
        ]);
    }

    private function authorizeAdmin(): void
    {
        abort_unless(Gate::allows('struktur.prepare'), 403, 'Unauthorized.');
    }
}
