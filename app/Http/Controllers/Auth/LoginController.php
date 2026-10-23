<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Rules\CaptchaRule;
use App\Services\CaptchaService;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Http\Request;
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
     * محدودسازی تلاش‌های ناموفق ورود: حداکثر ۵ تلاش، سپس قفل ۱۵ دقیقه‌ای
     * (کلید throttle: ایمیل + IP — خود تریت ThrottlesLogins).
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
    }

    /**
     * اعتبارسنجی فرم ورود مدیر + کپچای خودمیزبان + هانی‌پات ضدربات.
     */
    protected function validateLogin(Request $request)
    {
        // هانی‌پات: فیلد مخفی website که انسان پر نمی‌کند؛ اگر پُر بود = ربات
        // → پاسخ مثل «گذرواژهٔ اشتباه» است تا ربات متوجه تله نشود.
        if (trim((string) $request->input('website')) !== '') {
            CaptchaService::fresh();

            throw ValidationException::withMessages([
                $this->username() => __('auth.failed'),
            ]);
        }

        // CaptchaRule سؤال را مصرف و تازه می‌کند؛ پس پس از هر تلاش
        // (حتی ورود موفق) سؤال بعدی متفاوت است.
        $request->validate([
            $this->username() => ['required', 'string'],
            'password'        => ['required', 'string'],
            'captcha'         => ['required', 'string', new CaptchaRule],
        ]);
    }
}
