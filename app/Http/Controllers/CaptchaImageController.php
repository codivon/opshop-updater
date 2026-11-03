<?php

namespace App\Http\Controllers;

use App\Services\CaptchaImageRenderer;
use App\Services\CaptchaService;
use Symfony\Component\HttpFoundation\Response;

/**
 * تصویر کپچا — سؤالِ فعلی سشن را به‌صورت PNG با نویز برمی‌گرداند.
 *
 *   - سؤال را «مصرف نمی‌کند» و تغییر نمی‌دهد (فقط در صورت نبود/انقضا می‌سازد)
 *   - پاسخ فقط در سشن است؛ در تصویر و URL و HTML هیچ‌جا نیست
 *   - no-store: تصویر نباید در کش مرورگر/پروکسی بماند (سؤال یکی‌ماندِ کهنه!)
 *   - throttle ملایم: جلوی سوءاستفادهٔ تولید انبوه تصویر را می‌گیرد
 */
class CaptchaImageController extends Controller
{
    public function show(): Response
    {
        // در صورت نبود/انقضای سؤال، سؤال تازه ساخته می‌شود (نسخه هم جلو می‌رود)
        CaptchaService::question();

        $png = CaptchaImageRenderer::render((string) session(CaptchaService::SESSION_QUESTION));

        return response($png, 200, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
