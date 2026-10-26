@extends('layouts.app')

@section('content')
@include('layouts.includes.sidebar')
@push('styles')
<link rel="stylesheet" href="{{asset('assets/css/stock_index.css')}}?v={{ filemtime(public_path('assets/css/stock_index.css')) }}">
@endpush

<!-- ADD STOCK MODAL -->
<div class="modal fade" id="addStockModal" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Add new stock received</h5>
        <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
      </div>
      <form id="addStockForm" action="{{ url('/add-stock') }}" method="POST">
        @csrf
        <input type="hidden" name="request_key" value="{{ (string)Str::uuid() }}">
        <div class="modal-body"><div id="addStockError" class="alert alert-danger" role="alert" hidden></div>
            @if(!$shopId)
            <div class="form-group mb-3">
                <label>Shop</label>
                <select class="form-control" name="shop_id" required>
                    <option value="" disabled selected>Select Shop</option>
                    @foreach($shops as $shop)
                        <option value="{{$shop->id}}">{{$shop->name}}</option>
                    @endforeach
                </select>
            </div>
            @endif
            <div class="form-group mb-3">
                <label>Product</label>
                <select class="form-select chosen" name="product_id" required style="width: 100%;">
                    <option value="" disabled selected>Select Product</option>
                    @foreach($products as $product)
                        <option value="{{$product->id}}" data-product-name="{{ $product->product_name }}" data-product-code="{{ $product->product_code }}">{{$product->product_name}} ({{$product->product_code}}) &mdash; {{ $shopId ? 'current stock' : 'current stock, all shops' }}: {{$product->quantity}}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-row">
                <div class="form-group col-12 mb-3">
                    <label>Quantity to Add</label>
                    <input type="number" min="1" class="form-control" name="quantity" placeholder="Enter quantity" required>
                </div>

            </div>
            <div class="form-group mb-3">
                <label>Reference / Invoice No. (optional)</label>
                <input type="text" class="form-control" name="reference" maxlength="255" placeholder="Optional reference number">
            </div>
            <div class="form-group mb-3">
                <label>Notes</label>
                <textarea class="form-control" name="notes" rows="1" style="min-height:44px;height:44px;resize:vertical" placeholder="Optional notes"></textarea>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            <button type="submit" class="btn btn-primary"><i class="fa-solid fa-box-open"></i> Add new stock received</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- QUICK "UPDATE STOCK QUANTITY" MODAL -->
<div class="modal fade" id="quickStockModal" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered quick-stock-dialog" role="document">
    <div class="modal-content">
      <button type="button" class="close quick-stock-close" data-bs-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
      <div class="quick-stock-header">
          <h5 class="modal-title">Set counted quantity</h5>
          <p>Set stock quantity for <strong id="quickStockProductName"></strong>. A stock history entry will be created automatically.</p>
      </div>
      <form id="quickStockForm" action="{{ route('quick_stock_update') }}" method="POST">
          @csrf
          <input type="hidden" name="product_id" id="quickStockProductId">
          <input type="hidden" name="quantity" id="quickStockQuantity" value="0">
          <div class="quick-stock-display" id="quickStockDisplay">0</div>
          <div class="quick-stock-keypad">
              <button type="button" class="qk-btn" data-key="1">1</button>
              <button type="button" class="qk-btn" data-key="2">2</button>
              <button type="button" class="qk-btn" data-key="3">3</button>
              <button type="button" class="qk-btn qk-muted" data-key="back"><i class="fa-solid fa-delete-left"></i></button>

              <button type="button" class="qk-btn" data-key="4">4</button>
              <button type="button" class="qk-btn" data-key="5">5</button>
              <button type="button" class="qk-btn" data-key="6">6</button>
              <button type="button" class="qk-btn qk-muted" data-key="esc" data-bs-dismiss="modal">esc</button>

              <button type="button" class="qk-btn" data-key="7">7</button>
              <button type="button" class="qk-btn" data-key="8">8</button>
              <button type="button" class="qk-btn" data-key="9">9</button>
              <button type="submit" class="qk-btn qk-enter"><i class="fa-solid fa-check"></i></button>

              <button type="button" class="qk-btn" data-key="-">-</button>
              <button type="button" class="qk-btn" data-key="0">0</button>
              <button type="button" class="qk-btn" data-key=".">.</button>
          </div>
      </form>
    </div>
  </div>
</div>

<!-- RIGHT-CLICK STOCK CONTEXT MENU -->
<ul id="stockContextMenu" class="stock-context-menu">
    <li class="stock-context-title" id="stockContextMenuTitle"></li>
    <li data-action="quick-update"><i class="fa-solid fa-calculator"></i> Set counted quantity</li>
    <li data-action="add-stock"><i class="fa-solid fa-box-open"></i> Add new stock received</li>
    <li data-action="stock-history"><i class="fa-solid fa-clock-rotate-left"></i> View Stock History</li>
</ul>

<div class="prod-shell">
    <div class="prod-toolbar">
        <div class="prod-tool-row">
            <div class="prod-tool-group">
                <button type="button" class="prod-tool" id="refreshBtn"><i class="fa-solid fa-arrows-rotate"></i><span>Refresh</span></button>
            </div>
            <div class="prod-tool-sep"></div>
            <div class="prod-tool-group">
                <a href="{{ route('stock_history') }}" class="prod-tool"><i class="fa-solid fa-clock-rotate-left"></i><span>Stock history</span></a>
                <button type="button" class="prod-tool" id="addStockBtn"><i class="fa-solid fa-box-open"></i><span>Add new stock received</span></button>
            </div>
            <div class="prod-tool-sep"></div>
            <div class="prod-tool-group">
                <button type="button" class="prod-tool" id="printBtn"><i class="fa-solid fa-print"></i><span>Print</span></button>
                @if(auth()->user()->hasAccess('products'))
                <a href="{{ route('export_products', $selectedBrand ? ['brand' => $selectedBrand] : []) }}" class="prod-tool"><i class="fa-solid fa-file-excel"></i><span>Excel</span></a>
                @endif
            </div>
            <div class="prod-tool-sep"></div>
            <div class="prod-tool-group">
                <a href="{{ route('inventory_count_report', array_filter(['brand' => $selectedBrand, 'shop_id' => $shopId])) }}" target="_blank" class="prod-tool"><i class="fa-solid fa-clipboard-list"></i><span>Inventory count report</span></a>
            </div>
            <div class="prod-tool-sep"></div>
            <div class="prod-tool-group">
                @include('partials.shop_picker', ['wrapClass' => 'prod-tool'])
            </div>
        </div>
    </div>

    <div class="stock-filters">
        <label class="stock-filter-chip">
            <input type="checkbox" class="stock-filter-cb" value="negative">
            <span>Negative quantity</span>
            <span class="stock-filter-count count-negative">{{ $negativeCount }}</span>
        </label>
        <label class="stock-filter-chip">
            <input type="checkbox" class="stock-filter-cb" value="nonzero">
            <span>Non zero quantity</span>
            <span class="stock-filter-count count-nonzero">{{ $nonZeroCount }}</span>
        </label>
        <label class="stock-filter-chip">
            <input type="checkbox" class="stock-filter-cb" value="zero">
            <span>Zero quantity</span>
            <span class="stock-filter-count count-zero">{{ $zeroCount }}</span>
        </label>
        <label class="stock-filter-chip">
            <input type="checkbox" class="stock-filter-cb" value="active">
            <span>Active products</span>
            <span class="stock-filter-count count-active">{{ $activeCount }}</span>
        </label>
    </div>

    <div class="prod-body">
        <div class="prod-tree">
            <div class="prod-tree-header"><i class="fa-solid fa-folder-tree"></i> Products</div>
            <ul class="prod-tree-list">
                <li class="prod-tree-item {{ !$selectedBrand ? 'active' : '' }}">
                    <a href="{{ route('stock_index') }}">All Products</a>
                </li>
                @foreach($brand as $b)
                <li class="prod-tree-item {{ $selectedBrand === $b->brand_name ? 'active' : '' }}">
                    <a href="{{ route('stock_index', ['brand' => $b->brand_name]) }}"><i class="fa-regular fa-folder"></i> {{ $b->brand_name }}</a>
                </li>
                @endforeach
            </ul>
        </div>

        <div class="prod-table-wrap">
            <div class="prod-search-row">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input type="text" id="prodQuickSearch" placeholder="Product name">
                <span class="prod-count">Products count: {{ $prod->count() }}</span>
            </div>
            <div class="prod-table-scroll">
                <table id="dtBasicExample" class="table table-striped table-bordered table-sm" cellspacing="0" width="100%">
                    <thead>
                        <tr>
                            <th></th>
                            <th>Code</th>
                            <th>Name</th>
                            <th>Quantity</th>
                            <th>Unit</th>
                            @if(auth()->user()->hasAccess('view_cost_prices'))
                            <th>Cost price</th>
                            <th>Cost</th>
                            <th>Cost incl. tax</th>
                            @endif
                            <th>Sale price</th>
                            <th>Sale total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($prod as $item)
                        @php
                            $status = $item->quantity < 0 ? 'negative' : ($item->quantity == 0 ? 'zero' : 'nonzero');
                            $cost = $item->quantity * $item->cost_price;
                            $saleTotal = $item->quantity * $item->price;
                        @endphp
                        <tr data-status="{{ $status }}" data-product-id="{{ $item->id }}" data-product-name="{{ $item->product_name }}" data-product-quantity="{{ $item->quantity }}" class="stock-row">
                            <td><span class="stock-dot dot-{{ $status }}"></span></td>
                            <td>{{ $item->product_code }}</td>
                            <td>{{ $item->product_name }}</td>
                            <td>{{ $item->quantity }}</td>
                            <td>Qty</td>
                            @if(auth()->user()->hasAccess('view_cost_prices'))
                            <td data-order="{{ $item->cost_price }}">{{ number_format($item->cost_price, 2) }}</td>
                            <td class="stock-cost-cell" data-cost="{{ $cost }}">{{ number_format($cost, 2) }}</td>
                            <td class="stock-cost-tax-cell" data-cost="{{ $cost }}">{{ number_format($cost, 2) }}</td>
                            @endif
                            <td>{{ number_format($item->price, 2) }}</td>
                            <td class="stock-sale-cell" data-sale="{{ $saleTotal }}">{{ number_format($saleTotal, 2) }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="stock-summary">
                @if(auth()->user()->hasAccess('view_cost_prices'))
                <div class="stock-summary-col">
                    <div class="stock-summary-title">Cost price</div>
                    <div class="stock-summary-row"><span>Total cost:</span> <strong id="sumCost">0.00</strong></div>
                    <div class="stock-summary-row"><span>Total cost inc. tax:</span> <strong id="sumCostTax">0.00</strong></div>
                </div>
                @endif
                <div class="stock-summary-col">
                    <div class="stock-summary-title">Sale price</div>
                    <div class="stock-summary-row"><span>Total:</span> <strong id="sumSale">0.00</strong></div>
                    <div class="stock-summary-row"><span>Total inc. tax:</span> <strong id="sumSaleTax">0.00</strong></div>
                </div>
            </div>
        </div>
    </div>
</div>

@endsection
@section('script')
<script>
  $(document).ready(function () {
    var activeFilters = [];

    $.fn.dataTable.ext.search.push(function (settings, data, dataIndex) {
        if (settings.nTable.id !== 'dtBasicExample') return true;
        if (!activeFilters.length) return true;
        if (activeFilters.indexOf('active') !== -1) return true;
        var row = new $.fn.dataTable.Api(settings).row(dataIndex).node();
        var status = $(row).data('status');
        return activeFilters.indexOf(status) !== -1;
    });

    var table = $('#dtBasicExample').DataTable({
        dom: 'rtip',
        paging: true,
        pageLength: 50
    });

    var printPage = 0;
    window.addEventListener('beforeprint', function () { printPage = table.page(); table.page.len(-1).draw(false); });
    window.addEventListener('afterprint', function () { table.page.len(50).draw(false); table.page(printPage).draw(false); });

    function fmt(n) {
        return n.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
    }

    function recalcSummary() {
        var sumCost = 0, sumSale = 0;
        table.rows({search: 'applied'}).every(function () {
            var $row = $(this.node());
            sumCost += parseFloat($row.find('.stock-cost-cell').data('cost')) || 0;
            sumSale += parseFloat($row.find('.stock-sale-cell').data('sale')) || 0;
        });
        $('#sumCost').text(fmt(sumCost));
        $('#sumCostTax').text(fmt(sumCost));
        $('#sumSale').text(fmt(sumSale));
        $('#sumSaleTax').text(fmt(sumSale));
    }

    table.on('draw', recalcSummary);
    recalcSummary();

    $('#prodQuickSearch').on('input', function () {
        table.search(this.value).draw();
    });

    $('.stock-filter-cb').on('change', function () {
        activeFilters = $('.stock-filter-cb:checked').map(function () { return this.value; }).get();
        table.draw();
    });

    $('#refreshBtn').on('click', function () {
        window.location.reload();
    });

    $('#printBtn').on('click', function () {
        window.print();
    });

    $('#addStockBtn').on('click', function () {
        $('#addStockModal').modal('show');
    });

    $('.decorative').on('click', function () {
        toastr.info($(this).data('tip'));
    });

    $(document).on('click', '[data-bs-dismiss="modal"]', function () {
        $(this).closest('.modal').modal('hide');
    });

    // RIGHT-CLICK CONTEXT MENU ON STOCK ROWS
    var $contextMenu = $('#stockContextMenu');
    var contextProductId = null;
    var contextProductName = null;
    var contextProductQuantity = 0;

    $(document).on('contextmenu', '#dtBasicExample tbody tr.stock-row', function (e) {
        e.preventDefault();
        contextProductId = $(this).data('product-id');
        contextProductName = $(this).data('product-name');
        contextProductQuantity = $(this).data('product-quantity');

        $('#stockContextMenuTitle').text(contextProductName);

        var menuWidth = $contextMenu.outerWidth() || 200;
        var menuHeight = $contextMenu.outerHeight() || 100;
        var x = e.pageX;
        var y = e.pageY;
        if (x + menuWidth > $(window).width() + $(window).scrollLeft()) {
            x -= menuWidth;
        }
        if (y + menuHeight > $(window).height() + $(window).scrollTop()) {
            y -= menuHeight;
        }

        $contextMenu.css({ top: y + 'px', left: x + 'px' }).addClass('show');
    });

    $(document).on('click', function () {
        $contextMenu.removeClass('show');
    });

    $contextMenu.on('contextmenu', function (e) {
        e.preventDefault();
    });

    $contextMenu.on('click', 'li[data-action]', function () {
        var action = $(this).data('action');
        if (!contextProductId) return;

        if (action === 'add-stock') {
            $('#addStockModal select[name="product_id"]').val(contextProductId);
            $('#addStockModal').modal('show');
        } else if (action === 'stock-history') {
            window.location.href = '{{ route('stock_history') }}?product_id=' + contextProductId;
        } else if (action === 'quick-update') {
            openQuickStockModal(contextProductId, contextProductName, contextProductQuantity);
        }

        $contextMenu.removeClass('show');
    });

    // QUICK "UPDATE STOCK QUANTITY" KEYPAD
    var quickStockValue = '0';

    function renderQuickStockDisplay() {
        $('#quickStockDisplay').text(quickStockValue);
        $('#quickStockQuantity').val(quickStockValue === '-' || quickStockValue === '' ? '0' : quickStockValue);
    }

    function openQuickStockModal(productId, productName, currentQuantity) {
        $('#quickStockProductId').val(productId);
        $('#quickStockProductName').text(productName);
        quickStockValue = String(currentQuantity);
        renderQuickStockDisplay();
        $('#quickStockModal').modal('show');
    }

    $('#quickStockModal').on('shown.bs.modal', function () {
        var typedYet = false;

        $('.qk-btn[data-key]').off('click.quickStock').on('click.quickStock', function () {
            var key = $(this).data('key');

            if (!typedYet && key !== 'back' && key !== 'esc') {
                quickStockValue = '0';
                typedYet = true;
            }

            if (key === 'esc') {
                return;
            } else if (key === 'back') {
                quickStockValue = quickStockValue.slice(0, -1);
            } else if (key === '-') {
                quickStockValue = quickStockValue.charAt(0) === '-' ? quickStockValue.slice(1) : '-' + quickStockValue;
            } else if (key === '.') {
                // Stock quantities are whole units; decimals are not meaningful here.
            } else {
                quickStockValue = (quickStockValue === '0' ? '' : quickStockValue) + key;
            }

            if (quickStockValue === '' || quickStockValue === '-') {
                quickStockValue = quickStockValue === '-' ? '-' : '0';
            }

            renderQuickStockDisplay();
        });
    });

    function applyStockUpdate(res) {
        table.rows(function(index,data,node){ return String(node.dataset.productId)===String(res.product_id); }).every(function(){
            var $row=$(this.node());
            $row.attr('data-status',res.status).data('status',res.status);
            $row.attr('data-product-quantity',res.quantity).data('product-quantity',res.quantity);
            $row.find('td').eq(3).text(res.quantity);
            $row.find('.stock-dot').attr('class','stock-dot dot-'+res.status);
            if(res.cost!==undefined)$row.find('.stock-cost-cell,.stock-cost-tax-cell').attr('data-cost',res.cost).data('cost',res.cost).text(fmt(res.cost));
            $row.find('.stock-sale-cell').attr('data-sale',res.saleTotal).data('sale',res.saleTotal).text(fmt(res.saleTotal));
            this.invalidate('dom');
        });
        var counts={negative:0,zero:0,nonzero:0,active:0};
        table.rows().every(function(){var qty=Number(this.node().dataset.productQuantity);counts.active++;if(qty<0)counts.negative++;if(qty===0)counts.zero++;if(qty!==0)counts.nonzero++;});
        Object.keys(counts).forEach(function(key){$('.count-'+key).text(counts[key]);});
        $('#addStockForm select[name="product_id"] option').each(function(){if(String(this.value)===String(res.product_id))this.textContent=this.dataset.productName+' ('+this.dataset.productCode+') — '+@json($shopId ? 'current stock' : 'current stock, all shops')+': '+res.quantity;});
        var scroll=window.scrollY;table.draw(false);requestAnimationFrame(function(){window.scrollTo(window.scrollX,scroll);});
    }
    var addingStock=false;
    $('#addStockForm').on('submit',function(e){
        e.preventDefault();if(addingStock||!this.reportValidity())return;
        var form=this,$button=$(form).find('button[type="submit"]'),label=$button.html(),$error=$('#addStockError');
        addingStock=true;$error.prop('hidden',true);$button.prop('disabled',true).text('Saving…');
        fetch(form.action,{method:'POST',headers:{Accept:'application/json'},body:new FormData(form)}).then(function(response){return response.json().then(function(data){return {ok:response.ok,data:data};});}).then(function(result){
            if(!result.ok){var errors=result.data.errors;var message=errors?Object.values(errors).flat().join(' '):(result.data.message||'Could not update stock.');$error.text(message).prop('hidden',false);return;}
            applyStockUpdate(result.data);addingStock=false;$('#addStockModal').modal('hide');
            form.querySelector('[name=request_key]').value=result.data.next_request_key;form.querySelector('[name=quantity]').value='';form.querySelector('[name=reference]').value='';form.querySelector('[name=notes]').value='';
            toastr.success(result.data.message);
        }).catch(function(){ $error.text('Could not confirm the save. Retry the same entry; it will not be added twice.').prop('hidden',false); }).finally(function(){addingStock=false;$button.prop('disabled',false).html(label);});
    });
    $('#addStockModal').on('hide.bs.modal',function(e){if(addingStock)e.preventDefault();});

    var countingStock=false;
    $('#quickStockModal').on('hide.bs.modal',function(e){if(countingStock)e.preventDefault();});
    $('#quickStockForm').on('submit', function (e) {
        e.preventDefault();
        if(countingStock)return;countingStock=true;

        if ($('#quickStockQuantity').val() === '') {
            $('#quickStockQuantity').val('0');
        }

        var productId = $('#quickStockProductId').val();
        var $submitBtn = $(this).find('.qk-enter');
        $submitBtn.prop('disabled', true);

        var formData = new FormData(this);

        fetch(this.action, {
            method: 'POST',
            headers: { 'Accept': 'application/json' },
            body: formData
        }).then(function (resp) {
            return resp.json().then(function (data) {
                return { ok: resp.ok, data: data };
            });
        }).then(function (result) {
            countingStock=false;$submitBtn.prop('disabled', false);

            if (!result.ok) {
                toastr.error(result.data.message || 'Could not update stock quantity.');
                return;
            }

            var res = result.data;
            $('#quickStockModal').modal('hide');

            applyStockUpdate(res);

            toastr.success(res.message);
        }).catch(function () {
            countingStock=false;$submitBtn.prop('disabled', false);
            toastr.error('Could not update stock quantity.');
        });
    });
  });
</script>
@endsection

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/vendor/dataTables.bootstrap5.min.css') }}?v={{ filemtime(public_path('assets/vendor/dataTables.bootstrap5.min.css')) }}">
@endpush
@push('libraries')
<script src="{{ asset('assets/vendor/jquery.dataTables.min.js') }}?v={{ filemtime(public_path('assets/vendor/jquery.dataTables.min.js')) }}"></script>
<script src="{{ asset('assets/vendor/dataTables.bootstrap5.min.js') }}?v={{ filemtime(public_path('assets/vendor/dataTables.bootstrap5.min.js')) }}"></script>
@endpush
