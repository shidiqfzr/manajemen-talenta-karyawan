<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Halaman galat mandiri (DESIGN.md §15). Sengaja tanpa RefreshDatabase: halaman ini tidak boleh
 * menyentuh database, sehingga tetap tampil ketika database yang bermasalah.
 */
class ErrorPagesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        foreach ([403, 419, 429, 500, 503] as $status) {
            Route::get("/_uji/galat-{$status}", fn () => abort($status, 'pesan-internal-rahasia'));
        }
    }

    public function test_unknown_url_renders_the_branded_not_found_page(): void
    {
        $response = $this->get('/halaman-yang-tidak-ada');

        $response->assertNotFound()
            ->assertSee('Halaman Tidak Ditemukan')
            ->assertSee('Kembali ke Beranda')
            ->assertSee('<html lang="id">', false)
            ->assertSee('<h1', false)
            ->assertSee('Talenta Hub');
    }

    /**
     * @return array<string, array{int, string}>
     */
    public static function statusPages(): array
    {
        return [
            '403' => [403, 'Anda Tidak Berwenang'],
            '419' => [419, 'Sesi Anda Telah Berakhir'],
            '429' => [429, 'Terlalu Banyak Permintaan'],
            '500' => [500, 'Terjadi Kesalahan pada Sistem'],
            '503' => [503, 'Sistem Sedang Dalam Pemeliharaan'],
        ];
    }

    #[DataProvider('statusPages')]
    public function test_each_status_renders_its_own_page_and_never_leaks_the_exception_message(int $status, string $title): void
    {
        $response = $this->get("/_uji/galat-{$status}");

        $response->assertStatus($status)
            ->assertSee($title)
            ->assertSee("kode {$status}")
            ->assertDontSee('pesan-internal-rahasia');
    }

    public function test_server_error_offers_retry_while_other_pages_offer_the_home_link(): void
    {
        $this->get('/_uji/galat-500')->assertSee('Coba Lagi');
        $this->get('/_uji/galat-503')->assertSee('Muat Ulang');
        $this->get('/_uji/galat-403')->assertSee('Kembali ke Beranda');
    }

    public function test_pages_render_without_touching_the_database(): void
    {
        // Koneksi sqlite :memory: tanpa migrasi: query apa pun akan melempar galat.
        $this->get('/_uji/galat-500')->assertStatus(500)->assertSee('Terjadi Kesalahan pada Sistem');
        $this->get('/halaman-yang-tidak-ada')->assertNotFound();
    }
}
