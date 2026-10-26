<form method="POST" action="{{ route('workshop.production') }}" id="workshopProduction" data-availability-url="{{ route('workshop.availability') }}">@csrf<input type="hidden" name="request_key" value="{{ (string)Str::uuid() }}">
<label>Fundi<select name="fundi_id" class="form-select" id="workshopFundi" required><option value="">Choose fundi</option>@foreach($fundis->where('is_active',true) as $person)<option value="{{ $person->id }}">{{ $person->name }}</option>@endforeach</select></label>
<label>Stage<select name="stage" class="form-select" id="workshopStage" required><option value="">Choose stage</option>@foreach($stages as $key=>$label)<option value="{{ $key }}">{{ $label }}</option>@endforeach</select></label>
<label id="workshopGarmentLabel" hidden>Garment type<select class="form-select" name="garment_type" id="workshopGarment" disabled><option value="">Choose garment type</option>@foreach(\App\Services\WorkshopService::GARMENTS as $key=>$label)<option value="{{ $key }}">{{ $label }}</option>@endforeach</select></label>
@include('hubs.product-picker',['pickerId'=>'workshopProduct'])

<label>Output hub<select name="hub_id" class="form-select" required><option value="" @selected(!$hubId)>Choose output hub</option>@foreach($hubs as $hub)<option value="{{ $hub->id }}" @selected($hubId===$hub->id)>{{ $hub->name }}</option>@endforeach</select></label>
<div class="workshop-fields"><label><span id="workshopQuantityLabel">Accepted quantity (pieces / pairs)</span><input class="form-control" type="number" min="1" max="999999" name="quantity" id="workshopQuantity" required></label><label>Work date<input class="form-control" type="date" name="work_date" value="{{ now('Africa/Nairobi')->toDateString() }}" max="{{ now('Africa/Nairobi')->toDateString() }}" required></label></div>
<p id="workshopAvailability" class="text-muted" aria-live="polite">Select a product to check components in the output hub.</p>
<p class="workshop-preview" id="workshopEarnings" aria-live="polite">Choose a fundi and stage to see their rate.</p>
<p class="text-muted" id="workshopStageHelp">Garments are pieces; uppers and shoes are pairs. Shoe assembly deducts one upper pair per finished pair automatically, using any upper type or size from this hub. Soles are not required or deducted.</p>
<button class="btn btn-primary">Save Entry & Credit Ledger</button></form>
