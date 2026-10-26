@extends('layouts.app')
@section('content')
@include('layouts.includes.sidebar')
@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/hubs.css') }}?v={{ filemtime(public_path('assets/css/hubs.css')) }}">
<link rel="stylesheet" href="{{ asset('assets/css/workshop.css') }}?v={{ filemtime(public_path('assets/css/workshop.css')) }}">
<link rel="stylesheet" href="{{ asset('assets/css/hub-workspace.css') }}?v={{ filemtime(public_path('assets/css/hub-workspace.css')) }}">
@endpush
<section class="workshop-page hub-workspace hub-page hub-send-page">
@include('hubs.workspace-header',['hubView'=>'send'])
@if($errors->any())<div class="alert alert-danger" role="alert">@foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>@endif
<div class="hub-form-page">
<section class="hub-panel"><h2 class="mb-3">Send stock to a shop</h2><p class="text-muted">Hub stock leaves now. Shop stock increases only after the cashier confirms.</p><form class="hub-send-form" method="POST" action="{{ route('hubs.send',$hubId) }}">@csrf<input type="hidden" name="request_key" value="{{ (string)Str::uuid() }}">
@include('hubs.product-picker',['pickerId'=>'hub-send-product'])
<label>Destination shop<select name="shop_id" class="form-select" required><option value="">Choose shop</option>@foreach($shops as $shop)<option value="{{ $shop->id }}">{{ $shop->name }}</option>@endforeach</select></label>
<label>Quantity to send<input type="number" class="form-control" name="quantity" min="1" max="999999" required></label>
<label class="hub-send-note">Note (optional)<input class="form-control" name="note" maxlength="500"></label><button class="btn btn-primary">Send stock</button></form></section>
</div>
</section>
<script type="application/json" id="hubCatalog">{!! json_encode(['products'=>$products,'balances'=>$balances],JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) !!}</script>
@push('libraries')
<script src="{{ asset('assets/js/product-search.js') }}?v={{ filemtime(public_path('assets/js/product-search.js')) }}"></script>
<script src="{{ asset('assets/js/hubs.js') }}?v={{ filemtime(public_path('assets/js/hubs.js')) }}"></script>
@endpush
@endsection
