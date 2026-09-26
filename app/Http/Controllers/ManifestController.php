<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
class ManifestController extends Controller
{
    public function show(): JsonResponse
    {
        $name = config('app.name') ?: 'Hotel Admin';

        $icons = collect([192, 512])->map(fn (int $size) => [
            'src' => asset("images/icons/icon-{$size}.png"),
            'sizes' => "{$size}x{$size}",
            'type' => 'image/png',
            'purpose' => 'any',
        ])->push([
            'src' => asset('images/icons/icon-maskable-512.png'),
            'sizes' => '512x512',
            'type' => 'image/png',
            'purpose' => 'maskable',
        ])->values()->all();

        return response()->json([
            'name' => $name,
            'short_name' => Str::limit($name, 12, ''),
            'description' => 'Front desk, reservations and reports, from a phone.',
            'start_url' => url('/'),
            'scope' => url('/'),
            'id' => url('/'),
            'display' => 'standalone',
            'background_color' => '#fffbf5',
            'theme_color' => '#fffbf5',
            'orientation' => 'portrait-primary',
            'icons' => $icons,
        ], 200, [
            'Content-Type' => 'application/manifest+json',
        ]);
    }
}
