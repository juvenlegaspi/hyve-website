<?php

namespace Tests\Feature;

use App\Models\BookingHeader;
use App\Models\SupportConversation;
use App\Models\SupportMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SupportChatTest extends TestCase
{
    use RefreshDatabase;

    public function test_hyve_assistant_replies_instantly_without_notifying_front_desk(): void
    {
        $frontDesk = User::factory()->create(['role' => User::ROLE_FRONT_DESK]);

        $created = $this->postJson(route('support.conversations.store'), [
            'customer_name' => 'AI Customer',
            'email' => 'ai@example.com',
            'message' => 'How much are the room rates?',
            'mode' => SupportConversation::MODE_ASSISTANT,
        ])->assertCreated()
            ->assertJsonPath('mode', SupportConversation::MODE_ASSISTANT)
            ->assertJsonPath('messages.1.sender', SupportMessage::SENDER_ASSISTANT)
            ->assertJsonPath('messages.1.sender_name', 'HYVE Assistant')
            ->assertJsonPath('messages.1.action.label', 'View Rates and Book');

        $this->assertDatabaseCount('support_messages', 2);
        $this->actingAs($frontDesk)
            ->getJson(route('admin.messages.unread'))
            ->assertOk()
            ->assertJsonPath('unread_total', 0);

        $token = (string) $created->json('token');
        $this->postJson(route('support.conversations.message', $token), [
            'message' => 'I want to talk to the front desk.',
        ])->assertOk()
            ->assertJsonPath('mode', SupportConversation::MODE_FRONT_DESK)
            ->assertJsonPath('messages.3.sender', SupportMessage::SENDER_ASSISTANT);

        $this->actingAs($frontDesk)
            ->getJson(route('admin.messages.unread'))
            ->assertOk()
            ->assertJsonPath('unread_total', 1)
            ->assertJsonPath('latest_message.customer_name', 'AI Customer');
    }

    public function test_customer_can_manually_handoff_assistant_conversation_to_front_desk(): void
    {
        $created = $this->postJson(route('support.conversations.store'), [
            'customer_name' => 'Handoff Customer',
            'phone' => '09170000000',
            'message' => 'Hello',
            'mode' => SupportConversation::MODE_ASSISTANT,
        ])->assertCreated();

        $this->postJson(route('support.conversations.handoff', $created->json('token')))
            ->assertOk()
            ->assertJsonPath('mode', SupportConversation::MODE_FRONT_DESK)
            ->assertJsonPath('messages.2.sender', SupportMessage::SENDER_ASSISTANT);
    }

    public function test_customer_can_start_and_continue_a_private_support_conversation(): void
    {
        $created = $this->postJson(route('support.conversations.store'), [
            'customer_name' => 'Jamie Cruz',
            'email' => 'jamie@example.com',
            'phone' => '',
            'message' => 'Is a room available tonight?',
        ])->assertCreated()
            ->assertJsonPath('status', SupportConversation::STATUS_OPEN)
            ->assertJsonPath('messages.0.body', 'Is a room available tonight?');

        $token = (string) $created->json('token');
        $this->assertNotSame('', $token);

        $this->getJson(route('support.conversations.show', $token))
            ->assertOk()
            ->assertJsonPath('customer_name', 'Jamie Cruz');

        $this->postJson(route('support.conversations.message', $token), [
            'message' => 'I need a two-seat office.',
        ])->assertOk()
            ->assertJsonCount(2, 'messages')
            ->assertJsonPath('messages.1.body', 'I need a two-seat office.');

        $this->assertDatabaseCount('support_conversations', 1);
        $this->assertDatabaseCount('support_messages', 2);
    }

    public function test_customer_must_provide_email_or_phone_and_cannot_access_an_invalid_token(): void
    {
        $this->postJson(route('support.conversations.store'), [
            'customer_name' => 'No Contact',
            'message' => 'Please reply.',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'phone']);

        $this->getJson('/support/conversations/not-a-valid-token')->assertNotFound();
    }

    public function test_front_desk_can_read_reply_close_and_reopen_customer_conversation(): void
    {
        $frontDesk = User::factory()->create(['role' => User::ROLE_FRONT_DESK]);
        $created = $this->postJson(route('support.conversations.store'), [
            'customer_name' => 'Alex Guest',
            'phone' => '09171234567',
            'message' => 'Can somebody help me book?',
        ])->assertCreated();
        $token = (string) $created->json('token');
        $conversation = SupportConversation::query()->firstOrFail();
        BookingHeader::query()->create([
            'reference_no' => 'HYVE-CHAT-'.Str::upper(Str::random(8)),
            'customer_name' => 'Alex Guest',
            'email' => 'alex@example.com',
            'phone' => '09171234567',
            'booking_type' => BookingHeader::TYPE_GUEST,
            'source' => BookingHeader::SOURCE_WEB,
            'payment_method' => 'pay_later',
            'payment_status' => 'pending_verification',
            'total_amount' => 160,
            'discounted_total_amount' => 160,
            'downpayment_amount' => 0,
            'balance_amount' => 160,
            'status' => BookingHeader::STATUS_PENDING,
        ]);

        $this->actingAs($frontDesk)
            ->get(route('admin.messages.index'))
            ->assertOk()
            ->assertSee('Customer support');

        $this->actingAs($frontDesk)
            ->getJson(route('admin.messages.unread'))
            ->assertOk()
            ->assertJsonPath('unread_total', 1)
            ->assertJsonPath('latest_message.customer_name', 'Alex Guest');

        $this->actingAs($frontDesk)
            ->getJson(route('admin.messages.feed', ['conversation_id' => $conversation->id]))
            ->assertOk()
            ->assertJsonPath('selected.customer_name', 'Alex Guest')
            ->assertJsonPath('selected.messages.0.body', 'Can somebody help me book?')
            ->assertJsonPath('selected.booking_match.count', 1);

        $this->actingAs($frontDesk)
            ->postJson(route('admin.messages.reply', $conversation), [
                'message' => 'Yes. You can also use the booking link below.',
                'action' => 'booking',
            ])
            ->assertOk()
            ->assertJsonPath('messages.1.sender', SupportMessage::SENDER_ADMIN)
            ->assertJsonPath('messages.1.action.label', 'Book Now')
            ->assertJsonPath('messages.1.is_read', false);

        $this->getJson(route('support.conversations.show', $token))
            ->assertOk()
            ->assertJsonPath('messages.1.body', 'Yes. You can also use the booking link below.')
            ->assertJsonPath('messages.1.action.label', 'Book Now');

        $this->actingAs($frontDesk)
            ->getJson(route('admin.messages.feed', ['conversation_id' => $conversation->id]))
            ->assertOk()
            ->assertJsonPath('selected.messages.1.is_read', true);

        $this->actingAs($frontDesk)
            ->patchJson(route('admin.messages.status', $conversation), ['status' => SupportConversation::STATUS_CLOSED])
            ->assertOk()
            ->assertJsonPath('status', SupportConversation::STATUS_CLOSED);

        $this->postJson(route('support.conversations.message', $token), ['message' => 'Common Area, please.'])
            ->assertOk()
            ->assertJsonPath('status', SupportConversation::STATUS_OPEN);

        $this->actingAs($frontDesk)
            ->deleteJson(route('admin.messages.destroy', $conversation))
            ->assertOk()
            ->assertJsonPath('deleted', true);

        $this->assertDatabaseMissing('support_conversations', ['id' => $conversation->id]);
        $this->assertDatabaseMissing('support_messages', ['support_conversation_id' => $conversation->id]);
        $this->getJson(route('support.conversations.show', $token))->assertNotFound();
    }

    public function test_owner_cannot_access_customer_messages(): void
    {
        $owner = User::factory()->create(['role' => User::ROLE_OWNER]);

        $this->actingAs($owner)->get(route('admin.messages.index'))->assertForbidden();
        $this->assertFalse($owner->hasPermission('messages.view'));
    }

    public function test_admin_can_search_and_filter_support_conversations(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $first = $this->postJson(route('support.conversations.store'), [
            'customer_name' => 'Open Customer',
            'email' => 'open@example.com',
            'message' => 'Open question',
        ])->assertCreated();
        $second = $this->postJson(route('support.conversations.store'), [
            'customer_name' => 'Closed Customer',
            'email' => 'closed@example.com',
            'message' => 'Closed question',
        ])->assertCreated();
        $closedConversation = SupportConversation::query()->where('public_token', $second->json('token'))->firstOrFail();
        $closedConversation->update(['status' => SupportConversation::STATUS_CLOSED]);

        $this->actingAs($admin)
            ->getJson(route('admin.messages.feed', ['search' => 'closed@example.com', 'status' => 'closed']))
            ->assertOk()
            ->assertJsonCount(1, 'conversations')
            ->assertJsonPath('conversations.0.customer_name', 'Closed Customer');

        $this->actingAs($admin)
            ->getJson(route('admin.messages.feed', ['unread' => 1]))
            ->assertOk()
            ->assertJsonCount(1, 'conversations')
            ->assertJsonPath('conversations.0.customer_name', 'Open Customer');
    }
}
