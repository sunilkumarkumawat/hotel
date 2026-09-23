<?php

namespace App\Helpers;

use App\Models\Branch\Branch;
use App\Models\City\City;
use App\Models\Common\Module;
use App\Models\Common\SubModule;
use App\Models\Country\Country;
use App\Models\State\State;
use App\Models\UserPermission\UserPermission;
use Illuminate\Support\Facades\Auth;

/**
 * Menu + permission helper.
 *
 * Permissions live in `user_permission`: one row per (user, branch) holding
 * comma-separated module/submodule ids and a JSON map of what the user may
 * do inside each submodule:
 *
 *     module_id     "1,2,6"
 *     submodule_id  "1,2,3,18,19"
 *     permissions   {"1":{"view":1,"add":1,"edit":1,"delete":0}, ...}
 */
class Helper
{
    /** Per-request cache so the sidebar costs one query, not one per module. */
    private static ?array $accessCache = null;

    private static ?int $accessCacheUser = null;

    /*
    |--------------------------------------------------------------------------
    | Menu
    |--------------------------------------------------------------------------
    */

    /**
     * The whole menu — every module with its submodules. No filtering.
     * Use sideMenus() for the sidebar; this one is for the permission matrix.
     */
    public static function allMenus()
    {
        return Module::with(['submodules' => fn ($q) => $q->orderBy('sort')->orderBy('name')])
            ->orderBy('sort')
            ->orderBy('name')
            ->get();
    }

    /**
     * The sidebar: active modules with only the submodules the signed-in user
     * is allowed to view. Modules left with nothing visible are dropped.
     */
    public static function sideMenus()
    {
        $user = Auth::user();

        if (! $user) {
            return collect();
        }

        $access = self::getUserAccessModules($user->user_id);
        $isAdmin = self::isAdmin();

        $menus = self::allMenus();

        $menus->each(function (Module $module) use ($access, $isAdmin) {
            $module->total_submodules = $module->submodules->count();

            $module->setRelation('submodules', $module->submodules->filter(
                fn (SubModule $sub) => $isAdmin || self::canSubmodule($sub->id, 'view', $access)
            )->values());
        });

        return $menus->filter(fn (Module $m) => $m->submodules->isNotEmpty())->values();
    }

    /*
    |--------------------------------------------------------------------------
    | Permissions
    |--------------------------------------------------------------------------
    */

    /**
     * Everything the given user may reach in the active branch.
     *
     * @return array{modules: array<int, string>, submodules: array<int, string>, permissions: array<string, array<string, int>>}
     */
    public static function getUserAccessModules($userId): array
    {
        if (self::$accessCache !== null && self::$accessCacheUser === (int) $userId) {
            return self::$accessCache;
        }

        $empty = ['modules' => [], 'submodules' => [], 'permissions' => []];

        $query = UserPermission::where('user_id', $userId);

        if ($branchId = self::getActiveBranchId()) {
            $query->where('branch_id', $branchId);
        }

        $row = $query->first();

        if (! $row) {
            self::$accessCacheUser = (int) $userId;

            return self::$accessCache = $empty;
        }

        $csv = fn (?string $value) => $value
            ? array_values(array_filter(array_map('trim', explode(',', $value))))
            : [];

        $permissions = $row->permissions;

        if (! is_array($permissions)) {
            $permissions = json_decode((string) $permissions, true) ?: [];
        }

        self::$accessCacheUser = (int) $userId;

        return self::$accessCache = [
            'modules' => $csv($row->module_id),
            'submodules' => $csv($row->submodule_id),
            'permissions' => $permissions,
        ];
    }

    /** Drop the cached access — after saving permissions, or in tests. */
    public static function flushAccess(): void
    {
        self::$accessCache = null;
        self::$accessCacheUser = null;
    }

    /** Is the signed-in user an administrator? Admins bypass every check. */
    public static function isAdmin(): bool
    {
        $user = Auth::user();

        return $user ? (int) $user->role_id === 1 : false;
    }

    /**
     * May the signed-in user perform $action ('view'|'add'|'edit'|'delete')
     * on the submodule with this id?
     */
    public static function checkUserPermission($submoduleId, string $action): bool
    {
        $user = Auth::user();

        if (! $user) {
            return false;
        }

        if (self::isAdmin()) {
            return true;
        }

        return self::canSubmodule($submoduleId, $action, self::getUserAccessModules($user->user_id));
    }

    /** Same check, but against the submodule matching the current URL. */
    public static function checkCurrentPermission(string $action): bool
    {
        if (self::isAdmin()) {
            return true;
        }

        $submodule = SubModule::where('url', trim(request()->path(), '/'))->first();

        return $submodule ? self::checkUserPermission($submodule->id, $action) : false;
    }

    /** May the user do $action on the submodule with this slug/url? */
    public static function can(string $submoduleUrl, string $action = 'view'): bool
    {
        if (self::isAdmin()) {
            return true;
        }

        $submodule = SubModule::where('url', $submoduleUrl)->first();

        return $submodule ? self::checkUserPermission($submodule->id, $action) : false;
    }

    /** @param array{submodules: array<int, string>, permissions: array<string, mixed>} $access */
    private static function canSubmodule($submoduleId, string $action, array $access): bool
    {
        $id = (string) $submoduleId;

        if (! in_array($id, array_map('strval', $access['submodules']), true)) {
            return false;
        }

        return isset($access['permissions'][$id][$action])
            && (int) $access['permissions'][$id][$action] === 1;
    }

    /*
    |--------------------------------------------------------------------------
    | Branch
    |--------------------------------------------------------------------------
    */

    public static function getActiveBranchId(): ?int
    {
        $branchId = session('active_branch_id', session('branch_id'));

        return $branchId ? (int) $branchId : null;
    }

    public static function activeBranch(): ?Branch
    {
        $id = self::getActiveBranchId();

        return $id ? Branch::find($id) : null;
    }

    /** Branches the signed-in user may switch to. */
    public static function availableBranches()
    {
        $user = Auth::user();

        if (! $user) {
            return collect();
        }

        return self::isAdmin()
            ? Branch::where('status', 1)->orderBy('branch_name')->get()
            : Branch::where('status', 1)->where('id', $user->branch_id)->get();
    }

    /*
    |--------------------------------------------------------------------------
    | Dropdowns
    |--------------------------------------------------------------------------
    */

    public static function getCountries()
    {
        return Country::orderBy('name')->get(['id', 'name']);
    }

    public static function getStates($countryId)
    {
        return State::where('country_id', $countryId)->orderBy('name')->get(['id', 'name']);
    }

    public static function getCities($stateId)
    {
        return City::where('state_id', $stateId)->orderBy('name')->get(['id', 'name']);
    }

    /*
    |--------------------------------------------------------------------------
    | WhatsApp
    |--------------------------------------------------------------------------
    */

    /**
     * Send a WhatsApp message.
     *
     *     Helper::sendWhatsappMessage($mobile, $finalMessage);
     *     Helper::sendWhatsappMessage($mobile, $text, $pdfUrl, 'bill.pdf');
     *
     * The gateway, the username and the token all come from .env
     * (`WHATSAPP_DRIVER`, `WHATSAPP_USERNAME`, `WHATSAPP_TOKEN`) rather than
     * being written into this file. That is not tidiness for its own sake: a
     * token in the source is a token in every backup, every zip and every copy
     * of the project anybody is ever sent, and changing it then means changing
     * the code.
     *
     * **It never throws.** A message that could not be sent must not take a
     * booking down with it, so the failure comes back as a value and is written
     * to `notification_deliveries`, where the Notification Settings screen
     * shows it with the gateway's own error on it.
     *
     * @param  string|null  $toMobile  however the desk typed it — 98765 43210,
     *                                 +91 98765 43210 and 09876543210 all work
     * @param  string|null  $filepath  a public URL to send as an attachment
     * @param  string|null  $filename  kept for callers that pass it; the
     *                                 gateway takes the name from the URL
     * @return array{status: string, response?: string, message?: string}
     */
    public static function sendWhatsappMessage($toMobile, $text, $filepath = null, $filename = null): array
    {
        if (blank($toMobile)) {
            return ['status' => 'error', 'message' => 'Mobile number is required.'];
        }

        if (blank($text) && blank($filepath)) {
            return ['status' => 'error', 'message' => 'There is nothing to send.'];
        }

        $number = \App\Support\WhatsApp::number((string) $toMobile);

        $delivery = \App\Support\GuestMessage::logRow($number, (string) ($filename ?: ''));

        try {
            $response = \App\Support\WhatsApp::send((string) $toMobile, (string) $text, $filepath);

            \App\Support\GuestMessage::markSent($delivery, $response);

            /*
             * A message written to the log file has not been sent, and a
             * caller that is told 'success' will go on to tell a guest it was.
             * The only honest answer is the reason.
             */
            if ($response === 'logged') {
                return ['status' => 'error', 'message' => \App\Support\GuestMessage::LOG_ONLY];
            }

            return ['status' => 'success', 'response' => $response];
        } catch (\Throwable $e) {
            \App\Support\GuestMessage::markFailed($delivery, $e->getMessage());

            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }
}
