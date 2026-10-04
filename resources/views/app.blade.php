<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    {{-- Link previews (WhatsApp, Telegram…) are built from this HTML, before any JavaScript runs. --}}
    @if ($og = $page['props']['og'] ?? null)
        <title>{{ $og['title'] }}</title>
        <meta name="robots" content="noindex, nofollow">
        <meta property="og:type" content="website">
        <meta property="og:title" content="{{ $og['title'] }}">
        <meta property="og:description" content="{{ $og['description'] }}">
        @if ($og['image'])
            <meta property="og:image" content="{{ $og['image'] }}">
        @endif
    @endif

    @viteReactRefresh
    @vite(['resources/js/app.jsx', "resources/js/Pages/{$page['component']}.jsx"])
    @inertiaHead
</head>
<body class="min-h-screen bg-surface text-ink antialiased">
    @inertia
</body>
</html>
