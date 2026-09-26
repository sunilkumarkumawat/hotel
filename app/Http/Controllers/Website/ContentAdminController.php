<?php

namespace App\Http\Controllers\Website;

use App\Helpers\Helper;
use App\Http\Controllers\Controller;
use App\Models\Branch\Branch;
use App\Models\Master\RoomType;
use App\Models\Website\BranchWebsite;
use App\Models\Website\RoomTypeImage;
use App\Models\Website\RoomTypeWebsite;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Where the hotel edits its own website — reachable at
 * /administration/website, signed-in and admin-only.
 *
 * Kept deliberately simple rather than wired into the granular per-submodule
 * permission matrix every other admin screen uses (config/masters.php +
 * the module/submodule tables): that would mean seeding new rows into a
 * live permissions system this session cannot fully exercise by hand.
 * Checking Helper::isAdmin() directly is a smaller, safer footprint for a
 * screen only the owner is expected to touch, and it costs nothing to widen
 * later.
 */
class ContentAdminController extends Controller
{
    private function guard(): void
    {
        abort_unless(Helper::isAdmin(), 403, 'Only an administrator can edit the website.');
    }

    /** GET /administration/website — pick a branch to edit. */
    public function index(): View
    {
        $this->guard();

        $branches = Branch::query()->where('status', 1)->orderBy('branch_name')->with('website')->get();

        return view('admin.website.index', compact('branches'));
    }

    /** GET /administration/website/{branch} */
    public function edit(Branch $branch): View
    {
        $this->guard();

        $site = BranchWebsite::firstOrCreate(
            ['branch_id' => $branch->id],
            ['slug' => $this->uniqueSlug($branch->branch_name)]
        );

        $roomTypes = RoomType::query()
            ->forBranch($branch->id)
            ->active()
            ->with(['website', 'images'])
            ->orderBy('name')
            ->get();

        return view('admin.website.branch-edit', compact('branch', 'site', 'roomTypes'));
    }

    /** PUT /administration/website/{branch} */
    public function update(Request $request, Branch $branch): RedirectResponse
    {
        $this->guard();

        $site = BranchWebsite::firstOrCreate(['branch_id' => $branch->id], ['slug' => $this->uniqueSlug($branch->branch_name)]);

        $data = $request->validate([
            'slug' => 'required|alpha_dash|max:80|unique:branch_website,slug,' . $site->id,
            'tagline' => 'nullable|string|max:255',
            'about' => 'nullable|string|max:5000',
            'amenities' => 'nullable|string|max:4000',
            'offers' => 'nullable|string|max:2000',
            'policies' => 'nullable|string|max:5000',
            'checkin_time' => 'nullable|string|max:20',
            'checkout_time' => 'nullable|string|max:20',
            'hero_image' => 'nullable|image|max:5120',
        ]);

        $heroPath = $site->hero_image;

        if ($request->hasFile('hero_image')) {
            $file = $request->file('hero_image');
            $name = 'hotel-' . $branch->id . '-' . Str::random(10) . '.' . $file->getClientOriginalExtension();
            $file->move(public_path('images/hotels'), $name);
            $heroPath = 'images/hotels/' . $name;
        }

        $site->update([
            'slug' => $data['slug'],
            'tagline' => $data['tagline'] ?? null,
            'about' => $data['about'] ?? null,
            'amenities' => $this->linesToArray($data['amenities'] ?? ''),
            'offers' => $this->linesToArray($data['offers'] ?? ''),
            'policies' => $data['policies'] ?? null,
            'checkin_time' => $data['checkin_time'] ?? null,
            'checkout_time' => $data['checkout_time'] ?? null,
            'is_published' => $request->boolean('is_published'),
            'hero_image' => $heroPath,
        ]);

        return back()->with('status', 'Website content for ' . $branch->branch_name . ' has been saved.');
    }

    /** PUT /administration/website/room-type/{roomType} */
    public function updateRoomType(Request $request, RoomType $roomType): RedirectResponse
    {
        $this->guard();

        $data = $request->validate([
            'description' => 'nullable|string|max:3000',
            'highlights' => 'nullable|string|max:1000',
            'images.*' => 'nullable|image|max:5120',
        ]);

        $website = RoomTypeWebsite::firstOrCreate(['room_type_id' => $roomType->id]);

        $website->update([
            'description' => $data['description'] ?? null,
            'highlights' => $this->linesToArray($data['highlights'] ?? ''),
        ]);

        $hasCover = RoomTypeImage::where('room_type_id', $roomType->id)->where('is_cover', true)->exists();
        $nextSort = (int) RoomTypeImage::where('room_type_id', $roomType->id)->max('sort_order');

        foreach ($request->file('images', []) as $file) {
            $name = 'room-' . $roomType->id . '-' . Str::random(10) . '.' . $file->getClientOriginalExtension();
            $file->move(public_path('images/rooms'), $name);

            $nextSort++;

            RoomTypeImage::create([
                'room_type_id' => $roomType->id,
                'path' => 'images/rooms/' . $name,
                'sort_order' => $nextSort,
                // The very first photo this room type ever gets becomes its
                // cover automatically — nobody should have to remember a
                // second step just to make the room card show a picture.
                'is_cover' => ! $hasCover,
            ]);

            $hasCover = true;
        }

        return back()->with('status', 'Photos and description for ' . $roomType->name . ' have been saved.');
    }

    /** DELETE /administration/website/room-type-image/{image} */
    public function destroyRoomTypeImage(RoomTypeImage $image): RedirectResponse
    {
        $this->guard();

        $wasCover = $image->is_cover;
        $roomTypeId = $image->room_type_id;
        $path = $image->path;

        $image->delete();

        if (str_starts_with($path, 'images/rooms/')) {
            @unlink(public_path($path));
        }

        // Hand the cover to whichever photo is now first, so a room type
        // that had one photo never goes back to showing none by accident.
        if ($wasCover) {
            RoomTypeImage::where('room_type_id', $roomTypeId)->orderBy('sort_order')->first()?->update(['is_cover' => true]);
        }

        return back()->with('status', 'Photo removed.');
    }

    /** PUT /administration/website/room-type-image/{image}/cover */
    public function makeCoverImage(RoomTypeImage $image): RedirectResponse
    {
        $this->guard();

        RoomTypeImage::where('room_type_id', $image->room_type_id)->update(['is_cover' => false]);
        $image->update(['is_cover' => true]);

        return back()->with('status', 'Cover photo updated.');
    }

    /*
    |--------------------------------------------------------------------------
    | Internals
    |--------------------------------------------------------------------------
    */

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'hotel';
        $slug = $base;
        $n = 1;

        while (BranchWebsite::where('slug', $slug)->exists()) {
            $slug = $base . '-' . (++$n);
        }

        return $slug;
    }

    /** One item per line, blank lines dropped — the plain-textarea convention this whole screen uses. */
    private function linesToArray(string $text): array
    {
        return collect(explode("\n", $text))
            ->map(fn ($line) => trim($line))
            ->filter(fn ($line) => $line !== '')
            ->values()
            ->all();
    }
}
