<?php

namespace App\Http\Controllers;

use App\Support\GuestDocument;
use Symfony\Component\HttpFoundation\BinaryFileResponse;


class GuestDocumentController extends Controller
{
    public function show(string $token): BinaryFileResponse
    {
        $token = preg_replace('/\.pdf$/i', '', $token) ?? $token;

        $path = GuestDocument::path($token) ?? abort(404);
        return response()->file($path, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $token . '.pdf"',
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }
}
