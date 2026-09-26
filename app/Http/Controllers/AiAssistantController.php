<?php

namespace App\Http\Controllers;

use App\Helpers\Helper;
use App\Support\HotelDashboard;
use App\Support\Reports;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AiAssistantController extends Controller
{
    public function chat(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'history' => ['array'],
            'history.*.role' => ['required_with:history', 'in:user,assistant'],
            'history.*.content' => ['required_with:history', 'string', 'max:2000'],
        ]);

        $driver = config('services.ai.driver', 'anthropic');
        $keys = $this->keysFor($driver);

        if (! $keys) {
            return response()->json([
                'reply' => "AI Assistant is not switched on yet — add an API key for the \"{$driver}\" driver "
                    . 'to your .env file (see the AI Assistant section in config/services.php for the exact '
                    . 'line) and this box starts answering. Everything else on this dashboard already works '
                    . 'without it.',
                'configured' => false,
            ]);
        }

        $branchId = Helper::getActiveBranchId();

        if (! $branchId) {
            return response()->json([
                'reply' => 'There is no active branch set up yet, so I have no house to report on.',
                'configured' => true,
            ]);
        }

        try {
            $reply = $this->ask($driver, $keys, $branchId, $validated['message'], $validated['history'] ?? []);
        } catch (\Throwable $e) {
            Log::warning('AI Assistant call failed: ' . $e->getMessage());

            return response()->json([
                'reply' => "Couldn't reach the AI service just now — the key and network are worth checking. "
                    . 'The rest of the dashboard is unaffected.',
                'configured' => true,
                'error' => true,
            ], 200);
        }

        return response()->json(['reply' => $reply, 'configured' => true]);
    }

    /**
     * @return list<string>
     */
    private function keysFor(string $driver): array
    {
        $raw = (string) config("services.ai.{$driver}.key", '');

        return array_values(array_filter(array_map('trim', explode(',', $raw))));
    }

    private function ask(string $driver, array $keys, int $branchId, string $message, array $history): string
    {
        $system = $this->systemPrompt($branchId);

        return match ($driver) {
            'anthropic' => $this->askAnthropic($keys, $system, $message, $history),
            'gemini' => $this->askOpenAiCompatible(
                'https://generativelanguage.googleapis.com/v1beta/openai/chat/completions',
                $keys,
                config('services.ai.gemini.model', 'gemini-2.5-flash'),
                $system,
                $message,
                $history,
            ),
            'groq' => $this->askOpenAiCompatible(
                'https://api.groq.com/openai/v1/chat/completions',
                $keys,
                config('services.ai.groq.model', 'openai/gpt-oss-120b'),
                $system,
                $message,
                $history,
            ),
            'huggingface' => $this->askOpenAiCompatible(
                'https://router.huggingface.co/v1/chat/completions',
                $keys,
                config('services.ai.huggingface.model', 'openai/gpt-oss-120b'),
                $system,
                $message,
                $history,
            ),
            default => throw new \RuntimeException("AI driver [{$driver}] is not implemented yet."),
        };
    }
    private function askAnthropic(array $keys, string $system, string $message, array $history): string
    {
        $messages = collect($history)
            ->take(-8)
            ->map(fn (array $m) => ['role' => $m['role'], 'content' => $m['content']])
            ->push(['role' => 'user', 'content' => $message])
            ->values()
            ->all();

        $lastError = null;

        foreach ($keys as $key) {
            $response = Http::withHeaders([
                'x-api-key' => $key,
                'anthropic-version' => '2023-06-01',
            ])
                ->timeout(config('services.ai.timeout', 20))
                ->post('https://api.anthropic.com/v1/messages', [
                    'model' => config('services.ai.anthropic.model', 'claude-sonnet-4-5'),
                    'max_tokens' => config('services.ai.max_tokens', 600),
                    'system' => $system,
                    'messages' => $messages,
                ]);

            if ($response->successful()) {
                $text = collect($response->json('content', []))->firstWhere('type', 'text')['text'] ?? null;

                return $text ?: "The AI service answered with nothing usable — try asking again.";
            }

            $lastError = 'AI provider returned ' . $response->status() . ': ' . $response->body();

            if (! in_array($response->status(), [401, 403, 429], true)) {
                break;
            }
        }

        throw new \RuntimeException($lastError ?? 'AI provider call failed.');
    }
    private function askOpenAiCompatible(
        string $url,
        array $keys,
        string $model,
        string $system,
        string $message,
        array $history
    ): string {
        $messages = collect($history)
            ->take(-8)
            ->map(fn (array $m) => ['role' => $m['role'], 'content' => $m['content']])
            ->push(['role' => 'user', 'content' => $message])
            ->prepend(['role' => 'system', 'content' => $system])
            ->values()
            ->all();

        $lastError = null;

        foreach ($keys as $key) {
            $response = Http::withToken($key)
                ->timeout(config('services.ai.timeout', 20))
                ->post($url, [
                    'model' => $model,
                    'messages' => $messages,
                    'max_tokens' => config('services.ai.max_tokens', 600),
                ]);

            if ($response->successful()) {
                $text = $response->json('choices.0.message.content');

                return $text ?: "The AI service answered with nothing usable — try asking again.";
            }

            $lastError = 'AI provider returned ' . $response->status() . ': ' . $response->body();

            if (! in_array($response->status(), [401, 403, 429], true)) {
                break;
            }
        }

        throw new \RuntimeException($lastError ?? 'AI provider call failed.');
    }
    private function systemPrompt(int $branchId): string
    {
        $dashboard = new HotelDashboard($branchId);
        $headline = $dashboard->headline();
        $overview = $dashboard->overview();
        $money = Reports::run('occupancy', $branchId, ['from' => $dashboard->date(), 'to' => $dashboard->date()]);
        $today = $money['rows']->first();

        $facts = [
            'Date' => now()->format('l, d F Y'),
            'Total rooms' => $headline['rooms'],
            'Occupied' => $headline['occupied'],
            'Vacant' => $headline['vacant'],
            'Blocked / repair' => $headline['blocked'],
            'Expected arrivals today' => $headline['expected_arrival'],
            'Expected departures today' => $headline['expected_departure'],
            'Checked in today' => $headline['checked_in'],
            'Checked out today' => $headline['checked_out'],
            'In-house guests' => $overview['guests'],
            'In-house folios not fully settled' => $overview['pending'],
            "Today's room revenue" => $today->revenue ?? 0,
            'ADR' => $today->adr ?? 0,
            'RevPAR' => $today->revpar ?? 0,
        ];

        $lines = collect($facts)->map(fn ($v, $k) => "- {$k}: {$v}")->implode("\n");

        return "You are the AI Assistant on the dashboard of {$this->appName()}, a hotel PMS. "
            . "Answer briefly and concretely, in plain sentences (no markdown headers or bullet lists unless "
            . "the user's question is itself a list of several items). Use the figures below as ground truth "
            . "for today; do not invent numbers you were not given. "
            . "You cannot create bookings, take payments, or change any record yet — if asked to do one of "
            . "those, say so plainly and name the screen where the user can do it themselves "
            . "(New Reservation, Check-in, Check-out, Room Calendar, Reports). "
            . "Today's figures:\n{$lines}";
    }

    private function appName(): string
    {
        return config('app.name', 'this hotel');
    }
}
