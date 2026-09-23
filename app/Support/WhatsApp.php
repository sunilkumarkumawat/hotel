<?php

namespace App\Support;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Sending a WhatsApp message, whoever the hotel buys that from.
 *
 * Five drivers, picked with `WHATSAPP_DRIVER` in .env and documented in
 * config/services.php. The default is `chatway`, which is the gateway this
 * hotel already has an account with.
 *
 * **Why this talks to the network with cURL and not with Laravel's `Http`.**
 * `Http::get()` is a wrapper around Guzzle, and Guzzle is a separate package.
 * This install does not have it — composer.json asks for `laravel/framework`
 * and nothing else — so every call through `Http` died with
 * `Class "GuzzleHttp\Client" not found`, the exception was caught one level up,
 * and the delivery log filled with failures nobody could read. cURL is built
 * into PHP, it is what the hotel's own working snippet used, and it needs no
 * `composer require`. A `file_get_contents` fallback covers the rare build with
 * cURL switched off.
 *
 * `send()` either returns or throws. It never returns false: a caller that
 * wants to record a failure needs the reason, and a boolean does not carry one.
 * {@see Notify} and {@see GuestMessage} catch and write it to
 * `notification_deliveries`, so nothing here can break a booking.
 */
class WhatsApp
{
    /**
     * Which build of this file is running.
     *
     * It exists so that "it still does not work" can be answered without
     * guessing. The settings screen prints it, so one screenshot says whether
     * the machine is running this code or the copy from two days ago — which
     * is otherwise impossible to tell apart from a real fault.
     */
    public const BUILD = '2026-09-17 · curl + dotted-config + ca-bundle';

    /**
     * The root certificates to fall back on, relative to the project folder.
     *
     * PHP on Windows ships without any, so unless php.ini has been told where
     * they are, every HTTPS call fails with "unable to get local issuer
     * certificate" — the commonest way a gateway that works everywhere else
     * stops working on a XAMPP machine.
     */
    public const CA_BUNDLE = 'resources/certs/cacert.pem';

    /**
     * `$fileUrl` is a publicly reachable link — a PDF bill, a registration
     * card. Only the gateways that can carry one use it; the rest ignore it
     * rather than failing, because a message that arrived without its
     * attachment beats no message at all.
     *
     * @throws RuntimeException when the provider refuses, cannot be reached,
     *                          or has not been configured
     */
    public static function send(string $to, string $message, ?string $fileUrl = null): string
    {
        $to = self::number($to);

        if ($to === '') {
            throw new RuntimeException('That does not look like a phone number.');
        }

        $config = config('services.whatsapp');
        $driver = $config['driver'] ?? 'chatway';

        // `log` is a deliberate choice — somebody set it while testing — so it
        // stays quiet and writes the message to the log file.
        if ($driver === 'log') {
            return self::log($to, $message, $fileUrl);
        }

        /*
         * A real driver with its keys missing used to fall through to the log
         * as well, and that is exactly how a hotel ends up believing messages
         * are going out when they are not: the delivery log said "sent", the
         * screen said nothing, and the guest's phone stayed silent. It now
         * throws, with the .env line that needs filling in named in the text,
         * so the reason is on the screen instead of in somebody's guess.
         */
        if ($missing = self::missing()) {
            throw new RuntimeException(
                'WhatsApp is not set up: ' . implode(' and ', $missing) . ' '
                . (count($missing) === 1 ? 'is' : 'are') . ' empty in .env. '
                . 'Fill it in and run: php artisan config:clear'
            );
        }

        return match ($driver) {
            'chatway' => self::chatway($to, $message, $fileUrl, $config),
            'meta' => self::meta($to, $message, $config),
            'twilio' => self::twilio($to, $message, $config),
            'gateway' => self::gateway($to, $message, $config),
            default => throw new RuntimeException(
                'WHATSAPP_DRIVER=' . $driver . ' is not a driver this system knows. '
                . 'Use chatway, meta, twilio, gateway or log.'
            ),
        };
    }

    /** True when a real provider is configured — the settings screen says so. */
    public static function isLive(): bool
    {
        $driver = config('services.whatsapp.driver', 'log');

        if ($driver === 'log') {
            return false;
        }

        return self::missing() === [];
    }

    /**
     * The .env lines the selected driver needs and does not have.
     *
     * Naming them is the whole point: "WhatsApp is not configured" sends
     * somebody hunting through four files, "WHATSAPP_TOKEN is empty" does not.
     *
     * @return array<int, string>
     */
    public static function missing(): array
    {
        $config = config('services.whatsapp');
        $driver = $config['driver'] ?? 'log';

        $needed = match ($driver) {
            'chatway' => ['WHATSAPP_USERNAME' => 'username', 'WHATSAPP_TOKEN' => 'token'],
            'meta' => ['WHATSAPP_PHONE_ID' => 'phone_id', 'WHATSAPP_TOKEN' => 'token'],
            'twilio' => [
                'WHATSAPP_TWILIO_SID' => 'twilio_sid',
                'WHATSAPP_TWILIO_TOKEN' => 'twilio_token',
                'WHATSAPP_FROM' => 'from',
            ],
            'gateway' => ['WHATSAPP_URL' => 'url'],
            default => [],
        };

        $missing = [];

        foreach ($needed as $line => $key) {
            if (blank($config[$key] ?? null)) {
                $missing[] = $line;
            }
        }

        return $missing;
    }

    /** What to tell the person setting this up. */
    public static function status(): string
    {
        $driver = config('services.whatsapp.driver', 'log');

        if ($driver === 'log') {
            return 'Not connected — WHATSAPP_DRIVER=log in .env, so messages are '
                . 'written to storage/logs/laravel.log instead of being sent. '
                . 'Change it to WHATSAPP_DRIVER=chatway to send for real.';
        }

        if ($missing = self::missing()) {
            return ucfirst($driver) . ' is selected but ' . implode(' and ', $missing)
                . ' ' . (count($missing) === 1 ? 'is' : 'are') . ' empty in .env, '
                . 'so nothing can be sent. Fill it in, then run php artisan config:clear.';
        }

        return 'Connected through ' . $driver
            . ($driver === 'chatway' ? ' as ' . config('services.whatsapp.username') : '')
            . ' using ' . self::transport() . '.';
    }

    /**
     * Everything the settings screen shows in its "Why is nothing sending?"
     * card — and everything `php artisan whatsapp:test` prints.
     *
     * Each row is [label, value, ok] where `ok` is true, false, or null when
     * the row is information rather than a verdict.
     *
     * @return array<int, array{0: string, 1: string, 2: bool|null}>
     */
    public static function diagnose(): array
    {
        $config = config('services.whatsapp');
        $driver = (string) ($config['driver'] ?? 'log');
        $missing = self::missing();
        $cached = file_exists(base_path('bootstrap/cache/config.php'));

        $rows = [
            ['Build running', self::BUILD, null],
            ['Driver (WHATSAPP_DRIVER)', $driver, $driver !== 'log'],
        ];

        if ($driver === 'chatway') {
            $rows[] = ['Username (WHATSAPP_USERNAME)',
                filled($config['username'] ?? null) ? (string) $config['username'] : 'empty',
                filled($config['username'] ?? null)];
            $rows[] = ['Token (WHATSAPP_TOKEN)',
                self::mask((string) ($config['token'] ?? '')),
                filled($config['token'] ?? null)];
            $rows[] = ['Gateway URL', (string) ($config['chatway_url'] ?: 'https://int.chatway.in/api/send-msg'), null];
        } elseif ($driver === 'meta') {
            $rows[] = ['Phone number id', (string) ($config['phone_id'] ?: 'empty'), filled($config['phone_id'] ?? null)];
            $rows[] = ['Token (WHATSAPP_TOKEN)', self::mask((string) ($config['token'] ?? '')), filled($config['token'] ?? null)];
        } elseif ($driver === 'twilio') {
            $rows[] = ['Twilio SID', self::mask((string) ($config['twilio_sid'] ?? '')), filled($config['twilio_sid'] ?? null)];
            $rows[] = ['Twilio token', self::mask((string) ($config['twilio_token'] ?? '')), filled($config['twilio_token'] ?? null)];
            $rows[] = ['From', (string) ($config['from'] ?: 'empty'), filled($config['from'] ?? null)];
        } elseif ($driver === 'gateway') {
            $rows[] = ['Gateway URL', (string) ($config['url'] ?: 'empty'), filled($config['url'] ?? null)];
        }

        $rows[] = ['Country code', (string) ($config['country_code'] ?: '91'), null];
        $rows[] = ['How PHP will call it', self::transport(), self::transport() !== 'nothing'];
        $rows[] = ['HTTPS certificates', self::certificates(), ! str_contains(self::certificates(), 'may fail')];
        $rows[] = [
            'Config cache',
            $cached
                ? 'bootstrap/cache/config.php exists — .env changes are ignored until you run php artisan config:clear'
                : 'off — .env is read live',
            ! $cached,
        ];

        if ($driver === 'log') {
            $verdict = 'Nothing is being sent. WHATSAPP_DRIVER is log. '
                . 'Put WHATSAPP_DRIVER=chatway in .env.';
        } elseif ($missing) {
            $verdict = 'Nothing can be sent: ' . implode(' and ', $missing) . ' empty in .env.';
        } elseif (self::transport() === 'nothing') {
            $verdict = 'Nothing can be sent: PHP has neither cURL nor allow_url_fopen. '
                . 'Switch on the php_curl extension in php.ini.';
        } else {
            $verdict = 'Ready to send. If a message still does not arrive, send a test '
                . 'below — the gateway\'s own reply appears in the delivery log.';
        }

        $rows[] = ['Verdict', $verdict, $driver !== 'log' && ! $missing && self::transport() !== 'nothing'];

        return $rows;
    }

    /**
     * Ask the gateway whether it knows this account, without messaging anyone.
     *
     * The number sent is `1`, which is not a phone number, so Chatway gets as
     * far as checking the username and token and then complains about the
     * number. That complaint is the answer: an account it does not recognise
     * fails earlier, with different words. Nobody's phone rings either way.
     *
     * This is the check that settles "is it my token or is it my server?" from
     * the machine that actually has to do the sending, which is the only
     * machine whose answer means anything.
     *
     * @return array{ok: bool, raw: string, verdict: string}
     */
    public static function probe(): array
    {
        $config = config('services.whatsapp');
        $driver = (string) ($config['driver'] ?? 'chatway');

        if ($driver === 'log') {
            return [
                'ok' => false,
                'raw' => '',
                'verdict' => 'Nothing was checked: WHATSAPP_DRIVER=log in .env, so this system is '
                    . 'not set up to talk to a gateway at all.',
            ];
        }

        if ($driver !== 'chatway') {
            return [
                'ok' => false,
                'raw' => '',
                'verdict' => 'This check only knows how to talk to Chatway, and WHATSAPP_DRIVER is '
                    . $driver . '. Use the Send a test box instead.',
            ];
        }

        if ($missing = self::missing()) {
            return [
                'ok' => false,
                'raw' => '',
                'verdict' => 'Nothing was checked: ' . implode(' and ', $missing) . ' empty in .env.',
            ];
        }

        try {
            $reply = self::call('GET', (string) ($config['chatway_url'] ?: 'https://int.chatway.in/api/send-msg'), [
                'query' => [
                    'username' => $config['username'],
                    'number' => '1',
                    'message' => 'connection check',
                    'token' => $config['token'],
                ],
                'timeout' => (int) ($config['timeout'] ?: 12),
            ]);
        } catch (RuntimeException $e) {
            return [
                'ok' => false,
                'raw' => $e->getMessage(),
                'verdict' => 'This server could not reach int.chatway.in at all, so no message can '
                    . 'leave it. This is a network or certificate problem on the server, not a '
                    . 'problem with the Chatway account.',
            ];
        }

        $raw = trim($reply['body']);
        $lower = strtolower($raw);

        foreach (['invalid user token', 'invalid token', 'token expired', 'unauthor'] as $phrase) {
            if (str_contains($lower, $phrase)) {
                return [
                    'ok' => false,
                    'raw' => $raw,
                    'verdict' => 'This server reached Chatway, and Chatway refused the token on the '
                        . 'WHATSAPP_TOKEN line in .env. Paste the token again and run '
                        . 'php artisan config:clear.',
                ];
            }
        }

        // Deliberately narrow. A broad word like "account" would catch
        // "insufficient account balance" and send somebody off to check a
        // username that was never the problem.
        foreach (['username is required', 'user not found', 'invalid username'] as $phrase) {
            if (str_contains($lower, $phrase)) {
                return [
                    'ok' => false,
                    'raw' => $raw,
                    'verdict' => 'This server reached Chatway, and Chatway did not accept the '
                        . 'WHATSAPP_USERNAME line in .env.',
                ];
            }
        }

        return [
            'ok' => true,
            'raw' => $raw,
            'verdict' => 'This server reached Chatway and Chatway accepted the username and token. '
                . 'The number sent was deliberately not a real one, so a complaint about the number '
                . 'is the right answer — it means everything except the number was fine. Sending to '
                . 'a real phone should now work; use Send a test below to prove it.',
        ];
    }

    /**
     * The root certificates to hand cURL, or null to leave it alone.
     *
     * Null means php.ini has already been told where they are, and a machine
     * somebody has configured keeps its own answer. Otherwise: the copy
     * shipped with this project first, then the ones XAMPP and WAMP install
     * and never point php.ini at — which is why HTTPS works in the browser on
     * those machines and fails from PHP on the very same box.
     */
    private static function caBundle(): ?string
    {
        if (filled(ini_get('curl.cainfo')) || filled(ini_get('openssl.cafile'))) {
            return null;
        }

        foreach (self::caCandidates() as $path) {
            if (is_file($path)) {
                return $path;
            }
        }

        return null;
    }

    /** @return array<int, string> */
    private static function caCandidates(): array
    {
        return [
            base_path(self::CA_BUNDLE),
            'C:/xampp/php/extras/ssl/cacert.pem',
            'C:/xampp/apache/bin/curl-ca-bundle.crt',
            'C:/wamp64/bin/php/cacert.pem',
            'C:/laragon/etc/ssl/cacert.pem',
            '/etc/ssl/certs/ca-certificates.crt',
            '/etc/pki/tls/certs/ca-bundle.crt',
        ];
    }

    /** Where PHP will look for the root certificates an HTTPS call needs. */
    private static function certificates(): string
    {
        foreach (['curl.cainfo', 'openssl.cafile'] as $setting) {
            if (filled(ini_get($setting))) {
                return 'php.ini ' . $setting . ' → ' . ini_get($setting);
            }
        }

        $bundle = self::caBundle();

        return $bundle
            ? 'php.ini says nothing, so this is used instead: ' . $bundle
            : 'php.ini says nothing and no certificate bundle was found — HTTPS may fail';
    }

    /*
    |--------------------------------------------------------------------------
    | Drivers
    |--------------------------------------------------------------------------
    */

    /**
     * int.chatway.in — the gateway this hotel already uses.
     *
     * Its API is a GET with everything in the query string, which is why the
     * parameters are built with http_build_query rather than pasted together:
     * a guest name with an & in it, or a message with a # in it, would
     * otherwise truncate the message halfway.
     *
     * The number is sent in full international form (919876543210). The
     * hotel's original snippet wrote `number=91` . $mobile, which is the same
     * thing for an Indian mobile and wrong for anybody else's — this way a
     * foreign guest's number reaches them too.
     */
    private static function chatway(string $to, string $message, ?string $fileUrl, array $config): string
    {
        $query = [
            'username' => $config['username'],
            'number' => $to,
            'message' => $message,
            'token' => $config['token'],
        ];

        if (filled($fileUrl)) {
            $query['fileurl'] = $fileUrl;
        }

        $reply = self::call(
            'GET',
            ($config['chatway_url'] ?: 'https://int.chatway.in/api/send-msg'),
            ['query' => $query, 'timeout' => (int) ($config['timeout'] ?: 12)]
        );

        // What was actually asked for, with the token blanked. A delivery log
        // row that says only "it failed" starts an argument; one that shows the
        // number and the account it went out under ends it.
        $trace = ' [sent number=' . $to . ' as ' . $config['username'] . ']';

        if ($reply['status'] >= 400) {
            throw new RuntimeException(
                'Chatway refused the message (HTTP ' . $reply['status'] . '). '
                . Str::limit(trim($reply['body']), 160) . $trace
            );
        }

        /*
         * Chatway answers 200 even when it did not send — the reason is in the
         * body. Treating a 200 as success would mean the delivery log said
         * "sent" for messages that never left, which is the one thing that log
         * exists to prevent.
         */
        if ($refusal = self::refusal($reply['body'])) {
            throw new RuntimeException($refusal . $trace);
        }

        $body = trim($reply['body']);

        return $body !== '' ? Str::limit($body, 200) : 'sent';
    }

    private static function meta(string $to, string $message, array $config): string
    {
        $url = sprintf(
            'https://graph.facebook.com/%s/%s/messages',
            $config['api_version'] ?: 'v21.0',
            $config['phone_id']
        );

        $reply = self::call('POST', $url, [
            'json' => [
                'messaging_product' => 'whatsapp',
                'recipient_type' => 'individual',
                'to' => $to,
                'type' => 'text',
                'text' => ['preview_url' => false, 'body' => $message],
            ],
            'headers' => ['Authorization: Bearer ' . $config['token']],
            'timeout' => (int) ($config['timeout'] ?: 12),
        ]);

        $json = json_decode($reply['body'], true);

        if ($reply['status'] >= 400) {
            /*
             * Meta puts the useful part in error.message and the useless part
             * in a 200-character envelope, so the envelope is dropped. A desk
             * clerk reading the delivery log needs "Recipient not in allowed
             * list", not a request id.
             */
            throw new RuntimeException(
                (is_array($json) ? ($json['error']['message'] ?? null) : null)
                    ?: 'Meta refused the message (HTTP ' . $reply['status'] . ').'
            );
        }

        return (string) (is_array($json) ? ($json['messages'][0]['id'] ?? 'sent') : 'sent');
    }

    private static function twilio(string $to, string $message, array $config): string
    {
        $url = sprintf(
            'https://api.twilio.com/2010-04-01/Accounts/%s/Messages.json',
            $config['twilio_sid']
        );

        $reply = self::call('POST', $url, [
            'form' => [
                'From' => $config['from'],
                'To' => 'whatsapp:+' . $to,
                'Body' => $message,
            ],
            'basic' => [(string) $config['twilio_sid'], (string) $config['twilio_token']],
            'timeout' => (int) ($config['timeout'] ?: 12),
        ]);

        $json = json_decode($reply['body'], true);

        if ($reply['status'] >= 400) {
            throw new RuntimeException(
                (is_array($json) ? ($json['message'] ?? null) : null)
                    ?: 'Twilio refused the message (HTTP ' . $reply['status'] . ').'
            );
        }

        return (string) (is_array($json) ? ($json['sid'] ?? 'sent') : 'sent');
    }

    private static function gateway(string $to, string $message, array $config): string
    {
        $extra = json_decode((string) ($config['extra'] ?? ''), true);

        $headers = [];

        if (filled($config['token'] ?? null)) {
            $headers[] = 'Authorization: Bearer ' . $config['token'];
        }

        $reply = self::call('POST', (string) $config['url'], [
            'json' => ['to' => $to, 'message' => $message] + (is_array($extra) ? $extra : []),
            'headers' => $headers,
            'timeout' => (int) ($config['timeout'] ?: 12),
        ]);

        if ($reply['status'] >= 400) {
            throw new RuntimeException('The gateway refused the message (HTTP ' . $reply['status'] . ').');
        }

        return 'sent';
    }

    private static function log(string $to, string $message, ?string $fileUrl = null): string
    {
        Log::info('[WhatsApp] to ' . $to . ': ' . $message . ($fileUrl ? ' [file: ' . $fileUrl . ']' : ''));

        return 'logged';
    }

    /*
    |--------------------------------------------------------------------------
    | The network
    |--------------------------------------------------------------------------
    */

    /** Which of PHP's own ways out of the building is available. */
    public static function transport(): string
    {
        if (function_exists('curl_init')) {
            return 'cURL';
        }

        if (filter_var(ini_get('allow_url_fopen'), FILTER_VALIDATE_BOOL)) {
            return 'allow_url_fopen';
        }

        return 'nothing';
    }

    /**
     * One HTTP call, with no package behind it.
     *
     * @param  array{query?: array<string, mixed>, json?: array<string, mixed>, form?: array<string, mixed>, headers?: array<int, string>, basic?: array<int, string>, timeout?: int}  $options
     * @return array{status: int, body: string}
     *
     * @throws RuntimeException when the call cannot be made at all
     */
    private static function call(string $method, string $url, array $options = []): array
    {
        if (blank($url)) {
            throw new RuntimeException('No gateway URL is configured.');
        }

        if (! empty($options['query'])) {
            $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query($options['query']);
        }

        $headers = $options['headers'] ?? [];
        $body = null;

        if (isset($options['json'])) {
            $body = json_encode($options['json']);
            $headers[] = 'Content-Type: application/json';
            $headers[] = 'Accept: application/json';
        } elseif (isset($options['form'])) {
            $body = http_build_query($options['form']);
            $headers[] = 'Content-Type: application/x-www-form-urlencoded';
        }

        if (! empty($options['basic'])) {
            $headers[] = 'Authorization: Basic ' . base64_encode($options['basic'][0] . ':' . $options['basic'][1]);
        }

        $timeout = max(3, (int) ($options['timeout'] ?? 12));

        if (function_exists('curl_init')) {
            return self::curl($method, $url, $headers, $body, $timeout);
        }

        if (filter_var(ini_get('allow_url_fopen'), FILTER_VALIDATE_BOOL)) {
            return self::stream($method, $url, $headers, $body, $timeout);
        }

        throw new RuntimeException(
            'PHP cannot make an outgoing request: the cURL extension is off and so is '
            . 'allow_url_fopen. Switch on php_curl in php.ini and restart the server.'
        );
    }

    /**
     * @param  array<int, string>  $headers
     * @return array{status: int, body: string}
     */
    private static function curl(string $method, string $url, array $headers, ?string $body, int $timeout): array
    {
        $handle = curl_init();

        curl_setopt_array($handle, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => min($timeout, 8),
            CURLOPT_CUSTOMREQUEST => $method,
        ]);

        if ($headers) {
            curl_setopt($handle, CURLOPT_HTTPHEADER, $headers);
        }

        /*
         * Only when php.ini has not been told where the root certificates are.
         * A machine that has been configured keeps its own setting; a Windows
         * machine that has not gets a bundle found on disk instead of an
         * "unable to get local issuer certificate" it cannot act on.
         * Verification itself is never switched off — a connection that only
         * pretends to be secure is worse than one that fails honestly.
         */
        if ($bundle = self::caBundle()) {
            curl_setopt($handle, CURLOPT_CAINFO, $bundle);
        }

        if ($body !== null) {
            curl_setopt($handle, CURLOPT_POSTFIELDS, $body);
        }

        $reply = curl_exec($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);
        $error = curl_error($handle);

        curl_close($handle);

        if ($reply === false) {
            /*
             * A certificate failure here is the single most common reason a
             * gateway "stops working" on a Windows XAMPP box, and the raw cURL
             * text does not say what to do about it, so it is spelled out.
             */
            throw new RuntimeException(
                str_contains(strtolower($error), 'certificate') || str_contains(strtolower($error), 'ssl')
                    ? 'Could not reach the gateway — ' . $error . '. On XAMPP this is usually a missing '
                        . 'CA bundle: download cacert.pem and point curl.cainfo at it in php.ini.'
                    : 'Could not reach the gateway — ' . $error . '.'
            );
        }

        return ['status' => $status ?: 200, 'body' => (string) $reply];
    }

    /**
     * @param  array<int, string>  $headers
     * @return array{status: int, body: string}
     */
    private static function stream(string $method, string $url, array $headers, ?string $body, int $timeout): array
    {
        $context = stream_context_create(['http' => array_filter([
            'method' => $method,
            'header' => $headers ? implode("\r\n", $headers) : null,
            'content' => $body,
            'timeout' => $timeout,
            'ignore_errors' => true,
        ], fn ($value) => $value !== null)]);

        $reply = @file_get_contents($url, false, $context);

        if ($reply === false) {
            throw new RuntimeException('Could not reach the gateway.');
        }

        $status = 200;

        foreach ($http_response_header ?? [] as $line) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', $line, $match)) {
                $status = (int) $match[1];
            }
        }

        return ['status' => $status, 'body' => $reply];
    }

    /*
    |--------------------------------------------------------------------------
    | Reading the reply
    |--------------------------------------------------------------------------
    */

    /**
     * Did the gateway actually refuse, and in what words?
     *
     * Returns null when the reply looks like a success, and it only calls
     * something a failure on clear evidence. The care here is deliberate: an
     * earlier version searched the whole body for the word "error", which meant
     * a perfectly good `{"error":false,"id":"..."}` was recorded as a failure
     * and a message that had gone out looked like one that had not.
     */
    private static function refusal(string $raw): ?string
    {
        $body = trim($raw);

        if ($body === '') {
            return null;
        }

        $json = json_decode($body, true);

        if (is_array($json)) {
            foreach (['error', 'err'] as $key) {
                if (array_key_exists($key, $json) && self::filledError($json[$key])) {
                    return Str::limit(
                        self::words($json['message'] ?? $json['msg'] ?? $json[$key])
                            ?: 'The gateway reported an error.',
                        200
                    );
                }
            }

            foreach (['status', 'success', 'sent'] as $key) {
                if (array_key_exists($key, $json) && self::says($json[$key]) === false) {
                    return Str::limit(
                        self::words($json['message'] ?? $json['msg'] ?? $json[$key])
                            ?: 'The gateway refused the message.',
                        200
                    );
                }
            }

            return null;
        }

        // A plain-text reply has no structure to read, so the words are all
        // there is. JSON never reaches this line, which is what keeps
        // `"error":false` from being mistaken for a refusal.
        return preg_match('/\b(error|invalid|fail|failed|not\s+sent|unauthor|expired)/i', $body)
            ? Str::limit($body, 200)
            : null;
    }

    /** An `error` field with something in it — false, 0 and "" are not errors. */
    private static function filledError(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value)) {
            return $value !== 0;
        }

        if (is_array($value)) {
            return $value !== [];
        }

        if ($value === null) {
            return false;
        }

        return ! in_array(strtolower(trim((string) $value)), ['', '0', 'false', 'no', 'none', 'null'], true);
    }

    /**
     * What a `status`-shaped field says: true it went, false it did not, null
     * when the value is a message id or something else with no opinion.
     */
    private static function says(mixed $value): ?bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value)) {
            return $value === 1 ? true : ($value === 0 ? false : null);
        }

        if (! is_string($value)) {
            return null;
        }

        $word = strtolower(trim($value));

        if (in_array($word, ['success', 'sent', 'ok', 'true', '1', 'yes', 'queued', 'submitted', 'delivered', 'accepted'], true)) {
            return true;
        }

        if (in_array($word, ['error', 'fail', 'failed', 'failure', 'false', '0', 'no', 'invalid', 'rejected', 'not sent'], true)) {
            return false;
        }

        return null;
    }

    private static function words(mixed $value): string
    {
        if (is_string($value)) {
            return trim($value);
        }

        if (is_scalar($value)) {
            return trim((string) $value);
        }

        return is_array($value) ? trim((string) json_encode($value)) : '';
    }

    /** Enough of a secret to recognise it, not enough to use it. */
    private static function mask(string $secret): string
    {
        if ($secret === '') {
            return 'empty';
        }

        return strlen($secret) <= 8
            ? str_repeat('•', strlen($secret))
            : substr($secret, 0, 4) . str_repeat('•', 6) . substr($secret, -4);
    }

    /*
    |--------------------------------------------------------------------------
    | Numbers
    |--------------------------------------------------------------------------
    */

    /**
     * Turn whatever somebody typed into what a provider expects.
     *
     * "+91 98765 43210", "098765 43210" and "9876543210" are the same number,
     * and a desk that has typed them one way for ten years is not going to
     * start typing them another way because a config file prefers it.
     */
    public static function number(string $raw): string
    {
        $digits = preg_replace('/\D+/', '', $raw) ?? '';

        if ($digits === '') {
            return '';
        }

        $code = preg_replace('/\D+/', '', (string) config('services.whatsapp.country_code', '91')) ?: '91';

        // A leading zero is a domestic prefix, never part of the number.
        $digits = ltrim($digits, '0');

        // Ten digits is a bare Indian mobile; anything longer already carries
        // its country code.
        if (strlen($digits) <= 10) {
            $digits = $code . $digits;
        }

        return $digits;
    }
}
