@extends('layouts.app')

@section('content')
@include('layouts.includes.sidebar')
<section class="management-page">
@push('styles')
<link rel="stylesheet" href="{{asset('assets/css/list_customer.css')}}?v={{ filemtime(public_path('assets/css/list_customer.css')) }}">
@endpush
@push('styles')
<link rel="stylesheet" href="{{asset('assets/css/security.css')}}?v={{ filemtime(public_path('assets/css/security.css')) }}">
@endpush
<div class="head">
    <p style="position: relative; bottom: 20px;">Users &amp; Security</p>
</div>

<div class="all">
    <div class="sec-tabs">
        <a href="{{ route('user') }}" class="sec-tab active">Users</a>
        <a href="{{ route('user_security') }}" class="sec-tab">Security</a>
    </div>

    <!-- ADD/EDIT USER MODAL -->
    <div class="modal fade" id="userModal" tabindex="-1" role="dialog" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="userModalTitle">Add User</h5>
            <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
          </div>
          <form id="userForm" method="POST" action="{{ route('add_user') }}">
              @csrf
              <input type="hidden" name="_method" id="formMethod" value="POST">
              <div class="modal-body">
                  <div class="form-group mb-3">
                      <label>Name</label>
                      <input type="text" class="form-control" name="name" id="f_name" required>
                  </div>
                  <div class="form-group mb-3">
                      <label>Username <i class="fa-solid fa-circle-question" title="Lets this user log in without an email - handy for a temp worker account you'll delete later"></i></label>
                      <input type="text" class="form-control" name="username" id="f_username" placeholder="e.g. temp1">
                  </div>
                  <div class="form-group mb-3">
                      <label>Email Address</label>
                      <input type="email" class="form-control" name="email" id="f_email" placeholder="Optional if a username is set">
                  </div>
                  <small class="text-muted d-block mb-3" style="margin-top: -12px;">At least one of Username or Email is required.</small>
                  <div class="form-group mb-3" id="passwordGroup">
                      <label>Password</label>
                      <input type="password" class="form-control" name="password" minlength="6" maxlength="72" placeholder="At least 6 characters" id="f_password">
                  </div>
                  <div class="form-group mb-3">
                      <label for="f_access_level">Role</label>
                      <select class="form-control" name="access_level" id="f_access_level" required>
                          @foreach(\App\Models\User::roles() as $level => $label)
                              <option value="{{ $level }}">{{ $label }}</option>
                          @endforeach
                      </select>
                  </div>
                  <div class="form-group mb-3">
                      <label>Shop</label>
                      <select class="form-control" name="shop_id" id="f_shop_id">
                          <option value="">— All shops (owner) —</option>
                          @foreach($shops as $shop)
                              <option value="{{ $shop->id }}">{{ $shop->name }}</option>
                          @endforeach
                      </select>
                  </div>
                  <div class="form-check">
                      <input type="checkbox" class="form-check-input" name="active" id="f_active" checked>
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

    <!-- RESET PASSWORD MODAL -->
    <div class="modal fade" id="resetModal" tabindex="-1" role="dialog" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Reset Password</h5>
            <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
          </div>
          <form id="resetForm" method="POST" action="">
              @csrf
              <div class="modal-body">
                  <div class="form-group mb-3">
                      <label>New Password</label>
                      <input type="password" class="form-control" name="password" minlength="6" maxlength="72" placeholder="At least 6 characters" required>
                  </div>
                  <div class="form-group mb-3">
                      <label>Confirm Password</label>
                      <input type="password" class="form-control" name="password_confirmation" required>
                  </div>
              </div>
              <div class="modal-footer">
                  <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                  <button type="submit" class="btn btn-primary">Reset Password</button>
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
            <form action="{{ url('delete-user')}}" method="POST">
                @csrf
                @method('DELETE')
                <div class="deleteSign"><h1><i class="fa-regular fa-circle-xmark"></i></h1></div>
                <br>
                <div class="confirmation">
                    <h1>Are you sure?</h1>
                    <br>
                    <h6>Do you really want to delete this User? This <br> process cannot be undone.</h6>
                </div>
                <br><br>
                <input type="hidden" name="delete_user" id="delete_id">
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
        <button type="button" class="btn btn-primary" id="addUserBtn"><i class="fa-solid fa-user-plus"></i> Add User</button>
      </div>
      <div class="card-body">
        <table id="dtBasicExample" class="table table-striped table-bordered table-sm" cellspacing="0" width="100%">
          <thead>
            <tr>
              <th class="th-sm" style="width: 30px;">ID</th>
              <th class="th-sm">Name</th>
              <th class="th-sm">Username</th>
              <th class="th-sm">Email</th>
              <th class="th-sm">Role</th>
              <th class="th-sm">Shop</th>
              <th class="th-sm">Active</th>
              <th class="th-sm" style="width: 260px;">Actions</th>
            </tr>
          </thead>
          <tbody>
               @foreach($user as $us)
                    <tr>
                        <td>{{$us->id}}</td>
                        <td>{{$us->name}}</td>
                        <td>{{$us->username ?? '—'}}</td>
                        <td>{{$us->email ?? '—'}}</td>
                        <td>
                            {{ $us->roleLabel() }}
                        </td>
                        <td>{{ $us->shop->name ?? 'All shops' }}</td>
                        <td>
                            @if($us->active)
                                <span class="badge badge-success">Active</span>
                            @else
                                <span class="badge badge-secondary">Inactive</span>
                            @endif
                        </td>
                        <td>
                            <button type="button" class="btn btn-primary btn-sm editbtn"
                                data-id="{{$us->id}}" data-name="{{$us->name}}" data-username="{{$us->username}}" data-email="{{$us->email}}"
                                data-level="{{$us->access_level}}" data-shop-id="{{$us->shop_id}}" data-active="{{$us->active ? 1 : 0}}">
                                <i class="fa-solid fa-pen-to-square"></i> Edit
                            </button>
                            <button type="button" class="btn btn-warning btn-sm resetbtn" data-id="{{$us->id}}">
                                <i class="fa-solid fa-key"></i> Reset Password
                            </button>
                            <button type="button" value="{{$us->id}}" class="btn btn-danger btn-sm delbtn"><i class="fa-solid fa-trash"></i> Delete</button>
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
        $('.dataTables_length').addClass('bs-select');
      });
</script>
<script>
  $(document).ready(function(){
    function updateShopRequirement() {
        var owner = $('#f_access_level').val() === '9';
        $('#f_shop_id').prop('required', !owner);
        $('#f_shop_id option[value=""]').prop('disabled', !owner);
        if (!owner && !$('#f_shop_id').val()) {
            $('#f_shop_id').val($('#f_shop_id option[value!=""]').first().val());
        }
    }
    $('#f_access_level').on('change', updateShopRequirement);
    $('#addUserBtn').click(function(){
        $('#userModalTitle').text('Add User');
        $('#userForm').attr('action', '{{ route("add_user") }}');
        $('#formMethod').val('POST');
        $('#userForm')[0].reset();
        $('#f_access_level').val(0);
        $('#f_shop_id').val('');
        $('#passwordGroup').show();
        $('#f_password').attr('required', true);
        updateShopRequirement();
        $('#userModal').modal('show');
    });

    $(document).on('click', '.editbtn', function(){
        $('#userModalTitle').text('Edit User');
        $('#userForm').attr('action', '/update-user/' + $(this).data('id'));
        $('#formMethod').val('POST');
        $('#f_name').val($(this).data('name'));
        $('#f_username').val($(this).data('username'));
        $('#f_email').val($(this).data('email'));
        $('#f_access_level').val($(this).data('level'));
        $('#f_shop_id').val($(this).data('shop-id') ?? '');
        $('#f_active').prop('checked', $(this).data('active') == 1);
        $('#passwordGroup').hide();
        $('#f_password').removeAttr('required');
        updateShopRequirement();
        $('#userModal').modal('show');
    });

    $(document).on('click', '.resetbtn', function(){
        $('#resetForm').attr('action', '/reset-password/' + $(this).data('id'));
        $('#resetModal').modal('show');
    });

    $(document).on('click', '.delbtn', function(){
      var user = $(this).val();
      $('#deleteModal').modal('show');
      $('#delete_id').val(user);
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
