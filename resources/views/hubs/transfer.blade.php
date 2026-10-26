@extends('layouts.app')
@section('content')
@include('layouts.includes.sidebar')
@push('styles')<link rel="stylesheet" href="{{ asset('assets/css/hubs.css') }}?v={{ filemtime(public_path('assets/css/hubs.css')) }}"><link rel="stylesheet" href="{{ asset('assets/css/workshop.css') }}?v={{ filemtime(public_path('assets/css/workshop.css')) }}">
<link rel="stylesheet" href="{{ asset('assets/css/hub-workspace.css') }}?v={{ filemtime(public_path('assets/css/hub-workspace.css')) }}">
@endpush
<section class="workshop-page hub-workspace hub-page hub-send-page">
@include('hubs.workspace-header',['hubView'=>'transfer'])
@if(session('success'))<div class="alert alert-success" role="status">{{ session('success') }}</div>@endif
@if($errors->any())<div class="alert alert-danger" role="alert">@foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>@endif
<section class="hub-panel"><h2>Send stock</h2><p class="text-muted">Stock leaves {{ $hub->name }} when sent. The receiving hub gains it only after Owner/Manager confirms receipt.</p>
@if($hubs->count()<2)<p>Add another active hub to transfer stock.</p>@else
<form method="POST" action="{{ route('hubs.transfer-send',$hubId) }}" id="hubTransferSend">@csrf<input type="hidden" name="request_key" value="{{ (string)Str::uuid() }}">
<label>Stock type<select class="form-select" name="kind">@foreach(\App\Services\HubTransferService::KINDS as $key=>$label)<option value="{{ $key }}">{{ $label }}</option>@endforeach</select></label>
@include('hubs.product-picker',['pickerId'=>'hub-transfer-product'])
<p id="hubTransferAvailability" class="text-muted" aria-live="polite">Search and select a product to check this hub’s stock.</p>
<label>Receiving hub<select class="form-select" name="destination_hub_id" required><option value="">Choose another hub</option>@foreach($hubs->where('id','!=',$hubId) as $location)<option value="{{ $location->id }}">{{ $location->name }}</option>@endforeach</select></label>
<label>Quantity to send<input class="form-control" type="number" name="quantity" min="1" max="999999" required></label><button class="btn btn-primary">Send transfer</button></form>@endif
</section>
<section class="hub-panel"><h2>Transfers involving {{ $hub->name }}</h2><form method="GET" class="hub-toolbar"><input type="hidden" name="hub_id" value="{{ $hubId }}"><label>Status<select class="form-select" name="status" onchange="this.form.requestSubmit()"><option value="">All statuses</option>@foreach(['in_transit'=>'Awaiting receipt','received'=>'Received','cancelled'=>'Cancelled / returned'] as $key=>$label)<option value="{{ $key }}" @selected(request('status')===$key)>{{ $label }}</option>@endforeach</select></label><noscript><button>Filter</button></noscript></form>
@forelse($transfers as $transfer)<article class="hub-transfer-record"><div class="hub-inventory-row"><strong>#{{ $transfer->id }} · {{ $transfer->product_name }}</strong><span>{{ ['in_transit'=>'Awaiting receipt','received'=>'Received','cancelled'=>'Cancelled / returned'][$transfer->status] }}</span></div><p><strong>{{ $transfer->source_name }} → {{ $transfer->destination_name }}</strong><br>{{ \App\Services\HubTransferService::KINDS[$transfer->kind] }} · Quantity: {{ $transfer->quantity }}</p><small class="text-muted">Sent by {{ $transfer->sender_name }} · {{ \Carbon\Carbon::parse($transfer->created_at)->timezone('Africa/Nairobi')->format('d M Y H:i') }}@if($transfer->completed_at)<br>{{ $transfer->status==='received'?'Received':'Returned' }} by {{ $transfer->completer_name }} · {{ \Carbon\Carbon::parse($transfer->completed_at)->timezone('Africa/Nairobi')->format('d M Y H:i') }}@endif</small>
@if($transfer->status==='in_transit')<div class="hub-toolbar mt-2">@if($hubId===(int)$transfer->destination_hub_id)<form method="POST" action="{{ route('hubs.transfer-complete',$transfer->id) }}" data-transfer-review="Confirm receipt of all {{ $transfer->quantity }} {{ $transfer->kind==='finished'?'units':'pairs' }} of {{ $transfer->product_name }} at {{ $transfer->destination_name }}?">@csrf<input type="hidden" name="request_key" value="{{ (string)Str::uuid() }}"><input type="hidden" name="action" value="receive"><button class="btn btn-primary">Confirm receipt</button></form>@else<a class="btn btn-outline-primary" href="{{ route('hubs.transfer-page',['hub_id'=>$transfer->destination_hub_id]) }}">Open receiving hub</a>@endif
<form method="POST" action="{{ route('hubs.transfer-complete',$transfer->id) }}" data-transfer-review="Cancel transfer #{{ $transfer->id }} only if all {{ $transfer->quantity }} items are back at {{ $transfer->source_name }}. This returns the stock to that hub.">@csrf<input type="hidden" name="request_key" value="{{ (string)Str::uuid() }}"><input type="hidden" name="action" value="cancel"><button class="btn btn-outline-danger">Cancel / return to sender</button></form></div><p class="text-muted mt-2">Confirm only a full delivery. If anything is missing or damaged, leave it awaiting receipt until resolved.</p>@endif</article>@empty<p class="text-muted">No hub transfers yet.</p>@endforelse
{{ $transfers->links('pagination::bootstrap-5') }}
</section>
</section>
<script type="application/json" id="hubCatalog">{!! json_encode(['products'=>$products,'balances'=>$balances,'showBalances'=>false],JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) !!}</script>
<script type="application/json" id="hubTransferStocks">{!! json_encode($stocks,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) !!}</script>
@push('libraries')
<script src="{{ asset('assets/js/product-search.js') }}?v={{ filemtime(public_path('assets/js/product-search.js')) }}"></script>
<script src="{{ asset('assets/js/hubs.js') }}?v={{ filemtime(public_path('assets/js/hubs.js')) }}"></script>
<script src="{{ asset('assets/js/hub-transfers.js') }}?v={{ filemtime(public_path('assets/js/hub-transfers.js')) }}"></script>
@endpush
@endsection
