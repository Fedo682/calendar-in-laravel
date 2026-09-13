<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title inertia>{{ config('app.name', 'Laravel') }}</title>

        {{-- THEME SCRIPT INSERTION POINT (design system phase) --}}

        {{-- Instrument Sans, self-hosted by the bunny() fonts plugin in
             vite.config.ts. Without this directive the plugin still builds the
             @font-face CSS but nothing ever links it. Binding the family to
             Tailwind's --font-sans belongs to the design system phase, which
             owns resources/css/app.css. --}}
        @fonts

        <!-- Scripts -->
        @routes
        @viteReactRefresh
        {{-- The @inertiajs/vite plugin emits one chunk per page and records it
             in the manifest under its source path, so the page the server is
             about to render can be fetched in parallel with the entry instead
             of waiting on it. Every page under resources/js/pages is .tsx. --}}
        @vite(['resources/css/app.css', 'resources/js/app.tsx', "resources/js/pages/{$page['component']}.tsx"])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>
