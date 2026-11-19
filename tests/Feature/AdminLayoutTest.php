<?php

namespace Tests\Feature;

use App\Models\EmployeeImportLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * SUT: resources/views/layouts/admin.blade.php (sidebar + topbar shell).
 */
class AdminLayoutTest extends TestCase
{
    use RefreshDatabase;

    private function page(): TestResponse
    {
        $this->actingAs(User::factory()->create(['name' => 'Siti Aminah', 'role' => 'admin']));

        return $this->get(route('admin.employees.index'));
    }

    public function test_topbar_shows_the_real_user_and_role_label_from_config(): void
    {
        $this->page()
            ->assertOk()
            ->assertSee('Siti Aminah')
            ->assertSee('Administrator SDM')
            ->assertDontSee('HR Administrator');
    }

    public function test_notification_bell_has_no_invented_data_and_says_so_when_empty(): void
    {
        $this->page()
            ->assertSee('Semua beres')
            ->assertDontSee('Validasi Formasi MPP Tersedia')
            ->assertDontSee('menit yang lalu')
            ->assertDontSee('Tandai Dibaca');
    }

    public function test_notification_bell_reflects_pending_work(): void
    {
        EmployeeImportLog::create(['file_name' => 'a.xlsx', 'user_name' => 'X', 'status' => EmployeeImportLog::STATUS_PENDING, 'total_rows' => 1]);

        $this->page()
            ->assertSee('Impor Data Karyawan Menunggu Persetujuan')
            ->assertSee('1 butir perlu tindak lanjut');
    }

    public function test_logout_is_a_single_confirmed_dialog_not_three_native_confirms(): void
    {
        $html = $this->page()->getContent();

        $this->assertStringNotContainsString("confirm('", $html);
        $this->assertSame(1, substr_count($html, 'action="'.route('logout').'"'));
        $this->assertStringContainsString('aria-labelledby="logout-title"', $html);
        $this->assertStringNotContainsString('Logout', $html);
    }

    public function test_search_and_toggles_are_named_for_assistive_technology(): void
    {
        $this->page()
            ->assertSee('role="search"', false)
            ->assertSee('aria-label="Cari karyawan berdasarkan nama, NIK, jabatan, atau unit kerja"', false)
            ->assertSee('aria-label="Ciutkan atau lebarkan menu samping"', false)
            ->assertSee('aria-label="Karyawan"', false);
    }

    public function test_sidebar_is_one_core_hr_group_in_priority_order_with_single_word_labels(): void
    {
        $html = $this->page()->getContent();
        preg_match('/<nav aria-label="Navigasi utama".*?<\/nav>/s', $html, $nav);

        $this->assertSame(1, substr_count($nav[0], 'Core HR'));
        $this->assertDoesNotMatchRegularExpression('/Talent &amp; Mobility|Development &amp; Performance|Master Data/', $nav[0]);

        preg_match_all('/<span class="flex-1 min-w-0 text-sm truncate"[^>]*>(.*?)<\/span>/s', $nav[0], $labels);
        $this->assertSame(['Karyawan', 'Mutasi', 'Formasi', 'Pelatihan', 'Penilaian'], $labels[1]);
    }

    public function test_breadcrumb_leaf_uses_the_same_word_as_the_sidebar_entry(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        foreach ([
            'admin.employees.index' => 'Karyawan',
            'admin.mutasi.index' => 'Mutasi',
            'admin.master.formasi.index' => 'Formasi',
            'admin.trainings.index' => 'Pelatihan',
            'admin.evaluations.index' => 'Penilaian',
        ] as $route => $label) {
            $html = $this->get(route($route))->assertOk()->getContent();
            preg_match('/<nav[^>]*aria-label="Breadcrumb".*?<\/nav>/s', $html, $crumb);

            $this->assertMatchesRegularExpression('/Core HR.*aria-current="page">\s*'.$label.'\s*</s', $crumb[0] ?? '', $route);
        }
    }

    public function test_brand_lives_only_in_the_sidebar_and_the_topbar_has_no_organization_badge(): void
    {
        $html = $this->page()->getContent();

        $this->assertSame(1, substr_count($html, 'PTPN IV Regional V</span>'));
        $this->assertStringNotContainsString('<span class="text-surface-600">Regional V</span>', $html);
        $this->assertStringContainsString('id="sidebar-brand"', $html);
    }

    public function test_every_sidebar_link_keeps_a_constant_left_border_so_icons_never_shift(): void
    {
        $html = $this->page()->getContent();
        preg_match('/<nav aria-label="Navigasi utama".*?<\/nav>/s', $html, $nav);

        $count = preg_match_all('/<a [^>]*class="[^"]*border-l-4[^"]*border-(?:primary-600|transparent)/s', $nav[0]);
        $this->assertSame(5, $count);
    }

    public function test_topbar_and_sidebar_brand_share_one_fixed_height_so_their_borders_meet(): void
    {
        $html = $this->page()->getContent();

        // shrink-0: the topbar sits in a fixed-height flex column and must not be squeezed below h-16 by tall page content
        $this->assertMatchesRegularExpression('/<header class="[^"]* shrink-0 [^"]* h-16 /', $html);
        $this->assertMatchesRegularExpression('/id="sidebar-brand" class="h-16 /', $html);
    }
}
