@extends('layouts.app')
@section('content')
@include('layouts.includes.sidebar')
@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/hubs.css') }}?v={{ filemtime(public_path('assets/css/hubs.css')) }}">
<link rel="stylesheet" href="{{ asset('assets/css/workshop.css') }}?v={{ filemtime(public_path('assets/css/workshop.css')) }}">
<link rel="stylesheet" href="{{ asset('assets/css/hub-workspace.css') }}?v={{ filemtime(public_path('assets/css/hub-workspace.css')) }}">
@endpush
<section class="workshop-page hub-workspace hub-page">
@include('hubs.workspace-header',['hubView'=>'overview'])
@if(session('success'))<div class="alert alert-success" role="status">{{ session('success') }}</div>@endif
@if($errors->any())<div class="alert alert-danger" role="alert">@foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>@endif

@if($hub)
<div class="hub-heading"><h2>{{ $hub->name }}</h2><div class="hub-management-bar"><details><summary>＋ Add hub</summary><form method="POST" action="{{ route('hubs.create') }}">@csrf<label>Hub name<input class="form-control" name="name" required maxlength="100" placeholder="NEW HUB"></label><button class="btn btn-primary">Add hub</button></form></details><details><summary>Manage hub</summary>
<form method="POST" action="{{ route('hubs.rename',$hubId) }}">@csrf<label>Hub name<input class="form-control" name="name" value="{{ $hub->name }}" maxlength="100" required></label><button class="btn btn-outline-secondary">Rename</button></form>
@if($hub->is_active)<form method="POST" action="{{ route('hubs.archive',$hubId) }}">@csrf<p class="text-muted">Remove only when empty and all deliveries are finished. History is kept.</p><button class="btn btn-outline-danger">Remove hub from use</button></form>@endif
</details></div></div>
@if(!$hub->is_active)<div class="alert alert-secondary">This hub is removed from use. Its inventory and delivery history remain available.</div>@endif
<div class="workshop-metrics"><article><span>Finished goods</span><strong>{{ number_format($finishedUnits) }} <em>units</em></strong><small>Available in this hub</small></article><article><span>Upper stock</span><strong>{{ number_format($componentTotals['uppers']??0) }} <em>pairs</em></strong><small>Ready for assembly</small></article><article><span>Sole stock</span><strong>{{ number_format($componentTotals['soles']??0) }} <em>pairs</em></strong><small>Components in this hub</small></article><article><span>Pending deliveries</span><strong>{{ number_format($pendingShop+$pendingTransfers) }}</strong><small>Shops {{ $pendingShop }} · Hub transfers {{ $pendingTransfers }}</small></article></div>
<div class="hub-overview-grid"><div class="hub-overview-inventory">
<section class="hub-panel"><h3>Stock in this hub</h3><form method="GET" class="hub-toolbar"><input type="hidden" name="hub_id" value="{{ $hubId }}"><label>Find stock<input class="form-control" type="search" name="q" value="{{ request('q') }}" maxlength="100"></label><button class="btn btn-outline-secondary">Search</button></form>
<div class="workshop-table-wrap" tabindex="0" role="region" aria-label="Finished stock in this hub"><table class="table"><thead><tr><th scope="col">Product / size</th><th scope="col">Available units</th></tr></thead><tbody>@forelse($inventory as $item)<tr><td>{{ $item->product_name }}</td><td class="workshop-amount">{{ number_format($item->quantity) }}</td></tr>@empty<tr><td colspan="2" class="workshop-empty">No finished stock recorded here yet.</td></tr>@endforelse</tbody></table></div>
{{ $inventory->links('pagination::bootstrap-5') }}</section>
</div><div class="hub-overview-activity"><section class="hub-panel"><div class="workshop-panel-title"><h3>Shop deliveries</h3><a href="{{ route('hubs.transfer-page',['hub_id'=>$hubId]) }}">Hub to hub transfer →</a></div><p class="text-muted">Sent stock, cashier confirmations and discrepancies.</p>
@forelse($deliveries as $delivery)@include('hubs.delivery',['delivery'=>$delivery])@empty<p class="text-muted">No deliveries yet.</p>@endforelse
{{ $deliveries->links('pagination::bootstrap-5') }}</section>
<details class="hub-panel"><summary>Stock history</summary>@forelse($events as $event)<div class="hub-inventory-row"><span>{{ $event->product_name }}<small class="d-block text-muted">{{ $event->action }} · {{ $event->actor_name }} · {{ $event->note }}</small></span><strong>{{ $event->delta>0?'+':'' }}{{ $event->delta }}<small class="d-block">Balance {{ $event->balance }}</small></strong></div>@empty<p>No stock changes yet.</p>@endforelse<p class="text-muted">Latest 30 changes.</p></details></div></div>
@else
<div class="hub-management-bar"><details><summary>＋ Add hub</summary><form method="POST" action="{{ route('hubs.create') }}">@csrf<label>Hub name<input class="form-control" name="name" required maxlength="100" placeholder="NEW HUB"></label><button class="btn btn-primary">Add hub</button></form></details></div>
@endif
</section>
@push('libraries')
<script src="{{ asset('assets/js/hub-deliveries.js') }}?v={{ filemtime(public_path('assets/js/hub-deliveries.js')) }}"></script>
@endpush
@endsection
