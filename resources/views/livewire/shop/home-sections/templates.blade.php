{{-- بخش «templates» صفحهٔ اصلی — محتوا/سوییچ/ترتیب از پنل (Task 59) --}}
        @if($ho['home_templates_enabled'])
        <div class="mx-auto w-full max-w-7xl px-4 sm:px-6">
                <section id="shop-templates" class="scroll-mt-24">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <h2 class="flex items-center gap-2 text-lg font-black text-zinc-900 dark:text-zinc-50">
                                <x-icon name="layout-template" class="size-5 text-brand-600" />
                                قالب‌های آماده
                            </h2>
                            <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                                {{ fa_num($this->stats['templates']) }} قالب — خرید بدون ثبت‌نام؛ دانلود فوری پس از پرداخت.
                            </p>
                        </div>
                        <a href="{{ route('shop.templates') }}" wire:navigate>
                            <x-btn variant="secondary" size="sm" icon="arrow-left">
                                مشاهده همه + فیلترها
                            </x-btn>
                        </a>
                    </div>

                    @if($this->templates->count())
                        <div class="mt-5 grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3">
                            @foreach($this->templates as $template)
                                @include('livewire.shop.partials.template-card', ['template' => $template])
                            @endforeach
                        </div>
                    @else
                        <div class="card mt-5">
                            <x-empty icon="layout-template" title="هنوز قالبی منتشر نشده" description="به‌زودی قالب‌های جدید اضافه می‌شوند." />
                        </div>
                    @endif
                </section>
        </div>
        @endif
