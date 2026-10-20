<?php

return [
    'domain' => env('TAKT_DOMAIN', ''),
    // Où sont collectés les événements. L'origine du service
    // ("https://taktlytics.com") comme l'URL complète de collecte
    // ("https://taktlytics.com/api/event") sont acceptées et donnent le même
    // résultat : le snippet navigateur et l'envoi serveur-à-serveur reçoivent
    // chacun la forme dont ils ont besoin. Une valeur portant un autre chemin
    // (proxy première-partie, ex. "/collect") est utilisée telle quelle.
    'endpoint' => env('TAKT_ENDPOINT', 'https://taktlytics.com'),
    // First-party origin to serve the tracker + derive the endpoint from
    // ({origin}/api/event): a custom domain you proxy through to dodge
    // ad-blockers (endpoint wins over it).
    'script_origin' => env('TAKT_SCRIPT_ORIGIN'),
    'api_key' => env('TAKT_API_KEY'),
    'mode' => env('TAKT_MODE', 'inline'),
    'outbound' => env('TAKT_OUTBOUND', false),
    'files' => env('TAKT_FILES', false),
    'tagged' => env('TAKT_TAGGED', false),
    'not_found' => env('TAKT_NOT_FOUND', false),
    // Restrict download tracking to these extensions (comma-separated env, e.g.
    // "pdf,zip,docx"). Empty leaves the tracker's built-in extension list.
    'file_extensions' => array_filter(array_map('trim', explode(',', (string) env('TAKT_FILE_EXTENSIONS', '')))),
    'exclude_localhost' => env('TAKT_EXCLUDE_LOCALHOST', true),
    // CSP nonce for the inline <script>. A CSP nonce is request-scoped: set a
    // static one here only if your policy uses one, otherwise rebind the
    // SnippetRenderer per request with a fresh nonce.
    'nonce' => env('TAKT_NONCE'),

    // --- Advanced options ---------------------------------------------------
    // Leave unset (null) to keep the tracker defaults; only a non-default value
    // is rendered.
    //
    // Sample a fraction of pageviews/events, e.g. 0.5 keeps ~50%.
    'sample_rate' => env('TAKT_SAMPLE_RATE'),
    // Keep the raw query string + hash in tracked URLs (default strips them).
    'track_query' => env('TAKT_TRACK_QUERY'),
    // Allowlist of query params to keep when track_query is off (comma-separated
    // env, e.g. "utm_source,utm_medium").
    'query_params' => array_filter(array_map('trim', explode(',', (string) env('TAKT_QUERY_PARAMS', '')))),
    // Path prefixes never tracked (comma-separated env, e.g. "/app,/account").
    // Requires mode=sdk. The minimal snippet can't express it.
    'exclude' => array_filter(array_map('trim', explode(',', (string) env('TAKT_EXCLUDE', '')))),
    // Set to false to stop honoring the browser Do-Not-Track header.
    'respect_dnt' => env('TAKT_RESPECT_DNT'),
    // Kill-switch: set to false to disable tracking entirely.
    'enabled' => env('TAKT_ENABLED'),
    // Raw JS function to rewrite URLs before they are sent, e.g.
    // "(u) => u.split('#')[0]". Requires mode=sdk and is DEV-CONTROLLED ONLY:
    // it is injected verbatim into the page; never build it from user input.
    'scrub_url' => env('TAKT_SCRUB_URL'),
    'redact_routes' => array_filter(array_map('trim', explode(',', (string) env('TAKT_REDACT_ROUTES', '')))),
    'route_templates' => (bool) env('TAKT_ROUTE_TEMPLATES', false),
    'redact_undecodable_paths' => filter_var(env('TAKT_REDACT_UNDECODABLE_PATHS', true), FILTER_VALIDATE_BOOLEAN),
];
