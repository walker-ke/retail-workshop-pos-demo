@extends('layouts.app')

@section('content')
<div class="pos-navigation">
    @include('layouts.includes.sidebar')
</div>
@push('styles')
<link rel="stylesheet" href="{{asset('assets/css/pos_index.css')}}?v={{ filemtime(public_path('assets/css/pos_index.css')) }}">
@endpush

<!-- BROWSE PRODUCTS MODAL (opened via Search) -->
<div class="modal fade" id="browseModal" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-xl" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fa-solid fa-magnifying-glass"></i> Search Products</h5>
        <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
      </div>
      <div class="modal-body">
        <div class="products-toolbar">
            <div class="search-box">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input type="text" id="posSearch" placeholder="Search by name, code or brand..." autofocus>
            </div>
            <div class="page-size-select">
                <label>Show</label>
                <select id="pageSizeSelect">
                    <option value="24">24</option>
                    <option value="48" selected>48</option>
                    <option value="96">96</option>
                    <option value="99999">All</option>
                </select>
            </div>
        </div>
        <div class="category-chips" id="categoryChips">
            <button type="button" class="chip active" data-category="all">All</button>
            @foreach($categories as $cat)
                <button type="button" class="chip" data-category="{{ strtolower(trim($cat)) }}">{{ $cat }}</button>
            @endforeach
        </div>

        <div class="tile-grid" id="tileGrid"></div>
        <script type="application/json" id="posCatalog" data-add-url="{{ url('/add-cart') }}">@json($catalog)</script>

        <div class="tile-pagination">
            <button type="button" id="prevPage" class="btn btn-sm btn-outline-secondary"><i class="fa-solid fa-chevron-left"></i></button>
            <span id="pageIndicator">Page 1</span>
            <button type="button" id="nextPage" class="btn btn-sm btn-outline-secondary"><i class="fa-solid fa-chevron-right"></i></button>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- BARCODE HINT / OUT OF STOCK -->
<div class="modal fade" id="outofStockModal" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" role="document">
    <div class="modal-content">
      <div class="modal-body">
        <div class="outofstock">
            <br><h1><i class="fa-solid fa-cart-plus"></i></h1><br>
            <h2>This Product is Out of Stock</h2>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary btnClose" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

@php $prod = Cart::content(); @endphp

@if(auth()->user()->isTopAdmin())
<div class="pos-shop-selector">@include('pos.shop_selector')</div>
@endif
<div class="pos-shell">
    <!-- MAIN: RECEIPT -->
    <div class="pos-main">
        <div class="pos-topbar">
            <button type="button" class="pos-icon-btn" id="topDeleteBtn" title="Delete last line"><i class="fa-solid fa-trash"></i><small>Delete</small></button>
            <button type="button" class="pos-icon-btn" id="topQtyBtn" title="Edit quantity of last line"><i class="fa-solid fa-hashtag"></i><small>Quantity</small></button>
            <button type="button" class="pos-icon-btn" id="topNewSaleBtn" title="Start a new sale"><i class="fa-solid fa-file-circle-plus"></i><small>New sale</small></button>
            <div class="pos-topbar-search-wrap">
                <div class="pos-topbar-search">
                    <i class="fa-solid fa-barcode"></i>
                    <input type="text" id="barcodeInput" placeholder="Search products by name, or scan barcode, then Enter" autocomplete="off">
                </div>
                <div class="topbar-search-results" id="topbarSearchResults"></div>
            </div>
        </div>

        <div class="receipt-wrap" id="receiptWrap">
            @if(count($prod) > 0)
            <table class="receipt-table">
                <thead>
                    <tr>
                        <th>Product name</th>
                        <th style="width: 190px;">Quantity</th>
                        <th style="width: 110px;">Price</th>
                        <th style="width: 120px;">Amount</th>
                        <th style="width: 40px;"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($prod as $product)
                        <tr data-row-id="{{ $product->rowId }}">
                            <td>{{$product->name}}</td>
                            <td>
                                <form action="{{ url('/update-cart/'.$product->rowId) }}" method="POST" class="receipt-qty-form" data-row-id="{{ $product->rowId }}">
                                    @csrf
                                    <input type="number" min="1" name="qty" value="{{$product->qty}}" title="Quantity">
                                    <input type="number" min="0" step="0.01" name="price" @readonly(!auth()->user()->hasAccess('edit_prices')) value="{{$product->price}}" title="Unit Price">
                                    <button type="submit" title="Update"><i class="fa-solid fa-check"></i></button>
                                </form>
                            </td>
                            <td><span class="currency">KES</span> {{$product->price}}</td>
                            <td><span class="currency">KES</span> {{$product->price*$product->qty}}</td>
                            <td><a href="{{ URL::to('/delete-cart/'.$product->rowId) }}" class="receipt-remove" data-row-id="{{ $product->rowId }}" title="Void item"><i class="fa-solid fa-xmark"></i></a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            @else
            <div class="receipt-empty">
                <i class="fa-solid fa-receipt"></i>
                <h4>No items</h4>
                <p>Search products by name or enter a barcode or product code</p>
            </div>
            @endif
        </div>

        <div class="pos-summary" id="posSummary">
            <div class="summary-row"><span>Subtotal</span><span><span class="currency">KES</span> <span id="sumSubtotal">{{Cart::subtotal();}}</span></span></div>
            <div class="summary-row summary-total"><span>Total</span><span><span class="currency">KES</span> <span id="sumTotal">{{Cart::total();}}</span></span></div>
        </div>
    </div>

    <!-- RIGHT: ACTION PANEL -->
    <div class="pos-side">
        <div class="pos-side-header">
            <div>
                <div class="pos-side-title">POS &middot; {{ Auth::user()->name }}</div>
                <div class="pos-side-date">{{ now('Africa/Nairobi')->format('d/m/Y') }}</div>
            </div>
            <button type="button" class="pos-menu-toggle" id="sideMenuToggle" title="Menu"><i class="fa-solid fa-bars"></i></button>
        </div>

        <div class="pos-side-menu" id="posSideMenu">
            @if(Auth::user()->hasAccess('dashboard'))
            <a href="{{ route('home') }}"><i class="fa-solid fa-gauge"></i> Management</a>
            @endif
            @if(Auth::user()->hasAccess('stock'))
            <a href="{{ route('stock_index') }}"><i class="fa-solid fa-dolly"></i> Adjust stock</a>
            @endif
            <a href="{{ route('transaction') }}"><i class="fa-solid fa-clock-rotate-left"></i> View sales history</a>
            <div class="pos-side-divider">User</div>
            <div class="pos-side-user"><i class="fa-solid fa-user"></i> {{ Auth::user()->email ?? Auth::user()->username ?? Auth::user()->name }} &middot; {{ Auth::user()->roleLabel() }}</div>
            <a href="{{ route('logout') }}" onclick="event.preventDefault(); document.getElementById('logout-form').submit();"><i class="fa-solid fa-right-from-bracket"></i> Sign out</a>
        </div>

        <form action="{{ url('/create-invoice') }}" method="POST" id="checkoutForm">
            @csrf
            @if ($errors->any())
                <div class="alert alert-danger m-2">
                    <ul class="mb-0">@foreach($errors->all() as $error)<li>{{$error}}</li>@endforeach</ul>
                </div>
            @endif
            <div class="pos-customer-row">
                <div class="w-100">
                    <label for="customerName" class="form-label">Customer name (optional)</label>
                    <input type="text" class="form-control" name="customer_name" id="customerName" maxlength="255" value="{{ old('customer_name') }}" placeholder="Walk-in Customer">
                </div>
            </div>
        </form>

        <div class="pos-payment-grid">
            @forelse($paymentTypes as $pt)
                <button type="submit" form="checkoutForm" name="payment_method" value="{{ $pt->name }}" class="pos-action pos-action-payment {{ $loop->first ? 'pos-action-payment-primary' : '' }}">
                    {{ $pt->name }}
                </button>
            @empty
                <span class="text-danger small">No payment types configured. Ask an administrator to add one under Payment Types.</span>
            @endforelse
            @if($paymentTypes->contains(fn ($type) => strcasecmp($type->name, 'Cash') === 0) && $paymentTypes->contains(fn ($type) => strcasecmp($type->name, 'Paybill') === 0))
            <button type="submit" form="checkoutForm" name="payment_method" value="__combined" class="pos-action pos-action-payment">Combined Payment</button>
            @endif
        </div>

        <div class="pos-action-grid">
            <a href="{{ URL::to('/delete-all') }}" id="voidOrderBtn" class="pos-action pos-action-danger"><i class="fa-solid fa-ban"></i> Void order</a>
        </div>

        <div class="pos-side-footer">
            <a href="{{ route('refunds') }}" class="btn btn-outline-danger btn-sm w-100"><i class="fa-solid fa-rotate-left"></i> Refund</a>
        </div>
    </div>
</div>

<form id="logout-form" action="{{ route('logout') }}" method="POST" class="d-none">@csrf</form>

@endsection
@section('script')


<script>
    // data-dismiss delegation is unreliable on this page (multiple conflicting
    // Bootstrap/jQuery builds loaded site-wide), so close/X buttons are bound directly.
    $(document).on('click', '[data-bs-dismiss="modal"]', function () {
        $(this).closest('.modal').modal('hide');
    });
</script>

<script src="{{ asset('assets/js/product-search.js') }}?v={{ filemtime(public_path('assets/js/product-search.js')) }}"></script>
<script src="{{ asset('assets/js/pos-catalog.js') }}?v={{ filemtime(public_path('assets/js/pos-catalog.js')) }}"></script>

<!-- ACTION PANEL WIRING -->
<script>
(function() {
    document.querySelectorAll('.decorative, .disabled-link').forEach(function(el) {
        el.addEventListener('click', function(e) {
            e.preventDefault();
            toastr.info(this.dataset.tip || 'Not available yet');
        });
    });

    var sideMenuToggle = document.getElementById('sideMenuToggle');
    var sideMenu = document.getElementById('posSideMenu');
    if (sideMenuToggle) {
        sideMenuToggle.addEventListener('click', function() {
            sideMenu.classList.toggle('show');
        });
    }

    // ===== AJAX CART (add/update/remove/clear without reloading the page) =====
    function escapeHtml(str) {
        return String(str).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    function renderCart(cart) {
        var $wrap = $('#receiptWrap');

        if (!cart.hasItems) {
            $wrap.html(
                '<div class="receipt-empty">' +
                    '<i class="fa-solid fa-receipt"></i>' +
                    '<h4>No items</h4>' +
                    '<p>Search products by name or enter a barcode or product code</p>' +
                '</div>'
            );
        } else {
            var rows = cart.items.map(function (item) {
                var rowId = escapeHtml(item.rowId);
                return (
                    '<tr data-row-id="' + rowId + '">' +
                        '<td>' + escapeHtml(item.name) + '</td>' +
                        '<td>' +
                            '<form action="{{ url('/update-cart') }}/' + rowId + '" method="POST" class="receipt-qty-form" data-row-id="' + rowId + '">' +
                                '<input type="hidden" name="_token" value="{{ csrf_token() }}">' +
                                '<input type="number" min="1" name="qty" value="' + item.qty + '" title="Quantity">' +
                                '<input type="number" min="0" step="0.01" name="price" @readonly(!auth()->user()->hasAccess('edit_prices')) value="' + item.price + '" title="Unit Price">' +
                                '<button type="submit" title="Update"><i class="fa-solid fa-check"></i></button>' +
                            '</form>' +
                        '</td>' +
                        '<td><span class="currency">KES</span> ' + item.price + '</td>' +
                        '<td><span class="currency">KES</span> ' + item.amount + '</td>' +
                        '<td><a href="{{ url('/delete-cart') }}/' + rowId + '" class="receipt-remove" data-row-id="' + rowId + '" title="Void item"><i class="fa-solid fa-xmark"></i></a></td>' +
                    '</tr>'
                );
            }).join('');

            $wrap.html(
                '<table class="receipt-table">' +
                    '<thead><tr>' +
                        '<th>Product name</th>' +
                        '<th style="width: 190px;">Quantity</th>' +
                        '<th style="width: 110px;">Price</th>' +
                        '<th style="width: 120px;">Amount</th>' +
                        '<th style="width: 40px;"></th>' +
                    '</tr></thead>' +
                    '<tbody>' + rows + '</tbody>' +
                '</table>'
            );
        }

        $('#sumSubtotal').text(cart.subtotal);
        $('#sumTax').text(cart.tax);
        $('#sumTotal').text(cart.total);
    }

    function cartRequest(url, formData) {
        return fetch(url, {
            method: 'POST',
            headers: { 'Accept': 'application/json' },
            body: formData
        }).then(function (resp) {
            return resp.json().then(function (data) {
                if (!resp.ok) throw new Error(data.message || 'Something went wrong.');
                return data;
            });
        });
    }

    window.submitAddToCart = function (form) {
        cartRequest(form.action, new FormData(form)).then(function (data) {
            renderCart(data.cart);
        }).catch(function (err) {
            toastr.error(err.message);
        });
    };

    var qtyPriceDebounce = {};

    $(document).on('submit', '.receipt-qty-form', function (e) {
        e.preventDefault();
        clearTimeout(qtyPriceDebounce[this.dataset.rowId]);
        cartRequest(this.action, new FormData(this)).then(function (data) {
            renderCart(data.cart);
            toastr.success(data.message || 'Cart updated');
        }).catch(function (err) {
            toastr.error(err.message);
        });
    });

    // Auto-save once the qty/price edit is committed (blur, tab-away, or a
    // spinner click) so adjusting a line doesn't need a tick-click too. Bound to
    // 'change' rather than 'input' - saving while the user is still typing would
    // re-render the row (and the table it lives in) out from under their cursor.
    $(document).on('change', '.receipt-qty-form input[name="qty"], .receipt-qty-form input[name="price"]', function () {
        var form = this.closest('form');
        var rowId = form.dataset.rowId;
        clearTimeout(qtyPriceDebounce[rowId]);
        qtyPriceDebounce[rowId] = setTimeout(function () {
            $(form).trigger('submit');
        }, 100);
    });

    $(document).on('click', '.receipt-remove', function (e) {
        e.preventDefault();
        var formData = new FormData();
        formData.append('_token', '{{ csrf_token() }}');
        cartRequest(this.href, formData).then(function (data) {
            renderCart(data.cart);
            toastr.success('Product removed');
        }).catch(function (err) {
            toastr.error(err.message);
        });
    });

    function clearCart() {
        var formData = new FormData();
        formData.append('_token', '{{ csrf_token() }}');
        cartRequest('{{ url('/delete-all') }}', formData).then(function (data) {
            renderCart(data.cart);
            toastr.success('Cart cleared');
        }).catch(function (err) {
            toastr.error(err.message);
        });
    }

    var voidOrderBtn = document.getElementById('voidOrderBtn');
    if (voidOrderBtn) {
        voidOrderBtn.addEventListener('click', function (e) {
            e.preventDefault();
            clearCart();
        });
    }

    var topNewSaleBtn = document.getElementById('topNewSaleBtn');
    if (topNewSaleBtn) {
        topNewSaleBtn.addEventListener('click', function() {
            clearCart();
        });
    }

    var topDeleteBtn = document.getElementById('topDeleteBtn');
    if (topDeleteBtn) {
        topDeleteBtn.addEventListener('click', function() {
            var rows = document.querySelectorAll('.receipt-table tbody tr');
            if (!rows.length) {
                toastr.info('Cart is empty');
                return;
            }
            var lastRow = rows[rows.length - 1];
            var removeLink = lastRow.querySelector('.receipt-remove');
            if (removeLink) removeLink.click();
        });
    }

    var topQtyBtn = document.getElementById('topQtyBtn');
    if (topQtyBtn) {
        topQtyBtn.addEventListener('click', function() {
            var rows = document.querySelectorAll('.receipt-table tbody tr');
            if (!rows.length) {
                toastr.info('Cart is empty');
                return;
            }
            var lastRow = rows[rows.length - 1];
            var qtyInput = lastRow.querySelector('input[name="qty"]');
            if (qtyInput) { qtyInput.focus(); qtyInput.select(); }
        });
    }

    document.querySelectorAll('.pos-action-payment').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            var rows = document.querySelectorAll('.receipt-table tbody tr');
            if (!rows.length) {
                e.preventDefault();
                toastr.info('Cart is empty');
            }
        });
    });


})();
</script>

@endsection
