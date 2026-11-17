@extends('back.layouts.master')

@section('title', 'مدیریت لاگ‌ها')

@section('content')
    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>لاگ‌های سیستم</h2>
            <div>
                <a href="{{ route('admin.logs.download') }}" class="btn btn-success me-2">
                    <i class="fas fa-download"></i> دانلود لاگ
                </a>
                <form action="{{ route('admin.logs.clear') }}" method="POST" class="d-inline" onsubmit="return confirm('آیا مطمئن هستید که می‌خواهید تمام لاگ‌ها را پاک کنید؟');">
                    @csrf
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-trash"></i> پاک کردن لاگ‌ها
                    </button>
                </form>
            </div>
        </div>


            <!-- فیلتر سطح لاگ -->
            <div class="card mb-3">
                <div class="card-body">
                    <form method="GET" action="{{ route('admin.logs.index') }}" class="row g-3">
                        <div class="col-auto">
                            <label class="visually-hidden">سطح لاگ</label>
                            <select name="level" class="form-select">
                                <option value="all" {{ request('level') == 'all' ? 'selected' : '' }}>همه سطوح</option>
                                <option value="error" {{ request('level') == 'error' ? 'selected' : '' }}>Error</option>
                                <option value="warning" {{ request('level') == 'warning' ? 'selected' : '' }}>Warning</option>
                                <option value="info" {{ request('level') == 'info' ? 'selected' : '' }}>Info</option>
                                <option value="debug" {{ request('level') == 'debug' ? 'selected' : '' }}>Debug</option>
                            </select>
                        </div>
                        <div class="col-auto">
                            <button type="submit" class="btn btn-primary">فیلتر</button>
                            <a href="{{ route('admin.logs.index') }}" class="btn btn-secondary">بازنشانی</a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- جدول لاگ‌ها -->
            <div class="table-responsive bg-white rounded shadow-sm">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                    <tr>
                        <th style="width: 180px;">تاریخ و زمان</th>
                        <th style="width: 100px;">سطح</th>
                        <th>پیام خطا / رویداد</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($paginatedLogs as $log)
                        <tr>
                            <td dir="ltr" class="text-end">{{ $log['date'] }}</td>
                            <td>
                                @php
                                    $badgeClass = match(strtolower($log['level'])) {
                                        'error' => 'bg-danger',
                                        'warning' => 'bg-warning text-dark',
                                        'info' => 'bg-info text-dark',
                                        'debug' => 'bg-secondary',
                                        default => 'bg-light text-dark'
                                    };
                                @endphp
                                <span class="badge {{ $badgeClass }}">{{ $log['level'] }}</span>
                            </td>
                            <td class="font-monospace small text-break">{{ \Illuminate\Support\Str::limit($log['message'], 150) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="text-center py-4">هیچ لاگی با این مشخصات یافت نشد.</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>

            <!-- صفحه‌بندی -->
            <div class="mt-3">
                {{ $paginatedLogs->links() }}
            </div>
        @endif
    </div>
@endsection
