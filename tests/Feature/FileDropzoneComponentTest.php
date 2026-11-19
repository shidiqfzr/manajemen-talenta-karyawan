<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

/**
 * SUT: komponen Blade x-admin.file-dropzone (area unggah berbasis Alpine) dan pemakaiannya pada tahap 1 Impor Data Karyawan.
 */
class FileDropzoneComponentTest extends TestCase
{
    use RefreshDatabase;

    private function render(string $attributes = ''): string
    {
        return Blade::render('<x-admin.file-dropzone id="berkas_uji" accept=".xlsx, .xls" file="picked" error="problem" select="onPick($event)" clear="reset()" size="sizeText(picked.size)" '.$attributes.' />');
    }

    public function test_it_renders_the_input_with_the_given_id_accept_and_handlers(): void
    {
        $html = $this->render();

        $this->assertStringContainsString('type="file"', $html);
        $this->assertStringContainsString('id="berkas_uji"', $html);
        $this->assertStringContainsString('accept=".xlsx, .xls"', $html);
        $this->assertStringContainsString('@change="onPick($event)"', $html);
        $this->assertStringContainsString('@click="reset()"', $html);
        $this->assertStringContainsString('x-text="sizeText(picked.size)"', $html);
        $this->assertStringContainsString('x-text="picked.name"', $html);
        $this->assertStringContainsString('x-text="problem"', $html);
    }

    public function test_the_area_is_keyboard_operable_and_announces_its_states(): void
    {
        $html = $this->render();

        // Area = tombol yang dapat difokus; Enter dan Spasi membuka pemilih berkas.
        $this->assertStringContainsString('role="button"', $html);
        $this->assertStringContainsString('tabindex="0"', $html);
        $this->assertStringContainsString('@keydown.enter.prevent="$refs.input.click()"', $html);
        $this->assertStringContainsString('@keydown.space.prevent="$refs.input.click()"', $html);
        $this->assertStringContainsString('aria-controls="berkas_uji"', $html);
        // Input tetap di pohon aksesibilitas (sr-only), bukan disembunyikan penuh.
        $this->assertMatchesRegularExpression('/<input[^>]*id="berkas_uji"[^>]*class="sr-only"/', $html);
        $this->assertStringNotContainsString('class="hidden"', $html);
        // Berkas terpilih diumumkan sopan, galat diumumkan tegas.
        $this->assertStringContainsString('role="status"', $html);
        $this->assertStringContainsString('role="alert"', $html);
    }

    public function test_dropping_a_file_hands_it_to_the_input_through_the_same_change_event(): void
    {
        $html = $this->render();

        $this->assertStringContainsString('@drop.prevent="drop($event)"', $html);
        $this->assertStringContainsString('@dragover.prevent="dragging = true"', $html);
        $this->assertStringContainsString('dispatchEvent(new Event(\'change\'', $html);
    }

    public function test_texts_can_be_customised_and_are_escaped(): void
    {
        $html = $this->render('hint="Hanya <b>PDF</b>" action="Pilih"');

        $this->assertStringContainsString('Hanya &lt;b&gt;PDF&lt;/b&gt;', $html);
        $this->assertStringContainsString('Pilih', $html);
    }

    public function test_the_import_wizard_uses_the_shared_dropzone_and_buttons_on_step_one(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $html = $this->actingAs($admin)->get(route('admin.employees.import.page'))->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, 'id="page_import_file"'), 'Satu input berkas, dari komponen bersama.');
        $this->assertStringContainsString('role="button"', $html);
        $this->assertStringNotContainsString('pageDropZone', $html, 'Pendengar tarik-dan-lepas lama sudah dipindahkan ke komponen.');
        $this->assertStringContainsString('Mulai Analisis Validasi Spreadsheet', $html);
        $this->assertStringContainsString('Unduh Template Excel Resmi', $html);
    }
}
