<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title inertia>{{ config('app.name', 'Laravel') }}</title>

        {{-- Blocking on purpose: this has to stamp the theme class onto <html>
             before the first paint, or the page flashes the wrong appearance
             while the bundle downloads. Inline and tiny for the same reason -
             an external file would cost another round trip.

             The test is inverted relative to the usual snippet because this app
             is dark by default: under 'system' we go dark unless the OS asks
             for light outright, so a client reporting no preference at all
             still lands on the intended default. --}}
        <script>
            (function () {
                try {
                    var stored = localStorage.getItem('theme') || 'system';
                    var dark = stored === 'dark' || (stored === 'system' &&
                        !window.matchMedia('(prefers-color-scheme: light)').matches);
                    document.documentElement.classList.add(dark ? 'dark' : 'light');
                } catch (e) {
                    // Safari in private mode throws on localStorage. :root
                    // already defaults to dark, so doing nothing is correct.
                }
            })();
        </script>

        {{-- Instrument Sans, self-hosted by the bunny() fonts plugin in
             vite.config.ts. Without this directive the plugin still builds the
             @font-face CSS but nothing ever links it. The family is bound to
             Tailwind's --font-sans in resources/css/app.css. --}}
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
