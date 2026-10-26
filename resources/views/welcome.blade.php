<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Welcome · DEMO RETAIL WORKSHOP</title>
    <link rel="stylesheet" href="{{ asset('assets/vendor/fontawesome.css') }}?v={{ filemtime(public_path('assets/vendor/fontawesome.css')) }}">
    <link rel="stylesheet" href="{{ asset('assets/css/welcome.css') }}?v={{ filemtime(public_path('assets/css/welcome.css')) }}">
</head>
<body>
    <main class="welcome">
        <header class="brand"><span class="brand-icon"><i class="fa-solid fa-shirt" aria-hidden="true"></i></span><span>DEMO RETAIL <small>WORKSHOP</small></span></header>
        <div class="welcome-content">
            <section class="entry" aria-labelledby="entry-title">
                <span class="entry-icon"><i class="fa-solid fa-shirt" aria-hidden="true"></i></span>
                <h2 id="entry-title">Welcome to DEMO RETAIL</h2>
                <p>A retail and workshop portfolio demo.<br>Explore shops, stock, sales and production with fictional records.</p>
                @auth
                    <a class="enter" href="{{ route(auth()->user()->hasAccess('dashboard') ? 'home' : 'pos') }}">Open your workspace <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>
                @else
                    <a class="enter" href="{{ route('login') }}">Sign in <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>
                @endauth
                <div class="entry-note"><i class="fa-solid fa-lock" aria-hidden="true"></i> Synthetic data · No real payments</div>
            </section>
        </div>
        <footer>DEMO RETAIL WORKSHOP <span>Point of Sale</span></footer>
    </main>
</body>
</html>
