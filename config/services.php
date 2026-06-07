<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'pushover' => [
        'token' => env('PUSHOVER_APP_TOKEN'),
    ],

    /*
    |--------------------------------------------------------------------------
    | OpenID Connect (OIDC)
    |--------------------------------------------------------------------------
    |
    | Configure PriceBuddy as an OIDC client (relying party). Set OIDC_BASE_URL
    | to your identity provider's base URL (discovery document is fetched from
    | {base_url}/.well-known/openid-configuration). Leave blank to disable OIDC.
    |
    | OIDC_ADMIN_GROUP: if set, OIDC users whose groups claim contains this value
    | are granted admin access (manage users + global settings). Requires the IdP
    | to emit a groups claim; see docs/oidc.md for IdP-specific setup.
    |
    */

    'oidc' => [
        'base_url'       => env('OIDC_BASE_URL'),
        'client_id'      => env('OIDC_CLIENT_ID'),
        'client_secret'  => env('OIDC_CLIENT_SECRET'),
        'redirect'       => env('OIDC_REDIRECT_URI'),
        'verify_jwt'     => env('OIDC_VERIFY_JWT', true),
        'jwt_public_key' => env('OIDC_JWT_PUBLIC_KEY'),
        'scopes'         => explode(' ', (string) env('OIDC_SCOPES', 'openid profile email')),
        'admin_group'    => env('OIDC_ADMIN_GROUP'),
        'groups_claim'   => env('OIDC_GROUPS_CLAIM', 'groups'),
    ],

];
