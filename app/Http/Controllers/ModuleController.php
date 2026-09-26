<?php

namespace App\Http\Controllers;

use App\Helpers\Helper;
use App\Models\Common\Module;
use App\Models\Common\SubModule;
use App\Models\UserPermission\UserPermission;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;


class ModuleController extends Controller
{
    public function index(): View
    {
        return view('modules.index', [
            'modules' => Helper::allMenus(),
            'counts' => [
                'modules' => Module::count(),
                'submodules' => SubModule::count(),
                'linked' => SubModule::whereNotNull('url')->where('url', '!=', '')->count(),
            ],
        ]);
    }

    public function storeModule(Request $request): RedirectResponse
    {
        $data = $this->validatedModule($request);
        $data['sort'] ??= (Module::max('sort') ?? 0) + 1;

        $module = Module::create($data);

        return back()->with('status', "Module \"{$module->name}\" has been added.");
    }

    public function updateModule(Request $request, Module $module): RedirectResponse
    {
        $module->update($this->validatedModule($request, $module));

        return back()->with('status', "Module \"{$module->name}\" has been updated.");
    }

    public function destroyModule(Module $module): RedirectResponse
    {
        $name = $module->name;
        $submoduleIds = $module->submodules()->pluck('id')->all();

        DB::transaction(function () use ($module, $submoduleIds) {
            $module->submodules()->delete();
            $module->delete();
            $this->prunePermissions($submoduleIds, [$module->id]);
        });

        return back()->with(
            'status',
            "Module \"{$name}\" and its " . count($submoduleIds) . ' sub-module(s) have been deleted.'
        );
    }

    public function storeSubmodule(Request $request): RedirectResponse
    {
        $data = $this->validatedSubmodule($request);
        $data['sort'] ??= (SubModule::where('module_id', $data['module_id'])->max('sort') ?? 0) + 1;

        $submodule = SubModule::create($data);

        return back()->with('status', "Sub-module \"{$submodule->name}\" has been added.");
    }

    public function updateSubmodule(Request $request, SubModule $submodule): RedirectResponse
    {
        $submodule->update($this->validatedSubmodule($request, $submodule));

        return back()->with('status', "Sub-module \"{$submodule->name}\" has been updated.");
    }

    public function destroySubmodule(SubModule $submodule): RedirectResponse
    {
        $name = $submodule->name;
        $id = $submodule->id;

        DB::transaction(function () use ($submodule, $id) {
            $submodule->delete();
            $this->prunePermissions([$id], []);
        });

        return back()->with('status', "Sub-module \"{$name}\" and its permissions have been removed.");
    }

    /**
     * @param  array<int, int>  $submoduleIds
     * @param  array<int, int>  $moduleIds
     */
    private function prunePermissions(array $submoduleIds, array $moduleIds): void
    {
        if (! $submoduleIds && ! $moduleIds) {
            return;
        }

        $strip = function (?string $csv, array $remove): string {
            $ids = $csv ? array_filter(array_map('trim', explode(',', $csv))) : [];

            return implode(',', array_diff($ids, array_map('strval', $remove)));
        };

        foreach (UserPermission::cursor() as $row) {
            $permissions = $row->permissions ?: [];

            foreach ($submoduleIds as $id) {
                unset($permissions[(string) $id]);
            }

            $row->update([
                'submodule_id' => $strip($row->submodule_id, $submoduleIds),
                'module_id' => $strip($row->module_id, $moduleIds),
                'permissions' => $permissions,
            ]);
        }

        Helper::flushAccess();
    }

    /** @return array<string, mixed> */
    private function validatedModule(Request $request, ?Module $module = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:80', Rule::unique('module', 'name')->ignore($module)],
            'icon' => ['nullable', 'string', 'max:40'],
            'url' => ['nullable', 'string', 'max:120'],
            'sort' => ['nullable', 'integer', 'min:0', 'max:127'],
        ]);
    }

    /** @return array<string, mixed> */
    private function validatedSubmodule(Request $request, ?SubModule $submodule = null): array
    {
        return $request->validate([
            'module_id' => ['required', Rule::exists('module', 'id')],
            'name' => ['required', 'string', 'max:80'],
            'url' => [
                'required', 'string', 'max:120', 'regex:/^[A-Za-z0-9._\/-]+$/',
                Rule::unique('submodule', 'url')->ignore($submodule),
            ],
            'sort' => ['nullable', 'integer', 'min:0', 'max:127'],
        ], [
            'url.regex' => 'The URL may only contain letters, numbers and . _ / -',
        ]);
    }
}
