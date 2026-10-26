<article class="hub-delivery">
<span class="badge bg-light text-dark">{{ ['dispatched'=>'Awaiting receipt','reported'=>'Missing items reported','received'=>'Received','resolved'=>'Resolved'][$delivery->status] ?? $delivery->status }}</span>
<strong class="d-block">Delivery #{{ $delivery->id }} · {{ $delivery->product_name }}</strong>
<small class="text-muted">{{ \Carbon\Carbon::parse($delivery->created_at)->format('d M Y, H:i') }}</small>
<p>{{ $delivery->hub_name }} → {{ $delivery->shop_name }}<br>Sent: <strong>{{ $delivery->quantity }}</strong> · By {{ $delivery->sender_name }}</p>
@if($delivery->note)<p>{{ $delivery->note }}</p>@endif
@if($delivery->received_quantity!==null)<p>Received: <strong>{{ $delivery->received_quantity }} / {{ $delivery->quantity }}</strong> · {{ $delivery->receiver_name }}@if($delivery->receipt_note)<br>{{ $delivery->receipt_note }}@endif</p>@endif
@if($delivery->resolution)<p>Remainder {{ $delivery->resolution==='returned'?'returned to hub':'written off' }}: {{ $delivery->quantity-$delivery->received_quantity }}<br>{{ $delivery->resolution_note }}</p>@endif
@if($delivery->status==='dispatched' && !auth()->user()->isTopAdmin() && auth()->user()->activeShopId()===(int)$delivery->shop_id)
<details><summary>Confirm delivery</summary><form method="POST" action="{{ route('hubs.receive',$delivery->id) }}">@csrf<input type="hidden" name="request_key" value="{{ (string)Str::uuid() }}">
<label>Quantity actually received<input class="form-control" type="number" name="quantity" min="0" max="{{ $delivery->quantity }}" required></label>
<label>Note — required if anything is missing or damaged<input class="form-control" name="note" maxlength="500"></label><p class="text-muted">Confirm after counting. Only this quantity enters your shop stock; any difference goes to the owner.</p><button class="btn btn-primary">Confirm received quantity</button></form></details>
@elseif($delivery->status==='dispatched' && auth()->user()->isTopAdmin())<small class="text-muted">Waiting for the shop cashier to confirm. Owners cannot confirm their own deliveries.</small>@endif
@if($delivery->status==='reported' && auth()->user()->isTopAdmin())
<form method="POST" action="{{ route('hubs.resolve',$delivery->id) }}">@csrf<input type="hidden" name="request_key" value="{{ (string)Str::uuid() }}"><label>Resolve {{ $delivery->quantity-$delivery->received_quantity }} missing items<select class="form-select" name="resolution" required><option value="">Choose what happened</option><option value="returned">Physically returned to hub</option><option value="written_off">Lost or damaged — write off</option></select></label><label>Reason<input class="form-control" name="note" maxlength="500" required></label><button class="btn btn-outline-primary">Save resolution</button></form>
@endif
</article>
