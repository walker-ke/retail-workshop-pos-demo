@extends('layouts.app')

@section('content')
@include('layouts.includes.sidebar')
<section class="management-page">
@push('styles')
<link rel="stylesheet" href="{{asset('assets/css/list_customer.css')}}?v={{ filemtime(public_path('assets/css/list_customer.css')) }}">
@endpush
<div class="head">
    <p style="position: relative; bottom: 20px;">Payment Types</p>
</div>

<div class="all">

    <!-- ADD/EDIT PAYMENT TYPE MODAL -->
    <div class="modal fade" id="paymentTypeModal" tabindex="-1" role="dialog" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="paymentTypeModalTitle">New Payment Type</h5>
            <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
          </div>
          <form id="paymentTypeForm" method="POST" action="{{ route('add_payment_type') }}">
              @csrf
              <div class="modal-body">
                  <div class="form-group mb-3">
                      <label>Name</label>
                      <input type="text" class="form-control" name="name" id="f_name" required>
                  </div>
                  <div class="form-row">
                      <div class="form-group col-md-6 mb-3">
                          <label>Position</label>
                          <input type="number" min="0" class="form-control" name="position" id="f_position" value="0">
                      </div>
                      <div class="form-group col-md-6 mb-3">
                          <label>Code <i class="fa-solid fa-circle-question" title="Optional short code, e.g. for exports/integrations"></i></label>
                          <input type="text" class="form-control" name="code" id="f_code">
                      </div>
                  </div>
                  <div class="form-check">
                      <input type="checkbox" class="form-check-input" name="enabled" id="f_enabled" checked>
                      <label class="form-check-label" for="f_enabled">Enabled <small class="text-muted">&mdash; shown as an option on the POS payment screen</small></label>
                  </div>
                  <div class="form-check">
                      <input type="checkbox" class="form-check-input" name="quick_payment" id="f_quick_payment">
                      <label class="form-check-label" for="f_quick_payment">Quick payment</label>
                  </div>
                  <div class="form-check">
                      <input type="checkbox" class="form-check-input" name="customer_required" id="f_customer_required">
                      <label class="form-check-label" for="f_customer_required">Customer required <small class="text-muted">&mdash; a Walk-in sale can't use this payment type</small></label>
                  </div>
                  <div class="form-check">
                      <input type="checkbox" class="form-check-input" name="change_allowed" id="f_change_allowed">
                      <label class="form-check-label" for="f_change_allowed">Change allowed <small class="text-muted">&mdash; shows Tendered Amount / Change on the POS payment screen</small></label>
                  </div>
                  <div class="form-check">
                      <input type="checkbox" class="form-check-input" name="mark_transaction_as_paid" id="f_mark_transaction_as_paid" checked>
                      <label class="form-check-label" for="f_mark_transaction_as_paid">Mark transaction as paid</label>
                  </div>
                  <div class="form-check">
                      <input type="checkbox" class="form-check-input" name="print_receipt" id="f_print_receipt" checked>
                      <label class="form-check-label" for="f_print_receipt">Print receipt</label>
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
            <form action="{{ url('delete-payment-type')}}" method="POST">
                @csrf
                @method('DELETE')
                <div class="deleteSign"><h1><i class="fa-regular fa-circle-xmark"></i></h1></div>
                <br>
                <div class="confirmation">
                    <h1>Are you sure?</h1>
                    <br>
                    <h6>Do you really want to delete this payment type? This <br> process cannot be undone.</h6>
                </div>
                <br><br>
                <input type="hidden" name="delete_payment_type" id="delete_id">
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
        <button type="button" class="btn btn-primary" id="addPaymentTypeBtn"><i class="fa-solid fa-plus"></i> New Payment Type</button>
      </div>
      <div class="card-body">
        <table id="dtBasicExample" class="table table-striped table-bordered table-sm" cellspacing="0" width="100%">
          <thead>
            <tr>
              <th class="th-sm">Name</th>
              <th class="th-sm">Position</th>
              <th class="th-sm">Code</th>
              <th class="th-sm">Enabled</th>
              <th class="th-sm">Quick payment</th>
              <th class="th-sm">Customer required</th>
              <th class="th-sm">Change allowed</th>
              <th class="th-sm">Mark as paid</th>
              <th class="th-sm">Print receipt</th>
              <th class="th-sm" style="width: 180px;">Actions</th>
            </tr>
          </thead>
          <tbody>
               @foreach($paymentTypes as $pt)
                    <tr>
                        <td>{{$pt->name}}</td>
                        <td>{{$pt->position}}</td>
                        <td>{{$pt->code ?? '—'}}</td>
                        <td>{!! $pt->enabled ? '<i class="fa-solid fa-check text-success"></i>' : '' !!}</td>
                        <td>{!! $pt->quick_payment ? '<i class="fa-solid fa-check text-success"></i>' : '' !!}</td>
                        <td>{!! $pt->customer_required ? '<i class="fa-solid fa-check text-success"></i>' : '' !!}</td>
                        <td>{!! $pt->change_allowed ? '<i class="fa-solid fa-check text-success"></i>' : '' !!}</td>
                        <td>{!! $pt->mark_transaction_as_paid ? '<i class="fa-solid fa-check text-success"></i>' : '' !!}</td>
                        <td>{!! $pt->print_receipt ? '<i class="fa-solid fa-check text-success"></i>' : '' !!}</td>
                        <td>
                            <button type="button" class="btn btn-primary btn-sm editbtn"
                                data-id="{{$pt->id}}" data-name="{{$pt->name}}" data-position="{{$pt->position}}" data-code="{{$pt->code}}"
                                data-enabled="{{$pt->enabled ? 1 : 0}}" data-quick-payment="{{$pt->quick_payment ? 1 : 0}}"
                                data-customer-required="{{$pt->customer_required ? 1 : 0}}" data-change-allowed="{{$pt->change_allowed ? 1 : 0}}"
                                data-mark-as-paid="{{$pt->mark_transaction_as_paid ? 1 : 0}}" data-print-receipt="{{$pt->print_receipt ? 1 : 0}}">
                                <i class="fa-solid fa-pen-to-square"></i> Edit
                            </button>
                            <button type="button" value="{{$pt->id}}" class="btn btn-danger btn-sm delbtn"><i class="fa-solid fa-trash"></i> Delete</button>
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
    $('#addPaymentTypeBtn').click(function(){
        $('#paymentTypeModalTitle').text('New Payment Type');
        $('#paymentTypeForm').attr('action', '{{ route("add_payment_type") }}');
        $('#paymentTypeForm')[0].reset();
        $('#f_position').val(0);
        $('#f_enabled').prop('checked', true);
        $('#f_mark_transaction_as_paid').prop('checked', true);
        $('#f_print_receipt').prop('checked', true);
        $('#paymentTypeModal').modal('show');
    });

    $(document).on('click', '.editbtn', function(){
        $('#paymentTypeModalTitle').text('Edit Payment Type');
        $('#paymentTypeForm').attr('action', '/update-payment-type/' + $(this).data('id'));
        $('#f_name').val($(this).data('name'));
        $('#f_position').val($(this).data('position'));
        $('#f_code').val($(this).data('code'));
        $('#f_enabled').prop('checked', $(this).data('enabled') == 1);
        $('#f_quick_payment').prop('checked', $(this).data('quick-payment') == 1);
        $('#f_customer_required').prop('checked', $(this).data('customer-required') == 1);
        $('#f_change_allowed').prop('checked', $(this).data('change-allowed') == 1);
        $('#f_mark_transaction_as_paid').prop('checked', $(this).data('mark-as-paid') == 1);
        $('#f_print_receipt').prop('checked', $(this).data('print-receipt') == 1);
        $('#paymentTypeModal').modal('show');
    });

    $(document).on('click', '.delbtn', function(){
      var id = $(this).val();
      $('#deleteModal').modal('show');
      $('#delete_id').val(id);
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
