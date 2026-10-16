<?php

namespace App\Livewire\Shop;

use App\Livewire\Concerns\WithToasts;
use App\Models\Product;
use App\Models\ProductComment;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * بخش دیدگاه‌های صفحه محصول (کامپوننت فرزند):
 * فرم ثبت دیدگاه مهمان (با تأیید مدیر) + فهرست دیدگاه‌های تأییدشده.
 */
class ProductComments extends Component
{
    use WithToasts, WithPagination;

    public Product $product;

    /* ---- form ---- */
    public string $name = '';
    public string $email = '';
    public int $rating = 0;
    public string $body = '';

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

    public function mount(Product $product): void
    {
        $this->product = $product;
    }

    public function setRating(int $stars): void
    {
        $this->rating = ($this->rating === $stars) ? 0 : min(5, max(1, $stars));
    }

    public function submit(): void
    {
        $data = $this->validate();

        ProductComment::create([
            'product_id' => $this->product->id,
            'name'       => trim($data['name']),
            'email'      => $data['email'] ?: null,
            'rating'     => $data['rating'] ?: null,
            'body'       => trim($data['body']),
            'status'     => ProductComment::STATUS_PENDING,
            'ip'         => request()->ip(),
        ]);

        $this->reset('name', 'email', 'rating', 'body');

        $this->toast('دیدگاه شما ثبت شد؛ پس از تأیید مدیر نمایش داده می‌شود.');
    }

    #[Computed]
    public function approved()
    {
        return $this->product->comments()
            ->where('status', ProductComment::STATUS_APPROVED)
            ->orderByDesc('id')
            ->paginate(8, pageName: 'commentsPage');
    }

    public function render()
    {
        return view('livewire.shop.product-comments');
    }
}
