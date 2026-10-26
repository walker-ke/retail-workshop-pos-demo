<article class="sales-receipt" aria-label="{{ $receipt['is_preview'] ? 'Sale preview' : 'Sales receipt' }}">
    <header class="sales-receipt-header">
        <h1>{{ $receipt['business']['name'] }}</h1>
        @if(!empty($receipt['business']['pin']))<p>PIN: {{ $receipt['business']['pin'] }}</p>@endif
        @if(!empty($receipt['business']['phone']))<p>TEL: {{ $receipt['business']['phone'] }}</p>@endif
        @if(!empty($receipt['business']['address']))<p>{{ $receipt['business']['address'] }}</p>@endif
        @if(!empty($receipt['business']['paybill']) || !empty($receipt['business']['account_number']))
            <div class="sales-receipt-payment-details">
                @if(!empty($receipt['business']['paybill']))<p>PAYBILL {{ $receipt['business']['paybill'] }}</p>@endif
                @if(!empty($receipt['business']['account_number']))<p>ACC NO: {{ $receipt['business']['account_number'] }}</p>@endif
            </div>
        @endif
    </header>
    <h2 class="sales-receipt-title">{{ $receipt['title'] }}</h2>
    @if($receipt['is_preview'])<p class="sales-receipt-preview">PREVIEW &mdash; not a completed sale</p>@endif
    <div class="sales-receipt-customer">Name: <strong>{{ $receipt['customer'] }}</strong></div>
    <section class="sales-receipt-meta" aria-label="Sale details">
        <div class="sales-receipt-line">
            <span>@if(!empty($receipt['business']['till_number']))TILL: {{ $receipt['business']['till_number'] }}@endif</span>
            <strong>{{ $receipt['is_preview'] ? 'PREVIEW' : 'RECEIPT' }}</strong>
        </div>
        <p>{{ $receipt['date'] }}</p>
        @if(!empty($receipt['number']))
            <p class="sales-receipt-number">Receipt No: <strong>{{ $receipt['number'] }}</strong></p>
        @elseif($receipt['is_preview'])
            <p>Receipt No: assigned when you complete the sale</p>
        @endif
    </section>
    <table class="sales-receipt-items">
        <colgroup><col class="sales-receipt-code-col"><col><col><col></colgroup>
        <thead><tr><th scope="col">PRODUCT</th><th scope="col">QTY</th><th scope="col">PRICE</th><th scope="col">AMOUNT</th></tr></thead>
        @foreach($receipt['items'] as $item)
            <tbody>
                <tr class="sales-receipt-item-name"><td colspan="4">{{ $item['name'] }}</td></tr>
                <tr class="sales-receipt-item-values">
                    <td>{{ $item['code'] }}</td>
                    <td>{{ rtrim(rtrim(number_format($item['quantity'], 3, '.', ''), '0'), '.') }}</td>
                    <td>{{ number_format($item['price'], 2) }}</td>
                    <td>{{ number_format($item['total'], 2) }}</td>
                </tr>
            </tbody>
        @endforeach
    </table>
    <section class="sales-receipt-totals" aria-label="Payment totals">
        <div class="sales-receipt-line sales-receipt-grand"><strong>TOTAL (KES)</strong><strong id="total_amount">{{ number_format($receipt['total'], 2) }}</strong></div>
        @if(!empty($receipt['payments']))
            @foreach($receipt['payments'] as $payment)
                <div class="sales-receipt-line"><span>{{ strtoupper($payment['method']) }}:</span><span>{{ number_format($payment['amount'], 2) }}</span></div>
            @endforeach
        @elseif($receipt['paid'] !== null)<div class="sales-receipt-line"><span>{{ strtoupper($receipt['payment_method']) }}:</span><span>{{ number_format($receipt['paid'], 2) }}</span></div>@endif
        @if($receipt['change'] !== null)<div class="sales-receipt-line"><span>CHANGE</span><span>{{ number_format($receipt['change'], 2) }}</span></div>@endif
        <div class="sales-receipt-line"><span>TOTAL ITEMS:</span><span>{{ rtrim(rtrim(number_format($receipt['total_items'], 3, '.', ''), '0'), '.') }}</span></div>
    </section>
    <footer class="sales-receipt-footer">
        <p class="sales-receipt-served">Served by: <strong>{{ $receipt['served_by'] }}</strong></p>
        @if(!empty($receipt['business']['footer']))<p class="sales-receipt-thanks">{{ $receipt['business']['footer'] }}</p>@endif
    </footer>
</article>
