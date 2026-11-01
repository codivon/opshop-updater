<?php

namespace Tests\Feature;

use App\Livewire\Shop\CustomerProfile;
use App\Livewire\Tickets\Index as TicketsIndex;
use App\Models\Subscription;
use App\Models\SubscriptionOrder;
use App\Models\Ticket;
use App\Models\TicketReply;
use App\Services\SubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\MakesShopData;
use Tests\TestCase;

/**
 * سیستم تیکت پشتیبانی — گات «اشتراک فعال»:
 *  - مشتریِ بدون اشتراک فعال نباید بتواند تیکت ثبت/پاسخ بدهد (هم UI هم اکشن)
 *  - مشتریِ دارای اشتراک فعال → تیکت + پاسخ + بستن
 *  - مدیر: پاسخ (وضعیت answered) + تغییر وضعیت + حذف
 */
class TicketTest extends TestCase
{
    use RefreshDatabase, MakesShopData;

    protected SubscriptionService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(SubscriptionService::class);
    }

    private function makePlan(array $attrs = []): \App\Models\SubscriptionPlan
    {
        return \App\Models\SubscriptionPlan::create(array_merge([
            'name'            => 'طرح تیکت تست',
            'slug'            => 'ticket-plan-' . uniqid(),
            'duration_months' => 1,
            'price'           => 0,
            'discount_price'  => 0,
            'is_one_time'     => false,
            'is_active'       => true,
            'sort_order'      => 0,
        ], $attrs));
    }

    /** اشتراک فعال برای مشتری (خرید رایگان + تأیید) */
    private function giveActiveSubscription($customer): Subscription
    {
        $plan = $this->makePlan();

        $this->service->purchase($customer, $plan, null, null);

        /** @var SubscriptionOrder $order */
        $order = SubscriptionOrder::query()
            ->where('customer_id', $customer->id)
            ->latest('id')
            ->first();

        return $this->service->approve($order);
    }

    /* ------------------------------------------------------------------ */
    /*  گات                                                                */
    /* ------------------------------------------------------------------ */

    public function test_gate_blocks_customer_without_subscription(): void
    {
        $customer = $this->makeCustomer();

        $this->assertFalse($this->service->canCreateTicket($customer));
    }

    public function test_gate_allows_customer_with_active_subscription(): void
    {
        $customer = $this->makeCustomer();
        $this->giveActiveSubscription($customer);

        $this->assertTrue($this->service->canCreateTicket($customer));
    }

    public function test_gate_blocks_customer_with_expired_subscription(): void
    {
        $customer = $this->makeCustomer();
        $this->giveActiveSubscription($customer);

        // انقضای اشتراک
        Subscription::query()->where('customer_id', $customer->id)->update([
            'expires_at' => now()->subDay(),
            'end_date'   => now()->subDay()->toDateString(),
        ]);

        $this->assertFalse($this->service->canCreateTicket($customer->refresh()));
    }

    /* ------------------------------------------------------------------ */
    /*  سمت مشتری (پروفایل)                                                 */
    /* ------------------------------------------------------------------ */

    public function test_customer_without_subscription_cannot_create_ticket(): void
    {
        $customer = $this->makeCustomer();

        Livewire::actingAs($customer, 'customer')
            ->test(CustomerProfile::class, ['tab' => 'tickets'])
            ->set('ticketSubject', 'مشکل در نصب پکیج')
            ->set('ticketBody', 'پکیج روی سایت من نصب نمی‌شود و خطا می‌دهد.')
            ->call('createTicket')
            ->assertDispatched('toast'); // پیام گات «اشتراک فعال ندارید»

        $this->assertSame(0, Ticket::count());
    }

    public function test_customer_with_subscription_can_create_ticket(): void
    {
        $customer = $this->makeCustomer();
        $subscription = $this->giveActiveSubscription($customer);

        Livewire::actingAs($customer, 'customer')
            ->test(CustomerProfile::class, ['tab' => 'tickets'])
            ->set('ticketSubject', 'مشکل در نصب پکیج')
            ->set('ticketBody', 'پکیج روی سایت من نصب نمی‌شود و خطا می‌دهد؛ لطفاً راهنمایی کنید.')
            ->call('createTicket')
            ->assertHasNoErrors();

        $this->assertSame(1, Ticket::count());

        $ticket = Ticket::sole();
        $this->assertSame($customer->id, $ticket->customer_id);
        $this->assertSame($subscription->id, $ticket->subscription_id); // اشتراک لحظهٔ ثبت
        $this->assertSame(Ticket::STATUS_OPEN, $ticket->status);
        $this->assertSame(1, $ticket->replies()->count());
        $this->assertSame(TicketReply::AUTHOR_CUSTOMER, $ticket->replies()->sole()->author_type);
    }

    public function test_ticket_creation_validates_input(): void
    {
        $customer = $this->makeCustomer();
        $this->giveActiveSubscription($customer);

        Livewire::actingAs($customer, 'customer')
            ->test(CustomerProfile::class, ['tab' => 'tickets'])
            ->set('ticketSubject', 'کم') // کمتر از ۳ حرف
            ->set('ticketBody', 'کوتاه') // کمتر از ۱۰ حرف
            ->call('createTicket')
            ->assertHasErrors(['ticketSubject', 'ticketBody']);

        $this->assertSame(0, Ticket::count());
    }

    public function test_customer_can_reply_to_own_open_ticket(): void
    {
        $customer = $this->makeCustomer();
        $this->giveActiveSubscription($customer);

        $ticket = Ticket::create([
            'customer_id' => $customer->id,
            'subject'     => 'سؤال درباره لایسنس',
            'status'      => Ticket::STATUS_ANSWERED,
        ]);

        TicketReply::create(['ticket_id' => $ticket->id, 'author_type' => TicketReply::AUTHOR_CUSTOMER, 'body' => 'سؤال اول من']);
        TicketReply::create(['ticket_id' => $ticket->id, 'author_type' => TicketReply::AUTHOR_ADMIN, 'body' => 'پاسخ پشتیبانی']);

        Livewire::actingAs($customer, 'customer')
            ->test(CustomerProfile::class, ['tab' => 'tickets'])
            ->set('replyBody', 'ممنون؛ اما مشکل هنوز باقی است.')
            ->call('replyToTicket', $ticket->id)
            ->assertHasNoErrors();

        $ticket->refresh();
        $this->assertSame(Ticket::STATUS_OPEN, $ticket->status); // پاسخ مشتری → برگشت به انتظار
        $this->assertSame(3, $ticket->replies()->count());
        $this->assertNotNull($ticket->last_reply_at);
    }

    public function test_customer_cannot_reply_to_closed_ticket_or_others_ticket(): void
    {
        $customer = $this->makeCustomer();
        $this->giveActiveSubscription($customer);

        $closed = Ticket::create(['customer_id' => $customer->id, 'subject' => 'تیکت بسته', 'status' => Ticket::STATUS_CLOSED]);

        $other = $this->makeCustomer();
        $othersTicket = Ticket::create(['customer_id' => $other->id, 'subject' => 'تیکت مشتری دیگر', 'status' => Ticket::STATUS_OPEN]);

        Livewire::actingAs($customer, 'customer')
            ->test(CustomerProfile::class, ['tab' => 'tickets'])
            ->set('replyBody', 'پاسخ به تیکت بسته')
            ->call('replyToTicket', $closed->id);

        $this->assertSame(0, $closed->replies()->count());

        Livewire::actingAs($customer, 'customer')
            ->test(CustomerProfile::class, ['tab' => 'tickets'])
            ->set('replyBody', 'پاسخ به تیکت دیگران')
            ->call('replyToTicket', $othersTicket->id);

        $this->assertSame(0, $othersTicket->replies()->count());
    }

    public function test_customer_can_close_own_ticket(): void
    {
        $customer = $this->makeCustomer();
        $this->giveActiveSubscription($customer);

        $ticket = Ticket::create(['customer_id' => $customer->id, 'subject' => 'مشکل حل شد', 'status' => Ticket::STATUS_OPEN]);

        Livewire::actingAs($customer, 'customer')
            ->test(CustomerProfile::class, ['tab' => 'tickets'])
            ->call('closeTicket', $ticket->id)
            ->assertHasNoErrors();

        $this->assertSame(Ticket::STATUS_CLOSED, $ticket->refresh()->status);
    }

    /* ------------------------------------------------------------------ */
    /*  سمت مدیر                                                            */
    /* ------------------------------------------------------------------ */

    public function test_admin_reply_sets_answered_status(): void
    {
        $customer = $this->makeCustomer();
        $ticket = Ticket::create(['customer_id' => $customer->id, 'subject' => 'درخواست راهنمایی', 'status' => Ticket::STATUS_OPEN]);
        TicketReply::create(['ticket_id' => $ticket->id, 'author_type' => TicketReply::AUTHOR_CUSTOMER, 'body' => 'لطفاً راهنمایی کنید']);

        Livewire::test(TicketsIndex::class)
            ->call('view', $ticket->id)
            ->set('replyText', 'سلام؛ مشکل را بررسی کردیم — راه‌حل داخل مستندات است.')
            ->call('reply')
            ->assertHasNoErrors();

        $ticket->refresh();
        $this->assertSame(Ticket::STATUS_ANSWERED, $ticket->status);
        $this->assertSame(2, $ticket->replies()->count());
        $this->assertSame(TicketReply::AUTHOR_ADMIN, $ticket->replies->last()->author_type);
    }

    public function test_admin_can_change_status_and_delete(): void
    {
        $customer = $this->makeCustomer();
        $ticket = Ticket::create(['customer_id' => $customer->id, 'subject' => 'تیکت برای تغییر وضعیت', 'status' => Ticket::STATUS_OPEN]);
        TicketReply::create(['ticket_id' => $ticket->id, 'author_type' => TicketReply::AUTHOR_CUSTOMER, 'body' => 'متن']);

        Livewire::test(TicketsIndex::class)
            ->call('setStatus', $ticket->id, Ticket::STATUS_CLOSED);

        $this->assertSame(Ticket::STATUS_CLOSED, $ticket->refresh()->status);

        Livewire::test(TicketsIndex::class)
            ->call('setStatus', $ticket->id, Ticket::STATUS_OPEN);

        $this->assertSame(Ticket::STATUS_OPEN, $ticket->refresh()->status);

        Livewire::test(TicketsIndex::class)
            ->call('delete', $ticket->id);

        $this->assertSame(0, Ticket::count());
        $this->assertSame(0, TicketReply::count()); // cascade
    }

    public function test_admin_records_filter_by_status_and_search(): void
    {
        $customer = $this->makeCustomer();
        $open = Ticket::create(['customer_id' => $customer->id, 'subject' => 'موضوع الف برای فیلتر', 'status' => Ticket::STATUS_OPEN]);
        $closed = Ticket::create(['customer_id' => $customer->id, 'subject' => 'موضوع ب برای فیلتر', 'status' => Ticket::STATUS_CLOSED]);

        Livewire::test(TicketsIndex::class)
            ->set('statusFilter', Ticket::STATUS_OPEN)
            ->assertSee('موضوع الف برای فیلتر')
            ->assertDontSee('موضوع ب برای فیلتر');

        Livewire::test(TicketsIndex::class)
            ->set('search', 'موضوع ب برای فیلتر')
            ->assertSee('موضوع ب برای فیلتر')
            ->assertDontSee('موضوع الف برای فیلتر');
    }
}
