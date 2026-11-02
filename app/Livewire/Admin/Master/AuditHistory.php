<?php

namespace App\Livewire\Admin\Master;

use App\Models\MasterAuditLog;
use App\Models\User;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Layar baca-saja riwayat perubahan master unit, jabatan, dan formasi jabatan (SECURITY.md §5).
 * Filter tersimpan pada URL (dapat dibagikan); catatan tidak pernah diubah dari layar ini.
 */
class AuditHistory extends Component
{
    use WithPagination;

    private const SORTABLE = ['created_at', 'user_name', 'action', 'entity_label'];

    #[Url(except: '')]
    public string $search = '';

    #[Url(as: 'entity_type', except: '')]
    public string $entityType = '';

    #[Url(as: 'user_id', except: '')]
    public string $userId = '';

    #[Url(except: '')]
    public string $from = '';

    #[Url(except: '')]
    public string $to = '';

    #[Url(as: 'sort', except: 'created_at')]
    public string $sortField = 'created_at';

    #[Url(as: 'direction', except: 'desc')]
    public string $sortDirection = 'desc';

    /** Pilihan jumlah baris per halaman. */
    public const PER_PAGE_OPTIONS = [10, 25, 50, 100];

    #[Url(as: 'per_page', except: 25)]
    public int $perPage = 25;

    public function updatedPerPage(): void
    {
        if (! in_array($this->perPage, self::PER_PAGE_OPTIONS, true)) {
            $this->perPage = 25;
        }
        $this->resetPage();
    }

    public function boot(): void
    {
        abort_unless(auth()->user()?->role === 'admin', 403, 'Unauthorized.');
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'entityType', 'userId', 'from', 'to'], true)) {
            $this->resetPage();
        }
    }

    public function sortBy(string $field): void
    {
        if (! in_array($field, self::SORTABLE, true)) {
            return;
        }

        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = 'asc';
        }
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->reset(['search', 'entityType', 'userId', 'from', 'to']);
        $this->resetPage();
    }

    public function hasFilters(): bool
    {
        return $this->search !== '' || $this->entityType !== '' || $this->userId !== '' || $this->from !== '' || $this->to !== '';
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

    public function render()
    {
        $sortField = in_array($this->sortField, self::SORTABLE, true) ? $this->sortField : 'created_at';
        $term = trim($this->search);

        $logs = MasterAuditLog::query()
            ->when(array_key_exists($this->entityType, MasterAuditLog::ENTITY_LABELS), fn ($q) => $q->where('entity_type', $this->entityType))
            ->when(ctype_digit($this->userId), fn ($q) => $q->where('user_id', (int) $this->userId))
            ->when($this->validDate($this->from), fn ($q) => $q->where('created_at', '>=', $this->from.' 00:00:00'))
            ->when($this->validDate($this->to), fn ($q) => $q->where('created_at', '<=', $this->to.' 23:59:59'))
            ->when($term !== '', function ($q) use ($term): void {
                $like = '%'.addcslashes($term, '%_\\').'%';
                $q->where(fn ($q) => $q->where('entity_label', 'like', $like)
                    ->orWhere('user_name', 'like', $like)
                    ->orWhere('reason', 'like', $like));
            })
            ->orderBy($sortField, $this->sortDirection === 'asc' ? 'asc' : 'desc')
            ->orderByDesc('id')
            ->paginate($this->perPage);

        return view('livewire.admin.master.audit-history', [
            'logs' => $logs,
            'entityLabels' => MasterAuditLog::ENTITY_LABELS,
        ]);
    }

    private function validDate(string $value): bool
    {
        return (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) && strtotime($value) !== false;
    }
}
