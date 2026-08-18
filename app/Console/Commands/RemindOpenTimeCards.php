<?php

namespace App\Console\Commands;

use App\Models\TimeCard;
use App\Notifications\StillClockedIn;
use Carbon\Carbon;
use Illuminate\Console\Command;

class RemindOpenTimeCards extends Command
{
    /**
     * A shift open longer than this is probably a forgotten clock-out.
     */
    public const REMIND_AFTER_HOURS = 10;

    protected $signature = 'bb:remind-open-time-cards';

    protected $description = 'Email crew who have been clocked in longer than '.self::REMIND_AFTER_HOURS.' hours (once per shift)';

    public function handle(): int
    {
        $cards = TimeCard::query()
            ->with('user')
            ->whereNull('clock_out_at')
            ->whereNull('reminder_sent_at')
            ->where('clock_in_at', '<=', Carbon::now()->subHours(self::REMIND_AFTER_HOURS))
            ->get();

        foreach ($cards as $card) {
            if ($card->user !== null && $card->user->email_notifications) {
                $card->user->notify(new StillClockedIn($card));
            }

            // Stamp even for opted-out users so the card is never re-examined.
            $card->update(['reminder_sent_at' => now()]);
        }

        $this->info($cards->count().' open time card(s) processed.');

        return self::SUCCESS;
    }
}
