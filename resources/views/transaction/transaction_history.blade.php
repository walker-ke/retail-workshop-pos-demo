@extends('layouts.app')

@section('content')
@include('layouts.includes.sidebar')
@push('styles')
<link rel="stylesheet" href="{{asset('assets/css/transaction.css')}}?v={{ filemtime(public_path('assets/css/transaction.css')) }}">
@endpush
<!--DATA TABLE START -->
<section class="transaction-page">
    <h1>Transaction History</h1>
    <p class="transaction-hint">Search by receipt number, customer or cashier. Scroll the table sideways to see all sale details.</p>
    <div class="card">
      <div class="card-body">
        @include('layouts.includes.history-search')
        <div class="transaction-table-scroll" role="region" aria-label="Sales history table, scroll sideways for more details" tabindex="0">
        <table id="dtBasicExample" class="table  table-sm" cellspacing="0" width="100%">
          <thead class="bg-gradient bg-light ">
            <tr>
              <th class="th-sm" style="width: 30px;">ID</th>
              <th class="th-sm">Shop</th>
              <th class="th-sm">Receipt No</th>
              <th class="th-sm">Served by</th>
              <th class="th-sm">Customer Name</th>
              <th class="th-sm">Customer ID</th>
              <th class="th-sm">Ordered Date/Time</th>
              <th class="th-sm">Total Products</th>
              <th class="th-sm">Payment Method</th>
              <th class="th-sm">Paid Amount</th>
              <th class="th-sm">Total Amount</th>
              <th class="th-sm">Order Status</th>
              <th class="th-sm" style="width: 150px;">Receipt</th>
            </tr>
          </thead>
          <tbody>
              @foreach($orders as $history)
                <tr>
                   <td>{{$history->id}}</td>
                   <td>{{$history->shop_name}}</td>
                   <td>{{$history->transaction_code}}</td>
                   <td>{{ $history->cashier_name ?: 'Not recorded' }}</td>
                   <td>{{$history->customer_name}}</td>
                   <td>{{$history->customer_id}}</td>
                   <td>{{$history->order_date}}</td>
                   <td>{{$history->total_products}}</td>
                   <td>{{$history->payment_method}}</td>
                   <td><span class="currency">KES</span> {{$history->paid_amount}}</td>
                   <td><span class="currency">KES</span> {{$history->total}}</td>
                   <td><span class="badge badge-success">{{$history->order_status}}</span></td>

                   <td>
                        <a href="{{ URL::to('/view-receipt/'.$history->id) }}" class="btn btn-primary"><i class="fa-solid fa-eye"></i> View Receipt</a>
                   </td>
                </tr>
              @endforeach
          </tbody>
        </table>
        </div>
        {{ $orders->links('pagination::bootstrap-5') }}
      </div>

    </div>
</section>
@endsection
