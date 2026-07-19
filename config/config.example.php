<?php
/**
 * Copy this file to config.php and fill in your real values.
 * config.php is listed in .gitignore - never commit real credentials.
 */

return [
    'db' => [
        'host'     => '127.0.0.1',
        'port'     => '3306',
        'database' => 'pq_timetable_system',
        'username' => 'your_db_user',
        'password' => 'your_db_password',
    ],

    // Used to sign/verify things like password-reset tokens.
    // Generate a random one with: php -r "echo bin2hex(random_bytes(32));"
    'app_key' => 'REPLACE_WITH_A_RANDOM_64_CHAR_HEX_STRING',

    // AI Solver / AI Study Assistant. Get a key from platform.openai.com
    // or console.anthropic.com - either works, the app adapts automatically.
    'ai' => [
        'provider' => 'openai', // 'openai' or 'anthropic'
        'api_key'  => 'REPLACE_WITH_YOUR_API_KEY',
        // Budget models - cheap and plenty capable for this use case.
        // Check your provider's current model list/pricing before relying on these names.
        'model'    => 'gpt-5.4-mini', // if provider is 'anthropic', use 'claude-haiku-4-5-20251001'
    ],

    'upload' => [
        // Kept OUTSIDE the public/ webroot so files can never be executed
        // or listed directly by the browser - access is always via a PHP
        // script that checks permissions first.
        'path'          => __DIR__ . '/../uploads/',
        'max_size_bytes'=> 10 * 1024 * 1024, // 10 MB
        'allowed_mimes' => [
            'application/pdf',
            'image/jpeg',
            'image/png',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ],
    ],

    // Controls two things: (1) whether PHP errors are shown on screen vs
    // just logged, and (2) whether forgot_password.php shows the reset
    // link on screen (only useful because local dev has no mail server).
    // MUST be false before this ever goes anywhere someone else can reach -
    // both behaviors leak information you don't want strangers to have.
    'debug' => true,
];
