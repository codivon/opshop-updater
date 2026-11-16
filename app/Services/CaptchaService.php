<?php

namespace App\Services;

use App\Support\Features;
use Illuminate\Support\Facades\Http;

/**
 * سرویس کپچا با «درایور» — چهار نوع:
 *
 *   ۱) math        — ریاضی پیشرفتهٔ خودمیزبان (تعداد عملوند و عملیات مجاز از پنل؛
 *                    مثل «۴ + ۷ × ۲») — پیش‌فرض و سازگار با اینترنت ایران
 *   ۲) image       — تصویری حرف‌عددی خودمیزبان (۵ نویسهٔ بی‌ابهام مثل «K7M2R»)
 *   ۳) hcaptcha    — ویجت ابری hCaptcha (کلیدها از پنل)
 *   ۴) turnstile   — ویجت ابری Cloudflare Turnstile (کلیدها از پنل)
 *
 * هر «نقطه» (spot) درایور خودش را از پنل دارد:
 *   admin_login | admin_register | customer_login | comments | contact | checkout
 * درایور ابری بدون کلید → فال‌بک امن به math (هیچ فرمی از کار نمی‌افتد).
 *
 * خودمیزبان‌ها:
 *   - نمایش به‌صورت «تصویر PNG» (CaptchaImageRenderer) نه متن HTML — استخراج با OCR نه regex
 *   - پاسخ فقط در سشن سرور؛ مصرف یک‌بارمصرف (هر verify سؤال تازه می‌سازد)
 *   - تلهٔ زمان: پاسخ‌دادن زیر sec_captcha_min_seconds ثانیه = ربات
 *   - انقضا: سؤال بیش از ۳۰ دقیقه اعتبار ندارد
 *
 * در فرم‌های عمومی جفت هانی‌پات (فیلد مخفی website) هم هست.
 *
 * شمارندهٔ «نسخه» در سشن با هر fresh() بالا می‌رود؛ img کپچا آن را به‌عنوان
 * پارامتر cache-busting در src دارد تا پس از تازه‌سازی، مرورگر تصویر نو بگیرد.
 */
class CaptchaService
{
    public const SESSION_QUESTION = 'captcha.question';

    public const SESSION_ANSWER = 'captcha.answer';

    public const SESSION_TS = 'captcha.ts';

    public const SESSION_VERSION = 'captcha.version';

    /** کمینهٔ ثانیه بین ساخت سؤال و پاسخ — پیش‌فرض (قابل تنظیم از پنل) */
    public const MIN_SECONDS = 2;

    /** حداکثر عمر سؤال (ثانیه) */
    public const MAX_AGE = 1800;

    /** درایورهای پشتیبانی‌شده */
    public const DRIVERS = ['image', 'math', 'hcaptcha', 'turnstile'];

    /** الفبای تصویری حرف‌عددی — نویسه‌های بی‌ابهام (بدون I/1، O/0 و…) */
    public const ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    /** طول پاسخ تصویری حرف‌عددی */
    public const ALPHA_LENGTH = 5;

    /** سقف مقدار جواب ریاضی (ورودی verify حداکثر ۴ رقم می‌پذیرد) */
    protected const MATH_CEIL = 9999;

    /**
     * کمینهٔ ثانیهٔ تلهٔ زمان — از پنل «امنیت و امکانات» قابل تنظیم.
     */
    public static function minSeconds(): int
    {
        return max(0, min(120, (int) setting('sec_captcha_min_seconds', (string) self::MIN_SECONDS)));
    }

    /**
     * درایور نقطهٔ داده‌شده — با فال‌بک امن خودمیزبان.
     * $spot: admin_login | admin_register | customer_login | comments | contact | checkout
     */
    public static function driver(string $spot = 'generic'): string
    {
        return Features::captchaDriver($spot);
    }

    /**
     * شمارندهٔ نسخهٔ سؤال — cache-busting تصویر کپچا در src.
     */
    public static function version(): int
    {
        return (int) session(self::SESSION_VERSION, 0);
    }

    /**
     * متن سؤال فعلی برای نمایش (در صورت نبود یا منقضی‌بودن، سؤال تازه می‌سازد).
     * نمایش مستقیم این متن در HTML ممنوع — فقط در تصویر استفاده می‌شود.
     */
    public static function question(): string
    {
        $ts = (int) session(self::SESSION_TS, 0);

        if (! is_numeric(session(self::SESSION_ANSWER)) && ! is_string(session(self::SESSION_ANSWER)) || (time() - $ts) > self::MAX_AGE) {
            static::fresh();
        }

        return (string) session(self::SESSION_QUESTION);
    }

    /**
     * ساخت سؤال تازه بر اساس درایور نقطهٔ داده‌شده — پاسخ قبلی باطل می‌شود.
     * سرویس‌های ابری سؤال سشن ندارند؛ فقط شمارندهٔ نسخه جلو می‌رود تا تصویرِ
     * اگر درایور بعداً عوض شد cache-busting درست کار کند.
     *
     * بدون پارامتر (فراخوانی‌های قدیمی) = ریاضی خودمیزبان.
     */
    public static function fresh(?string $spot = null): void
    {
        $driver = $spot !== null ? static::driver($spot) : 'math';

        if ($driver === 'image') {
            // حرف‌عددی: پاسخ همان رشتهٔ سؤال است (باید یک رشته بسازیم نه دو!)
            $code = static::randomAlpha();
            static::store($code, $code);
        } elseif ($driver === 'math') {
            [$text, $answer] = static::mathExpression();
            static::store($text, (string) $answer);
        } else {
            // hcaptcha / turnstile — سؤال سشن ندارد؛ فقط نسخه جلو می‌رود
            session([self::SESSION_VERSION => static::version() + 1]);
        }
    }

    /**
     * بررسی پاسخ کاربر بر اساس درایور نقطه.
     * خودمیزبان‌ها همیشه سؤال را مصرف و تازه می‌کنند (حتی اگر پاسخ درست باشد)
     * تا یک پاسخ درست قابل استفادهٔ مجدد برای حمله نباشد.
     */
    public static function verify(mixed $input, string $spot = 'generic'): bool
    {
        $driver = static::driver($spot);

        if ($driver === 'hcaptcha' || $driver === 'turnstile') {
            return static::verifyCloud($input, $driver);
        }

        $expected = session(self::SESSION_ANSWER);
        $raw = trim((string) en_num(trim((string) $input)));
        $age = time() - (int) session(self::SESSION_TS, 0);
        $timed = $age >= static::minSeconds() && $age <= self::MAX_AGE;

        if ($driver === 'image') {
            // حرف‌عددی: فقط نویسه‌های الفبایی‌عددی، بدون حساسیت به بزرگی/کوچکی
            $given = strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', $raw));
            $ok = is_string($expected)
                && $given !== ''
                && $given === strtoupper((string) $expected)
                && $timed;
        } else {
            // ریاضی — ارقام فارسی/عربی هم پذیرفته می‌شود (en_num)
            $given = $raw;
            $ok = is_numeric($expected)
                && preg_match('/^\d{1,4}$/', $given) === 1
                && (int) $given === (int) $expected
                && $timed;
        }

        static::fresh($spot);

        return $ok;
    }

    /* ------------------------------------------------------------------ */
    /*  تولید سؤال */
    /* ------------------------------------------------------------------ */

    /** ذخیرهٔ سؤال/پاسخ تازه در سشن */
    protected static function store(string $question, string $answer): void
    {
        session([
            self::SESSION_QUESTION => $question,
            self::SESSION_ANSWER => $answer,
            self::SESSION_TS => time(),
            self::SESSION_VERSION => static::version() + 1,
        ]);
    }

    /** رشتهٔ تصویری حرف‌عددی — نویسه‌های بی‌ابهام */
    protected static function randomAlpha(): string
    {
        $chars = '';
        $max = strlen(self::ALPHABET) - 1;

        for ($i = 0; $i < self::ALPHA_LENGTH; $i++) {
            $chars .= self::ALPHABET[random_int(0, $max)];
        }

        return $chars;
    }

    /**
     * عبارت ریاضی پیشرفته — تعداد عملوند و عملیات مجاز از پنل.
     * تفریق همیشه نتیجهٔ نامنفی؛ ضرب سقف ۹۹۹۹ را رعایت می‌کند
     * (ورودی verify حداکثر ۴ رقم است).
     *
     * @return array{0: string, 1: int} [متن سؤال، جواب]
     */
    protected static function mathExpression(): array
    {
        [$operands, $ops] = Features::mathConfig();
        $operands = max(2, min(5, $operands));

        $acc = random_int(2, 9);
        $numbers = [$acc];
        $operators = [];

        for ($i = 1; $i < $operands; $i++) {
            $op = $ops[random_int(0, count($ops) - 1)];

            if ($op === '×' && $acc * 9 > self::MATH_CEIL) {
                $op = '+'; // سقف جواب
            }

            if ($op === '−' && $acc <= 2) {
                $op = '+'; // تفریقِ نامنفی ممکن نیست
            }

            $n = match ($op) {
                '+' => random_int(2, 9),
                '×' => random_int(2, 9),
                default => random_int(2, min(9, $acc - 1)),
            };

            $acc = match ($op) {
                '+' => $acc + $n,
                '×' => $acc * $n,
                default => $acc - $n,
            };

            $numbers[] = $n;
            $operators[] = $op;
        }

        $text = fa_num($numbers[0]);

        foreach ($operators as $i => $op) {
            $text .= ' '.$op.' '.fa_num($numbers[$i + 1]);
        }

        return [$text, $acc];
    }

    /* ------------------------------------------------------------------ */
    /*  راستی‌آزمایی سرویس ابری */
    /* ------------------------------------------------------------------ */

    /**
     * توکن ویجت ابری (hcaptcha/turnstile) را با API رسمی سایت‌وریفی چک می‌کند.
     * خطای شبکه/سرویس = پاسخ نامعتبر (fail-closed — کپچا است، نه پرداخت).
     */
    protected static function verifyCloud(mixed $input, string $driver): bool
    {
        $token = trim((string) $input);

        if ($token === '' || mb_strlen($token) > 8192) {
            return false;
        }

        $secret = trim((string) setting($driver === 'hcaptcha' ? 'sec_captcha_hcaptcha_secret' : 'sec_captcha_turnstile_secret', ''));

        if ($secret === '') {
            return false;
        }

        $endpoint = $driver === 'hcaptcha'
            ? 'https://api.hcaptcha.com/siteverify'
            : 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

        try {
            $response = Http::asForm()
                ->connectTimeout(5)
                ->timeout(10)
                ->post($endpoint, [
                    'secret' => $secret,
                    'response' => $token,
                    'remoteip' => (string) request()->ip(),
                ]);

            return (bool) ($response->json('success') ?? false);
        } catch (\Throwable) {
            return false;
        }
    }
}
