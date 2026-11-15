<?php

namespace App\Livewire\Settings;

use App\Livewire\Concerns\WithToasts;
use App\Services\LicenseSigningKeyService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * پنل «کلید امضای لایسنس» (/admin/settings/license-key):
 * ساخت/تعویض جفت‌کلید EC P-256 و پاک‌سازی کش پیکربندی — بدون نیاز به شِل،
 * مخصوص هاست‌های اشتراکی. خطای
 *   «نصب پکیج ناموفق بود: کلید امضای لایسنس (PACKAGE_LICENSE_SIGNING_KEY) تنظیم نشده است.»
 * از همین صفحه با یک دکمه قابل رفع است.
 *
 * نکتهٔ امنیتی: کلید خصوصی هرگز به کلاینت نمی‌رود — فقط وضعیت، اثرانگشت و
 * کلید عمومی (که در فاز ۲ سخت‌سازی در کد فروشگاه جاسازی می‌شود) نمایش داده می‌شود.
 */
#[Layout('components.layouts.app')]
#[Title('کلید امضای لایسنس')]
class LicenseKey extends Component
{
    use WithToasts;

    /** وضعیت جاری از سرویس (منبع حقیقت: خود فایل .env) */
    public array $status = [];

    /** کلید عمومیِ تازه‌ساخته‌شده — فقط بلافاصله پس از ساخت/تعویض برای بکاپ نمایش داده می‌شود */
    public ?string $lastPublicKey = null;

    public ?string $lastFingerprint = null;

    public bool $confirmGenerate = false;

    public bool $confirmRotate = false;

    public bool $confirmClearCache = false;

    public function mount(): void
    {
        $this->refreshStatus();
    }

    /** ساخت کلید جدید (وقتی کلیدی وجود ندارد) */
    public function generate(): void
    {
        $this->confirmGenerate = false;

        $this->finishAction(app(LicenseSigningKeyService::class)->generate(false));
    }

    /** تعویض کلید موجود — پاکت‌های امضاشده با کلید قبلی بی‌اعتبار می‌شوند */
    public function rotate(): void
    {
        $this->confirmRotate = false;

        $this->finishAction(app(LicenseSigningKeyService::class)->generate(true));
    }

    /**
     * پاک‌سازی دستی کش پیکربندی — برای وقتی مدیر .env را از File Manager
     * ویرایش کرده و معادل artisan config:clear را لازم دارد.
     */
    public function clearConfigCache(): void
    {
        $this->confirmClearCache = false;

        $ok = app(LicenseSigningKeyService::class)->clearConfigCache();
        $this->refreshStatus();

        $this->toast(
            $ok
                ? 'کش پیکربندی پاک شد — از این به بعد .env مستقیم خوانده می‌شود.'
                : 'پاک‌سازی کش ناموفق بود؛ لاگ سیستم را بررسی کنید.',
            $ok ? 'success' : 'error'
        );
    }

    public function render()
    {
        return view('livewire.settings.license-key');
    }

    /* ------------------------------------------------------------------ */
    /*  Private helpers                                                    */
    /* ------------------------------------------------------------------ */

    private function finishAction(array $result): void
    {
        $this->refreshStatus();

        if ($result['ok'] ?? false) {
            $this->lastPublicKey   = $result['public_key'] ?? null;
            $this->lastFingerprint = $result['fingerprint'] ?? null;

            $message = (string) ($result['message'] ?? 'انجام شد.');

            if (($result['config_cache_cleared'] ?? true) === false) {
                $message .= ' (پاک‌سازی کش پیکربندی ناموفق بود — از دکمهٔ «پاک‌سازی کش پیکربندی» دستی انجام دهید.)';
            }

            $this->toast($message, 'success');
        } else {
            $this->toast((string) ($result['message'] ?? 'عملیات ناموفق بود.'), 'error');
        }
    }

    private function refreshStatus(): void
    {
        $this->status = app(LicenseSigningKeyService::class)->status();
    }
}
