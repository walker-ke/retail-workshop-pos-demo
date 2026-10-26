@extends('layouts.app')
@section('content')
@include('layouts.includes.sidebar')
@push('styles')
<link rel="stylesheet" href="{{ asset('assets/css/receipt-settings.css') }}?v={{ filemtime(public_path('assets/css/receipt-settings.css')) }}">
@endpush
<div class="receipt-settings-panel">
    <h1>Receipt settings</h1>
    <p>Only the administrator can change these details. Each shop has its own receipt settings.</p>
    <form method="GET" action="{{ route('receipt_settings') }}" class="mb-4">
        <label for="receiptShop">Shop</label>
        <select id="receiptShop" name="shop_id" class="form-select" onchange="this.form.submit()">
            @foreach($shops as $choice)
                <option value="{{ $choice->id }}" @selected($choice->id === $shop->id)>{{ $choice->name }}</option>
            @endforeach
        </select>
    </form>
    @if($errors->any())
        <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif
    <form method="POST" action="{{ route('update_receipt_settings', $shop->id) }}">
        @csrf
        <div class="receipt-settings-fields">
            @foreach(['name' => 'Business name', 'phone' => 'Telephone', 'address' => 'Address / town', 'pin' => 'Business PIN (optional)', 'paybill' => 'Paybill', 'account_number' => 'Account number', 'till_number' => 'Till / terminal number', 'footer' => 'Thank-you message'] as $field => $label)
                @php
                    $default = match($field) { 'name' => 'DEMO RETAIL WORKSHOP', 'footer' => 'THANK YOU FOR SHOPPING WITH US', 'till_number' => str_pad((string) $shop->id, 2, '0', STR_PAD_LEFT), default => '' };
                @endphp
                <div>
                    <label for="receipt_{{ $field }}">{{ $label }}</label>
                    <input type="text" class="form-control" id="receipt_{{ $field }}" name="receipt_{{ $field }}" value="{{ old('receipt_'.$field, $shop->{'receipt_'.$field} ?: $default) }}" maxlength="255" @required($field === 'name')>
                </div>
            @endforeach
        </div>
        <p class="small text-muted mt-3">Blank optional fields are left off the receipt. Changes apply to new sales; completed sales retain their saved receipt details and cashier name.</p>
        <button class="btn btn-primary" type="submit">Save receipt settings</button>
    </form>
</div>
@endsection
