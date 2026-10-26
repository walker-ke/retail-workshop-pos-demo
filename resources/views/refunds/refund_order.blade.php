@extends('layouts.app')

@section('content')
@include('layouts.includes.sidebar')
<section class="management-page">
@push('styles')
<link rel="stylesheet" href="{{asset('assets/css/list_customer.css')}}?v={{ filemtime(public_path('assets/css/list_customer.css')) }}">
@endpush
<div class="head">
    <p style="position: relative; bottom: 20px;">Refund Receipt No: {{$order->transaction_code}}</p>
</div>
<div class="all">
    <div class="card">
      <div class="card-body">
        <p><b>Customer:</b> {{$order->customer_name}} &nbsp; | &nbsp; <b>Date:</b> {{$order->order_date}} &nbsp; | &nbsp; <b>Order Total:</b> <span class="currency">KES</span> {{$order->total}}</p>

        @if($errors->any())
            <div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>
        @endif
        @if(in_array($order->order_status, ['Refunded', 'Partially Refunded']))
            <div class="alert alert-warning">This receipt has previous refunds. Only the remaining quantities can be refunded.</div>
        @endif

        <form action="{{ url('/refund-order/'.$order->id) }}" method="POST">
            @csrf
            <table class="table table-striped table-bordered table-sm" cellspacing="0" width="100%">
              <thead>
                <tr>
                  <th>Product</th>
                  <th>Code</th>
                  <th>Sold Qty</th>
                  <th>Already Refunded</th>
                  <th>Unit Price</th>
                  <th style="width: 140px;">Refund Qty</th>
                </tr>
              </thead>
              <tbody>
                    @foreach($orderDetails as $item)
                        @php $already = $alreadyRefunded[$item->product_id] ?? 0; @endphp
                        <tr>
                            <td>{{$item->product_name}}</td>
                            <td>{{$item->product_code}}</td>
                            <td>{{$item->quantity}}</td>
                            <td>{{$already}}</td>
                            <td><span class="currency">KES</span> {{$item->unit_price}}</td>
                            <td>
                                <input type="number" min="0" max="{{ $item->quantity - $already }}" value="0" class="form-control" name="refund_qty[{{$item->product_id}}]" {{ ($item->quantity - $already) <= 0 ? 'disabled' : '' }}>
                            </td>
                        </tr>
                    @endforeach
              </tbody>
            </table>
            <div class="form-group mb-4">
                <label>Reason for Refund</label>
                <textarea class="form-control" name="reason" rows="3" placeholder="Optional reason"></textarea>
            </div>
            <a href="{{ route('refunds') }}" class="btn btn-secondary">Cancel</a>
            <button type="submit" class="btn btn-danger"><i class="fa-solid fa-rotate-left"></i> Process Refund</button>
        </form>
      </div>
    </div>
</div>
</section>
@endsection
