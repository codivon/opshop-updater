<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\TwoFactorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * چالش ورود دومرحله‌ای مدیر — صفحهٔ واردکردن کد یک‌بارمصرف.
 *
 * جریان: LoginController::authenticated با ورود موفق، سشن نیمه‌کاره را
 * می‌بندد و به همین صفحه می‌فرستد؛ بعد از تأیید کد، کاربر کامل login می‌شود.
 *
 * ضدحملهٔ حدس کد: ۵ تلاش در ۵ دقیقه برای هر کاربر+IP (استور file — مستقل از DB).
 */
class TwoFactorController extends Controller
{
    /** سقف تلاش ناموفق */
    protected const MAX_ATTEMPTS = 5;

    /** پنجرهٔ قفل (ثانیه) */
    protected const LOCK_SECONDS = 300;

    public function show(Request $request)
    {
        $user = $this->pendingUser($request);

        if (! $user) {
            return redirect()->route('login');
        }

        return view('auth.two-factor', [
            'maskedEmail' => $this->maskEmail((string) $user->email),
            'channel'     => \App\Support\Features::twofaChannel(),
            'sent'        => $request->session()->pull('2fa:sent', true),
        ]);
    }

    public function verify(Request $request)
    {
        $request->validate([
            'code' => ['required', 'string', 'max:20'],
        ], [
            'code.required' => 'کد تأیید الزامی است.',
        ]);

        $user = $this->pendingUser($request);

        if (! $user) {
            return redirect()->route('login');
        }

        $key = '2fa-attempts:'.$user->id.'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            $minutes = (int) ceil(RateLimiter::availableIn($key) / 60);

            throw ValidationException::withMessages([
                'code' => 'تلاش‌های ناموفق بیش از حد مجاز است؛ حدود '.fa_num(max(1, $minutes)).' دقیقهٔ دیگر دوباره تلاش کنید.',
            ]);
        }

        $service = app(TwoFactorService::class);

        if (! $service->verify($user, $request->input('code'))) {
            RateLimiter::hit($key, self::LOCK_SECONDS);

            $remaining = self::MAX_ATTEMPTS - RateLimiter::attempts($key);

            throw ValidationException::withMessages([
                'code' => 'کد واردشده نادرست است.'
                    .($remaining > 0 ? ' '.fa_num($remaining).' تلاش باقی مانده.' : ''),
            ]);
        }

        RateLimiter::clear($key);

        $remember = (bool) $request->session()->pull('2fa:remember', false);
        $request->session()->forget('2fa:id');
        $request->session()->regenerate();

        Auth::guard('web')->login($user, $remember);

        return redirect()->intended('/admin');
    }

    /**
     * ارسال مجدد کد — حداکثر ۱ بار در دقیقه.
     */
    public function resend(Request $request)
    {
        $user = $this->pendingUser($request);

        if (! $user) {
            return redirect()->route('login');
        }

        $key = '2fa-resend:'.$user->id.'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, 1)) {
            $seconds = RateLimiter::availableIn($key);

            return back()->withErrors([
                'code' => 'برای ارسال مجدد کد '.fa_num(max(1, (int) ceil($seconds / 60))).' دقیقه صبر کنید.',
            ]);
        }

        RateLimiter::hit($key, 60);

        $sent = app(TwoFactorService::class)->issue($user);

        return back()->with('2fa:sent', $sent)
            ->withErrors($sent ? [] : ['code' => 'ارسال کد ناموفق بود؛ تنظیمات ایمیل/پیامک را بررسی کنید.']);
    }

    /* ------------------------------------------------------------------ */

    protected function pendingUser(Request $request): ?User
    {
        if (! \App\Support\Features::twofa()) {
            return null;
        }

        $id = $request->session()->get('2fa:id');

        return $id ? User::find($id) : null;
    }

    protected function maskEmail(string $email): string
    {
        if (! str_contains($email, '@')) {
            return $email;
        }

        [$name, $domain] = explode('@', $email, 2);

        $masked = $name !== '' ? mb_substr($name, 0, 1).'***' : '***';

        return $masked.'@'.$domain;
    }
}
