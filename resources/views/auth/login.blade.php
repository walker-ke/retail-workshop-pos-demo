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
        <div class="auth-heading">{{ __('Sign in to try the demo') }}</div>

        @if(config('demo.enabled'))
        <div style="font-size:13px;background:#f6f2f3;padding:12px;border-radius:8px;margin:16px 0;line-height:1.6">
            <strong>Try a sample account</strong><br>
            Usernames: <code>cashier</code>, <code>manager</code>, or <code>owner</code> (read-only).<br>
            Password: <code>DemoWorkshop2026!</code><br>
            Use sample information only. Everyone shares these demo records.
        </div>
        @endif
        <form method="POST" action="{{ route('login') }}">
            @csrf

            <div class="auth-form-group">
                <label for="login">{{ __('Username or Email') }}</label>
                <div class="auth-input-group">
                    <i class="fa-solid fa-user"></i>
                    <input id="login" type="text" class="@error('login') is-invalid @enderror" name="login" value="{{ old('login') }}" required autocomplete="username" autofocus placeholder="username or you@example.com">
                </div>
                @error('login')
                    <span class="auth-error">{{ $message }}</span>
                @enderror
            </div>

            <div class="auth-form-group">
                <label for="password">{{ __('Password') }}</label>
                <div class="auth-input-group auth-password-group">
                    <input id="password" type="password" class="@error('password') is-invalid @enderror" name="password" required autocomplete="current-password" placeholder="Enter your password">
                </div>
                @error('password')
                    <span class="auth-error">{{ $message }}</span>
                @enderror
            </div>

            <div class="auth-remember">
                <input type="checkbox" name="remember" id="remember" {{ old('remember') ? 'checked' : '' }}>
                <label for="remember">{{ __('Remember Me') }}</label>
            </div>

            <button type="submit" class="auth-submit"><i class="fa-solid fa-right-to-bracket"></i> {{ __('Sign in') }}</button>


        </form>
    </div>
</div>
@endsection
