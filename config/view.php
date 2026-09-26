<?php

return [

    /*
    |--------------------------------------------------------------------------
    | View Storage Paths
    |--------------------------------------------------------------------------
    |
    | Where Blade looks for templates.
    |
    */

    'paths' => [
        resource_path('views'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Compiled View Path
    |--------------------------------------------------------------------------
    |
    | Where Blade writes the PHP it compiles each template into.
    |
    | Laravel's own default wraps this in `realpath()`, which returns **false**
    | when the folder is not there — and false becomes an empty string, so the
    | app dies with "Please provide a valid cache path" instead of saying which
    | folder is missing. That happens to anybody who unzips this project, since
    | `storage/framework/views` is empty and archives routinely drop empty
    | folders.
    |
    | Written without `realpath()`, Blade creates the folder itself the first
    | time it compiles a view, and the app just works.
    |
    */

    'compiled' => env(
        'VIEW_COMPILED_PATH',
        storage_path('framework/views')
    ),

];
