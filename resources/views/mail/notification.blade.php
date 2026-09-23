{{--
    A notification, as an email.

    Written as a table with inline styles rather than with the theme's classes,
    because an email client is not a browser: Gmail strips <style> blocks,
    Outlook ignores flexbox, and a stylesheet link never loads at all. This is
    the one file in the project that is allowed to look like 2004.
--}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>{{ $notification->title }}</title>
</head>
<body style="margin:0;padding:24px;background:#f4f5f7;font-family:'Segoe UI',Helvetica,Arial,sans-serif;color:#1f2430">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;margin:0 auto">
        <tr>
            <td style="background:#ffffff;border-radius:12px;padding:28px 26px;border:1px solid #e3e6ec">
                @if ($hotel)
                    <p style="margin:0 0 6px;font-size:12px;letter-spacing:.08em;text-transform:uppercase;color:#7b8194">
                        {{ $hotel }}
                    </p>
                @endif

                <h1 style="margin:0 0 12px;font-size:19px;line-height:1.35;font-weight:600">
                    {{ $notification->title }}
                </h1>

                @if ($notification->body)
                    <p style="margin:0 0 18px;font-size:14.5px;line-height:1.6;color:#454c5d">
                        {{ $notification->body }}
                    </p>
                @endif

                @if ($notification->url)
                    <p style="margin:0 0 18px">
                        <a href="{{ $notification->url }}"
                           style="display:inline-block;background:#3b5bdb;color:#ffffff;text-decoration:none;
                                  padding:10px 18px;border-radius:8px;font-size:14px;font-weight:600">
                            Open it
                        </a>
                    </p>
                @endif

                <p style="margin:0;font-size:12.5px;color:#8b91a1">
                    {{ $notification->created_at?->format('d M Y, h:i A') }}
                </p>
            </td>
        </tr>

        <tr>
            <td style="padding:16px 4px 0;font-size:11.5px;color:#9aa0ae;line-height:1.6">
                Sent by {{ config('app.name') }}. You are getting this because this event is
                switched on for email under Administration → Notification Settings.
            </td>
        </tr>
    </table>
</body>
</html>
