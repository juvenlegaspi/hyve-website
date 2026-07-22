<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\View\View;

class PasswordResetLinkController extends Controller
{
    private const STATUS_MESSAGE = 'If an active HYVE account matches that email, a password reset link has been sent.';

    public function create(): View
    {
        return view('auth.forgot-password');
    }

    public function store(Request $request): RedirectResponse
    {
        $email = Str::lower(trim($request->string('email')->toString()));
        $request->merge(['email' => $email]);

        $request->validate([
            'email' => ['required', 'email'],
        ]);

        $hasActiveAccount = User::query()
            ->where('email', $email)
            ->where('status', 0)
            ->exists();

        if ($hasActiveAccount) {
            Password::sendResetLink(['email' => $email]);
        }

        return back()->with('status', self::STATUS_MESSAGE);
    }
}
