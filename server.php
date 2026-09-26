<?php

/*
|--------------------------------------------------------------------------
| Local dev server router
|--------------------------------------------------------------------------
|
| start-pms.bat runs this instead of "php artisan serve" (which uses
| Laravel's own vendor/laravel/framework/.../resources/server.php — this
| file is a copy of it, with one addition below).
|
| Why: PHP's built-in web server ("php -S", which "artisan serve" is a
| thin wrapper around) refuses to serve a file whose real location, after
| following a symlink, lands outside the folder it was started in — it
| answers 403 instead of just sending the file. public/storage is exactly
| that kind of symlink (it points at storage/app/public, which is outside
| public/), so on this server every uploaded photo — item photos, ID proof
| photos, outlet logos — is invisible even once "php artisan storage:link"
| has made the link correctly. Apache/Nginx never had this restriction;
| it only shows up here because this project runs on PHP's own built-in
| server. The block below hands /storage/* to PHP itself instead of
| letting the built-in server's stricter check see it, so photos work
| the same way they would on a real web server. Everything else below is
| untouched from Laravel's original file.
|
*/

$publicPath = getcwd();

$uri = urldecode(
    parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? ''
);

if (str_starts_with($uri, '/storage/')) {
    // realpath() is what actually keeps this safe: it resolves the
    // symlink and any "..", so the only way through is a real file that
    // ends up genuinely inside storage/app/public. Anything else —
    // a missing file, or a path that tries to climb out with "../" —
    // falls straight through to the 404 below.
    $storageRoot = realpath($publicPath.'/../storage/app/public');
    $requested = realpath($publicPath.$uri);

    if ($storageRoot !== false && $requested !== false
        && is_file($requested)
        && str_starts_with($requested, $storageRoot.DIRECTORY_SEPARATOR)) {
        header('Content-Type: '.(mime_content_type($requested) ?: 'application/octet-stream'));
        header('Content-Length: '.filesize($requested));
        header('Cache-Control: public, max-age=604800');
        readfile($requested);

        return true;
    }

    http_response_code(404);

    return true;
}

// This file allows us to emulate Apache's "mod_rewrite" functionality from the
// built-in PHP web server. This provides a convenient way to test a Laravel
// application without having installed a "real" web server software here.
if ($uri !== '/' && file_exists($publicPath.$uri)) {
    return false;
}

$formattedDateTime = date('D M j H:i:s Y');

$requestMethod = $_SERVER['REQUEST_METHOD'];
$remoteAddress = $_SERVER['REMOTE_ADDR'].':'.$_SERVER['REMOTE_PORT'];

file_put_contents('php://stdout', "[$formattedDateTime] $remoteAddress [$requestMethod] URI: $uri\n");

require_once $publicPath.'/index.php';
