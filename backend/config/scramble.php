<?php

use Dedoc\Scramble\Http\Middleware\RestrictedDocsAccess;

return [
    /*
     * Your API path. By default, all routes starting with this path will be added to the docs.
     */
    'api_path' => 'api',

    /*
     * Your API domain. By default, app domain is used.
     */
    'api_domain' => null,

    /*
     * The path where your OpenAPI specification will be exported.
     */
    'export_path' => 'api.json',

    'info' => [
        /*
         * API version.
         */
        'version' => '1.0.0',

        /*
         * Description rendered on the docs page. Supports Markdown.
         */
        'description' => 'Digibase BaaS Platform — Auto-generated API Documentation',
    ],

    /*
     * Customize Stoplight Elements UI
     */
    'ui' => [
        /*
         * Define the title of the documentation's website.
         */
        'title' => 'Digibase API Docs',

        /*
         * Define the theme of the documentation. Available: "light", "dark".
         */
        'theme' => 'dark',

        /*
         * Hide the "Try It" feature. Enabled by default.
         */
        'hide_try_it' => false,

        /*
         * URL to an image that will be used as a logo in the top left corner.
         */
        'logo' => '',
    ],

    /*
     * The list of servers of the API. By default, when the list is empty, the current host will be used.
     */
    'servers' => [],

    'middleware' => [
        'web',
        // RestrictedDocsAccess::class,  // Commented out = public access
    ],
];
