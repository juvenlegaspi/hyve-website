<?php

namespace App\Http\Controllers;

use App\Models\MemberAnnouncement;
use App\Models\MemberAnnouncementRead;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MemberAnnouncementController extends Controller
{
    public function feed(Request $request): JsonResponse
    {
        $this->ensureMember($request);
        $userId = (int) $request->user()->id;
        $announcements = MemberAnnouncement::query()
            ->published()
            ->withCount(['reads as member_read_count' => fn ($query) => $query->where('user_id', $userId)])
            ->latest('published_at')
            ->latest('id')
            ->limit(20)
            ->get();

        return response()->json([
            'unread_total' => MemberAnnouncement::query()
                ->published()
                ->whereDoesntHave('reads', fn ($query) => $query->where('user_id', $userId))
                ->count(),
            'latest_announcement' => $announcements->first() ? [
                'id' => $announcements->first()->getKey(),
                'title' => $announcements->first()->title,
                'priority' => $announcements->first()->priority,
            ] : null,
            'announcements' => $announcements->map(fn (MemberAnnouncement $announcement): array => [
                'id' => $announcement->getKey(),
                'title' => $announcement->title,
                'body' => $announcement->body,
                'priority' => $announcement->priority,
                'published_at' => $announcement->published_at?->format('M j, Y g:i A'),
                'is_read' => (int) $announcement->member_read_count > 0,
                'read_url' => route('member.announcements.read', $announcement),
            ])->values(),
        ]);
    }

    public function markRead(Request $request, MemberAnnouncement $announcement): JsonResponse
    {
        $this->ensureMember($request);
        abort_unless($this->isPublished($announcement), 404);

        MemberAnnouncementRead::query()->updateOrCreate(
            ['member_announcement_id' => $announcement->getKey(), 'user_id' => $request->user()->id],
            ['read_at' => now()],
        );

        return response()->json(['message' => 'Announcement marked as read.']);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $this->ensureMember($request);
        $now = now();
        $rows = MemberAnnouncement::query()
            ->published()
            ->pluck('id')
            ->map(fn ($announcementId): array => [
                'member_announcement_id' => $announcementId,
                'user_id' => $request->user()->id,
                'read_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ])
            ->all();

        if ($rows !== []) {
            MemberAnnouncementRead::query()->upsert(
                $rows,
                ['member_announcement_id', 'user_id'],
                ['read_at', 'updated_at'],
            );
        }

        return response()->json(['message' => 'All announcements marked as read.']);
    }

    private function ensureMember(Request $request): void
    {
        abort_if($request->user()?->isAdmin(), 403);
    }

    private function isPublished(MemberAnnouncement $announcement): bool
    {
        return $announcement->is_active
            && $announcement->published_at?->lte(now())
            && (! $announcement->expires_at || $announcement->expires_at->gt(now()));
    }
}
