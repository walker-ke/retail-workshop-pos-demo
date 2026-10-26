@extends('layouts.app')

@section('content')
@include('layouts.includes.sidebar')
<section class="management-page">
@push('styles')
<link rel="stylesheet" href="{{asset('assets/css/list_customer.css')}}?v={{ filemtime(public_path('assets/css/list_customer.css')) }}">
@endpush
<div class="head">
    <p style="position: relative; bottom: 20px;">Stock History</p>
</div>
<div class="all">
    <div class="card">
      <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <a href="{{ route('stock_index') }}" class="btn btn-outline-secondary"><i class="fa-solid fa-arrow-left"></i> Back to Stock</a>
        @if($filterProduct)
            <span>Filtered to: <strong>{{ $filterProduct->product_name }}</strong> <a href="{{ route('stock_history') }}" class="btn btn-sm btn-outline-danger ms-2">Clear filter</a></span>
        @endif
      </div>
      <div class="card-body">
        <table class="table table-striped table-bordered table-sm" cellspacing="0" width="100%">
          <thead>
            <tr>
              <th class="th-sm">Date</th>
              <th class="th-sm">Shop</th>
              <th class="th-sm">Product</th>
              <th class="th-sm">Code</th>
              <th class="th-sm">Qty Added</th>
              @if(auth()->user()->hasAccess('view_cost_prices'))
              <th class="th-sm">Cost Price</th>
              @endif
              <th class="th-sm">Reference</th>
              <th class="th-sm">Notes</th>
            </tr>
          </thead>
          <tbody>
                @forelse($stockIns as $s)
                    <tr>
                        <td>{{ \Carbon\Carbon::parse($s->created_at)->format('d/m/Y H:i') }}</td>
                        <td>{{$s->shop_name}}</td>
                        <td>{{$s->product_name}}</td>
                        <td>{{$s->product_code}}</td>
                        <td><span class="badge {{ $s->quantity < 0 ? 'badge-danger' : 'badge-success' }}">{{ $s->quantity > 0 ? '+' : '' }}{{$s->quantity}}</span></td>
                        @if(auth()->user()->hasAccess('view_cost_prices'))
                        <td><span class="currency">KES</span> {{$s->cost_price}}</td>
                        @endif
                        <td>{{$s->reference}}</td>
                        <td>{{$s->notes}}</td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="text-center">No stock intake recorded yet.</td></tr>
                @endforelse
          </tbody>
        </table>
      </div>
    </div>
</div>
</section>
@endsection
