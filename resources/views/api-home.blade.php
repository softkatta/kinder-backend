<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $appName }}</title>
    <style>
        body {
            margin: 0;
            min-height: 100vh;
            display: grid;
            place-items: center;
            font-family: system-ui, sans-serif;
            background: #f4f6f5;
            color: #1a1f1c;
        }
        main {
            text-align: center;
            padding: 2rem;
        }
        h1 { font-size: 1.25rem; font-weight: 600; margin: 0 0 .5rem; }
        p { margin: 0; color: #5b655e; font-size: .95rem; }
        a {
            display: inline-block;
            margin-top: 1.25rem;
            color: #0f5c4c;
            font-weight: 600;
            text-decoration: none;
        }
        a:hover { text-decoration: underline; }
    </style>
</head>
<body>
<main>
    <h1>{{ $appName }}</h1>
    <p>This is the API server. The website is not served from here.</p>
    @if ($frontend)
        <a href="{{ $frontend }}">Open website → {{ parse_url($frontend, PHP_URL_HOST) ?: $frontend }}</a>
    @endif
</main>
</body>
</html>
