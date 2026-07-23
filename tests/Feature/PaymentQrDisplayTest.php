<?php

namespace Tests\Feature;

use App\Models\PaymentSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PaymentQrDisplayTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_payment_qr_is_served_without_a_public_storage_link(): void
    {
        Storage::fake('public');
        $path = 'payment-settings/gcash-test.png';
        Storage::disk('public')->put($path, base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII='
        ));

        PaymentSetting::query()->update(['is_active' => false]);
        PaymentSetting::query()->create([
            'gcash_account_name' => 'HYVE',
            'gcash_number' => '09171234567',
            'gcash_qr_path' => $path,
            'downpayment_percentage' => 50,
            'is_active' => true,
        ]);

        $this->get(route('payment-qr.show', 'gcash'))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png')
            ->assertHeader('Cache-Control', 'must-revalidate, no-cache, no-store, private');
    }

    public function test_missing_payment_qr_returns_not_found(): void
    {
        Storage::fake('public');

        $this->get(route('payment-qr.show', 'bank'))->assertNotFound();
    }
}
