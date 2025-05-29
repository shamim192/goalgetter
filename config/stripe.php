<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Stripe API Keys
    |--------------------------------------------------------------------------
    |
    | Your Stripe secret key, publishable key, client ID, and other values
    | required to integrate Stripe with your application.
    |
    */
    'secret_key' => env('STRIPE_SECRET', ''),
    'publishable_key' => env('STRIPE_PUBLISHABLE_KEY', ''),


];

