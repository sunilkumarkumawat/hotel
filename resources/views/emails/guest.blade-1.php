{{--
    The guest's document as an email.

    Written the way email has to be written rather than the way a page is:
    tables for layout, styles on the elements themselves, no external
    stylesheet and no web fonts. Half the mail clients in the world throw away a
    <style> block, and a confirmation that arrives as unstyled text with the
    room number missing is worse than a plain one that reads properly.

    The same definition builds this and the PDF, so the two say the same things
    and always will.
--}}
@php
    /**
     * Fill the braces in — and say the line is empty if any of them were, so a
     * booking with no advance shows no advance row rather than a blank one.
     */
    $fill = function (?string $pattern) use ($data) {
        if (! $pattern) {
            return '';
        }

        $empty = false;

        $filled = preg_replace_callback('/\{(\w+)\}/', function ($match) use ($data, &$empty) {
            $value = $data[$match[1]] ?? null;

            if ($value === null || $value === '' || $value === false) {
                $empty = true;

                return '';
            }

            return (string) $value;
        }, $pattern);

        if (! $empty) {
            return trim($filled);
        }

        return trim(preg_replace('/[^\p{L}\p{N}]+/u', '', $filled)) === ''
            ? ''
            : trim(preg_replace('/\s{2,}/', ' ', $filled));
    };

    $reference = $fill($definition['reference'] ?? null);
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>{{ $definition['title'] ?? 'Your booking' }}</title>
</head>
<body style="margin:0;padding:0;background:#f4f5f8;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
           style="background:#f4f5f8;padding:26px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
                       style="max-width:600px;background:#ffffff;border-radius:14px;overflow:hidden;
                              font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Arial,sans-serif;
                              box-shadow:0 2px 10px rgba(16,20,40,0.08);">

                    {{-- The band, same as the PDF's --}}
                    <tr>
                        <td style="background:#5c1745;padding:24px 28px;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                                <tr>
                                    <td style="color:#ffffff;font-size:19px;font-weight:700;letter-spacing:-0.3px;">
                                        {{ strtoupper($hotel) }}
                                    </td>
                                    <td align="right" style="color:#ffffff;font-size:11.5px;font-weight:700;letter-spacing:0.6px;">
                                        {{ strtoupper($definition['title'] ?? 'Document') }}
                                    </td>
                                </tr>
                                @if ($reference !== '')
                                    <tr>
                                        <td colspan="2" align="right" style="color:#e2cdd9;font-size:12px;padding-top:4px;">
                                            {{ $reference }}
                                        </td>
                                    </tr>
                                @endif
                            </table>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:26px 28px 8px;">
                            @if ($greeting = $fill($definition['greeting'] ?? null))
                                <p style="margin:0 0 10px;font-size:17px;font-weight:700;color:#14161f;">
                                    {{ $greeting }}
                                </p>
                            @endif

                            @if ($lead = $fill($definition['lead'] ?? null))
                                <p style="margin:0;font-size:14px;line-height:1.6;color:#4a4d5c;">
                                    {{ $lead }}
                                </p>
                            @endif
                        </td>
                    </tr>

                    @foreach (($definition['sections'] ?? []) as $heading => $rows)
                        @php
                            $filled = [];

                            foreach ((array) $rows as $label => $pattern) {
                                if (($value = $fill($pattern)) !== '') {
                                    $filled[$label] = $value;
                                }
                            }
                        @endphp

                        @continue($filled === [])

                        <tr>
                            <td style="padding:18px 28px 0;">
                                <p style="margin:0 0 8px;font-size:11px;font-weight:700;letter-spacing:0.8px;
                                          text-transform:uppercase;color:#8b8fa3;">{{ $heading }}</p>

                                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
                                       style="border-top:1px solid #e8e9ef;">
                                    @foreach ($filled as $label => $value)
                                        <tr>
                                            <td style="padding:9px 0;font-size:14px;color:#5a5d6e;border-bottom:1px solid #f0f1f5;">
                                                {{ $label }}
                                            </td>
                                            <td align="right"
                                                style="padding:9px 0;font-size:14px;font-weight:600;color:#14161f;border-bottom:1px solid #f0f1f5;">
                                                {{ $value }}
                                            </td>
                                        </tr>
                                    @endforeach
                                </table>
                            </td>
                        </tr>
                    @endforeach

                    {{-- The gold total-amount callout — the same figure the
                         PDF's highlightBox() draws, so a guest glancing at
                         the email sees what they owe/paid without having to
                         open the attachment. --}}
                    @if (! empty($definition['highlight']))
                        @php
                            $highlight = (array) $definition['highlight'];
                            $highlightValue = $fill($highlight['value'] ?? null);

                            $highlightSub = [];
                            foreach ((array) ($highlight['sub'] ?? []) as $subLabel => $subPattern) {
                                if (($subValue = $fill($subPattern)) !== '') {
                                    $highlightSub[] = $subLabel.': '.$subValue;
                                }
                            }
                        @endphp

                        @if ($highlightValue !== '')
                            <tr>
                                <td style="padding:18px 28px 0;">
                                    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
                                           style="background:#f6f2f5;border-radius:10px;">
                                        <tr>
                                            <td style="padding:16px 18px;">
                                                <p style="margin:0 0 4px;font-size:11px;font-weight:700;letter-spacing:0.8px;
                                                          text-transform:uppercase;color:#5c1745;">
                                                    {{ strtoupper($highlight['label'] ?? 'Total') }}
                                                </p>
                                                <p style="margin:0;font-size:24px;font-weight:700;color:#5c1745;">
                                                    {{ $highlightValue }}
                                                </p>
                                                @if ($highlightSub !== [])
                                                    <p style="margin:6px 0 0;font-size:12px;color:#8b8fa3;">
                                                        {{ implode('   ·   ', $highlightSub) }}
                                                    </p>
                                                @endif
                                            </td>
                                        </tr>
                                    </table>
                                </td>
                            </tr>
                        @endif
                    @endif

                    @if ($attached)
                        <tr>
                            <td style="padding:20px 28px 0;">
                                <p style="margin:0;padding:11px 14px;background:#f6f2f5;border-radius:10px;
                                          font-size:13px;color:#5c1745;">
                                    A PDF copy is attached to this email — keep it, or show it at the desk.
                                </p>
                            </td>
                        </tr>
                    @endif

                    @if ($note = $fill($definition['note'] ?? null))
                        <tr>
                            <td style="padding:18px 28px 0;">
                                <p style="margin:0;font-size:12.5px;line-height:1.6;color:#8b8fa3;">{{ $note }}</p>
                            </td>
                        </tr>
                    @endif

                    <tr>
                        <td style="padding:24px 28px 26px;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
                                   style="border-top:1px solid #e8e9ef;">
                                <tr>
                                    <td style="padding-top:14px;font-size:12.5px;font-weight:700;color:#5a5d6e;">
                                        {{ $hotel }}
                                    </td>
                                    <td align="right" style="padding-top:14px;font-size:12.5px;color:#8b8fa3;">
                                        {{ trim(implode('  ·  ', array_filter([$data['phone'] ?? '', $data['email'] ?? '']))) }}
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
