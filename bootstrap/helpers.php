<?php

if (! function_exists('setting')) {
    /**
     * خواندن تنظیمات از جدول settings (با کش ۶۰ ثانیه‌ای).
     */
    function setting(string $key, mixed $default = null): mixed
    {
        try {
            return \Illuminate\Support\Facades\Cache::remember(
                'setting:' . $key,
                60,
                fn () => \App\Models\Setting::query()->where('key', $key)->value('value')
            ) ?? $default;
        } catch (\Throwable) {
            return $default;
        }
    }
}

if (! function_exists('shaparak_proxy')) {
    /**
     * آدرس پروکسی شاپرک (در صورت تنظیم) — برای هاست‌هایی که خارج از ایران
     * هستند و شاپرک به IPهای خارجی پاسخ نمی‌دهد.
     *
     * فرمت‌های مجاز: http://ip:port | http://user:pass@ip:port | socks5://ip:port
     * تنظیم از: پنل → تنظیمات → پرداخت → «پروکسی شاپرک»
     */
    function shaparak_proxy(): ?string
    {
        $proxy = trim((string) setting('payment_shaparak_proxy', ''));

        if ($proxy === '') {
            return null;
        }

        // اعتبارسنجی ساده
        if (!preg_match('#^(https?|socks5h?|tcp)://[^\s]+$#i', $proxy)) {
            return null;
        }

        return $proxy;
    }
}

if (! function_exists('exception_status')) {
    /**
     * کد وضعیت HTTP امن از روی کد استثنا.
     *
     * ⚠️ ریشه‌یابِ خطای «JsonResponse::__construct(): Argument #2 ($status)
     * must be of type int, string given»:
     * PDOException (که خودش RuntimeException است!) کد SQLSTATE مثل "HY000"
     * یا "23000" برمی‌گرداند — یعنی یک «رشته». چنین رشته‌ای به‌عنوان status
     * به response()->json() می‌رسید و TypeError می‌داد. این هِلپر فقط کدهای
     * صحیحِ ۱۰۰ تا ۵۹۹ را برمی‌گرداند و در غیر این صورت مقدار پیش‌فرض.
     */
    function exception_status(\Throwable $e, int $fallback = 500): int
    {
        $code = $e->getCode();

        // PDOException و برخی استثناها کد را به‌صورت رشته (SQLSTATE) برمی‌گردانند
        if (is_int($code) && $code >= 100 && $code <= 599) {
            return $code;
        }

        // رشته‌های عددی معتبر (مثل "404") هم پذیرفته می‌شوند
        if (is_string($code) && ctype_digit($code)) {
            $code = (int) $code;

            if ($code >= 100 && $code <= 599) {
                return $code;
            }
        }

        return $fallback;
    }
}

if (! function_exists('gateway_logo_url')) {
    /**
     * URL لوگوی درگاه — لوگوی اختصاصی آپلودشده یا لوگوی پیش‌فرض (public/uploads/gateways/logos/{key}.svg)
     */
    function gateway_logo_url(?string $key, ?string $customPath = null): ?string
    {
        if (blank($key)) {
            return null;
        }

        // لوگوی اختصاصی (آپلودی) — از مسیر uploads/… سرو می‌شود
        if (!blank($customPath)) {
            return asset($customPath);
        }

        // لوگوی پیش‌فرض داخل پروژه
        $default = public_path('uploads/gateways/logos/' . $key . '.svg');

        return is_file($default) ? asset('uploads/gateways/logos/' . $key . '.svg') : null;
    }
}

if (! function_exists('normalize_mobile')) {
    /**
     * نرمال‌سازی شماره موبایل ایرانی به فرمت 09xxxxxxxxx
     * (ارقام فارسی/عربی → لاتین، +98/0098/98 → 0)
     */
    function normalize_mobile(string|int|null $mobile): ?string
    {
        if (blank($mobile)) {
            return null;
        }

        $number = en_num(trim((string) $mobile));
        $number = preg_replace('/[^0-9]/', '', $number);

        if ($number === '' || $number === null) {
            return null;
        }

        // +989xxxxxxxxx / 00989xxxxxxxxx / 989xxxxxxxxx → 09xxxxxxxxx
        if (str_starts_with($number, '0098')) {
            $number = '0' . substr($number, 4);
        } elseif (str_starts_with($number, '98') && strlen($number) === 12) {
            $number = '0' . substr($number, 2);
        } elseif (str_starts_with($number, '9') && strlen($number) === 10) {
            $number = '0' . $number;
        }

        // موبایل معتبر ایران: 09xxxxxxxxx
        if (preg_match('/^09\d{9}$/', $number)) {
            return $number;
        }

        return null;
    }
}

function get_gateway_configs($gateway)
{
    $gateway = \App\Models\Gateway::where('key', $gateway)->first();

    if (!$gateway) {
        return [];
    }

    $configs = [];

    switch ($gateway->key) {
        case "local": {
            // درگاه آزمایشی — فقط برای تست جریان پرداخت (در شبیه‌ساز درگاه رندر می‌شود)
            $configs['title']        = $gateway->config('title') ?? 'درگاه پرداخت آزمایشی';
            $configs['description']  = $gateway->config('description') ?? 'این درگاه فقط برای تست جریان پرداخت است — پول واقعی کم نمی‌شود';
            $configs['orderLabel']   = $gateway->config('orderLabel') ?? 'شماره سفارش';
            $configs['amountLabel']  = $gateway->config('amountLabel') ?? 'مبلغ';
            $configs['payButton']    = $gateway->config('payButton') ?? 'پرداخت (موفق)';
            $configs['cancelButton'] = $gateway->config('cancelButton') ?? 'لغو پرداخت';
            break;
        }
        case "zarinpal": {
            $configs['merchantId'] = $gateway->config('merchantId');
            break;
        }
        case "toman": {
            $user = auth()->user();
            $order =  $user->orders->last();
            $items = [];
            foreach ($order->items as $product) {

                $data = [
                    'name' => $product->title,
                    'price' => $product->price . 0,
                    'quantity' => $product->quantity
                ];
                array_push($items, $data);
                $data = null;
            }

            if ($order->shipping_cost > 0) {
                $shipping = [
                    'name' => $order->carrier->title,
                    'price' => $order->shipping_cost . 0,
                    'quantity' => 1
                ];
                array_push($items, $shipping);
            }

            $data = [
                'res_number' => $order->id,
                'return_to' => route('front.orders.verify', ['gateway' => 'toman']),
                'items' => $items
            ];

            $configs['shop_slug'] = $gateway->config('shop_slug');
            $configs['auth_code'] = $gateway->config('auth_code');
            $configs['data'] = $data;
            break;
        }
        case "payping": {
            $configs['merchantId'] = $gateway->config('merchantId');
            break;
        }
        case "irankish": {
            $configs['terminalId'] = $gateway->config('terminalId');
            $configs['password']   = $gateway->config('password');
            $configs['acceptorId'] = $gateway->config('acceptorId');
            $configs['pubKey']     = $gateway->config('pubKey');
            break;
        }
        case "idpay": {
            $configs['merchantId'] = $gateway->config('merchantId');
            break;
        }
        case "saman": {
            $configs['merchantId'] = $gateway->config('merchantId');
            break;
        }
        case "sep": {
            // سامان SEP — ترمینال جدید (UUID) یا عددی
            $configs['terminalId'] = $gateway->config('terminalId') ?: $gateway->config('merchantId');
            $mode = trim((string) ($gateway->config('mode') ?? ''));
            if (in_array($mode, ['v1', 'onlinepg'], true)) {
                $configs['mode'] = $mode; // خالی = خودکار (UUID → v1)
            }
            break;
        }
        case "behpardakht": {
            $configs['terminalId'] = $gateway->config('terminalId');
            $configs['username']   = $gateway->config('username');
            $configs['password']   = $gateway->config('password');
            break;
        }
        case "payir": {
            $configs['merchantId'] = $gateway->config('merchantId');
            break;
        }
        case "sepehr": {
            $configs['terminalId'] = $gateway->config('terminalId');
            break;
        }
        case "sadad": {
            $configs['key']        = $gateway->config('key');
            $configs['merchantId'] = $gateway->config('merchantId');
            $configs['terminalId'] = $gateway->config('terminalId');
            break;
        }
        case "zibal": {
            $configs['merchantId'] = $gateway->config('merchantId');
            break;
        }
    }

    return $configs;
}

/* ------------------------------------------------------------------ */
/*  Presentation helpers                                                */
/* ------------------------------------------------------------------ */

if (! function_exists('verta_date')) {
    /**
     * Format a date as Jalali (شمسی).
     */
    function verta_date($date, string $format = 'Y/m/d'): ?string
    {
        if (blank($date)) {
            return null;
        }

        try {
            return \Morilog\Jalali\Jalalian::forge($date)->format($format);
        } catch (\Throwable) {
            return null;
        }
    }
}

if (! function_exists('verta_from_now')) {
    /**
     * Jalali relative time, e.g. "۳ روز پیش".
     */
    function verta_from_now($date): ?string
    {
        if (blank($date)) {
            return null;
        }

        try {
            return \Morilog\Jalali\Jalalian::forge($date)->ago();
        } catch (\Throwable) {
            return null;
        }
    }
}

if (! function_exists('fa_num')) {
    /**
     * Convert digits to Persian numerals.
     */
    function fa_num(string|int|float|null $value): string
    {
        return strtr((string) $value, ['0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴', '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹']);
    }
}

if (! function_exists('en_num')) {
    /**
     * Convert Persian/Arabic digits to Latin numerals.
     */
    function en_num(string $value): string
    {
        return strtr($value, ['۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9', '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9']);
    }
}

if (! function_exists('money')) {
    /**
     * Human money format (تومان).
     */
    function money(string|int|float|null $amount, bool $withUnit = true): string
    {
        $formatted = number_format((float) en_num((string) $amount));

        return $withUnit ? $formatted . ' تومان' : $formatted;
    }
}

if (! function_exists('bytes_human')) {
    /**
     * Human readable file size.
     */
    function bytes_human($bytes, int $precision = 1): ?string
    {
        if (blank($bytes)) {
            return null;
        }

        $units = ['بایت', 'کیلوبایت', 'مگابایت', 'گیگابایت', 'ترابایت'];
        $index = 0;
        $size = (float) en_num((string) $bytes);

        while ($size >= 1024 && $index < count($units) - 1) {
            $size /= 1024;
            $index++;
        }

        return round($size, $precision) . ' ' . $units[$index];
    }
}

if (! function_exists('template_asset_url')) {
    /**
     * URL تصویر/فایل آپلودشده (الگوی «uploads/…») با پشتیبانی هر دو محل ذخیره‌سازی:
     *
     *  ۱) public/uploads/…            → سرو مستقیم وب‌سرور
     *  ۲) storage/app/public/uploads/… → سرو با روت uploads.serve (public غیرقابل‌نوشتن)
     *  ۳) هیچ‌کدام نبود               → تصویر پیش‌فرض (جلوگیری از آیکون شکسته)
     *
     * ریشه‌یابی باگ «تصویر قالب تغییر نمی‌کند»: وقتی فایل روی دیسک نیست،
     * قبلاً URL به‌اصطلاح معتبر ساخته می‌شد که 404 می‌داد و مرورگر تصویر را
     * نشان نمی‌داد؛ حالا مسیر واقعی فایل بررسی و URL درست ساخته می‌شود.
     */
    function template_asset_url(?string $path, ?string $default = null): ?string
    {
        $path = trim((string) $path);

        if ($path === '') {
            return $default ?? asset('images/package-default.png');
        }

        // URL کامل (خارجی) — دست‌نخورده
        if (preg_match('#^https?://#', $path)) {
            return $path;
        }

        // ۱) در public وجود دارد → asset مستقیم
        if (is_file(public_path($path))) {
            return asset($path);
        }

        // ۲) fallback داخل storage/app/public → همان URL اما با کش‌بریک تا
        //    مرورگر نسخه تازه را بگیرد (رفع «تصویر عوض نمی‌شود»)
        $fallback = storage_path('app/public/' . $path);

        if (is_file($fallback)) {
            return asset($path) . '?v=' . substr(md5($path . '|' . filemtime($fallback)), 0, 10);
        }

        // ۳) فایل فیزیکی یافت نشد → تصویر پیش‌فرض
        return $default ?? asset('images/package-default.png');
    }
}

if (! function_exists('jalali_to_carbon')) {
    /**
     * تبدیل ورودی تاریخ شمسی یا میلادی به Carbon — برای فیلتر بازه تاریخ در گزارش‌ها.
     *
     * پشتیبانی از: ۱۴۰۴/۰۷/۰۱ , 1404-07-01 , 2025-09-23 , 2025/09/23 (با ارقام فارسی هم)
     * اگر سال بین ۱۳۰۰ تا ۱۵۰۰ باشد → شمسی؛ در غیر این صورت → میلادی.
     *
     * @return \Carbon\Carbon|null تاریخ شروع/پایان (پایان = آخرِ روز)
     */
    function jalali_to_carbon(?string $input, bool $endOfDay = false): ?\Carbon\Carbon
    {
        $input = trim((string) en_num((string) $input));

        if ($input === '') {
            return null;
        }

        try {
            if (preg_match('/^(\d{4})\s*[\/\-]\s*(\d{1,2})\s*[\/\-]\s*(\d{1,2})$/', $input, $m)) {
                $year = (int) $m[1];

                // سال شمسی → Jalalian
                if ($year >= 1300 && $year <= 1500) {
                    $jalali = \Morilog\Jalali\Jalalian::fromFormat('Y/n/j', "{$year}/" . (int) $m[2] . '/' . (int) $m[3]);
                    $carbon = $jalali->toCarbon();
                } else {
                    $carbon = \Carbon\Carbon::createFromDate($year, (int) $m[2], (int) $m[3]);
                }
            } else {
                // فرمت‌های دیگر میلادی
                $carbon = \Carbon\Carbon::parse($input);
            }

            return $endOfDay ? $carbon->copy()->endOfDay() : $carbon->copy()->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }
}
