@if(auth()->user()->isTopAdmin())
<form method="POST" action="{{ route('switch_shop') }}" class="d-flex flex-wrap align-items-center justify-content-center gap-2">
    @csrf
    <label for="posShopSelector" class="fw-semibold">Shop</label>
    <select id="posShopSelector" name="shop_id" class="form-select" style="width: min(100%, 300px);" onchange="this.form.submit()">
        <option value="" {{ !auth()->user()->activeShopId() ? 'selected' : '' }}>All Shops (combined)</option>
        @foreach(\App\Models\Shop::where('is_active', true)->orderBy('name')->get() as $shop)
            <option value="{{ $shop->id }}" {{ auth()->user()->activeShopId() === $shop->id ? 'selected' : '' }}>{{ $shop->name }}</option>
        @endforeach
    </select>
</form>
@endif
