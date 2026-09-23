<?php

namespace App\Models\Common;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Throwable;

/** One screen inside a module. `url` is both the link and the permission key. */
class SubModule extends Model
{
    protected $table = 'submodule';

    protected $fillable = ['module_id', 'name', 'url', 'sort'];

    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class, 'module_id');
    }

    /**
     * Is there a real screen behind this menu entry yet?
     *
     * A sub-module can be added to the menu before its route exists — the
     * sidebar then shows it with a "Soon" pill instead of a dead link. The
     * moment you register a route with that name or path, the pill goes away
     * on its own.
     *
     * The check asks the router to match the url the way a real request would,
     * so a screen served by a parameterised route (masters/{master}) counts as
     * linked, while a path nothing answers does not. That means wildcard routes
     * must be constrained — see whereNumber() on the reservation routes —
     * otherwise `reservation/{reservation}` would claim every unbuilt
     * reservation screen.
     */
    public function isLinked(): bool
    {
        if (! $this->url) {
            return false;
        }

        // The sidebar asks this for every row on every page, so answer once.
        static $cache = [];

        if (array_key_exists($this->url, $cache)) {
            return $cache[$this->url];
        }

        if (Route::has($this->url)) {
            return $cache[$this->url] = true;
        }

        try {
            Route::getRoutes()->match(Request::create('/' . ltrim($this->url, '/'), 'GET'));

            return $cache[$this->url] = true;
        } catch (Throwable) {
            return $cache[$this->url] = false;
        }
    }
}
