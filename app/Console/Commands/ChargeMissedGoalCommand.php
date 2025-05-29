<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Stripe\Stripe;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ChargeMissedGoalCommand extends Command
{
    protected $signature = 'charge:missed-goal';
    protected $description = 'Charge all users for missed goals in their challenges every week';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        // Get all challenges that have users associated with them
        $challenges = DB::table('challenges')->get(); // This assumes you have a table for challenges

        // Iterate over each challenge
        foreach ($challenges as $challenge) {
            // Fetch all users associated with this challenge
            $bookings = DB::table('bookings')
                ->where('challenge_id', $challenge->id)
                ->where('status', 'pending') // Make sure the user hasn't already been charged
                ->get();

            foreach ($bookings as $booking) {
                $this->chargeUserForMissedGoal($booking->user_id, $challenge->id);
            }
        }
    }

    public function chargeUserForMissedGoal($userId, $challengeId)
    {
        // Fetch the user's booking details
        $booking = DB::table('bookings')
            ->where('user_id', $userId)
            ->where('challenge_id', $challengeId)
            ->first();

        if (!$booking) {
            $this->error("No booking found for user $userId and challenge $challengeId.");
            return;
        }

        // Fetch goals for the challenge
        $goals = DB::table('goals')
            ->where('challenge_id', $challengeId)
            ->get();

        if ($goals->isEmpty()) {
            $this->error('No goals found for this challenge.');
            return;
        }

        // Get the current week start and end dates
        $weekStart = Carbon::now()->startOfWeek();
        $weekEnd = Carbon::now()->endOfWeek();

        // Calculate required logs and track actual logs
        $missedLogs = 0;
        $requiredLogs = 0;

        foreach ($goals as $goal) {
            // Fetch logs for this goal in the current week
            $loggedDates = DB::table('goal_progress')
                ->where('user_id', $userId)
                ->where('goal_id', $goal->id)
                ->whereBetween('date', [$weekStart, $weekEnd])
                ->pluck('date')
                ->toArray();

            $loggedDates = array_map(fn($date) => Carbon::parse($date)->toDateString(), $loggedDates);

            switch ($goal->frequency) {
                case 'Daily (7 days of the week)':
                    $requiredDays = collect(range(0, 6))->map(fn($d) => $weekStart->copy()->addDays($d)->toDateString());
                    break;
                case '5 days out of the week':
                    $requiredDays = collect(range(0, 6))->map(fn($d) => $weekStart->copy()->addDays($d)->toDateString())->take(5);
                    break;
                case 'Every other day':
                    $requiredDays = collect([0, 2, 4, 6])->map(fn($d) => $weekStart->copy()->addDays($d)->toDateString());
                    break;
                case '3 times a week':
                    $requiredDays = collect([1, 3, 5])->map(fn($d) => $weekStart->copy()->addDays($d)->toDateString());
                    break;
                default:
                    $requiredDays = collect();
            }

            // Count missed days
            $missedDays = $requiredDays->diff($loggedDates);
            $missedLogs += $missedDays->count();
            $requiredLogs += $requiredDays->count();
        }

        // If no logs are missed, no charge is applied
        if ($missedLogs == 0) {
            $this->info("User $userId has met the progress requirements for challenge $challengeId. No charge applied.");
            return;
        }

        // Charge user for each missed log
        $chargeAmount = $booking->punishment_amount * $missedLogs * 100; // Convert to cents

        Stripe::setApiKey(env('STRIPE_SECRET'));

        try {
            // Platform's share (e.g., 20% for the platform)
            $platformShare = 0.1;
            $creatorShare = 0.9; // 90% for the challenge creator

            // Get the challenge creator's Stripe account ID from the users table
            $challengeCreator = DB::table('users')->where('id', $booking->user_id)->first();

            // If no stripe_account_id exists for the creator, return error
            if (!$challengeCreator || !$challengeCreator->stripe_account_id) {
                $this->error('Challenge creator does not have a connected Stripe account.');
                return;
            }

            // Calculate the platform fee (admin's share)
            $applicationFeeAmount = $chargeAmount * $platformShare; // Admin fee (10%)

            // Create the PaymentIntent and set up a transfer to the challenge creator's account
            $paymentIntent = \Stripe\PaymentIntent::create([
                'amount' => $chargeAmount,
                'currency' => 'usd',
                'payment_method' => $booking->payment_method_id,
                'confirm' => true,
                'off_session' => true, // Charge without user interaction
                'transfer_data' => [
                    'destination' => $challengeCreator->stripe_account_id, // The challenge creator's Stripe account
                    'amount' => $chargeAmount * $creatorShare, // Amount for the creator (90%)
                ],
                'application_fee_amount' => $applicationFeeAmount, // Platform's fee (10%)
            ]);

            if ($paymentIntent->status === 'succeeded') {
                // Update booking status to 'completed'
                DB::table('bookings')->where('id', $booking->id)->update([
                    'status' => 'completed',
                    'updated_at' => now(),
                ]);
                $this->info("User $userId charged successfully for {$missedLogs} missed logs! Total: $" . number_format($chargeAmount / 100, 2));
            } else {
                $this->error("Payment failed for user $userId in challenge $challengeId.");
            }
        } catch (\Exception $e) {
            $this->error('Error: ' . $e->getMessage());
        }
    }
}
