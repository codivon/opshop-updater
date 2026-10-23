<?php

namespace App\Livewire\Shop;

use App\Models\PackageVersion;
use App\Models\ProductVersion;
use App\Models\TemplateVersion;
use App\Support\Features;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * صفحهٔ عمومی تغییرات (Changelog) — /changelog
 *
 * تایم‌لاین نسخه‌های فعالِ پکیج + محصول + قالب (مرتب‌شده بر اساس تاریخ انتشار)
 * با فیلتر نوع. وقتی در پنل (امنیت و امکانات) خاموش باشد → 404.
 */
#[Layout('components.layouts.shop')]
class Changelog extends Component
{
    use WithPagination;

    /** فیلتر نوع آیتم: all | package | product | template */
    public string $kind = 'all';

    public function mount(): void
    {
        if (! Features::changelog()) {
            abort(404);
        }
    }

    public function filterKind(string $kind): void
    {
        $this->kind = in_array($kind, ['all', 'package', 'product', 'template'], true) ? $kind : 'all';
        $this->resetPage();
    }

    #[Computed]
    public function items(): LengthAwarePaginator
    {
        $rows = collect();

        $rows = $rows->merge($this->packageRows());
        $rows = $rows->merge($this->productRows());
        $rows = $rows->merge($this->templateRows());

        $rows = $rows->sortByDesc('date')->values();

        if ($this->kind !== 'all') {
            $rows = $rows->where('kind', $this->kind)->values();
        }

        // صفحه‌بندی دستی روی کالکشن یکپارچه
        $page     = Paginator::resolveCurrentPage();
        $perPage  = 15;
        $total    = $rows->count();

        return new Paginator(
            $rows->slice(($page - 1) * $perPage, $perPage)->values(),
            $total,
            $page,
            [
                'path'      => Paginator::resolveCurrentPath(),
                'pageName'  => 'page',
            ]
        );
    }

    public function render()
    {
        return view('livewire.shop.changelog', [
            'title'   => Features::changelogTitle(),
            'siteName' => Features::siteName(),
        ]);
    }

    /* ------------------------------------------------------------------ */

    protected function packageRows(): Collection
    {
        return PackageVersion::query()
            ->with('package:id,name,slug')
            ->where('status', PackageVersion::STATUS_ACTIVE)
            ->whereHas('package') // پکیج حذف‌شده را نشان نده
            ->orderByDesc('release_date')
            ->limit(150)
            ->get()
            ->map(fn (PackageVersion $v) => $this->row(
                kind: 'package',
                label: $v->package?->name ?? 'پکیج',
                url: $v->package?->slug ? route('shop.package', $v->package->slug) : null,
                version: $v->version,
                type: $v->type,
                date: $v->release_date,
                changelog: $v->changelog,
                added: $v->what_added,
                changed: $v->what_changed,
                fixed: $v->what_fixed,
            ));
    }

    protected function productRows(): Collection
    {
        return ProductVersion::query()
            ->with('product:id,name,slug')
            ->where('status', ProductVersion::STATUS_ACTIVE)
            ->whereHas('product')
            ->orderByDesc('release_date')
            ->limit(150)
            ->get()
            ->map(fn (ProductVersion $v) => $this->row(
                kind: 'product',
                label: $v->product?->name ?? 'محصول',
                url: $v->product?->slug ? route('shop.product', $v->product->slug) : null,
                version: $v->version,
                type: $v->type,
                date: $v->release_date,
                changelog: $v->changelog,
                added: $v->what_added,
                changed: $v->what_changed,
                fixed: $v->what_fixed,
            ));
    }

    protected function templateRows(): Collection
    {
        return TemplateVersion::query()
            ->with('template:id,name,slug')
            ->where('status', TemplateVersion::STATUS_ACTIVE)
            ->whereHas('template')
            ->orderByDesc('release_date')
            ->limit(150)
            ->get()
            ->map(fn (TemplateVersion $v) => $this->row(
                kind: 'template',
                label: $v->template?->name ?? 'قالب',
                url: $v->template?->slug ? route('shop.template', $v->template->slug) : null,
                version: $v->version,
                type: $v->type,
                date: $v->release_date,
                changelog: $v->changelog,
                added: $v->what_added,
                changed: $v->what_changed,
                fixed: $v->what_fixed,
            ));
    }

    /**
     * یک ردیف یکسان برای هر سه نوع نسخه.
     */
    protected function row(
        string $kind,
        string $label,
        ?string $url,
        string $version,
        string $type,
        mixed $date,
        ?string $changelog,
        ?string $added,
        ?string $changed,
        ?string $fixed,
    ): array {
        return [
            'kind'      => $kind,
            'label'     => $label,
            'url'       => $url,
            'version'   => $version,
            'type'      => $type,
            'date'      => $date,
            'changelog' => trim((string) $changelog),
            'added'     => trim((string) $added),
            'changed'   => trim((string) $changed),
            'fixed'     => trim((string) $fixed),
        ];
    }
}
