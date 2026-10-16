<?php

namespace App\Livewire\Shop;

use App\Livewire\Concerns\WithToasts;
use App\Models\Comment;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * بخش دیدگاه‌های صفحه محصول/پکیج (کامپوننت فرزند چندریختی):
 * فرم ثبت دیدگاه مهمان (با تأیید مدیر) + فهرست دیدگاه‌های تأییدشده.
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
        $data = $this->validate();

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

        $this->reset('body', 'rating');

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
