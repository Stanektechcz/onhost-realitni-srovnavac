<?php

return [
    // ONhost ID (OIDC, Authorization Code + PKCE). Vypnuto (fail-closed), dokud vlastník nedodá klientské údaje.
    'enabled' => (bool) env('ONHOST_OIDC_ENABLED', false),
    'issuer' => rtrim((string) env('ONHOST_OIDC_ISSUER', 'https://id.onhost.cz'), '/'),
    'client_id' => env('ONHOST_OIDC_CLIENT_ID'),
    'client_secret' => env('ONHOST_OIDC_CLIENT_SECRET'),
    // Prázdné = /auth/onhost/callback na APP_URL. Musí odpovídat registraci klienta u id.onhost.cz.
    'redirect_uri' => env('ONHOST_OIDC_REDIRECT_URI'),
    'scopes' => env('ONHOST_OIDC_SCOPES', 'openid email profile'),
    'discovery_ttl' => 3600,
    'leeway' => 60,
    'timeout' => 10,
];
