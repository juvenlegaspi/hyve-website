<?php

namespace Tests\Feature;

use App\Models\BookingHeader;
use App\Models\BookingActivity;
use App\Models\BookingDetail;
use App\Models\BookingPayment;
use App\Models\HyveRoom;
use App\Models\Space;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AdminSalesMonitoringTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_sales_monitoring_uses_only_verified_approved_payments_and_supports_filters(): void
    {
        Carbon::setTestNow('2026-07-23 10:00:00');

        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $online = $this->booking('HYVE-SALES-ONLINE', BookingHeader::SOURCE_WEB, 'gcash', 500, 500, 0);
        $walkIn = $this->booking('HYVE-SALES-WALKIN', BookingHeader::SOURCE_ADMIN, 'cash', 400, 300, 100);

        $this->payment($online, $admin, 500, 'gcash', BookingPayment::STATUS_APPROVED, now());
        $this->payment($walkIn, $admin, 300, 'cash', BookingPayment::STATUS_APPROVED, now());
        $this->payment($walkIn, $admin, 100, 'cash', BookingPayment::STATUS_PENDING, null);

        $this->actingAs($admin)
            ->get(route('admin.sales-monitoring.index'))
            ->assertOk()
            ->assertSee('Sales Monitoring')
            ->assertSee('Php 800.00')
            ->assertSee('Php 100.00')
            ->assertSee('Online')
            ->assertSee('Walk-in')
            ->assertSee('GCash')
            ->assertSee('Cash');

        $this->actingAs($admin)
            ->get(route('admin.sales-monitoring.index', [
                'range' => 'custom',
                'date_from' => '2026-07-23',
                'date_to' => '2026-07-23',
                'source' => BookingHeader::SOURCE_ADMIN,
                'method' => 'cash',
            ]))
            ->assertOk()
            ->assertViewHas('summary', fn (array $summary): bool => $summary['gross_sales'] === 300.0
                && $summary['pending_amount'] === 100.0
                && $summary['transactions'] === 1);
    }

    public function test_sales_monitoring_permission_matches_the_admin_roles(): void
    {
        $audit = User::factory()->create(['role' => User::ROLE_AUDIT]);
        $frontDesk = User::factory()->create(['role' => User::ROLE_FRONT_DESK]);

        $this->actingAs($audit)
            ->get(route('admin.sales-monitoring.index'))
            ->assertOk();

        $this->actingAs($frontDesk)
            ->get(route('admin.sales-monitoring.index'))
            ->assertForbidden();
    }

    public function test_excel_export_is_styled_and_respects_the_selected_filters(): void
    {
        Carbon::setTestNow('2026-07-23 10:00:00');

        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $online = $this->booking('HYVE-EXPORT-ONLINE', BookingHeader::SOURCE_WEB, 'gcash', 500, 500, 0);
        $walkIn = $this->booking('HYVE-EXPORT-WALKIN', BookingHeader::SOURCE_ADMIN, 'cash', 300, 300, 0);

        $this->payment($online, $admin, 500, 'gcash', BookingPayment::STATUS_APPROVED, now());
        $this->payment($walkIn, $admin, 300, 'cash', BookingPayment::STATUS_APPROVED, now());

        $response = $this->actingAs($admin)->get(route('admin.sales-monitoring.export', [
            'range' => 'custom',
            'date_from' => '2026-07-23',
            'date_to' => '2026-07-23',
            'source' => BookingHeader::SOURCE_ADMIN,
            'method' => 'cash',
        ]));

        $response
            ->assertOk()
            ->assertDownload('hyve-sales-monitoring-2026-07-23-to-2026-07-23.xls')
            ->assertHeader('content-type', 'application/vnd.ms-excel; charset=UTF-8');

        $content = $response->streamedContent();

        $this->assertStringContainsString('HYVE SALES MONITORING', $content);
        $this->assertStringContainsString('Executive Summary', $content);
        $this->assertStringContainsString('Revenue Breakdown', $content);
        $this->assertStringContainsString('Space Performance', $content);
        $this->assertStringContainsString('Demand &amp; Bookings', $content);
        $this->assertStringContainsString('DEMAND HEATMAP - BOOKINGS / GUESTS', $content);
        $this->assertStringContainsString('TODAY&apos;S LIVE SALES', $content);
        $this->assertStringContainsString('BALANCE COLLECTION MONITORING', $content);
        $this->assertStringContainsString('SPACE UTILIZATION &amp; REVENUE EFFICIENCY', $content);
        $this->assertStringContainsString('Transaction Details', $content);
        $this->assertStringContainsString('Walk-in Customer', $content);
        $this->assertStringNotContainsString('Online Customer', $content);
        $this->assertStringContainsString('ss:Color="#173B29"', $content);
        $this->assertNotFalse(simplexml_load_string($content));
    }

    public function test_sales_monitoring_builds_space_demand_source_and_lifecycle_analytics(): void
    {
        Carbon::setTestNow('2026-07-23 10:00:00');

        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $booking = $this->booking('HYVE-ANALYTICS-WALKIN', BookingHeader::SOURCE_ADMIN, 'cash', 500, 500, 0);
        $detail = BookingDetail::query()->create([
            'booking_header_id' => $booking->getKey(),
            'space_id' => Space::query()->where('slug', 'common-area')->value('id'),
            'hyve_room_id' => HyveRoom::query()->where('room_name', 'Table 1-A')->value('id'),
            'booking_date' => '2026-07-23',
            'start_time' => '08:00:00',
            'end_time' => '12:00:00',
            'guests' => 3,
            'rate_name' => 'Hourly',
            'rate_amount' => 125,
            'subtotal' => 500,
            'status' => BookingDetail::STATUS_CONFIRMED,
        ]);
        $this->payment($booking, $admin, 500, 'cash', BookingPayment::STATUS_APPROVED, now());
        BookingActivity::query()->create([
            'booking_header_id' => $booking->getKey(),
            'booking_detail_id' => $detail->getKey(),
            'actor_user_id' => $admin->getKey(),
            'event_key' => 'booking_rescheduled_by_admin',
            'event_label' => 'Booking rescheduled',
        ]);
        $overdue = $this->booking('HYVE-ANALYTICS-OVERDUE', BookingHeader::SOURCE_ADMIN, 'cash', 1000, 500, 500);
        BookingDetail::query()->create([
            'booking_header_id' => $overdue->getKey(),
            'space_id' => Space::query()->where('slug', 'fortitude-office-2-seats')->value('id'),
            'hyve_room_id' => HyveRoom::query()->where('room_name', 'Room 1')->value('id'),
            'booking_date' => '2026-07-20',
            'start_time' => '08:00:00',
            'end_time' => '10:00:00',
            'guests' => 2,
            'subtotal' => 1000,
            'status' => BookingDetail::STATUS_CONFIRMED,
        ]);
        $upcoming = $this->booking('HYVE-ANALYTICS-UPCOMING', BookingHeader::SOURCE_ADMIN, 'cash', 1000, 700, 300);
        BookingDetail::query()->create([
            'booking_header_id' => $upcoming->getKey(),
            'space_id' => Space::query()->where('slug', 'fortitude-office-2-seats')->value('id'),
            'hyve_room_id' => HyveRoom::query()->where('room_name', 'Room 2')->value('id'),
            'booking_date' => '2026-07-25',
            'start_time' => '08:00:00',
            'end_time' => '10:00:00',
            'guests' => 2,
            'subtotal' => 1000,
            'status' => BookingDetail::STATUS_CONFIRMED,
        ]);
        $response = $this->actingAs($admin)
            ->get(route('admin.sales-monitoring.index', [
                'range' => 'custom',
                'date_from' => '2026-07-23',
                'date_to' => '2026-07-23',
            ]));

        $response
            ->assertOk()
            ->assertSee('Monthly sales by space')
            ->assertSee('Customer demand by day and time')
            ->assertViewHas('spaceMonthlyComparison', function (array $comparison): bool {
                $commonArea = $comparison['rows']->firstWhere('label', 'Common Area');

                return (float) ($commonArea['current'] ?? 0) === 500.0;
            })
            ->assertViewHas('spaceWeeklySales', fn ($weeks): bool => (float) $weeks->sum('total') === 500.0)
            ->assertViewHas('bookingSourceCounts', fn ($rows): bool => $rows->firstWhere('key', 'admin')['count'] === 3)
            ->assertViewHas('bookingLifecycle', fn (array $lifecycle): bool => $lifecycle['rows']->firstWhere('key', 'rescheduled')['count'] === 1)
            ->assertViewHas('salesFunnel', fn (array $funnel): bool => $funnel['booked'] === 2500.0
                && $funnel['collected'] === 500.0
                && $funnel['outstanding'] === 800.0)
            ->assertViewHas('todayLiveSales', fn (array $sales): bool => $sales['sales'] === 500.0
                && $sales['transactions'] === 1)
            ->assertViewHas('outstandingAging', fn ($rows): bool => $rows->firstWhere('label', '1-7 days overdue')['amount'] === 500.0)
            ->assertViewHas('upcomingCollections', fn ($rows): bool => $rows->firstWhere('label', 'Next 7 days')['amount'] === 300.0)
            ->assertViewHas('roomUtilization', function ($rows): bool {
                $common = $rows->firstWhere('label', 'Common Area');

                return $common['booked_hours'] === 4.0
                    && $common['revenue'] === 500.0
                    && $common['revenue_per_hour'] === 125.0;
            })
            ->assertViewHas('liveAlerts', fn ($alerts): bool => $alerts->contains(
                fn (array $alert): bool => str_contains($alert['title'], 'overdue booking balance')
            ));

        $heatmap = $response->viewData('demandHeatmap');
        $morning = collect($heatmap['rows'])->firstWhere('key', 'morning');
        $this->assertSame(1, $heatmap['booking_total']);
        $this->assertSame(3, $heatmap['guest_total']);
        $this->assertSame(1, $morning['cells']['Thursday']['bookings']);
    }

    private function booking(
        string $reference,
        string $source,
        string $method,
        float $total,
        float $downpayment,
        float $balance,
    ): BookingHeader {
        return BookingHeader::query()->create([
            'reference_no' => $reference,
            'customer_name' => $source === BookingHeader::SOURCE_ADMIN ? 'Walk-in Customer' : 'Online Customer',
            'email' => strtolower($reference).'@example.com',
            'phone' => '09171234567',
            'booking_type' => BookingHeader::TYPE_GUEST,
            'source' => $source,
            'payment_method' => $method,
            'payment_status' => $balance > 0 ? 'partially_paid' : 'paid',
            'total_amount' => $total,
            'downpayment_amount' => $downpayment,
            'balance_amount' => $balance,
            'status' => 'confirmed',
        ]);
    }

    private function payment(
        BookingHeader $header,
        User $admin,
        float $amount,
        string $method,
        string $status,
        ?Carbon $verifiedAt,
    ): BookingPayment {
        return BookingPayment::query()->create([
            'booking_header_id' => $header->getKey(),
            'payment_type' => BookingPayment::TYPE_DOWNPAYMENT,
            'amount' => $amount,
            'payment_method' => $method,
            'status' => $status,
            'paid_at' => now(),
            'verified_at' => $verifiedAt,
            'verified_by' => $verifiedAt ? $admin->getKey() : null,
        ]);
    }
}
