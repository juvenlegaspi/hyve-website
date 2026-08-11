<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginUserRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    public function create(Request $request): View
    {
        $this->rememberReturnTo($request);

        return view('auth.login');
    }

    public function store(LoginUserRequest $request): RedirectResponse
    {
        $request->authenticate();
        $request->session()->regenerate();
        $request->session()->forget('url.intended');

        /** @var User|null $user */
        $user = $request->user();

        if ($user?->isOwner()) {
            Auth::logout();

            throw ValidationException::withMessages([
                'login' => 'Please use the dedicated HYVE Owner login page.',
            ]);
        }

        if ($user?->isAdmin()) {
            return redirect()->route('admin.dashboard');
        }

        return redirect()->route('member.dashboard');
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
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
