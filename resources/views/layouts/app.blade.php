<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Table of Specification & Exam Generator | CvSU Naic Faculty')</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Source+Serif+4:wght@500;600;700&family=Inter:wght@400;500;600&family=IBM+Plex+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/tos-theme.css') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <header class="cvsu-header">
        <div class="cvsu-eyebrow">Cavite State University &mdash; Naic Campus &middot; Faculty Portal</div>
        <h1>Table of Specification &amp; Exam Generator</h1>
        <p class="tagline">Bloom's Taxonomy classification and item generation for course assessment.</p>
    </header>
    <div class="cvsu-page">
        @yield('content')
    </div>
</body>
</html>