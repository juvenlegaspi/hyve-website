<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BookingHeader;
use App\Models\SupportConversation;
use App\Models\SupportMessage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AdminSupportMessageController extends Controller
{
    public function index(Request $request): View
    {
        return view('admin.messages.index', [
            'adminUser' => $request->user(),
            'meta' => [
                'title' => 'Customer Messages | HYVE Admin',
                'description' => 'Reply to HYVE website customer conversations.',
            ],
        ]);
    }

    public function feed(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', 'in:all,open,closed'],
            'unread' => ['nullable', 'boolean'],
        ]);
        $selectedId = $request->integer('conversation_id');
        $search = trim((string) ($validated['search'] ?? ''));
        $status = (string) ($validated['status'] ?? 'all');
        $conversations = SupportConversation::query()
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $match) use ($search): void {
                    $match->where('customer_name', 'like', '%'.$search.'%')
                        ->orWhere('email', 'like', '%'.$search.'%')
                        ->orWhere('phone', 'like', '%'.$search.'%');
                });
            })
            ->when($status !== 'all', fn (Builder $query) => $query->where('status', $status))
            ->when((bool) ($validated['unread'] ?? false), fn (Builder $query) => $query->withUnreadForAdmin())
            ->with(['latestMessage.senderUser'])
            ->withCount(['messages as unread_count' => function (Builder $messages): void {
                $messages->where('sender_type', SupportMessage::SENDER_CUSTOMER)
                    ->where(function (Builder $unread): void {
                        $unread->whereColumn('support_messages.id', '>', 'support_conversations.admin_last_read_message_id')
                            ->orWhereNull('support_conversations.admin_last_read_message_id');
                    });
            }])
            ->orderByRaw('last_message_at IS NULL')
            ->orderByDesc('last_message_at')
            ->limit(100)
            ->get();

        $selected = $selectedId
            ? $conversations->firstWhere('id', $selectedId)
            : $conversations->first();

        if ($selected) {
            $selected->forceFill([
                'admin_last_read_at' => now(),
                'admin_last_read_message_id' => $selected->messages()->max('id'),
            ])->save();
            $selected->setAttribute('unread_count', 0);
        }

        return response()->json([
            'unread_total' => SupportConversation::query()->withUnreadForAdmin()->count(),
            'conversations' => $conversations->map(fn (SupportConversation $conversation): array => $this->conversationSummary($conversation))->all(),
            'selected' => $selected ? $this->adminConversationPayload($selected->fresh()) : null,
        ]);
    }

    public function unread(): JsonResponse
    {
        $latestUnread = SupportMessage::query()
            ->where('sender_type', SupportMessage::SENDER_CUSTOMER)
            ->whereHas('conversation', function (Builder $conversation): void {
                $conversation->where('mode', SupportConversation::MODE_FRONT_DESK)
                    ->where(function (Builder $unread): void {
                        $unread->whereColumn('support_messages.id', '>', 'support_conversations.admin_last_read_message_id')
                            ->orWhereNull('support_conversations.admin_last_read_message_id');
                    });
            })
            ->with('conversation:id,customer_name')
            ->latest('id')
            ->first();

        return response()->json([
            'unread_total' => SupportConversation::query()->withUnreadForAdmin()->count(),
            'latest_message' => $latestUnread ? [
                'id' => $latestUnread->getKey(),
                'customer_name' => $latestUnread->conversation?->customer_name ?? 'Website visitor',
                'preview' => str($latestUnread->body)->limit(100)->toString(),
            ] : null,
        ]);
    }

    public function reply(Request $request, SupportConversation $conversation): JsonResponse
    {
        $validated = $request->validate([
            'message' => ['required', 'string', 'max:2000'],
            'action' => ['nullable', 'in:booking'],
        ]);

        DB::transaction(function () use ($request, $conversation, $validated): void {
            $now = now();
            $message = $conversation->messages()->create([
                'sender_type' => SupportMessage::SENDER_ADMIN,
                'sender_user_id' => $request->user()?->getKey(),
                'body' => trim($validated['message']),
                'action_type' => ($validated['action'] ?? null) === 'booking' ? 'booking' : null,
                'action_label' => ($validated['action'] ?? null) === 'booking' ? 'Book Now' : null,
                'action_url' => ($validated['action'] ?? null) === 'booking' ? route('bookings.index') : null,
            ]);
            $conversation->forceFill([
                'mode' => SupportConversation::MODE_FRONT_DESK,
                'assigned_user_id' => $conversation->assigned_user_id ?: $request->user()?->getKey(),
                'status' => SupportConversation::STATUS_OPEN,
                'last_message_at' => $now,
                'admin_last_read_at' => $now,
                'admin_last_read_message_id' => $message->getKey(),
            ])->save();
        });

        return response()->json($this->adminConversationPayload($conversation->fresh()));
    }

    public function status(Request $request, SupportConversation $conversation): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'in:open,closed'],
        ]);
        $conversation->update([
            'status' => $validated['status'],
            'assigned_user_id' => $conversation->assigned_user_id ?: $request->user()?->getKey(),
            'admin_last_read_at' => now(),
            'admin_last_read_message_id' => $conversation->messages()->max('id'),
        ]);

        return response()->json($this->adminConversationPayload($conversation->fresh()));
    }

    public function destroy(SupportConversation $conversation): JsonResponse
    {
        $conversationId = $conversation->getKey();
        $conversation->delete();

        return response()->json([
            'deleted' => true,
            'conversation_id' => $conversationId,
        ]);
    }

    /** @return array<string, mixed> */
    private function conversationSummary(SupportConversation $conversation): array
    {
        return [
            'id' => $conversation->getKey(),
            'customer_name' => $conversation->customer_name,
            'contact' => $conversation->email ?: ($conversation->phone ?: 'No contact provided'),
            'status' => $conversation->status,
            'mode' => $conversation->mode,
            'unread_count' => $conversation->mode === SupportConversation::MODE_FRONT_DESK
                ? (int) ($conversation->unread_count ?? 0)
                : 0,
            'preview' => str((string) ($conversation->latestMessage?->body ?? 'No messages'))->limit(80)->toString(),
            'last_message_at' => optional($conversation->last_message_at)->diffForHumans() ?? '--',
        ];
    }

    /** @return array<string, mixed> */
    private function adminConversationPayload(SupportConversation $conversation): array
    {
        $conversation->load(['messages.senderUser', 'assignedUser']);
        $bookingMatch = $this->bookingMatch($conversation);

        return [
            'id' => $conversation->getKey(),
            'customer_name' => $conversation->customer_name,
            'email' => $conversation->email,
            'phone' => $conversation->phone,
            'status' => $conversation->status,
            'mode' => $conversation->mode,
            'assigned_to' => $conversation->assignedUser?->name,
            'reply_url' => route('admin.messages.reply', $conversation),
            'status_url' => route('admin.messages.status', $conversation),
            'delete_url' => route('admin.messages.destroy', $conversation),
            'booking_match' => $bookingMatch,
            'messages' => $conversation->messages->map(fn (SupportMessage $message): array => [
                'id' => $message->getKey(),
                'sender' => $message->sender_type,
                'sender_name' => match ($message->sender_type) {
                    SupportMessage::SENDER_ADMIN => $message->senderUser?->name ?: 'HYVE Front Desk',
                    SupportMessage::SENDER_ASSISTANT => 'HYVE Assistant',
                    default => $conversation->customer_name,
                },
                'body' => $message->body,
                'created_at' => optional($message->created_at)->format('M j, Y g:i A'),
                'action' => $message->action_url ? [
                    'type' => $message->action_type,
                    'label' => $message->action_label,
                    'url' => $message->action_url,
                ] : null,
                'is_read' => $message->sender_type === SupportMessage::SENDER_ADMIN
                    && $conversation->customer_last_read_message_id
                    && $message->getKey() <= $conversation->customer_last_read_message_id,
            ])->all(),
        ];
    }

    /** @return array<string, mixed>|null */
    private function bookingMatch(SupportConversation $conversation): ?array
    {
        $email = trim((string) $conversation->email);
        $phone = trim((string) $conversation->phone);

        if ($email === '' && $phone === '') {
            return null;
        }

        $bookings = BookingHeader::query()
            ->where(function (Builder $query) use ($email, $phone): void {
                if ($email !== '') {
                    $query->whereRaw('LOWER(email) = ?', [mb_strtolower($email)]);
                }
                if ($phone !== '') {
                    $email !== '' ? $query->orWhere('phone', $phone) : $query->where('phone', $phone);
                }
            });
        $count = (clone $bookings)->count();

        if ($count < 1) {
            return null;
        }

        $latest = $bookings->latest('created_at')->latest('id')->first();

        return [
            'count' => $count,
            'latest_reference' => $latest?->reference_no,
            'url' => route('admin.bookings.index', ['search' => $latest?->reference_no ?: ($email ?: $phone)]),
        ];
    }
}
