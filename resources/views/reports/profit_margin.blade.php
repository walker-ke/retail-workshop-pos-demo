@extends('layouts.app')
@section('content')
@include('layouts.includes.sidebar')
@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/payment-reports.css') }}?v={{ filemtime(public_path('assets/css/payment-reports.css')) }}">
@endpush
@php
    $colours = ['#7b1e3a', '#247458', '#37669a', '#a66b18', '#765395', '#567377'];
    $methodColours = [];
    foreach (array_keys($paymentTotals) as $i => $method) $methodColours[$method] = $colours[$i % count($colours)];
@endphp
<section class="payment-report">
    <header class="pr-header">
        <div><p class="pr-eyebrow">Reports / Collections</p><h1>{{ $periodTitle }} payment report</h1><p class="pr-muted">{{ $periodLabel }} · Nairobi time</p></div>
        <div class="pr-actions no-print"><a href="{{ request()->fullUrl() }}" class="btn btn-outline-primary">Refresh</a><button type="button" class="btn btn-primary" id="printPaymentReport">Print report</button></div>
    </header>
    <div class="pr-panel pr-filters no-print">
        <form method="get" class="pr-date-form">
            <label for="reportPeriod">Period<select id="reportPeriod" name="period" class="form-select">@foreach(['daily'=>'Daily','weekly'=>'Weekly','monthly'=>'Monthly'] as $value=>$label)<option value="{{ $value }}" @selected($period === $value)>{{ $label }}</option>@endforeach</select></label>
            <label for="reportDate">Report date<input id="reportDate" type="date" name="date" value="{{ $date }}" class="form-control" required></label>

            <a class="pr-date-link" href="{{ route('report_profit', ['date'=>$date, 'period'=>'weekly']) }}">Weekly</a>
            <a class="pr-date-link" href="{{ route('report_profit', ['date'=>$date, 'period'=>'monthly']) }}">Monthly</a>
        </form>
        <div class="pr-shop">@include('partials.shop_picker')</div>
    </div>
    @error('date')<p class="text-danger">{{ $message }}</p>@enderror
    <div class="pr-metrics">
        <article class="pr-panel pr-metric pr-primary"><span>Total collected</span><strong>KES {{ number_format($totalCollected / 100, 2) }}</strong><small>Before refunds · change excluded</small></article>
        <article class="pr-panel pr-metric"><span>Sales recorded</span><strong>{{ number_format($transactionCount) }}</strong><small>All orders for this period</small></article>
        <article class="pr-panel pr-metric pr-top-products">
            <span>Top 5 moving products</span>
            <small>By quantity sold · selected period and shop</small>
            <ol class="pr-product-ranking">
                @forelse($topProducts as $product)
                    <li><span>{{ $product->product_name }}</span><b>{{ number_format($product->qty_sold) }} sold</b></li>
                @empty
                    <li class="pr-muted">No products sold on this period.</li>
                @endforelse
            </ol>
        </article>
        <article class="pr-panel pr-metric"><span>Pending sales</span><strong>{{ number_format($pendingCount) }}</strong><small>Unpaid balances may remain</small></article>
    </div>
    <p class="pr-note">Collections on sales recorded in this period. Split payments are separated. Refunds and later credit settlements are not deducted or allocated here.</p>
    <div class="pr-charts">
        <section class="pr-panel">
            <h2>Method Split</h2><p class="pr-muted">Collections by payment method · KES</p>
            <div class="pr-methods">
            @forelse($paymentTotals as $method => $amount)
                <div class="pr-method"><span><i style="background:{{ $methodColours[$method] }}"></i>{{ $method }}</span><strong>@if($amount > 0)KSh {{ number_format($amount/100,2) }}@else<span aria-label="No collections">—</span>@endif</strong></div>
            @empty<p class="pr-muted">No payments collected.</p>@endforelse
                <div class="pr-method pr-method-total"><span>Total collected</span><strong>KSh {{ number_format($totalCollected / 100, 2) }}</strong></div>
            </div>
        </section>
    </div>
    <section class="pr-panel pr-transactions">
        <div class="pr-section-header"><div><h2>Payment transactions</h2><p class="pr-muted">10 sales per page · summary figures above cover the full period</p></div>
            <form method="get" class="pr-search no-print"><input type="hidden" name="date" value="{{ $date }}"><input type="hidden" name="period" value="{{ $period }}"><label class="visually-hidden" for="paymentSearch">Search receipt or customer</label><input class="form-control" id="paymentSearch" name="q" value="{{ $search }}" maxlength="100" placeholder="Receipt or customer"><button class="btn btn-outline-primary">Search</button>@if($search !== '')<a href="{{ route('report_profit',['date'=>$date, 'period'=>$period]) }}">Clear</a>@endif</form>
        </div>
        @error('q')<p class="text-danger">{{ $message }}</p>@enderror
        <div class="pr-table-wrap"><table class="pr-table"><thead><tr><th>Receipt</th><th>Time</th><th>Customer</th><th>Payment method</th><th class="pr-number">Collected (KES)</th><th>Status</th></tr></thead><tbody>
        @forelse($transactions as $transaction)
            @php $parts = \App\Support\DailyPayments::totals([$transaction]); @endphp
            <tr><td><a href="{{ route('view_receipt',$transaction->id) }}">{{ $transaction->transaction_code }}</a></td><td class="pr-nowrap">{{ \Carbon\Carbon::parse($transaction->created_at,config('app.timezone'))->timezone('Africa/Nairobi')->format('H:i') }}</td><td>{{ $transaction->customer_name ?: 'Walk-in Customer' }}</td><td>@forelse($parts as $method=>$amount)<div class="pr-payment-line">{{ $method }}@if(count($parts)>1)<small> KES {{ number_format($amount/100,2) }}</small>@endif</div>@empty{{ $transaction->payment_method }}@endforelse</td><td class="pr-number"><strong>{{ number_format(array_sum($parts)/100,2) }}</strong></td><td><span class="pr-status {{ $transaction->order_status === 'Paid' ? 'pr-paid' : '' }}">{{ $transaction->order_status }}</span></td></tr>
        @empty<tr><td colspan="6" class="pr-empty">{{ $search !== '' ? 'No sales match your search.' : 'No sales recorded on this period.' }}</td></tr>@endforelse
        </tbody></table></div>
        <div class="pr-pagination no-print"><span>{{ $transactions->total() }} matching sales</span><div>@if($transactions->previousPageUrl())<a class="btn btn-outline-primary" href="{{ $transactions->previousPageUrl() }}">Previous</a>@endif<span>Page {{ $transactions->currentPage() }} of {{ $transactions->lastPage() }}</span>@if($transactions->hasMorePages())<a class="btn btn-outline-primary" href="{{ $transactions->nextPageUrl() }}">Next</a>@endif</div></div>
    </section>
    <details class="pr-panel pr-profit"><summary>Product profit details <span>KES {{ number_format($totalProfit,2) }} profit</span></summary>
        <div class="pr-profit-stats"><span>Revenue <strong>KES {{ number_format($totalRevenue,2) }}</strong></span><span>Cost <strong>KES {{ number_format($totalCost,2) }}</strong></span><span>Profit <strong>KES {{ number_format($totalProfit,2) }}</strong></span></div>
        <p class="pr-muted">Uses each product's current cost price, before refunds. Figures cover the selected period.</p><div class="pr-table-wrap">
        <table id="dtBasicExample" class="table table-striped table-bordered table-sm" cellspacing="0" width="100%">
          <thead>
            <tr>
              <th>Product</th>
              <th>Code</th>
              <th>Brand</th>
              <th>Qty Sold</th>
              <th>Revenue</th>
              <th>Cost</th>
              <th>Profit</th>
              <th>Margin %</th>
            </tr>
          </thead>
          <tbody>
                @foreach($rows as $row)
                    <tr>
                        <td>{{$row->product_name}}</td>
                        <td>{{$row->product_code}}</td>
                        <td>{{$row->brand}}</td>
                        <td>{{$row->qty_sold}}</td>
                        <td><span class="currency">KES</span> {{ number_format($row->revenue, 2) }}</td>
                        <td><span class="currency">KES</span> {{ number_format($row->cost, 2) }}</td>
                        <td class="{{ $row->profit >= 0 ? 'text-success' : 'text-danger' }}"><span class="currency">KES</span> {{ number_format($row->profit, 2) }}</td>
                        <td>{{ $row->revenue > 0 ? number_format(($row->profit / $row->revenue) * 100, 1) : 0 }}%</td>
                    </tr>
                @endforeach
          </tbody>
        </table>
        </div>
    </details>
</section>
@endsection
@section('script')
<script>
document.getElementById('printPaymentReport').addEventListener('click', () => window.print());
for (const field of document.querySelectorAll('.pr-date-form input, .pr-date-form select')) {
    field.addEventListener('change', () => {
        if (field.form.checkValidity()) field.form.requestSubmit();
    });
}
</script>
@endsection
