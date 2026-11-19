<?php

namespace App\Support;

use App\Models\User;
use App\Notifications\Channels\SmsChannel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification as NotificationFacade;

/**
 * Recipient and channel resolution for app notifications. The per-user
 * preferences (users.email_notifications, users.sms_notifications + phone) are
 * enforced here, in one place: recipients are picked by admins()/users(), and
 * each notification's via() asks channels() which of its channels this user
 * accepts. Notification classes and call sites never check preferences.
 */
class Notify
{
    /**
     * Notify every admin who accepts at least one channel, optionally
     * excluding the acting user so nobody is notified about their own click.
     */
    public static function admins(Notification $notification, ?User $except = null): void
    {
        $recipients = User::query()
            ->where('role', User::ROLE_ADMIN)
            ->where(fn (Builder $q) => $q
                ->where('email_notifications', true)
                ->orWhere(fn (Builder $q) => $q->where('sms_notifications', true)->whereNotNull('phone')))
            ->when($except !== null, fn ($q) => $q->whereKeyNot($except->id))
            ->get();

        NotificationFacade::send($recipients, $notification);
    }

    /**
     * Notify the given users, dropping anyone who accepts no channel.
     *
     * @param  Collection<int, User>  $users
     */
    public static function users(Collection $users, Notification $notification): void
    {
        NotificationFacade::send(
            $users->filter(fn (User $u) => $u->email_notifications || self::acceptsSms($u)),
            $notification,
        );
    }

    /**
     * The subset of a notification's channels this user has switched on.
     * Called from via(); pass 'mail' and/or 'sms'.
     *
     * @param  list<'mail'|'sms'>  $wanted
     * @return list<string>
     */
    public static function channels(object $notifiable, array $wanted): array
    {
        $channels = [];

        if (in_array('mail', $wanted, true) && ($notifiable->email_notifications ?? false)) {
            $channels[] = 'mail';
        }

        if (in_array('sms', $wanted, true) && $notifiable instanceof User && self::acceptsSms($notifiable)) {
            $channels[] = SmsChannel::class;
        }

        return $channels;
    }

    private static function acceptsSms(User $user): bool
    {
        return $user->sms_notifications && filled($user->phone);
    }
}
