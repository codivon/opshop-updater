<?php

namespace App\Livewire\Concerns;

use App\Services\CaptchaService;
use App\Services\IranIpGuard;
use App\Support\Features;

/**
 * گارد پرداخت فروشگاه — کپچای فرم پرداخت + گارد IP ایران (ضد فیلترشکن/VPN).
 *
 * روی هر کامپوننت Livewire که دکمهٔ پرداخت دارد نصب می‌شود:
 *
 *   use WithPaymentGuard;
 *
 *   mount():      $this->initPaymentCaptcha();
 *   pay()/buy():  if (! $this->verifyPaymentCaptcha()) return;   ← ابتدای اکشن
 *                 ... مسیرهای رایگان (بدون درگاه) ...
 *                 if (! $this->ensurePaymentIpAllowed()) return; ← فقط مسیر انتقال به درگاه
 *
 *   ویو:          <x-captcha spot="checkout" wireTarget="captcha" refreshWire="refreshCaptcha" />
 *                 <x-vpn-guard-modal wireModel="showVpnModal" retryMethod="pay" />
 */
trait WithPaymentGuard
{
    /** توکن/پاسخ کپچای فرم پرداخت (تصویر یا ویجت ابری) */
    public string $captcha = '';

    /** نمایش مودال «فیلترشکن/VPN شناسایی شد» */
    public bool $showVpnModal = false;

    /** پیام فارسی دلیل بسته‌شدن پرداخت (نمایش در مودال) */
    public ?string $vpnBlockReason = null;

    /** ساخت سؤال تازهٔ کپچای پرداخت در mount */
    public function initPaymentCaptcha(): void
    {
        if (Features::captcha('checkout')) {
            CaptchaService::fresh('checkout');
        }
    }

    /** سؤال دیگر — دکمهٔ تازه‌سازی کپچا */
    public function refreshCaptcha(): void
    {
        CaptchaService::fresh('checkout');
        $this->reset('captcha');
    }

    /**
     * راستی‌آزمایی کپچای پرداخت — ناموفق: خطا زیر فیلد + سؤال تازه.
     * true یعنی مجاز به ادامه.
     */
    public function verifyPaymentCaptcha(): bool
    {
        if (! Features::captcha('checkout')) {
            return true;
        }

        if (CaptchaService::verify($this->captcha, 'checkout')) {
            return true;
        }

        $this->reset('captcha');
        $this->addError('captcha', 'پاسخ سؤال امنیتی نادرست است؛ سؤال جدید آماده شد.');

        return false;
    }

    /**
     * گارد IP ایران برای انتقال به درگاه — در مسیر پرداختِ واقعی
     * (رایگان/کوپنِ کامل که به درگاه نمی‌رود، نباید بسته شود).
     * مسدود → مودال زیبای هشدار فیلترشکن + پیام.
     * true یعنی مجاز به ادامهٔ انتقال به درگاه.
     */
    public function ensurePaymentIpAllowed(): bool
    {
        if (! Features::paymentIpGuard()) {
            $this->showVpnModal = false;
            $this->vpnBlockReason = null;

            return true;
        }

        $verdict = IranIpGuard::check((string) request()->ip());

        if ($verdict['ok']) {
            $this->showVpnModal = false;
            $this->vpnBlockReason = null;

            return true;
        }

        $this->vpnBlockReason = $verdict['reason'] ?? 'پرداخت از این IP مجاز نیست.';
        $this->showVpnModal = true;

        return false;
    }

    /** بستن مودال هشدار فیلترشکن */
    public function closeVpnModal(): void
    {
        $this->showVpnModal = false;
    }
}
