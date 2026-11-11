{{-- ۵۰۳ — سرویس در دسترس نیست / حالت تعمیر (php artisan down) — Task 73 --}}
@include('errors.modern', [
    'code' => '۵۰۳',
    'icon' => 'wrench',
    'tone' => 'sky',
    'title' => 'به‌زودی برمی‌گردیم',
    'message' => ($exception->getMessage() ?? '') !== ''
        ? $exception->getMessage()
        : 'سایت در حال بروزرسانی یا تعمیر است؛ کمی دیگر دوباره سر بزنید.',
])
