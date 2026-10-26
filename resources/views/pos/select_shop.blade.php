@extends('layouts.app')

@section('content')
<div class="pos-navigation">
    @include('layouts.includes.sidebar')
</div>
<div style="padding: 56px 20px 0;">
    @include('pos.shop_selector')
</div>
<div class="d-flex flex-column align-items-center justify-content-center text-center" style="min-height: 50vh; padding: 20px;">
    <i class="fa-solid fa-shop fa-3x text-muted mb-3"></i>
    <h4>Select a Shop to Start Selling</h4>
    <p class="text-muted" style="max-width: 420px;">
        You are viewing <strong>All Shops (combined)</strong>. Sales can't be made from the combined view
        because a sale belongs to one shop’s stock. Choose a shop above to start selling.
    </p>
</div>
@endsection
