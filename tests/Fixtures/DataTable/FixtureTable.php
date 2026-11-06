<?php

namespace Tests\Fixtures\DataTable;

use App\Livewire\Concerns\WithDataTable;
use App\Support\DataTable\Column;
use App\Support\DataTable\Filter;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Minimal module component exercising the WithDataTable trait in tests.
 */
class FixtureTable extends Component
{
    use WithDataTable;

    #[Url(except: '')]
    public string $bidang = '';

    protected function baseQuery(): Builder
    {
        return FixtureRow::query();
    }

    protected function columns(): array
    {
        return [
            Column::make('nama', 'Nama')->sortable()->searchable()->exportable(),
            Column::make('kode', 'Kode')->exportable(),
            Column::make('nilai', 'Nilai')->sortable()->mono()->exportable(),
        ];
    }

    protected function filters(): array
    {
        return [
            Filter::select('bidang', 'Bidang', ['KEU' => 'KEU', 'TAN' => 'TAN']),
        ];
    }

    protected function defaultSort(): array
    {
        return ['nama', 'asc'];
    }

    protected function tableLabel(): string
    {
        return 'Tabel Uji';
    }

    public function render(): string
    {
        // Replaced by the shared table Blade components once they exist.
        return <<<'BLADE'
            <div>@foreach($this->rows as $r)<span>{{ $r->nama }}</span>@endforeach</div>
            BLADE;
    }
}
