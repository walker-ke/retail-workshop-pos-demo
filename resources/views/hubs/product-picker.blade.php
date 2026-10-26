<div class="hub-product-picker">
<label for="{{ $pickerId }}">Product</label>
<input id="{{ $pickerId }}" class="form-control" type="search" autocomplete="off" placeholder="Search products by name" aria-controls="{{ $pickerId }}-results" aria-expanded="false" required>
<input type="hidden" name="product_id" value="">
<div id="{{ $pickerId }}-results" class="hub-product-results" hidden></div>
<p class="hub-product-status text-muted" aria-live="polite">Search and select an item.</p>
</div>
