<?php

namespace App\Services;

use Illuminate\Support\Str;

class HyveAssistantService
{
    /** @return array{body:string, action_type:?string, action_label:?string, action_url:?string, handoff:bool} */
    public function reply(string $message): array
    {
        $question = Str::of($message)->lower()->ascii()->replaceMatches('/[^a-z0-9\s]/', ' ')->squish()->toString();

        if ($this->containsAny($question, ['front desk', 'frontdesk', 'staff', 'human', 'admin', 'real person', 'agent', 'representative'])) {
            return $this->answer(
                'Certainly. I am connecting this conversation to the HYVE Front Desk. A staff member can continue assisting you here.',
                handoff: true,
            );
        }

        if (preg_match('/^(hi|hello|hey|good morning|good afternoon|good evening)\b/', $question) === 1) {
            return $this->answer('Hello! I am the HYVE Assistant. I can help with bookings, rooms, rates, operating hours, payments, discounts, location, and other common HYVE questions. What would you like to know?');
        }

        if ($this->containsAny($question, ['thank', 'salamat'])) {
            return $this->answer('You are welcome! If you need anything else, you can ask me here or choose Chat with Front Desk below.');
        }

        if ($this->containsAny($question, ['available', 'availability', 'vacant', 'free room', 'open room'])) {
            return $this->answer(
                'Room availability changes live as bookings are added. Open the booking page, select a room and date, and the available start and end times will appear automatically.',
                'booking',
                'Check Live Availability',
                route('bookings.index'),
            );
        }

        if ($this->containsAny($question, ['book', 'booking', 'reserve', 'reservation'])) {
            return $this->answer(
                'To book, open the booking page, choose your space, select an available date and time or a long-stay plan, then complete the customer and payment details. Walk-in customers may also ask the HYVE Front Desk to create the booking.',
                'booking',
                'Book Now',
                route('bookings.index'),
            );
        }

        if ($this->containsAny($question, ['rate', 'rates', 'price', 'pricing', 'cost', 'how much', 'pila'])) {
            return $this->answer(
                'HYVE rates depend on the selected space, schedule, customer type, and stay period. The live booking page calculates the correct total before checkout, including applicable day, night, daily, weekly, or monthly pricing.',
                'booking',
                'View Rates and Book',
                route('bookings.index'),
            );
        }

        if ($this->containsAny($question, ['room', 'space', 'common area', 'conference', 'cowork', 'office', 'seat'])) {
            return $this->answer(
                'HYVE offers the Common Area and several private or conference-room options. Open live availability to see the current room list, photos, capacity, rates, and open schedules.',
                'booking',
                'View Rooms',
                route('bookings.index'),
            );
        }

        if ($this->containsAny($question, ['hour', 'hours', 'open time', 'closing', 'close', '24 hour', '24/7', 'schedule'])) {
            return $this->answer('HYVE standard booking hours are 8:00 AM to 2:00 AM. Temporary closures, including Sunday closures when active, appear in live availability, so please check the booking page before visiting.');
        }

        if ($this->containsAny($question, ['walk in', 'walk-in', 'walkin'])) {
            return $this->answer('Walk-ins are welcome. The HYVE Front Desk can check current availability, create a walk-in booking, and assist with payment when you arrive.');
        }

        if ($this->containsAny($question, ['pay', 'payment', 'gcash', 'bank', 'cash', 'downpayment', 'balance'])) {
            return $this->answer('Available payment methods may include GCash, bank transfer, Pay Later, and cash for eligible walk-in transactions. The valid options, required downpayment, and instructions appear during checkout.');
        }

        if ($this->containsAny($question, ['discount', 'promo', 'voucher', 'senior', 'pwd', 'student', 'reviewee', 'early bird', 'engagement'])) {
            return $this->answer('HYVE offers eligible promotions such as the 2-hour Common Area voucher, Engagement Discount, Board Exam Reviewee Discount, Early Bird Promo, Senior Citizen discount, and PWD discount. Supporting ID or a promo reference may be required, and the Front Desk verifies eligibility.');
        }

        if ($this->containsAny($question, ['where', 'location', 'address', 'map', 'direction'])) {
            return $this->answer(
                'HYVE is located at 10F The Space Building, A.S. Fortuna Street, Mandaue City.',
                'location',
                'Open Directions',
                (string) config('hyve.contact.map_url', route('home').'#contact'),
            );
        }

        if ($this->containsAny($question, ['wifi', 'wi-fi', 'internet', 'voucher code'])) {
            return $this->answer('Wi-Fi access is prepared for eligible confirmed bookings. If you need help with a voucher or connection, please choose Chat with Front Desk so staff can check your booking and device.');
        }

        if ($this->containsAny($question, ['cancel', 'cancellation', 'reschedule', 'move booking', 'change date', 'change time', 'extend', 'extension'])) {
            return $this->answer('Booking changes depend on the booking status, room availability, and possible time conflicts. Members can review eligible actions in My Bookings; otherwise, choose Chat with Front Desk for assistance.');
        }

        if ($this->containsAny($question, ['member', 'membership', 'login', 'account', 'register', 'sign up'])) {
            return $this->answer('HYVE members can sign in to view their dashboard, bookings, balances, live rooms, events, and announcements. New customers may create an account from the website registration page.');
        }

        if ($this->containsAny($question, ['event', 'events', 'announcement', 'announcements', 'holiday'])) {
            return $this->answer('Current HYVE events and announcements are shown in the member dashboard. Logged-in members also receive announcement notifications when new updates are published.');
        }

        if ($this->containsAny($question, ['contact', 'phone', 'email', 'call'])) {
            $phone = (string) config('hyve.contact.phone_display', '09706240749');
            $email = (string) config('hyve.contact.email', 'frontdesk@hyvecoworkingspace.com');

            return $this->answer("You may contact the HYVE Front Desk at {$phone} or {$email}. You can also choose Chat with Front Desk below to continue here.");
        }

        return $this->answer('I could not find a verified HYVE answer for that question. Please rephrase it, or choose Chat with Front Desk below and a staff member will assist you.');
    }

    private function containsAny(string $question, array $phrases): bool
    {
        foreach ($phrases as $phrase) {
            if (str_contains($question, $phrase)) {
                return true;
            }
        }

        return false;
    }

    /** @return array{body:string, action_type:?string, action_label:?string, action_url:?string, handoff:bool} */
    private function answer(
        string $body,
        ?string $actionType = null,
        ?string $actionLabel = null,
        ?string $actionUrl = null,
        bool $handoff = false,
    ): array {
        return [
            'body' => $body,
            'action_type' => $actionType,
            'action_label' => $actionLabel,
            'action_url' => $actionUrl,
            'handoff' => $handoff,
        ];
    }
}
