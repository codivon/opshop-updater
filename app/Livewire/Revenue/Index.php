<?php

namespace App\Livewire\Revenue;

use App\Livewire\Concerns\AuthorizesAdmin;
use App\Livewire\Concerns\WithToasts;
use App\Models\PackagePurchase;
use App\Models\ProductPurchase;
use App\Models\Subscription;
use App\Models\SubscriptionOrder;
use App\Models\TemplatePurchase;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Task 85 — صفحهٔ «درآمد» پنل مدیریت:
 * درآمد کلی و جزئی همهٔ فروش‌ها (پکیج + محصول + قالب + اشتراک/طرح)
 * به‌صورت تعداد، مبلغ و نمودار — با فیلتر بازهٔ زمانی.
 *
 * نکتهٔ حسابداری: با تأیید هر سفارشِ طرح، اشتراکِ مرتبط هم «paid» می‌شود
 * (SubscriptionService::approve). پس درآمد اشتراک = سفارش‌های پرداخت‌شدهٔ طرح
 * + اشتراک‌های دستیِ بدون سفارش؛ هرگز هر دو را با هم جمع نمی‌کنیم (جلوگیری از دوبار‌شماری).
 */
#[Layout('components.layouts.app')]
#[Title('درآمد')]
class Index extends Component
{
    use AuthorizesAdmin, WithToasts;

    /** بازهٔ زمانی: today | week | month | year | all | custom */
    #[Url]
    public string $period = 'month';

    /** بازهٔ دلخواه (شمسی — مثل ۱۴۰۴/۰۸/۰۱) */
    #[Url]
    public string $from = '';

    #[Url]
    public string $to = '';

    public function setPeriod(string $period): void
    {
        if (! in_array($period, ['today', 'week', 'month', 'year', 'all', 'custom'], true)) {
            return;
        }

        $this->period = $period;

        if ($period !== 'custom') {
            $this->from = '';
            $this->to = '';
        }
    }

    public function resetFilters(): void
    {
        $this->period = 'month';
        $this->from = '';
        $this->to = '';
    }

    /* ------------------------------------------------------------------ */
    /*  بازهٔ زمانی فعال */
    /* ------------------------------------------------------------------ */

    /** @return array{0: ?Carbon, 1: ?Carbon, 2: string} [شروع، پایان، برچسب] */
    #[Computed]
    public function range(): array
    {
        $now = Carbon::now();

        switch ($this->period) {
            case 'today':
                return [$now->copy()->startOfDay(), $now->copy()->endOfDay(), 'امروز'];

            case 'week':
                return [$now->copy()->subDays(6)->startOfDay(), $now->copy()->endOfDay(), '۷ روز اخیر'];

            case 'year':
                return [$now->copy()->subDays(364)->startOfDay(), $now->copy()->endOfDay(), 'یک سال اخیر'];

            case 'custom':
                $from = jalali_to_carbon($this->from);
                $to = jalali_to_carbon($this->to, endOfDay: true);

                if ($from && $to && $from->gt($to)) {
                    [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
                }

                $label = 'بازهٔ دلخواه';
                if ($from || $to) {
                    $label = ($from ? verta_date($from) : '…').' تا '.($to ? verta_date($to) : '…');
                }

                return [$from, $to, $label];

            case 'all':
                return [null, null, 'همهٔ زمان‌ها'];

            case 'month':
            default:
                return [$now->copy()->startOfMonth(), $now->copy()->endOfDay(), 'این ماه'];
        }
    }

    /** اعمال بازهٔ فعال روی یک کوئری (ستون تاریخ به‌صورت پارامتر) */
    private function inRange($query, string $dateColumn = 'paid_at')
    {
        [$from, $to] = $this->range;

        if ($from) {
            $query->where($dateColumn, '>=', $from);
        }

        if ($to) {
            $query->where($dateColumn, '<=', $to);
        }

        return $query;
    }

    /* ------------------------------------------------------------------ */
    /*  کارت‌های آماری (KPI) */
    /* ------------------------------------------------------------------ */

    /** آمار خلاصهٔ هر منبع در بازهٔ فعال */
    #[Computed]
    public function sourceStats(): array
    {
        $package = $this->inRange(PackagePurchase::query()->where('status', PackagePurchase::STATUS_PAID))
            ->selectRaw('COUNT(*) as c, COALESCE(SUM(amount),0) as r, COALESCE(SUM(discount_amount),0) as d')->first();

        $product = $this->inRange(ProductPurchase::query()->where('status', ProductPurchase::STATUS_PAID))
            ->selectRaw('COUNT(*) as c, COALESCE(SUM(amount),0) as r, COALESCE(SUM(discount_amount),0) as d')->first();

        $template = $this->inRange(TemplatePurchase::query()->where('status', TemplatePurchase::STATUS_PAID))
            ->selectRaw('COUNT(*) as c, COALESCE(SUM(amount),0) as r, COALESCE(SUM(discount_amount),0) as d')->first();

        $plan = $this->inRange(SubscriptionOrder::query()->where('status', SubscriptionOrder::STATUS_PAID))
            ->selectRaw('COUNT(*) as c, COALESCE(SUM(final_amount),0) as r, COALESCE(SUM(discount),0) as d')->first();

        // اشتراک‌های دستیِ بدون سفارش (ثبت‌شده توسط مدیر) — برای جلوگیری از دوبار‌شماری با طرح‌ها
        $manual = $this->inRange(
            Subscription::query()->where('payment_status', 'paid')->whereNull('subscription_order_id'),
            'created_at'
        )->selectRaw('COUNT(*) as c, COALESCE(SUM(final_amount),0) as r, COALESCE(SUM(discount),0) as d')->first();

        return [
            'package' => ['label' => 'پکیج‌ها', 'count' => (int) $package->c, 'revenue' => (int) $package->r, 'discount' => (int) $package->d],
            'product' => ['label' => 'محصولات (پروژه‌ها)', 'count' => (int) $product->c, 'revenue' => (int) $product->r, 'discount' => (int) $product->d],
            'template' => ['label' => 'قالب‌ها', 'count' => (int) $template->c, 'revenue' => (int) $template->r, 'discount' => (int) $template->d],
            'plan' => ['label' => 'طرح‌های اشتراک', 'count' => (int) $plan->c, 'revenue' => (int) $plan->r, 'discount' => (int) $plan->d],
            'manual' => ['label' => 'اشتراک دستی', 'count' => (int) $manual->c, 'revenue' => (int) $manual->r, 'discount' => (int) $manual->d],
        ];
    }

    #[Computed]
    public function kpis(): array
    {
        $stats = $this->sourceStats;

        $totalCount = array_sum(array_column($stats, 'count'));
        $totalRevenue = array_sum(array_column($stats, 'revenue'));
        $totalDiscount = array_sum(array_column($stats, 'discount'));

        // درآمد امروز و این ماه — مستقل از فیلتر، برای مقایسهٔ سریع
        $now = Carbon::now();

        return [
            'total_revenue' => $totalRevenue,
            'total_count' => $totalCount,
            'avg_order' => $totalCount > 0 ? (int) round($totalRevenue / $totalCount) : 0,
            'total_discount' => $totalDiscount,
            'today' => $this->revenueBetween($now->copy()->startOfDay(), $now->copy()->endOfDay()),
            'month' => $this->revenueBetween($now->copy()->startOfMonth(), $now->copy()->endOfDay()),
        ];
    }

    /* ------------------------------------------------------------------ */
    /*  نمودارها */
    /* ------------------------------------------------------------------ */

    /** روند درآمد ۱۲ ماه اخیر (مستقل از فیلتر بازه) */
    #[Computed]
    public function monthlyChart(): array
    {
        $start = Carbon::now()->subMonths(11)->startOfMonth();
        $end = Carbon::now()->endOfDay();

        // تجمیع ماه‌به‌ماه در سطح PHP — هم روی SQLite و هم MySQL یکسان کار می‌کند
        $byMonth = [];

        foreach ($this->sourceRowsBetween($start, $end) as $row) {
            $key = $row['date']->format('Y-m');
            $byMonth[$key]['revenue'] = ($byMonth[$key]['revenue'] ?? 0) + $row['amount'];
            $byMonth[$key]['count'] = ($byMonth[$key]['count'] ?? 0) + 1;
        }

        $series = [];

        foreach (range(11, 0) as $i) {
            $monthStart = Carbon::now()->subMonths($i)->startOfMonth();
            $key = $monthStart->format('Y-m');

            $series[] = [
                'label' => verta_date($monthStart, 'Y/m'),
                'value' => (int) ($byMonth[$key]['revenue'] ?? 0),
                'secondary' => (int) ($byMonth[$key]['count'] ?? 0),
            ];
        }

        return ['title' => 'روند درآمد — ۱۲ ماه اخیر (تومان)', 'series' => $series, 'color' => 'brand'];
    }

    /** تراکنش‌های روزانهٔ بازهٔ فعال (وقتی «همهٔ زمان‌ها» است: ۳۰ روز اخیر) */
    #[Computed]
    public function dailyChart(): array
    {
        [$from, $to, $label] = $this->range;

        if (! $from || ! $to) {
            $from = Carbon::now()->subDays(29)->startOfDay();
            $to = Carbon::now()->endOfDay();
            $label = '۳۰ روز اخیر';
        }

        // سقف ۹۰ سطل روزانه برای حفظ خوانایی نمودار
        if ($from->diffInDays($to) > 90) {
            $from = $to->copy()->subDays(90)->startOfDay();
        }

        $buckets = [];

        foreach ($from->daysUntil($to->copy()->endOfDay()) as $day) {
            $buckets[$day->format('Y-m-d')] = ['revenue' => 0, 'count' => 0];
        }

        foreach ($this->dailySourceRows($from, $to) as $row) {
            $key = substr((string) $row->d, 0, 10);

            if (isset($buckets[$key])) {
                $buckets[$key]['revenue'] += (int) $row->r;
                $buckets[$key]['count'] += (int) $row->c;
            }
        }

        $series = [];

        // برچسب فقط هر چند سطل یک‌بار تا نمودار شلوغ نشود
        $labelEvery = max(1, (int) ceil(count($buckets) / 25));
        $index = 0;

        foreach ($buckets as $key => $bucket) {
            $series[] = [
                'label' => $index % $labelEvery === 0 ? verta_date($key, 'm/d') : '',
                'value' => $bucket['revenue'],
                'secondary' => $bucket['count'],
            ];
            $index++;
        }

        return ['title' => "درآمد روزانه — {$label} (تومان)", 'series' => $series, 'color' => 'teal'];
    }

    /* ------------------------------------------------------------------ */
    /*  جداول جزئی */
    /* ------------------------------------------------------------------ */

    /** تفکیک کامل منابع + تعداد در انتظار/ناموفق + سهم درصدی */
    #[Computed]
    public function breakdown(): array
    {
        $stats = $this->sourceStats;

        $pending = [
            'package' => (int) $this->inRange(PackagePurchase::query()->where('status', PackagePurchase::STATUS_PENDING), 'created_at')->count(),
            'product' => (int) $this->inRange(ProductPurchase::query()->where('status', ProductPurchase::STATUS_PENDING), 'created_at')->count(),
            'template' => (int) $this->inRange(TemplatePurchase::query()->where('status', TemplatePurchase::STATUS_PENDING), 'created_at')->count(),
            'plan' => (int) $this->inRange(SubscriptionOrder::query()->where('status', SubscriptionOrder::STATUS_PENDING), 'created_at')->count(),
            'manual' => 0,
        ];

        $failed = [
            'package' => (int) $this->inRange(PackagePurchase::query()->where('status', PackagePurchase::STATUS_FAILED), 'created_at')->count(),
            'product' => (int) $this->inRange(ProductPurchase::query()->where('status', ProductPurchase::STATUS_FAILED), 'created_at')->count(),
            'template' => (int) $this->inRange(TemplatePurchase::query()->where('status', TemplatePurchase::STATUS_FAILED), 'created_at')->count(),
            'plan' => (int) $this->inRange(SubscriptionOrder::query()->where('status', SubscriptionOrder::STATUS_FAILED), 'created_at')->count(),
            'manual' => 0,
        ];

        $totalRevenue = array_sum(array_column($stats, 'revenue'));

        $rows = [];

        foreach (['package', 'product', 'template', 'plan', 'manual'] as $key) {
            $s = $stats[$key];

            $rows[] = [
                'key' => $key,
                'label' => $s['label'],
                'count' => $s['count'],
                'pending' => $pending[$key],
                'failed' => $failed[$key],
                'revenue' => $s['revenue'],
                'discount' => $s['discount'],
                'avg' => $s['count'] > 0 ? (int) round($s['revenue'] / $s['count']) : 0,
                'share' => $totalRevenue > 0 ? (int) round($s['revenue'] / $totalRevenue * 100) : 0,
            ];
        }

        return $rows;
    }

    /** پرفروش‌ترین‌ها — ۸ مورد برتر بر اساس درآمد */
    #[Computed]
    public function topSellers(): array
    {
        $rows = [];

        foreach ($this->inRange(PackagePurchase::query()->where('status', PackagePurchase::STATUS_PAID))
            ->selectRaw('package_id, COUNT(*) as c, COALESCE(SUM(amount),0) as r')
            ->groupBy('package_id')->orderByDesc('r')->limit(8)->get() as $row) {
            $rows[] = ['type' => 'پکیج', 'name' => $row->package?->name ?? ('پکیج #'.$row->package_id), 'count' => (int) $row->c, 'revenue' => (int) $row->r];
        }

        foreach ($this->inRange(ProductPurchase::query()->where('status', ProductPurchase::STATUS_PAID))
            ->selectRaw('product_id, COUNT(*) as c, COALESCE(SUM(amount),0) as r')
            ->groupBy('product_id')->orderByDesc('r')->limit(8)->get() as $row) {
            $rows[] = ['type' => 'محصول', 'name' => $row->product?->name ?? ('محصول #'.$row->product_id), 'count' => (int) $row->c, 'revenue' => (int) $row->r];
        }

        foreach ($this->inRange(TemplatePurchase::query()->where('status', TemplatePurchase::STATUS_PAID))
            ->selectRaw('template_id, COUNT(*) as c, COALESCE(SUM(amount),0) as r')
            ->groupBy('template_id')->orderByDesc('r')->limit(8)->get() as $row) {
            $rows[] = ['type' => 'قالب', 'name' => $row->template?->name ?? ('قالب #'.$row->template_id), 'count' => (int) $row->c, 'revenue' => (int) $row->r];
        }

        // طرح‌ها: تجمیع در PHP (نام طرح = رابطهٔ فعال یا snapshot ذخیره‌شدهٔ سفارش)
        $planOrders = $this->inRange(SubscriptionOrder::query()->where('status', SubscriptionOrder::STATUS_PAID))
            ->with('plan:id,name')
            ->get(['id', 'subscription_plan_id', 'final_amount', 'meta']);

        foreach ($planOrders->groupBy(fn ($o) => $o->subscription_plan_id ?? 0) as $group) {
            $first = $group->first();

            $rows[] = [
                'type' => 'طرح',
                'name' => $first->plan?->name
                    ?? ($first->meta['plan']['name'] ?? 'طرح حذف‌شده'),
                'count' => $group->count(),
                'revenue' => (int) $group->sum('final_amount'),
            ];
        }

        return collect($rows)->sortByDesc('revenue')->take(8)->values()->all();
    }

    /** درگاه‌های پرداخت — تعداد و درآمد موفق هر درگاه + سهم درصدی */
    #[Computed]
    public function gateways(): array
    {
        $gateways = [];

        $queries = [
            $this->inRange(PackagePurchase::query()->where('status', PackagePurchase::STATUS_PAID))
                ->selectRaw("COALESCE(gateway, '—') as g, COUNT(*) as c, COALESCE(SUM(amount),0) as r"),
            $this->inRange(ProductPurchase::query()->where('status', ProductPurchase::STATUS_PAID))
                ->selectRaw("COALESCE(gateway, '—') as g, COUNT(*) as c, COALESCE(SUM(amount),0) as r"),
            $this->inRange(TemplatePurchase::query()->where('status', TemplatePurchase::STATUS_PAID))
                ->selectRaw("COALESCE(gateway, '—') as g, COUNT(*) as c, COALESCE(SUM(amount),0) as r"),
            $this->inRange(SubscriptionOrder::query()->where('status', SubscriptionOrder::STATUS_PAID))
                ->selectRaw("COALESCE(gateway, '—') as g, COUNT(*) as c, COALESCE(SUM(final_amount),0) as r"),
        ];

        foreach ($queries as $query) {
            foreach ($query->groupBy('gateway')->get() as $row) {
                $gateways[$row->g]['count'] = ($gateways[$row->g]['count'] ?? 0) + (int) $row->c;
                $gateways[$row->g]['revenue'] = ($gateways[$row->g]['revenue'] ?? 0) + (int) $row->r;
            }
        }

        uasort($gateways, fn ($a, $b) => $b['revenue'] <=> $a['revenue']);

        $total = array_sum(array_column($gateways, 'revenue'));

        return collect($gateways)->map(function ($g, $name) use ($total) {
            $g['name'] = $name;
            $g['share'] = $total > 0 ? (int) round($g['revenue'] / $total * 100) : 0;

            return $g;
        })->values()->all();
    }

    /* ------------------------------------------------------------------ */
    /*  کمکی‌ها */
    /* ------------------------------------------------------------------ */

    /** جمع درآمد همهٔ منابع بین دو تاریخ */
    private function revenueBetween(Carbon $start, Carbon $end): int
    {
        return (int) PackagePurchase::query()->where('status', PackagePurchase::STATUS_PAID)
            ->whereBetween('paid_at', [$start, $end])->sum('amount')
            + (int) ProductPurchase::query()->where('status', ProductPurchase::STATUS_PAID)
                ->whereBetween('paid_at', [$start, $end])->sum('amount')
            + (int) TemplatePurchase::query()->where('status', TemplatePurchase::STATUS_PAID)
                ->whereBetween('paid_at', [$start, $end])->sum('amount')
            + (int) SubscriptionOrder::query()->where('status', SubscriptionOrder::STATUS_PAID)
                ->whereBetween('paid_at', [$start, $end])->sum('final_amount');
    }

    /** ردیف‌های سبک [date, amount] همهٔ منابع برای تجمیع در PHP */
    private function sourceRowsBetween(Carbon $from, Carbon $to): array
    {
        $rows = [];

        $fetch = function ($query, string $amountColumn, string $dateColumn = 'paid_at') {
            return $query->get([$dateColumn, $amountColumn])
                ->map(fn ($row) => ['date' => $row->{$dateColumn}, 'amount' => (int) $row->{$amountColumn}]);
        };

        $rows[] = $fetch(PackagePurchase::query()->where('status', PackagePurchase::STATUS_PAID)->whereBetween('paid_at', [$from, $to]), 'amount')->all();

        $rows[] = $fetch(ProductPurchase::query()->where('status', ProductPurchase::STATUS_PAID)->whereBetween('paid_at', [$from, $to]), 'amount')->all();

        $rows[] = $fetch(TemplatePurchase::query()->where('status', TemplatePurchase::STATUS_PAID)->whereBetween('paid_at', [$from, $to]), 'amount')->all();

        $rows[] = $fetch(SubscriptionOrder::query()->where('status', SubscriptionOrder::STATUS_PAID)->whereBetween('paid_at', [$from, $to]), 'final_amount')->all();

        $rows[] = $fetch(
            Subscription::query()->where('payment_status', 'paid')->whereNull('subscription_order_id')->whereBetween('created_at', [$from, $to]),
            'final_amount',
            'created_at'
        )->all();

        return array_merge(...$rows);
    }

    /** جمع روزانهٔ هر منبع برای نمودار روزانه (DATE() هم در SQLite و هم MySQL پشتیبانی می‌شود) */
    private function dailySourceRows(Carbon $from, Carbon $to): array
    {
        $rows = [];

        $rows[] = PackagePurchase::query()->where('status', PackagePurchase::STATUS_PAID)
            ->whereBetween('paid_at', [$from, $to])
            ->selectRaw('DATE(paid_at) as d, COUNT(*) as c, COALESCE(SUM(amount),0) as r')->groupBy('d')->get()->all();

        $rows[] = ProductPurchase::query()->where('status', ProductPurchase::STATUS_PAID)
            ->whereBetween('paid_at', [$from, $to])
            ->selectRaw('DATE(paid_at) as d, COUNT(*) as c, COALESCE(SUM(amount),0) as r')->groupBy('d')->get()->all();

        $rows[] = TemplatePurchase::query()->where('status', TemplatePurchase::STATUS_PAID)
            ->whereBetween('paid_at', [$from, $to])
            ->selectRaw('DATE(paid_at) as d, COUNT(*) as c, COALESCE(SUM(amount),0) as r')->groupBy('d')->get()->all();

        $rows[] = SubscriptionOrder::query()->where('status', SubscriptionOrder::STATUS_PAID)
            ->whereBetween('paid_at', [$from, $to])
            ->selectRaw('DATE(paid_at) as d, COUNT(*) as c, COALESCE(SUM(final_amount),0) as r')->groupBy('d')->get()->all();

        return array_merge(...$rows);
    }

    public function render()
    {
        return view('livewire.revenue.index');
    }
}
