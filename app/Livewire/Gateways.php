<?php

namespace App\Livewire;

use App\Livewire\Concerns\WithBulkActions;
use App\Livewire\Concerns\WithToasts;
use App\Models\Gateway;
use App\Services\ImageUploadService;
use Illuminate\Support\Facades\Schema;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('components.layouts.app')]
#[Title('درگاه‌های پرداخت')]
class Gateways extends Component
{
    use WithBulkActions, WithFileUploads, WithToasts;

    /** @var array<int, array<string, mixed>> */
    public array $gateways = [];

    /** فایل‌های لوگوی جدید — کلید = شناسه درگاه */
    public array $logoUploads = [];

    /** فیلتر نمایش/ترتیب: جدیدترین، قدیمی‌ترین، شناسه، ترتیب نمایش و نام */
    #[Url]
    public string $sort = 'newest';

    /** آی‌دی درگاه برای حذف تکی */
    public ?int $deleteId = null;

    public function mount(): void
    {
        $this->loadGateways();
    }

    public function updatedSort(): void
    {
        $this->loadGateways(ensure: false);
    }

    /* ---------------------------------------------------------------- */
    /*  Actions (پورت‌شده از SettingController@updateGateways) */
    /* ---------------------------------------------------------------- */

    public function save(): void
    {
        $this->validate([
            'gateways.*.name' => ['required', 'string', 'max:255'],
            'gateways.*.ordering' => ['nullable', 'integer', 'min:0'],
        ], [
            'gateways.*.name.required' => 'عنوان درگاه الزامی است.',
            'gateways.*.ordering.integer' => 'ترتیب نمایش باید عدد باشد.',
        ]);

        foreach ($this->gateways as $gateway) {
            $model = Gateway::find($gateway['id']);

            if (! $model) {
                continue;
            }

            $model->update([
                'name' => $gateway['name'],
                'ordering' => $gateway['ordering'] === '' || $gateway['ordering'] === null ? null : (int) $gateway['ordering'],
                'is_active' => (bool) $gateway['is_active'],
            ]);

            foreach ($gateway['configs'] as $key => $value) {
                $model->configs()->updateOrCreate(
                    ['key' => $key],
                    ['value' => (string) $value]
                );
            }
        }

        // آپلود لوگوهای جدید (در صورت انتخاب)
        $this->uploadPendingLogos();

        $this->logoUploads = [];

        $this->loadGateways(ensure: false);

        $this->toast('تنظیمات درگاه‌های پرداخت با موفقیت ذخیره شد.');
    }

    /* ---------------------------------------------------------------- */
    /*  لوگوی درگاه */
    /* ---------------------------------------------------------------- */

    /** آپلود لوگوهای انتخاب‌شده برای هر درگاه */
    private function uploadPendingLogos(): void
    {
        if (empty($this->logoUploads)) {
            return;
        }

        $service = app(ImageUploadService::class);

        foreach ($this->logoUploads as $gatewayId => $file) {
            if (! $file) {
                continue;
            }

            $gateway = Gateway::find((int) $gatewayId);

            if (! $gateway) {
                continue;
            }

            try {
                // حذف لوگوی قبلی (اگر اختصاصی بود)
                if ($gateway->logo) {
                    $service->deleteFile($gateway->logo);
                }

                $gateway->update([
                    'logo' => $service->uploadGatewayLogo($file, $gateway->key),
                ]);
            } catch (\Throwable $e) {
                $this->toast('خطا در آپلود لوگوی «'.($gateway->name ?: $gateway->key).'»: '.$e->getMessage(), 'error');
            }
        }
    }

    /** حذف لوگوی اختصاصی و بازگشت به لوگوی پیش‌فرض */
    public function removeLogo(int $gatewayId): void
    {
        $gateway = Gateway::findOrFail($gatewayId);

        if ($gateway->logo) {
            app(ImageUploadService::class)->deleteFile($gateway->logo);
            $gateway->update(['logo' => null]);
        }

        $this->loadGateways(ensure: false);

        $this->toast('لوگوی اختصاصی حذف شد؛ لوگوی پیش‌فرض نمایش داده می‌شود.');
    }

    /** URL لوگوی درگاه (اختصاصی یا پیش‌فرض) — برای ویو */
    public static function logoUrl(array $gw): ?string
    {
        return gateway_logo_url($gw['key'], $gw['logo'] ?? null);
    }

    /* ---------------------------------------------------------------- */
    /*  Bulk selection (WithBulkActions) */
    /* ---------------------------------------------------------------- */

    /** آی‌دی همه‌ی درگاه‌های نمایش‌داده‌شده (صفحه‌بندی نداریم) */
    public function bulkPageIds(): array
    {
        return array_map(fn (array $gw) => (string) $gw['id'], $this->gateways);
    }

    public function deleteSelectedRecords(): void
    {
        $ids = array_map('intval', $this->selectedIds);

        $gateways = Gateway::query()->whereIn('id', $ids)->get();

        foreach ($gateways as $gateway) {
            // FK در gateway_configs از نوع cascade است؛ دستی هم پاک می‌شود برای اطمینان
            $gateway->configs()->delete();
            $gateway->delete();
        }

        $this->loadGateways(ensure: false);

        $this->toast(fa_num(count($gateways)).' درگاه حذف شد.');
    }

    /** حذف تکی درگاه */
    public function delete(): void
    {
        $gateway = Gateway::findOrFail($this->deleteId ?? 0);

        $label = static::schema()[$gateway->key]['label'] ?? $gateway->name;

        // FK در gateway_configs از نوع cascade است؛ دستی هم پاک می‌شود برای اطمینان
        $gateway->configs()->delete();
        $gateway->delete();

        $this->deleteId = null;
        $this->loadGateways(ensure: false);

        $this->toast("درگاه «{$label}» حذف شد.");
    }

    public function render()
    {
        return view('livewire.gateways');
    }

    /* ---------------------------------------------------------------- */
    /*  Helpers */
    /* ---------------------------------------------------------------- */

    /**
     * ساختار درگاه‌ها و فیلدهای پیکربندی آن‌ها (پورت gwList در ویو قدیمی).
     *
     * @return array<string, array<string, mixed>>
     */
    public static function schema(): array
    {
        return [
            // ── درگاه‌های بانکی ─────────────────────────────────
            'behpardakht' => ['label' => 'درگاه به‌پرداخت (بانک ملت)', 'fields' => [
                ['name' => 'username', 'label' => 'نام کاربری', 'type' => 'text'],
                ['name' => 'password', 'label' => 'رمز عبور', 'type' => 'text'],
                ['name' => 'terminalId', 'label' => 'کد پذیرنده', 'type' => 'text'],
            ]],
            'sadad' => ['label' => 'درگاه سداد (بانک ملی)', 'fields' => [
                ['name' => 'terminalId', 'label' => 'شماره پذیرنده', 'type' => 'text'],
                ['name' => 'merchantId', 'label' => 'کد پذیرنده', 'type' => 'text'],
                ['name' => 'key', 'label' => 'کلید تراکنش', 'type' => 'text'],
            ]],
            'parsian' => ['label' => 'درگاه بانک پارسیان', 'fields' => [
                ['name' => 'merchantId', 'label' => 'کد پذیرنده (مرچنت)', 'type' => 'text'],
            ]],
            'pasargad' => ['label' => 'درگاه بانک پاسارگاد', 'fields' => [
                ['name' => 'terminalCode', 'label' => 'کد پذیرنده (ترمینال)', 'type' => 'text'],
                ['name' => 'userName', 'label' => 'نام کاربری', 'type' => 'text'],
                ['name' => 'password', 'label' => 'رمز عبور', 'type' => 'text'],
            ]],
            'irankish' => ['label' => 'درگاه ایران کیش', 'fields' => [
                ['name' => 'terminalId', 'label' => 'کد پایانه', 'type' => 'text'],
                ['name' => 'acceptorId', 'label' => 'کد پذیرنده', 'type' => 'text'],
                ['name' => 'password', 'label' => 'کلمه عبور', 'type' => 'text'],
                ['name' => 'pubKey', 'label' => 'کلید عمومی', 'type' => 'textarea'],
            ]],
            'asanpardakht' => ['label' => 'درگاه آسان‌پرداخت', 'fields' => [
                ['name' => 'username', 'label' => 'نام کاربری', 'type' => 'text'],
                ['name' => 'password', 'label' => 'رمز عبور', 'type' => 'text'],
                ['name' => 'merchantConfigID', 'label' => 'شناسهٔ پیکربندی پذیرنده', 'type' => 'text'],
            ]],
            'sepehr' => ['label' => 'درگاه سپهر (بانک صادرات)', 'fields' => [
                ['name' => 'terminalId', 'label' => 'کد پذیرنده', 'type' => 'text'],
            ]],
            'saman' => ['label' => 'درگاه سامان (کلاسیک)', 'fields' => [
                ['name' => 'merchantId', 'label' => 'کد پذیرنده', 'type' => 'text'],
            ]],
            'sep' => ['label' => 'درگاه سامان SEP (REST جدید)', 'fields' => [
                ['name' => 'terminalId', 'label' => 'کد پایانه/پذیرنده (UUID یا عددی)', 'type' => 'text'],
                ['name' => 'mode', 'label' => 'مُد درایور (خالی=خودکار | v1 | onlinepg)', 'type' => 'text'],
            ]],

            // ── درگاه‌های واسط پرداخت ───────────────────────────
            'novinopay' => ['label' => 'درگاه نوین پی', 'fields' => [
                ['name' => 'merchantId', 'label' => 'کد مرچنت نوین‌پی', 'type' => 'text'],
                ['name' => 'mode', 'label' => 'نحوهٔ پرداخت (خالی=عادی | direct=مستقیم)', 'type' => 'text'],
            ]],
            'payping' => ['label' => 'درگاه پی پینگ', 'fields' => [
                ['name' => 'merchantId', 'label' => 'کد درگاه پرداخت', 'type' => 'text'],
            ]],
            'zarinpal' => ['label' => 'درگاه زرین پال', 'fields' => [
                ['name' => 'merchantId', 'label' => 'کد درگاه پرداخت', 'type' => 'text'],
            ]],
            'zibal' => ['label' => 'درگاه زیبال', 'fields' => [
                ['name' => 'merchantId', 'label' => 'کد پذیرنده', 'type' => 'text'],
            ]],
            'idpay' => ['label' => 'درگاه idpay', 'fields' => [
                ['name' => 'merchantId', 'label' => 'کد درگاه پرداخت', 'type' => 'text'],
            ]],
            'payir' => ['label' => 'درگاه pay.ir', 'fields' => [
                ['name' => 'merchantId', 'label' => 'api کد', 'type' => 'text'],
            ]],
            'toman' => ['label' => 'درگاه تومن', 'fields' => [
                ['name' => 'shop_slug', 'label' => 'shop slug', 'type' => 'text'],
                ['name' => 'auth_code', 'label' => 'auth code', 'type' => 'text'],
            ]],

            // ── درگاه آزمایشی ───────────────────────────────────
            'local' => ['label' => 'درگاه آزمایشی (تست)', 'is_test' => true, 'fields' => [
                ['name' => 'title', 'label' => 'عنوان صفحه درگاه', 'type' => 'text'],
                ['name' => 'description', 'label' => 'توضیحات', 'type' => 'text'],
            ]],
        ];
    }

    /**
     * بارگذاری درگاه‌ها از دیتابیس (پورت SettingController@showGateways).
     *
     * @param  bool  $ensure  درگاه‌های پشتیبانی‌شده از پیکربندی سیستم ساخته شوند؟
     *                        (بعد از حذف، تا رفرش بعدی صفحه false پاس می‌شود)
     */
    protected function loadGateways(bool $ensure = true): void
    {
        // اطمینان از وجود همه درگاه‌های پشتیبانی‌شده
        if ($ensure) {
            foreach (config('general.supported_gateways') as $key => $name) {
                Gateway::firstOrCreate(
                    ['key' => $key],
                    ['name' => $name]
                );
            }
        }

        // اگر migration لوگو اجرا نشده باشد (نصب‌های قدیمی) ستون را صدا نزنیم
        try {
            $hasLogoColumn = Schema::hasColumn('gateways', 'logo');
        } catch (\Throwable) {
            $hasLogoColumn = false;
        }

        $rows = Gateway::query()->with('configs')->get()->keyBy('key');

        $list = [];

        foreach (static::schema() as $key => $meta) {
            $gateway = $rows->get($key);

            if (! $gateway) {
                continue;
            }
            $stored = $gateway->configs->pluck('value', 'key')->all();

            $configs = [];
            foreach ($meta['fields'] as $field) {
                $configs[$field['name']] = (string) ($stored[$field['name']] ?? '');
            }

            $list[] = [
                'id' => (int) $gateway->id,
                'key' => (string) $gateway->key,
                'name' => (string) $gateway->name,
                'logo' => $hasLogoColumn ? $gateway->logo : null,
                'ordering' => $gateway->ordering,
                'is_active' => (bool) $gateway->is_active,
                'is_test' => (bool) ($meta['is_test'] ?? false),
                'configs' => $configs,
                // فقط برای مرتب‌سازی (قبل از انتساب حذف می‌شوند)
                '_created' => $gateway->created_at?->getTimestamp() ?? 0,
                '_schema' => count($list),
            ];
        }

        // اعمال ترتیب نمایش — «newest» ترتیب قبلی (اسکیما) را با تاریخ ساخت حفظ می‌کند
        usort($list, function (array $a, array $b): int {
            return match ($this->sort) {
                'oldest' => [$a['_created'], $a['_schema']] <=> [$b['_created'], $b['_schema']],
                'id_desc' => $b['id'] <=> $a['id'],
                'id_asc' => $a['id'] <=> $b['id'],
                'ordering_asc' => [$a['ordering'] ?? PHP_INT_MAX, $a['_schema']] <=> [$b['ordering'] ?? PHP_INT_MAX, $b['_schema']],
                'name_asc' => [strnatcasecmp($a['name'], $b['name']), $a['_schema']] <=> [strnatcasecmp($b['name'], $b['name']), $b['_schema']],
                default => [$b['_created'], $a['_schema']] <=> [$a['_created'], $b['_schema']],
            };
        });

        // کلیدهای کمکی مرتب‌سازی کنار گذاشته می‌شوند
        $this->gateways = array_map(
            fn (array $gw) => array_filter($gw, fn ($value, $key) => ! str_starts_with((string) $key, '_'), ARRAY_FILTER_USE_BOTH),
            $list
        );
    }
}
