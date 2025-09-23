<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'ReleaseIt.ai') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/auth.js'])

        <style>
            .auth-card {
                background: rgba(9, 9, 11, 0.8);
                border: 1px solid #27272A;
                border-radius: 16px;
                backdrop-filter: blur(12px);
            }
            .purple-gradient-button {
                background: linear-gradient(135deg, #884DFF 0%, #9333EA 100%);
                box-shadow: 0 4px 15px rgba(136, 77, 255, 0.3);
            }
            .purple-gradient-button:hover {
                background: linear-gradient(135deg, #9333EA 0%, #A855F7 100%);
                box-shadow: 0 6px 20px rgba(136, 77, 255, 0.4);
            }
            body {
                background: #090909;
            }
        </style>
    </head>
    <body class="font-sans antialiased" style="background: #090909; min-height: 100vh;">
        <div class="min-h-screen bg-gradient-to-br from-primary/5 via-background to-accent/5 flex items-center justify-center p-4" >
            <!-- Auth Form Card -->
            <div id="app">
                @yield('content')
            </div>
        </div>
    </body>
</html>