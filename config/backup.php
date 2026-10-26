<?php
return [
    'google' => [
        'client_id' => env('GOOGLE_DRIVE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_DRIVE_CLIENT_SECRET'),
        'redirect_uri' => env('GOOGLE_DRIVE_REDIRECT_URI'),
    ],
    'hosted' => env('BACKUP_HOSTED', false),
    'test_database' => env('BACKUP_TEST_DATABASE'),
    'time' => env('BACKUP_TIME', '22:00'),
    'timezone' => 'Africa/Nairobi',
    'external_type' => env('BACKUP_EXTERNAL_TYPE', 'drive'),
    'external_path' => env('BACKUP_EXTERNAL_PATH'),
    'key_path' => storage_path('app/backup-key'),
    'directory' => storage_path('app/backups'),
    'uploads' => ['public/images', 'public/uploads', 'public/public', 'storage/app/public'],
];
