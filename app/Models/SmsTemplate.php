<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * قالب پیامک — متن + متغیرها + کد پترن هر درایور
 */
class SmsTemplate extends Model
{
    protected $fillable = [
        'key', 'title', 'body', 'variables', 'pattern_codes', 'is_active',
    ];

    protected $casts = [
        'variables' => 'array',
        'pattern_codes' => 'array',
        'is_active' => 'boolean',
    ];

    /** نام درایورهای پشتیبانی‌شده (برای UI و ارسال) */
    public const DRIVERS = [
        'kavenegar' => 'کاوه‌نگار',
        'melipayamak' => 'ملی‌پیامک',
        'ippanel' => 'آی‌پی‌پنل',
        'farazsms' => 'فراز اس‌ام‌اس',
        'idehpardazan' => 'ایده پردازان',
    ];

    /** کلیدهای قالب سیستمی (غیرقابل حذف) */
    public const SYSTEM_KEYS = [
        'purchase_paid', 'subscription_paid', 'subscription_activated',
        'subscription_renewed', 'subscription_expiring', 'subscription_expired',
        'package_reserved', 'package_renewal_activated', 'subscription_reserved',
    ];

    /** کد پترن برای یک درایور مشخص */
    public function patternCode(string $driver): ?string
    {
        $value = $this->pattern_codes[$driver] ?? null;

        return $value !== null && trim((string) $value) !== '' ? trim((string) $value) : null;
    }

    /** رندر متن با مقادیر متغیرها */
    public function render(array $vars = []): string
    {
        $text = (string) $this->body;
        $names = is_array($this->variables) ? $this->variables : [];

        foreach ($names as $name) {
            $text = str_replace('{'.$name.'}', (string) ($vars[$name] ?? ''), $text);
        }

        return $text;
    }
}
