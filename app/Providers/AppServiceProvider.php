<?php

namespace App\Providers;

use App\Helpers\Helper;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * The four folders Laravel writes to, made sure of before anything uses them.
     *
     * `storage/framework/sessions` and friends are empty in every source tree,
     * and empty folders are exactly what gets lost on the way to a hotel's
     * server: a zip tool drops them, FTP skips them, a Git checkout never had
     * them. What the user then sees is a 500 with
     * "file_put_contents(...sessions/...): No such file or directory" on the
     * login page, which reads like the app is broken rather than like a folder
     * is missing.
     */
    private const WRITABLE = [
        'framework/sessions',
        'framework/views',
        'framework/cache/data',
        'logs',
    ];

    public function register(): void
    {
        /*
         * Done in register() rather than boot(): the session handler is built
         * while the request is still starting up, which is before boot() on
         * some paths. Creating a folder that already exists costs nothing, and
         * a host that refuses (an open_basedir, a read-only mount) is left to
         * fail with its own message rather than being hidden by an exception
         * from here.
         */
        foreach (self::WRITABLE as $path) {
            $full = storage_path($path);

            if (! is_dir($full)) {
                @mkdir($full, 0775, true);
            }
        }
    }

    public function boot(): void
    {
        // Use the theme's own pagination markup instead of Laravel's default.
        Paginator::defaultView('vendor.pagination.nova');
        Paginator::defaultSimpleView('vendor.pagination.nova');

        /*
         * Blade shortcuts for the permission checks, by submodule url:
         *
         *     @canView('room-list') ... @endCanView
         *     @canAdd('room-list')  ... @endCanAdd
         */
        foreach (['View' => 'view', 'Add' => 'add', 'Edit' => 'edit', 'Delete' => 'delete'] as $name => $action) {
            Blade::directive('can' . $name, fn ($submodule) => "<?php if (\\App\\Helpers\\Helper::can({$submodule}, '{$action}')): ?>");
            Blade::directive('endCan' . $name, fn () => '<?php endif; ?>');
        }

        // @admin ... @endAdmin
        Blade::directive('admin', fn () => "<?php if (\\App\\Helpers\\Helper::isAdmin()): ?>");
        Blade::directive('endAdmin', fn () => '<?php endif; ?>');
    }
}
