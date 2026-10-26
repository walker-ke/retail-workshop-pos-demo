@extends('layouts.app')

@section('content')
@include('layouts.includes.sidebar')
@push('styles')
<link rel="stylesheet" href="{{asset('assets/css/dashboard.css')}}?v={{ filemtime(public_path('assets/css/dashboard.css')) }}">
@endpush

<div class="dash-shell">
    <div class="dash-top">
        <div class="dash-card dash-chart-card">
            <div class="dash-chart-header">
                <div>
                    <div class="dash-chart-title">Weekly Sales</div>
                    <div class="dash-chart-sub">{{ $weekStart->format('d M') }} – {{ $weekEnd->format('d M Y') }} · Daily sales</div>
                </div>
                <div class="dash-chart-nav">
                    <a href="{{ route('home', array_merge(request()->query(), ['week' => $weekStart->toDateString()])) }}" class="dash-nav-btn" id="refreshDashBtn" title="Refresh"><i class="fa-solid fa-arrows-rotate"></i></a>
                    <a href="{{ route('home', array_merge(request()->query(), ['week' => $weekStart->copy()->subWeek()->toDateString()])) }}" class="dash-nav-btn" title="Previous week"><i class="fa-solid fa-chevron-left"></i></a>
                    <a href="{{ route('home', array_merge(request()->query(), ['week' => $weekStart->copy()->addWeek()->toDateString()])) }}" class="dash-nav-btn" title="Next week"><i class="fa-solid fa-chevron-right"></i></a>
                </div>
            </div>
            <div class="dash-bar-chart">
                @for($day = 0; $day < 7; $day++)
                    @php $amount = $weeklySales[$day]; @endphp
                    <div class="dash-bar-col">
                        <div class="dash-bar-track">
                            <div class="dash-bar" style="height: {{ $maxWeekly > 0 ? max(2, round($amount / $maxWeekly * 100)) : 2 }}%" title="{{ number_format($amount, 2) }}"></div>
                        </div>
                        <div class="dash-bar-value">{{ $amount > 0 ? number_format($amount, 0) : 0 }}</div>
                        <div class="dash-bar-label">{{ $weekStart->copy()->addDays($day)->format('D') }}</div>
                    </div>
                @endfor
            </div>
        </div>

        <div class="dash-card dash-total-card">
            <div class="dash-total-title">Sales this month · {{ $monthStart->format('F Y') }}</div>
            <div class="dash-total-number">{{ $monthOrderCount }}</div>
            <div class="dash-total-sub">Top performing day:</div>
            <div class="dash-total-month">{{ $topDayName ?: '---' }}</div>
            <div class="dash-total-amount">{{ number_format($topDayAmount, 2) }}</div>
        </div>
    </div>

    <div class="dash-period-bar">
        <div class="dash-period-title">Periodic Reports ( {{ $from->format('d/m/Y') }} - {{ $to->format('d/m/Y') }} )</div>
        @include('partials.shop_picker', ['wrapClass' => 'text-muted'])
        <button type="button" class="dash-nav-btn" id="dateRangeToggle" title="Change date range"><i class="fa-regular fa-calendar"></i></button>
    </div>

    <form method="GET" action="{{ route('home') }}" class="dash-date-form" id="dateRangeForm" style="display:none;">
        <input type="hidden" name="week" value="{{ $weekStart->toDateString() }}">
        <label>From <input type="date" name="from" value="{{ $from->format('Y-m-d') }}"></label>
        <label>To <input type="date" name="to" value="{{ $to->format('Y-m-d') }}"></label>
        <button type="submit" class="btn btn-sm btn-primary">Apply</button>
    </form>

    <div class="dash-grid">
        <div class="dash-card">
            <div class="dash-card-title">Top Products</div>
            @if($topProducts->isEmpty())
                <div class="dash-empty">No data to display</div>
            @else
                <ul class="dash-list">
                    @foreach($topProducts as $p)
                        <li><span>{{ $p->name }}</span><strong>{{ $p->qty }}</strong></li>
                    @endforeach
                </ul>
            @endif
        </div>

        <div class="dash-card">
            <div class="dash-card-title">Hourly Sales</div>
            @if(!$hasHourlyData)
                <div class="dash-empty">No data to display</div>
            @else
                <div class="dash-hour-chart">
                    @foreach($hourlySales as $h => $amount)
                        <div class="dash-hour-col">
                            <div class="dash-bar-track dash-hour-track">
                                <div class="dash-bar" style="height: {{ $maxHourly > 0 ? max(2, round($amount / $maxHourly * 100)) : 2 }}%" title="{{ $h }}:00 &mdash; {{ number_format($amount, 2) }}"></div>
                            </div>
                            @if($h % 3 === 0)
                                <div class="dash-hour-label">{{ $h }}</div>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        <div class="dash-card dash-card-total">
            <div class="dash-card-title">Total Sales (Amount)</div>
            <div class="dash-big-number">{{ number_format($rangeTotal, 0) }}</div>
        </div>

        <div class="dash-card">
            <div class="dash-card-title">Top Product Groups</div>
            <div class="dash-card-sub">Top selling product groups in selected period</div>
            @if($topGroups->isEmpty())
                <div class="dash-empty">No data to display</div>
            @else
                <ul class="dash-list">
                    @foreach($topGroups as $g)
                        <li><span>{{ $g->name }}</span><strong>{{ number_format($g->total, 2) }}</strong></li>
                    @endforeach
                </ul>
            @endif
        </div>

        @if(!$shopId)
        <div class="dash-card">
            <div class="dash-card-title">Sales by Shop</div>
            <div class="dash-card-sub">All-time totals across all locations</div>
            @if($salesByShop->isEmpty())
                <div class="dash-empty">No data to display</div>
            @else
                <ul class="dash-list">
                    @foreach($salesByShop as $s)
                        <li><span>{{ $s->name }} <small class="text-muted">({{ $s->order_count }} orders)</small></span><strong>{{ number_format($s->total, 2) }}</strong></li>
                    @endforeach
                </ul>
            @endif
        </div>
        @endif
    </div>
</div>

@endsection
@section('script')
<script>
(function () {
    var toggleBtn = document.getElementById('dateRangeToggle');
    var form = document.getElementById('dateRangeForm');
    if (!toggleBtn || !form) return;
    toggleBtn.addEventListener('click', function () {
        form.style.display = (form.style.display === 'none' || !form.style.display) ? 'flex' : 'none';
    });
})();
</script>
@endsection
