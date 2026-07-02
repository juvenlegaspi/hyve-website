<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\AdminLoginRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AdminAuthController extends Controller
{
    public function create(Request $request): View|RedirectResponse
    {
        if ($request->user()) {
            return $request->user()->canAccessAdminPanel()
                ? redirect()->route('admin.dashboard')
                : redirect()->route('bookings.index');
        }

        $this->rememberReturnTo($request);

        return view('admin.auth.login');
    }

    public function store(AdminLoginRequest $request): RedirectResponse
    {
        $request->authenticate();
        $request->session()->regenerate();

        return redirect()->intended(route('admin.dashboard'));
    }

    private function rememberReturnTo(Request $request): void
    {
        $returnTo = $request->query('return_to');

        if (! is_string($returnTo) || $returnTo === '') {
            return;
        }

        if ($this->isSafeReturnTo($request, $returnTo)) {
            $request->session()->put('url.intended', $returnTo);
        }
    }

    private function isSafeReturnTo(Request $request, string $returnTo): bool
    {
        if (Str::startsWith($returnTo, '/')) {
            return true;
        }

        return Str::startsWith($returnTo, $request->getSchemeAndHttpHost());
    }
}
