<?php
/**
 * Demo data seeder (development preview).
 * Usage: php demo-seed.php
 */
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Customer;
use App\Models\Category;
use App\Models\Package;
use App\Models\PackageImage;
use App\Models\PackageLicense;
use App\Models\PackagePricingPlan;
use App\Models\PackagePurchase;
use App\Models\PackageVersion;
use App\Models\Product;
use App\Models\ProductComment;
use App\Models\ProductPurchase;
use App\Models\ProductVersion;
use App\Models\Project;
use App\Models\Subscription;
use App\Models\SubscriptionOrder;
use App\Models\User;
use App\Models\SubscriptionPlan;
use App\Models\Update;
use Illuminate\Support\Str;

// ---------- projects ----------
$projects = collect([
    ['name' => 'فروشگاه ساز وب‌ترو', 'slug' => 'webtro-shop', 'description' => 'سیستم فروشگاه اینترنتی ماژولار', 'status' => 'active'],
    ['name' => 'سامانه انبار', 'slug' => 'warehouse', 'description' => 'مدیریت انبار و موجودی', 'status' => 'active'],
    ['name' => 'پرتال سازمانی', 'slug' => 'portal', 'description' => 'پرتال داخلی سازمان', 'status' => 'pending'],
])->map(fn ($p) => Project::updateOrCreate(['slug' => $p['slug']], $p + ['repository_url' => 'https://github.com/example/' . $p['slug']]));

// ---------- packages ----------
$pkgData = [
    ['name' => 'درگاه پرداخت ملت', 'slug' => 'behpardakht-gateway', 'category' => 'payment', 'price' => 950000, 'author' => 'تیم وب‌ترو', 'short' => 'اتصال به درگاه به‌پرداخت ملت', 'status' => 'active'],
    ['name' => 'ماژول پیامکی کاوه‌نگار', 'slug' => 'kavenegar-sms', 'category' => 'notification', 'price' => 450000, 'author' => 'تیم وب‌ترو', 'short' => 'ارسال پیامک با کاوه‌نگار', 'status' => 'active'],
    ['name' => 'سئوپروس', 'slug' => 'seo-pro', 'category' => 'seo', 'price' => 1200000, 'author' => 'علیرضا محمدی', 'short' => 'ابزارک‌های سئو پیشرفته', 'status' => 'active'],
    ['name' => 'بلاگ حرفه‌ای', 'slug' => 'pro-blog', 'category' => 'blog', 'price' => 780000, 'author' => 'سارا احمدی', 'short' => 'بلاگ با ویرایشگر گرافیکی', 'status' => 'draft'],
    ['name' => 'قالب فروشگاهی مدرن', 'slug' => 'modern-shop-theme', 'category' => 'theme', 'price' => 0, 'is_free' => true, 'author' => 'تیم طراحی', 'short' => 'قالب ریسپانسیو فروشگاه', 'status' => 'active'],
];

$packages = collect($pkgData)->map(function ($p) use ($projects) {
    return Package::updateOrCreate(['slug' => $p['slug']], [
        'project_id' => $projects->firstWhere('slug', 'webtro-shop')->id,
        'name' => $p['name'],
        'short_description' => $p['short'],
        'description' => $p['short'] . ' با مستندات کامل و پشتیبانی یک‌ساله. سازگار با آخرین نسخه فریم‌ورک.',
        'author' => $p['author'],
        'category' => $p['category'],
        'thumbnail' => null,
        'is_free' => $p['is_free'] ?? false,
        'default_price' => $p['price'],
        'status' => $p['status'],
        'module_name' => ucfirst(Str::camel($p['slug'])),
        'downloads_count' => rand(40, 900),
        'purchases_count' => 0,
        'sort_order' => rand(0, 50),
    ]);
});

// ---------- versions ----------
$packages->each(function ($package, $i) {
    $versions = [
        ['version' => '1.0.0', 'type' => 'major', 'date' => now()->subMonths(4)],
        ['version' => '1.1.0', 'type' => 'minor', 'date' => now()->subMonths(2)],
        ['version' => '1.1.3', 'type' => 'patch', 'date' => now()->subDays(12)],
    ];
    foreach ($versions as $v) {
        PackageVersion::updateOrCreate([
            'package_id' => $package->id,
            'version' => $v['version'],
        ], [
            'type' => $v['type'],
            'changelog' => 'بهبود عملکرد و رفع باگ‌های گزارش‌شده.',
            'what_added' => 'امکانات جدید گزارش‌گیری',
            'what_changed' => 'بازطراحی بخش تنظیمات',
            'what_fixed' => 'رفع مشکل کش درگاه پرداخت',
            'file_path' => "packages/{$package->slug}-{$v['version']}.zip",
            'file_size' => rand(300000, 5000000),
            'file_hash' => hash('sha256', $package->slug . $v['version']),
            'min_project_version' => '2.5.0',
            'min_php_version' => '8.1',
            'min_laravel_version' => '10.0',
            'is_mandatory' => $v['type'] === 'major',
            'status' => 'active',
            'downloads_count' => rand(10, 400),
            'release_date' => $v['date'],
        ]);
    }
});

// ---------- pricing plans ----------
$packages->where('is_free', false)->each(function ($package) {
    PackagePricingPlan::updateOrCreate(['package_id' => $package->id, 'name' => 'یک‌ساله'], [
        'duration_months' => 12, 'price' => $package->default_price, 'discount_price' => (int) ($package->default_price * 0.85),
        'is_one_time' => false, 'description' => 'لایسنس ۱۲ ماهه با آپدیت رایگان', 'is_active' => true, 'sort_order' => 1,
    ]);
    PackagePricingPlan::updateOrCreate(['package_id' => $package->id, 'name' => 'خرید دائمی'], [
        'duration_months' => 0, 'price' => (int) ($package->default_price * 2), 'discount_price' => null,
        'is_one_time' => true, 'description' => 'دسترسی دائمی + ۲ سال آپدیت', 'is_active' => true, 'sort_order' => 2,
    ]);
});

// ---------- customers ----------
$customerNames = [
    ['name' => 'علی رضایی', 'email' => 'ali@example.com', 'phone' => '09121234567', 'site' => 'https://ali-shop.ir'],
    ['name' => 'مریم حسینی', 'email' => 'maryam@example.com', 'phone' => '09351112233', 'site' => 'https://maryamstore.com'],
    ['name' => 'شرکت پارس‌فن', 'email' => 'info@parsfan.ir', 'phone' => '02155667788', 'site' => 'https://parsfan.ir'],
    ['name' => 'حسن کریمی', 'email' => 'hassan@example.com', 'phone' => '09199876543', 'site' => null],
    ['name' => 'نرگس محمدی', 'email' => 'narges@example.com', 'phone' => '09361239876', 'site' => 'https://narges.shop'],
    ['name' => 'فروشگاه دیجی‌لند', 'email' => 'support@digiland.ir', 'phone' => '05138889977', 'site' => 'https://digiland.ir'],
    ['name' => 'مهدی توکلی', 'email' => 'mehdi@example.com', 'phone' => '09121110000', 'site' => null],
    ['name' => 'آتنا شریفی', 'email' => 'atena@example.com', 'phone' => '09301234567', 'site' => 'https://atena.ir'],
];
$customers = collect($customerNames)->map(function ($c, $i) {
    $created = now()->subDays(rand(10, 160));
    return Customer::updateOrCreate(['email' => $c['email']], $c + [
        'website_url' => $c['site'] ?? ('https://' . Str::slug($c['name']) . '.ir'),
        'status' => $i === 3 ? 'inactive' : 'active',
        'created_at' => $created, 'updated_at' => $created,
    ]);
});

// ---------- updates (legacy project updates) ----------
$updates = collect([
    ['title' => 'رفع باگ پرداخت', 'version' => '2.5.1', 'status' => 'active'],
    ['title' => 'افزودن گزارش فروش', 'version' => '2.5.0', 'status' => 'active'],
    ['title' => 'بازطراحی پنل کاربری', 'version' => '2.4.0', 'status' => 'archived'],
    ['title' => 'بهبود سرعت صفحه‌محصول', 'version' => '2.6.0-beta', 'status' => 'draft'],
]);
$updates->each(function ($u, $i) use ($projects) {
    Update::updateOrCreate(['title' => $u['title'], 'version' => $u['version']], [
        'project_id' => $projects[0]->id,
        'description' => $u['title'] . ' — جزئیات کامل در CHANGELOG.',
        'type' => ['patch', 'minor', 'major'][$i % 3],
        'download_link' => 'updates/' . Str::slug($u['title']) . '.zip',
        'file_size' => '2.5MB',
        'release_date' => now()->subDays(rand(1, 90)),
        'status' => $u['status'],
        'is_mandatory' => $i === 0,
        'created_at' => now()->subDays(rand(1, 90)),
    ]);
});

// ---------- subscriptions ----------
$customers->take(5)->each(function ($customer, $i) use ($projects) {
    $price = $i % 2 ? 800000 : 350000;
    $start = now()->subMonths(2);
    Subscription::updateOrCreate(['customer_id' => $customer->id, 'start_date' => $start->format('Y-m-d')], [
        'project_id' => $projects[$i % 2]->id,
        'status' => $i === 4 ? 'expired' : 'active',
        'start_date' => $start,
        'end_date' => $i === 4 ? now()->subDays(5) : now()->addMonths(10),
        'expires_at' => $i === 4 ? now()->subDays(5) : now()->addMonths(10),
        'price' => $price,
        'discount' => 0,
        'final_amount' => $price,
        'payment_status' => 'paid',
        'description' => 'اشتراک ' . ($i % 2 ? 'حرفه‌ای' : 'پایه'),
    ]);
});

// ---------- purchases + licenses ----------
$paidPackages = $packages->where('is_free', false)->values();
$customers->take(6)->each(function ($customer, $i) use ($paidPackages, $packages) {
    $package = $paidPackages[$i % $paidPackages->count()];
    $paidAt = now()->subDays(rand(2, 80));
    $months = 12;

    $purchase = PackagePurchase::updateOrCreate(['customer_id' => $customer->id, 'package_id' => $package->id, 'created_at' => $paidAt->copy()], [
        'version_id' => $package->versions()->latest('id')->first()?->id,
        'pricing_plan_id' => $package->pricingPlans()->first()?->id,
        'transaction_id' => 'TRX' . strtoupper(Str::random(8)),
        'amount' => $package->default_price,
        'gateway' => collect(['zarinpal', 'idpay', 'payping'])->random(),
        'status' => $i === 5 ? 'pending' : 'paid',
        'paid_at' => $i === 5 ? null : $paidAt,
        'created_at' => $paidAt,
    ]);

    if ($purchase->status === 'paid') {
        PackageLicense::updateOrCreate(['customer_id' => $customer->id, 'package_id' => $package->id, 'status' => 'active'], [
            'license_key' => 'LIC-' . strtoupper(Str::random(4)) . '-' . strtoupper(Str::random(4)) . '-' . strtoupper(Str::random(4)),
            'purchase_id' => $purchase->id,
            'status' => $i === 2 ? 'revoked' : 'active',
            'starts_at' => $paidAt,
            'expires_at' => $i === 4 ? now()->addDays(9) : $paidAt->copy()->addMonths($months),
            'duration_months' => $months,
            'notes' => $i === 2 ? 'نقض قوانین استفاده' : null,
        ]);
        $package->increment('purchases_count');
    }
});

// one expired license
$expiredCustomer = $customers[7];
$anyPkg = $paidPackages[0];
PackageLicense::updateOrCreate(['customer_id' => $expiredCustomer->id, 'package_id' => $anyPkg->id, 'status' => 'expired'], [
    'license_key' => 'LIC-' . strtoupper(Str::random(4)) . '-' . strtoupper(Str::random(4)) . '-EXPI',
    'starts_at' => now()->subMonths(14),
    'expires_at' => now()->subDays(20),
    'duration_months' => 12,
]);

// ---------- subscription plans (طرح‌های اشتراک) ----------
$planData = [
    [
        'name' => 'اشتراک پایه', 'slug' => 'basic-monthly', 'duration' => 1, 'price' => 0, 'discount' => null,
        'one_time' => true, 'active' => true, 'order' => 1,
        'description' => 'برای آشنایی با سیستم؛ یک‌ماهه و کاملاً رایگان — فقط یک‌بار قابل استفاده.',
        'features' => ['پشتیبانی رایگان', 'دریافت آپدیت‌های امنیتی', 'دسترسی به پکیج‌های منتخب'],
    ],
    [
        'name' => 'اشتراک حرفه‌ای', 'slug' => 'pro-quarterly', 'duration' => 3, 'price' => 1200000, 'discount' => 300000,
        'one_time' => false, 'active' => true, 'order' => 2,
        'description' => 'سه ماه دسترسی کامل با اولویت پشتیبانی و پکیج‌های رایگان همراه.',
        'features' => ['پشتیبانی اولویت‌دار (۴۸ ساعت)', 'نصب رایگان پکیج‌ها', 'مشاوره سئو ماهانه', 'گزارش عملکرد پروژه'],
    ],
    [
        'name' => 'اشتراک ویژه سالانه', 'slug' => 'vip-yearly', 'duration' => 12, 'price' => 4800000, 'discount' => 1300000,
        'one_time' => false, 'active' => true, 'order' => 3,
        'description' => 'کامل‌ترین طرح: یک سال، همه پکیج‌ها یک‌ماه رایگان + پشتیبانی اختصاصی.',
        'features' => ['پشتیبانی اختصاصی ۲۴ ساعته', 'نصب و راه‌اندازی کامل', 'بهینه‌سازی کارایی سالانه', 'تمدید رایگان لایسنس‌ها', 'دسترسی زودهنگام به نسخه‌های بتا'],
    ],
];

$plans = collect($planData)->map(function ($p) {
    return SubscriptionPlan::updateOrCreate(['slug' => $p['slug']], [
        'name'            => $p['name'],
        'description'     => $p['description'],
        'duration_months' => $p['duration'],
        'price'           => $p['price'],
        'discount_price'  => $p['discount'],
        'is_one_time'     => $p['one_time'],
        'is_active'       => $p['active'],
        'sort_order'      => $p['order'],
        'features'        => $p['features'],
    ]);
});

// پکیج‌های همراه هر طرح (free_months)
$planPackages = [
    'basic-monthly'   => [['kavenegar-sms', 1]],
    'pro-quarterly'   => [['kavenegar-sms', 3], ['seo-pro', 2], ['modern-shop-theme', 0]],
    'vip-yearly'      => [['behpardakht-gateway', 1], ['kavenegar-sms', 1], ['seo-pro', 1], ['modern-shop-theme', 0]],
];
$slugToId = $packages->pluck('id', 'slug');
foreach ($planPackages as $planSlug => $rows) {
    $plan = $plans->firstWhere('slug', $planSlug);
    $sync = [];
    foreach ($rows as [$pkgSlug, $months]) {
        if ($slugToId->has($pkgSlug)) {
            $sync[$slugToId[$pkgSlug]] = ['free_months' => $months];
        }
    }
    $plan->packages()->sync($sync);
}

// ---------- subscription orders (درخواست‌ها برای دموی پنل) ----------
// ۱) سفارش پرداخت‌شده در انتظار تأیید مدیر (مریم حسینی → حرفه‌ای)
$proPlan = $plans->firstWhere('slug', 'pro-quarterly');
$pendingCustomer = $customers[1];
$pendingOrder = SubscriptionOrder::updateOrCreate(['customer_id' => $pendingCustomer->id, 'subscription_plan_id' => $proPlan->id, 'status' => 'paid'], [
    'amount'       => $proPlan->price,
    'discount'     => $proPlan->discount_price,
    'final_amount' => $proPlan->final_price,
    'gateway'      => 'local',
    'transaction_id' => 'TRXSUB' . strtoupper(Str::random(6)),
    'status'       => 'paid',
    'admin_status' => 'pending',
    'paid_at'      => now()->subHours(3),
    'meta'         => ['plan' => [
        'name' => $proPlan->name, 'slug' => $proPlan->slug, 'duration_months' => $proPlan->duration_months,
        'features' => $proPlan->features, 'price' => $proPlan->price, 'final_price' => $proPlan->final_price, 'is_one_time' => $proPlan->is_one_time,
        'packages' => $proPlan->packages()->get(['packages.id', 'packages.name', 'packages.slug'])->map(fn ($p) => ['id' => $p->id, 'name' => $p->name, 'slug' => $p->slug, 'free_months' => (int) $p->pivot->free_months])->values()->all(),
    ]],
]);

// ۲) سفارش رایگان (پایه) تأییدشده + اشتراک فعال + لایسنس پکیج همراه
$basicPlan = $plans->firstWhere('slug', 'basic-monthly');
$freeCustomer = $customers[0]; // علی رضایی
$approvedOrder = SubscriptionOrder::updateOrCreate(['customer_id' => $freeCustomer->id, 'subscription_plan_id' => $basicPlan->id, 'status' => 'paid'], [
    'amount'       => 0,
    'discount'     => 0,
    'final_amount' => 0,
    'status'       => 'paid',
    'admin_status' => 'approved',
    'paid_at'      => now()->subDays(5),
    'approved_at'  => now()->subDays(4),
    'starts_at'    => now()->subDays(4),
    'expires_at'   => now()->subDays(4)->addMonth(),
    'meta'         => ['plan' => [
        'name' => $basicPlan->name, 'slug' => $basicPlan->slug, 'duration_months' => $basicPlan->duration_months,
        'features' => $basicPlan->features, 'price' => 0, 'final_price' => 0, 'is_one_time' => $basicPlan->is_one_time,
        'packages' => $basicPlan->packages()->get(['packages.id', 'packages.name', 'packages.slug'])->map(fn ($p) => ['id' => $p->id, 'name' => $p->name, 'slug' => $p->slug, 'free_months' => (int) $p->pivot->free_months])->values()->all(),
    ]],
]);
$approvedSubscription = Subscription::updateOrCreate(['subscription_order_id' => $approvedOrder->id], [
    'customer_id'          => $freeCustomer->id,
    'project_id'           => null,
    'subscription_plan_id' => $basicPlan->id,
    'start_date'           => now()->subDays(4)->toDateString(),
    'end_date'             => now()->subDays(4)->addMonth()->toDateString(),
    'expires_at'           => now()->subDays(4)->addMonth(),
    'status'               => 'active',
    'price'                => 0,
    'discount'             => 0,
    'final_amount'         => 0,
    'payment_status'       => 'paid',
    'description'          => 'اشتراک «پایه» — فعال‌شده پس از تأیید مدیر.',
]);
$approvedOrder->update(['subscription_id' => $approvedSubscription->id]);

// لایسنسِ دسترسی رایگان صادرشده از طریق اشتراک تأییدشده (علی + kavenegar-sms یک‌ماه)
$grantPkg = $packages->firstWhere('slug', 'kavenegar-sms');
if ($grantPkg) {
    PackageLicense::updateOrCreate([
        'customer_id' => $freeCustomer->id,
        'package_id'  => $grantPkg->id,
        'notes'       => 'دسترسی رایگان از طریق اشتراک «' . $basicPlan->name . '».',
    ], [
        'license_key'     => 'LIC-' . strtoupper(Str::random(4)) . '-' . strtoupper(Str::random(4)) . '-SUB1',
        'status'          => 'active',
        'starts_at'       => now()->subDays(4),
        'expires_at'      => now()->subDays(4)->addMonth(),
        'duration_months' => 1,
        'notes'           => 'دسترسی رایگان از طریق اشتراک «' . $basicPlan->name . '».',
    ]);
}

// ---------- admin user (برای ورود اولیه پنل) ----------
User::updateOrCreate(
    ['email' => 'admin@panel.test'],
    ['name' => 'مدیر سیستم', 'password' => bcrypt('secret123'), 'status' => 'active'],
);

// ============================================================
//  انتقال دسته‌های متنی پکیج‌ها → جدول categories (type=package)
//  (همان منطق مایگریشن backfill — برای نصب تازه هم دسته‌بندی دمو ساخته شود)
// ============================================================
$legacyCats = Package::query()
    ->whereNotNull('category')->where('category', '!=', '')
    ->distinct()->orderBy('category')->pluck('category');

$catLabels = \App\Livewire\Packages\Index::CATEGORIES;

foreach ($legacyCats as $legacyName) {
    $cat = Category::firstOrCreate(
        ['type' => 'package', 'name' => $catLabels[$legacyName] ?? $legacyName],
        ['slug' => Str::slug($legacyName) ?: ('pkg-' . Str::lower(Str::random(6))), 'is_active' => true, 'sort_order' => 50]
    );

    Package::where('category', $legacyName)->update(['category_id' => $cat->id]);
}

// ============================================================
//  محصولات قابل‌فروش (پروژه‌ها — خرید مهمان)
// ============================================================

// ---------- دسته‌بندی‌های محصول ----------
$productCats = collect([
    ['name' => 'اسکریپت',   'slug' => 'script',      'sort_order' => 1],
    ['name' => 'قالب',      'slug' => 'template',    'sort_order' => 2],
    ['name' => 'اپلیکیشن',  'slug' => 'application', 'sort_order' => 3],
    ['name' => 'افزونه',    'slug' => 'plugin',      'sort_order' => 4],
    ['name' => 'پنل آماده', 'slug' => 'ready-panel', 'sort_order' => 5],
    ['name' => 'سایر',      'slug' => 'misc',        'sort_order' => 99],
])->mapWithKeys(fn ($c) => [Category::updateOrCreate(['type' => 'product', 'slug' => $c['slug']], $c + ['is_active' => true])->slug => true]);

// ---------- محصولات دمو ----------
$demoProducts = [
    [
        'slug' => 'laravel-shop-script', 'category' => 'script', 'price' => 4900000, 'discount' => 20, 'is_free' => false,
        'name' => 'اسکریپت فروشگاهی لاراول (ShopPro)',
        'short_description' => "فروشگاه‌ساز کامل با لاراول ۱۳ — پنل مدیریت، درگاه‌های پرداخت ایرانی، مدیریت محصول و سفارش، سئو و گزارش‌گیری. نصب پنج‌دقیقه‌ای با مستندات فارسی.",
        'description' => "<h2>اسکریپت فروشگاهی ShopPro</h2><p>یک فروشگاه اینترنتی کامل و آماده راه‌اندازی بر پایه <strong>Laravel 13 + Livewire 3</strong> با طراحی مدرن RTL و فونت وزیرمتن.</p><h3>امکانات کلیدی</h3><ul><li>پنل مدیریت کامل (محصول، سفارش، مشتری، تخفیف)</li><li>۱۲ درگاه پرداخت ایرانی آماده</li><li>چندفروشندگی و مدیریت انبار</li><li>سئو پیشرفته + اسکیما</li><li>نصب آسان با ویزارد فارسی</li></ul><p>مستندات نصب و توسعه به‌همراه فایل ارائه می‌شود.</p>",
        'sales' => 34, 'views' => 1280, 'sort' => 1,
        'versions' => [
            ['v' => '3.2.0', 'type' => 'minor', 'changelog' => "افزودن درگاه زیبال\nبهبود سرعت صفحه محصول\nرفع باگ محاسبه ارسال رایگان", 'added' => 'درگاه زیبال + صف بررسی سفارش‌ها', 'changed' => 'کش توضیحات محصول', 'fixed' => 'باگ محاسبه ارسال رایگان برای سبد بالای ۵ میلیون'],
            ['v' => '3.1.0', 'type' => 'minor', 'changelog' => "افزودن گزارش فروش ماهانه\nپشتیبانی از PHP 8.4", 'added' => 'گزارش فروش ماهانه', 'changed' => null, 'fixed' => null],
            ['v' => '3.0.0', 'type' => 'major', 'changelog' => "بازطراحی کامل پنل با Tailwind 4\nمهاجرت به Livewire 3", 'added' => null, 'changed' => 'مهاجرت کامل به Livewire 3', 'fixed' => null],
        ],
    ],
    [
        'slug' => 'admin-dashboard-template', 'category' => 'template', 'price' => 1900000, 'discount' => 0, 'is_free' => false,
        'name' => 'قالب پنل مدیریت (AdminKit RTL)',
        'short_description' => "قالب HTML پنل مدیریت RTL با بیش از ۴۰ صفحه آماده، دارک‌مود، چارت و جدول‌های پیشرفته — بدون وابستگی به فریم‌ورک.",
        'description' => "<h2>قالب پنل مدیریت AdminKit</h2><p>بیش از <strong>۴۰ صفحه آماده</strong> با طراحی تمیز، دارک‌مود خودکار و کامپوننت‌های Tailwind CSS 4.</p><ul><li>داشبورد، جدول‌ها، فرم‌ها، چارت</li><li>RTL کامل + LTR</li><li>بدون jQuery — Alpine.js</li></ul>",
        'sales' => 18, 'views' => 760, 'sort' => 2,
        'versions' => [
            ['v' => '2.1.0', 'type' => 'minor', 'changelog' => "۵ صفحه جدید (چت، کانبان، تقویم)\nبهبود دسترسی‌پذیری", 'added' => 'صفحات چت، کانبان و تقویم', 'changed' => null, 'fixed' => 'رفع مشکل اسکرول در سافاری'],
            ['v' => '2.0.0', 'type' => 'major', 'changelog' => "مهاجرت به Tailwind 4", 'added' => null, 'changed' => 'Tailwind 4 + فونت وزیرمتن', 'fixed' => null],
        ],
    ],
    [
        'slug' => 'inventory-app', 'category' => 'application', 'price' => 7800000, 'discount' => 10, 'is_free' => false,
        'name' => 'اپلیکیشن مدیریت انبار (StockMaster)',
        'short_description' => "سامانه تحت وب مدیریت انبار با بارکدخوان، حواله ورود/خروج، شمارش موجودی و اتصال به فروشگاه — مناسب کسب‌وکارهای متوسط.",
        'description' => "<h2>StockMaster — مدیریت انبار حرفه‌ای</h2><p>سامانه کامل انبار با <strong>پشتیبانی بارکد</strong>، حواله‌های ورود/خروج، گردش کالا و گزارش‌های لحظه‌ای.</p><ul><li>چند انبار و چند شعبه</li><li>اتصال به فروشگاه‌ساز ShopPro</li><li>گزارش گردش کالا و کم‌موجودی</li></ul>",
        'sales' => 9, 'views' => 430, 'sort' => 3,
        'versions' => [
            ['v' => '1.4.2', 'type' => 'patch', 'changelog' => "رفع باگ چاپ حواله\nبهبود سرعت جستجوی کالا", 'added' => null, 'changed' => null, 'fixed' => 'باگ چاپ حواله در فایرفاکس'],
        ],
    ],
    [
        'slug' => 'seo-tools-pack', 'category' => 'plugin', 'price' => 0, 'discount' => 0, 'is_free' => true,
        'name' => 'پک ابزار سئو (رایگان)',
        'short_description' => "مجموعه ابزار رایگان سئو: تولید اسکیما، بررسی متاتگ، مپ XML خودکار و آنالیز کلمات کلیدی — هدیه فروشگاه.",
        'description' => "<h2>پک ابزار سئو — رایگان!</h2><p>چهار ابزار کاربردی سئو در یک بسته: تولیدکننده <strong>JSON-LD Schema</strong>، بررسی متاتگ، SiteMap خودکار و پیشنهاد کلمه کلیدی.</p>",
        'sales' => 210, 'views' => 3120, 'sort' => 4,
        'versions' => [
            ['v' => '1.0.0', 'type' => 'minor', 'changelog' => "انتشار اولیه", 'added' => null, 'changed' => null, 'fixed' => null],
        ],
    ],
];

foreach ($demoProducts as $row) {
    $product = Product::updateOrCreate(
        ['slug' => $row['slug']],
        [
            'category_id'       => Category::where('type', 'product')->where('slug', $row['category'])->value('id'),
            'name'              => $row['name'],
            'short_description' => $row['short_description'],
            'description'       => $row['description'],
            'price'             => $row['price'],
            'discount'          => $row['discount'],
            'is_free'           => $row['is_free'],
            'status'            => 'active',
            'sort_order'        => $row['sort'],
            'sales_count'       => $row['sales'],
            'views_count'       => $row['views'],
        ]
    );

    // نسخه‌ها + فایل ZIP دمو
    foreach ($row['versions'] as $i => $v) {
        $existing = ProductVersion::where('product_id', $product->id)->where('version', $v['v'])->first();

        $filePath = null; $fileHash = null; $fileSize = null;

        $dir = storage_path('app/private/products/' . $product->slug);
        if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
        $zipPath = $dir . '/v' . $v['v'] . '.zip';

        if (!file_exists($zipPath)) {
            $zip = new ZipArchive();
            if ($zip->open($zipPath, ZipArchive::CREATE) === true) {
                $zip->addFromString('README.txt',
                    "محصول: {$product->name}\nنسخه: {$v['v']}\n\nاین یک فایل دموی فروشگاه آپدیت‌سنتر است.\nفایل واقعی هر نسخه را از پنل مدیریت آپلود کنید.\n");
                $zip->addFromString('docs/install-fa.txt', "راهنمای نصب نسخه {$v['v']} — مستندات دمو\n");
                $zip->close();
            }
        }

        if (file_exists($zipPath)) {
            $filePath = 'products/' . $product->slug . '/v' . $v['v'] . '.zip';
            $fileHash = hash_file('sha256', $zipPath);
            $fileSize = filesize($zipPath);
        }

        $payload = [
            'type'         => $v['type'],
            'changelog'    => $v['changelog'],
            'what_added'   => $v['added'],
            'what_changed' => $v['changed'],
            'what_fixed'   => $v['fixed'],
            'file_path'    => $filePath,
            'file_hash'    => $fileHash,
            'file_size'    => $fileSize,
            'status'       => 'active',
            'release_date' => now()->subDays($i * 21 + 5),
        ];

        $existing ? $existing->update($payload) : $product->versions()->create(['version' => $v['v']] + $payload);
    }

    // خرید دمو (paid) برای محصول پولی — برای نمایش آمار
    if (!$row['is_free'] && !$product->purchases()->where('status', 'paid')->exists()) {
        $latestV = $product->latestVersion()->first();
        if ($latestV) {
            ProductPurchase::create([
                'product_id'    => $product->id,
                'version_id'    => $latestV->id,
                'transaction_id' => 'DEMO-PROD-' . strtoupper(Str::random(10)),
                'amount'        => $product->final_price,
                'gateway'       => 'local',
                'status'        => 'paid',
                'paid_at'       => now()->subDays(rand(2, 20)),
                'meta'          => ['demo' => true],
            ]);
        }
    }

    // دیدگاه‌های دمو (تأییدشده + یک در انتظار)
    if (!$product->comments()->exists()) {
        $demoComments = [
            ['name' => 'سینا مرادی',   'rating' => 5, 'status' => 'approved', 'body' => 'کیفیت کد عالیه، مستندات هم کامل بود. نصبش ده دقیقه طول کشید و بدون مشکل بالا اومد. حتماً پیشنهاد می‌کنم.'],
            ['name' => 'نازنین احمدی', 'rating' => 4, 'status' => 'approved', 'body' => 'بسیار خوب و تمیز. فقط ای کاش تعداد قالب‌های آماده بیشتر بود. پشتیبانی هم سریع جواب داد.'],
            ['name' => 'کاربر مهمان',  'rating' => null, 'status' => 'pending', 'body' => 'این نسخه با PHP 8.4 هم سازگاره؟ ممنون می‌شم قبل از خرید راهنمایی کنید.'],
        ];

        foreach ($demoComments as $ci => $c) {
            $product->comments()->create($c + [
                'email'       => null,
                'approved_at' => $c['status'] === 'approved' ? now()->subDays(rand(1, 15)) : null,
                'created_at'  => now()->subDays(rand(3, 30)),
                'ip'          => '127.0.0.1',
            ]);
        }
    }
}

echo "Demo data seeded OK\n";
echo 'Projects: ' . Project::count() . ' | Packages: ' . Package::count() . ' | Versions: ' . PackageVersion::count()
    . ' | Plans: ' . PackagePricingPlan::count() . ' | Customers: ' . Customer::count()
    . ' | Licenses: ' . PackageLicense::count() . ' | Purchases: ' . PackagePurchase::count()
    . ' | Subscriptions: ' . Subscription::count() . ' | Updates: ' . Update::count()
    . ' | SubPlans: ' . SubscriptionPlan::count() . ' | SubOrders: ' . SubscriptionOrder::count() . "\n";
echo 'Products: ' . Product::count() . ' | ProductVersions: ' . ProductVersion::count()
    . ' | ProductComments: ' . ProductComment::count()
    . ' | ProductPurchases: ' . ProductPurchase::count()
    . ' | Categories: ' . Category::count() . "\n";
