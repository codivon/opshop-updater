<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * مرجع یکتای تنظیمات «امنیت و امکانات» (گروه‌های security و features در جدول settings).
 *
 * - همهٔ قابلیت‌های این ماژول از پنل `/admin/settings/security` (امنیت و امکانات)
 *   قابل فعال/غیرفعال‌کردن و شخصی‌سازی هستند.
 * - پیش‌فرض‌ها دقیقاً رفتار فعلی سیستم را حفظ می‌کنند (همهٔ لایه‌های امنیتی روشن،
 *   قابلیت‌های جدید مثل نوار اعلان/تعمیر خاموش) → بعد از آپلود هیچ چیز عوض نمی‌شود.
 * - خواندن از helper سراسری setting() (کش ۶۰ ثانیه‌ای)؛ هنگام ذخیره در پنل،
 *   کش هر کلید پاک می‌شود (Cache::forget('setting:'.key)).
 */
class Features
{
    /** پیش‌فرض همهٔ کلیدها — مرجع مشترک پنل و کد */
    public const DEFAULTS = [
        /* -------------------- امنیت (گروه security) -------------------- */

        // ربات‌گیر (کپچای خودمیزبان) — کلید اصلی + نصب روی هر نقطه
        'sec_captcha_enabled'        => '1',
        'sec_captcha_admin_login'    => '1',
        'sec_captcha_admin_register' => '1',
        'sec_captcha_customer_login' => '1',
        'sec_captcha_comments'       => '1',
        // تلهٔ زمان: پاسخ‌دادن زیر این ثانیه‌ها = ربات
        'sec_captcha_min_seconds'    => '2',

        // هانی‌پات (فیلد مخفی website)
        'sec_honeypot_enabled'       => '1',

        // قفل تلاش‌های ناموفق — max: سقف تلاش، decay: پنجرهٔ قفل (دقیقه)
        'sec_lockout_enabled'        => '1',
        'sec_lockout_admin_max'      => '5',
        'sec_lockout_admin_decay'    => '15',
        'sec_lockout_customer_max'   => '8',
        'sec_lockout_customer_decay' => '15',
        'sec_lockout_comment_max'    => '5',
        'sec_lockout_comment_decay'  => '10',

        // هدرهای امنیتی
        'sec_headers_enabled'        => '1',
        'sec_headers_hsts'           => '1',

        // ورود دومرحله‌ای مدیر (کد یک‌بارمصرف)
        'sec_2fa_enabled'            => '0',
        'sec_2fa_channel'            => 'email', // email | sms | both

        // نقش‌های مدیر (اجبار نقش admin برای ورود به پنل)
        'sec_roles_enabled'          => '0',

        /* -------------------- امکانات (گروه features) -------------------- */

        // نوار اعلان فروشگاه
        'feat_announcement_enabled'     => '0',
        'feat_announcement_text'        => '',
        'feat_announcement_bg'          => '#10b981',
        'feat_announcement_color'       => '#ffffff',
        'feat_announcement_link'        => '',
        'feat_announcement_link_text'   => '',
        'feat_announcement_dismissible' => '1',

        // حالت تعمیر و نگهداری
        'feat_maintenance_enabled'  => '0',
        'feat_maintenance_message'  => 'سایت موقتاً در حال بروزرسانی است؛ کمی دیگر دوباره سر بزنید.',

        // صفحهٔ عمومی تغییرات (changelog)
        'feat_changelog_enabled'    => '1',
        'feat_changelog_title'      => 'تغییرات و بروزرسانی‌ها',

        // داشبورد — وضعیت لایسنس‌ها (آستانهٔ «در حال اتمام» به روز)
        'feat_lic_expiry_days'      => '14',

        // داشبورد — وضعیت اشتراک‌ها (آستانهٔ «در حال اتمام» به روز)
        'feat_sub_expiry_days'      => '14',

        // شخصی‌سازی فوتر فروشگاه (خالی = متن‌های پیش‌فرض)
        'feat_footer_text'          => '',
        'feat_footer_description'   => '',
        'feat_social_telegram'      => '',
        'feat_social_instagram'     => '',
        'feat_social_whatsapp'      => '',
        'feat_social_email'         => '',

        // اعلان پیامکی فروش به مدیر (per-area)
        'notify_sale_package'       => '1',
        'notify_sale_product'       => '1',
        'notify_sale_template'      => '1',
        'notify_sale_subscription'  => '1',
    ];

    /** کلیدهای بولی (در پنل به‌صورت toggle ذخیرهٔ 1/0 می‌شوند) */
    public const BOOL_KEYS = [
        'sec_captcha_enabled', 'sec_captcha_admin_login', 'sec_captcha_admin_register',
        'sec_captcha_customer_login', 'sec_captcha_comments', 'sec_honeypot_enabled',
        'sec_lockout_enabled', 'sec_headers_enabled', 'sec_headers_hsts',
        'sec_2fa_enabled', 'sec_roles_enabled',
        'feat_announcement_enabled', 'feat_announcement_dismissible',
        'feat_maintenance_enabled', 'feat_changelog_enabled',
        'notify_sale_package', 'notify_sale_product', 'notify_sale_template', 'notify_sale_subscription',
    ];

    /** گروه جدول settings برای هر کلید */
    public static function group(string $key): string
    {
        return str_starts_with($key, 'notify_') || str_starts_with($key, 'feat_')
            ? 'features'
            : 'security';
    }

    /* --------------------------------------------------------------- */
    /*  خواندن                                                          */
    /* --------------------------------------------------------------- */

    protected static function raw(string $key): string
    {
        return (string) setting($key, self::DEFAULTS[$key] ?? '');
    }

    protected static function flag(string $key): bool
    {
        return filter_var(self::raw($key), FILTER_VALIDATE_BOOLEAN);
    }

    /* --------------------------------------------------------------- */
    /*  امنیت                                                            */
    /* --------------------------------------------------------------- */

    /** کلید اصلی ربات‌گیر */
    public static function captchaOn(): bool
    {
        return self::flag('sec_captcha_enabled');
    }

    /**
     * آیا روی این نقطه کپچا فعال است؟
     * $spot: admin_login | admin_register | customer_login | comments
     */
    public static function captcha(string $spot): bool
    {
        return self::captchaOn() && self::flag('sec_captcha_'.$spot);
    }

    public static function honeypot(): bool
    {
        return self::flag('sec_honeypot_enabled');
    }

    public static function lockout(): bool
    {
        return self::flag('sec_lockout_enabled');
    }

    /**
     * [سقف تلاش، پنجره به دقیقه] برای هر نقطه؛
     * وقتی قفل خاموش است سقف عملاً نامحدود برمی‌گردد (کد فراخوان بدون شرط کار می‌کند).
     * $spot: admin_login | customer_login | comment
     * (کلیدهای settings فقط admin/customer/comment دارند — نگاشت اینجا انجام می‌شود)
     */
    public static function lockoutConfig(string $spot): array
    {
        $key = match ($spot) {
            'admin_login'    => 'admin',
            'customer_login' => 'customer',
            default          => $spot,
        };

        $max   = max(1, (int) self::raw('sec_lockout_'.$key.'_max'));
        $decay = max(1, (int) self::raw('sec_lockout_'.$key.'_decay'));

        return self::lockout() ? [$max, $decay] : [1_000_000, 1];
    }

    public static function headers(): bool
    {
        return self::flag('sec_headers_enabled');
    }

    public static function hsts(): bool
    {
        return self::flag('sec_headers_hsts');
    }

    public static function twofa(): bool
    {
        return self::flag('sec_2fa_enabled');
    }

    /** email | sms | both */
    public static function twofaChannel(): string
    {
        $channel = self::raw('sec_2fa_channel');

        return in_array($channel, ['email', 'sms', 'both'], true) ? $channel : 'email';
    }

    public static function roles(): bool
    {
        return self::flag('sec_roles_enabled');
    }

    /* --------------------------------------------------------------- */
    /*  امکانات                                                          */
    /* --------------------------------------------------------------- */

    public static function announcement(): bool
    {
        return self::flag('feat_announcement_enabled');
    }

    /** داده‌های نوار اعلان فروشگاه (رنگ‌ها sanitize می‌شوند؛ متن escaped در ویو) */
    public static function announcementData(): array
    {
        $text  = trim(self::raw('feat_announcement_text'));
        $link  = trim(self::raw('feat_announcement_link'));
        $bg    = self::hex(self::raw('feat_announcement_bg'), '#10b981');
        $color = self::hex(self::raw('feat_announcement_color'), '#ffffff');

        // تغییر متن/رنگ → هش جدید → نوار برای کسانی که بسته بودند دوباره پیدا می‌شود
        $hash = md5($text.'|'.$link.'|'.$bg.'|'.$color);

        return [
            'enabled'     => self::announcement(),
            'text'        => $text,
            'link'        => $link,
            'link_text'   => trim(self::raw('feat_announcement_link_text')),
            'bg'          => $bg,
            'color'       => $color,
            'dismissible' => self::flag('feat_announcement_dismissible'),
            'hash'        => $hash,
        ];
    }

    public static function maintenance(): bool
    {
        return self::flag('feat_maintenance_enabled');
    }

    public static function maintenanceMessage(): string
    {
        return self::raw('feat_maintenance_message') ?: (string) self::DEFAULTS['feat_maintenance_message'];
    }

    public static function changelog(): bool
    {
        return self::flag('feat_changelog_enabled');
    }

    public static function changelogTitle(): string
    {
        return trim(self::raw('feat_changelog_title')) ?: (string) self::DEFAULTS['feat_changelog_title'];
    }

    public static function footerText(): string
    {
        return trim(self::raw('feat_footer_text'));
    }

    public static function footerDescription(): string
    {
        return trim(self::raw('feat_footer_description'));
    }

    /**
     * شبکه‌های اجتماعی فوتر — فقط مقادیر پُر و معتبر؛
     * هر آیتم: ['icon' => ..., 'title' => ..., 'url' => ...]
     */
    public static function socialLinks(): array
    {
        $links = [];

        $map = [
            'telegram'  => ['icon' => 'send',           'title' => 'تلگرام'],
            'instagram' => ['icon' => 'instagram',      'title' => 'اینستاگرام'],
            'whatsapp'  => ['icon' => 'message-circle', 'title' => 'واتس‌اپ'],
        ];

        foreach ($map as $key => $meta) {
            $url = trim(self::raw('feat_social_'.$key));

            // فقط http(s) — جلوگیری از javascript:/data: در href
            if ($url !== '' && Str::startsWith($url, ['http://', 'https://'])) {
                $links[] = $meta + ['url' => $url];
            }
        }

        $email = filter_var(trim(self::raw('feat_social_email')), FILTER_VALIDATE_EMAIL);

        if ($email) {
            $links[] = ['icon' => 'mail', 'title' => 'ایمیل', 'url' => 'mailto:'.$email];
        }

        return $links;
    }

    /**
     * اعلان پیامکی فروش به مدیر برای هر بخش روشن است؟
     * $area: package | product | template | subscription
     */
    public static function saleNotify(string $area): bool
    {
        return self::flag('notify_sale_'.$area);
    }

    /** نام نمایشی سایت (site_name پنل؛ خالی → نام پیش‌فرض) */
    public static function siteName(): string
    {
        return trim((string) setting('site_name', '')) ?: 'آپدیت‌سنتر';
    }

    /* --------------------------------------------------------------- */

    /** فقط #RGB/#RRGGBB/#RRGGBBAA — وگرنه مقدار پیش‌فرض */
    protected static function hex(string $value, string $fallback): string
    {
        return preg_match('/^#[0-9a-fA-F]{3,8}$/', trim($value)) === 1
            ? trim($value)
            : $fallback;
    }
}
