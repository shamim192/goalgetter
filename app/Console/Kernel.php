<?php

namespace App\Console;

use Illuminate\Support\Facades\DB;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // $schedule->command('inspire')->hourly();
        $schedule->call(function () {
            $users = DB::table('bookings')->where('status', 'pending')->get();
            foreach ($users as $user) {
                app()->call('App\Http\Controllers\ChallengeController@chargeUserForMissedGoal', [
                    'userId' => $user->user_id,
                    'challengeId' => $user->challenge_id
                ]);
            }
        })->weeklyOn(7, '23:59'); // Runs every Sunday at 11:59 PM
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
