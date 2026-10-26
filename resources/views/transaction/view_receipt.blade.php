@extends('layouts.app')
@push('styles')
<link rel="stylesheet" href="{{asset('assets/css/pos_receipt.css')}}?v={{ filemtime(public_path('assets/css/pos_receipt.css')) }}">
@endpush

@section('content')
@include('layouts.includes.sidebar')
<div class="receipt-page receipt-with-sidebar">
<div class="receipt-actions no-print">
    <a href="{{ route('pos') }}" class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-arrow-left"></i> Back to POS</a>
    <button type="button" class="btn btn-primary btn-sm" onclick="window.print()"><i class="fa fa-print"></i> Print</button>
</div>

@include('receipts.thermal', ['receipt' => $receipt])
</div>
@endsection
