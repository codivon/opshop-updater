<?php

namespace App\Livewire\Logs;

use App\Livewire\Concerns\AuthorizesAdmin;
use App\Livewire\Concerns\WithBulkActions;
use App\Livewire\Concerns\WithToasts;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\File;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
#[Title('لاگ‌های سیستم')]
class Index extends Component
{
    use AuthorizesAdmin;
    use WithBulkActions, WithPagination, WithToasts;

    #[Url]
    public string $search = '';

    /** error | warning | info | … (هر سطحی که در فایل لاگ وجود داشته باشد) */
    #[Url]
    public string $level = '';

    /** فیلتر نمایش/ترتیب: جدیدترین (پیش‌فرض)، قدیمی‌ترین، بر اساس سطح */
    #[Url]
    public string $sort = 'newest';

    public bool $confirmClear = false;

    /** کلید ورودی برای حذف تکی (اندیس در آرایه‌ی parsed) */
    public ?int $deleteKey = null;

    /** تعداد رکورد در هر صفحه */
    protected const PER_PAGE = 25;

    /** @var array<int, array<string, string>>|null */
    protected ?array $parsedCache = null;

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedLevel(): void
    {
        $this->resetPage();
    }

    public function updatedSort(): void
    {
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->reset('search', 'level');
        $this->resetPage();
    }

    /* ---------------------------------------------------------------- */
    /*  Data */
    /* ---------------------------------------------------------------- */

    /**
     * ورودی‌های فیلتر/جستجو/مرتب‌شده — کلید هر آیتم = اندیس همان ورودی در parsed()
     * (حذف ورودی‌ها با همین کلیدها انجام می‌شود، پس نباید re-index شوند).
     */
    #[Computed]
    public function records(): LengthAwarePaginator
    {
        $entries = $this->parsed();

        // فیلتر بر اساس سطح (array_filter کلیدها را حفظ می‌کند)
        if ($this->level !== '') {
            $entries = array_filter(
                $entries,
                fn (array $entry) => strtolower($entry['level']) === strtolower($this->level)
            );
        }

        // جستجو در تاریخ، سطح و متن پیام
        if ($this->search !== '') {
            $needle = strtolower(trim(en_num($this->search)));
            $entries = array_filter($entries, function (array $entry) use ($needle) {
                $haystack = strtolower(
                    $entry['date'].' '.$entry['level'].' '.$entry['message'].' '.($entry['context'] ?? '')
                );

                return str_contains($haystack, $needle);
            });
        }

        // ترتیب نمایش (کلیدها همچنان به parsed اشاره می‌کنند)
        if ($this->sort === 'oldest') {
            $entries = array_reverse($entries, true);
        } elseif ($this->sort === 'level') {
            uasort($entries, fn (array $a, array $b) => strcasecmp($a['level'], $b['level']));
        }
        // «newest» = ترتیب پیش‌فرض (جدیدترین اول)

        // صفحه‌بندی دستی (مجموعه فایل‌محور) — preserve_keys برای پایداری کلیدها
        $page = max((int) LengthAwarePaginator::resolveCurrentPage(), 1);
        $perPage = static::PER_PAGE;
        $items = array_slice($entries, ($page - 1) * $perPage, $perPage, true);

        return new LengthAwarePaginator($items, count($entries), $perPage, $page, [
            'path' => Paginator::resolveCurrentPath(),
        ]);
    }

    /** سطوح موجود در فایل لاگ (برای فیلتر) */
    #[Computed]
    public function levels(): array
    {
        $levels = array_unique(array_map(
            fn (array $entry) => strtolower($entry['level']),
            $this->parsed()
        ));

        sort($levels);

        return $levels;
    }

    #[Computed]
    public function fileSize(): ?int
    {
        return File::exists($this->logPath())
            ? (int) File::size($this->logPath())
            : null;
    }

    /* ---------------------------------------------------------------- */
    /*  Actions (پورت‌شده از LogController) */
    /* ---------------------------------------------------------------- */

    /** دانلود فایل لاگ (پورت LogController@download) */
    public function download()
    {
        if (! File::exists($this->logPath())) {
            $this->toast('فایل لاگ یافت نشد.', 'error');

            return null;
        }

        return response()->download($this->logPath(), 'system.log');
    }

    /** پاک کردن کامل فایل لاگ (پورت LogController@clear) */
    public function clear(): void
    {
        if (File::exists($this->logPath())) {
            File::put($this->logPath(), '');
        }

        $this->confirmClear = false;
        $this->parsedCache = null;
        unset($this->records, $this->levels, $this->fileSize);
        $this->resetPage();

        $this->toast('لاگ‌ها با موفقیت پاک شدند.');
    }

    /* ---------------------------------------------------------------- */
    /*  Bulk selection (WithBulkActions) */
    /* ---------------------------------------------------------------- */

    /** کلید ردیف‌های صفحه‌ی جاری (اندیس‌های آرایه‌ی parsed به‌صورت رشته) */
    public function bulkPageIds(): array
    {
        return array_map('strval', array_keys($this->records->items()));
    }

    public function deleteSelectedRecords(): void
    {
        $this->deleteLogEntries($this->selectedIds);
    }

    /** حذف تکی ورودی لاگ (کلید = اندیس در parsed) */
    public function delete(): void
    {
        if ($this->deleteKey === null) {
            return;
        }

        $this->deleteLogEntries([(string) $this->deleteKey]);
        $this->deleteKey = null;
    }

    /**
     * حذف چند ورودی از فایل لاگ بر اساس کلید (اندیس در parsed).
     * فایل دوباره parse می‌شود، ورودی‌ها حذف و بقیه با همان قالب اصلی
     * ([تاریخ] env.LEVEL: پیام + کانتکست، خط خالی بین ورودی‌ها) بازنویسی می‌شوند.
     *
     * @param  array<int, string>  $keys
     */
    public function deleteLogEntries(array $keys): void
    {
        $keys = array_values(array_unique(array_map('intval', $keys)));

        if ($keys === []) {
            return;
        }

        $entries = $this->parsed();

        $remaining = array_filter(
            $entries,
            fn (array $entry, int $key) => ! in_array($key, $keys, true),
            ARRAY_FILTER_USE_BOTH
        );

        // بازسازی فایل در ترتیب اصلی (قدیمی → جدید) با خط خالی بین ورودی‌ها
        $blocks = [];
        foreach (array_reverse(array_values($remaining)) as $entry) {
            $block = "[{$entry['date']}] {$entry['env']}.{$entry['level']}: {$entry['message']}";
            if (($entry['context'] ?? '') !== '') {
                $block .= "\n{$entry['context']}";
            }
            $blocks[] = $block;
        }

        File::put($this->logPath(), $blocks === [] ? '' : implode("\n\n", $blocks)."\n");

        $this->parsedCache = null;
        unset($this->records, $this->levels, $this->fileSize);
        $this->resetPage();

        $this->toast(fa_num(count($keys)).' ورودی لاگ حذف شد.');
    }

    public function render()
    {
        return view('livewire.logs.index');
    }

    /* ---------------------------------------------------------------- */
    /*  Helpers */
    /* ---------------------------------------------------------------- */

    protected function logPath(): string
    {
        return storage_path('logs/laravel.log');
    }

    /**
     * تجزیه ساده لاگ‌ها (هر لاگ با تاریخ شروع می‌شود) – پورت‌شده از LogController.
     * الگو: [2024-01-01 12:00:00] production.ERROR: ...
     *
     * @return array<int, array<string, string>>
     */
    protected function parsed(): array
    {
        if ($this->parsedCache !== null) {
            return $this->parsedCache;
        }

        if (! File::exists($this->logPath())) {
            return $this->parsedCache = [];
        }

        $content = (string) File::get($this->logPath());

        preg_match_all(
            '/\[(.*?)\]\s+(\w+)\.(.*?):\s(.*?)(?=\n\[\d{4}-\d{2}-\d{2}|\z)/s',
            $content,
            $matches,
            PREG_SET_ORDER
        );

        $logs = [];
        foreach ($matches as $match) {
            // اولین خط = پیام، باقی = استک‌تریس/کانتکست
            $lines = preg_split('/\r\n|\r|\n/', trim((string) $match[4])) ?: [];

            $logs[] = [
                'date' => $match[1],
                // [تاریخ] environment.ERROR: ← گروه ۲ = محیط، گروه ۳ = سطح لاگ
                'env' => $match[2],
                'level' => $match[3],
                'message' => (string) array_shift($lines),
                'context' => trim(implode("\n", $lines)),
            ];
        }

        // نمایش جدیدترین‌ها اول (مطابق کنترلر قدیمی)
        return $this->parsedCache = array_reverse($logs);
    }
}
