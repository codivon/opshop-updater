<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Rules\CaptchaRule;
use App\Services\CaptchaService;
use App\Services\TwoFactorService;
use App\Support\Features;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Login Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles authenticating users for the application and
    | redirecting them to your home screen. The controller uses a trait
    | to conveniently provide its functionality to your applications.
    |
    */

    use AuthenticatesUsers;

    /**
     * Where to redirect users after login.
     *
     * @var string
     */
    protected $redirectTo = '/admin';

    /**
     * محدودسازی تلاش‌های ناموفق ورود — اعداد از پنل «امنیت و امکانات»
     * (کلید تلاش: ایمیل + IP — خود تریت ThrottlesLogins).
     * خاموش‌کردن در پنل → سقف عملاً نامحدود.
     */
    protected $maxAttempts = 5;
    protected $decayMinutes = 15;

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('guest')->except('logout');
        $this->middleware('auth')->only('logout');

        // سقف/پنجرهٔ قفل از تنظیمات پنل (امنیت و امکانات)
        [$this->maxAttempts, $this->decayMinutes] = Features::lockoutConfig('admin_login');
    }

    /**
     * اعتبارسنجی فرم ورود مدیر + کپچای خودمیزبان + هانی‌پات ضدربات.
     * هر دو لایه از پنل امنیت و امکانات قابل خاموش/روشن‌کردن‌اند.
     */
    protected function validateLogin(Request $request): void
    {
        // هانی‌پات: فیلد مخفی website که انسان پر نمی‌کند؛ اگر پُر بود = ربات
        // → پاسخ مثل «گذرواژهٔ اشتباه» است تا ربات متوجه تله نشود.
        if (Features::honeypot() && trim((string) $request->input('website')) !== '') {
            CaptchaService::fresh();

            throw ValidationException::withMessages([
                $this->username() => __('auth.failed'),
            ]);
        }

        // CaptchaRule سؤال را مصرف و تازه می‌کند؛ پس پس از هر تلاش
        // (حتی ورود موفق) سؤال بعدی متفاوت است.
        $rules = [
            $this->username() => ['required', 'string'],
            'password'        => ['required', 'string'],
        ];

        if (Features::captcha('admin_login')) {
            $rules['captcha'] = ['required', 'string', new CaptchaRule];
        }

        $request->validate($rules);
    }

    /**
     * پس از ورود موفق — اگر ورود دومرحله‌ای مدیر (2FA) فعال باشد:
     * کد یک‌بارمصرف صادر و ارسال می‌شود، سشن نیمه‌کاره بسته می‌شود
     * و کاربر به صفحهٔ چالش کد هدایت می‌شود.
     */
    protected function authenticated(Request $request, $user)
    {
        if (! Features::twofa()) {
            return null;
        }

        $sent = app(TwoFactorService::class)->issue($user);

        Auth::guard('web')->logout();

        $request->session()->put([
            '2fa:id'       => $user->id,
            '2fa:remember' => $request->boolean('remember'),
            '2fa:sent'     => $sent,
        ]);

        return redirect()->route('admin.2fa.challenge');
    }
}
