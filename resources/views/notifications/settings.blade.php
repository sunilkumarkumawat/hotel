@extends('layouts.app')

@section('title', 'Notification Settings')

@section('content')
    <x-page-header
        title="Notification Settings"
        subtitle="Who hears about what, and on which channel."
        :crumbs="['Home' => url('/'), 'Administration', 'Notification Settings']"
    />

    {{-- ══ Where the keys go ═══════════════════════════════════════════════

         This is the part somebody reads once, on the day they set the system
         up, and never again. It says the file, the line, and what goes on it —
         nothing else — because a person holding a Gmail app password in one
         hand does not want a tutorial.
    --}}
    <div class="nv-grid nv-grid-2 nv-mt">
        <x-card title="Email" subtitle="Sent from the hotel's own address">
            @php
                // "Set up" is not "working" — only the mail server can say that,
                // and it only says it when the button below is pressed.
                $mailBroken = str_starts_with($mail, 'Not connected') || str_contains($mail, 'is empty');
            @endphp

            <p @class(['nv-callout', 'is-warning' => $mailBroken, 'is-info' => ! $mailBroken])>
                {{ $mail }}
            </p>

            <p class="nv-help">
                Open <code>.env</code> in the project folder. There are
                <strong>two ways</strong> to send, and the only difference between them is which
                password the mail server will accept.
            </p>

            <p class="nv-help">
                <strong>1 — Gmail.</strong> Gmail refuses the password you sign in to Gmail with:
                over SMTP it only accepts a 16-character <em>App Password</em>, and that is
                <strong>Google's rule, not this system's</strong> — there is no setting here that
                changes it. Google account → 2-Step Verification (turn it on first, or App
                passwords will not appear) → Security → App passwords → Mail.
            </p>

            <pre class="nv-code">MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_ENCRYPTION=tls
MAIL_USERNAME=you@gmail.com
MAIL_PASSWORD=                 <span class="nv-code-note">← the 16-letter App Password, no spaces</span>
MAIL_FROM_ADDRESS=you@gmail.com
MAIL_FROM_NAME="{{ config('app.name') }}"</pre>

            <p class="nv-help">
                <strong>2 — the hotel's own domain</strong>, from whoever hosts the website. Here
                <strong>you choose the password</strong>, any length, and it also means guests get
                mail from the hotel's own address rather than from a Gmail account. Your host's
                control panel (cPanel → Email Accounts) gives you the exact server name.
            </p>

            <pre class="nv-code">MAIL_MAILER=smtp
MAIL_HOST=mail.yourhotel.com   <span class="nv-code-note">← your host gives you this</span>
MAIL_PORT=587
MAIL_ENCRYPTION=tls
MAIL_USERNAME=info@yourhotel.com
MAIL_PASSWORD=                 <span class="nv-code-note">← the password you set for that mailbox</span>
MAIL_FROM_ADDRESS=info@yourhotel.com
MAIL_FROM_NAME="{{ config('app.name') }}"</pre>

            <p class="nv-help">
                Some hosts want port <code>465</code> with <code>MAIL_ENCRYPTION=ssl</code> instead
                — the check below tells you which one the server is happy with. After saving
                <code>.env</code>, run <code>php artisan config:clear</code> once, then press
                <strong>Check the mail server</strong>.
            </p>
        </x-card>

        <x-card title="WhatsApp" subtitle="Messages to a phone, the way the guest already reads them">
            <p @class(['nv-callout', 'is-warning' => ! $whatsappLive, 'is-success' => $whatsappLive])>
                {{ $whatsapp }}
            </p>

            <p class="nv-help">
                Same file, <code>.env</code>. The gateway and the username are already set —
                <strong>only the token line is left for you.</strong>
            </p>

            <pre class="nv-code">WHATSAPP_DRIVER=chatway
WHATSAPP_USERNAME=sales@rukmanisoftware.com
WHATSAPP_TOKEN=                  <span class="nv-code-note">← paste the Chatway token here</span>
WHATSAPP_CHATWAY_URL=https://int.chatway.in/api/send-msg</pre>

            <p class="nv-help">
                Save it, then run <code>php artisan config:clear</code> once — this last step is
                the one people miss, and until it is done Laravel keeps reading the old file.
                Nothing here can break a booking: if the token is wrong the booking is still
                taken and the reason appears in the delivery log at the bottom of this page.
            </p>

            <p class="nv-help">
                Numbers can be typed however the desk types them — <code>98765 43210</code>,
                <code>+91 98765 43210</code> and <code>09876543210</code> all reach the same
                phone. Moving to Meta or Twilio later is a different
                <code>WHATSAPP_DRIVER</code> and nothing else; the options are listed in
                <code>config/services.php</code>.
            </p>
        </x-card>
    </div>

    {{-- ══ Why is nothing going out? ═══════════════════════════════════════

         Every field on this card is read out of the running application, not
         out of the .env file, which is the difference that matters: a config
         cache means the two disagree, and that disagreement is the commonest
         reason a token that "has been filled in" still does nothing.
    --}}
    <div class="nv-grid nv-grid-2 nv-mt">
        <x-card title="WhatsApp check" subtitle="What the system is actually running with, right now">
            <div class="nv-table-wrap">
                <table class="nv-table">
                    <thead>
                        <tr>
                            <th>Setting</th>
                            <th>What it is</th>
                            <th class="is-end">Result</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($whatsappCheck as [$label, $value, $ok])
                            <tr>
                                <td>{{ $label }}</td>
                                <td>{{ $value }}</td>
                                <td class="is-end">
                                    @if ($ok === null)
                                        <span class="nv-sub">—</span>
                                    @else
                                        <x-badge :tone="$ok ? 'success' : 'danger'">{{ $ok ? 'OK' : 'Fix this' }}</x-badge>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <form method="POST" action="{{ route('notification-settings.probe') }}" class="nv-mt">
                @csrf
                <button type="submit" class="nv-btn nv-btn-primary">
                    <x-icon name="check" /> Check the gateway now
                </button>
            </form>

            <p class="nv-help">
                This asks Chatway whether it knows the username and token above.
                <strong>Nobody is messaged</strong> — the number sent is deliberately not a real
                one, so Chatway checks the account, then complains about the number, and its
                complaint tells us which of the two it was unhappy about. Press it as often as
                you like.
            </p>

            <p class="nv-help">
                The same thing runs from the command line, and there it can also send a real
                message and print the gateway's own reply:
                <code>php artisan whatsapp:test 9876543210</code>. Run it with no number and it
                only prints this table.
            </p>
        </x-card>

        <x-card title="Email check" subtitle="The same, for the mail side">
            <div class="nv-table-wrap">
                <table class="nv-table">
                    <thead>
                        <tr>
                            <th>Setting</th>
                            <th>What it is</th>
                            <th class="is-end">Result</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($mailCheck as [$label, $value, $ok])
                            <tr>
                                <td>{{ $label }}</td>
                                <td>{{ $value }}</td>
                                <td class="is-end">
                                    @if ($ok === null)
                                        <span class="nv-sub">—</span>
                                    @else
                                        <x-badge :tone="$ok ? 'success' : 'danger'">{{ $ok ? 'OK' : 'Fix this' }}</x-badge>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <form method="POST" action="{{ route('notification-settings.probe-mail') }}" class="nv-mt">
                @csrf
                <button type="submit" class="nv-btn nv-btn-primary">
                    <x-icon name="check" /> Check the mail server now
                </button>
            </form>

            <p class="nv-help">
                This does everything sending does except send: it connects to the mail server,
                starts the encryption and logs in, then hangs up. <strong>Nobody is emailed.</strong>
                If the password is wrong, the mail server says so here in one sentence instead of
                in a hundred lines after a failed test.
            </p>

            <p class="nv-help">
                A ticked channel with nothing to send to is not an error anybody can see, so it
                is written into the delivery log as a failure with those words on it. If a row
                below says <em>nobody</em>, put an address or a number in that event's box.
            </p>
        </x-card>
    </div>

    {{-- ══ Test it ═════════════════════════════════════════════════════════ --}}
    <div class="nv-mt">
        <x-card title="Send a test" subtitle="Before waiting for a real guest to check in">
            <form method="POST" action="{{ route('notification-settings.test') }}" class="nv-toolbar">
                @csrf

                <x-field label="Email address" name="mail_to">
                    <x-input name="mail_to" type="email" placeholder="you@example.com" />
                </x-field>

                <x-field label="WhatsApp number" name="whatsapp_to">
                    <x-input name="whatsapp_to" placeholder="98765 43210" />
                </x-field>

                <button type="submit" class="nv-btn nv-btn-primary">
                    <x-icon name="mail" /> Send test
                </button>
            </form>

            <p class="nv-help">
                Fill in one or both. The result — including the provider's own error message
                when it refuses — appears at the top of this page and in the log below.
            </p>
        </x-card>
    </div>

    {{-- ══ Where the guest's own messages are written ═══════════════════════ --}}
    <div class="nv-mt">
        <x-card title="What the guest is told"
                subtitle="The WhatsApp messages that go to the guest, not to the desk">
            <p class="nv-help">
                Booking confirmed, welcome on check-in, payment receipt, the bill, a pool or hall
                booking, the car and its driver, a parking ticket — each of those is a message on
                the guest's own phone, and each one is a row further down this page under
                <strong>Messages to the guest</strong>. Untick WhatsApp on any of them and it stops.
            </p>

            <p class="nv-help">
                The <strong>wording</strong> lives in
                <code>config/guest-messages.php</code> — plain text you can edit, with
                <code>{curly braces}</code> for the bits that get filled in. A placeholder with
                nothing behind it takes its whole line with it, so a booking with no arrival time
                does not send a message with a dangling “Time:”.
            </p>

            <p class="nv-help">
                Each message also carries a <strong>PDF</strong> — a booking confirmation, a
                registration slip, the final bill. What each one says is
                <code>config/guest-documents.php</code>, laid out the same way.
            </p>

            <p class="nv-help">
                It goes out on <strong>both</strong> channels, and only one of them needs an
                address. <strong>Email carries the file itself</strong>, so it works from this
                computer exactly as it would from a server — nothing to set up. WhatsApp is handed
                a <em>link</em> and fetches the file itself, so it needs an address the internet
                can reach.
            </p>

            @if ($pdfProblem)
                <p class="nv-callout is-warning">{{ $pdfProblem }}</p>
            @else
                <p class="nv-callout is-success">
                    Both channels are carrying the PDF. WhatsApp fetches each one from
                    <code>{{ \App\Support\GuestDocument::base() }}/guest-doc/…</code>, so that
                    address has to stay reachable from the internet.
                </p>
            @endif

            <p class="nv-help">
                Anywhere else in the code, one line sends a message:
                <code>Helper::sendWhatsappMessage($mobile, $message);</code> — and a third argument
                sends a file by URL.
            </p>
        </x-card>
    </div>

    {{-- ══ The events ══════════════════════════════════════════════════════ --}}
    <form method="POST" action="{{ route('notification-settings.save') }}">
        @csrf

        {{-- ══ Who all of this goes to ══════════════════════════════════════

             One box for the whole system. The per-event boxes further down are
             for the exception — the housekeeper who should hear about rooms and
             nothing else — not for the ordinary case, and asking somebody to
             type the same number into fifty-four rows was how this screen ended
             up with every event ticked and nowhere to send to.
        --}}
        <div class="nv-mt">
            <x-card title="Send every staff notification to"
                    subtitle="Fill this in once and every ticked event below has somewhere to go">
                <div class="nv-grid nv-grid-2">
                    <x-field label="WhatsApp numbers"
                             name="defaults.whatsapp_to"
                             for="defaults_whatsapp_to"
                             help="The desk's phone, the manager's phone. Separate several with commas.">
                        <input type="text"
                               name="defaults[whatsapp_to]"
                               id="defaults_whatsapp_to"
                               class="nv-input"
                               value="{{ old('defaults.whatsapp_to', $defaults?->whatsapp_to) }}"
                               placeholder="98765 43210, 98765 43211" />
                    </x-field>

                    <x-field label="Email addresses"
                             name="defaults.mail_to"
                             for="defaults_mail_to"
                             help="Only used for the events that have Mail ticked.">
                        <input type="text"
                               name="defaults[mail_to]"
                               id="defaults_mail_to"
                               class="nv-input"
                               value="{{ old('defaults.mail_to', $defaults?->mail_to) }}"
                               placeholder="manager@hotel.com, owner@hotel.com" />
                    </x-field>
                </div>

                <p class="nv-help">
                    This is on top of whatever a single event's own boxes say, and on top of the
                    WhatsApp No. and Email filled in on each user in Administration → Users. A
                    guest's own messages do not use this — those go to the number on the booking.
                </p>
            </x-card>
        </div>

        @foreach ($groups as $key => $groupName)
            @php $list = $events->get($key, collect()); @endphp

            @continue($list->isEmpty())

            <div class="nv-mt">
                <x-card :title="$groupName" flush>
                    <div class="nv-table-wrap">
                        <table class="nv-table nv-notif-table">
                            <thead>
                                <tr>
                                    <th style="width:30%">What happened</th>
                                    <th style="width:22%">Channels</th>
                                    <th>Extra email addresses</th>
                                    <th>Extra WhatsApp numbers</th>
                                    <th style="width:70px">On</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($list as $event)
                                    <tr>
                                        <td>
                                            <strong>{{ $event['label'] }}</strong>
                                            <span class="nv-sub">
                                                {{ $event['key'] }}@unless ($event['saved']) · using defaults @endunless
                                            </span>
                                        </td>

                                        <td>
                                            <div class="nv-check-row">
                                                @foreach ($channels as $channel => $channelLabel)
                                                    <label class="nv-check" title="{{ $channelLabel }}">
                                                        <input type="checkbox"
                                                               name="events[{{ $event['key'] }}][channels][]"
                                                               value="{{ $channel }}"
                                                               @checked(in_array($channel, $event['channels'], true)) />
                                                        <span>{{ ucfirst($channel) }}</span>
                                                    </label>
                                                @endforeach
                                            </div>
                                        </td>

                                        <td>
                                            <input type="text" class="nv-input nv-input-sm"
                                                   name="events[{{ $event['key'] }}][mail_to]"
                                                   value="{{ $event['mail_to'] }}"
                                                   placeholder="manager@hotel.com, owner@hotel.com" />
                                        </td>

                                        <td>
                                            <input type="text" class="nv-input nv-input-sm"
                                                   name="events[{{ $event['key'] }}][whatsapp_to]"
                                                   value="{{ $event['whatsapp_to'] }}"
                                                   placeholder="98765 43210, 98765 43211" />
                                        </td>

                                        <td>
                                            <label class="nv-check">
                                                <input type="hidden" name="events[{{ $event['key'] }}][status]" value="0" />
                                                <input type="checkbox"
                                                       name="events[{{ $event['key'] }}][status]"
                                                       value="1" @checked($event['status'] === 1) />
                                                <span class="nv-sr">Switched on</span>
                                            </label>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </x-card>
            </div>
        @endforeach

        <div class="nv-actions nv-mt" style="justify-content:flex-end">
            <button type="submit" class="nv-btn nv-btn-primary">
                <x-icon name="check" /> Save settings
            </button>
        </div>
    </form>

    {{-- ══ What actually left the building ═════════════════════════════════ --}}
    <div class="nv-mt">
        <x-card title="Delivery log"
                subtitle="The last 25 messages that left the building — to staff and to guests"
                flush>
            @if ($deliveries->isEmpty())
                <div class="nv-empty">
                    <span class="nv-empty-icon"><x-icon name="inbox" /></span>
                    <strong>Nothing sent yet</strong>
                    <p>Emails and WhatsApp messages show up here with their result — including why one failed.</p>
                </div>
            @else
                <div class="nv-table-wrap">
                    <table class="nv-table">
                        <thead>
                            <tr>
                                <th>When</th>
                                <th>Channel</th>
                                <th>To</th>
                                <th>For</th>
                                <th>Result</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($deliveries as $delivery)
                                <tr>
                                    <td>{{ $delivery->created_at?->format('d M, h:i A') }}</td>
                                    <td>{{ ucfirst($delivery->channel) }}</td>
                                    <td>
                                        {{ $delivery->target }}
                                        <span class="nv-sub">
                                            {{ $delivery->audience === 'guest' ? 'the guest' : 'staff' }}
                                        </span>
                                    </td>
                                    <td>
                                        {{ event_label($delivery->event) }}
                                    </td>
                                    <td>
                                        <x-badge :tone="$delivery->tone">{{ ucfirst($delivery->status) }}</x-badge>
                                        @if ($delivery->error)
                                            <span class="nv-sub">{{ $delivery->error }}</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-card>
    </div>
@endsection
