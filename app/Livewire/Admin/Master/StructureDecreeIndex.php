<?php

namespace App\Livewire\Admin\Master;

use App\Livewire\Concerns\WithDataTable;
use App\Models\StructureDecree;
use App\Support\DataTable\Column;
use App\Support\DataTable\Filter;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Daftar SK struktur organisasi di atas lapisan tabel data bersama (WithDataTable, DESIGN.md §7.5 dan §8.5a).
 * Tab status di atas tabel mengendalikan filter `status`, sehingga toolbar hanya menampilkannya sebagai chip.
 * Baca-saja: pembuatan dan keputusan ada pada layar lain.
 */
class StructureDecreeIndex extends Component
{
    use WithDataTable;

    /** Kunci tab => label. `all` berarti tanpa filter status. */
    public const TABS = [
        'all' => 'Semua',
        'open' => 'Berjalan',
        'applied' => 'Diterapkan',
        'closed' => 'Ditutup',
    ];

    /** Kunci tab => status SK yang dicakupnya. */
    public const TAB_STATUSES = [
        'open' => StructureDecree::OPEN_STATUSES,
        'applied' => [StructureDecree::STATUS_APPLIED],
        'closed' => [StructureDecree::STATUS_REJECTED, StructureDecree::STATUS_CANCELLED],
    ];

    /** '' = semua; selain itu salah satu kunci TAB_STATUSES. */
    #[Url(except: '')]
    public string $status = '';

    /** Rute middleware tidak diterapkan ulang pada permintaan update Livewire, jadi tolak non-admin di setiap permintaan. */
    public function boot(): void
    {
        abort_unless(Gate::allows('struktur.prepare'), 403, 'Unauthorized.');
    }

    /**
     * Jumlah SK per tab, atas seluruh SK tanpa memperhatikan pencarian.
     *
     * @return array<string, int>
     */
    #[Computed]
    public function tabCounts(): array
    {
        $byStatus = StructureDecree::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');
        $counts = ['all' => (int) $byStatus->sum()];

        foreach (self::TAB_STATUSES as $key => $statuses) {
            $counts[$key] = (int) $byStatus->only($statuses)->sum();
        }

        return $counts;
    }

    protected function baseQuery(): Builder
    {
        return StructureDecree::query()->with('decider');
    }

    protected function columns(): array
    {
        return [
            Column::make('sk_number', 'Nomor SK')->sortable()->searchable(),
            Column::make('title', 'Tentang')->searchable(),
            Column::make('sk_date', 'Tanggal SK')->sortable(),
            Column::make('effective_date', 'Berlaku')->sortable(),
            Column::make('status', 'Status')->sortable(),
            Column::make('aksi', 'Aksi')->align('right'),
        ];
    }

    protected function filters(): array
    {
        $options = self::TABS;
        unset($options['all']);

        // Tab status adalah kendali filter ini; toolbar hanya menampilkannya sebagai chip yang dapat dihapus.
        return [
            Filter::select('status', 'Status', $options)
                ->icon('fas fa-flag')
                ->withoutControl()
                ->apply(fn (Builder $query, string $value) => $query->whereIn('status', self::TAB_STATUSES[$value])),
        ];
    }

    protected function defaultSort(): array
    {
        return ['sk_date', 'desc'];
    }

    protected function tableLabel(): string
    {
        return 'Surat Keputusan Struktur';
    }

    public function render(): View
    {
        return view('livewire.admin.master.structure-decree-index');
    }
}
