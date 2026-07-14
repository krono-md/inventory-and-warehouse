<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1.0" />
        <title>@yield('title', 'Nexora')</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
        @stack('head')
        <style>
            *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
            body { font-family: 'Inter', sans-serif; background: #132b52; color: #fff; min-height: 100vh; }
            ::-webkit-scrollbar { width: 5px; } ::-webkit-scrollbar-track { background: #0b1e3d; } ::-webkit-scrollbar-thumb { background: #1b3a6b; border-radius: 4px; }
        </style>
        @stack('styles')
    </head>
    <body>
        @include('partials.header')
        <div id="main">
            <div class="sidebar-overlay" onclick="closeSidebarMobile()"></div>
            @include('partials.sidebar')
            <div id="page-content">
                @yield('content')
            </div>
        </div>
        @include('partials.sidebar-scripts')
        @stack('scripts')
    </body>
</html>
