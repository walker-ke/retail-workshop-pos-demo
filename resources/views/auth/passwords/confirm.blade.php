@extends('layouts.app')

@section('content')
@push('styles')
<link rel="stylesheet" href="{{asset('assets/css/auth.css')}}?v={{ filemtime(public_path('assets/css/auth.css')) }}">
@endpush
<div class="auth-wrapper">
    <div class="auth-card">
        <div class="auth-brand">
            <div class="auth-brand-icon"><i class="fa-solid fa-shirt"></i></div>
            <div class="auth-brand-title">DEMO RETAIL WORKSHOP</div>
            <div class="auth-brand-subtitle">POINT OF SALE</div>
        </div>
        <div class="auth-heading">{{ __('Confirm Password') }}</div>

        <p class="auth-note">{{ __('Please confirm your password before continuing.') }}</p>

        <form method="POST" action="{{ route('password.confirm') }}">
            @csrf

            <div class="auth-form-group">
                <label for="password">{{ __('Password') }}</label>
                <div class="auth-input-group">
                    <i class="fa-solid fa-lock"></i>
                    <input id="password" type="password" class="@error('password') is-invalid @enderror" name="password" required autocomplete="current-password" placeholder="••••••••">
                </div>
                @error('password')
                    <span class="auth-error">{{ $message }}</span>
                @enderror
            </div>

            <button type="submit" class="auth-submit"><i class="fa-solid fa-lock"></i> {{ __('Confirm Password') }}</button>


        </form>
    </div>
</div>
@endsection
