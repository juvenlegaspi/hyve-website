<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MemberAnnouncement;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdminAnnouncementController extends Controller
{
    public function index(Request $request): View
    {
        return view('admin.announcements.index', [
            'meta' => [
                'title' => 'Member Announcements | HYVE Admin',
                'description' => 'Publish updates and important notices to HYVE members.',
            ],
            'adminUser' => $request->user(),
            'announcements' => MemberAnnouncement::query()
                ->with('creator:id,first_name,last_name')
                ->withCount('reads')
                ->latest('published_at')
                ->latest('id')
                ->paginate(12),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validatedPayload($request);

        MemberAnnouncement::query()->create([
            ...$validated,
            'created_by' => $request->user()?->getKey(),
        ]);

        return back()->with('admin_success', 'Member announcement published successfully.');
    }

    public function update(Request $request, MemberAnnouncement $announcement): RedirectResponse
    {
        $announcement->update($this->validatedPayload($request));

        return back()->with('admin_success', 'Member announcement updated successfully.');
    }

    public function destroy(MemberAnnouncement $announcement): RedirectResponse
    {
        $announcement->delete();

        return back()->with('admin_success', 'Member announcement deleted successfully.');
    }

    private function validatedPayload(Request $request): array
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'body' => ['required', 'string', 'max:3000'],
            'priority' => ['required', Rule::in([
                MemberAnnouncement::PRIORITY_INFO,
                MemberAnnouncement::PRIORITY_IMPORTANT,
                MemberAnnouncement::PRIORITY_URGENT,
            ])],
            'published_at' => ['required', 'date'],
            'expires_at' => ['nullable', 'date', 'after:published_at'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $validated['is_active'] = (bool) ($validated['is_active'] ?? false);

        return $validated;
    }
}
