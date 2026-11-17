<?php

namespace App\Http\Controllers;

use App\Models\Package;
use App\Models\Product;
use App\Models\Template;
use App\Support\Features;
use App\Support\Seo;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

/**
 * نقاط انتهایی سئو (Task 82):
 * - sitemap.xml : نقشهٔ سایت پویا از دیتابیس (کش ۳۰ دقیقه)
 * - robots.txt  : پویا بر اساس تنظیمات پنل (سئو ← خزنده‌ها)
 * - llms.txt / llms-full.txt : خلاصهٔ سایت برای موتورهای جستجوی هوش مصنوعی
 *
 * ⚠️ فایل فیزیکی public/robots.txt حذف شد تا نسخهٔ پویا سرو شود
 * (فایل ثابت بر روت اولویت دارد و تنظیمات پنل را می‌خشکاند).
 */
class SeoController extends Controller
{
    public function sitemap(): Response
    {
        abort_unless(Seo::on('seo_sitemap_enabled'), 404);

        $xml = Cache::remember('seo:sitemap', now()->addMinutes(30), fn () => $this->buildSitemap());

        return response($xml, 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
        ]);
    }

    public function robots(): Response
    {
        return response(Seo::robotsTxt(), 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
        ]);
    }

    public function llms(): Response
    {
        abort_unless(Seo::on('seo_llms_enabled'), 404);

        return response(Seo::llmsTxt(), 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
        ]);
    }

    public function llmsFull(): Response
    {
        abort_unless(Seo::on('seo_llms_enabled'), 404);

        return response(Seo::llmsFullTxt(), 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
        ]);
    }

    /* ------------------------------------------------------------------ */

    /** ساخت XML نقشهٔ سایت — فقط صفحات قابل ایندکس */
    private function buildSitemap(): string
    {
        $root = Seo::canonicalHostRoot();
        $entries = [];

        $add = function (string $loc, ?string $lastmod = null, string $changefreq = 'weekly', string $priority = '0.8') use (&$entries, $root) {
            $entry = ['loc' => $root.$loc, 'changefreq' => $changefreq, 'priority' => $priority];

            if ($lastmod) {
                $entry['lastmod'] = $lastmod;
            }

            $entries[] = $entry;
        };

        $formatDate = fn ($date) => $date ? $date->toDateString() : null;

        // صفحهٔ اصلی و فهرست‌ها
        $add('/', null, 'daily', '1.0');
        $add('/shop/packages', null, 'daily', '0.9');
        $add('/shop/products', null, 'daily', '0.9');
        $add('/shop/templates', null, 'daily', '0.9');
        $add('/subscriptions', null, 'weekly', '0.7');

        // تاریخچه تغییرات — فقط وقتی امکانات فعال است (غیرفعال = 404)
        if (Features::changelog()) {
            $add('/changelog', null, 'daily', '0.6');
        }

        // پکیج‌های فعال
        Package::query()
            ->where('status', Package::STATUS_ACTIVE)
            ->orderByDesc('updated_at')
            ->get(['slug', 'updated_at'])
            ->each(fn (Package $p) => $add('/packages/'.$p->slug, $formatDate($p->updated_at), 'weekly', '0.9'));

        // محصولات (پروژه‌های قابل‌فروش) فعال
        Product::query()
            ->where('status', Product::STATUS_ACTIVE)
            ->orderByDesc('updated_at')
            ->get(['slug', 'updated_at'])
            ->each(fn (Product $p) => $add('/products/'.$p->slug, $formatDate($p->updated_at), 'weekly', '0.9'));

        // قالب‌های فعال (فروش‌رفتهٔ اختصاصی حذف می‌شوند)
        Template::query()
            ->where('status', Template::STATUS_ACTIVE)
            ->where('is_sold_exclusively', false)
            ->orderByDesc('updated_at')
            ->get(['slug', 'updated_at'])
            ->each(fn (Template $t) => $add('/templates/'.$t->slug, $formatDate($t->updated_at), 'weekly', '0.9'));

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";

        foreach ($entries as $entry) {
            $xml .= "  <url>\n";
            $xml .= '    <loc>'.htmlspecialchars($entry['loc'], ENT_XML1).'</loc>'."\n";

            if (! empty($entry['lastmod'])) {
                $xml .= '    <lastmod>'.$entry['lastmod'].'</lastmod>'."\n";
            }

            $xml .= '    <changefreq>'.$entry['changefreq'].'</changefreq>'."\n";
            $xml .= '    <priority>'.$entry['priority'].'</priority>'."\n";
            $xml .= "  </url>\n";
        }

        $xml .= '</urlset>'."\n";

        return $xml;
    }
}
