<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Firebase Credentials
    |--------------------------------------------------------------------------
    |
    | Path to Firebase service account JSON file.
    | Get this from Firebase Console > Project Settings > Service Accounts
    |
    */

    'credentials' => [
        'file' => env('FIREBASE_CREDENTIALS', storage_path('app/firebase-credentials.json')),
    ],

    /*
    |--------------------------------------------------------------------------
    | Firebase Realtime Database URL
    |--------------------------------------------------------------------------
    |
    | Your Firebase Realtime Database URL (REQUIRED for this app)
    | Example: https://your-project-default-rtdb.firebaseio.com
    |
    */

    'database' => [
        'url' => env('FIREBASE_DATABASE_URL', ''),
    ],

    /*
    |--------------------------------------------------------------------------
    | Firebase Project ID
    |--------------------------------------------------------------------------
    |
    | Your Firebase project ID
    |
    */

    'project_id' => env('FIREBASE_PROJECT_ID', ''),

    /*
    |--------------------------------------------------------------------------
    | Firebase Web SDK Config (Public Client Identifiers)
    |--------------------------------------------------------------------------
    */

    'web' => [
        'api_key' => env('FIREBASE_API_KEY', ''),
        'auth_domain' => env('FIREBASE_AUTH_DOMAIN', ''),
        'database_url' => env('FIREBASE_DATABASE_URL', ''),
        'project_id' => env('FIREBASE_PROJECT_ID', ''),
        'storage_bucket' => env('FIREBASE_STORAGE_BUCKET', ''),
        'messaging_sender_id' => env('FIREBASE_MESSAGING_SENDER_ID', ''),
        'app_id' => env('FIREBASE_APP_ID', ''),
    ],

];
