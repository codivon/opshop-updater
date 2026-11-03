<?php

namespace App\Services;

/**
 * رندر تصویری سؤال کپچا (PNG با GD + فونت Vazirmatn).
 *
 * چرا تصویر؟ سؤال متنی در HTML با یک regex قابل استخراج و با اسکریپت حل است؛
 * تصویرِ دارای نویز/چرخش/خطوط مزاحم برای حل شدن نیاز به OCR دارد که هزینهٔ
 * حمله را عملاً غیرقابل‌توجیه می‌کند (به‌ویژه در کنار تلهٔ زمان و مصرف یک‌بارمصرف).
 *
 *   - هر نویسه با زاویه/اندازه/رنگ/جابه‌جایی عمودی تصادفی رسم می‌شود
 *   - نویز نقطه‌ای + خطوط مزاحم روی بوم
 *   - پاسخ هرگز در تصویر یا HTML نیست — فقط در سشن سرور (CaptchaService)
 *   - fallback: بدون GD/فونت → متن لاتین با imagestring (بدون fatal error)
 *
 * خروجی فقط بایت‌های PNG است — هدرها در کنترلر ست می‌شوند (no-store).
 */
class CaptchaImageRenderer
{
    private const WIDTH = 150;

    private const HEIGHT = 46;

    /** مسیر فونت TTF — در ریپو نگه‌داری می‌شود تا روی هر هاستی کار کند */
    public static function fontPath(): string
    {
        return resource_path('fonts/Vazirmatn-Bold.ttf');
    }

    public static function supported(): bool
    {
        return function_exists('imagettftext')
            && function_exists('imagepng')
            && is_file(static::fontPath());
    }

    /**
     * بایت‌های PNG برای سؤال داده‌شده (مثل «۴ + ۳»).
     */
    public static function render(string $question): string
    {
        if (! static::supported()) {
            return static::renderFallback($question);
        }

        $width = static::WIDTH;
        $height = static::HEIGHT;

        $img = imagecreatetruecolor($width, $height);

        // بوم روشن خنثی — در تم تیره هم خوانا (کادر سفید دور تصویر در کامپوننت)
        $bg = imagecolorallocate($img, 244, 245, 247);
        imagefilledrectangle($img, 0, 0, $width, $height, $bg);

        // نویز نقطه‌ای کم‌رنگ
        for ($i = 0; $i < 240; $i++) {
            $c = imagecolorallocate($img, random_int(185, 225), random_int(185, 225), random_int(185, 225));
            imagesetpixel($img, random_int(0, $width - 1), random_int(0, $height - 1), $c);
        }

        // خطوط مزاحم — چند خط با زاویهٔ تصادفی
        for ($i = 0; $i < 3; $i++) {
            $c = imagecolorallocate($img, random_int(170, 210), random_int(170, 210), random_int(170, 210));
            imagesetthickness($img, random_int(1, 2));
            imageline(
                $img,
                random_int(0, (int) ($width * 0.3)),
                random_int(4, $height - 4),
                random_int((int) ($width * 0.7), $width - 1),
                random_int(4, $height - 4),
                $c
            );
        }

        // نویسه‌به‌نویسه: چرخش/اندازه/رنگ/جابه‌جایی عمودی تصادفی — خوانا برای انسان، سخت برای OCR
        $x = 14;

        foreach (mb_str_split($question) as $ch) {
            if ($ch === ' ' || $ch === "\u{a0}") {
                $x += random_int(5, 8);

                continue;
            }

            $size = random_int(19, 23);
            $angle = random_int(-20, 20);
            $y = 31 + random_int(-3, 3);
            $tone = random_int(35, 95);
            $color = imagecolorallocate($img, $tone, $tone, $tone + random_int(0, 15));

            $box = imagettftext($img, $size, $angle, $x, $y, $color, static::fontPath(), $ch);

            $x += max(11, ($box[2] - $box[0])) + random_int(1, 4);

            if ($x > $width - 14) {
                break;
            }
        }

        return static::toPng($img);
    }

    /**
     * بدون GD/فونت — متن لاتین ساده با فونت داخلی GD (حداقلِ حداقل، بدون خطا).
     */
    private static function renderFallback(string $question): string
    {
        if (! function_exists('imagepng')) {
            // بدون GD واقعاً تصویری نداریم — PNG یک‌پیکسلی شفاف تا UI نشکند
            return base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mPcv5+hHgAGgwJ/lK3Q6wAAAABJRU5ErkJggg==');
        }

        $img = imagecreatetruecolor(static::WIDTH, static::HEIGHT);
        $bg = imagecolorallocate($img, 244, 245, 247);
        imagefilledrectangle($img, 0, 0, static::WIDTH, static::HEIGHT, $bg);
        $color = imagecolorallocate($img, 60, 65, 75);

        imagestring($img, 5, 30, 15, en_num($question), $color);

        return static::toPng($img);
    }

    private static function toPng(\GdImage $img): string
    {
        ob_start();
        imagepng($img, null, 6);
        imagedestroy($img);

        return (string) ob_get_clean();
    }
}
