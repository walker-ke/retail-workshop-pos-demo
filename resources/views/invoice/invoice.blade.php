@extends('layouts.app')
@push('styles')
<link rel="stylesheet" href="{{asset('assets/css/invoice.css')}}?v={{ filemtime(public_path('assets/css/invoice.css')) }}">
@endpush
@push('styles')
<link rel="stylesheet" href="{{asset('assets/css/pos_receipt.css')}}?v={{ filemtime(public_path('assets/css/pos_receipt.css')) }}">
@endpush

@section('content')

<form action="{{ url('/add-transaction')}}" method="POST" id="completeSaleForm" class="receipt-page">
@csrf

<div class="receipt-actions no-print">
    <a href="{{ route('pos') }}" class="btn btn-outline-danger btn-sm">Cancel</a>
</div>

@include('receipts.thermal', ['receipt' => $receipt])

<div class="payment-panel no-print">
    <label>Payment Method:</label>
    <div class="payMethod">
        @forelse($paymentTypes as $pt)
            @php
                $n = strtolower($pt->name);
                $icon = str_contains($n, 'cash') ? 'fa-money-bill-wave'
                    : (str_contains($n, 'card') ? 'fa-credit-card'
                    : (str_contains($n, 'check') ? 'fa-money-check'
                    : (str_contains($n, 'gcash') || str_contains($n, 'mobile') ? 'fa-mobile-screen-button'
                    : 'fa-wallet')));
            @endphp
            <span class="radio-item">
                <input type="radio" name="payment_method" value="{{ $pt->name }}" data-change-allowed="{{ $pt->change_allowed ? 1 : 0 }}" {{ $pt->name === ($selectedPaymentMethod ?? null) ? 'checked' : '' }} required>
                <div class="radio-tile">
                <i class="fa-solid {{ $icon }} icon"></i>
                <div>
                    <label>{{ $pt->name }}</label>
                </div>
                </div>
            </span>
        @empty
            <span class="text-danger">No payment types are set up yet. Ask an administrator to add one under Payment Types.</span>
        @endforelse
    </div>
    @if($combinedAvailable)
    <label class="d-block my-2"><input type="radio" name="payment_method" value="__combined" {{ $selectedPaymentMethod === '__combined' ? 'checked' : '' }}> Combined Payment (Cash + Paybill)</label>
    @endif
    <div id="combinedPaymentFields" hidden>
        <label for="cash_amount">Cash amount (KES)</label>
        <input type="number" class="form-control mb-2" id="cash_amount" name="cash_amount" min="0.01" step="0.01">
        <label for="paybill_amount">Paybill amount (KES)</label>
        <input type="number" class="form-control mb-2" id="paybill_amount" name="paybill_amount" min="0.01" step="0.01">
        <p id="combinedBalance" class="small" aria-live="polite"></p>
    </div>
    <!--End Payment Method-->

    <div class="tenderedAmount">
        <div class="form-group" style="width: 200px;">
            <label>Tendered Amount</label>
            <input type="number" class="form-control" name="paid_amount" id="paid_amount" min="0" step="0.01" required>
        </div>
        <div class="form-group" style="width: 200px; margin-left: 20px;">
            <label>Change</label>
            <input id="change" class="form-control" readonly></input>
        </div>
        <span id="cantExceed" style="visibility: hidden; color: red;">*Paid Amount cannot be lower than Total Amount</span>
    </div>

    <input type="hidden" name="customer_id" value="{{$customer->id}}">
    <input type="hidden" name="customer_name" value="{{ $customer->customer_name }}">

    <div class="receipt-actions no-print">
        <button type="button" class="btn btn-outline-secondary btn-sm" id="printReceiptBtn"><i class="fa fa-print"></i> Print Preview</button>
        <button type="submit" class="btn btn-primary btn-sm" id="save">Done</button>
    </div>
</div>

</form>

@endsection

@section('script')
   <script>
        $(document).ready(function(){
            function totalAmount() {
                return parseFloat($('#total_amount').text().replace(/,/g, '')) || 0;
            }

            function updatePaymentUI() {
                var checked = $('input[name="payment_method"]:checked');
                var combined = checked.val() === '__combined';
                $('#combinedPaymentFields').prop('hidden', !combined);
                $('#cash_amount, #paybill_amount').prop('disabled', !combined).prop('required', combined);
                var changeAllowed = !combined && (checked.length ? checked.data('change-allowed') == 1 : true);

                if (changeAllowed) {
                    $('.tenderedAmount').show();
                    $('#paid_amount').prop('required', true);
                } else {
                    $('.tenderedAmount').hide();
                    $('#paid_amount').prop('required', false).val(totalAmount());
                    $('#change').val(0);
                }
            }

            $('input[name="payment_method"]').on('change', updatePaymentUI);
            updatePaymentUI();

            $('#printReceiptBtn').on('click', function(){
                window.print();
            });

            function updateCombinedBalance() {
                var cash = parseFloat($('#cash_amount').val()) || 0;
                var paybill = parseFloat($('#paybill_amount').val()) || 0;
                var remaining = Math.round(totalAmount() * 100) - Math.round(cash * 100) - Math.round(paybill * 100);
                $('#combinedBalance').text(remaining === 0 ? 'Total covered' : (remaining > 0 ? 'Remaining: KES ' : 'Over total: KES ') + (Math.abs(remaining) / 100).toFixed(2));
            }
            $('#cash_amount, #paybill_amount').on('input', updateCombinedBalance);
            updateCombinedBalance();
            $('#completeSaleForm').on('submit', function(event){
                if ($('input[name="payment_method"]:checked').val() === '__combined') {
                    var cash = parseFloat($('#cash_amount').val()) || 0;
                    var paybill = parseFloat($('#paybill_amount').val()) || 0;
                    if (cash <= 0 || paybill <= 0 || Math.round(cash * 100) + Math.round(paybill * 100) !== Math.round(totalAmount() * 100)) {
                        event.preventDefault();
                        updateCombinedBalance();
                        $('#combinedBalance').text('Cash and Paybill must both be positive and add up to KES ' + totalAmount().toFixed(2));
                    }
                }
            });
            $('#save').on('click', function(){
                var checked = $('input[name="payment_method"]:checked');
                var changeAllowed = checked.length ? checked.data('change-allowed') == 1 : true;
                if (!changeAllowed) {
                    return true;
                }
                var paidAmount = parseFloat($('#paid_amount').val());

                if(paidAmount < totalAmount())
                {
                    $('#cantExceed').css('visibility', 'visible');
                    setTimeout(() => {
                        $('#cantExceed').css('visibility', 'hidden');
                    }, 5000);
                    return false;
                }
            })
            $('#paid_amount, #total_amount').on('input keyup', function(){
                var x = Number($('#paid_amount').val());
                var y = totalAmount();
                $('#change').val(Math.max(0, x - y).toFixed(2));
            })
        })
   </script>
@endsection
