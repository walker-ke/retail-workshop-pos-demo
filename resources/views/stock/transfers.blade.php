@extends('layouts.app')
@section('content')
@include('layouts.includes.sidebar')
@push('styles')
<style>
.transfer-page { max-width:1200px; margin:auto; padding:70px 16px 24px; }
.transfer-page summary { min-height:44px; cursor:pointer; }
.transfer-page h1 { font-size:1.6rem; }
.transfer-toolbar,.transfer-actions { display:flex; flex-wrap:wrap; gap:10px; align-items:end; }
.transfer-page > .transfer-toolbar:first-child > div { flex-basis:450px; }
.transfer-toolbar > a { flex:0 0 auto; }
.transfer-toolbar > * { flex:1 1 160px; min-width:0; }
.transfer-card { background:white; border:1px solid #ddd; border-radius:10px; padding:18px; margin:16px 0; overflow-wrap:anywhere; }
.transfer-actions form { display:flex; flex-wrap:wrap; gap:8px; align-items:end; }
.transfer-actions input[type=number] { width:100px; }
.transfer-page input,.transfer-page select,.transfer-page button,.transfer-page .btn { min-height:44px; }
.transfer-stock { display:grid; grid-template-columns:repeat(auto-fit,minmax(min(100%,220px),1fr)); gap:8px; margin:10px 0; align-items:start; }
.transfer-shop { padding:10px 12px; border:1px solid #ddd; border-radius:8px; }
.transfer-shop strong,.transfer-available { display:block; }
.transfer-available { font-size:.85rem; color:#62656b; }
details.transfer-shop { padding:0; }
.transfer-shop summary { padding:10px 12px; list-style:none; position:relative; padding-right:112px; }
.transfer-shop summary::-webkit-details-marker { display:none; }
.transfer-shop summary:hover { background:#faf2f5; border-radius:8px; }
.transfer-shop summary:focus-visible { outline:2px solid #7a1f36; outline-offset:2px; border-radius:8px; }
.transfer-request-label { position:absolute; right:12px; top:50%; transform:translateY(-50%); color:#7a1f36; font-size:.8rem; font-weight:600; }
.transfer-shop[open] .transfer-request-label { display:none; }
.transfer-shop form { display:grid; gap:8px; padding:0 12px 12px; }
.transfer-pagination[hidden] { display:none; }
.transfer-pagination { gap:8px; align-items:center; font-size:.8rem; }
.transfer-page .transfer-pagination button { min-height:36px; padding:5px 10px; font-size:.8rem; }
.transfer-status { background:#f4e8ed; color:#7a1f36; padding:5px 10px; border-radius:6px; display:inline-block; }
.transfer-record { padding:12px 14px; margin:8px 0; border-radius:8px; }
.transfer-record .transfer-status { float:right; padding:2px 7px; font-size:.75rem; margin:0 0 4px 10px; }
.transfer-record h3.h5 { font-size:.95rem; line-height:1.4; margin:0 0 5px !important; }
.transfer-record > p { font-size:.82rem; line-height:1.5; margin:0; color:#62656b; }
.transfer-record > p strong { font-weight:500; }
.transfer-record .transfer-actions:has(form) { margin-top:10px; }
.transfer-record::after { content:""; display:block; clear:both; }
.transfer-audit { margin:14px 0 0; padding-left:20px; font-size:.9rem; color:#555; }
@media(min-width:901px) { .transfer-page { margin-left:270px; } }
@media(max-width:500px) { .transfer-actions form,.transfer-actions .btn { width:100%; } }
</style>
@endpush
<section class="transfer-page">
<div class="transfer-toolbar"><div><h1>Shop Stock Requests</h1><p class="text-muted">Request an item. The other shop confirms it, then you confirm receipt.</p></div><a class="btn btn-outline-secondary" href="{{ route('transfers.index') }}">Refresh requests</a></div>
@if(session('success'))<div class="alert alert-success" role="status">{{ session('success') }}</div>@endif
@if($errors->any())<div class="alert alert-danger" role="alert">@foreach($errors->all() as $message)<div>{{ $message }}</div>@endforeach</div>@endif
@if(auth()->user()->isTopAdmin())
<form action="{{ route('switch_shop') }}" method="POST" class="transfer-toolbar">@csrf
<label>Receiving shop<select class="form-select" name="shop_id" onchange="this.form.requestSubmit()"><option value="">All shops (view only)</option>@foreach($shops as $shop)<option value="{{ $shop->id }}" @selected($shopId === $shop->id)>{{ $shop->name }}</option>@endforeach</select></label><noscript><button class="btn btn-primary">Choose shop</button></noscript></form>
@else<p><strong>Your shop:</strong> {{ auth()->user()->shop?->name ?? 'No shop assigned' }}</p>@endif
@if(!$shopId)<div class="alert alert-info">Select a receiving shop before making a request. The owner can still oversee existing requests below.</div>@endif

<section class="transfer-card" aria-labelledby="availability-heading">
<h2 class="h5" id="availability-heading">Find an item &amp; check shop availability</h2>
<label for="transferSearch">Search products</label>
<input id="transferSearch" class="form-control" type="search" value="{{ request('q') }}" placeholder="Search products by name" maxlength="100" autocomplete="off">
<p class="text-muted mt-2">Type to see matching items and available quantities at every shop. You can request an item only when your shop has 5 or fewer.</p>
<p id="transferSearchStatus" role="status" aria-live="polite">Loading products…</p>
<div id="transferSearchResults"></div>
<div class="transfer-actions transfer-pagination" id="transferPagination" hidden><button type="button" class="btn btn-outline-secondary" id="transferPrevious">Previous</button><span id="transferSearchPage"></span><button type="button" class="btn btn-outline-secondary" id="transferNext">Next</button></div>
<noscript>Enable JavaScript to search item availability.</noscript>
<script type="application/json" id="transferCatalog" data-request-url="{{ route('transfers.store') }}">{!! json_encode($catalog, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
</section>
@isset($hubDeliveries)
@push('libraries')<script src="{{ asset('assets/js/hub-deliveries.js') }}?v={{ filemtime(public_path('assets/js/hub-deliveries.js')) }}"></script>@endpush
@push('styles')<link rel="stylesheet" href="{{ asset('assets/css/hubs.css') }}?v={{ filemtime(public_path('assets/css/hubs.css')) }}">@endpush
<details class="transfer-card" @if($hubDeliveries->contains(fn($delivery)=>$delivery->status==='dispatched')) open @endif><summary class="fw-semibold">Deliveries from hubs · {{ $hubDeliveries->total() }}</summary><p class="text-muted">Count what arrived before confirming. Only your confirmed quantity becomes shop stock.</p>
@forelse($hubDeliveries as $delivery)@include('hubs.delivery',['delivery'=>$delivery])@empty<p class="text-muted">No hub deliveries yet.</p>@endforelse
{{ $hubDeliveries->links('pagination::bootstrap-5') }}</details>
@endisset
@push('libraries')
<script src="{{ asset('assets/js/product-search.js') }}?v={{ filemtime(public_path('assets/js/product-search.js')) }}"></script>
<script src="{{ asset('assets/js/stock-request-search.js') }}?v={{ filemtime(public_path('assets/js/stock-request-search.js')) }}"></script>
@endpush

<h2 class="h4 mt-4">Requests &amp; transfers</h2>
<form method="GET" class="transfer-toolbar"><label>Status<select name="status" class="form-select"><option value="">All statuses</option>@foreach(['requested','dispatched','received','declined','cancelled'] as $status)<option value="{{ $status }}" @selected(request('status') === $status)>{{ $status === 'dispatched' ? 'Awaiting receipt' : ($status === 'accepted' ? 'Awaiting confirmation' : ucfirst($status)) }}</option>@endforeach</select></label><button class="btn btn-outline-secondary">Filter</button></form>
@forelse($transfers as $transfer)
@php($source = auth()->user()->isTopAdmin() || $shopId === (int)$transfer->from_shop_id)
@php($destination = auth()->user()->isTopAdmin() || $shopId === (int)$transfer->to_shop_id)
<article class="transfer-card transfer-record"><span class="transfer-status">{{ $transfer->status === 'dispatched' ? 'Awaiting receipt' : ($transfer->status === 'accepted' ? 'Awaiting confirmation' : ucfirst($transfer->status)) }}</span><h3 class="h5 mt-3">Request #{{ $transfer->id }} · {{ $transfer->product_name }}</h3><p><strong>{{ $transfer->from_name }} → {{ $transfer->to_name }}</strong><br>Quantity: {{ $transfer->accepted_quantity ?? $transfer->requested_quantity }} @if($transfer->accepted_quantity !== null && $transfer->accepted_quantity < $transfer->requested_quantity) (requested {{ $transfer->requested_quantity }}) @endif</p>
@if($transfer->note)<p>{{ $transfer->note }}</p>@endif
<div class="transfer-actions">
@if($source && $transfer->status === 'requested')
<form method="POST" action="{{ route('transfers.update',$transfer->id) }}">@csrf<input type="hidden" name="action" value="approve"><label>Quantity to send<input name="quantity" class="form-control" type="number" min="1" max="{{ $transfer->requested_quantity }}" value="{{ $transfer->requested_quantity }}" required></label><button class="btn btn-primary">Confirm request</button></form>
<form method="POST" action="{{ route('transfers.update',$transfer->id) }}">@csrf<input type="hidden" name="action" value="decline"><label>Reason (optional)<input class="form-control" name="note" maxlength="500"></label><button class="btn btn-outline-danger">Decline</button></form>
@endif
@if($source && $transfer->status === 'accepted')<form method="POST" action="{{ route('transfers.update',$transfer->id) }}">@csrf<input type="hidden" name="action" value="dispatch"><button class="btn btn-primary">Confirm request</button></form>@endif
@if($destination && $transfer->status === 'dispatched')<form method="POST" action="{{ route('transfers.update',$transfer->id) }}">@csrf<input type="hidden" name="action" value="receive"><button class="btn btn-primary">Receive {{ $transfer->accepted_quantity }} items</button></form>@endif
@if($destination && in_array($transfer->status,['requested','accepted']))<form method="POST" action="{{ route('transfers.update',$transfer->id) }}">@csrf<input type="hidden" name="action" value="cancel"><button class="btn btn-outline-secondary">Cancel request</button></form>@endif
</div>

</article>
@empty<p class="transfer-card">No stock requests yet.</p>@endforelse
{{ $transfers->links() }}
</section>
@endsection
