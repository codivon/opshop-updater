@extends('back.layouts.master')

@push('styles')
    <style>
        .gw-wrapper {
            display: flex;
            flex-direction: column;
            gap: 1.25rem;
        }

        .gw-card {
            border: 1px solid #e9ecef;
            border-radius: 14px;
            overflow: hidden;
            transition: box-shadow .2s ease, border-color .2s ease;
            background: #fff;
        }

        .gw-card:hover {
            box-shadow: 0 6px 18px rgba(0, 0, 0, .06);
        }

        .gw-card.is-active {
            border-color: #7367f0;
            box-shadow: 0 4px 14px rgba(115, 103, 240, .12);
        }

        .gw-card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            padding: 1rem 1.25rem;
            background: #f8f8fb;
            cursor: pointer;
            user-select: none;
        }

        .gw-card.is-active .gw-card-header {
            background: linear-gradient(135deg, rgba(115, 103, 240, .08), rgba(115, 103, 240, .02));
        }

        .gw-title-group {
            display: flex;
            align-items: center;
            gap: .85rem;
        }

        .gw-icon {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #eceafd;
            color: #7367f0;
            font-weight: 700;
            font-size: 1.1rem;
            flex-shrink: 0;
        }

        .gw-name {
            font-weight: 600;
            font-size: 1.02rem;
            color: #3b3b4f;
            margin: 0;
        }

        .gw-status {
            font-size: .75rem;
            display: inline-flex;
            align-items: center;
            gap: .3rem;
            padding: .15rem .6rem;
            border-radius: 20px;
            background: #f1f1f4;
            color: #82868b;
            margin-top: .2rem;
        }

        .gw-status.on {
            background: #e5f8ee;
            color: #28c76f;
        }

        .gw-status .dot {
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: currentColor;
        }

        .gw-header-right {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .gw-chevron {
            transition: transform .2s ease;
            color: #b9b9c3;
        }

        .gw-card.is-open .gw-chevron {
            transform: rotate(180deg);
        }

        .gw-card-body {
            display: none;
            padding: 1.25rem;
            border-top: 1px solid #eee;
        }

        .gw-card.is-open .gw-card-body {
            display: block;
        }

        /* Toggle switch */
        .gw-switch {
            position: relative;
            display: inline-block;
            width: 44px;
            height: 24px;
            flex-shrink: 0;
        }

        .gw-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .gw-switch .slider {
            position: absolute;
            cursor: pointer;
            inset: 0;
            background-color: #d8d6de;
            border-radius: 24px;
            transition: .2s;
        }

        .gw-switch .slider::before {
            position: absolute;
            content: "";
            height: 18px;
            width: 18px;
            right: 3px;
            top: 3px;
            background-color: #fff;
            border-radius: 50%;
            transition: .2s;
            box-shadow: 0 1px 3px rgba(0, 0, 0, .2);
        }

        .gw-switch input:checked+.slider {
            background-color: #7367f0;
        }

        .gw-switch input:checked+.slider::before {
            transform: translateX(-20px);
        }

        .gw-field label {
            font-size: .8rem;
            color: #6e6b7b;
            font-weight: 600;
            margin-bottom: .35rem;
        }

        .gw-field .form-control {
            border-radius: 8px;
        }

        .gw-order-input {
            max-width: 100px;
        }

        .gw-required::after {
            content: " *";
            color: #ea5455;
        }

        .gw-summary-bar {
            display: flex;
            flex-wrap: wrap;
            gap: .5rem;
            margin-bottom: 1.25rem;
        }

        .gw-summary-pill {
            font-size: .8rem;
            padding: .4rem .85rem;
            border-radius: 20px;
            background: #f8f8fb;
            color: #6e6b7b;
            display: flex;
            align-items: center;
            gap: .4rem;
            border: 1px solid #eee;
        }

        .gw-save-bar {
            position: sticky;
            bottom: 0;
            background: #fff;
            border-top: 1px solid #eee;
            padding: 1rem 1.25rem;
            margin-top: 1rem;
            border-radius: 0 0 14px 14px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: .75rem;
        }

        .gw-save-bar .hint {
            font-size: .82rem;
            color: #82868b;
            display: flex;
            align-items: center;
            gap: .4rem;
        }
    </style>
@endpush

@section('content')
    <div class="app-content content">
        <div class="content-overlay"></div>
        <div class="header-navbar-shadow"></div>
        <div class="content-wrapper">
            <div class="content-header row">
                <div class="content-header-left col-md-9 col-12 mb-2">
                    <div class="row breadcrumbs-top">
                        <div class="col-12">
                            <div class="breadcrumb-wrapper col-12">
                                <ol class="breadcrumb no-border">
                                    <li class="breadcrumb-item">مدیریت</li>
                                    <li class="breadcrumb-item">تنظیمات</li>
                                    <li class="breadcrumb-item active">درگاه های پرداخت</li>
                                </ol>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="content-body">
                <section class="users-edit">
                    <div class="card">
                        <div id="main-card" class="card-content">
                            <div class="card-body">

                                @php
                                    $gwList = [
                                        ['key' => 'payir', 'label' => 'درگاه pay.ir', 'abbr' => 'PI', 'fields' => [
                                            ['name' => 'merchantId', 'label' => 'api کد', 'type' => 'text'],
                                        ]],
                                        ['key' => 'behpardakht', 'label' => 'درگاه بانک ملت', 'abbr' => 'ملت', 'fields' => [
                                            ['name' => 'username', 'label' => 'نام کاربری', 'type' => 'text'],
                                            ['name' => 'password', 'label' => 'رمز عبور', 'type' => 'text'],
                                            ['name' => 'terminalId', 'label' => 'کد پذیرنده', 'type' => 'text'],
                                        ]],
                                        ['key' => 'zarinpal', 'label' => 'درگاه زرین پال', 'abbr' => 'ZP', 'fields' => [
                                            ['name' => 'merchantId', 'label' => 'کد درگاه پرداخت', 'type' => 'text'],
                                        ]],
                                        ['key' => 'toman', 'label' => 'درگاه تومن', 'abbr' => 'TM', 'fields' => [
                                            ['name' => 'shop_slug', 'label' => 'shop slug', 'type' => 'text', 'col' => 4],
                                            ['name' => 'auth_code', 'label' => 'auth code', 'type' => 'text', 'col' => 8],
                                        ]],
                                        ['key' => 'payping', 'label' => 'درگاه پی پینگ', 'abbr' => 'PP', 'fields' => [
                                            ['name' => 'merchantId', 'label' => 'کد درگاه پرداخت', 'type' => 'text'],
                                        ]],
                                        ['key' => 'irankish', 'label' => 'درگاه ایران کیش', 'abbr' => 'IK', 'fields' => [
                                            ['name' => 'terminalId', 'label' => 'کد پایانه', 'type' => 'text'],
                                            ['name' => 'acceptorId', 'label' => 'کد پذیرنده', 'type' => 'text'],
                                            ['name' => 'password', 'label' => 'کلمه عبور', 'type' => 'text'],
                                            ['name' => 'pubKey', 'label' => 'کلید عمومی', 'type' => 'textarea', 'col' => 6],
                                        ]],
                                        ['key' => 'idpay', 'label' => 'درگاه idpay', 'abbr' => 'ID', 'fields' => [
                                            ['name' => 'merchantId', 'label' => 'کد درگاه پرداخت', 'type' => 'text'],
                                        ]],
                                        ['key' => 'sepehr', 'label' => 'درگاه سپهر (بانک صادرات)', 'abbr' => 'سپهر', 'fields' => [
                                            ['name' => 'terminalId', 'label' => 'کد پذیرنده', 'type' => 'text'],
                                        ]],
                                        ['key' => 'saman', 'label' => 'درگاه سامان', 'abbr' => 'سامان', 'fields' => [
                                            ['name' => 'merchantId', 'label' => 'کد پذیرنده', 'type' => 'text'],
                                        ]],
                                        ['key' => 'sadad', 'label' => 'درگاه بانک ملی (سداد)', 'abbr' => 'ملی', 'fields' => [
                                            // Task 97: برچسب‌های قدیمی جابجا بودند (terminalId را «شماره پذیرنده» می‌نوشت) →
                                            // شماره پذیرنده جای ترمینال ذخیره می‌شد و بانک خطای عمومی می‌داد. حالا دقیقاً
                                            // مطابق واژگان اطلاعیهٔ رسمی بانک ملی برچسب‌گذاری شده + hint برای هر فیلد.
                                            ['name' => 'merchantId', 'label' => 'شماره پذیرنده', 'type' => 'text', 'hint' => '«شماره پذیرنده» در اطلاعیهٔ بانک (مثل 000000140352535 — صفرهای ابتدایی را نگه دارید)'],
                                            ['name' => 'terminalId', 'label' => 'شماره ترمینال', 'type' => 'text', 'hint' => '«شماره ترمینال» در اطلاعیهٔ بانک — نه شماره پذیرنده!'],
                                            ['name' => 'key', 'label' => 'کلید ترمینال', 'type' => 'text', 'hint' => '«کلید ترمینال» (کلید تراکنش) در اطلاعیهٔ بانک — رشتهٔ base64'],
                                        ]],
                                        ['key' => 'zibal', 'label' => 'درگاه زیبال', 'abbr' => 'ZB', 'fields' => [
                                            ['name' => 'merchantId', 'label' => 'کد پذیرنده', 'type' => 'text'],
                                        ]],
                                    ];

                                    $activeCount = $gateways->where('is_active', true)->count();
                                    $totalCount = $gateways->count();
                                @endphp

                                <div class="gw-summary-bar">
                                    <span class="gw-summary-pill">
                                        <i class="feather icon-check-circle" style="color:#28c76f"></i>
                                        {{ $activeCount }} از {{ $totalCount }} درگاه فعال
                                    </span>
                                    <span class="gw-summary-pill">
                                        <i class="feather icon-info"></i>
                                        برای فعال‌سازی، درگاه را روشن کرده و اطلاعات آن را تکمیل کنید
                                    </span>
                                </div>

                                <form id="gateway-form" action="{{ route('admin.settings.gateways') }}" method="POST">
                                    @csrf

                                    <div class="gw-wrapper">
                                        @foreach ($gwList as $index => $g)
                                            @php
                                                $gateway = $gateways->where('key', $g['key'])->first();
                                                if (!$gateway) continue;
                                            @endphp
                                            <div class="gw-card {{ $gateway->is_active ? 'is-active is-open' : '' }} "
                                                 data-gw="{{ $g['key'] }}">

                                                <div class="gw-card-header" data-gw-toggle>
                                                    <div class="gw-title-group">
                                                        <div class="gw-icon">{{ $g['abbr'] }}</div>
                                                        <div>
                                                            <p class="gw-name">{{ $g['label'] }}</p>
                                                            <span class="gw-status {{ $gateway->is_active ? 'on' : '' }}">
                                                                <span class="dot"></span>
                                                                {{ $gateway->is_active ? 'فعال' : 'غیرفعال' }}
                                                            </span>
                                                        </div>
                                                    </div>

                                                    <div class="gw-header-right">
                                                        <label class="gw-switch" onclick="event.stopPropagation()">
                                                            <input data-class="{{ $g['key'] }}" type="checkbox"
                                                                   name="gateways[{{ $gateway->id }}][is_active]"
                                                                {{ $gateway->is_active ? 'checked' : '' }}>
                                                            <span class="slider"></span>
                                                        </label>
                                                        <i class="feather icon-chevron-down gw-chevron"></i>
                                                    </div>
                                                </div>

                                                <div class="gw-card-body">
                                                    <div class="row">
                                                        <div class="col-md-2 col-6 form-group gw-field">
                                                            <label>ترتیب نمایش</label>
                                                            <input type="number"
                                                                   name="gateways[{{ $gateway->id }}][ordering]"
                                                                   class="form-control ltr {{ $g['key'] }} gw-order-input"
                                                                   value="{{ $gateway->ordering }}">
                                                        </div>

                                                        <div class="col-md-4 col-6 form-group gw-field">
                                                            <label>عنوان</label>
                                                            <input type="text"
                                                                   name="gateways[{{ $gateway->id }}][name]"
                                                                   class="form-control {{ $g['key'] }}"
                                                                   value="{{ $gateway->name }}">
                                                        </div>

                                                        @foreach ($g['fields'] as $field)
                                                            <div class="col-md-{{ $field['col'] ?? 4 }} col-12 form-group gw-field">
                                                                <label class="gw-required">{{ $field['label'] }}</label>
                                                                @if (($field['type'] ?? 'text') === 'textarea')
                                                                    <textarea rows="3"
                                                                              name="gateways[{{ $gateway->id }}][configs][{{ $field['name'] }}]"
                                                                              class="form-control ltr {{ $g['key'] }}"
                                                                              required>{{ $gateway->config($field['name']) }}</textarea>
                                                                @else
                                                                    <input type="text"
                                                                           name="gateways[{{ $gateway->id }}][configs][{{ $field['name'] }}]"
                                                                           class="form-control ltr {{ $g['key'] }}"
                                                                           value="{{ $gateway->config($field['name']) }}"
                                                                           required>
                                                                @endif
                                                                @if (! empty($field['hint']))
                                                                    <small class="form-text text-muted d-block mt-1">{{ $field['hint'] }}</small>
                                                                @endif
                                                            </div>
                                                        @endforeach
                                                    </div>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>

                                    <div class="gw-save-bar">
                                        <span class="hint">
                                            <i class="feather icon-info"></i>
                                            برای فعال نمودن هر یک از درگاه‌ها، پس از انتخاب درگاه اطلاعات مربوط به آن را پر کنید.
                                        </span>
                                        <button type="submit" class="btn btn-primary glow">
                                            <i class="feather icon-save ml-1 align-middle"></i>
                                            ذخیره تغییرات
                                        </button>
                                    </div>
                                </form>

                            </div>
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </div>
@endsection

{{--@include('back.partials.plugins', ['plugins' => ['jquery.validate']])--}}

@push('scripts')
    <script>
        (function () {
            document.querySelectorAll('[data-gw-toggle]').forEach(function (header) {
                header.addEventListener('click', function () {
                    header.closest('.gw-card').classList.toggle('is-open');
                });
            });

            document.querySelectorAll('.gw-switch input[type="checkbox"]').forEach(function (checkbox) {
                checkbox.addEventListener('change', function () {
                    var card = checkbox.closest('.gw-card');
                    var statusEl = card.querySelector('.gw-status');
                    card.classList.toggle('is-active', checkbox.checked);
                    card.classList.toggle('is-open', checkbox.checked);
                    statusEl.classList.toggle('on', checkbox.checked);
                    statusEl.innerHTML = '<span class="dot"></span>' + (checkbox.checked ? 'فعال' : 'غیرفعال');
                    if (checkbox.checked) {
                        card.classList.add('is-open');
                    }
                });
            });
        })();
    </script>
    <script src="{{ asset('back/assets/js/pages/settings/gateways.js') }}?v=2"></script>
@endpush
