<?php

namespace Tests\Feature;

use App\Models\MppPeriod;
use App\Services\Mpp\MppPeriodService;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class MppCapturePeriodCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-11-01 01:00:00'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_captures_the_previous_month_by_default(): void
    {
        $this->artisan('hris:mpp-capture-period')
            ->expectsOutputToContain('Draf periode MPP Okt 2026 direkam.')
            ->assertExitCode(0);

        $period = app(MppPeriodService::class)->find(2026, 10);
        $this->assertNotNull($period);
        $this->assertSame(MppPeriod::STATUS_DRAFT, $period->status);
        $this->assertNull($period->captured_by);
    }

    public function test_skips_a_closed_month(): void
    {
        $service = app(MppPeriodService::class);
        $service->capture(2026, 10, null);
        $period = $service->find(2026, 10);
        $period->update(['status' => MppPeriod::STATUS_CLOSED]);
        $revision = $period->revision;

        $this->artisan('hris:mpp-capture-period')
            ->expectsOutputToContain('Periode MPP Okt 2026 sudah ditutup; dilewati.')
            ->assertExitCode(0);

        $this->assertSame($revision, $service->find(2026, 10)->revision);
    }

    public function test_options_select_a_month(): void
    {
        $this->artisan('hris:mpp-capture-period', ['--year' => 2026, '--month' => 9])
            ->expectsOutputToContain('Draf periode MPP Sep 2026 direkam.')
            ->assertExitCode(0);

        $this->assertNotNull(app(MppPeriodService::class)->find(2026, 9));
    }

    public function test_january_captures_december_of_the_previous_year(): void
    {
        Carbon::setTestNow(Carbon::parse('2027-01-01 01:00:00'));

        $this->artisan('hris:mpp-capture-period')->assertExitCode(0);

        $this->assertNotNull(app(MppPeriodService::class)->find(2026, 12));
    }

    public function test_invalid_month_fails(): void
    {
        $this->artisan('hris:mpp-capture-period', ['--month' => 13])->assertExitCode(1);
    }

    public function test_command_is_scheduled_monthly(): void
    {
        $event = collect(app(Schedule::class)->events())
            ->first(fn ($e) => str_contains($e->command, 'hris:mpp-capture-period'));

        $this->assertNotNull($event);
        $this->assertSame('0 1 1 * *', $event->expression);
    }
}
