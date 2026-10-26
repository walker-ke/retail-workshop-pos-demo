@if(Auth::user()->isTopAdmin())
    <form method="POST" action="{{ route('switch_shop') }}" class="shop-picker-form {{ $wrapClass ?? '' }}">
        @csrf
        <i class="fa-solid fa-shop"></i>
        <select name="shop_id" class="shop-picker-select" onchange="this.form.submit()">
            <option value="" {{ !$shopId ? 'selected' : '' }}>All Shops (combined)</option>
            @foreach(\App\Models\Shop::where('is_active', true)->orderBy('name')->get() as $shop)
                <option value="{{ $shop->id }}" {{ (int) $shopId === $shop->id ? 'selected' : '' }}>{{ $shop->name }}</option>
            @endforeach
        </select>
    </form>
@else
    <span class="{{ $wrapClass ?? '' }}"><i class="fa-solid fa-shop"></i> {{ $shopId ? \App\Models\Shop::find($shopId)?->name : 'All Shops (combined)' }}</span>
@endif
