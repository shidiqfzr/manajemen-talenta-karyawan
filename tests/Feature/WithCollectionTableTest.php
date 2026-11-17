<?php

namespace Tests\Feature;

use App\Livewire\Concerns\WithCollectionTable;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Livewire\Component;
use Livewire\WithPagination;
use Tests\TestCase;

/**
 * Konsern tabel untuk data dalam memori (ENGINEERING.md §2.3-2.4): opsi baris per halaman dari config, nilai tidak sah kembali
 * ke bawaan, dan halaman diklem ke halaman terakhir yang ada.
 */
class WithCollectionTableTest extends TestCase
{
    private function table(): object
    {
        return new class extends Component
        {
            use WithCollectionTable;
            use WithPagination;

            public int $perPage = 25;

            public function render(): string
            {
                return '';
            }

            public function options(): array
            {
                return $this->perPageOptions();
            }

            public function defaults(): int
            {
                return $this->defaultPerPage();
            }

            public function page(Collection $rows): LengthAwarePaginator
            {
                return $this->paginateCollection($rows);
            }
        };
    }

    public function test_options_and_default_come_from_the_shared_pagination_config(): void
    {
        $table = $this->table();

        $this->assertSame(array_map('intval', config('hris.pagination.options')), $table->options());
        $this->assertSame((int) config('hris.pagination.default'), $table->defaults());
    }

    public function test_an_invalid_per_page_falls_back_to_the_default_and_returns_to_page_one(): void
    {
        $table = $this->table();
        $table->perPage = 999;
        $table->setPage(4);

        $table->updatedPerPage();

        $this->assertSame((int) config('hris.pagination.default'), $table->perPage);
        $this->assertSame(1, $table->getPage());
    }

    public function test_a_valid_per_page_is_kept(): void
    {
        $table = $this->table();
        $table->perPage = 50;

        $table->updatedPerPage();

        $this->assertSame(50, $table->perPage);
    }

    public function test_the_collection_is_sliced_to_the_current_page(): void
    {
        $table = $this->table();
        $table->perPage = 10;
        $table->setPage(2);

        $page = $table->page(collect(range(1, 25)));

        $this->assertSame(range(11, 20), $page->items());
        $this->assertSame([25, 10, 2], [$page->total(), $page->perPage(), $page->currentPage()]);
    }

    public function test_a_page_past_the_end_is_clamped_to_the_last_page_and_stored(): void
    {
        $table = $this->table();
        $table->perPage = 10;
        $table->setPage(9);

        $page = $table->page(collect(range(1, 25)));

        $this->assertSame(3, $page->currentPage());
        $this->assertSame(3, $table->getPage(), 'halaman yang sebenarnya tampil disimpan agar Sebelumnya dan Berikutnya bergerak dari sana');
    }

    public function test_an_empty_collection_stays_on_page_one(): void
    {
        $table = $this->table();
        $table->setPage(5);

        $page = $table->page(collect());

        $this->assertSame([0, 1], [$page->total(), $page->currentPage()]);
    }
}
