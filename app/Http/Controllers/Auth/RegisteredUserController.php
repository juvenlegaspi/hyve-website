<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterUserRequest;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    public function create(Request $request): View
    {
        $this->rememberReturnTo($request);

        return view('auth.register');
    }

    public function store(RegisterUserRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        
        $user = User::create([
            'username' => $validated['username'],
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'email' => $validated['email'],
            'number' => $validated['phone'],
            'password' => $validated['password'],
            'status' => 0,
            'role' => User::ROLE_MEMBER,
        ]);

        event(new Registered($user));

        Auth::login($user);

        $request->session()->regenerate();

        return redirect()->intended(route('bookings.index'));
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
