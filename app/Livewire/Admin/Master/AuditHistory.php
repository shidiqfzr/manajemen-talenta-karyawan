<?php

namespace App\Livewire\Admin\Master;

use App\Livewire\Concerns\WithDataTable;
use App\Models\MasterAuditLog;
use App\Models\User;
use App\Support\DataTable\Column;
use App\Support\DataTable\Filter;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Layar baca-saja riwayat perubahan master unit, jabatan, dan formasi jabatan (SECURITY.md §5).
 * Filter tersimpan pada URL (dapat dibagikan); catatan tidak pernah diubah dari layar ini.
 *
 * Berjalan di atas lapisan tabel data bersama (WithDataTable): pencarian, urutan, filter, dan
 * jumlah baris per halaman divalidasi di sana; layar ini hanya mendeklarasikan kolom dan filternya.
 */
class AuditHistory extends Component
{
    use WithDataTable;

    #[Url(as: 'entity_type', except: '')]
    public string $entityType = '';

    #[Url(as: 'user_id', except: '')]
    public string $userId = '';

    #[Url(except: '')]
    public string $from = '';

    #[Url(except: '')]
    public string $to = '';

    public function boot(): void
    {
        abort_unless(auth()->user()?->role === 'admin', 403, 'Unauthorized.');
    }

    /**
     * @return Collection<int, User>
     */
    #[Computed]
    public function admins(): Collection
    {
        return User::whereIn('id', MasterAuditLog::query()->whereNotNull('user_id')->distinct()->pluck('user_id'))
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    protected function baseQuery(): Builder
    {
        return MasterAuditLog::query()->excludingViewAccess();
    }

    protected function columns(): array
    {
        return [
            Column::make('created_at', 'Waktu')->sortable(),
            Column::make('user_name', 'Admin')->sortable()->searchable(),
            Column::make('action', 'Aksi')->sortable(),
            Column::make('entity_label', 'Data')->sortable()->searchable(),
            Column::make('perubahan', 'Perubahan'),
            Column::make('reason', 'Alasan')->searchable(),
        ];
    }

    protected function filters(): array
    {
        return [
            Filter::select('entityType', 'Jenis Data', MasterAuditLog::ENTITY_LABELS)
                ->icon('fas fa-tag')
                ->allLabel('Semua Jenis Data')
                ->apply(fn (Builder $query, string $value) => $query->where('entity_type', $value)),
            Filter::select('userId', 'Admin', $this->admins->pluck('name', 'id')->all())
                ->icon('fas fa-user')
                ->allLabel('Semua Admin')
                ->apply(fn (Builder $query, string $value) => $query->where('user_id', (int) $value)),
            Filter::dateFrom('from', 'Dari Tanggal', 'created_at'),
            Filter::dateTo('to', 'Sampai Tanggal', 'created_at'),
        ];
    }

    protected function defaultSort(): array
    {
        return ['created_at', 'desc'];
    }

    protected function tableLabel(): string
    {
        return 'Riwayat Perubahan Master';
    }

    public function render(): View
    {
        return view('livewire.admin.master.audit-history', [
            'entityLabels' => MasterAuditLog::ENTITY_LABELS,
        ]);
    }
}
