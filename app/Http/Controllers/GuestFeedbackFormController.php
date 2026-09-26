<?php

namespace App\Http\Controllers;

use App\Models\Crm\GuestFeedback;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class GuestFeedbackFormController extends Controller
{
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

    public function store(Request $request, string $token)
    {
        $feedback = GuestFeedback::where('token', $token)->firstOrFail();

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
