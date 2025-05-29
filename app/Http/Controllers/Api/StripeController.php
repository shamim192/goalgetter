<?php

namespace App\Http\Controllers\Api;

use Stripe\Stripe;
use Stripe\Account;
use App\Models\User;
use Stripe\AccountLink;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class StripeController extends Controller
{
    public function createConnectedAccount(Request $request)
    {
        // Set the Stripe secret key from config
        Stripe::setApiKey(config('stripe.secret_key'));

        $user = auth('api')->user();

        if ($user->stripe_account_id) {
            return response()->json([
                'message' => 'User already has a connected account',
                'stripe_account_id' => $user->stripe_account_id,
            ]);
        }

        // Create a connected account
        $account = Account::create([
            'type' => 'express',
            'country' => 'US',
            'email' => $user->email,
            'business_type' => 'individual',
            'capabilities' => [
                'transfers' => ['requested' => true],
            ],
            'capabilities' => [
                'card_payments' => ['requested' => true],
                'transfers' => ['requested' => true],
            ],
        ]);


        // Generate an onboarding link
        $accountLink = AccountLink::create([
            'account' => $account->id,
            'refresh_url' => 'https://thiagozanett.softvencefsd.xyz/stripe/onboarding/refresh?account_id=' . $account->id . '&user_id=' . $user->id,
            // 'return_url' => 'https://thiagozanett.softvencefsd.xyz/stripe/onboarding/complete?account_id=' . $account->id,
            'return_url' => 'https://thiagozanett.softvencefsd.xyz/stripe/onboarding/complete?account_id=' . $account->id . '&user_id=' . $user->id,
            'type' => 'account_onboarding',
        ]);

        return response()->json([
            'onboarding_url' => $accountLink->url,
        ]);
    }

    // 2. Refresh onboarding
    public function refreshOnboarding(Request $request)
    {
        // Set the Stripe secret key from config
        Stripe::setApiKey(config('stripe.secret_key'));

        // Retrieve the authenticated user
        $user = auth('api')->user();

        // Extract the account ID and user ID from the query parameters
        $accountId = $request->query('account_id');
        $userId = $request->query('user_id');


        try {
            // Generate a fresh onboarding link for the existing account
            $accountLink = AccountLink::create([
                'account' => $accountId,
                'refresh_url' => 'https://thiagozanett.softvencefsd.xyz/stripe/onboarding/refresh?account_id=' . $accountId . '&user_id=' . $userId,
                'return_url' => 'https://thiagozanett.softvencefsd.xyz/stripe/onboarding/complete?account_id=' . $accountId . '&user_id=' . $userId,
                'type' => 'account_onboarding',
            ]);

            // return response()->json([
            //     'onboarding_url' => $accountLink->url,
            // ]);
            return redirect()->away($accountLink->url);
        } catch (\Stripe\Exception\ApiErrorException $e) {
            // Handle Stripe API errors
            return response()->json([
                'message' => 'An error occurred while generating the onboarding link',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function completeOnboarding(Request $request)
    {
        // Set the Stripe API key
        Stripe::setApiKey(config('stripe.secret_key'));

        // Retrieve account ID and user ID from the query string
        $accountId = $request->query('account_id');
        $userId = $request->query('user_id'); // User ID from the return URL

        if (!$accountId || !$userId) {
            return response()->json(['message' => 'Account ID or User ID is missing'], 200);
        }


        // Find the user in the database using user ID
        $user = User::find($userId);

        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        // Update the user's stripe_account_id in the database
        $user->update(['stripe_account_id' => $accountId]);

        return response()->json(['message' => 'Stripe account updated successfully']);
    }

    public function checkIfUserIsReadyForPayouts(Request $request)
    {
         $user = auth('api')->user();

        // Check if the user has a Stripe connected account
        if (!$user->stripe_account_id) {
            return response()->json(['success' => 'User does not have a connected Stripe account.'], 200);
        }

        // Initialize Stripe API
        Stripe::setApiKey(config('stripe.secret_key'));

        try {
            // Retrieve the user's Stripe account details using the account ID
            $account = \Stripe\Account::retrieve($user->stripe_account_id);

            // Check if payouts are enabled for the user's Stripe account
            if ($account->payouts_enabled) {
                return response()->json(['message' => 'User is ready for payouts.'], 200);
            } else {
                return response()->json(['message' => 'User is not ready for payouts yet.'], 200);
            }
        } catch (\Stripe\Exception\ApiErrorException $e) {
            // Handle Stripe API error
            return response()->json(['error' => 'Failed to retrieve account status: ' . $e->getMessage()], 500);
        }
    }

    public function checkIfUserIsConected(Request $request)
    {
         $user = auth('api')->user();

        // Check if the user has a Stripe connected account
        if (!$user->stripe_account_id) {
            return response()->json(['success' => 'User does not have a connected Stripe account.'], 200);
        }

        return response()->json(['success' => 'User has a connected Stripe account.'], 200);

    }
}
