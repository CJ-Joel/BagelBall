<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <title>Bagel Ball Pre-Games</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <style>
        body {
            font-size: clamp(14px, 2vw, 16px);
            -webkit-touch-callout: none;
        }
        @media (max-width: 640px) {
            input, button, a {
                font-size: 16px;
            }
        }
    </style>
</head>
<body class="bg-gray-900 text-white min-h-screen">
    @yield('content')
</body>
</html>
