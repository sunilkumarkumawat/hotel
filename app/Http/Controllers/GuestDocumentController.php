<?php

namespace App\Http\Controllers;

use App\Support\GuestDocument;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Handing out the PDF that went with a guest's message.
 *
 * This is the one route in the system that is not behind a login, and it has to
 * be: WhatsApp's gateway fetches the file itself, with no account and no
 * session, and so does the guest when they tap it on their phone.
 *
 * What makes that safe is the link. It is forty random characters — there is
 * nothing to guess and nothing to count up through — and it holds one guest's
 * own booking, which is the document they were just sent. Old files are cleared
 * out on a schedule, so a link does not stay live for ever.
 */
class GuestDocumentController extends Controller
{
    public function show(string $token): BinaryFileResponse
    {
        $path = GuestDocument::path($token) ?? abort(404);

        /*
         * Shown in the browser rather than downloaded: a guest tapping a link
         * on a phone wants to read it, and on most phones a download is a file
         * they then have to go and find.
         */
        return response()->file($path, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $token . '.pdf"',
            // It never changes, and the gateway may well fetch it twice.
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }
}
