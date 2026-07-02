<?php

namespace App\Http\Controllers;

use App\Models\HyveRate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __invoke(Request $request): View|RedirectResponse
    {
        if ($request->user()?->isAdmin()) {
            return redirect()->route('admin.dashboard');
        }

        $payload = config('hyve');

        if (Schema::hasTable('hyve_rates')) {
            $payload['rates'] = HyveRate::query()
                ->active()
                ->orderBy('sort_order')
                ->get()
                ->map(fn (HyveRate $rate): array => $rate->toDisplayArray())
                ->all();
        }

        return view('welcome', $payload);
    }
}
