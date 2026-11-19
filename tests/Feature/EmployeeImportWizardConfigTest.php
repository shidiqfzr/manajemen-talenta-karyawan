<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Import\EmployeeImportDictionary;
use App\Services\Import\EmployeeImportWizardConfig;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * SUT: App\Services\Import\EmployeeImportWizardConfig dan cara halaman Impor Data Karyawan menyerahkannya kepada komponen
 * Alpine `employeeImportWizard` (resources/js/admin/employee-import-wizard.js).
 */
class EmployeeImportWizardConfigTest extends TestCase
{
    use RefreshDatabase;

    public function test_config_carries_the_server_values_the_component_needs(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        $config = EmployeeImportWizardConfig::make();

        $this->assertSame(['requiresApproval', 'columns', 'urls', 'csrf'], array_keys($config));
        $this->assertIsBool($config['requiresApproval']);
        $this->assertSame(EmployeeImportDictionary::getColumns(), $config['columns']);
        $this->assertSame(route('admin.employees.import.preview'), $config['urls']['preview']);
        $this->assertSame(route('admin.employees.import.commit'), $config['urls']['commit']);
        $this->assertNotSame('', $config['csrf']);
    }

    public function test_the_status_url_comes_from_the_route_and_keeps_a_token_placeholder(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        $status = EmployeeImportWizardConfig::make()['urls']['status'];

        $this->assertStringEndsWith('/admin/employees/import/status/'.EmployeeImportWizardConfig::TOKEN_PLACEHOLDER, $status);
        $this->assertSame(route('admin.employees.import.status', ['token' => '5b7a6c1e-0000-4000-8000-000000000001']), str_replace(EmployeeImportWizardConfig::TOKEN_PLACEHOLDER, '5b7a6c1e-0000-4000-8000-000000000001', $status));
    }

    public function test_the_page_hands_the_config_to_the_component_and_has_no_inline_wizard_script(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $html = $this->actingAs($admin)->get(route('admin.employees.import.page'))->assertOk()->getContent();

        $this->assertStringContainsString('x-data="employeeImportWizard(', $html);
        $this->assertStringContainsString('/import/status/__TOKEN__', str_replace(chr(92), '', $html), 'URL status dari rute, dengan penanda token.');
        $this->assertStringNotContainsString('function employeeImportWizard', $html, 'Logika wizard berada di modul JavaScript, bukan skrip Blade.');
        $this->assertStringNotContainsString("fetch('/admin/employees/import", $html, 'Tanpa URL yang ditulis keras pada halaman.');
        $this->assertFileDoesNotExist(resource_path('views/admin/employees/partials/import-wizard-script.blade.php'));
        $this->assertFileExists(resource_path('js/admin/employee-import-wizard.js'));
    }
}
