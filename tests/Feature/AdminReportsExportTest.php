<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminReportsExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_download_a_structured_multi_sheet_management_report(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $response = $this->actingAs($admin)->get(route('admin.sections.reports', [
            'range' => 'month',
            'export' => 'excel',
        ]));

        $response
            ->assertOk()
            ->assertHeader('content-type', 'application/vnd.ms-excel; charset=UTF-8');

        $disposition = (string) $response->headers->get('content-disposition');
        $content = $response->streamedContent();

        $this->assertStringContainsString('hyve-management-report-', $disposition);
        $this->assertStringContainsString('.xls', $disposition);
        $this->assertStringContainsString('ss:Name="Executive Summary"', $content);
        $this->assertStringContainsString('ss:Name="Room Performance"', $content);
        $this->assertStringContainsString('ss:Name="Members and Payments"', $content);
        $this->assertStringContainsString('ss:Name="Daily Activity"', $content);
        $this->assertStringContainsString('ss:Name="Shift Collections"', $content);
        $this->assertStringContainsString('Approved collections', $content);
        $this->assertStringContainsString('What this means', $content);
    }

    public function test_reports_page_promotes_excel_while_legacy_csv_remains_available(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAs($admin)
            ->get(route('admin.sections.reports'))
            ->assertOk()
            ->assertSee('Export Excel')
            ->assertSee('export=excel', false);

        $this->actingAs($admin)
            ->get(route('admin.sections.reports', ['export' => 'csv']))
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }
}
