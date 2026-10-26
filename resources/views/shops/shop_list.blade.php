@extends('layouts.app')

@section('content')
@include('layouts.includes.sidebar')
<section class="management-page">
@push('styles')
<link rel="stylesheet" href="{{asset('assets/css/list_customer.css')}}?v={{ filemtime(public_path('assets/css/list_customer.css')) }}">
@endpush
<div class="head">
    <p style="position: relative; bottom: 20px;">Shops</p>
</div>

<div class="all">

    <!-- ADD/EDIT SHOP MODAL -->
    <div class="modal fade" id="shopModal" tabindex="-1" role="dialog" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="shopModalTitle">Add Shop</h5>
            <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
          </div>
          <form id="shopForm" method="POST" action="{{ route('add_shop') }}">
              @csrf
              <div class="modal-body">
                  <div class="form-group mb-3">
                      <label>Shop Name</label>
                      <input type="text" class="form-control" name="name" id="f_name" required>
                  </div>
                  <div class="form-group mb-3">
                      <label>Address</label>
                      <input type="text" class="form-control" name="address" id="f_address">
                  </div>
                  <div class="form-group mb-3">
                      <label>Phone</label>
                      <input type="text" class="form-control" name="phone" id="f_phone">
                  </div>
                  <div class="form-check">
                      <input type="checkbox" class="form-check-input" name="is_active" id="f_active" checked>
                      <label class="form-check-label" for="f_active">Active</label>
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

    <!--DELETE MODAL -->
    <div class="modal fade" id="deleteModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
               <br><br>
            <form action="{{ url('delete-shop')}}" method="POST">
                @csrf
                @method('DELETE')
                <div class="deleteSign"><h1><i class="fa-regular fa-circle-xmark"></i></h1></div>
                <br>
                <div class="confirmation">
                    <h1>Are you sure?</h1>
                    <br>
                    <h6>Do you really want to delete this shop? This <br> process cannot be undone.</h6>
                </div>
                <br><br>
                <input type="hidden" name="delete_shop" id="delete_id">
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" style="position: relative; right: 150px; width: 100px; padding: 10px;" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-danger" style="position: relative; bottom: 2px; right: 110px; width: 100px; padding: 10px;">Delete</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
    <!-- END DELETE MODAL -->

    <div class="card">
      <div class="card-header bg-white">
        <button type="button" class="btn btn-primary" id="addShopBtn"><i class="fa-solid fa-shop"></i> Add Shop</button>
      </div>
      <div class="card-body">
        <table id="dtBasicExample" class="table table-striped table-bordered table-sm" cellspacing="0" width="100%">
          <thead>
            <tr>
              <th class="th-sm" style="width: 30px;">ID</th>
              <th class="th-sm">Name</th>
              <th class="th-sm">Address</th>
              <th class="th-sm">Phone</th>
              <th class="th-sm">Status</th>
              <th class="th-sm" style="width: 180px;">Actions</th>
            </tr>
          </thead>
          <tbody>
               @foreach($shops as $shop)
                    <tr>
                        <td>{{$shop->id}}</td>
                        <td>{{$shop->name}}</td>
                        <td>{{$shop->address ?? '—'}}</td>
                        <td>{{$shop->phone ?? '—'}}</td>
                        <td>
                            @if($shop->is_active)
                                <span class="badge badge-success">Active</span>
                            @else
                                <span class="badge badge-secondary">Inactive</span>
                            @endif
                        </td>
                        <td>
                            <button type="button" class="btn btn-primary btn-sm editbtn"
                                data-id="{{$shop->id}}" data-name="{{$shop->name}}" data-address="{{$shop->address}}"
                                data-phone="{{$shop->phone}}" data-active="{{$shop->is_active ? 1 : 0}}">
                                <i class="fa-solid fa-pen-to-square"></i> Edit
                            </button>
                            <button type="button" value="{{$shop->id}}" class="btn btn-danger btn-sm delbtn"><i class="fa-solid fa-trash"></i> Delete</button>
                        </td>
                    </tr>
               @endforeach
          </tbody>
        </table>
      </div>
    </div>
</div>
</section>
@endsection

@section('script')
<script>
      $(document).ready(function () {
        $('#dtBasicExample').DataTable();
      });
</script>
<script>
  $(document).ready(function(){
    $('#addShopBtn').click(function(){
        $('#shopModalTitle').text('Add Shop');
        $('#shopForm').attr('action', '{{ route("add_shop") }}');
        $('#shopForm')[0].reset();
        $('#f_active').prop('checked', true);
        $('#shopModal').modal('show');
    });

    $(document).on('click', '.editbtn', function(){
        $('#shopModalTitle').text('Edit Shop');
        $('#shopForm').attr('action', '/update-shop/' + $(this).data('id'));
        $('#f_name').val($(this).data('name'));
        $('#f_address').val($(this).data('address'));
        $('#f_phone').val($(this).data('phone'));
        $('#f_active').prop('checked', $(this).data('active') == 1);
        $('#shopModal').modal('show');
    });

    $(document).on('click', '.delbtn', function(){
      var shop = $(this).val();
      $('#deleteModal').modal('show');
      $('#delete_id').val(shop);
    })

    // data-dismiss delegation is unreliable on this page (multiple conflicting
    // Bootstrap/jQuery builds loaded site-wide), so close/X buttons are bound directly.
    $(document).on('click', '[data-bs-dismiss="modal"]', function(){
      $(this).closest('.modal').modal('hide');
    });
  })
</script>
@endsection

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/vendor/dataTables.bootstrap5.min.css') }}?v={{ filemtime(public_path('assets/vendor/dataTables.bootstrap5.min.css')) }}">
@endpush
@push('libraries')
<script src="{{ asset('assets/vendor/jquery.dataTables.min.js') }}?v={{ filemtime(public_path('assets/vendor/jquery.dataTables.min.js')) }}"></script>
<script src="{{ asset('assets/vendor/dataTables.bootstrap5.min.js') }}?v={{ filemtime(public_path('assets/vendor/dataTables.bootstrap5.min.js')) }}"></script>
@endpush
