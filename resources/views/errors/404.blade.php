<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Page not found · DEMO RETAIL WORKSHOP</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; background: #f7f8fa; color: #242424; font-family: system-ui, -apple-system, 'Segoe UI', sans-serif; }
        header { padding: 20px 24px; background: #7a1f36; color: white; text-align: center; font-weight: 650; letter-spacing: .04em; }
        main { max-width: 560px; margin: 0 auto; padding: clamp(48px, 12vh, 110px) 24px; text-align: center; }
        .code { margin: 0; color: #7a1f36; font-size: clamp(80px, 20vw, 128px); font-weight: 750; line-height: 1; }
        h1 { font-size: 28px; margin: 24px 0 12px; }
        p { color: #646464; font-size: 16px; line-height: 1.6; }
        nav { display: flex; justify-content: center; flex-wrap: wrap; gap: 12px; margin-top: 28px; }
        a { border: 1px solid #7a1f36; border-radius: 6px; padding: 12px 24px; color: #7a1f36; font-weight: 650; text-decoration: none; }
        a.primary { background: #7a1f36; color: white; }
        a:hover { background: #efe2e6; }
        a.primary:hover { background: #60162a; }
        a:focus-visible { outline: 3px solid #242424; outline-offset: 4px; }
    </style>
</head>
<body>
    <header>DEMO RETAIL WORKSHOP</header>
    <main>
        <p class="code" aria-hidden="true">404</p>
        <h1>Page not found</h1>
        <p>This link may be out of date, or the page may have been removed. You can continue from the POS or sign in again.</p>
        <nav aria-label="Return to the application">
            <a class="primary" href="{{ url('/pos') }}">Go to POS</a>
            <a href="{{ url('/login') }}">Sign in</a>
        </nav>
    </main>
</body>
</html>
