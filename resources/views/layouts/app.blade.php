<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Table of Specification & Exam Generator | CvSU Naic Faculty')</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Source+Serif+4:wght@500;600;700&family=Inter:wght@400;500;600&family=IBM+Plex+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/tos-theme.css') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('styles')
    <style>
        /* Campus photo behind the header, CvSU green as a translucent
           overlay so text stays legible over any part of the photo. */
        .cvsu-header {
            position: relative;
            background-image:
                linear-gradient(120deg, rgba(31, 71, 45, 0.92), rgba(28, 92, 56, 0.85)),
                url('{{ asset('images/cvsu-naic-campus.jpg') }}');
            background-size: cover;
            background-position: center;
        }
        .cvsu-header-inner {
            display: flex;
            align-items: center;
            gap: 1.1rem;
            max-width: 1100px;
        }
        .cvsu-header-logo {
            width: 64px;
            height: 64px;
            object-fit: contain;
            flex-shrink: 0;
            filter: drop-shadow(0 2px 6px rgba(0,0,0,0.35));
        }
    </style>
</head>
<body>
    <header class="cvsu-header">
        <div class="cvsu-header-inner">
            <img src="{{ asset('images/cvsu-logo.jpg') }}" alt="Cavite State University seal" class="cvsu-header-logo">
            <div>
                <div class="cvsu-eyebrow">Cavite State University &mdash; Naic Campus &middot; Faculty Portal</div>
                <h1>Table of Specification &amp; Exam Generator</h1>
                <p class="tagline">Bloom's Taxonomy classification and item generation for course assessment.</p>
            </div>
        </div>
    </header>
    <div class="cvsu-page">
        @yield('content')
    </div>
    @stack('scripts')
</body>
</html>