<?php

namespace App\Http\Controllers;

use App\Http\Requests\Auth\OwnerLoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OwnerAuthController extends Controller
{
    public function create(Request $request): View|RedirectResponse
    {
        if ($request->user()) {
            return $request->user()->canAccessAdminPanel()
                ? redirect()->route('admin.dashboard')
                : redirect()->route('bookings.index');
        }

        return view('owner.auth.login');
    }

    public function store(OwnerLoginRequest $request): RedirectResponse
    {
        $request->authenticate();
        $request->session()->regenerate();

        return redirect()->route('admin.dashboard');
    }
}
