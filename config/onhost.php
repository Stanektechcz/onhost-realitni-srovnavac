<?php

/*
 * ONhost ID (OIDC) + ONhost credit (ecosystem wallet API, ONhost control plane K2-1).
 * Everything here is OFF by default: with ONHOST_CREDIT_ENABLED=false no route, button or HTTP call exists.
 */
return [
    'credit' => [
        'enabled' => (bool) env('ONHOST_CREDIT_ENABLED', false),
        // the catalogue family this app is registered for in ONhost's ONHOST_WALLET_ECOSYSTEM_APPS (`<client_id>:listing`)
        'family' => env('ONHOST_CREDIT_FAMILY', 'listing'),
        'currency' => 'CZK',
        // bonus credit may pay part of a charge (ONhost decides how much by family); purchased credit always pays 100 %
        'allow_bonus' => (bool) env('ONHOST_CREDIT_ALLOW_BONUS', true),
        'api_url' => rtrim((string) env('ONHOST_API_URL', rtrim((string) env('ONHOST_ID_ISSUER', env('ONHOST_OIDC_ISSUER', '')), '/').'/v1'), '/'),
        'timeout' => (int) env('ONHOST_CREDIT_TIMEOUT', 10),
        'connect_timeout' => (int) env('ONHOST_CREDIT_CONNECT_TIMEOUT', 3),
    ],

    // ONhost ID — this app is a CONFIDENTIAL client (server-side code flow + PKCE, client_secret_post)
    'id' => [
        // bez vlastních ONHOST_ID_* se použije klient přihlášení (ONHOST_OIDC_*) – jedna sada údajů v .env
        'issuer' => rtrim((string) env('ONHOST_ID_ISSUER', env('ONHOST_OIDC_ISSUER', '')), '/'),
        'client_id' => env('ONHOST_ID_CLIENT_ID', env('ONHOST_OIDC_CLIENT_ID')),
        'client_secret' => env('ONHOST_ID_CLIENT_SECRET', env('ONHOST_OIDC_CLIENT_SECRET')),
        'redirect' => env('ONHOST_ID_REDIRECT_URI'), // default: route('onhost.callback')
    ],
];
