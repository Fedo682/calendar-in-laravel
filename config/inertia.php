<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Server Side Rendering
    |--------------------------------------------------------------------------
    |
    | These options configures if and how Inertia uses Server Side Rendering
    | to pre-render each initial request made to your application's pages
    | so that server rendered HTML is delivered for the user's browser.
    |
    | See: https://inertiajs.com/server-side-rendering
    |
    */

    'ssr' => [
        'enabled' => true,
        'url' => 'http://127.0.0.1:13714',
        // 'bundle' => base_path('bootstrap/ssr/ssr.mjs'),

    ],

    /*
    |--------------------------------------------------------------------------
    | Initial Page Element
    |--------------------------------------------------------------------------
    |
    | The installed @inertiajs/react package (v3) reads the initial page data
    | from a <script data-page="..." type="application/json"> element rather
    | than the legacy <div data-page="..."> attribute. This must be true to
    | match the client-side package version.
    |
    */

    'use_script_element_for_initial_page' => true,

    /*
    |--------------------------------------------------------------------------
    | Pages
    |--------------------------------------------------------------------------
    |
    | These options configure how Inertia discovers page components on the
    | filesystem. The paths and extensions are used to locate components
    | when rendering responses and during testing assertions.
    |
    */

    'pages' => [

        'paths' => [
            resource_path('js/pages'),
        ],

        'extensions' => [
            'js',
            'jsx',
            'svelte',
            'ts',
            'tsx',
            'vue',
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Testing
    |--------------------------------------------------------------------------
    |
    | The values described here are used to locate Inertia components on the
    | filesystem. For instance, when using `assertInertia`, the assertion
    | attempts to locate the component as a file relative to the paths.
    |
    */

    'testing' => [

        'ensure_pages_exist' => true,

        // The installed inertia-laravel reads these two keys directly when it
        // builds the testing view finder (ServiceProvider::register binds
        // 'inertia.testing.view-finder' from them). Without them the finder is
        // handed null and every assertInertia()->component(...) call dies with
        // "FileViewFinder::__construct(): Argument #2 ($paths) must be of type
        // array, null given". They mirror the 'pages' block above, which is
        // the shape a later package release moves to.
        'page_paths' => [
            resource_path('js/pages'),
        ],

        'page_extensions' => [
            'js',
            'jsx',
            'svelte',
            'ts',
            'tsx',
            'vue',
        ],

    ],

];
