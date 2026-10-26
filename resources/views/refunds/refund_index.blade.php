@extends('layouts.app')

@section('content')
@include('layouts.includes.sidebar')
<section class="management-page">
@push('styles')
<link rel="stylesheet" href="{{asset('assets/css/list_customer.css')}}?v={{ filemtime(public_path('assets/css/list_customer.css')) }}">
@endpush
<div class="head">
    <p style="position: relative; bottom: 20px;">Refunds</p>
</div>
<div class="all">
    <div class="card">
      <div class="card-header bg-white"><b>Orders &mdash; Select One to Refund</b></div>
      <div class="card-body">
        @include('layouts.includes.history-search')
        <table id="dtBasicExample" class="table table-striped table-bordered table-sm" cellspacing="0" width="100%">
          <thead>
            <tr>
              <th>Receipt No</th>
              <th>Shop</th>
              <th>Customer</th>
              <th>Date</th>
              <th>Total</th>
              <th>Status</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
                @foreach($orders as $order)
                    <tr>
                        <td>{{$order->transaction_code}}</td>
                        <td>{{$order->shop_name}}</td>
                        <td>{{$order->customer_name}}</td>
                        <td>{{$order->order_date}}</td>
                        <td><span class="currency">KES</span> {{$order->total}}</td>
                        <td>
                            @if($order->order_status == 'Refunded')
                                <span class="badge badge-danger">Refunded</span>
                            @else
                                <span class="badge badge-success">{{$order->order_status}}</span>
                            @endif
                        </td>
                        <td>
                            <a href="{{ route('refund_order', $order->id) }}" class="btn btn-primary btn-sm"><i class="fa-solid fa-rotate-left"></i> Refund</a>
                        </td>
                    </tr>
                @endforeach
          </tbody>
        </table>
        {{ $orders->links('pagination::bootstrap-5') }}
      </div>
    </div>

    <div class="card mt-4">
      <div class="card-header bg-white"><b>Refund History</b></div>
      <div class="card-body">
        <table class="table table-striped table-bordered table-sm" cellspacing="0" width="100%">
          <thead>
            <tr>
              <th>Refund ID</th>
              <th>Shop</th>
              <th>Receipt No</th>
              <th>Customer</th>
              <th>Reason</th>
              <th>Total Refunded</th>
              <th>Date</th>
            </tr>
          </thead>
          <tbody>
                @forelse($refunds as $refund)
                    <tr>
                        <td>{{$refund->id}}</td>
                        <td>{{$refund->shop_name}}</td>
                        <td>{{$refund->transaction_code}}</td>
                        <td>{{$refund->customer_name}}</td>
                        <td>{{$refund->reason}}</td>
                        <td><span class="currency">KES</span> {{ number_format($refund->total_refunded, 2) }}</td>
                        <td>{{ \Carbon\Carbon::parse($refund->created_at)->format('d/m/Y H:i') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center">No refunds processed yet.</td></tr>
                @endforelse
          </tbody>
        </table>
        {{ $refunds->links('pagination::bootstrap-5') }}
      </div>
    </div>
</div>
</section>
@endsection
