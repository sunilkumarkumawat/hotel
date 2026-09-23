<?php

use App\Models\Branch\Branch;
use App\Support\NightAudit;
use App\Support\PostingRefused;
use App\Support\WhatsApp;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| php artisan whatsapp:test
|--------------------------------------------------------------------------
| The fastest way to answer "why is nothing going out?". Run it with no
| arguments and it prints what the system thinks it is configured with and
| what it makes of that; run it with a number and it also sends one message
| and prints the gateway's own reply, word for word.
|
|   php artisan whatsapp:test
|   php artisan whatsapp:test 9876543210
|   php artisan whatsapp:test 9876543210 --message="Hello from the front desk"
|
| It deliberately does not go anywhere near the notification settings — no
| event, no channel tick, no recipient list. If this works and the hotel's
| notifications do not, the fault is in the settings, not in the gateway.
*/
Artisan::command('whatsapp:test {number? : the mobile number to message} {--message= : what to say}', function () {
    $this->newLine();
    $this->line('<options=bold>WhatsApp set-up</>');
    $this->newLine();

    foreach (WhatsApp::diagnose() as [$label, $value, $ok]) {
        // Four characters whatever the answer is, so the labels line up.
        $mark = $ok === null ? '[  ]' : ($ok ? '[ok]' : '[NO]');
        $line = sprintf('  %s %-30s %s', $mark, $label, $value);

        $this->line($ok === false ? '<fg=red>' . $line . '</>' : $line);
    }

    $this->newLine();

    $number = $this->argument('number');

    if (blank($number)) {
        /*
         * With no number to send to, ask the gateway whether it knows this
         * account anyway. It messages nobody and it answers the only question
         * worth asking at this point: is the fault the token, or this server?
         */
        $this->line('<options=bold>Asking Chatway whether it knows this account</>');
        $this->line('  (nobody is messaged — the number sent is deliberately not a real one)');
        $this->newLine();

        $probe = WhatsApp::probe();

        if ($probe['raw'] !== '') {
            $this->line('  Chatway said: ' . $probe['raw']);
            $this->newLine();
        }

        $probe['ok'] ? $this->info('  ' . $probe['verdict']) : $this->error('  ' . $probe['verdict']);

        $this->newLine();
        $this->comment('  To send a real message: php artisan whatsapp:test 9876543210');
        $this->newLine();

        return $probe['ok'] ? 0 : 1;
    }

    $text = (string) ($this->option('message')
        ?: 'Test message from ' . config('app.name') . ' at ' . now()->format('d M Y, h:i A') . '.');

    $this->line('  Sending to <options=bold>' . WhatsApp::number((string) $number) . '</> ...');

    try {
        $reply = WhatsApp::send((string) $number, $text);

        if ($reply === 'logged') {
            $this->newLine();
            $this->error('  Not sent. WHATSAPP_DRIVER=log, so it went to storage/logs/laravel.log.');
            $this->newLine();

            return 1;
        }

        $this->newLine();
        $this->info('  Gateway accepted it. Its reply: ' . $reply);
        $this->newLine();

        return 0;
    } catch (Throwable $e) {
        $this->newLine();
        $this->error('  Not sent: ' . $e->getMessage());
        $this->newLine();

        return 1;
    }
})->purpose('Show why WhatsApp is or is not sending, and optionally send one test message');

/*
|--------------------------------------------------------------------------
| php artisan hotel:night-audit
|--------------------------------------------------------------------------
| The same audit the screen runs, from the command line — so a hotel that
| would rather it happened by itself at three in the morning can schedule it:
|
|   php artisan hotel:night-audit                 # every active branch, one night
|   php artisan hotel:night-audit --branch=1      # just this one
|   php artisan hotel:night-audit --catch-up      # keep going until it reaches today
|   php artisan hotel:night-audit --dry-run       # say what it would do, change nothing
|
| --catch-up exists for the hotel that comes back from a long weekend with
| four nights waiting. It still closes them one at a time, each with its own
| figures; it just does not make somebody press the button four times.
|
| Nothing here can double-charge: App\Support\NightAudit refuses a night that
| is already closed, and the room rent underneath it refuses a night it has
| already posted. A cron that fires twice is not a problem.
*/
Artisan::command(
    'hotel:night-audit {--branch= : only this branch id} {--catch-up : close every night up to today} {--dry-run : show what would happen and stop}',
    function () {
        $dry = (bool) $this->option('dry-run');

        $branches = Branch::query()
            ->when($this->option('branch'), fn ($q) => $q->where('id', (int) $this->option('branch')))
            ->where('status', 1)
            ->orderBy('id')
            ->get(['id', 'branch_name']);

        if ($branches->isEmpty()) {
            $this->error('No active branch matched.');

            return 1;
        }

        foreach ($branches as $branch) {
            $this->newLine();
            $this->line('<options=bold>' . $branch->branch_name . '</> (branch ' . $branch->id . ')');

            // A guard rather than a loop condition: --catch-up on a hotel that
            // has never audited would otherwise walk back to the year dot.
            for ($pass = 0; $pass < 60; $pass++) {
                $date = NightAudit::businessDate((int) $branch->id);
                $preview = NightAudit::preview((int) $branch->id, $date);

                if ($preview['closed']) {
                    $this->line('  Nothing to do — ' . $date . ' is already audited.');
                    break;
                }

                foreach ($preview['checks'] as $check) {
                    if ($check['count'] > 0 && $check['tone'] !== 'info') {
                        $this->line('  <fg=yellow>!</> ' . $check['label'] . ': ' . $check['count']);
                    }
                }

                if ($dry) {
                    $this->line('  Would close ' . $date . ' — ' . $preview['stays'] . ' occupied '
                        . \Illuminate\Support\Str::plural('room', $preview['stays']) . '. (dry run, nothing written)');
                    break;
                }

                try {
                    // Nobody is signed in on a cron, so the close is recorded
                    // against no user rather than against whoever ran it last.
                    $day = NightAudit::run((int) $branch->id, null, 'Closed by php artisan hotel:night-audit', $date);
                } catch (PostingRefused $e) {
                    $this->error('  ' . $e->getMessage());
                    break;
                }

                NightAudit::announce($day);

                $this->info(sprintf(
                    '  Closed %s — %d room %s posted, %d no %s, %d rooms sold.',
                    $date,
                    $day->nights_posted, \Illuminate\Support\Str::plural('night', $day->nights_posted),
                    $day->no_shows, \Illuminate\Support\Str::plural('show', $day->no_shows),
                    $day->rooms_sold,
                ));

                if (! $this->option('catch-up')) {
                    break;
                }
            }
        }

        $this->newLine();

        return 0;
    }
)->purpose('Close the hotel\'s day: post room rent, mark no-shows, freeze the figures');
