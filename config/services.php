<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | WhatsApp
    |--------------------------------------------------------------------------
    | Four ways to send a WhatsApp message, plus a fifth that sends nothing.
    | Pick one with WHATSAPP_DRIVER in .env:
    |
    |   chatway   int.chatway.in — the gateway this hotel already uses, and the
    |             default. It wants a username and a token, both of which live
    |             in .env:
    |
    |             WHATSAPP_DRIVER=chatway
    |             WHATSAPP_USERNAME=sales@rukmanisoftware.com
    |             WHATSAPP_TOKEN=...                 ← the token goes here
    |
    |             It can also send a file by URL, which is what puts a PDF bill
    |             on a guest's phone: pass the third argument to
    |             Helper::sendWhatsappMessage().
    |
    |   log       writes the message to storage/logs/laravel.log and stops.
    |             Set this while testing — the system then works with no
    |             account at all and nothing is silently lost.
    |
    |   meta      WhatsApp Cloud API, straight from Meta. You need a phone
    |             number id and a permanent access token from
    |             business.facebook.com → WhatsApp → API Setup.
    |
    |             WHATSAPP_DRIVER=meta
    |             WHATSAPP_PHONE_ID=123456789012345
    |             WHATSAPP_TOKEN=EAAG...            ← paste the token here
    |
    |   twilio    Twilio's WhatsApp sender.
    |
    |             WHATSAPP_DRIVER=twilio
    |             WHATSAPP_TWILIO_SID=AC...
    |             WHATSAPP_TWILIO_TOKEN=...          ← paste the token here
    |             WHATSAPP_FROM=whatsapp:+14155238886
    |
    |   gateway   any other provider that takes a JSON POST. The body sent is
    |             {"to": "...", "message": "..."} plus whatever you put in
    |             WHATSAPP_EXTRA (as JSON), with the token as a Bearer header.
    |
    |             WHATSAPP_DRIVER=gateway
    |             WHATSAPP_URL=https://your-provider/send
    |             WHATSAPP_TOKEN=...                 ← paste the token here
    |
    | Meta and most gateways want the number in full international form with no
    | plus and no spaces — 919876543210 for an Indian mobile. The sender adds
    | the country code from WHATSAPP_COUNTRY_CODE to a bare 10-digit number, so
    | the desk can keep typing numbers the way it always has.
    */

    /*
    |--------------------------------------------------------------------------
    | AI Assistant
    |--------------------------------------------------------------------------
    | The dashboard's "Ask AI" panel. Nothing else on the dashboard depends on
    | this — the stat cards and the Insights panel are plain PHP against the
    | real tables and work with no key at all. This is only for the free-text
    | chat box, and until a key is set it replies with a short, honest message
    | instead of failing silently.
    |
    | Four providers are wired up. AI_DRIVER picks which one is live — only
    | that one needs a key set:
    |
    |   AI_DRIVER=anthropic   (default) — console.anthropic.com/settings/keys
    |       AI_API_KEY=sk-ant-...
    |
    |   AI_DRIVER=gemini      — aistudio.google.com/apikey
    |       GEMINI_API_KEY=AIzaSy...
    |
    |   AI_DRIVER=groq        — console.groq.com/keys (fast, generous free tier)
    |       GROQ_API_KEY=gsk_...
    |
    |   AI_DRIVER=huggingface — huggingface.co/settings/tokens
    |       HUGGINGFACE_API_KEY=hf_...
    |
    | Switching later is just changing AI_DRIVER — keys for the other three
    | can stay in .env unused; nothing reads a key for a driver that isn't
    | active.
    |
    | Any one *_API_KEY line can hold more than one key, comma-separated with
    | no spaces: if the first is invalid, out of quota, or rate-limited, the
    | next is tried automatically before the assistant gives up. A single key
    | still works exactly as before — this is optional.
    |
    | Gemini and Hugging Face are called through their OpenAI-compatible chat
    | endpoints, so they share the same request code as Groq; only the base
    | URL, key and model differ. *_MODEL below already defaults to a sensible
    | model for each provider.
    */

    'ai' => [
        'driver' => env('AI_DRIVER', 'anthropic'),
        'timeout' => (int) env('AI_TIMEOUT', 20),
        'max_tokens' => (int) env('AI_MAX_TOKENS', 600),

        'anthropic' => [
            'key' => env('AI_API_KEY'),
            'model' => env('AI_MODEL', 'claude-sonnet-4-5'),
        ],

        'gemini' => [
            'key' => env('GEMINI_API_KEY'),
            'model' => env('GEMINI_MODEL', 'gemini-2.5-flash'),
        ],

        'groq' => [
            'key' => env('GROQ_API_KEY'),
            'model' => env('GROQ_MODEL', 'openai/gpt-oss-120b'),
        ],

        'huggingface' => [
            'key' => env('HUGGINGFACE_API_KEY'),
            'model' => env('HUGGINGFACE_MODEL', 'openai/gpt-oss-120b'),
        ],
    ],

    'whatsapp' => [
        /*
         * `chatway` by default because that is the account this hotel has.
         * With WHATSAPP_TOKEN still empty every send fails loudly and says so
         * on Administration -> Notification Settings, which is the opposite of
         * what this used to do: it wrote the message to a log file and called
         * it sent, so a hotel could run for a week believing guests were being
         * messaged. Set WHATSAPP_DRIVER=log to get the quiet behaviour back on
         * purpose.
         */
        'driver' => env('WHATSAPP_DRIVER', 'chatway'),
        'chatway_url' => env('WHATSAPP_CHATWAY_URL', 'https://int.chatway.in/api/send-msg'),
        /*
         * The Chatway username is an account name, not a secret, so it has a
         * default: a .env written before Chatway existed has no
         * WHATSAPP_USERNAME line, and without a default the system would decide
         * it was "not configured" and quietly log every message instead of
         * sending it.
         */
        'username' => env('WHATSAPP_USERNAME', 'sales@rukmanisoftware.com'),
        'phone_id' => env('WHATSAPP_PHONE_ID'),
        'token' => env('WHATSAPP_TOKEN'),
        'url' => env('WHATSAPP_URL'),
        'from' => env('WHATSAPP_FROM'),
        'twilio_sid' => env('WHATSAPP_TWILIO_SID'),
        'twilio_token' => env('WHATSAPP_TWILIO_TOKEN'),
        'country_code' => env('WHATSAPP_COUNTRY_CODE', '91'),
        'api_version' => env('WHATSAPP_API_VERSION', 'v21.0'),
        'extra' => env('WHATSAPP_EXTRA'),
        'timeout' => (int) env('WHATSAPP_TIMEOUT', 12),
    ],

];
