@extends('layouts.app')
@section('content')
@include('layouts.includes.sidebar')
@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/stock_index.css') }}?v={{ filemtime(public_path('assets/css/stock_index.css')) }}">
<link rel="stylesheet" href="{{ asset('assets/css/hubs.css') }}?v={{ filemtime(public_path('assets/css/hubs.css')) }}">
<link rel="stylesheet" href="{{ asset('assets/css/workshop.css') }}?v={{ filemtime(public_path('assets/css/workshop.css')) }}">
<link rel="stylesheet" href="{{ asset('assets/css/hub-workspace.css') }}?v={{ filemtime(public_path('assets/css/hub-workspace.css')) }}">
@endpush
<section class="workshop-page hub-workspace hub-page hub-stock-page">
@include('hubs.workspace-header',['hubView'=>'stock'])
@if(session('success'))<div class="alert alert-success" role="status">{{ session('success') }}</div>@endif
@if($errors->any())<div class="alert alert-danger" role="alert">@foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>@endif
<div class="prod-toolbar"><div class="prod-tool-row">
<button type="button" class="prod-tool" id="hubStockRefresh"><i class="fa-solid fa-arrows-rotate" aria-hidden="true"></i><span>Refresh</span></button>
<a class="prod-tool" href="{{ route('hubs.index',['hub_id'=>$hubId]) }}"><i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i><span>Stock history</span></a>

</div></div>
<div class="hub-stock-layout">
<aside class="hub-panel hub-stock-categories"><label for="hubStockBrand">Product group</label><select id="hubStockBrand" class="form-select"><option value="">All products</option></select><label class="mt-3" for="hubStockQuantityFilter">Stock in hub</label><select id="hubStockQuantityFilter" class="form-select"><option value="all">All quantities</option><option value="positive">In stock</option><option value="zero">Zero quantity</option></select></aside>
<div class="hub-panel hub-stock-list">
<div class="hub-stock-search"><label for="hubStockSearch">Search products</label><input id="hubStockSearch" class="form-control" type="search" placeholder="Search products by name" autocomplete="off"><label for="hubStockPageSize">Show entries</label><select id="hubStockPageSize" class="form-select"><option>25</option><option selected>50</option><option>100</option></select></div>
<p id="hubStockResultsStatus" class="text-muted" aria-live="polite"></p>
<div class="hub-stock-table-scroll" tabindex="0" role="region" aria-label="Hub product stock"><table class="table table-striped table-bordered table-sm hub-stock-table"><thead><tr><th scope="col">Product</th><th scope="col">In this hub</th><th scope="col">Action</th></tr></thead><tbody id="hubStockRows"></tbody></table></div>
<nav class="hub-stock-pagination" aria-label="Product pages"><button type="button" class="btn btn-outline-secondary" id="hubStockPrevious">Previous</button><span id="hubStockPageLabel"></span><button type="button" class="btn btn-outline-secondary" id="hubStockNext">Next</button></nav>
<noscript><p>Enable JavaScript to search products and adjust hub stock.</p></noscript>
</div></div>
<div class="modal fade" id="hubStockEditor" tabindex="-1" aria-labelledby="hubStockEditorTitle" aria-hidden="true"><div class="modal-dialog modal-dialog-centered modal-dialog-scrollable"><div class="modal-content">
<div class="modal-header"><h2 class="modal-title" id="hubStockEditorTitle">Adjust hub stock</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
<div class="modal-body"><p id="hubStockProductName" class="fw-bold"></p><p>{{ $hub->name }}: <strong id="hubStockCurrent">—</strong> in hub</p>
<p id="hubStockLoadStatus" role="status" aria-live="polite"></p><button type="button" id="hubStockRetry" class="btn btn-outline-secondary mb-2" hidden>Retry</button>
<form id="hubStockForm" method="POST" action="{{ route('hubs.stock',$hubId) }}">@csrf<input type="hidden" name="request_key" value="{{ (string)Str::uuid() }}"><input type="hidden" name="product_id" id="hubStockProductId"><input type="hidden" name="expected_quantity" id="hubStockExpectedQuantity">
<label for="hubStockAction">Action</label><select class="form-select" name="action" id="hubStockAction"><option value="add">Add stock received</option><option value="count">Set counted quantity</option></select>
<label for="hubStockQuantity" id="hubStockQuantityLabel">Quantity to add</label><input id="hubStockQuantity" class="form-control" type="number" name="quantity" min="1" max="999999" required>
<p class="text-muted mb-0">Only {{ $hub->name }} changes. Shop stock stays the same.</p>
<button class="btn btn-primary" id="hubStockSave" disabled>Save hub stock</button></form></div>
</div></div></div>
</section>
<script type="application/json" id="hubCatalog">{!! json_encode(['products'=>$products,'balances'=>$balances,'stockUrl'=>route('hubs.product-stock',[$hubId,0])],JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) !!}</script>
@push('libraries')
<script src="{{ asset('assets/js/product-search.js') }}?v={{ filemtime(public_path('assets/js/product-search.js')) }}"></script>
<script src="{{ asset('assets/js/hub-stock.js') }}?v={{ filemtime(public_path('assets/js/hub-stock.js')) }}"></script>
@endpush
@endsection
