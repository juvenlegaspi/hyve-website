<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PaymentSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class AdminSettingController extends Controller
{
    public function index(Request $request): View
    {
        $paymentSetting = PaymentSetting::query()
            ->where('is_active', true)
            ->latest('updated_at')
            ->first();

        if (! $paymentSetting) {
            $paymentSetting = PaymentSetting::query()->latest('updated_at')->first();
        }

        return view('admin.settings.index', [
            'meta' => [
                'title' => 'Settings | HYVE Admin',
                'description' => 'Manage payment instructions and booking rules used by the HYVE booking flow.',
            ],
            'adminUser' => $request->user(),
            'paymentSetting' => $paymentSetting,
            'canManageSettings' => $request->user()?->hasPermission('settings.manage') ?? false,
            'websiteUrl' => (string) config('hyve.website.public_url', config('app.url')),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'gcash_account_name' => ['nullable', 'string', 'max:150'],
            'gcash_number' => ['nullable', 'string', 'max:50'],
            'gcash_qr' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'bank_name' => ['nullable', 'string', 'max:150'],
            'bank_account_name' => ['nullable', 'string', 'max:150'],
            'bank_account_number' => ['nullable', 'string', 'max:80'],
            'bank_qr' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'downpayment_percentage' => ['required', 'numeric', 'min:1', 'max:100'],
            'instructions' => ['nullable', 'string', 'max:2000'],
            'mikrotik_enabled' => ['nullable', 'boolean'],
            'mikrotik_host' => ['nullable', 'string', 'max:150'],
            'mikrotik_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'mikrotik_username' => ['nullable', 'string', 'max:120'],
            'mikrotik_password' => ['nullable', 'string', 'max:255'],
            'mikrotik_hotspot_server' => ['nullable', 'string', 'max:120'],
            'mikrotik_dns_name' => ['nullable', 'string', 'max:150'],
        ]);

        $setting = PaymentSetting::query()
            ->where('is_active', true)
            ->latest('updated_at')
            ->first();

        if (! $setting) {
            $setting = PaymentSetting::query()->latest('updated_at')->first();
        }

        if (! $setting) {
            $setting = new PaymentSetting();
        }

        $gcashQrPath = $setting->gcash_qr_path;

        if ($request->hasFile('gcash_qr')) {
            $newPath = $request->file('gcash_qr')?->store('payment-settings', 'public');

            if ($newPath) {
                if ($gcashQrPath && Storage::disk('public')->exists($gcashQrPath)) {
                    Storage::disk('public')->delete($gcashQrPath);
                }

                $gcashQrPath = $newPath;
            }
        }

        $bankQrPath = $setting->bank_qr_path;

        if ($request->hasFile('bank_qr')) {
            $newPath = $request->file('bank_qr')?->store('payment-settings', 'public');

            if ($newPath) {
                if ($bankQrPath && Storage::disk('public')->exists($bankQrPath)) {
                    Storage::disk('public')->delete($bankQrPath);
                }

                $bankQrPath = $newPath;
            }
        }

        $setting->fill([
            'gcash_account_name' => trim((string) ($validated['gcash_account_name'] ?? '')) ?: null,
            'gcash_number' => trim((string) ($validated['gcash_number'] ?? '')) ?: null,
            'gcash_qr_path' => $gcashQrPath,
            'bank_name' => trim((string) ($validated['bank_name'] ?? '')) ?: null,
            'bank_account_name' => trim((string) ($validated['bank_account_name'] ?? '')) ?: null,
            'bank_account_number' => trim((string) ($validated['bank_account_number'] ?? '')) ?: null,
            'bank_qr_path' => $bankQrPath,
            'downpayment_percentage' => round((float) $validated['downpayment_percentage'], 2),
            'instructions' => trim((string) ($validated['instructions'] ?? '')) ?: null,
            'mikrotik_enabled' => (bool) ($validated['mikrotik_enabled'] ?? false),
            'mikrotik_host' => trim((string) ($validated['mikrotik_host'] ?? '')) ?: null,
            'mikrotik_port' => (int) ($validated['mikrotik_port'] ?? 8728),
            'mikrotik_username' => trim((string) ($validated['mikrotik_username'] ?? '')) ?: null,
            'mikrotik_password' => trim((string) ($validated['mikrotik_password'] ?? '')) ?: ($setting->mikrotik_password ?: null),
            'mikrotik_hotspot_server' => trim((string) ($validated['mikrotik_hotspot_server'] ?? '')) ?: null,
            'mikrotik_dns_name' => trim((string) ($validated['mikrotik_dns_name'] ?? '')) ?: null,
            'is_active' => true,
        ]);
        $setting->save();

        PaymentSetting::query()
            ->whereKeyNot($setting->getKey())
            ->where('is_active', true)
            ->update(['is_active' => false]);

        return back()->with('admin_success', 'Settings updated successfully.');
    }

    public function testMikrotik(Request $request): RedirectResponse
    {
        $setting = PaymentSetting::query()
            ->where('is_active', true)
            ->latest('updated_at')
            ->first();

        if (! $setting) {
            return back()->with('admin_error', 'No active settings profile found yet.');
        }

        if (! $setting->mikrotik_enabled) {
            return back()->with('admin_error', 'MikroTik sync is still disabled in Settings.');
        }

        if (! $setting->hasMikrotikSetup()) {
            return back()->with('admin_error', 'Please complete the MikroTik host, username, and password first before testing.');
        }

        $host = trim((string) $setting->mikrotik_host);
        $port = (int) ($setting->mikrotik_port ?: 8728);
        $connection = @stream_socket_client(
            sprintf('tcp://%s:%d', $host, $port),
            $errorNumber,
            $errorMessage,
            3
        );

        if (! is_resource($connection)) {
            $message = trim($errorMessage ?: 'Unable to reach the MikroTik API port.');

            return back()->with('admin_error', 'MikroTik test failed: '.$message);
        }

        fclose($connection);

        return back()->with('admin_success', 'MikroTik host is reachable on '.$host.':'.$port.'. Router login verification will be added when the real device integration is connected.');
    }
}
