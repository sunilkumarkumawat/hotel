<?php

/*
|--------------------------------------------------------------------------
| Global helpers
|--------------------------------------------------------------------------
| Loaded by composer (see "autoload.files" in composer.json). These are thin
| wrappers over App\Helpers\Helper so the views stay readable.
|
| After adding a function here, run:  composer dump-autoload
*/

use App\Helpers\Helper;
use App\Models\Common\Module;
use App\Models\Common\SubModule;

if (! function_exists('sidebar_menu')) {
    /** Modules with the submodules the signed-in user may see. */
    function sidebar_menu()
    {
        return Helper::sideMenus();
    }
}

if (! function_exists('can_do')) {
    /** can_do('room-list', 'add') — permission check by submodule url. */
    function can_do(string $submoduleUrl, string $action = 'view'): bool
    {
        return Helper::can($submoduleUrl, $action);
    }
}

if (! function_exists('can_here')) {
    /** can_here('edit') — same check against the page you are already on. */
    function can_here(string $action): bool
    {
        return Helper::checkCurrentPermission($action);
    }
}

if (! function_exists('is_admin')) {
    function is_admin(): bool
    {
        return Helper::isAdmin();
    }
}

if (! function_exists('menu_url')) {
    /** Where a submodule points — a named route, a path, or '#'. */
    function menu_url(SubModule $submodule): string
    {
        if (! $submodule->url) {
            return '#';
        }

        return \Illuminate\Support\Facades\Route::has($submodule->url)
            ? route($submodule->url)
            : url($submodule->url);
    }
}

if (! function_exists('is_menu_active')) {
    /** Is this submodule the page currently being viewed? */
    function is_menu_active(SubModule $submodule): bool
    {
        if (! $submodule->url) {
            return false;
        }

        $path = trim($submodule->url, '/');

        return request()->is($path) || request()->is($path . '/*');
    }
}

if (! function_exists('is_module_active')) {
    /** Does this module contain the page currently being viewed? */
    function is_module_active(Module $module): bool
    {
        return $module->submodules->contains(fn (SubModule $s) => is_menu_active($s));
    }
}

if (! function_exists('active_branch')) {
    function active_branch()
    {
        return Helper::activeBranch();
    }
}

if (! function_exists('event_meta')) {
    /**
     * One event's entry out of config/notifications.php.
     *
     * This exists because `config('notifications.events.reservation.created')`
     * silently returns nothing. Laravel reads every dot in a config key as
     * "go one level deeper", so it looks for an array called `reservation`
     * with a `created` key inside it — and the event names in that file are
     * single keys with a dot in them, `'reservation.created' => [...]`. The
     * lookup finds nothing, returns the default, and the caller carries on
     * with an empty array. Nothing errors; things just quietly stop happening.
     *
     * Every lookup of a dotted key has to index the array itself, which is
     * what this does — once, here, rather than as the same mistake spread
     * across the controllers and the views.
     *
     * @return array<string, mixed>
     */
    function event_meta(?string $event): array
    {
        if (! $event) {
            return [];
        }

        $events = config('notifications.events', []);

        return is_array($events[$event] ?? null) ? $events[$event] : [];
    }
}

if (! function_exists('event_label')) {
    /** What to call an event on screen, falling back to its own key. */
    function event_label(?string $event): string
    {
        return (string) (event_meta($event)['label'] ?? ($event ?: '—'));
    }
}

if (! function_exists('guest_template')) {
    /**
     * One message template out of config/guest-messages.php.
     *
     * Same dotted-key trap as event_meta(): the keys are `'guest.booking'`
     * and friends, so they can only be reached by indexing the array.
     */
    function guest_template(?string $event): ?string
    {
        if (! $event) {
            return null;
        }

        $templates = config('guest-messages', []);
        $template = $templates[$event] ?? null;

        return is_string($template) && trim($template) !== '' ? $template : null;
    }
}

if (! function_exists('sidebar_photo')) {
    /**
     * The hotel's own photo behind the sidebar menu, if somebody has put one
     * there.
     *
     * Drop a picture at public/images/sidebar.jpg — or .jpeg, .png, .webp —
     * and it appears. Take it away and the sidebar goes back to its plain
     * colour. There is no setting to switch on and nothing to rebuild, because
     * "put the file here" is a thing a hotel manager can do and "edit a config
     * file" is not.
     *
     * The returned URL carries the file's timestamp, so replacing the picture
     * shows the new one immediately instead of whatever the browser cached
     * yesterday.
     */
    function sidebar_photo(): ?string
    {
        foreach (['jpg', 'jpeg', 'png', 'webp'] as $extension) {
            $file = 'images/sidebar.' . $extension;
            $path = public_path($file);

            if (is_file($path)) {
                return asset($file) . '?v=' . filemtime($path);
            }
        }

        return null;
    }
}
