<?php

namespace App\Http\Controllers;

use App\Models\BookingActivity;
use App\Models\BookingHeader;
use App\Models\MemberAnnouncement;
use App\Models\MemberAnnouncementRead;
use App\Models\MemberBookingNotificationRead;
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
        $bookingNotifications = $this->bookingNotifications($request);
        $announcementUnreadTotal = MemberAnnouncement::query()
            ->published()
            ->whereDoesntHave('reads', fn ($query) => $query->where('user_id', $userId))
            ->count();
        $bookingUnreadTotal = $bookingNotifications
            ->where('member_notification_read_count', 0)
            ->count();
        $latestAnnouncement = $announcements->first();
        $latestBookingNotification = $bookingNotifications->first();
        $latestNotification = collect([
            $latestAnnouncement ? [
                'key' => 'announcement:'.$latestAnnouncement->getKey(),
                'title' => $latestAnnouncement->title,
                'created_at' => $latestAnnouncement->published_at,
            ] : null,
            $latestBookingNotification ? [
                'key' => 'booking:'.$latestBookingNotification->getKey(),
                'title' => 'Booking approved: '.$latestBookingNotification->reference_no,
                'created_at' => $latestBookingNotification->created_at,
            ] : null,
        ])->filter()->sortByDesc(fn (array $item) => optional($item['created_at'])->timestamp ?? 0)->first();

        return response()->json([
            'unread_total' => $announcementUnreadTotal + $bookingUnreadTotal,
            'latest_notification' => $latestNotification ? [
                'key' => $latestNotification['key'],
                'title' => $latestNotification['title'],
            ] : null,
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
            'booking_notifications' => $bookingNotifications->map(fn (BookingActivity $activity): array => $this->bookingNotificationPayload($activity)),
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

        $bookingRows = $this->bookingNotifications($request)
            ->map(fn (BookingActivity $activity): array => [
                'booking_activity_id' => $activity->getKey(),
                'user_id' => $request->user()->id,
                'read_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ])
            ->all();

        if ($bookingRows !== []) {
            MemberBookingNotificationRead::query()->upsert(
                $bookingRows,
                ['booking_activity_id', 'user_id'],
                ['read_at', 'updated_at'],
            );
        }

        return response()->json(['message' => 'All announcements marked as read.']);
    }

    public function markBookingNotificationRead(Request $request, BookingActivity $bookingActivity): JsonResponse
    {
        $this->ensureMember($request);
        abort_unless($this->memberCanViewBookingNotification($request, $bookingActivity), 404);

        MemberBookingNotificationRead::query()->updateOrCreate(
            ['booking_activity_id' => $bookingActivity->getKey(), 'user_id' => $request->user()->id],
            ['read_at' => now()],
        );

        return response()->json(['message' => 'Booking notification marked as read.']);
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

    private function bookingNotifications(Request $request)
    {
        $userId = (int) $request->user()->id;

        return BookingActivity::query()
            ->whereIn('event_key', ['booking_approved', 'booking_line_approved'])
            ->whereHas('bookingHeader', fn ($query) => $query
                ->where('booking_type', BookingHeader::TYPE_MEMBER)
                ->where('user_id', $userId))
            ->withCount(['memberNotificationReads as member_notification_read_count' => fn ($query) => $query->where('user_id', $userId)])
            ->latest('created_at')
            ->latest('id')
            ->limit(20)
            ->get();
    }

    private function memberCanViewBookingNotification(Request $request, BookingActivity $activity): bool
    {
        return in_array((string) $activity->event_key, ['booking_approved', 'booking_line_approved'], true)
            && $activity->bookingHeader()
                ->where('booking_type', BookingHeader::TYPE_MEMBER)
                ->where('user_id', $request->user()->id)
                ->exists();
    }

    private function bookingNotificationPayload(BookingActivity $activity): array
    {
        $schedule = collect([
            optional($activity->booking_date)->format('F j, Y'),
            trim((string) $activity->time_range),
        ])->filter()->implode(' · ');

        return [
            'id' => $activity->getKey(),
            'title' => 'Booking approved',
            'body' => 'Your booking '.$activity->reference_no.' has been approved by HYVE.'.($schedule !== '' ? ' '.$schedule.'.' : ''),
            'reference_no' => $activity->reference_no,
            'created_at' => optional($activity->created_at)->format('M j, Y g:i A'),
            'is_read' => (int) ($activity->member_notification_read_count ?? 0) > 0,
            'read_url' => route('member.booking-notifications.read', $activity),
        ];
    }
}
