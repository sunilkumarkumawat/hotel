<?php

namespace App\Http\Controllers;

use App\Models\Crm\GuestFeedback;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * The form the guest fills in, from a link on their phone.
 *
 * Deliberately outside every middleware group — there is no account, no
 * session and no cookie. The forty random characters in the link are what
 * stands in for a login: nothing to guess and nothing to count up through,
 * exactly like the document links.
 *
 * ── What this screen does NOT do ──────────────────────────────────────────
 *
 * It does not ask for a name, an email or a phone number. The hotel already
 * knows all three — the token says which stay this is — and a form that opens
 * by asking a guest to identify themselves is a form that gets closed.
 *
 * Every score is optional. Somebody who rates the room and skips the food has
 * told the hotel something useful, and a form that insists on all five gets
 * answered by nobody.
 */
class GuestFeedbackFormController extends Controller
{
    /** GET feedback/{token} */
    public function show(string $token): View
    {
        $feedback = GuestFeedback::query()
            ->where('token', $token)
            ->with(['checkIn.room', 'guest'])
            ->firstOrFail();

        return view('feedback.form', [
            'feedback' => $feedback,
            'hotel' => $feedback->checkIn?->branch_id
                ? \App\Models\Branch\Branch::find($feedback->checkIn->branch_id)
                : \App\Models\Branch\Branch::find($feedback->branch_id),
            'areas' => GuestFeedback::AREAS,
            'done' => $feedback->isAnswered(),
        ]);
    }

    /** POST feedback/{token} */
    public function store(Request $request, string $token)
    {
        $feedback = GuestFeedback::where('token', $token)->firstOrFail();

        /*
         * A second submission is not an error and not an overwrite. A guest
         * who taps the link again should see their answer, not a form that
         * quietly replaces what they already said.
         */
        if ($feedback->isAnswered()) {
            return redirect()->route('guest-feedback', $token);
        }

        $score = ['nullable', 'integer', 'min:1', 'max:5'];

        $data = $request->validate([
            'overall' => $score,
            'room' => $score,
            'cleanliness' => $score,
            'staff' => $score,
            'food' => $score,
            'value' => $score,
            'liked' => ['nullable', 'string', 'max:2000'],
            'improve' => ['nullable', 'string', 'max:2000'],
            'would_return' => ['nullable', Rule::in(['1', '0'])],
        ]);

        // Nothing at all answered is not an answer. Send them back rather than
        // recording a row of nulls as feedback.
        $said = collect($data)->filter(fn ($v) => $v !== null && $v !== '')->isNotEmpty();

        if (! $said) {
            return back()->with('error', 'Nothing was filled in — even one star tells us something.');
        }

        $data['would_return'] = $request->has('would_return') ? $request->boolean('would_return') : null;
        $data['answered_at'] = now();

        $feedback->update($data);

        return redirect()->route('guest-feedback', $token);
    }
}
