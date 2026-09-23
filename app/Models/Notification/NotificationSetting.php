<?php

namespace App\Models\Notification;

use Illuminate\Database\Eloquent\Model;

/**
 * Who hears about an event, and how — one row per event per branch.
 *
 * A branch with no row for an event falls back to the defaults in
 * config/notifications.php. That matters: a hotel that never opens the settings
 * screen still gets every notification in the bell, and adding an event to the
 * config does not need a migration or a seeder run.
 */
class NotificationSetting extends Model
{
    protected $guarded = ['id'];

    /**
     * The event name of the row that holds the branch's standing recipients.
     *
     * It is not an event and never fires. It is a place to keep "send every
     * staff notification here too", so a hotel does not have to type the
     * manager's number into fifty-four separate boxes — which is what the
     * screen used to ask of them, and the reason fifty-four events were ticked
     * with nowhere to send to.
     */
    public const DEFAULTS = '__default__';

    public const CHANNELS = [
        'app' => 'In the app (bell + browser pop-up)',
        'mail' => 'Email',
        'whatsapp' => 'WhatsApp',
    ];

    /** The branch's standing recipients, or null when nobody has set any. */
    public static function defaults(int $branchId): ?self
    {
        return static::query()
            ->where('branch_id', $branchId)
            ->where('event', self::DEFAULTS)
            ->first();
    }

    /**
     * One event's entry out of config/notifications.php.
     *
     * A thin pass through to the global helper so model code does not have to
     * reach for a function; see event_meta() for why the obvious
     * `config('notifications.events.' . $event)` cannot work.
     *
     * @return array<string, mixed>
     */
    public static function meta(string $event): array
    {
        return event_meta($event);
    }

    /** @return array<int, string> */
    public function channelList(): array
    {
        return array_values(array_filter(
            array_map('trim', explode(',', (string) $this->channels)),
            fn (string $c) => isset(self::CHANNELS[$c])
        ));
    }

    public function uses(string $channel): bool
    {
        return (int) $this->status === 1 && in_array($channel, $this->channelList(), true);
    }

    /** @return array<int, string> */
    public function mailAddresses(): array
    {
        return self::split($this->mail_to);
    }

    /** @return array<int, string> */
    public function whatsappNumbers(): array
    {
        return self::split($this->whatsapp_to);
    }

    /**
     * Split a typed-in list on commas, semicolons, spaces or new lines.
     *
     * People paste addresses out of Outlook and numbers out of a phone. Being
     * strict about the separator here buys nothing and loses recipients.
     *
     * @return array<int, string>
     */
    public static function split(?string $value): array
    {
        return array_values(array_unique(array_filter(
            array_map('trim', preg_split('/[\s,;]+/', (string) $value) ?: [])
        )));
    }
}
