<?php

namespace App\Livewire\Settings;

use App\Livewire\Concerns\WithToasts;
use Illuminate\Support\Facades\Hash;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * پروفایل مدیر (/admin/profile):
 * ویرایش نام نمایشی + ایمیل ورود، و تغییر رمز عبور با تأیید رمز فعلی.
 *
 * الگو: مطابق بقیهٔ صفحات Livewire پنل — دو فرم مستقل با اعتبارسنجی جدا
 * تا ذخیرهٔ اطلاعات حساب نیازمند تایپ مجدد رمز نباشد (اما تغییر رمز همیشه
 * رمز فعلی را می‌پرسد).
 */
#[Layout('components.layouts.app')]
#[Title('پروفایل مدیر')]
class Profile extends Component
{
    use WithToasts;

    /* --- فرم اطلاعات حساب --- */
    public string $name = '';

    public string $email = '';

    /* --- فرم تغییر رمز عبور --- */
    public string $current_password = '';

    public string $password = '';

    public string $password_confirmation = '';

    public function mount(): void
    {
        $user = auth()->user();

        $this->name = (string) ($user?->name ?? '');
        $this->email = (string) ($user?->email ?? '');
    }

    /** ذخیرهٔ نام نمایشی + ایمیل ورود */
    public function saveProfile(): void
    {
        // نرمال‌سازی پیش از اعتبارسنجی (ایمیل با فاصلهٔ ابتدایی/انتهایی معتبر نیست)
        $this->name = trim($this->name);
        $this->email = trim($this->email);

        $this->validate($this->profileRules(), $this->messages());

        auth()->user()?->update([
            'name'  => $this->name,
            'email' => mb_strtolower($this->email),
        ]);

        // همگام‌سازی فیلدها با مقدار نهایی (مثلاً بعد از trim/حروف‌کوچک ایمیل)
        $this->name = (string) auth()->user()?->name;
        $this->email = (string) auth()->user()?->email;

        $this->toast('اطلاعات حساب ذخیره شد.');
    }

    /** تغییر رمز عبور — همیشه با تأیید رمز فعلی */
    public function savePassword(): void
    {
        $this->validate($this->passwordRules(), $this->messages());

        auth()->user()?->update([
            'password' => $this->password, // cast «hashed» مدل خودش هش می‌کند
        ]);

        $this->reset('current_password', 'password', 'password_confirmation');

        $this->toast('رمز عبور با موفقیت تغییر کرد.');
    }

    public function render()
    {
        return view('livewire.settings.profile');
    }

    /* ------------------------------------------------------------------ */
    /*  قواعد اعتبارسنجی                                                   */
    /* ------------------------------------------------------------------ */

    protected function profileRules(): array
    {
        return [
            'name'  => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:190', 'unique:users,email,'.(auth()->id() ?? 'NULL')],
        ];
    }

    protected function passwordRules(): array
    {
        return [
            'current_password' => [
                'required', 'string',
                function (string $attribute, mixed $value, \Closure $fail) {
                    $user = auth()->user();

                    if (! $user || ! Hash::check((string) $value, $user->password)) {
                        $fail('رمز عبور فعلی صحیح نیست.');
                    }
                },
            ],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
        ];
    }

    protected function messages(): array
    {
        return [
            'name.required' => 'نام نمایشی الزامی است.',
            'name.max'      => 'نام نمایشی حداکثر ۲۵۵ کاراکتر است.',

            'email.required' => 'ایمیل الزامی است.',
            'email.email'    => 'ایمیل معتبر نیست.',
            'email.max'      => 'ایمیل حداکثر ۱۹۰ کاراکتر است.',
            'email.unique'   => 'این ایمیل قبلاً برای کاربر دیگری ثبت شده است.',

            'current_password.required' => 'رمز عبور فعلی را وارد کنید.',

            'password.required'  => 'رمز عبور جدید الزامی است.',
            'password.min'       => 'رمز عبور باید حداقل ۶ کاراکتر باشد.',
            'password.confirmed' => 'تکرار رمز عبور با رمز جدید یکسان نیست.',
        ];
    }
}
