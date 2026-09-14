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

             Paper is the design and paper is light, so 'system' follows the OS
             the ordinary way round: dark only when the OS asks for dark. A
             client that reports no preference at all gets paper, which is also
             what :root carries. --}}
        <script>
            (function () {
                try {
                    var stored = localStorage.getItem('theme') || 'system';
                    var dark = stored === 'dark' || (stored === 'system' &&
                        window.matchMedia('(prefers-color-scheme: dark)').matches);
                    document.documentElement.classList.add(dark ? 'dark' : 'light');
                } catch (e) {
                    // Safari in private mode throws on localStorage. :root
                    // already carries the paper palette, so doing nothing is
                    // correct.
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
