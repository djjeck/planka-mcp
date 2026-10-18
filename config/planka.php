<?php

return [
    'url' => env('PLANKA_URL', 'http://localhost:3000'),

    // Hosts planka_upload_attachment may download a file from when given a url (comma-separated,
    // e.g. "human-dm"). Empty: uploads by URL are refused.
    'upload_url_allowed_hosts' => array_values(array_filter(array_map(
        static fn (string $host): string => strtolower(trim($host)),
        explode(',', (string) env('PLANKA_UPLOAD_URL_ALLOWED_HOSTS', '')),
    ))),
];
