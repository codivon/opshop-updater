<?php

namespace App\Livewire\Shop;

use App\Livewire\Concerns\WithToasts;
use App\Models\Customer;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * ورود مشتری فروشگاه — فقط با «کد آپدیت».
 * جدول مشتریان همان customers است (فیلد update_code).
 */
#[Layout('components.layouts.shop')]
#[Title('ورود مشتری')]
class CustomerLogin extends Component
{
    use WithToasts;

    public string $updateCode = '';

    public string $redirect = '';

    public function mount(): void
    {
        // اگر از قبل وارد شده، به پروفایل برو
        if (auth('customer')->check()) {
            $this->redirect(route('customer.profile'), navigate: true);
        }

        $this->redirect = (string) request()->query('redirect', '');
    }

    protected function rules(): array
    {
        return [
            'updateCode' => ['required', 'string', 'max:64'],
        ];
    }

    protected $messages = [
        'updateCode.required' => 'کد آپدیت الزامی است.',
    ];

    public function login(): void
    {
        $data = $this->validate();

        $customer = Customer::query()
            ->where('update_code', strtoupper(trim($data['updateCode'])))
            ->first();

        if (!$customer) {
            $this->addError('updateCode', 'کد آپدیت نامعتبر است.');
            return;
        }

        if ($customer->status !== Customer::STATUS_ACTIVE) {
            $this->addError('updateCode', 'حساب این مشتری غیرفعال است. با پشتیبانی تماس بگیرید.');
            return;
        }

        Auth::guard('customer')->login($customer, remember: true);

        // هم‌گام با مکانیزم قدیمی (کد در session برای نشان اشتراک)
        session()->put('shop_update_code', $customer->update_code);

        $this->toast('خوش آمدید، ' . $customer->name . '!');

        $target = ($this->redirect !== '' && str_starts_with($this->redirect, '/'))
            ? $this->redirect
            : route('customer.profile');

        $this->redirect($target, navigate: true);
    }

    public function render()
    {
        return view('livewire.shop.customer-login');
    }
}
