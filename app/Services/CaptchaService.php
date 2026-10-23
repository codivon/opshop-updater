<?php

namespace App\Services;

/**
 * کپچای خودمیزبان — بدون سرویس خارجی (گوگل/hCaptcha روی اینترنت ایران ناپایدارند):
 *
 *   ۱) سؤال ریاضی ساده با ارقام فارسی (مثل «۴ + ۳») — پاسخ فقط در سشن سرور
 *   ۲) مصرف یک‌بارمصرف: هر بار verify سؤال تازه ساخته می‌شود (حتی اگر پاسخ درست باشد)
 *   ۳) تلهٔ زمان: پاسخ‌دادن زیر ۲ ثانیه = ربات (انسان باید سؤال را بخواند و تایپ کند)
 *   ۴) انقضا: سؤال بیش از ۳۰ دقیقه اعتبار ندارد (سشن بازِ رهاشده)
 *
 * در فرم‌های عمومی جفت هانی‌پات (فیلد مخفی website) هم هست: ربات‌های ساده
 * همهٔ inputها را پر می‌کنند و قبل از رسیدن به کپچا رد می‌شوند.
 */
class CaptchaService
{
    public const SESSION_QUESTION = 'captcha.question';
    public const SESSION_ANSWER   = 'captcha.answer';
    public const SESSION_TS       = 'captcha.ts';

    /** کمینهٔ ثانیه بین ساخت سؤال و پاسخ — پیش‌فرض (قابل تنظیم از پنل) */
    public const MIN_SECONDS = 2;

    /** حداکثر عمر سؤال (ثانیه) */
    public const MAX_AGE = 1800;

    /**
     * کمینهٔ ثانیهٔ تلهٔ زمان — از پنل «امنیت و امکانات» قابل تنظیم.
     */
    public static function minSeconds(): int
    {
        return max(0, min(120, (int) setting('sec_captcha_min_seconds', (string) self::MIN_SECONDS)));
    }

    /**
     * متن سؤال فعلی برای نمایش (در صورت نبود یا منقضی‌بودن، سؤال تازه می‌سازد).
     */
    public static function question(): string
    {
        $ts = (int) session(self::SESSION_TS, 0);

        if (! is_numeric(session(self::SESSION_ANSWER)) || (time() - $ts) > self::MAX_AGE) {
            static::fresh();
        }

        return (string) session(self::SESSION_QUESTION);
    }

    /**
     * ساخت سؤال تازه — پاسخ قبلی باطل می‌شود.
     */
    public static function fresh(): void
    {
        $a = random_int(2, 9);
        $b = random_int(2, 9);

        switch (random_int(1, 3)) {
            case 1:
                $text   = fa_num($a) . ' + ' . fa_num($b);
                $answer = $a + $b;
                break;

            case 2:
                $text   = fa_num($a) . ' × ' . fa_num($b);
                $answer = $a * $b;
                break;

            default: // تفریق همیشه نامنفی (عدد بزرگ‌تر منهای کوچک‌تر)
                $text   = fa_num(max($a, $b)) . ' − ' . fa_num(min($a, $b));
                $answer = max($a, $b) - min($a, $b);
        }

        session([
            self::SESSION_QUESTION => $text,
            self::SESSION_ANSWER   => $answer,
            self::SESSION_TS       => time(),
        ]);
    }

    /**
     * بررسی پاسخ کاربر (ارقام فارسی/عربی هم پذیرفته می‌شود).
     * همیشه سؤال را مصرف و تازه می‌کند — مستقل از درست‌بودن پاسخ —
     * تا یک پاسخ درست قابل استفادهٔ مجدد برای حمله نباشد.
     */
    public static function verify(mixed $input): bool
    {
        $expected = session(self::SESSION_ANSWER);
        $given    = trim((string) en_num(trim((string) $input)));
        $age      = time() - (int) session(self::SESSION_TS, 0);

        $ok = is_numeric($expected)
            && preg_match('/^\d{1,4}$/', $given) === 1
            && (int) $given === (int) $expected
            && $age >= self::minSeconds()
            && $age <= self::MAX_AGE;

        static::fresh();

        return $ok;
    }
}
