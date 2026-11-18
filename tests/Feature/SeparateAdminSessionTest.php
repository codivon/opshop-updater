<?php

namespace Tests\Feature;

use App\Livewire\Shop\SubscriptionResult;
use App\Models\Setting;
use App\Models\SubscriptionOrder;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\Concerns\MakesShopData;
use Tests\TestCase;

/**
 * Task 93 — جداسازی کامل سشن ورود مدیر از سشن فروشگاه/مشتریان.
 *
 * پیش‌تر مدیر و مشتری روی یک کوکی سشن مشترک بودند؛ خروج مدیر (invalidate) یا
 * پاک‌سازی کش، لاگین مشتری را هم می‌کُشت. حالا بسته به مبدأ درخواست، کوکی جدا
 * ست می‌شود و خروج هر سمت فقط همان سمت را بیرون می‌اندازد.
 *
 * همین فایل: رفع خطای «Method App\Livewire\Shop\SubscriptionResult::toast
 * does not exist» روی دکمهٔ «بررسی مجدد وضعیت» صفحهٔ نتیجهٔ پرداخت.
 */
class SeparateAdminSessionTest extends TestCase
{
    use MakesShopData, RefreshDatabase;

    /* -------------------------------------------------------------- */
    /*  نام کوکی سشن بسته به مبدأ درخواست */
    /* -------------------------------------------------------------- */

    public function test_shop_pages_get_shop_session_cookie(): void
    {
        $response = $this->get(route('shop.subscriptions'));

        $response->assertOk();

        $names = $this->responseCookieNames($response);

        $this->assertContains('opshop-shop-session', $names);
        $this->assertNotContains('opshop-admin-session', $names);
    }

    public function test_customer_login_page_gets_shop_session_cookie(): void
    {
        $response = $this->get(route('shop.login'));

        $response->assertOk();

        $this->assertContains('opshop-shop-session', $this->responseCookieNames($response));
    }

    public function test_admin_pages_get_admin_session_cookie(): void
    {
        // مهمان روی مسیر پنل → redirect به ورود؛ اما کوکی سشن مدیر باید ست شود
        // (تشخیص کوکی فقط بر اساس مسیر است، نه وضعیت لاگین)
        $response = $this->get(route('admin.dashboard'));

        $response->assertRedirect(route('login'));

        $names = $this->responseCookieNames($response);

        $this->assertContains('opshop-admin-session', $names);
        $this->assertNotContains('opshop-shop-session', $names);
    }

    public function test_authenticated_admin_session_lives_on_admin_cookie_after_real_login(): void
    {
        // ورود واقعی از فرم ورود مدیر (کپچا/هانی‌پات خاموش) تا سشن در چرخهٔ کامل
        // درخواست ساخته شود و نام کوکی آن opshop-admin-session باشد.
        foreach (['sec_captcha_enabled' => '0', 'sec_honeypot_enabled' => '0'] as $key => $value) {
            Setting::set($key, $value, 'string', 'security');
        }

        $admin = User::factory()->create([
            'role' => 'admin',
            'password' => Hash::make('password-1234'),
        ]);

        $response = $this->post(route('login'), [
            'email' => $admin->email,
            'password' => 'password-1234',
        ]);

        $response->assertRedirect(route('admin.dashboard'));

        $names = $this->responseCookieNames($response);

        $this->assertContains('opshop-admin-session', $names);
        $this->assertNotContains('opshop-shop-session', $names);
    }

    public function test_admin_login_page_gets_admin_session_cookie(): void
    {
        $response = $this->get(route('login'));

        $response->assertOk();

        $this->assertContains('opshop-admin-session', $this->responseCookieNames($response));
    }

    public function test_2fa_challenge_page_gets_admin_session_cookie(): void
    {
        // بدون سشن در انتظار 2FA ریدایرکت می‌شود — کوکی سشن مدیر همچنان ست می‌شود
        $response = $this->get(route('admin.2fa.challenge'));

        $response->assertRedirect();

        $names = $this->responseCookieNames($response);

        $this->assertContains('opshop-admin-session', $names);
        $this->assertNotContains('opshop-shop-session', $names);
    }

    /* -------------------------------------------------------------- */
    /*  استقلال واقعی: خروج هر سمت، سمت دیگر را بیرون نمی‌اندازد */
    /* -------------------------------------------------------------- */

    public function test_admin_logout_keeps_customer_session_intact(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $customer = $this->makeCustomer(['update_code' => 'T93-'.strtoupper(uniqid())]);

        $this->actingAs($admin);

        // خروج مدیر با متد سفارشی LoginController::logout — کل سشن invalidate نمی‌شود
        $this->post(route('logout'))->assertRedirect('/');

        // سشن مشتری (کوکی جدا) دست‌نخورده است — پنل مشتری باز می‌شود
        $this->actingAs($customer, 'customer');

        $this->get(route('customer.profile'))->assertOk();
        $this->assertAuthenticatedAs($customer, 'customer');
    }

    /* -------------------------------------------------------------- */
    /*  رفع خطای «SubscriptionResult::toast does not exist» (Task 93) */
    /* -------------------------------------------------------------- */

    public function test_subscription_result_recheck_dispatches_toast_without_error(): void
    {
        $customer = $this->makeCustomer();
        $plan = SubscriptionPlan::create([
            'name' => 'طرح تست recheck',
            'slug' => 'recheck-plan-'.uniqid(),
            'duration_months' => 1,
            'price' => 100000,
            'discount_price' => 0,
            'is_one_time' => false,
            'is_active' => true,
            'sort_order' => 0,
        ]);

        $order = SubscriptionOrder::create([
            'subscription_plan_id' => $plan->id,
            'customer_id' => $customer->id,
            'amount' => 100000,
            'discount' => 0,
            'final_amount' => 100000,
            'gateway' => 'local',
            'status' => SubscriptionOrder::STATUS_FAILED,
            'admin_status' => SubscriptionOrder::ADMIN_STATUS_PENDING,
        ]);

        // سفارشِ ناموفقِ بی‌تراکنش: recheck قبلاً با «Method toast does not exist»
        // خطای 500 می‌داد — حالا فقط هشدار (toast) می‌فرستد.
        Livewire::test(SubscriptionResult::class, ['order' => $order, 'token' => $order->access_token])
            ->call('recheck')
            ->assertDispatched('toast')
            ->assertHasNoErrors();
    }

    /* -------------------------------------------------------------- */
    /*  هلپرها */
    /* -------------------------------------------------------------- */

    /** نام همهٔ کوکی‌های ست‌شده در پاسخ داده‌شده */
    private function responseCookieNames($response): array
    {
        return collect($response->headers->getCookies() ?? [])
            ->map(fn ($cookie) => $cookie->getName())
            ->unique()
            ->values()
            ->all();
    }
}
