<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * پیام ثبت‌شده از فرم «تماس با ما» صفحهٔ اصلی فروشگاه.
 *
 * نوع درخواست (request_type) چهار مقدار دارد؛ برچسب فارسی در TYPES.
 * وضعیت: new (جدید) → read (خوانده‌شده توسط مدیر).
 */
class ContactMessage extends Model
{
    public const STATUS_NEW  = 'new';
    public const STATUS_READ = 'read';

    public const STATUS_LABELS = [
        self::STATUS_NEW  => 'جدید',
        self::STATUS_READ => 'خوانده‌شده',
    ];

    public const TYPES = [
        'template' => 'قالب اختصاصی',
        'project'  => 'پروژه اختصاصی',
        'startup'  => 'استارتاپ اختصاصی',
        'consult'  => 'مشاوره',
    ];

    protected $fillable = [
        'name',
        'phone',
        'email',
        'request_type',
        'message',
        'status',
        'ip',
    ];

    /** تعداد پیام‌های جدید (نشان قرمز منوی پنل) */
    public static function newCount(): int
    {
        return static::query()->where('status', self::STATUS_NEW)->count();
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->request_type] ?? $this->request_type;
    }

    public function isNew(): bool
    {
        return $this->status === self::STATUS_NEW;
    }
}
