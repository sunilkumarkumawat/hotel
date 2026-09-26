<?php

namespace App\Support;

/**
 * Asking the mail server, in its own words, whether it will take this password.
 *
 * Laravel will happily tell you mail is "configured" the moment MAIL_PASSWORD
 * has something in it. The mail server is the only thing that knows whether
 * that something is right, and it will not say so until a message is actually
 * sent — by which time the failure is buried in a hundred-line exception on a
 * screen somebody was not watching.
 *
 * So this does what sending does, and stops one step short: connect, start TLS,
 * log in, and hang up without sending anything. It speaks SMTP over a plain
 * socket because SMTP is six commands and a reply code, and because the
 * alternative is a dependency for something PHP can already do.
 *
 * {@see readable()} is the other half: Gmail's refusal arrives as three nested
 * authenticator failures and a support URL, and what the person needs out of
 * all that is one sentence saying their account password will never work here.
 */
class Smtp
{
    /**
     * Connect, log in, hang up. Nothing is sent to anybody.
     *
     * @return array{ok: bool, raw: string, verdict: string}
     */
    public static function probe(): array
    {
        $mailer = (string) config('mail.default');

        if (in_array($mailer, ['log', 'array'], true)) {
            return self::no('Nothing was checked: MAIL_MAILER=' . $mailer . ' in .env, so this '
                . 'system is writing mail to a log file rather than sending it.');
        }

        if ($mailer !== 'smtp') {
            return self::no('This check only knows how to talk to an SMTP server, and MAIL_MAILER '
                . 'is ' . $mailer . '.');
        }

        $host = (string) config('mail.mailers.smtp.host');
        $port = (int) config('mail.mailers.smtp.port');
        $user = (string) config('mail.mailers.smtp.username');
        $password = (string) config('mail.mailers.smtp.password');

        if ($host === '' || $port === 0) {
            return self::no('Nothing was checked: MAIL_HOST or MAIL_PORT is empty in .env.');
        }

        if ($password === '') {
            return self::no('Nothing was checked: MAIL_PASSWORD is empty in .env.');
        }

        // 465 is TLS from the first byte; 587 starts in the clear and upgrades.
        // Mirrors MailManager::createSmtpTransport()'s own fallback exactly, so
        // this probe never disagrees with how the real send will connect.
        $direct = $port === 465 || in_array((string) config('mail.mailers.smtp.scheme'), ['smtps', 'ssl'], true);
        $address = ($direct ? 'ssl://' : 'tcp://') . $host . ':' . $port;

        $socket = @stream_socket_client(
            $address,
            $errorNumber,
            $errorText,
            8,
            STREAM_CLIENT_CONNECT,
            stream_context_create(['ssl' => array_filter([
                'SNI_enabled' => true,
                // Windows PHP ships without a certificate bundle, so the
                // encrypted part of this check would fail on a XAMPP box for a
                // reason that has nothing to do with the password. The same
                // bundle the WhatsApp sender falls back on is used here.
                'cafile' => self::certificates(),
            ])])
        );

        if (! $socket) {
            return self::no(
                'This server could not reach ' . $host . ':' . $port . ' at all'
                . ($errorText ? ' — ' . $errorText : '') . '. That is the network between this '
                . 'computer and the mail server, not the password. Some offices and hosts block '
                . 'outgoing mail ports.',
                (string) $errorText
            );
        }

        stream_set_timeout($socket, 8);

        $log = [];

        try {
            $greeting = self::read($socket, $log);

            if (! str_starts_with($greeting, '2')) {
                return self::no('The mail server answered but did not want to talk: ' . $greeting, implode("\n", $log));
            }

            $ehlo = parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'localhost';

            self::say($socket, 'EHLO ' . $ehlo, $log);

            if (! $direct) {
                $start = self::say($socket, 'STARTTLS', $log);

                if (! str_starts_with($start, '220')) {
                    return self::no('The mail server refused to start an encrypted connection: '
                        . $start, implode("\n", $log));
                }

                if (! @stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    return self::no('The encrypted connection could not be started. On XAMPP this '
                        . 'is usually the missing certificate bundle — the same one the WhatsApp '
                        . 'check names.', implode("\n", $log));
                }

                // A server may answer differently once encrypted, so it is
                // greeted again — which is what the protocol asks for.
                self::say($socket, 'EHLO ' . $ehlo, $log);
            }

            $auth = self::say($socket, 'AUTH LOGIN', $log);

            if (! str_starts_with($auth, '334')) {
                return self::no('The mail server would not accept a username and password: '
                    . $auth, implode("\n", $log));
            }

            self::say($socket, base64_encode($user), $log, hide: true);
            $result = self::say($socket, base64_encode($password), $log, hide: true);

            self::say($socket, 'QUIT', $log);

            if (str_starts_with($result, '235')) {
                return [
                    'ok' => true,
                    'raw' => $result,
                    'verdict' => 'The mail server accepted the username and password. Sending should '
                        . 'work — use Send a test below to prove it.',
                ];
            }

            return self::no(self::readable($result), self::isKnown($result) ? '' : $result);
        } finally {
            @fclose($socket);
        }
    }

    /**
     * Did we recognise this failure well enough to say what to do about it?
     *
     * When we did, the verdict already contains the instructions and pasting
     * the server's raw reply after it just makes a wall of text nobody reads.
     * When we did not, the raw reply is the only real information there is.
     */
    public static function isKnown(string $error): bool
    {
        $lower = strtolower(trim($error));

        foreach ([
            '5.7.8', 'username and password not accepted', 'bad credentials', 'invalid credentials',
            'application-specific password', '5.7.0', 'could not open socket',
            'connection could not be established', 'connection timed out', 'connection refused',
            'certificate', '5.7.1', 'not allowed to send',
        ] as $phrase) {
            if (str_contains($lower, $phrase)) {
                return true;
            }
        }

        return false;
    }

    /**
     * A mail failure, in one sentence somebody can act on.
     *
     * Gmail's refusal arrives as three authenticators failing in turn, each
     * with its own quoted reply and a support link. All of it means one thing,
     * and that one thing is what goes on the screen.
     */
    public static function readable(string $error): string
    {
        $text = trim($error);

        if ($text === '') {
            return 'The mail server refused the message without saying why.';
        }

        $lower = strtolower($text);

        /*
         * The one that catches almost everybody — but only on Gmail. Gmail's
         * sixteen-character App Password is GOOGLE'S rule, not this system's:
         * nothing here checks a length, and any other mail server takes
         * whatever password its own account was given, six characters or sixty.
         * Saying "you need a 16-character App Password" to somebody using their
         * own hosting would send them hunting for a setting that does not
         * exist.
         */
        if (str_contains($lower, '5.7.8') || str_contains($lower, 'username and password not accepted')
            || str_contains($lower, 'bad credentials') || str_contains($lower, 'invalid credentials')) {
            return self::isGmail()
                ? 'Gmail refused the password. It does not accept the password you sign in to '
                    . 'Gmail with — Google insists on a 16-character App Password for this. In the '
                    . 'Google account: turn on 2-Step Verification, then Security → App passwords '
                    . '→ Mail, and put those 16 characters on MAIL_PASSWORD in .env with no spaces. '
                    . 'To use your own password instead, point MAIL_HOST at your own mail server '
                    . 'rather than at Gmail. Then run php artisan config:clear.'
                : 'The mail server refused this username and password. Check MAIL_USERNAME and '
                    . 'MAIL_PASSWORD in .env against what your mail provider gave you — the '
                    . 'username is usually the full email address. Then run '
                    . 'php artisan config:clear.';
        }

        if (str_contains($lower, 'application-specific password')) {
            return 'Gmail is asking for an App Password. 2-Step Verification is on for this '
                . 'account, so the normal password will never work here: Security → App passwords '
                . '→ Mail, and put those 16 characters on MAIL_PASSWORD in .env.';
        }

        if (str_contains($lower, '5.7.0') && str_contains($lower, 'authentication required')) {
            return 'The mail server wants a login and got none — MAIL_USERNAME or MAIL_PASSWORD '
                . 'is empty in .env.';
        }

        if (str_contains($lower, 'could not open socket') || str_contains($lower, 'connection could not be established')
            || str_contains($lower, 'connection timed out') || str_contains($lower, 'connection refused')) {
            return 'This server could not reach the mail server at all. That is the network, not '
                . 'the password — many offices and some hosts block outgoing mail ports.';
        }

        if (str_contains($lower, 'certificate')) {
            return 'The encrypted connection to the mail server failed a certificate check. On '
                . 'XAMPP this is the missing certificate bundle — the same one the WhatsApp check '
                . 'names.';
        }

        if (str_contains($lower, '5.7.1') || str_contains($lower, 'not allowed to send')) {
            return 'The mail server accepted the login but will not send from this address. '
                . 'MAIL_FROM_ADDRESS has to be an address this account is allowed to send as.';
        }

        // Nothing recognised: hand back the first sentence rather than the
        // whole stack, because the first sentence is where the reason lives.
        $first = preg_split('/(?<=\.)\s|\n/', $text)[0] ?? $text;

        return rtrim(mb_substr(trim($first), 0, 300));
    }

    /** What the settings screen says, before anybody presses anything. */
    public static function status(): string
    {
        $mailer = (string) config('mail.default');

        if (in_array($mailer, ['log', 'array'], true)) {
            return 'Not connected — mail is being written to storage/logs/laravel.log instead of '
                . 'sent. Set MAIL_MAILER=smtp in .env.';
        }

        if ($mailer === 'smtp' && blank(config('mail.mailers.smtp.password'))) {
            return 'SMTP is selected but MAIL_PASSWORD in .env is empty, so nothing will send.';
        }

        /*
         * Deliberately not "Connected". Nothing here has spoken to the mail
         * server, and saying otherwise is how somebody spends an afternoon
         * wondering why a screen that says connected sends nothing.
         */
        return 'Set up to send as ' . (config('mail.from.address') ?: 'the address in .env')
            . '. Press "Check the mail server" below to find out whether the password works.';
    }

    /**
     * Is this account on Gmail?
     *
     * It matters because every piece of advice about App Passwords is Google's
     * rule and nobody else's. A hotel using its own domain's mail sets whatever
     * password it likes, and telling it about 2-Step Verification would be
     * sending it to look for a screen that is not there.
     */
    public static function isGmail(): bool
    {
        $host = strtolower((string) config('mail.mailers.smtp.host'));

        return str_contains($host, 'gmail.com') || str_contains($host, 'googlemail.com');
    }

    /**
     * Does this password look like it was pasted with spaces still in it?
     *
     * Google shows an App Password as four groups of four and copying it that
     * way is the commonest reason a correct password is refused — but a space
     * in the middle of any password is nearly always a copy that went wrong, so
     * this does not care how long it is.
     */
    public static function looksSpaced(string $password): bool
    {
        return str_contains(trim($password), ' ');
    }

    /*
    |--------------------------------------------------------------------------
    | Talking SMTP
    |--------------------------------------------------------------------------
    */

    /**
     * A certificate bundle to verify with, or null to let PHP decide.
     *
     * Null when php.ini already knows where the certificates are — a machine
     * somebody has configured keeps its own answer.
     */
    private static function certificates(): ?string
    {
        if (filled(ini_get('curl.cainfo')) || filled(ini_get('openssl.cafile'))) {
            return null;
        }

        $bundle = base_path(WhatsApp::CA_BUNDLE);

        return is_file($bundle) ? $bundle : null;
    }

    /** @param  array<int, string>  $log */
    private static function say($socket, string $line, array &$log, bool $hide = false): string
    {
        $log[] = '> ' . ($hide ? '(hidden)' : $line);

        fwrite($socket, $line . "\r\n");

        return self::read($socket, $log);
    }

    /**
     * Read one reply, however many lines it runs to.
     *
     * SMTP continues a reply with a hyphen after the code — "250-STARTTLS" —
     * and ends it with a space. Reading one line would leave the rest in the
     * pipe and every later command would read the wrong answer.
     *
     * @param  array<int, string>  $log
     */
    private static function read($socket, array &$log): string
    {
        $reply = '';

        while (! feof($socket)) {
            $line = fgets($socket, 1024);

            if ($line === false) {
                break;
            }

            $reply .= $line;
            $log[] = '< ' . rtrim($line);

            // "250 " ends it; "250-" means another line is coming.
            if (strlen($line) >= 4 && $line[3] === ' ') {
                break;
            }
        }

        return trim($reply);
    }

    /** @return array{ok: bool, raw: string, verdict: string} */
    private static function no(string $verdict, string $raw = ''): array
    {
        return ['ok' => false, 'raw' => $raw, 'verdict' => $verdict];
    }
}
