{{-- ۴۰۳ — دسترسی غیرمجاز (Task 73: طراحی مدرن فارسی) — پیام abort از $exception می‌آید --}}
@include('errors.modern', [
    'code' => '۴۰۳',
    'icon' => 'lock',
    'tone' => 'rose',
    'title' => 'دسترسی غیرمجاز',
    'message' => ($exception->getMessage() ?? '') !== ''
        ? $exception->getMessage()
        : 'شما اجازهٔ دسترسی به این بخش را ندارید؛ اگر فکر می‌کنید این یک اشتباه است، با مدیر سامانه تماس بگیرید.',
])
