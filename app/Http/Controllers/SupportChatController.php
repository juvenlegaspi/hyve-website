<?php

namespace App\Http\Controllers;

use App\Models\SupportConversation;
use App\Models\SupportMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SupportChatController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'customer_name' => ['required', 'string', 'max:120'],
            'email' => ['nullable', 'required_without:phone', 'email:rfc', 'max:190'],
            'phone' => ['nullable', 'required_without:email', 'string', 'max:40'],
            'message' => ['required', 'string', 'max:2000'],
        ]);

        $conversation = DB::transaction(function () use ($request, $validated): SupportConversation {
            $now = now();
            $conversation = SupportConversation::query()->create([
                'public_token' => (string) Str::uuid(),
                'user_id' => $request->user()?->getKey(),
                'customer_name' => trim($validated['customer_name']),
                'email' => trim((string) ($validated['email'] ?? '')) ?: null,
                'phone' => trim((string) ($validated['phone'] ?? '')) ?: null,
                'status' => SupportConversation::STATUS_OPEN,
                'last_message_at' => $now,
                'customer_last_read_at' => $now,
            ]);

            $message = $conversation->messages()->create([
                'sender_type' => SupportMessage::SENDER_CUSTOMER,
                'sender_user_id' => $request->user()?->getKey(),
                'body' => trim($validated['message']),
            ]);
            $conversation->forceFill(['customer_last_read_message_id' => $message->getKey()])->save();

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
        });

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
            'customer_name' => $conversation->customer_name,
            'messages' => $messages->map(fn (SupportMessage $message): array => [
                'id' => $message->getKey(),
                'sender' => $message->sender_type,
                'sender_name' => $message->sender_type === SupportMessage::SENDER_ADMIN
                    ? ($message->senderUser?->name ?: 'HYVE Front Desk')
                    : $conversation->customer_name,
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
        ];
    }
}
