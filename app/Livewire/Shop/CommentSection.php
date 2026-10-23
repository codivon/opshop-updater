<?php

namespace App\Livewire\Shop;

use App\Livewire\Concerns\WithToasts;
use App\Models\Comment;
use App\Services\CaptchaService;
use App\Support\Features;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * بخش دیدگاه‌های صفحه محصول/پکیج (کامپوننت فرزند چندریختی):
 * فرم ثبت دیدگاه مهمان (با تأیید مدیر) + فهرست دیدگاه‌های تأییدشده.
 *
 * لایه‌های ضداسپم (هرکدام از پنل «امنیت و امکانات» قابل تنظیم):
 * کپچای خودمیزبان + هانی‌پات + سقف ارسال برای هر IP؛ تأیید مدیر هم جداگانه لازم است.
 *
 * <livewire:shop.comment-section :model="$product" :key="'cs-'.$product->id" />
 */
class CommentSection extends Component
{
    use WithToasts, WithPagination;

    /** مدل هدف — Product یا Package (morph map: product | package) */
    public Model $model;

    /** برچسب «محصول» یا «پکیج» برای متن‌های صفحه */
    public string $label = 'محصول';

    /* ---- form ---- */
    public string $name = '';
    public string $email = '';
    public int $rating = 0;
    public string $body = '';

    /** پاسخ سؤال امنیتی (کپچای خودمیزبان) */
    public string $captcha = '';

    /** هانی‌پات — انسان این فیلد مخفی را پر نمی‌کند */
    public string $website = '';

    /** پیش‌پر کردن نام/ایمیل مشتری واردشده */
    public function mount(Model $model, string $label = 'محصول'): void
    {
        $this->model  = $model;
        $this->label  = $label;

        $customer = auth('customer')->user();

        if ($customer) {
            $this->name  = (string) ($customer->name ?: '');
            $this->email = (string) ($customer->email ?: '');
        }

        if (Features::captcha('comments')) {
            CaptchaService::fresh();
        }
    }

    /** سؤال امنیتی جدید (دکمهٔ «سؤال دیگر» — بدون رفرش صفحه) */
    public function refreshCaptcha(): void
    {
        CaptchaService::fresh();
        $this->reset('captcha');
    }

    protected function rules(): array
    {
        return [
            'name'   => ['required', 'string', 'max:120'],
            'email'  => ['nullable', 'email', 'max:190'],
            // نکته: rating پیش‌فرض 0 است (بدون امتیاز) → min:0 و در submit صفر به null تبدیل می‌شود
            'rating' => ['nullable', 'integer', 'min:0', 'max:5'],
            'body'   => ['required', 'string', 'min:3', 'max:5000'],
        ];
    }

    protected $messages = [
        'name.required' => 'نام شما الزامی است.',
        'name.max'      => 'نام حداکثر ۱۲۰ کاراکتر است.',
        'email.email'   => 'ایمیل معتبر نیست.',
        'body.required' => 'متن دیدگاه الزامی است.',
        'body.min'      => 'متن دیدگاه خیلی کوتاه است.',
        'body.max'      => 'متن دیدگاه حداکثر ۵٬۰۰۰ کاراکتر است.',
    ];

    public function setRating(int $stars): void
    {
        $this->rating = ($this->rating === $stars) ? 0 : min(5, max(1, $stars));
    }

    public function submit(): void
    {
        // ۱) سقف ارسال دیدگاه برای هر IP — اعداد از پنل «امنیت و امکانات»
        [$maxPerWindow, $decayMinutes] = Features::lockoutConfig('comment');
        $limiterKey = 'shop-comment:'.request()->ip();

        if (RateLimiter::tooManyAttempts($limiterKey, $maxPerWindow)) {
            $minutes = (int) ceil(RateLimiter::availableIn($limiterKey) / 60);

            $this->addError('captcha', 'تعداد دیدگاه‌های ارسالی بیش از حد مجاز است؛ حدود '.fa_num(max(1, $minutes)).' دقیقهٔ دیگر دوباره تلاش کنید.');
            return;
        }

        $data = $this->validate();

        // ۲) هانی‌پات: فیلد مخفی پُر است = ربات → ذخیره نمی‌شود
        if (Features::honeypot() && trim($this->website) !== '') {
            CaptchaService::fresh();
            $this->reset('captcha');
            $this->addError('captcha', 'پاسخ سؤال امنیتی نادرست است؛ سؤال جدید آماده شد.');
            return;
        }

        // ۳) کپچای خودمیزبان (پاسخ مصرف و سؤال تازه می‌شود)
        if (Features::captcha('comments') && ! CaptchaService::verify($this->captcha)) {
            $this->reset('captcha');
            $this->addError('captcha', 'پاسخ سؤال امنیتی نادرست است؛ سؤال جدید آماده شد.');
            return;
        }

        RateLimiter::hit($limiterKey, $decayMinutes * 60);

        Comment::create([
            'commentable_type' => $this->model->getMorphClass(),
            'commentable_id'   => $this->model->getKey(),
            'name'             => trim($data['name']),
            'email'            => $data['email'] ?: null,
            'rating'           => $data['rating'] ?: null,
            'body'             => trim($data['body']),
            'status'           => Comment::STATUS_PENDING,
            'ip'               => request()->ip(),
        ]);

        $this->reset('body', 'rating', 'captcha');

        $this->toast('دیدگاه شما ثبت شد؛ پس از تأیید مدیر نمایش داده می‌شود.');
    }

    #[Computed]
    public function approved()
    {
        return $this->model->comments()
            ->where('status', Comment::STATUS_APPROVED)
            ->orderByDesc('id')
            ->paginate(8, pageName: 'commentsPage');
    }

    /** میانگین امتیاز دیدگاه‌های تأییدشده */
    #[Computed]
    public function averageRating(): ?float
    {
        $avg = $this->model->comments()
            ->where('status', Comment::STATUS_APPROVED)
            ->whereNotNull('rating')
            ->avg('rating');

        return $avg ? round((float) $avg, 1) : null;
    }

    #[Computed]
    public function approvedCount(): int
    {
        return $this->model->comments()
            ->where('status', Comment::STATUS_APPROVED)
            ->count();
    }

    public function render()
    {
        return view('livewire.shop.comment-section');
    }
}
