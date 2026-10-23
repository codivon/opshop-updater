<?php

namespace App\Services\Sms;

use App\Models\SmsLog;
use App\Models\SmsTemplate;
use App\Services\Sms\Contracts\SmsDriver;
use App\Services\Sms\Drivers\FarazSmsDriver;
use App\Services\Sms\Drivers\IdehPardazanDriver;
use App\Services\Sms\Drivers\IppanelDriver;
use App\Services\Sms\Drivers\KavenegarDriver;
use App\Services\Sms\Drivers\MelipayamakDriver;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * مدیر پیامک — نقطه ورود یکتای ارسال پیامک در کل برنامه
 *
 *  SmsManager::send('purchase_paid', $customer->phone, [
 *      'customer_name' => '…', 'package_name' => '…', …
 *  ]);
 *
 *  ویژگی‌ها:
 *   - هرگز Exception به بیرون نمی‌دهد (خرید/فعال‌سازی خراب نمی‌شود)
 *   - قالب از جدول sms_templates خوانده و با متغیرها رندر می‌شود
 *   - اگر کد پترنِ درایورِ فعال برای قالب ثبت شده باشد → ارسال پترنی
 *     (استاندارد خطوط خدماتی) وگرنه ارسال متن خام (اگر درایور پشتیبانی کند)
 *   - همه ارسال‌ها در sms_logs ثبت می‌شوند (sent/failed)
 */
class SmsManager
{
    /** @var array<string, class-string<SmsDriver>> */
    public const DRIVER_MAP = [
        'kavenegar'    => KavenegarDriver::class,
        'melipayamak'  => MelipayamakDriver::class,
        'ippanel'      => IppanelDriver::class,
        'farazsms'     => FarazSmsDriver::class,
        'idehpardazan' => IdehPardazanDriver::class,
    ];

    /**
     * ارسال پیامک بر اساس کلید قالب
     *
     * @param  string      $templateKey کلید قالب (مثلاً purchase_paid)
     * @param  string|null $mobile      شماره موبایل (خودکار نرمال می‌شود)
     * @param  array       $vars        مقادیر متغیرها (name => value)
     * @param  object|null $loggable    مدل مرتبط (خرید/اشتراک) برای لاگ
     * @param  bool        $once        فقط یک‌بار برای این مدل ارسال شود (تکرار کال‌بک → بدون پیامک مجدد)
     * @return SmsLog|null رکورد لاگ (یا null اگر ارسالی رخ نداد)
     */
    public function send(string $templateKey, ?string $mobile, array $vars = [], ?object $loggable = null, bool $once = false): ?SmsLog
    {
        try {
            // ۰) ارسال یکتا: اگر قبلاً موفق ارسال شده → رد شو
            if ($once && $loggable !== null && SmsLog::query()
                ->where('template_key', $templateKey)
                ->where('loggable_type', $loggable->getMorphClass())
                ->where('loggable_id', $loggable->getKey())
                ->where('status', SmsLog::STATUS_SENT)
                ->exists()
            ) {
                return null;
            }

            // ۱) فعال بودن سیستم
            if (!filter_var(setting('sms_enabled', '0'), FILTER_VALIDATE_BOOLEAN)) {
                return null;
            }

            // ۲) شماره معتبر
            $mobile = normalize_mobile($mobile);
            if (!$mobile) {
                return $this->log(
                    mobile: (string) ($mobile ?? ''),
                    templateKey: $templateKey,
                    message: null,
                    patternCode: null,
                    status: SmsLog::STATUS_SKIPPED,
                    response: null,
                    error: 'شماره موبایل مشتری معتبر نیست.',
                    loggable: $loggable,
                );
            }

            // ۳) قالب فعال
            $template = SmsTemplate::query()->where('key', $templateKey)->first();
            if (!$template || !$template->is_active) {
                return null; // قالب ناموجود/غیرفعال → بی‌صدا رد شو
            }

            // ۴) رندر متن
            $siteName = (string) ($vars['site_name'] ?? setting('site_name', config('app.name')));
            $vars['site_name'] ??= $siteName;

            $rendered = $template->render($vars);

            // ۵) درایور فعال
            $driver = $this->resolveDriver();

            if ($driver === null) {
                return $this->log(
                    mobile: $mobile,
                    templateKey: $templateKey,
                    message: $rendered,
                    patternCode: null,
                    status: SmsLog::STATUS_FAILED,
                    response: null,
                    error: 'درایور پیامک معتبر نیست؛ تنظیمات را بررسی کنید.',
                    loggable: $loggable,
                );
            }

            if (!$driver->isConfigured()) {
                return $this->log(
                    mobile: $mobile,
                    templateKey: $templateKey,
                    message: $rendered,
                    patternCode: null,
                    status: SmsLog::STATUS_FAILED,
                    response: null,
                    error: $driver->missingConfigHint(),
                    loggable: $loggable,
                );
            }

            // ۶) انتخاب روش ارسال: پترن (اولویت) → متن خام
            $patternCode = $template->patternCode($driver->name());

            // مقادیر به ترتیب متغیرهای تعریف‌شده قالب
            $ordered = [];
            foreach ((array) $template->variables as $name) {
                $ordered[$name] = (string) ($vars[$name] ?? '');
            }

            if ($patternCode !== null) {
                $result = $driver->sendPattern($mobile, $patternCode, $ordered, $rendered);
            } elseif ($driver->supportsText()) {
                $result = $driver->sendText($mobile, $rendered);
            } else {
                return $this->log(
                    mobile: $mobile,
                    templateKey: $templateKey,
                    message: $rendered,
                    patternCode: null,
                    status: SmsLog::STATUS_FAILED,
                    response: null,
                    error: 'این درایور فقط ارسال پترنی دارد؛ کد پترن «' . $driver->name() . '» را در تنظیمات قالب وارد کنید.',
                    loggable: $loggable,
                );
            }

            // ۷) ثبت نتیجه
            return $this->log(
                mobile: $mobile,
                templateKey: $templateKey,
                message: $rendered,
                patternCode: $patternCode,
                status: $result['ok'] ? SmsLog::STATUS_SENT : SmsLog::STATUS_FAILED,
                response: isset($result['response']) ? mb_substr((string) $result['response'], 0, 2000) : null,
                error: $result['ok'] ? null : 'پاسخ سرویس پیامک ناموفق بود.',
                loggable: $loggable,
            );
        } catch (Throwable $e) {
            // خطای غیرمنتظره → فقط لاگ؛ جریان اصلی هرگز نشکند
            Log::warning('SMS send failed', [
                'template' => $templateKey,
                'error'    => $e->getMessage(),
            ]);

            return $this->log(
                mobile: (string) $mobile,
                templateKey: $templateKey,
                message: null,
                patternCode: null,
                status: SmsLog::STATUS_FAILED,
                response: null,
                error: $e->getMessage(),
                loggable: $loggable,
            );
        }
    }

    /**
     * ارسال اطلاع‌رسانی به مدیر (در صورت فعال بودن)
     */
    public function notifyAdmin(string $templateKey, array $vars = [], ?object $loggable = null): ?SmsLog
    {
        if (!filter_var(setting('sms_notify_admin', '0'), FILTER_VALIDATE_BOOLEAN)) {
            return null;
        }

        $adminMobile = normalize_mobile(setting('sms_admin_mobile'));

        if (!$adminMobile) {
            return null;
        }

        return $this->send($templateKey, $adminMobile, $vars, $loggable);
    }

    /**
     * اعلان فروش به مدیر — با کلید جداگانه برای هر بخش تا از پنل
     * «امنیت و امکانات» قابل خاموش/روشن‌کردن باشد:
     *
     *   $area: package | product | template | subscription
     *   (کلیدهای notify_sale_package / notify_sale_product / …)
     */
    public function notifyAdminSale(string $area, string $templateKey, array $vars = [], ?object $loggable = null): ?SmsLog
    {
        if (!\App\Support\Features::saleNotify($area)) {
            return null;
        }

        return $this->notifyAdmin($templateKey, $vars, $loggable);
    }

    /**
     * ارسال آزمایشی (دکمه تست در صفحه قالب‌ها)
     */
    public function sendTest(string $templateKey, string $mobile): ?SmsLog
    {
        $template = SmsTemplate::query()->where('key', $templateKey)->first();

        if (!$template) {
            return null;
        }

        // مقادیر نمونه برای متغیرها
        $sample = [];
        foreach ((array) $template->variables as $name) {
            $sample[$name] = match ($name) {
                'customer_name' => 'کاربر نمونه',
                'package_name'  => 'پکیج نمونه',
                'plan_name'     => 'طرح نمونه',
                'amount'        => '۱۰۰,۰۰۰',
                'license_key'   => 'TEST-XXXX-XXXX',
                'days_left'     => '۳',
                'end_date'      => verta_date(now()->addMonth(), 'Y/m/d') ?? now()->addMonth()->format('Y/m/d'),
                'site_name'     => (string) setting('site_name', config('app.name')),
                default         => 'نمونه',
            };
        }

        return $this->send($templateKey, $mobile, $sample);
    }

    /**
     * درایور فعال از تنظیمات
     */
    public function resolveDriver(): ?SmsDriver
    {
        $name = (string) setting('sms_driver', 'kavenegar');
        $class = self::DRIVER_MAP[$name] ?? null;

        return $class ? app($class) : null;
    }

    /* ------------------------------------------------------------------ */

    private function log(
        string $mobile,
        string $templateKey,
        ?string $message,
        ?string $patternCode,
        string $status,
        ?string $response,
        ?string $error,
        ?object $loggable = null,
    ): ?SmsLog {
        try {
            return SmsLog::create([
                'mobile'       => mb_substr($mobile, 0, 20),
                'template_key' => $templateKey,
                'driver'       => (string) setting('sms_driver', 'kavenegar'),
                'message'      => $message,
                'pattern_code' => $patternCode,
                'status'       => $status,
                'response'     => $response,
                'error'        => $error,
                'loggable_type' => $loggable?->getMorphClass(),
                'loggable_id'  => $loggable?->getKey(),
            ]);
        } catch (Throwable) {
            return null;
        }
    }
}
