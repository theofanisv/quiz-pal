<?php

/*
|--------------------------------------------------------------------------
| One-time operations
|--------------------------------------------------------------------------
|
| Data changes that must run exactly once on every environment, the way a
| migration changes the schema. Create one with `php artisan operations:make
| <Name>`; `composer update-app` runs the pending ones on the next deploy
| with `php artisan operations:process --sync`. The server runs no queue
| worker, so operations always run synchronously during the update.
|
*/

return [

    // The directory that holds the operation files, relative to the application root.
    'directory' => 'database/operations',

    // The table that records which operations have run.
    'table' => 'operations',

    // The database connection of that table; null uses the default connection.
    'connection' => null,

];
