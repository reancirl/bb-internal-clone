<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification as NotificationFacade;

/**
 * Recipient resolution for app notifications. The per-user opt-out
 * (users.email_notifications) is enforced here, in one place, so individual
 * notification classes and call sites never need to remember it.
 */
class Notify
{
    /**
     * Notify every opted-in admin, optionally excluding the acting user so
     * nobody is emailed about their own click.
     */
    public static function admins(Notification $notification, ?User $except = null): void
    {
        $recipients = User::query()
            ->where('role', User::ROLE_ADMIN)
            ->where('email_notifications', true)
            ->when($except !== null, fn ($q) => $q->whereKeyNot($except->id))
            ->get();

        NotificationFacade::send($recipients, $notification);
    }

    /**
     * Notify the given users, dropping anyone who opted out.
     *
     * @param  Collection<int, User>  $users
     */
    public static function users(Collection $users, Notification $notification): void
    {
        NotificationFacade::send(
            $users->filter(fn (User $u) => $u->email_notifications),
            $notification,
        );
    }
}
