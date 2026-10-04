{{-- Standalone error layout: no database queries, so it still renders when the app is broken. --}}
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <title>@yield('title') - {{ config('app.name') }}</title>
    <style>
        :root {
            --first-color: #0d9488;
            --title-color: rgba(24, 24, 27, 1);
            --text-color: rgb(55 65 81 / 75%);
            --bg-color: #f7f7f7;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px 16px;
            background: var(--bg-color);
            color: var(--title-color);
            font-family: 'IBM Plex Sans Arabic', 'Segoe UI', Tahoma, system-ui, sans-serif;
            text-align: center;
        }
        .box { max-width: 460px; }
        .logo { display: inline-flex; align-items: center; gap: 8px; margin-bottom: 32px; color: var(--title-color); font-size: 1.25rem; font-weight: 700; text-decoration: none; }
        .code {
            font-size: 5rem;
            font-weight: 700;
            line-height: 1;
            color: var(--first-color);
            margin-bottom: 16px;
        }
        h1 { font-size: 1.5rem; margin-bottom: 12px; }
        p { color: var(--text-color); line-height: 1.8; margin-bottom: 28px; }
        .btn {
            display: inline-block;
            padding: 10px 28px;
            border-radius: 8px;
            background: var(--first-color);
            color: #fff;
            text-decoration: none;
            font-weight: 600;
        }
        .btn:hover { opacity: .9; }
    </style>
</head>
<body>
    <main class="box">
        <a class="logo" href="{{ url('/') }}">{{ config('app.name') }}</a>
        <div class="code">@yield('code')</div>
        <h1>@yield('title')</h1>
        <p>@yield('message')</p>
        <a class="btn" href="@yield('action_url', url('/'))">@yield('action', 'العودة إلى الرئيسية')</a>
    </main>
</body>
</html>
