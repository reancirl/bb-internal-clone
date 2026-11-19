<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Support\Sms\SmsSender;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * "Send test text" on the profile page: proves the configured SMS driver can
 * reach this user's saved number without waiting for a real lead. Sent
 * synchronously so the result shows up as the page's flash message.
 */
class TestSmsController extends Controller
{
    public function __invoke(Request $request, SmsSender $sms): RedirectResponse
    {
        $user = $request->user();

        if (blank($user->phone)) {
            return back()->with('error', 'Save a mobile number first.');
        }

        try {
            $sms->send($user->phone, 'BuffaloBuilt Internal: test text for '.$user->name.'. New lead alerts will arrive like this.');
        } catch (Throwable $e) {
            report($e);

            return back()->with('error', 'The test text could not be sent: '.str($e->getMessage())->limit(160));
        }

        return back()->with('success', 'Test text sent to '.$user->phone.'.');
    }
}
