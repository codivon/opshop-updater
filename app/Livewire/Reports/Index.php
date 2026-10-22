<?php

namespace App\Livewire\Reports;

use App\Livewire\Concerns\WithToasts;
use App\Models\Customer;
use App\Models\Package;
use App\Models\PackageLicense;
use App\Models\PackagePurchase;
use App\Models\Product;
use App\Models\ProductPurchase;
use App\Models\Project;
use App\Models\Subscription;
use App\Models\SubscriptionOrder;
use App\Models\SubscriptionPlan;
use App\Models\Template;
use App\Models\TemplateLicense;
use App\Models\TemplatePurchase;
use App\Models\Update;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
#[Title('گزارش‌ها')]
class Index extends Component
{
    use WithPagination, WithToasts;

    /** تب مشتریان: بازه تاریخ عضویت */
    #[Url]
    public string $from = '';

    #[Url]
    public string $to = '';

    /** تب آپدیت‌ها: فیلتر پروژه و وضعیت */
    #[Url]
    public string $project = '';

    #[Url]
    public string $status = '';

    /** تب فروش: بازه زمانی — today | week | month | year | custom | all */
    #[Url]
    public string $period = 'month';

    /** تب فروش: بازه دلخواه (شمسی یا میلادی) */
    #[Url]
    public string $salesFrom = '';

    #[Url]
    public string $salesTo = '';

    public function updatedFrom(): void
    {
        $this->resetPage('customersPage');
    }

    public function updatedTo(): void
    {
        $this->resetPage('customersPage');
    }

    public function updatedProject(): void
    {
        $this->resetPage('updatesPage');
    }

    public function updatedStatus(): void
    {
        $this->resetPage('updatesPage');
    }

    public function resetFilters(): void
    {
        $this->reset(['from', 'to', 'project', 'status']);
        $this->resetPage('customersPage');
        $this->resetPage('updatesPage');
    }

    /* ------------------------------------------------------------------ */
    /*  تب «فروش» — فیلتر بازه زمانی                                       */
    /* ------------------------------------------------------------------ */

    public function updatedPeriod(): void
    {
        $this->resetPage('salesPage');
    }

    public function updatedSalesFrom(): void
    {
        $this->resetPage('salesPage');
    }

    public function updatedSalesTo(): void
    {
        $this->resetPage('salesPage');
    }

    /** کلیک روی دکمه‌های سریع بازه — بازه دلخواه را پاک می‌کند */
    public function setPeriod(string $period): void
    {
        if (!in_array($period, ['today', 'week', 'month', 'year', 'custom', 'all'], true)) {
            return;
        }

        $this->period = $period;

        if ($period !== 'custom') {
            $this->salesFrom = '';
            $this->salesTo = '';
        }

        $this->resetPage('salesPage');
    }

    public function resetSalesFilters(): void
    {
        $this->period = 'month';
        $this->salesFrom = '';
        $this->salesTo = '';
        $this->resetPage('salesPage');
    }

    /* ------------------------------------------------------------------ */
    /*  تب «نمای کلی»                                                      */
    /* ------------------------------------------------------------------ */

    #[Computed]
    public function overviewStats(): array
    {
        $totalCustomers = Customer::count();
        $activeCustomers = Customer::where('status', 'active')->count();

        $totalUpdates = Update::count();
        $activeUpdates = Update::where('status', Update::STATUS_ACTIVE)->count();

        $totalSubscriptions = Subscription::count();
        $activeSubscriptions = Subscription::where('status', 'active')
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->count();

        $subscriptionsRevenue = (int) Subscription::where('payment_status', 'paid')->sum('final_amount');
        $packageRevenue = (int) PackagePurchase::where('status', PackagePurchase::STATUS_PAID)->sum('amount');
        $productRevenue = (int) ProductPurchase::where('status', ProductPurchase::STATUS_PAID)->sum('amount');
        $templateRevenue = (int) TemplatePurchase::where('status', TemplatePurchase::STATUS_PAID)->sum('amount');

        $planStats = $this->plansStats;

        return [
            [
                'label' => 'کل مشتریان',
                'value' => $totalCustomers,
                'icon' => 'users',
                'variant' => 'primary',
                'hint' => fa_num($activeCustomers) . ' فعال',
                'href' => route('admin.customers.index'),
            ],
            [
                'label' => 'آپدیت‌های فعال',
                'value' => $activeUpdates,
                'icon' => 'git-branch',
                'variant' => 'info',
                'hint' => 'از ' . fa_num($totalUpdates) . ' آپدیت',
                'href' => route('admin.updates.index'),
            ],
            [
                'label' => 'اشتراک‌های فعال',
                'value' => $activeSubscriptions,
                'icon' => 'ticket',
                'variant' => 'warning',
                'hint' => 'از ' . fa_num($totalSubscriptions) . ' اشتراک',
                'href' => route('admin.subscriptions.index'),
            ],
            [
                'label' => 'قالب‌های فعال',
                'value' => Template::where('status', Template::STATUS_ACTIVE)->where('is_sold_exclusively', false)->count(),
                'icon' => 'layout-template',
                'variant' => 'violet',
                'hint' => 'درآمد: ' . money($templateRevenue, false) . ' تومان',
                'href' => route('admin.templates.index'),
            ],
            [
                'label' => 'محصولات فعال',
                'value' => Product::where('status', Product::STATUS_ACTIVE)->count(),
                'icon' => 'shopping-bag',
                'variant' => 'info',
                'hint' => 'درآمد: ' . money($productRevenue, false) . ' تومان',
                'href' => route('admin.products.index'),
            ],
            [
                'label' => 'لایسنس‌های فعال',
                'value' => $this->licensesStats['package']['active'] + $this->licensesStats['template']['active'],
                'icon' => 'key',
                'variant' => 'warning',
                'hint' => fa_num($this->licensesStats['template']['active']) . ' قالب + ' . fa_num($this->licensesStats['package']['active']) . ' پکیج',
                'href' => route('admin.licenses.index'),
            ],
            [
                'label' => 'کاربران پنل',
                'value' => User::count(),
                'icon' => 'shield-check',
                'variant' => 'violet',
                'hint' => 'مدیران و اپراتورها',
                'href' => route('admin.users.index'),
            ],
            [
                'label' => 'خریدهای موفق پکیج',
                'value' => PackagePurchase::where('status', PackagePurchase::STATUS_PAID)->count(),
                'icon' => 'package',
                'variant' => 'neutral',
                'hint' => PackagePurchase::where('status', PackagePurchase::STATUS_PENDING)->count() . ' در انتظار پرداخت',
                'href' => route('admin.purchases.index'),
            ],
            [
                'label' => 'سفارش‌های اشتراک',
                'value' => $planStats['total_orders'],
                'icon' => 'crown',
                'variant' => 'warning',
                'hint' => fa_num($planStats['pending_orders']) . ' در انتظار تأیید',
                'href' => route('admin.subscriptions.orders'),
            ],
            [
                'label' => 'درآمد طرح‌ها',
                'value' => money($planStats['plan_revenue'], false),
                'icon' => 'wallet',
                'variant' => 'primary',
                'hint' => 'سفارش‌های پرداخت‌شده طرح‌ها',
                'href' => route('admin.plans.index'),
            ],
            [
                'label' => 'درآمد کل',
                'value' => money($subscriptionsRevenue + $packageRevenue + $productRevenue + $templateRevenue, false),
                'icon' => 'wallet',
                'variant' => 'primary',
                'hint' => 'اشتراک + پکیج + محصول + قالب (تومان)',
                'href' => route('admin.reports.index'),
            ],
        ];
    }

    #[Computed]
    public function customerChart(): array
    {
        $data = collect(range(5, 0))->map(function ($i) {
            $date = Carbon::now()->subMonths($i);

            return [
                'label' => verta_date($date, 'Y/m'),
                'value' => Customer::whereYear('created_at', $date->year)
                    ->whereMonth('created_at', $date->month)->count(),
            ];
        });

        return ['title' => 'روند ثبت‌نام مشتریان (۶ ماه اخیر)', 'series' => $data->all(), 'color' => 'brand'];
    }

    #[Computed]
    public function subscriptionBreakdown(): array
    {
        $total = Subscription::count();
        $active = Subscription::where('status', 'active')
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->count();
        $expired = Subscription::where('status', 'active')
            ->where('expires_at', '<=', now())
            ->count();

        return [
            'total' => $total,
            'active' => $active,
            'expired' => $expired,
            'inactive' => max(0, $total - $active - $expired),
        ];
    }

    #[Computed]
    public function summaryMetrics(): array
    {
        $totalCustomers = Customer::count();
        $activeCustomers = Customer::where('status', 'active')->count();

        $updates = [
            'total' => Update::count(),
            'active' => Update::where('status', Update::STATUS_ACTIVE)->count(),
            'draft' => Update::where('status', Update::STATUS_DRAFT)->count(),
            'archived' => Update::where('status', Update::STATUS_ARCHIVED)->count(),
        ];

        $totalSubscriptions = Subscription::count();
        $activeSubscriptions = Subscription::where('status', 'active')
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->count();
        $expiredSubscriptions = Subscription::where('status', 'active')
            ->where('expires_at', '<=', now())
            ->count();

        $totalPurchases = PackagePurchase::count();
        $paidPurchases = PackagePurchase::where('status', PackagePurchase::STATUS_PAID)->count();
        $pendingPurchases = PackagePurchase::where('status', PackagePurchase::STATUS_PENDING)->count();
        $failedPurchases = PackagePurchase::where('status', PackagePurchase::STATUS_FAILED)->count();

        $subscriptionsRevenue = (int) Subscription::where('payment_status', 'paid')->sum('final_amount');
        $packageRevenue = (int) PackagePurchase::where('status', PackagePurchase::STATUS_PAID)->sum('amount');
        $productRevenue = (int) ProductPurchase::where('status', ProductPurchase::STATUS_PAID)->sum('amount');
        $templateRevenue = (int) TemplatePurchase::where('status', TemplatePurchase::STATUS_PAID)->sum('amount');
        $templateExclusiveRevenue = (int) TemplatePurchase::where('status', TemplatePurchase::STATUS_PAID)
            ->where('purchase_type', TemplatePurchase::TYPE_EXCLUSIVE)->sum('amount');

        $planStats = $this->plansStats;
        $licStats = $this->licensesStats;

        return [
            [
                'icon' => 'users',
                'title' => 'مشتریان',
                'rows' => [
                    ['label' => 'کل مشتریان', 'value' => fa_num($totalCustomers)],
                    ['label' => 'فعال', 'value' => fa_num($activeCustomers)],
                    ['label' => 'غیرفعال', 'value' => fa_num($totalCustomers - $activeCustomers)],
                ],
            ],
            [
                'icon' => 'git-branch',
                'title' => 'آپدیت‌ها',
                'rows' => [
                    ['label' => 'کل آپدیت‌ها', 'value' => fa_num($updates['total'])],
                    ['label' => 'فعال', 'value' => fa_num($updates['active'])],
                    ['label' => 'پیش‌نویس', 'value' => fa_num($updates['draft'])],
                    ['label' => 'بایگانی شده', 'value' => fa_num($updates['archived'])],
                ],
            ],
            [
                'icon' => 'ticket',
                'title' => 'اشتراک‌ها',
                'rows' => [
                    ['label' => 'کل اشتراک‌ها', 'value' => fa_num($totalSubscriptions)],
                    ['label' => 'فعال', 'value' => fa_num($activeSubscriptions)],
                    ['label' => 'منقضی شده', 'value' => fa_num($expiredSubscriptions)],
                    ['label' => 'غیرفعال / معلق', 'value' => fa_num(max(0, $totalSubscriptions - $activeSubscriptions - $expiredSubscriptions))],
                ],
            ],
            [
                'icon' => 'package',
                'title' => 'فروش پکیج‌ها',
                'rows' => [
                    ['label' => 'کل خریدها', 'value' => fa_num($totalPurchases)],
                    ['label' => 'پرداخت‌شده', 'value' => fa_num($paidPurchases)],
                    ['label' => 'در انتظار پرداخت', 'value' => fa_num($pendingPurchases)],
                    ['label' => 'ناموفق', 'value' => fa_num($failedPurchases)],
                ],
            ],
            [
                'icon' => 'layout-template',
                'title' => 'فروش قالب‌ها',
                'rows' => [
                    ['label' => 'خرید موفق', 'value' => fa_num(TemplatePurchase::where('status', TemplatePurchase::STATUS_PAID)->count())],
                    ['label' => 'درآمد کل', 'value' => money($templateRevenue)],
                    ['label' => 'فروش اختصاصی', 'value' => fa_num(TemplatePurchase::where('status', TemplatePurchase::STATUS_PAID)->where('purchase_type', TemplatePurchase::TYPE_EXCLUSIVE)->count()) . ' (' . money($templateExclusiveRevenue) . ')'],
                    ['label' => 'قالب واگذارشده', 'value' => fa_num(Template::where('is_sold_exclusively', true)->count())],
                ],
            ],
            [
                'icon' => 'shopping-bag',
                'title' => 'فروش محصولات',
                'rows' => [
                    ['label' => 'خرید موفق', 'value' => fa_num(ProductPurchase::where('status', ProductPurchase::STATUS_PAID)->count())],
                    ['label' => 'درآمد', 'value' => money($productRevenue)],
                    ['label' => 'در انتظار', 'value' => fa_num(ProductPurchase::where('status', ProductPurchase::STATUS_PENDING)->count())],
                ],
            ],
            [
                'icon' => 'key',
                'title' => 'لایسنس‌ها',
                'rows' => [
                    ['label' => 'لایسنس پکیج (فعال)', 'value' => fa_num($licStats['package']['active']) . ' از ' . fa_num($licStats['package']['total'])],
                    ['label' => 'لایسنس قالب (فعال)', 'value' => fa_num($licStats['template']['active']) . ' از ' . fa_num($licStats['template']['total'])],
                    ['label' => 'باطل‌شده', 'value' => fa_num($licStats['package']['revoked'] + $licStats['template']['revoked'])],
                    ['label' => 'منقضی', 'value' => fa_num($licStats['package']['expired'] + $licStats['template']['expired'])],
                ],
            ],
            [
                'icon' => 'crown',
                'title' => 'طرح‌های اشتراک',
                'rows' => [
                    ['label' => 'طرح فعال', 'value' => fa_num($planStats['active_plans']) . ' از ' . fa_num(SubscriptionPlan::count()) . ' طرح'],
                    ['label' => 'سفارش تأییدشده', 'value' => fa_num($planStats['approved_orders'])],
                    ['label' => 'در انتظار تأیید', 'value' => fa_num($planStats['pending_orders'])],
                    ['label' => 'ردشده', 'value' => fa_num($planStats['rejected_orders'])],
                    ['label' => 'درآمد طرح‌ها', 'value' => money($planStats['plan_revenue'])],
                ],
            ],
            [
                'icon' => 'wallet',
                'title' => 'مالی',
                'rows' => [
                    ['label' => 'درآمد اشتراک‌ها (پرداخت‌شده)', 'value' => money($subscriptionsRevenue)],
                    ['label' => 'درآمد پکیج‌ها (پرداخت‌شده)', 'value' => money($packageRevenue)],
                    ['label' => 'درآمد محصولات (پرداخت‌شده)', 'value' => money($productRevenue)],
                    ['label' => 'درآمد قالب‌ها (پرداخت‌شده)', 'value' => money($templateRevenue)],
                    ['label' => 'درآمد کل', 'value' => money($subscriptionsRevenue + $packageRevenue + $productRevenue + $templateRevenue)],
                    ['label' => 'مجموع دانلود پکیج‌ها', 'value' => fa_num((int) Package::sum('downloads_count'))],
                ],
            ],
        ];
    }

    /* ------------------------------------------------------------------ */
    /*  تب «مشتریان»                                                       */
    /* ------------------------------------------------------------------ */

    #[Computed]
    public function customersReport()
    {
        return Customer::query()
            ->withCount('subscriptions')
            ->addSelect([
                'subscriptions_spent' => Subscription::selectRaw('coalesce(sum(final_amount), 0)')
                    ->whereColumn('customer_id', 'customers.id')
                    ->where('payment_status', 'paid'),
                'purchases_spent' => PackagePurchase::selectRaw('coalesce(sum(amount), 0)')
                    ->whereColumn('customer_id', 'customers.id')
                    ->where('status', PackagePurchase::STATUS_PAID),
            ])
            ->when($this->from, fn ($q) => $q->whereDate('created_at', '>=', $this->from))
            ->when($this->to, fn ($q) => $q->whereDate('created_at', '<=', $this->to))
            ->orderByDesc('created_at')
            ->paginate(12, ['*'], 'customersPage');
    }

    /* ------------------------------------------------------------------ */
    /*  تب «آپدیت‌ها»                                                      */
    /* ------------------------------------------------------------------ */

    #[Computed]
    public function projects()
    {
        return Project::query()->orderBy('name')->get(['id', 'name']);
    }

    #[Computed]
    public function updatesReport()
    {
        return Update::query()
            ->with('project:id,name')
            ->when($this->project, fn ($q) => $q->where('project_id', (int) $this->project))
            ->when($this->status, fn ($q) => $q->where('status', $this->status))
            ->orderByDesc('created_at')
            ->paginate(12, ['*'], 'updatesPage');
    }

    /* ------------------------------------------------------------------ */
    /*  تب «فروش» — بازه، ردیف‌ها و آمار                                    */
    /* ------------------------------------------------------------------ */

    /**
     * بازه زمانی فعلی تب فروش.
     * @return array{0: ?Carbon, 1: ?Carbon, 2: string} [از، تا، برچسب]
     */
    private function salesRange(): array
    {
        $now = Carbon::now();

        switch ($this->period) {
            case 'today':
                return [$now->copy()->startOfDay(), $now->copy()->endOfDay(), 'امروز'];

            case 'week':
                return [$now->copy()->startOfWeek(), $now->copy()->endOfDay(), 'این هفته'];

            case 'year':
                return [$now->copy()->startOfYear(), $now->copy()->endOfDay(), 'امسال'];

            case 'custom':
                $from = jalali_to_carbon($this->salesFrom);
                $to   = jalali_to_carbon($this->salesTo, endOfDay: true);

                if ($from && $to && $from->gt($to)) {
                    [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
                }

                $label = 'بازه دلخواه';
                if ($from || $to) {
                    $label = ($from ? verta_date($from) : '…') . ' تا ' . ($to ? verta_date($to) : '…');
                }

                return [$from, $to, $label];

            case 'all':
                return [null, null, 'همه زمان‌ها'];

            case 'month':
            default:
                return [$now->copy()->startOfMonth(), $now->copy()->endOfDay(), 'این ماه'];
        }
    }

    /** همه فروش‌های پرداخت‌شده در بازه — یکپارچه از ۴ منبع */
    #[Computed]
    public function salesRows(): \Illuminate\Support\Collection
    {
        [$from, $to] = $this->salesRange();

        $range = fn ($query, $column) => $from
            ? $query->whereBetween($column, [$from, $to])
            : $query;

        $rows = collect();

        // ---- پکیج‌ها ----
        $packagePurchases = $range(
            PackagePurchase::query()->where('status', PackagePurchase::STATUS_PAID)
                ->with(['package:id,name,slug', 'customer:id,name', 'discountCode:id,code']),
            'paid_at'
        )->get();

        foreach ($packagePurchases as $p) {
            $rows->push([
                'type'        => 'package',
                'type_label'  => 'پکیج',
                'title'       => $p->package?->name ?? '—',
                'customer'    => $p->customer?->name ?? '—',
                'base_amount' => (int) $p->original_amount,
                'discount'    => (int) $p->discount_amount,
                'amount'      => (int) $p->amount,
                'gateway'     => $p->gateway ?? '—',
                'discount_code' => $p->discountCode?->code,
                'paid_at'     => $p->paid_at ?? $p->created_at,
                'href'        => route('admin.purchases.show', $p),
            ]);
        }

        // ---- محصولات ----
        $productPurchases = $range(
            ProductPurchase::query()->where('status', ProductPurchase::STATUS_PAID)
                ->with(['product:id,name,slug', 'customer:id,name', 'discountCode:id,code']),
            'paid_at'
        )->get();

        foreach ($productPurchases as $p) {
            $rows->push([
                'type'        => 'product',
                'type_label'  => 'محصول',
                'title'       => $p->product?->name ?? '—',
                'customer'    => $p->customer?->name ?? 'مهمان',
                'base_amount' => (int) $p->original_amount,
                'discount'    => (int) $p->discount_amount,
                'amount'      => (int) $p->amount,
                'gateway'     => $p->gateway ?? '—',
                'discount_code' => $p->discountCode?->code,
                'paid_at'     => $p->paid_at ?? $p->created_at,
                'href'        => null,
            ]);
        }

        // ---- قالب‌ها ----
        $templatePurchases = $range(
            TemplatePurchase::query()->where('status', TemplatePurchase::STATUS_PAID)
                ->with(['template:id,name,slug', 'customer:id,name', 'discountCode:id,code']),
            'paid_at'
        )->get();

        foreach ($templatePurchases as $p) {
            $rows->push([
                'type'        => 'template',
                'type_label'  => 'قالب',
                'title'       => $p->template?->name ?? '—',
                'customer'    => $p->customer?->name ?? 'مهمان',
                'base_amount' => (int) $p->original_amount,
                'discount'    => (int) $p->discount_amount,
                'amount'      => (int) $p->amount,
                'gateway'     => $p->gateway ?? '—',
                'discount_code' => $p->discountCode?->code,
                'paid_at'     => $p->paid_at ?? $p->created_at,
                'href'        => null,
                'purchase_type' => $p->purchase_type,
            ]);
        }

        // ---- اشتراک‌ها (سفارش‌های پرداخت‌شده طرح‌ها) ----
        $subscriptionOrders = $range(
            SubscriptionOrder::query()->where('status', SubscriptionOrder::STATUS_PAID)
                ->with(['plan:id,name', 'customer:id,name', 'discountCode:id,code']),
            'paid_at'
        )->get();

        foreach ($subscriptionOrders as $o) {
            $rows->push([
                'type'        => 'subscription',
                'type_label'  => 'اشتراک',
                'title'       => $o->plan?->name ?? '—',
                'customer'    => $o->customer?->name ?? '—',
                // توجه: در SubscriptionOrder نام فیلدها amount (پایه) و discount است
                'base_amount' => (int) $o->amount,
                'discount'    => (int) $o->discount,
                'amount'      => (int) $o->final_amount,
                'gateway'     => $o->gateway ?? '—',
                'discount_code' => $o->discountCode?->code,
                'paid_at'     => $o->paid_at ?? $o->created_at,
                'href'        => route('admin.subscriptions.orders'),
            ]);
        }

        return $rows->sortByDesc('paid_at')->values();
    }

    /** جدول صفحه‌بندی‌شده ردیف‌های فروش */
    #[Computed]
    public function salesTable(): LengthAwarePaginator
    {
        $rows = $this->salesRows;
        $per  = 12;
        $page = LengthAwarePaginator::resolveCurrentPage('salesPage');

        return new LengthAwarePaginator(
            $rows->forPage($page, $per)->values(),
            $rows->count(),
            $per,
            $page,
            ['pageName' => 'salesPage', 'path' => LengthAwarePaginator::resolveCurrentPath()],
        );
    }

    /** آمار کامل بازه — KPI، تفکیک نوع/درگاه، رشد و سری نمودار */
    #[Computed]
    public function salesStats(): array
    {
        [$from, $to, $label] = $this->salesRange();
        $rows = $this->salesRows;

        $revenue      = (int) $rows->sum('amount');
        $discount     = (int) $rows->sum('discount');
        $count        = $rows->count();

        // رشد نسبت به دورهٔ قبل (هم‌طول)
        $prevRevenue = null;
        $growth = null;

        if ($from && $to) {
            $length = $from->diffInSeconds($to);
            $prevFrom = $from->copy()->subSeconds($length + 1);
            $prevTo = $from->copy()->subSecond();

            $prevRevenue = (int) PackagePurchase::where('status', PackagePurchase::STATUS_PAID)
                ->whereBetween('paid_at', [$prevFrom, $prevTo])->sum('amount');
            $prevRevenue += (int) ProductPurchase::where('status', ProductPurchase::STATUS_PAID)
                ->whereBetween('paid_at', [$prevFrom, $prevTo])->sum('amount');
            $prevRevenue += (int) TemplatePurchase::where('status', TemplatePurchase::STATUS_PAID)
                ->whereBetween('paid_at', [$prevFrom, $prevTo])->sum('amount');
            $prevRevenue += (int) SubscriptionOrder::where('status', SubscriptionOrder::STATUS_PAID)
                ->whereBetween('paid_at', [$prevFrom, $prevTo])->sum('final_amount');

            $growth = $prevRevenue > 0
                ? (int) round(($revenue - $prevRevenue) * 100 / $prevRevenue)
                : ($revenue > 0 ? 100 : null);
        }

        // تفکیک نوع
        $byType = [];
        foreach (['package' => 'پکیج‌ها', 'product' => 'محصولات', 'template' => 'قالب‌ها', 'subscription' => 'اشتراک‌ها'] as $type => $typeLabel) {
            $subset = $rows->where('type', $type);
            $byType[$type] = [
                'label'   => $typeLabel,
                'count'   => $subset->count(),
                'revenue' => (int) $subset->sum('amount'),
            ];
        }

        // تفکیک درگاه
        $byGateway = $rows
            ->groupBy('gateway')
            ->map(fn ($g) => ['count' => $g->count(), 'revenue' => (int) $g->sum('amount')])
            ->sortByDesc('revenue')
            ->all();

        // سری نمودار: ≤۳۱ روز → روزانه؛ بیشتر → ماهانه
        $series = collect();

        if ($from && $to) {
            $days = $from->diffInDays($to) + 1;

            if ($days <= 31) {
                $bucket = $rows->groupBy(fn ($r) => $r['paid_at']?->format('Y-m-d'));

                for ($d = $from->copy(); $d->lte($to); $d->addDay()) {
                    $day = $bucket->get($d->format('Y-m-d'));
                    $series->push([
                        'label'     => verta_date($d->copy(), 'm/d'),
                        'value'     => $day ? $day->count() : 0,
                        'secondary' => $day ? (int) $day->sum('amount') : 0,
                    ]);
                }
            } else {
                $bucket = $rows->groupBy(fn ($r) => $r['paid_at']?->format('Y-m'));

                for ($d = $from->copy()->startOfMonth(); $d->lte($to); $d->addMonth()) {
                    $month = $bucket->get($d->format('Y-m'));
                    $series->push([
                        'label'     => verta_date($d->copy(), 'Y/m'),
                        'value'     => $month ? $month->count() : 0,
                        'secondary' => $month ? (int) $month->sum('amount') : 0,
                    ]);
                }
            }
        } else {
            // همه زمان‌ها → ۶ ماه اخیر
            $data = collect(range(5, 0))->map(function ($i) use ($rows) {
                $date = Carbon::now()->subMonths($i);
                $subset = $rows->filter(fn ($r) => $r['paid_at']
                    && $r['paid_at']->year === $date->year
                    && $r['paid_at']->month === $date->month);

                return [
                    'label'     => verta_date($date, 'Y/m'),
                    'value'     => $subset->count(),
                    'secondary' => (int) $subset->sum('amount'),
                ];
            });

            $series = $data->values();
        }

        return [
            'label'           => $label,
            'revenue'         => $revenue,
            'discount'        => $discount,
            'count'           => $count,
            'avg_order'       => $count > 0 ? (int) round($revenue / $count) : 0,
            'prev_revenue'    => $prevRevenue,
            'growth'          => $growth,
            'by_type'         => $byType,
            'by_gateway'      => $byGateway,
            'discount_used'   => $rows->where('discount', '>', 0)->count(),
            'chart'           => [
                'title'  => 'فروش در بازه: ' . $label,
                'series' => $series->all(),
                'color'  => 'teal',
            ],
        ];
    }

    #[Computed]
    public function revenue(): array
    {
        $subscriptionsRevenue = (int) Subscription::where('payment_status', 'paid')->sum('final_amount');
        $packageRevenue = (int) PackagePurchase::where('status', PackagePurchase::STATUS_PAID)->sum('amount');
        $productRevenue = (int) ProductPurchase::where('status', ProductPurchase::STATUS_PAID)->sum('amount');
        $templateRevenue = (int) TemplatePurchase::where('status', TemplatePurchase::STATUS_PAID)->sum('amount');

        return [
            'total' => $subscriptionsRevenue + $packageRevenue + $productRevenue + $templateRevenue,
            'subscriptions' => $subscriptionsRevenue,
            'packages' => $packageRevenue,
            'products' => $productRevenue,
            'templates' => $templateRevenue,
            'paid_purchases' => PackagePurchase::where('status', PackagePurchase::STATUS_PAID)->count(),
            'pending_purchases' => PackagePurchase::where('status', PackagePurchase::STATUS_PENDING)->count(),
            'failed_purchases' => PackagePurchase::where('status', PackagePurchase::STATUS_FAILED)->count(),
        ];
    }

    /** آمار لایسنس‌ها (پکیج + قالب) — بررسی «لایسنس‌ها جزو گزارش» */
    #[Computed]
    public function licensesStats(): array
    {
        [$from, $to] = $this->salesRange();

        $issuedIn = fn ($query) => $from
            ? $query->whereBetween('created_at', [$from, $to])
            : $query;

        $packageActive = PackageLicense::where('status', PackageLicense::STATUS_ACTIVE)
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()));

        $templateActive = TemplateLicense::where('status', TemplateLicense::STATUS_ACTIVE);

        return [
            'package' => [
                'total'   => PackageLicense::count(),
                'active'  => (clone $packageActive)->count(),
                'expired' => PackageLicense::where('status', PackageLicense::STATUS_EXPIRED)->count(),
                'revoked' => PackageLicense::where('status', PackageLicense::STATUS_REVOKED)->count(),
                'issued'  => $issuedIn(PackageLicense::query())->count(),
            ],
            'template' => [
                'total'   => TemplateLicense::count(),
                'active'  => (clone $templateActive)->count(),
                'expired' => TemplateLicense::where('status', TemplateLicense::STATUS_EXPIRED)->count(),
                'revoked' => TemplateLicense::where('status', TemplateLicense::STATUS_REVOKED)->count(),
                'issued'  => $issuedIn(TemplateLicense::query())->count(),
            ],
        ];
    }

    #[Computed]
    public function salesChart(): array
    {
        $data = collect(range(5, 0))->map(function ($i) {
            $date = Carbon::now()->subMonths($i);
            $month = PackagePurchase::where('status', PackagePurchase::STATUS_PAID)
                ->whereYear('paid_at', $date->year)
                ->whereMonth('paid_at', $date->month);

            return [
                'label' => verta_date($date, 'Y/m'),
                'value' => $month->count(),
                'secondary' => (int) $month->sum('amount'),
            ];
        });

        return ['title' => 'خریدهای پرداخت‌شده (۶ ماه اخیر)', 'series' => $data->all(), 'color' => 'teal'];
    }

    #[Computed]
    public function topPackages()
    {
        return Package::query()
            ->select('id', 'name', 'slug', 'is_free', 'default_price', 'downloads_count', 'status')
            ->withCount(['purchases as paid_purchases_count' => fn ($q) => $q->where('status', PackagePurchase::STATUS_PAID)])
            ->withSum(['purchases as paid_purchases_revenue' => fn ($q) => $q->where('status', PackagePurchase::STATUS_PAID)], 'amount')
            ->orderByDesc('paid_purchases_count')
            ->orderByDesc('paid_purchases_revenue')
            ->limit(5)
            ->get();
    }

    /** پرفروش‌ترین محصولات (فروشگاه) */
    #[Computed]
    public function topProducts()
    {
        return Product::query()
            ->select('id', 'name', 'slug', 'is_free', 'price', 'sales_count', 'status')
            ->withCount(['purchases as paid_purchases_count' => fn ($q) => $q->where('status', ProductPurchase::STATUS_PAID)])
            ->withSum(['purchases as paid_revenue' => fn ($q) => $q->where('status', ProductPurchase::STATUS_PAID)], 'amount')
            ->orderByDesc('paid_purchases_count')
            ->orderByDesc('sales_count')
            ->limit(5)
            ->get();
    }

    /** پرفروش‌ترین قالب‌ها */
    #[Computed]
    public function topTemplates()
    {
        return Template::query()
            ->select('id', 'name', 'slug', 'is_free', 'price', 'sales_count', 'is_sold_exclusively', 'status')
            ->withCount(['purchases as paid_purchases_count' => fn ($q) => $q->where('status', TemplatePurchase::STATUS_PAID)])
            ->withSum(['purchases as paid_revenue' => fn ($q) => $q->where('status', TemplatePurchase::STATUS_PAID)], 'amount')
            ->orderByDesc('paid_purchases_count')
            ->orderByDesc('sales_count')
            ->limit(5)
            ->get();
    }

    /* ------------------------------------------------------------------ */
    /*  تب «فروش» — خروجی CSV                                              */
    /* ------------------------------------------------------------------ */

    /** خروجی CSV از فروش‌های بازه فعلی (سازگار با اکسل — UTF-8 BOM) */
    public function exportSalesCsv()
    {
        $rows = $this->salesRows;

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');

            // BOM برای نمایش درست فارسی در اکسل
            fwrite($out, "\xEF\xBB\xBF");

            fputcsv($out, [
                'نوع', 'عنوان', 'خریدار', 'مبلغ اصلی', 'تخفیف', 'مبلغ نهایی',
                'درگاه', 'کد تخفیف', 'تاریخ پرداخت (شمسی)', 'تاریخ پرداخت (میلادی)',
            ]);

            foreach ($rows as $row) {
                fputcsv($out, [
                    $row['type_label'],
                    $row['title'],
                    $row['customer'],
                    $row['base_amount'],
                    $row['discount'],
                    $row['amount'],
                    $row['gateway'],
                    $row['discount_code'] ?? '',
                    $row['paid_at'] ? verta_date($row['paid_at'], 'Y/m/d H:i') : '',
                    $row['paid_at']?->format('Y-m-d H:i:s'),
                ]);
            }

            fclose($out);
        }, 'sales-report-' . now()->format('Ymd-His') . '.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /* ------------------------------------------------------------------ */
    /*  تب «طرح‌های اشتراک»                                                */
    /* ------------------------------------------------------------------ */

    /** خلاصه آمار طرح‌های اشتراک (همان planStats داشبورد) */
    #[Computed]
    public function plansStats(): array
    {
        $pendingApprovals = SubscriptionOrder::where('admin_status', SubscriptionOrder::ADMIN_STATUS_PENDING)
            ->where('status', SubscriptionOrder::STATUS_PAID)
            ->count();

        return [
            'active_plans' => SubscriptionPlan::where('is_active', true)->count(),
            'total_orders' => SubscriptionOrder::count(),
            'approved_orders' => SubscriptionOrder::where('admin_status', SubscriptionOrder::ADMIN_STATUS_APPROVED)->count(),
            'pending_orders' => $pendingApprovals,
            'rejected_orders' => SubscriptionOrder::where('admin_status', SubscriptionOrder::ADMIN_STATUS_REJECTED)->count(),
            'plan_revenue' => (int) SubscriptionOrder::where('status', SubscriptionOrder::STATUS_PAID)->sum('final_amount'),
            'active_plan_subscriptions' => Subscription::whereNotNull('subscription_plan_id')
                ->where('status', 'active')
                ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
                ->count(),
            'pending_approvals' => $pendingApprovals,
        ];
    }

    /** گزارش کامل همه طرح‌ها، مرتب بر اساس درآمد */
    #[Computed]
    public function plansReport()
    {
        return SubscriptionPlan::query()
            ->with('packages:id,name')
            ->withCount('orders')
            ->withCount(['orders as approved_orders_count' => fn ($q) => $q->where('admin_status', SubscriptionOrder::ADMIN_STATUS_APPROVED)])
            ->withCount(['orders as pending_orders_count' => fn ($q) => $q->where('admin_status', SubscriptionOrder::ADMIN_STATUS_PENDING)
                ->where('status', SubscriptionOrder::STATUS_PAID)])
            ->withCount(['orders as rejected_orders_count' => fn ($q) => $q->where('admin_status', SubscriptionOrder::ADMIN_STATUS_REJECTED)])
            ->withSum(['orders as revenue' => fn ($q) => $q->where('status', SubscriptionOrder::STATUS_PAID)], 'final_amount')
            ->withCount('packages')
            ->addSelect([
                'active_subs_count' => Subscription::selectRaw('count(*)')
                    ->whereColumn('subscription_plan_id', 'subscription_plans.id')
                    ->where('status', 'active')
                    ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now())),
            ])
            ->orderByDesc('revenue')
            ->orderByDesc('orders_count')
            ->orderBy('sort_order')
            ->get();
    }

    public function render()
    {
        return view('livewire.reports.index');
    }
}
