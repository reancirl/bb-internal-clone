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

    // Text messages. 'log' (default) writes them to the log; 'ringcentral'
    // sends through the RingCentral SMS API.
    'sms' => [
        'driver' => env('SMS_DRIVER', 'log'),
    ],

    'ringcentral' => [
        'client_id' => env('RINGCENTRAL_CLIENT_ID'),
        'client_secret' => env('RINGCENTRAL_CLIENT_SECRET'),
        'jwt' => env('RINGCENTRAL_JWT'),
        'from' => env('RINGCENTRAL_FROM'),
        'server_url' => env('RINGCENTRAL_SERVER_URL', 'https://platform.ringcentral.com'),
    ],

    // HubSpot private app. The client secret verifies webhook signatures.
    // The access token reads the contact HubSpot just created.
    'hubspot' => [
        'client_secret' => env('HUBSPOT_CLIENT_SECRET'),
        'access_token' => env('HUBSPOT_ACCESS_TOKEN'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
