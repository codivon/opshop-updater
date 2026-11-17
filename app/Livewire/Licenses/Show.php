<?php

namespace App\Livewire\Licenses;

use App\Livewire\Concerns\AuthorizesAdmin;
use App\Livewire\Concerns\WithToasts;
use App\Models\PackageLicense;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('جزئیات لایسنس')]
class Show extends Component
{
    use AuthorizesAdmin;
    use WithToasts;

    public PackageLicense $license;

    public bool $confirmRevoke = false;

    public function mount(PackageLicense $license): void
    {
        $license->load(['package:id,name,slug', 'customer', 'purchase', 'renewedFrom:id,license_key,status']);

        $this->license = $license;
    }

    /* ---------------------------------------------------------------- */
    /*  Actions (ported from old PackageLicenseController) */
    /* ---------------------------------------------------------------- */

    public function revoke(): void
    {
        $this->license->revoke();

        $this->confirmRevoke = false;
        $this->toast('لایسنس باطل شد.');
    }

    public function activate(): void
    {
        $this->license->update(['status' => PackageLicense::STATUS_ACTIVE]);

        $this->toast('لایسنس فعال شد.');
    }

    #[Computed]
    public function downloadTokens()
    {
        return $this->license->downloadTokens()
            ->with('version:id,version')
            ->latest()
            ->limit(10)
            ->get();
    }

    public function render()
    {
        return view('livewire.licenses.show');
    }
}
