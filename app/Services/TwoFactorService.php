<?php

namespace App\Services;

use App\Models\User;
use App\Support\Features;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * ورود دومرحله‌ای مدیر (2FA) — کد یک‌بارمصرف ۶ رقمی.
 *
 * کانال‌های ارسال (از پنل «امنیت و امکانات»):
 *   - email: Mail::raw با پیکربندی SMTP (اگر «استفاده از تنظیمات ایمیل پنل» روشن باشد)
 *   - sms  : قالب admin_2fa_code → شمارهٔ sms_admin_mobile از طریق SmsManager
 *   - both : هر دو
 *
 * ایمنی:
 *   - کد هش‌شده (bcrypt) فقط ۱۰ دقیقه در کش می‌ماند
 *   - هر verify موفق کد را مصرف می‌کند
 *   - اگر هیچ کانالی موفق نشد، کد در laravel.log ثبت می‌شود تا مدیر قفل نشود
 *     (روی هاست اشتراکی لاگ برای صاحب سایت در دسترس است)
 */
class TwoFactorService
{
    /** عمر کد (دقیقه) */
    public const TTL_MINUTES = 10;

    public function cacheKey(int $userId): string
    {
        return '2fa:code:'.$userId;
    }

    /**
     * صدور کد تازه و ارسال آن — true یعنی حداقل یک کانال موفق بود.
     */
    public function issue(User $user): bool
    {
        $code = (string) random_int(100000, 999999);

        Cache::put($this->cacheKey($user->id), Hash::make($code), now()->addMinutes(self::TTL_MINUTES));

        $sent = $this->deliver($user, $code);

        if (! $sent) {
            // کانال پشتیبان نهایی — مدیر قفل نشود (هشدار: فقط هنگام خرابی ارسال)
            Log::warning('2FA: ارسال کد ورود دومرحله‌ای ناموفق بود — کد پشتیبان', [
                'user_id' => $user->id,
                'code'    => $code,
            ]);
        }

        return $sent;
    }

    /**
     * ارسال کد از کانال(های) انتخاب‌شده.
     */
    public function deliver(User $user, string $code): bool
    {
        $channel = Features::twofaChannel();
        $sent    = false;

        if (in_array($channel, ['email', 'both'], true)) {
            try {
                Mail::raw(
                    "کد ورود دومرحله‌ای شما: {$code}\nاعتبار: ۱۰ دقیقه\n\n".Features::siteName(),
                    function ($message) use ($user): void {
                        $message->to($user->email)->subject('کد ورود دومرحله‌ای — '.Features::siteName());
                    }
                );

                $sent = true;
            } catch (Throwable $e) {
                Log::warning('2FA: ارسال ایمیل ناموفق', ['error' => $e->getMessage()]);
            }
        }

        if (in_array($channel, ['sms', 'both'], true)) {
            try {
                $log = app(SmsManager::class)->send('admin_2fa_code', (string) setting('sms_admin_mobile', ''), [
                    'code' => $code,
                ]);

                $sent = $sent || ($log !== null && $log->status === \App\Models\SmsLog::STATUS_SENT);
            } catch (Throwable $e) {
                Log::warning('2FA: ارسال پیامک ناموفق', ['error' => $e->getMessage()]);
            }
        }

        return $sent;
    }

    /**
     * بررسی کد (ارقام فارسی/عربی هم پذیرفته می‌شود) — کد درست مصرف می‌شود.
     */
    public function verify(User $user, mixed $input): bool
    {
        $cached = Cache::get($this->cacheKey($user->id));
        $given  = (string) en_num(trim((string) $input));

        if ($cached === null || ! Hash::check($given, $cached)) {
            return false;
        }

        $this->forget($user);

        return true;
    }

    public function forget(User $user): void
    {
        Cache::forget($this->cacheKey($user->id));
    }
}
