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
        <a href="{{ route('user') }}" class="sec-tab">Users</a>
        <a href="{{ route('user_security') }}" class="sec-tab active">Security</a>
    </div>

    <div class="card">
      <div class="card-body">
        <h5>Three simple roles</h5>
        <p class="text-muted">Choose a role when adding or editing a user. These permissions apply automatically.</p>
        <div class="table-responsive">
            <table class="table">
                <thead><tr><th>Role</th><th>What they can do</th></tr></thead>
                <tbody>
                    <tr><th>Cashier</th><td>All POS functions: sell, change quantities and prices, void items and sales, take payments, print receipts, find sales, issue refunds.</td></tr>
                    <tr><th>Manager</th><td>Everything a Cashier can do, plus view and adjust stock.</td></tr>
                    <tr><th>Owner / Admin</th><td>Everything, including products, reports, shops, users, payment types, receipt settings, and backups.</td></tr>
                </tbody>
            </table>
        </div>
        <p class="text-muted">Cashiers and managers work in their assigned shop. Only the Owner / Admin can change roles.</p>
        <a href="{{ route('user') }}" class="btn btn-primary">Manage users and roles</a>
      </div>
    </div>
</div>
</section>
@endsection
