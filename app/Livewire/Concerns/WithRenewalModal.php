<?php

namespace App\Livewire\Concerns;

use App\Services\RenewalService;

/**
 * مودال مدرن «محدودیت خرید مجدد» — روی هر کامپوننت فروشگاه که خرید پکیج/طرح دارد:
 *
 *   use WithRenewalModal;
 *
 *   buy():  $renewal = app(RenewalService::class)->evaluatePackageRenewal(...);
 *           if (! $renewal['allowed']) { $this->openRenewalModal($renewal, 'package'); return; }
 *
 *   ویو:    <x-renewal-modal />
 */
trait WithRenewalModal
{
    /** نمایش مودال محدودیت خرید مجدد */
    public bool $showRenewalModal = false;

    /** داده‌های نمایش مودال: state/title/message/days/plan/activate_at */
    public array $renewalModalData = [];

    /**
     * ساخت دادهٔ مودال از خروجی evaluatePackageRenewal / evaluateSubscriptionRenewal
     * و باز کردن آن.
     *
     * @param  array{message?: string, reason?: ?string, days_remaining?: ?int,
     *              active_license?: ?object, active_subscription?: ?object,
     *              activate_at?: ?string}  $renewal
     * @param  'package'|'plan'  $kind
     */
    public function openRenewalModal(array $renewal, string $kind = 'package'): void
    {
        $reason = $renewal['reason'] ?? 'window_closed';

        $state = match ($reason) {
            'reserved_active', 'reserved_payment' => 'reserved',
            'unlimited' => 'unlimited',
            'pending_order' => 'pending',
            default => 'blocked',
        };

        $titles = [
            'package' => [
                'blocked' => 'این پکیج را از قبل دارید',
                'reserved' => 'خرید رزروشده دارید',
                'unlimited' => 'لایسنس نامحدود دارید',
                'pending' => 'خرید رزروشده در انتظار پرداخت',
            ],
            'plan' => [
                'blocked' => 'این طرح را از قبل دارید',
                'reserved' => 'خرید رزروشده دارید',
                'unlimited' => 'اشتراک نامحدود دارید',
                'pending' => 'درخواست شما در انتظار تأیید است',
            ],
        ];

        /** @var PackageLicense|null $license */
        $license = $renewal['active_license'] ?? null;
        /** @var Subscription|null $subscription */
        $subscription = $renewal['active_subscription'] ?? null;

        $expiresAt = $license?->expires_at ?? $subscription?->effective_expires_at;

        $days = $renewal['days_remaining']
            ?? ($expiresAt ? max(0, (int) floor(now()->diffInDays($expiresAt, false))) : null);

        $plan = null;

        if ($license) {
            // Task 89: اگر لایسنس از طریق اشتراک صادر شده، برچسب «اشتراک «X»» نمایش داده می‌شود
            $plan = app(RenewalService::class)->licenseSourceLabel($license);
        } elseif ($subscription) {
            $plan = $subscription->plan?->name ?? 'طرح فعلی';
        }

        $activateAt = $renewal['activate_at']
            ?? ($expiresAt ? $expiresAt->format('Y-m-d H:i:s') : null);

        $this->renewalModalData = [
            'state' => $state,
            'title' => $titles[$kind][$state] ?? ($kind === 'package' ? 'امکان خرید مجدد فعلاً وجود ندارد' : 'امکان خرید مجدد این طرح فعلاً وجود ندارد'),
            'message' => $renewal['message'] ?? '',
            'days' => $state === 'blocked' ? $days : null,
            'plan' => $state === 'blocked' ? $plan : null,
            'activate_at' => in_array($state, ['reserved', 'pending'], true) ? $activateAt : null,
        ];

        $this->showRenewalModal = true;
    }

    /** بستن مودال محدودیت خرید مجدد */
    public function closeRenewalModal(): void
    {
        $this->showRenewalModal = false;
        $this->renewalModalData = [];
    }

    /**
     * باز کردن مودال از روی «بنر وضعیت» صفحه (pageNoticeForPackage / pageNoticeForPlan) —
     * دکمهٔ قفل‌شدهٔ خرید با کلیک، همین را نشان می‌دهد تا پیام محدودیت همیشه
     * با مودال زیبا دیده شود.
     */
    public function openRenewalModalFromNotice(array $notice, string $kind = 'package'): void
    {
        $state = $notice['state'] === 'reserved' ? 'reserved' : 'blocked';

        $this->renewalModalData = [
            'state' => $state,
            'title' => $state === 'reserved'
                ? 'خرید رزروشده دارید'
                : ($kind === 'package' ? 'این پکیج را از قبل دارید' : 'این طرح را از قبل دارید'),
            'message' => $notice['message'] ?? '',
            'days' => $state === 'blocked' ? ($notice['days'] ?? null) : null,
            'plan' => $state === 'blocked' ? ($notice['plan'] ?? null) : null,
            'activate_at' => $state === 'reserved' ? ($notice['activate_at'] ?? null) : null,
        ];

        $this->showRenewalModal = true;
    }
}
