<?php

namespace App\Http\Controllers;

use App\Helpers\Helper;
use App\Models\Notification\AppNotification;
use App\Models\Notification\NotificationDelivery;
use App\Models\Notification\NotificationSetting;
use App\Support\GuestDocument;
use App\Support\Notify;
use App\Support\Smtp;
use App\Support\WhatsApp;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;


class NotificationController extends Controller
{
    public function index(Request $request): View
    {
        $branchId = (int) Helper::getActiveBranchId();
        $userId = (int) $request->user()->user_id;

        $rows = AppNotification::query()
            ->visibleTo($branchId, $userId)
            ->when($request->string('event')->toString(), fn ($q, $e) => $q->where('event', $e))
            ->when($request->string('level')->toString(), fn ($q, $l) => $q->where('level', $l))
            ->when($request->boolean('unread'), fn ($q) => $q->unread())
            ->latest('id')
            ->paginate(40)
            ->withQueryString();

        return view('notifications.index', [
            'rows' => $rows,
            'events' => collect(config('notifications.events'))->map(fn ($e) => $e['label'] ?? '')->sort(),
            'filters' => [
                'event' => $request->string('event')->toString(),
                'level' => $request->string('level')->toString(),
                'unread' => $request->boolean('unread'),
            ],
            'unread' => AppNotification::query()->visibleTo($branchId, $userId)->unread()->count(),
        ]);
    }

    public function feed(Request $request): JsonResponse
    {
        $branchId = (int) Helper::getActiveBranchId();
        $userId = (int) $request->user()->user_id;
        $since = $request->integer('since');

        $limit = (int) config('pms.notify_feed_limit', 30);

        $rows = AppNotification::query()
            ->visibleTo($branchId, $userId)
            ->latest('id')
            ->limit($limit)
            ->get(['id', 'event', 'title', 'body', 'url', 'icon', 'level', 'read_at', 'created_at']);

        if ($request->boolean('prune')) {
            Notify::prune();
        }

        return response()->json([
            'unread' => AppNotification::query()->visibleTo($branchId, $userId)->unread()->count(),
            'latest' => (int) ($rows->first()->id ?? 0),
            'items' => $rows->map(fn (AppNotification $row) => [
                'id' => $row->id,
                'event' => $row->event,
                'title' => $row->title,
                'body' => $row->body,
                'url' => $row->url,
                'icon' => $row->icon ?: 'bell',
                'level' => $row->level,
                'unread' => $row->read_at === null,
                'at' => $row->created_at?->toIso8601String(),
                'when' => $row->created_at?->diffForHumans(),
                'fresh' => $since > 0 && $row->id > $since,
            ])->values(),
        ]);
    }
    public function read(Request $request): RedirectResponse|JsonResponse
    {
        $branchId = (int) Helper::getActiveBranchId();
        $userId = (int) $request->user()->user_id;

        $query = AppNotification::query()->visibleTo($branchId, $userId)->unread();

        if ($id = $request->integer('id')) {
            $query->whereKey($id);
        }

        $marked = $query->update(['read_at' => now()]);

        if ($request->expectsJson()) {
            return response()->json([
                'marked' => $marked,
                'unread' => AppNotification::query()->visibleTo($branchId, $userId)->unread()->count(),
            ]);
        }

        return back()->with('status', $marked === 1 ? 'Marked as read.' : $marked . ' marked as read.');
    }

    public function settings(): View
    {
        $branchId = (int) Helper::getActiveBranchId();

        $saved = NotificationSetting::query()
            ->where('branch_id', $branchId)
            ->get()
            ->keyBy('event');

        $events = collect(config('notifications.events'))
            ->map(function (array $meta, string $key) use ($saved) {
                $row = $saved->get($key);

                return [
                    'key' => $key,
                    'label' => $meta['label'] ?? $key,
                    'group' => $meta['group'] ?? 'system',
                    'level' => $meta['level'] ?? 'info',
                    'channels' => $row
                        ? $row->channelList()
                        : NotificationSetting::split($meta['channels'] ?? 'app'),
                    'mail_to' => $row?->mail_to,
                    'whatsapp_to' => $row?->whatsapp_to,
                    'status' => $row ? (int) $row->status : 1,
                    'saved' => (bool) $row,
                ];
            })
            ->groupBy('group');

        $whatsappCheck = WhatsApp::diagnose();
        $pdfProblem = GuestDocument::unreachableBecause();

        array_splice($whatsappCheck, -1, 0, [[
            'PDF on WhatsApp',
            $pdfProblem ?: 'on — the link points at ' . GuestDocument::base() . '/guest-doc/…',
            $pdfProblem === null,
        ]]);

        return view('notifications.settings', [
            'groups' => config('notifications.groups'),
            'events' => $events,
            'channels' => NotificationSetting::CHANNELS,
            'defaults' => NotificationSetting::defaults($branchId),
            'mail' => Smtp::status(),
            'mailCheck' => $this->mailCheck(),
            'whatsapp' => WhatsApp::status(),
            'whatsappLive' => WhatsApp::isLive(),
            'whatsappCheck' => $whatsappCheck,
            'pdfProblem' => $pdfProblem,
            'deliveries' => NotificationDelivery::query()
                ->where('branch_id', $branchId)
                ->latest('id')
                ->limit(25)
                ->get(),
        ]);
    }

    public function saveSettings(Request $request): RedirectResponse
    {
        $branchId = (int) Helper::getActiveBranchId();

        $data = $request->validate([
            'defaults' => 'nullable|array',
            'defaults.mail_to' => 'nullable|string|max:2000',
            'defaults.whatsapp_to' => 'nullable|string|max:2000',
            'events' => 'required|array',
            'events.*.channels' => 'nullable|array',
            'events.*.channels.*' => 'in:app,mail,whatsapp',
            'events.*.mail_to' => 'nullable|string|max:2000',
            'events.*.whatsapp_to' => 'nullable|string|max:2000',
            'events.*.status' => 'nullable|boolean',
        ]);

        $known = array_keys(config('notifications.events'));
        $saved = 0;
        NotificationSetting::updateOrCreate(
            ['branch_id' => $branchId, 'event' => NotificationSetting::DEFAULTS],
            [
                'channels' => 'mail,whatsapp',
                'mail_to' => $data['defaults']['mail_to'] ?? null,
                'whatsapp_to' => $data['defaults']['whatsapp_to'] ?? null,
                'status' => 1,
            ]
        );

        foreach ($data['events'] as $event => $row) {
            if (! in_array($event, $known, true)) {
                continue;
            }

            NotificationSetting::updateOrCreate(
                ['branch_id' => $branchId, 'event' => $event],
                [
                    'channels' => implode(',', $row['channels'] ?? []),
                    'mail_to' => $row['mail_to'] ?? null,
                    'whatsapp_to' => $row['whatsapp_to'] ?? null,
                    'status' => ($row['status'] ?? 0) ? 1 : 0,
                ]
            );

            $saved++;
        }

        return back()->with('status', $saved . ' notification setting(s) saved.');
    }
    public function test(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'mail_to' => 'nullable|email',
            'whatsapp_to' => 'nullable|string|max:20',
        ]);

        if (blank($data['mail_to'] ?? null) && blank($data['whatsapp_to'] ?? null)) {
            return back()->with('error', 'Put an email address or a WhatsApp number in first.');
        }

        $notify = Notify::event('system.test')
            ->title('Test message from ' . config('app.name'))
            ->body('If you are reading this, notifications are working. Sent at ' . now()->format('d M Y, h:i A') . '.')
            ->url(url('/'))
            ->force();
        $only = [];

        if (filled($data['mail_to'] ?? null)) {
            $notify->mail($data['mail_to']);
            $only[] = 'mail';
        }

        if (filled($data['whatsapp_to'] ?? null)) {
            $notify->whatsapp($data['whatsapp_to']);
            $only[] = 'whatsapp';
        }

        $notification = $notify->only($only)->send();

        if (! $notification) {
            return back()->with('error', 'The test could not be raised — check storage/logs/laravel.log.');
        }

        $deliveries = $notification->deliveries()->get();
        $failed = $deliveries->where('status', 'failed');

        if ($failed->isNotEmpty()) {
            return back()->with('error', $failed->map(
                fn (NotificationDelivery $d) => ucfirst($d->channel) . ' to ' . $d->target . ' — '
                    . ($d->channel === 'mail' ? Smtp::readable((string) $d->error) : $d->error)
            )->implode(' · '));
        }

        $said = $deliveries->filter(fn (NotificationDelivery $d) => filled($d->error))->map(
            fn (NotificationDelivery $d) => ucfirst($d->channel) . ': ' . $d->error
        );

        return back()->with('status', 'Test sent to ' . $deliveries->pluck('target')->implode(', ')
            . '.' . ($said->isNotEmpty() ? ' The gateway said — ' . $said->implode(' · ') : ''));
    }
    public function probe(): RedirectResponse
    {
        $result = WhatsApp::probe();

        $said = $result['raw'] !== ''
            ? ' Chatway said: ' . \Illuminate\Support\Str::limit($result['raw'], 300)
            : '';

        return back()->with($result['ok'] ? 'status' : 'error', $result['verdict'] . $said);
    }

    public function probeMail(): RedirectResponse
    {
        $result = Smtp::probe();

        $said = $result['raw'] !== ''
            ? ' The server said: ' . \Illuminate\Support\Str::limit($result['raw'], 300)
            : '';

        return back()->with($result['ok'] ? 'status' : 'error', $result['verdict'] . $said);
    }

    private function mailStatus(): string
    {
        $mailer = config('mail.default');

        if ($mailer === 'log' || $mailer === 'array') {
            return 'Not connected — mail is being written to storage/logs/laravel.log instead of sent. '
                . 'Set MAIL_MAILER=smtp in .env.';
        }

        if ($mailer === 'smtp' && blank(config('mail.mailers.smtp.password'))) {
            return 'SMTP is selected but MAIL_PASSWORD in .env is empty, so nothing will send.';
        }

        return 'Connected — sending as ' . (config('mail.from.address') ?: 'the address in .env') . '.';
    }

    /**
     * @return array<int, array{0: string, 1: string, 2: bool|null}>
     */
    private function mailCheck(): array
    {
        $mailer = (string) config('mail.default');
        $password = (string) config('mail.mailers.smtp.password');
        $cached = file_exists(base_path('bootstrap/cache/config.php'));

        $rows = [
            ['Mailer (MAIL_MAILER)', $mailer, ! in_array($mailer, ['log', 'array'], true)],
        ];

        if ($mailer === 'smtp') {
            $rows[] = ['Host', (string) (config('mail.mailers.smtp.host') ?: 'empty'), filled(config('mail.mailers.smtp.host'))];
            $rows[] = ['Port', (string) (config('mail.mailers.smtp.port') ?: 'empty'), filled(config('mail.mailers.smtp.port'))];
            $rows[] = ['Username', (string) (config('mail.mailers.smtp.username') ?: 'empty'), filled(config('mail.mailers.smtp.username'))];
            $gmail = Smtp::isGmail();
            $quoted = preg_match('/^[\'"].*[\'"]$/', $password) === 1;
            $appShaped = preg_match('/^[a-z]{16}$/', $password) === 1;

            $shape = match (true) {
                $password === '' => 'empty',
                Smtp::looksSpaced($password) => strlen($password) . ' characters, with a space in '
                    . 'it — that is nearly always a copy that went wrong',
                $quoted => strlen($password) . ' characters, starting and ending in a quote — '
                    . '.env needs it without quotes',
                $gmail && $appShaped => '16 lowercase letters — that is the shape of a Gmail App '
                    . 'Password',
                $gmail => strlen($password) . ' characters — Gmail only accepts a 16-letter App '
                    . 'Password here, so this looks like an ordinary account password',
                default => strlen($password) . ' characters',
            };

            $rows[] = [
                'Password (MAIL_PASSWORD)',
                str_repeat('•', min(16, max(1, strlen($password)))) . '  ·  ' . $shape,
                $password !== '' && ! $quoted && ! Smtp::looksSpaced($password)
                    && (! $gmail || $appShaped),
            ];
        }

        $rows[] = ['From address', (string) (config('mail.from.address') ?: 'empty'), filled(config('mail.from.address'))];
        $rows[] = [
            'Config cache',
            $cached
                ? 'bootstrap/cache/config.php exists — .env changes are ignored until you run php artisan config:clear'
                : 'off — .env is read live',
            ! $cached,
        ];

        if (in_array($mailer, ['log', 'array'], true)) {
            $verdict = 'Nothing is being emailed. Put MAIL_MAILER=smtp in .env.';
        } elseif ($mailer === 'smtp' && $password === '') {
            $verdict = 'Nothing can be emailed: MAIL_PASSWORD is empty in .env.';
        } elseif ($mailer === 'smtp' && Smtp::looksSpaced($password)) {
            $verdict = 'MAIL_PASSWORD has a space in it, which is nearly always a copy that went '
                . 'wrong. Google shows an App Password as four groups of four; it has to go in as '
                . 'sixteen characters with no spaces.';
        } elseif ($mailer === 'smtp' && Smtp::isGmail() && preg_match('/^[a-z]{16}$/', $password) !== 1) {
            $verdict = 'MAIL_HOST is Gmail, and Gmail refuses ordinary account passwords over SMTP '
                . '— it only takes a 16-letter App Password. Either create one (Google account → '
                . '2-Step Verification → App passwords → Mail) or point MAIL_HOST at your own mail '
                . 'server, where your own password works.';
        } else {

            $verdict = 'Configured. Press "Check the mail server" to find out whether the mail '
                . 'server actually accepts this password.';
        }

        $rows[] = [
            'Verdict',
            $verdict,
            ! in_array($mailer, ['log', 'array'], true)
                && ! ($mailer === 'smtp' && (
                    $password === ''
                    || Smtp::looksSpaced($password)
                    || (Smtp::isGmail() && preg_match('/^[a-z]{16}$/', $password) !== 1)
                )),
        ];

        return $rows;
    }
}
