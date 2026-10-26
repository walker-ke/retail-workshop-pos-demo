<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- CSRF Token -->
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>DEMO RETAIL WORKSHOP POS</title>
    @if(Route::is('login'))
        @vite(['resources/sass/login.scss', 'resources/js/login.js'])
    @else
        @vite(['resources/sass/app.scss', 'resources/js/app.js'])
    @endif
    <link rel="preload" href="{{ asset('assets/vendor/webfonts/fa-solid-900.woff2') }}" as="font" type="font/woff2" crossorigin>
    @stack('styles')
    <link rel="stylesheet" href="{{ asset('assets/css/responsive.css') }}?v={{ filemtime(public_path('assets/css/responsive.css')) }}">
    @if(Route::is('pos', 'stock_index', 'list_product') || session()->has('error') || session()->has('success'))
        <link rel="stylesheet" href="{{ asset('assets/vendor/toastr.min.css') }}?v={{ filemtime(public_path('assets/vendor/toastr.min.css')) }}">
    @endif
</head>
<body>
@if(config('demo.enabled'))<div style="position:fixed;top:60px;left:0;right:0;z-index:1050;background:#fff3cd;color:#533f03;padding:6px 12px;text-align:center;font-size:12px;line-height:1.4">Portfolio demo · Fictional records only · Owner is read-only · No payments are sent</div>@endif



    <div id="app">
        <div class="sidebar-overlay" id="sidebarOverlay"></div>
        <nav class="navbar navbar-expand-md  shadow-sm">
            <div class="container">
                <button class="navbar-toggler sidebar-toggle-btn" type="button" id="sidebarToggleBtn" aria-label="Toggle menu" aria-expanded="false">
                    <i class="fa-solid fa-bars"></i>
                </button>
                <div class="navlogo">
                    <a href="{{ route(auth()->check() && auth()->user()->hasAccess('dashboard') ? 'home' : 'pos') }}" class="nav-link">DEMO RETAIL WORKSHOP</a>
                </div>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="{{ __('Toggle navigation') }}">
                    <span class="navbar-toggler-icon"></span>
                </button>

                <div class="collapse navbar-collapse" id="navbarSupportedContent">
                    <!-- Left Side Of Navbar -->
                    <ul class="navbar-nav me-auto">

                    </ul>

                    <!-- Right Side Of Navbar -->
                    <ul class="navbar-nav ms-auto">
                        <!-- Authentication Links -->
                        @guest
                            @if (Route::has('login'))
                                <li class="nav-item">
                                    <a class="nav-link" href="{{ route('login') }}">{{ __('Login') }}</a>
                                </li>
                            @endif
                        @else
                            <li class="nav-item dropdown">
                                <a id="navbarDropdown"  class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false" v-pre>
                                        <i class="fa-solid fa-user"></i> {{ Auth::user()->name }}
                                </a>

                                <div class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarDropdown">
                                    <a class="dropdown-item" href="{{ route('logout') }}"
                                       onclick="event.preventDefault();
                                                     document.getElementById('logout-form').submit();">
                                        {{ __('Logout') }}
                                    </a>

                                    <form id="logout-form" action="{{ route('logout') }}" method="POST" class="d-none">
                                        @csrf
                                    </form>
                                </div>
                            </li>
                        @endguest
                    </ul>
                </div>
            </div>
        </nav>

        <main class="py-4">
            @if(!empty($backupAttention) && !Route::is('backup'))
                <div class="management-page pb-0"><div class="alert alert-danger" role="alert">Backup needs attention. <a href="{{ route('backup') }}">Open Backups & Recovery</a></div></div>
            @endif
            @yield('content')
        </main>
        @include('sweetalert::alert')
    </div>
    @if(!Route::is('login', 'home', 'backup', 'receipt_settings', 'view_receipt', 'verification.notice') || session()->has('error') || session()->has('success'))
    <script src="{{ asset('assets/vendor/jquery-3.7.1.min.js') }}?v={{ filemtime(public_path('assets/vendor/jquery-3.7.1.min.js')) }}"></script>
    @endif
    @if(Route::is('pos', 'stock_index', 'list_product') || session()->has('error') || session()->has('success'))
        <script src="{{ asset('assets/vendor/toastr.min.js') }}?v={{ filemtime(public_path('assets/vendor/toastr.min.js')) }}"></script>
    @endif
    @stack('libraries')
    <script>
        // One default for every paginated table in the application.
        if (window.jQuery && jQuery.fn.dataTable) {
            jQuery.extend(jQuery.fn.dataTable.defaults, { pageLength: 50 });
        }
    </script>
    <!-- TOAST FOR ERROR NOTIFICATION -->
        @if(Session::has('error'))
        <script>

            toastr.options = {
                "closeButton" : true,
            }
            toastr.error(@json(Session::get('error')));
        </script>
        @endif
    <!-- TOAST FOR ERROR NOTIFICATION END -->
    <!-- TOAST FOR SUCCESS NOTIFICATION -->
    @if(Session::has('success'))
        <script>
            toastr.options = {
                "closeButton" : true,
            }
            toastr.success(@json(Session::get('success')))

        </script>
    @endif
    <!-- TOAST FOR SUCCESS NOTIFICATION END -->
    <!-- MOBILE SIDEBAR TOGGLE (plain JS, independent of whichever jQuery build loaded last) -->
    <script>
        (function() {
            var toggleBtn = document.getElementById('sidebarToggleBtn');
            var overlay = document.getElementById('sidebarOverlay');
            var sidebar = document.querySelector('.side-bar');
            if (!toggleBtn || !sidebar) return;

            function closeSidebar() {
                sidebar.classList.remove('mobile-open');
                overlay.classList.remove('show');
                toggleBtn.setAttribute('aria-expanded', 'false');
            }

            toggleBtn.addEventListener('click', function() {
                sidebar.classList.toggle('mobile-open');
                overlay.classList.toggle('show');
                toggleBtn.setAttribute('aria-expanded', String(sidebar.classList.contains('mobile-open')));
            });

            overlay.addEventListener('click', closeSidebar);
            document.addEventListener('keydown', function(event) { if (event.key === 'Escape') { closeSidebar(); toggleBtn.focus(); } });

            sidebar.querySelectorAll('.menu > .item > a:not(.sub-btn)').forEach(function(link) {
                link.addEventListener('click', closeSidebar);
            });
        })();
    </script>

    <!-- SIDEBAR SUBMENU TOGGLE (plain JS; lives here in the base layout instead of a
         page-level script section, so it can never collide with one) -->
    <script>
        document.querySelectorAll('.sub-btn').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var subMenu = btn.nextElementSibling;
                if (subMenu) subMenu.classList.toggle('show');
                var dropdown = btn.querySelector('.dropdown');
                if (dropdown) dropdown.classList.toggle('rotate');
            });
        });
    </script>

    <!-- PASSWORD VISIBILITY TOGGLE (plain JS; wraps every type="password" field
         app-wide with a show/hide eye button, so no per-form markup is needed) -->
    <script>
        document.querySelectorAll('input[type="password"]').forEach(function(input) {
            var wrap = document.createElement('span');
            wrap.className = 'pw-toggle-wrap';
            input.parentNode.insertBefore(wrap, input);
            wrap.appendChild(input);

            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'pw-toggle-btn';
            btn.setAttribute('aria-label', 'Show password');
            btn.innerHTML = '<i class="fa-solid fa-eye"></i>';
            wrap.appendChild(btn);

            btn.addEventListener('click', function() {
                var showing = input.type === 'text';
                input.type = showing ? 'password' : 'text';
                btn.innerHTML = showing ? '<i class="fa-solid fa-eye"></i>' : '<i class="fa-solid fa-eye-slash"></i>';
                btn.setAttribute('aria-label', showing ? 'Show password' : 'Hide password');
            });
        });
    </script>
@yield('script')
</body>
</html>
