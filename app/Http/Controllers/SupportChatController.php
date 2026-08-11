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

    public function show(string $publicToken): JsonResponse
    {
        $conversation = $this->findPublicConversation($publicToken);
        $conversation->forceFill([
            'customer_last_read_at' => now(),
            'customer_last_read_message_id' => $conversation->messages()->max('id'),
        ])->save();

        return response()->json($this->customerPayload($conversation));
    }

    public function message(Request $request, string $publicToken): JsonResponse
    {
        $validated = $request->validate([
            'message' => ['required', 'string', 'max:2000'],
        ]);
        $conversation = $this->findPublicConversation($publicToken);

        DB::transaction(function () use ($request, $conversation, $validated): void {
            $now = now();
            $message = $conversation->messages()->create([
                'sender_type' => SupportMessage::SENDER_CUSTOMER,
                'sender_user_id' => $request->user()?->getKey(),
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
                $conversation->messages()->create([
                    'sender_type' => SupportMessage::SENDER_ASSISTANT,
                    'body' => 'Your conversation has been sent to the HYVE Front Desk. A staff member can reply here as soon as they are available.',
                ]);
                $conversation->forceFill([
                    'mode' => SupportConversation::MODE_FRONT_DESK,
                    'status' => SupportConversation::STATUS_OPEN,
                    'last_message_at' => $now,
                ])->save();
            });
        }

        return response()->json($this->customerPayload($conversation->fresh()));
    }

    private function findPublicConversation(string $publicToken): SupportConversation
    {
        abort_unless(Str::isUuid($publicToken), 404);

        return SupportConversation::query()->where('public_token', $publicToken)->firstOrFail();
    }

    /** @return array<string, mixed> */
    private function customerPayload(SupportConversation $conversation): array
    {
        $messages = $conversation->messages()
            ->with('senderUser:id,first_name,last_name')
            ->latest('id')
            ->limit(100)
            ->get()
            ->reverse()
            ->values();

        return [
            'token' => $conversation->public_token,
            'status' => $conversation->status,
            'mode' => $conversation->mode,
            'customer_name' => $conversation->customer_name,
            'messages' => $messages->map(fn (SupportMessage $message): array => [
                'id' => $message->getKey(),
                'sender' => $message->sender_type,
                'sender_name' => match ($message->sender_type) {
                    SupportMessage::SENDER_ADMIN => $message->senderUser?->name ?: 'HYVE Front Desk',
                    SupportMessage::SENDER_ASSISTANT => 'HYVE Assistant',
                    default => $conversation->customer_name,
                },
                'body' => $message->body,
                'created_at' => optional($message->created_at)->format('M j, g:i A'),
                'action' => $message->action_url ? [
                    'type' => $message->action_type,
                    'label' => $message->action_label,
                    'url' => $message->action_url,
                ] : null,
            ])->all(),
            'poll_url' => route('support.conversations.show', $conversation->public_token),
            'message_url' => route('support.conversations.message', $conversation->public_token),
            'handoff_url' => route('support.conversations.handoff', $conversation->public_token),
        ];
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
