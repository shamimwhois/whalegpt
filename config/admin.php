<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Administrators
    |--------------------------------------------------------------------------
    |
    | The email addresses allowed into the admin panel, given as a
    | comma-separated list in WHALE_ADMIN_EMAILS and matched case-insensitively
    | against the signed-in user. Empty by default, which means nobody reaches
    | /admin until at least one address is listed: an unset allowlist must fail
    | closed rather than open.
    |
    */

    'emails' => array_values(array_filter(array_map(
        static fn (string $email): string => strtolower(trim($email)),
        explode(',', (string) env('WHALE_ADMIN_EMAILS', '')),
    ))),

];
