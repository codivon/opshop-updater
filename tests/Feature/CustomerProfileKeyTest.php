<?php

namespace Tests\Feature;

use App\Livewire\Shop\CustomerProfile;
use App\Models\Customer;
use App\Services\LicenseSigningKeyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Task 92 — کارت «کلید عمومی فروشگاه» در پروفایل مشتری:
 *  - وقتی کلید امضای لایسنس تنظیم شده باشد، کارت با دکمه‌های کپی رندر می‌شود
 *  - کلید هرگز در DOM رندر نمی‌شود (کپی با کلیک و از طریق $wire انجام می‌شود)
 *  - وقتی کلیدی تنظیم نشده، کارت نمایش داده نمی‌شود
 *
 * نکته: سرویس کلید، .env را مستقیم می‌خواند؛ پس در تست سرویس را Mock می‌کنیم
 * تا نتیجه مستقل از .envِ ماشینِ اجرا باشد.
 */
class CustomerProfileKeyTest extends TestCase
{
    use RefreshDatabase;

    private function makeCustomer(): Customer
    {
        return Customer::create([
            'name' => 'مشتری کلید عمومی',
            'email' => 'pubkey-'.uniqid().'@example.com',
            'website_url' => 'https://pubkey.example.com',
            'status' => Customer::STATUS_ACTIVE,
        ]);
    }

    public function test_profile_shows_public_key_copy_card_when_key_configured(): void
    {
        $publicPem = "-----BEGIN PUBLIC KEY-----\nMFkwEwYHKoZIzj0CAQYIKoZIzj0DAQcDQgAEtest\n-----END PUBLIC KEY-----\n";

        $this->mock(LicenseSigningKeyService::class)
            ->shouldReceive('status')
            ->andReturn([
                'state' => 'ok',
                'fingerprint' => 'fingerprint-test',
                'public_key' => $publicPem,
                'shop_env_value' => 'PACKAGE_LICENSE_PUBLIC_KEY="'.str_replace("\n", '\\n', trim($publicPem)).'"',
                'env_exists' => true,
                'env_writable' => true,
                'config_cached' => false,
                'grace_hours' => 24,
                'check_interval_hours' => 24,
            ]);

        $customer = $this->makeCustomer();

        $html = Livewire::actingAs($customer, 'customer')
            ->test(CustomerProfile::class)
            ->html();

        $this->assertStringContainsString('کلید عمومی را هم در فروشگاه خود تنظیم کنید', $html);
        $this->assertStringContainsString('کپی خط .env', $html);
        $this->assertStringContainsString('کپی فقط کلید', $html);
        $this->assertStringContainsString('PACKAGE_LICENSE_PUBLIC_KEY', $html);

        // کلید به‌صورت متنِ نمایشی رندر نمی‌شود — فقط نق‌نق‌دار
        $this->assertStringContainsString('••••••', $html);
        // کلید اصلاً در DOM رندر نمی‌شود (کپی با کلیک و از طریق $wire انجام می‌شود)
        $this->assertStringNotContainsString('BEGIN PUBLIC KEY', $html);
    }

    public function test_profile_hides_public_key_card_when_no_key_configured(): void
    {
        $this->mock(LicenseSigningKeyService::class)
            ->shouldReceive('status')
            ->andReturn([
                'state' => 'missing',
                'fingerprint' => null,
                'public_key' => null,
                'shop_env_value' => null,
                'env_exists' => true,
                'env_writable' => true,
                'config_cached' => false,
                'grace_hours' => 24,
                'check_interval_hours' => 24,
            ]);

        $customer = $this->makeCustomer();

        $component = Livewire::actingAs($customer, 'customer')
            ->test(CustomerProfile::class);

        $this->assertStringNotContainsString('کلید عمومی را هم در فروشگاه خود تنظیم کنید', $component->html());
    }
}
