<?php

namespace App\Livewire\Purchases;

use App\Livewire\Concerns\AuthorizesAdmin;
use App\Livewire\Concerns\WithToasts;
use App\Models\PackagePurchase;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('جزئیات خرید')]
class Show extends Component
{
    use AuthorizesAdmin;
    use WithToasts;

    public PackagePurchase $purchase;

    public bool $confirmingDelete = false;

    public function mount(PackagePurchase $purchase): void
    {
        $purchase->load([
            'package:id,name,slug',
            'customer',
            'license:id,license_key,status,expires_at,duration_months',
            'pricingPlan:id,name,price,discount_price,duration_months,is_one_time',
            'version:id,version',
        ]);

        $this->purchase = $purchase;
    }

    /** حذف خرید و بازگشت به فهرست */
    public function delete(): void
    {
        $purchase = $this->purchase;

        $purchase->delete();

        $this->toast('خرید حذف شد.');
        $this->redirect(route('admin.purchases.index'), true);
    }

    public function render()
    {
        return view('livewire.purchases.show');
    }
}
