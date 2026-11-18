<?php

namespace App\Livewire\Shop;

use App\Livewire\Concerns\WithToasts;
use App\Models\PackageLicense;
use App\Models\SubscriptionOrder;
use App\Services\SubscriptionService;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.shop')]
#[Title('نتیجه خرید اشتراک')]
class SubscriptionResult extends Component
{
    use WithToasts;

    public SubscriptionOrder $order;

    public function mount(SubscriptionOrder $order, string $token): void
    {
        // Task 85 — امنیت: صفحهٔ نتیجهٔ اشتراک (لایسنس‌ها) فقط با توکن محرمانهٔ سفارش باز می‌شود
        abort_unless($order->hasValidAccessToken($token), 404);

        $order->load(['plan:id,name,slug,duration_months,features', 'customer:id,name', 'subscription']);

        // اگر پرداخت هنوز pending است (کال‌بک دیر رسیده) یک‌بار بررسی می‌کنیم
        if ($order->status === SubscriptionOrder::STATUS_PENDING && $order->transaction_id) {
            app(SubscriptionService::class)->verifyPayment($order->transaction_id);
            $order->refresh();
        }

        $this->order = $order;
    }

    #[Computed]
    public function grantedLicenses()
    {
        // پس از تأیید مدیر: لایسنس‌های صادرشده از این سفارش (نکته: notes + meta قابل شناسایی‌اند؛
        // ما لایسنس‌ها را از snapshot پکیج‌های طرح پیدا می‌کنیم)
        if ($this->order->admin_status !== SubscriptionOrder::ADMIN_STATUS_APPROVED) {
            return collect();
        }

        $packageIds = collect($this->order->meta['plan']['packages'] ?? [])->pluck('id')->all();

        if ($packageIds === []) {
            return collect();
        }

        return PackageLicense::query()
            ->with('package:id,name,slug')
            ->where('customer_id', $this->order->customer_id)
            ->whereIn('package_id', $packageIds)
            ->where('status', PackageLicense::STATUS_ACTIVE)
            ->latest('id')
            ->get()
            ->unique('package_id')
            ->values();
    }

    public function recheck(): void
    {
        if (! $this->order->transaction_id) {
            $this->toast('این سفارش تراکنش درگاه ندارد.', 'warning');

            return;
        }

        $result = app(SubscriptionService::class)->verifyPayment($this->order->transaction_id);
        $this->order->refresh();

        $this->toast($result['message'] ?? 'وضعیت به‌روزرسانی شد.', ($result['paid'] ?? false) ? 'success' : 'warning');
    }

    public function render()
    {
        return view('livewire.shop.subscription-result');
    }
}
