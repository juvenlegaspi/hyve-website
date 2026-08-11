<?php

namespace App\Http\Controllers;

use App\Models\SupportConversation;
use App\Models\SupportMessage;
use App\Services\HyveAssistantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SupportChatController extends Controller
{
    public function __construct(private readonly HyveAssistantService $assistant) {}

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'customer_name' => ['required', 'string', 'max:120'],
            'email' => ['nullable', 'required_without:phone', 'email:rfc', 'max:190'],
            'phone' => ['nullable', 'required_without:email', 'string', 'max:40'],
            'message' => ['required', 'string', 'max:2000'],
            'mode' => ['nullable', 'in:assistant,front_desk'],
        ]);

        $conversation = DB::transaction(function () use ($request, $validated): SupportConversation {
            $now = now();
            $mode = (string) ($validated['mode'] ?? SupportConversation::MODE_FRONT_DESK);
            $conversation = SupportConversation::query()->create([
                'public_token' => (string) Str::uuid(),
                'user_id' => $request->user()?->getKey(),
                'customer_name' => trim($validated['customer_name']),
                'email' => trim((string) ($validated['email'] ?? '')) ?: null,
                'phone' => trim((string) ($validated['phone'] ?? '')) ?: null,
                'status' => SupportConversation::STATUS_OPEN,
                'mode' => $mode,
                'last_message_at' => $now,
                'customer_last_read_at' => $now,
            ]);

            $message = $conversation->messages()->create([
                'sender_type' => SupportMessage::SENDER_CUSTOMER,
                'sender_user_id' => $request->user()?->getKey(),
                'body' => trim($validated['message']),
            ]);
            $conversation->forceFill(['customer_last_read_message_id' => $message->getKey()])->save();

            if ($mode === SupportConversation::MODE_ASSISTANT) {
                $this->addAssistantReply($conversation, $validated['message']);
            }

            return $conversation;
        });

        return response()->json($this->customerPayload($conversation->fresh()), 201);
    }

    public function show(Request $request, string $publicToken): JsonResponse
    {
        $validated = $request->validate([
            'before_id' => ['nullable', 'integer', 'min:1'],
            'mark_read' => ['nullable', 'boolean'],
        ]);
        $conversation = $this->findPublicConversation($publicToken);

        if (($validated['mark_read'] ?? false) && ! isset($validated['before_id'])) {
            $conversation->forceFill([
                'customer_last_read_at' => now(),
                'customer_last_read_message_id' => $conversation->messages()->max('id'),
            ])->save();
        }

        return response()->json($this->customerPayload($conversation, isset($validated['before_id']) ? (int) $validated['before_id'] : null));
    }

    public function message(Request $request, string $publicToken): JsonResponse
    {
        $validated = $request->validate([
            'message' => ['required', 'string', 'max:2000'],
            'reply_to_message_id' => ['nullable', 'integer', 'min:1'],
        ]);
        $conversation = $this->findPublicConversation($publicToken);
        $replyToId = $this->replyTargetId($conversation, $validated['reply_to_message_id'] ?? null);

        DB::transaction(function () use ($request, $conversation, $validated, $replyToId): void {
            $now = now();
            $message = $conversation->messages()->create([
                'sender_type' => SupportMessage::SENDER_CUSTOMER,
                'sender_user_id' => $request->user()?->getKey(),
                'reply_to_message_id' => $replyToId,
                'body' => trim($validated['message']),
            ]);
            $conversation->forceFill([
                'status' => SupportConversation::STATUS_OPEN,
                'last_message_at' => $now,
                'customer_last_read_at' => $now,
                'customer_last_read_message_id' => $message->getKey(),
            ])->save();

            if ($conversation->mode === SupportConversation::MODE_ASSISTANT) {
                $this->addAssistantReply($conversation, $validated['message']);
            }
        });

        return response()->json($this->customerPayload($conversation->fresh()));
    }

    public function handoff(string $publicToken): JsonResponse
    {
        $conversation = $this->findPublicConversation($publicToken);

        if ($conversation->mode !== SupportConversation::MODE_FRONT_DESK) {
            DB::transaction(function () use ($conversation): void {
                $now = now();
                $message = $conversation->messages()->create([
                    'sender_type' => SupportMessage::SENDER_ASSISTANT,
                    'body' => 'Your conversation has been sent to the HYVE Front Desk. A staff member can reply here as soon as they are available.',
                ]);
                $conversation->forceFill([
                    'mode' => SupportConversation::MODE_FRONT_DESK,
                    'status' => SupportConversation::STATUS_OPEN,
                    'last_message_at' => $now,
                    'customer_last_read_at' => $now,
                    'customer_last_read_message_id' => $message->getKey(),
                ])->save();
            });
        }

        return response()->json($this->customerPayload($conversation->fresh()));
    }

    public function react(Request $request, string $publicToken, SupportMessage $message): JsonResponse
    {
        $validated = $request->validate([
            'emoji' => ['nullable', 'string', 'in:👍,❤️,😂,😮,😢,🙏'],
        ]);
        $conversation = $this->findPublicConversation($publicToken);
        abort_unless((int) $message->support_conversation_id === (int) $conversation->getKey(), 404);

        $reaction = $message->reactions()->where('actor_key', 'customer')->first();
        $emoji = $validated['emoji'] ?? null;

        if (! $emoji || $reaction?->emoji === $emoji) {
            $reaction?->delete();
        } else {
            $message->reactions()->updateOrCreate(
                ['actor_key' => 'customer'],
                ['actor_type' => SupportMessage::SENDER_CUSTOMER, 'actor_user_id' => $request->user()?->getKey(), 'emoji' => $emoji],
            );
        }

        $conversation->forceFill([
            'customer_last_read_at' => now(),
            'customer_last_read_message_id' => $conversation->messages()->max('id'),
        ])->save();

        return response()->json($this->customerPayload($conversation->fresh()));
    }

    private function findPublicConversation(string $publicToken): SupportConversation
    {
        abort_unless(Str::isUuid($publicToken), 404);

        $conversation = SupportConversation::query()->where('public_token', $publicToken)->firstOrFail();
        $retentionDays = max(1, (int) config('hyve.support.conversation_retention_days', 90));
        abort_if($conversation->last_message_at?->lt(now()->subDays($retentionDays)), 404);

        return $conversation;
    }

    /** @return array<string, mixed> */
    private function customerPayload(SupportConversation $conversation, ?int $beforeId = null): array
    {
        $messageQuery = $conversation->messages()
            ->with(['senderUser:id,first_name,last_name', 'replyTo:id,sender_type,body', 'reactions:id,support_message_id,actor_key,emoji'])
            ->when($beforeId, fn ($query) => $query->where('id', '<', $beforeId))
            ->latest('id')
            ->limit(51)
            ->get();
        $hasMore = $messageQuery->count() > 50;
        $messages = $messageQuery->take(50)
            ->reverse()
            ->values();

        return [
            'token' => $conversation->public_token,
            'status' => $conversation->status,
            'mode' => $conversation->mode,
            'customer_name' => $conversation->customer_name,
            'messages' => $messages->map(fn (SupportMessage $message): array => $this->customerMessagePayload($conversation, $message))->all(),
            'has_more' => $hasMore,
            'next_before_id' => $hasMore ? $messages->first()?->getKey() : null,
            'unread_count' => $conversation->messages()
                ->where('sender_type', SupportMessage::SENDER_ADMIN)
                ->when($conversation->customer_last_read_message_id, fn ($query, $id) => $query->where('id', '>', $id))
                ->when(! $conversation->customer_last_read_message_id, fn ($query) => $query)
                ->count(),
            'expires_after_days' => max(1, (int) config('hyve.support.conversation_retention_days', 90)),
            'poll_url' => route('support.conversations.show', $conversation->public_token),
            'message_url' => route('support.conversations.message', $conversation->public_token),
            'handoff_url' => route('support.conversations.handoff', $conversation->public_token),
            'reaction_url_template' => route('support.conversations.reaction', [$conversation->public_token, '__MESSAGE__']),
        ];
    }

    /** @return array<string, mixed> */
    private function customerMessagePayload(SupportConversation $conversation, SupportMessage $message): array
    {
        return [
                'id' => $message->getKey(),
                'sender' => $message->sender_type,
                'sender_name' => match ($message->sender_type) {
                    SupportMessage::SENDER_ADMIN => $message->senderUser?->name ?: 'HYVE Front Desk',
                    SupportMessage::SENDER_ASSISTANT => 'HYVE Assistant',
                    default => $conversation->customer_name,
                },
                'body' => $message->body,
                'created_at' => optional($message->created_at)->format('M j, g:i A'),
                'reply_to' => $message->replyTo ? [
                    'id' => $message->replyTo->getKey(),
                    'sender_name' => $message->replyTo->sender_type === SupportMessage::SENDER_CUSTOMER ? $conversation->customer_name : ($message->replyTo->sender_type === SupportMessage::SENDER_ASSISTANT ? 'HYVE Assistant' : 'HYVE Front Desk'),
                    'body' => str($message->replyTo->body)->limit(100)->toString(),
                ] : null,
                'reactions' => $message->reactions->groupBy('emoji')->map(fn ($items, $emoji): array => [
                    'emoji' => $emoji,
                    'count' => $items->count(),
                ])->values()->all(),
                'my_reaction' => $message->reactions->firstWhere('actor_key', 'customer')?->emoji,
                'action' => $message->action_url ? [
                    'type' => $message->action_type,
                    'label' => $message->action_label,
                    'url' => $message->action_url,
                ] : null,
        ];
    }

    private function replyTargetId(SupportConversation $conversation, mixed $replyToId): ?int
    {
        if (! $replyToId) {
            return null;
        }

        $replyToId = (int) $replyToId;
        abort_unless($conversation->messages()->whereKey($replyToId)->exists(), 422, 'The message being replied to is no longer available.');

        return $replyToId;
    }

    private function addAssistantReply(SupportConversation $conversation, string $customerMessage): void
    {
        $reply = $this->assistant->reply($customerMessage);
        $assistantMessage = $conversation->messages()->create([
            'sender_type' => SupportMessage::SENDER_ASSISTANT,
            'body' => $reply['body'],
            'action_type' => $reply['action_type'],
            'action_label' => $reply['action_label'],
            'action_url' => $reply['action_url'],
        ]);

        $conversation->forceFill([
            'mode' => $reply['handoff'] ? SupportConversation::MODE_FRONT_DESK : SupportConversation::MODE_ASSISTANT,
            'last_message_at' => $assistantMessage->created_at ?? now(),
            'customer_last_read_message_id' => $assistantMessage->getKey(),
        ])->save();
    }
}
