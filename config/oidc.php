<?php

// "Přihlásit přes ONhost ID" – OpenID Connect relying party (kontrakt ONhost ID 2026-10-19).
return [
    'enabled' => (bool) env('ONHOST_OIDC_ENABLED', false),
    'issuer' => rtrim((string) env('ONHOST_OIDC_ISSUER', ''), '/'),
    'client_id' => env('ONHOST_OIDC_CLIENT_ID'),
    'client_secret' => env('ONHOST_OIDC_CLIENT_SECRET'),
    'redirect_uri' => env('ONHOST_OIDC_REDIRECT_URI'),
    'scopes' => 'openid email profile',
    'cache_ttl' => 3600,
    'leeway' => 60,
    'default_role' => 'seeker',
];
