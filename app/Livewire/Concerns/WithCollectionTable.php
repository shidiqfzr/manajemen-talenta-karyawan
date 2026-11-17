<?php

namespace App\Livewire\Concerns;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * Tabel untuk data yang sudah ada di memori (hasil service, bukan query Eloquent) pada komponen Livewire yang memakai
 * WithPagination dan menyatakan `public int $perPage` sendiri (atribut #[Url] dapat berbeda per komponen). Pasangan WithDataTable
 * untuk tabel yang bersumber dari query (ENGINEERING.md §2.3-2.4): opsi baris per halaman satu standar dari config
 * (`hris.pagination`), nilai tidak sah kembali ke bawaan, dan halaman diklem ke halaman terakhir yang ada.
 */
trait WithCollectionTable
{
    /** @return array<int, int> Opsi baris per halaman; satu standar untuk seluruh tabel. */
    protected function perPageOptions(): array
    {
        return array_map('intval', config('hris.pagination.options'));
    }

    protected function defaultPerPage(): int
    {
        return (int) config('hris.pagination.default');
    }

    /** Jumlah baris per halaman yang sah: nilai terpilih bila termasuk opsi, selain itu bawaan. */
    protected function normalisedPerPage(): int
    {
        return in_array($this->perPage, $this->perPageOptions(), true) ? $this->perPage : $this->defaultPerPage();
    }

    public function updatedPerPage(): void
    {
        $this->perPage = $this->normalisedPerPage();
        $this->resetPage();
    }

    /**
     * Mengiris koleksi ke halaman berjalan. Halaman diklem ke halaman terakhir yang ada (mis. setelah baris dihapus atau
     * filter dipersempit) dan disimpan, agar "Sebelumnya" dan "Berikutnya" bergerak dari halaman yang benar-benar tampil.
     *
     * @param  Collection<int, mixed>  $rows
     */
    protected function paginateCollection(Collection $rows): LengthAwarePaginator
    {
        $perPage = $this->normalisedPerPage();
        $page = max(1, min($this->getPage(), (int) ceil($rows->count() / $perPage)));

        if ($page !== $this->getPage()) {
            $this->setPage($page);
        }

        return new LengthAwarePaginator($rows->forPage($page, $perPage)->values(), $rows->count(), $perPage, $page);
    }
}
