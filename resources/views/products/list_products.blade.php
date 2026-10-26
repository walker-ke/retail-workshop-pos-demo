@extends('layouts.app')

@section('content')
@include('layouts.includes.sidebar')
@push('styles')
<link rel="stylesheet" href="{{asset('assets/css/list_products.css')}}?v={{ filemtime(public_path('assets/css/list_products.css')) }}">
@endpush

<!-- IMPORT PRODUCTS MODAL -->
<div class="modal fade" id="importModal" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Import Products</h5>
        <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
      </div>
      <form action="{{ url('import-products') }}" method="POST" enctype="multipart/form-data">
        @csrf
        <div class="modal-body">
            <p class="text-muted" style="font-size: 13px;">
                Upload a CSV with <strong>Code</strong> and <strong>Name</strong> columns (required), plus optional
                <strong>Group</strong>, <strong>Cost Price</strong>, <strong>Sale Price</strong> and <strong>Quantity</strong> columns.
                A product whose Code already exists is updated; any other Code is created as a new product. A Group that doesn't exist yet is created automatically.
            </p>
            <a href="{{ route('import_products_template') }}" class="btn btn-sm btn-outline-secondary mb-3"><i class="fa-solid fa-download"></i> Download CSV template</a>
            @if(!$shopId)
            <div class="form-group mb-3">
                <label>Shop (the Quantity column applies to this shop)</label>
                <select class="form-control" name="shop_id" required>
                    <option value="" disabled selected>Select Shop</option>
                    @foreach($shops as $shop)
                        <option value="{{$shop->id}}">{{$shop->name}}</option>
                    @endforeach
                </select>
            </div>
            @endif
            <div class="form-group mb-3">
                <label>CSV File</label>
                <input type="file" class="form-control" name="import_file" accept=".csv,text/csv" required>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            <button type="submit" class="btn btn-primary">Import</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- NEW PRODUCT MODAL -->
<div class="modal fade" id="newProductModal" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">New Product</h5>
        <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
      </div>
      <form action="{{ url('add-product') }}" method="POST">
        @csrf
        <div class="modal-body">
            <div class="form-group mb-3">
                <label>Product Name</label>
                <input type="text" class="form-control" name="product_name" placeholder="Enter product name" required>
            </div>
            <div class="form-group mb-3">
                <label>Group</label>
                <select name="brand" class="form-control" required>
                    <option value="" disabled selected>Choose group...</option>
                    @foreach($brand as $b)
                    <option value="{{ $b->brand_name }}">{{ $b->brand_name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-row">
                <div class="form-group col-md-6 mb-3">
                    <label>Product Code</label>
                    <input type="text" class="form-control" name="product_code" placeholder="Enter product code" required>
                </div>
                <div class="form-group col-md-6 mb-3">
                    <label>Initial Quantity</label>
                    <input type="number" class="form-control" name="quantity" min="0" value="0" required>
                </div>
            </div>
            @if(!$shopId)
            <div class="form-group mb-3">
                <label>Shop (this product's initial stock belongs to)</label>
                <select class="form-control" name="shop_id" required>
                    <option value="" disabled selected>Select Shop</option>
                    @foreach($shops as $shop)
                        <option value="{{$shop->id}}">{{$shop->name}}</option>
                    @endforeach
                </select>
            </div>
            @endif
            <div class="form-row">
                @if(auth()->user()->hasAccess('view_cost_prices'))
                <div class="form-group col-md-6 mb-3">
                    <label>Cost Price</label>
                    <input type="number" step="0.01" class="form-control" name="cost_price" min="0" value="0">
                </div>
                @endif
                <div class="form-group col-md-6 mb-3">
                    <label>Sale Price</label>
                    <input type="number" step="0.01" class="form-control" name="price" min="0" value="0" required>
                </div>
            </div>
            <div class="form-group mb-3">
                <label>Description</label>
                <textarea class="form-control" name="description" rows="3" placeholder="Enter product description"></textarea>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            <button type="submit" class="btn btn-primary">Save</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- NEW GROUP MODAL -->
<div class="modal fade" id="newGroupModal" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">New Group</h5>
        <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
      </div>
      <form action="{{ url('add-brand') }}" method="POST">
        @csrf
        <div class="modal-body">
            <div class="form-group mb-3">
                <label>Group Name</label>
                <input type="text" class="form-control" name="brand_name" placeholder="Enter group name" required>
            </div>
            <div class="form-group mb-3">
                <label>Manufacturer</label>
                <input type="text" class="form-control" name="manufacturer" placeholder="Enter manufacturer" required>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            <button type="submit" class="btn btn-primary">Save</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- EDIT GROUP MODAL -->
<div class="modal fade" id="editGroupModal" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Edit Group</h5>
        <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
      </div>
      <form action="{{ url('edit-brand') }}" method="POST">
        @csrf
        @method('PUT')
        <input type="hidden" name="brand_id" id="group_id">
        <div class="modal-body">
            <div class="form-group mb-3">
                <label>Group Name</label>
                <input type="text" class="form-control" name="brand_name" id="group_name" required>
            </div>
            <div class="form-group mb-3">
                <label>Manufacturer</label>
                <input type="text" class="form-control" name="manufacturer" id="group_manufacturer" required>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            <button type="submit" class="btn btn-primary">Save</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- DELETE GROUP MODAL -->
<div class="modal fade" id="deleteGroupModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
           <br><br>
        <form action="{{ url('delete-brand') }}" method="POST">
            @csrf
            @method('DELETE')
            <div class="deleteSign"><h1><i class="fa-regular fa-circle-xmark"></i></h1></div>
            <br>
            <div class="confirmation">
                <h1>Are you sure?</h1>
                <br>
                <h6>Do you really want to delete this group? This <br> process cannot be undone.</h6>
            </div>
            <br><br>
            <input type="hidden" name="delete_brand" id="delete_group_id">
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" style="position: relative; right: 150px; width: 100px; padding: 10px;" data-bs-dismiss="modal">Close</button>
                <button type="submit" class="btn btn-danger" style="position: relative; bottom: 2px; right: 110px; width: 100px; padding: 10px;">Delete</button>
            </div>
        </form>
        </div>
    </div>
</div>

<!-- DELETE PRODUCT MODAL -->
<div class="modal fade" id="deleteModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
           <br><br>
        <form action="{{ url('delete-product') }}" method="POST">
            @csrf
            @method('DELETE')
            <div class="deleteSign"><h1><i class="fa-regular fa-circle-xmark"></i></h1></div>
            <br>
            <div class="confirmation">
                <h1>Are you sure?</h1>
                <br>
                <h6>Do you really want to delete this product? This <br> process cannot be undone.</h6>
            </div>
            <br><br>
            <input type="hidden" name="delete_product" id="delete_id">
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" style="position: relative; right: 150px; width: 100px; padding: 10px;" data-bs-dismiss="modal">Close</button>
                <button type="submit" class="btn btn-danger" style="position: relative; bottom: 2px; right: 110px; width: 100px; padding: 10px;">Delete</button>
            </div>
        </form>
        </div>
    </div>
</div>

<div class="prod-shell">
    <div class="prod-toolbar">
        <div class="prod-tool-row">
            <div class="prod-tool-group">
                <button type="button" class="prod-tool" id="refreshBtn"><i class="fa-solid fa-arrows-rotate"></i><span>Refresh</span></button>
            </div>
            <div class="prod-tool-sep"></div>
            @if(auth()->user()->hasAccess('products'))
            <div class="prod-tool-group">
                <button type="button" class="prod-tool" id="newGroupBtn"><i class="fa-solid fa-folder-plus"></i><span>New group</span></button>
                <button type="button" class="prod-tool" id="editGroupBtn"><i class="fa-solid fa-pen"></i><span>Edit group</span></button>
                <button type="button" class="prod-tool" id="deleteGroupBtn"><i class="fa-solid fa-folder-minus"></i><span>Delete group</span></button>
            </div>
            <div class="prod-tool-sep"></div>
            <div class="prod-tool-group">
                <button type="button" class="prod-tool" id="newProductBtn"><i class="fa-solid fa-plus"></i><span>New product</span></button>
                <button type="button" class="prod-tool" id="editProductBtn"><i class="fa-solid fa-pen-to-square"></i><span>Edit product</span></button>
                <button type="button" class="prod-tool" id="deleteProductBtn"><i class="fa-solid fa-trash"></i><span>Delete product</span></button>
            </div>
            <div class="prod-tool-sep"></div>
            @endif
            <div class="prod-tool-group">
                <button type="button" class="prod-tool" id="printBtn"><i class="fa-solid fa-print"></i><span>Print</span></button>
                @if(auth()->user()->hasAccess('edit_prices'))
                <button type="button" class="prod-tool" id="priceLockToggle" aria-pressed="false" aria-label="Unlock price editing" title="Unlock price editing"><i class="fa-solid fa-lock" aria-hidden="true"></i><span>Prices locked</span></button>
                @endif
            </div>
            <div class="prod-tool-sep"></div>
            @if(auth()->user()->hasAccess('products'))
            <div class="prod-tool-group">
                <button type="button" class="prod-tool" id="importBtn"><i class="fa-solid fa-file-import"></i><span>Import</span></button>
                <a href="{{ route('export_products', $selectedBrand ? ['brand' => $selectedBrand] : []) }}" class="prod-tool"><i class="fa-solid fa-file-export"></i><span>Export</span></a>
            </div>
            <div class="prod-tool-sep"></div>
            @endif
            <div class="prod-tool-group">
                @include('partials.shop_picker', ['wrapClass' => 'prod-tool'])
            </div>
        </div>
    </div>

    <div class="prod-body">
        <div class="prod-tree">
            <div class="prod-tree-header"><i class="fa-solid fa-folder-tree"></i> Products</div>
            <ul class="prod-tree-list">
                <li class="prod-tree-item {{ !$selectedBrand ? 'active' : '' }}">
                    <a href="{{ route('list_product') }}">All Products</a>
                </li>
                @foreach($brand as $b)
                <li class="prod-tree-item {{ $selectedBrand === $b->brand_name ? 'active' : '' }}"
                    data-id="{{ $b->id }}" data-name="{{ $b->brand_name }}" data-manufacturer="{{ $b->manufacturer }}">
                    <a href="{{ route('list_product', ['brand' => $b->brand_name]) }}"><i class="fa-regular fa-folder"></i> {{ $b->brand_name }}</a>
                </li>
                @endforeach
            </ul>
        </div>

        <div class="prod-table-wrap">
            <div class="prod-search-row">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input type="text" id="prodQuickSearch" placeholder="Product name">
                <span class="prod-count">Products count: {{ $product }}</span>
            </div>
            <div class="prod-table-scroll">
                <table id="dtBasicExample" class="table table-striped table-bordered table-sm" cellspacing="0" width="100%">
                    <thead>
                        <tr>
                            <th>Code</th>
                            <th>Name</th>
                            <th>Group</th>
                            <th>Barcode</th>
                            @if(auth()->user()->hasAccess('view_cost_prices'))
                            <th>Cost</th>
                            @endif
                            <th>Sale price excl.</th>
                            <th>Taxes</th>
                            <th>Sale price incl.</th><th class="no-print">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($prod as $item)
                        <tr class="prod-row" data-id="{{ $item->id }}" data-edit-url="{{ URL::to('edit-product/'.$item->id) }}" data-price="{{ $item->price }}" data-name="{{ $item->product_name }}">
                            <td>{{ $item->product_code }}</td>
                            <td>{{ $item->product_name }}</td>
                            <td>{{ $item->brand }}</td>
                            <td>{{ $item->product_code }}</td>
                            @if(auth()->user()->hasAccess('view_cost_prices'))
                            <td>{{ number_format($item->cost_price, 2) }}</td>
                            @endif
                            <td class="product-sale-price" data-order="{{ $item->price }}">{{ number_format($item->price, 2) }}</td>
                            <td>&mdash;</td>
                            <td class="product-sale-price" data-order="{{ $item->price }}">{{ number_format($item->price, 2) }}</td>
                            <td class="no-print"><button type="button" class="btn btn-sm btn-outline-primary edit-price-btn" disabled title="Unlock price editing in the toolbar first" data-url="{{ route('products.update-price',$item->id) }}" aria-label="Edit price for {{ $item->product_name }}">Edit price</button></td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="productPriceModal" tabindex="-1" aria-labelledby="productPriceTitle" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><div class="modal-header"><h2 class="modal-title fs-5" id="productPriceTitle">Edit selling price</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div><form id="productPriceForm" method="POST">@csrf<input type="hidden" name="expected_price"><div class="modal-body"><p id="productPriceName" class="fw-bold"></p><p class="text-muted">Selling price applies to all shops. Previous sales keep their recorded prices.</p><div id="productPriceError" class="alert alert-danger" role="alert" hidden></div><label for="productPriceValue">Selling price (KES)</label><input id="productPriceValue" class="form-control" type="number" name="price" min="0" max="2147483647" step="1" required></div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Save price</button></div></form></div></div></div>
@endsection
@section('script')
<script>
  $(document).ready(function () {
    var table = $('#dtBasicExample').DataTable({
        dom: 'lrtip',
        columnDefs: [{targets:-1,orderable:false,searchable:false}]
    });

    var savingPrice=false,priceProductId=null,pricesUnlocked=false;
    function applyPriceLock(){
        $(table.rows().nodes()).find('.edit-price-btn').prop('disabled',!pricesUnlocked).attr('title',pricesUnlocked?'Edit selling price':'Unlock price editing in the toolbar first');
        $('#priceLockToggle').attr('aria-pressed',String(pricesUnlocked)).attr('aria-label',pricesUnlocked?'Lock price editing':'Unlock price editing').attr('title',pricesUnlocked?'Lock price editing':'Unlock price editing');
        $('#priceLockToggle span').text(pricesUnlocked?'Prices unlocked':'Prices locked');
        $('#priceLockToggle i').toggleClass('fa-lock',!pricesUnlocked).toggleClass('fa-lock-open',pricesUnlocked);
    }
    $('#priceLockToggle').on('click',function(){
        if(savingPrice)return;
        pricesUnlocked=!pricesUnlocked;
        if(!pricesUnlocked)$('#productPriceModal').modal('hide');
        applyPriceLock();
    });
    table.on('draw',applyPriceLock);
    applyPriceLock();
    $(document).on('click','.edit-price-btn',function(e){
        e.stopPropagation();if(savingPrice||!pricesUnlocked)return;
        var row=this.closest('tr'),form=document.getElementById('productPriceForm');priceProductId=row.dataset.id;
        form.action=this.dataset.url;form.elements.price.value=row.dataset.price;form.elements.expected_price.value=row.dataset.price;
        $('#productPriceName').text(row.dataset.name);$('#productPriceError').prop('hidden',true);$('#productPriceModal').modal('show');
    });
    $('#productPriceModal').on('shown.bs.modal',function(){document.getElementById('productPriceValue').focus();document.getElementById('productPriceValue').select();}).on('hide.bs.modal',function(e){if(savingPrice)e.preventDefault();});
    $('#productPriceForm').on('submit',function(e){
        e.preventDefault();if(savingPrice||!pricesUnlocked||!this.reportValidity())return;
        savingPrice=true;var button=this.querySelector('button[type=submit]');button.disabled=true;button.textContent='Saving…';$('#productPriceError').prop('hidden',true);
        fetch(this.action,{method:'POST',headers:{Accept:'application/json'},body:new FormData(this)}).then(function(response){return response.json().then(function(data){return {ok:response.ok,data:data};});}).then(function(result){
            if(!result.ok){$('#productPriceError').text(result.data.errors?Object.values(result.data.errors).flat().join(' '):(result.data.message||'Could not save price.')).prop('hidden',false);return;}
            var scroll=window.scrollY;
            table.rows(function(index,data,node){return node.dataset.id===String(result.data.product_id);}).every(function(){var row=this.node();row.dataset.price=result.data.price;$(row).find('.product-sale-price').attr('data-order',result.data.price).text(Number(result.data.price).toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2}));this.invalidate('dom');});
            table.draw(false);savingPrice=false;$('#productPriceModal').modal('hide');requestAnimationFrame(function(){window.scrollTo(window.scrollX,scroll);});toastr.success(result.data.message);
        }).catch(function(){$('#productPriceError').text('Could not confirm the save. Your price is still here; check the connection and try again.').prop('hidden',false);}).finally(function(){savingPrice=false;button.disabled=false;button.textContent='Save price';});
    });
    $('#prodQuickSearch').on('input' , function () {
        table.search(this.value).draw();
    });

    $('#refreshBtn').on('click', function () {
        window.location.reload();
    });

    $('#printBtn').on('click', function () {
        window.print();
    });

    $('#newGroupBtn').on('click', function () {
        $('#newGroupModal').modal('show');
    });

    $('#newProductBtn').on('click', function () {
        $('#newProductModal').modal('show');
    });

    $('#importBtn').on('click', function () {
        $('#importModal').modal('show');
    });

    $('.decorative').on('click', function () {
        toastr.info($(this).data('tip'));
    });

    // PRODUCT ROW SELECTION
    $(document).on('click', '.prod-row', function () {
        $('.prod-row').removeClass('prod-picked');
        $(this).addClass('prod-picked');
    });

    $('#editProductBtn').on('click', function () {
        var $sel = $('.prod-row.prod-picked');
        if (!$sel.length) {
            toastr.warning('Select a product in the table first.');
            return;
        }
        window.location.href = $sel.data('edit-url');
    });

    $('#deleteProductBtn').on('click', function () {
        var $sel = $('.prod-row.prod-picked');
        if (!$sel.length) {
            toastr.warning('Select a product in the table first.');
            return;
        }
        $('#delete_id').val($sel.data('id'));
        $('#deleteModal').modal('show');
    });

    // GROUP (BRAND) SELECTION IS DRIVEN BY THE ACTIVE TREE ITEM
    $('#editGroupBtn').on('click', function () {
        var $active = $('.prod-tree-item.active[data-id]');
        if (!$active.length) {
            toastr.warning('Select a group in the tree first.');
            return;
        }
        $('#group_id').val($active.data('id'));
        $('#group_name').val($active.data('name'));
        $('#group_manufacturer').val($active.data('manufacturer'));
        $('#editGroupModal').modal('show');
    });

    $('#deleteGroupBtn').on('click', function () {
        var $active = $('.prod-tree-item.active[data-id]');
        if (!$active.length) {
            toastr.warning('Select a group in the tree first.');
            return;
        }
        $('#delete_group_id').val($active.data('id'));
        $('#deleteGroupModal').modal('show');
    });

    // data-dismiss delegation is unreliable on this page (multiple conflicting
    // Bootstrap/jQuery builds loaded site-wide), so close/X buttons are bound directly.
    $(document).on('click', '[data-bs-dismiss="modal"]', function () {
        $(this).closest('.modal').modal('hide');
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
