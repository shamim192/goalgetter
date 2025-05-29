<?php

namespace App\Http\Controllers\Api;

use Carbon\Carbon;
use Stripe\Stripe;
use Stripe\Webhook;
use App\Models\User;
use App\Models\Booking;
use App\Models\Challenge;
use Stripe\PaymentIntent;
use App\Traits\ApiResponse;
use App\Models\Notification;
use App\Services\FCMService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class EventController extends Controller
{
    use ApiResponse;


    public function myCreatedChallenges()
    {
        $user = auth('api')->user();

        // Fetch challenges created by the authenticated user
        $createdChallenges = Challenge::with('goals:id,challenge_id,goal_type')
            ->where('user_id', $user->id) // Filter by the authenticated user's ID
            ->select('id', 'name', 'start_date', 'end_date', 'created_at') // Select necessary fields
            ->orderBy('created_at', 'desc')
            ->get();

        // Format each challenge
        $data = $createdChallenges->map(function ($challenge) {
            // Format start and end dates
            $challenge->date = Carbon::parse($challenge->start_date)->format('d M Y') . ' - ' . Carbon::parse($challenge->end_date)->format('d M Y');

            // Extract goal types into a comma-separated string
            $challenge->goal_types = $challenge->goals->pluck('goal_type')->implode(', ');

            // Fetch the first 3 participants with avatars
            $participants = $challenge->participants()
                ->select('users.id as user_id', 'users.avatar')
                ->take(3)
                ->get()
                ->map(function ($participant) {
                    return [
                        'user_id' => $participant->user_id,
                        'avatar' => $participant->avatar ?: null,
                    ];
                });

            // Return formatted challenge data
            return [
                'id' => $challenge->id,
                'name' => $challenge->name,
                'date' => $challenge->date,
                'goal_types' => $challenge->goal_types,
                'created_at' => $challenge->created_at,
                'participants' => $participants,
            ];
        });

        return $this->success($data, 'My Created challenges retrieved successfully!', 200);
    }


    public function joinedChallengeList(Request $request)
    {
        $user = auth('api')->user();

        // Fetch challenges the user has joined with goals and limited participants
        $joinedChallenges = Challenge::whereHas('participants', function ($query) use ($user) {
            $query->where('user_id', $user->id);
        })
            ->with(['goals:id,challenge_id,goal_type']) // Include goals with minimal fields
            ->select('id', 'name', 'start_date', 'end_date', 'created_at') // Fetch only required fields
            ->orderBy('created_at', 'desc')
            ->get();

        // Format each challenge
        $data = $joinedChallenges->map(function ($challenge) {
            // Format start and end dates
            $challenge->date = Carbon::parse($challenge->start_date)->format('d M Y') . ' - ' . Carbon::parse($challenge->end_date)->format('d M Y');

            // Extract goal types into a comma-separated string
            $challenge->goal_types = $challenge->goals->pluck('goal_type')->implode(', ');

            // Fetch the first 3 participants with avatars
            $participants = $challenge->participants()
                ->select('users.id as user_id', 'users.avatar')
                ->take(3)
                ->get()
                ->map(function ($participant) {
                    return [
                        'user_id' => $participant->user_id,
                        'avatar' => $participant->avatar ?? null,
                    ];
                });

            // Return formatted challenge data
            return [
                'id' => $challenge->id,
                'name' => $challenge->name,
                'date' => $challenge->date,
                'goal_types' => $challenge->goal_types,
                'created_at' => $challenge->created_at,
                'participants' => $participants,
            ];
        });

        return $this->success($data, 'Joined Challenges retrieved successfully!', 200);
    }

    // public function joinChallenge(Request $request)
    // {
    //     $user = auth('api')->user();
    //     $challengeId = $request->input('challenge_id');
    //     $paymentMethodId = $request->input('payment_method_id'); // Payment Method ID from Stripe

    //     // Validate punishment amount (if needed)
    //     $punishmentAmount = $request->input('punishment_amount'); // In dollars

    //     $validAmounts = [1000, 500, 200, 100, 50, 20, 10, 5];
    //     if (!in_array($punishmentAmount, $validAmounts)) {
    //         return response()->json(['message' => 'Invalid punishment amount'], 400);
    //     }

    //     // Save booking in the database
    //     $bookingId = DB::table('bookings')->insertGetId([
    //         'user_id' => $user->id,
    //         'challenge_id' => $challengeId,
    //         'payment_method_id' => $paymentMethodId, // Save the Payment Method ID
    //         'punishment_amount' => $punishmentAmount, // Save the punishment amount
    //         'status' => 'pending', // Initial status
    //         'joined_at' => now(),
    //     ]);

    //     // Fetch event owner
    //     $eventOwner = User::whereHas('challenges', function ($query) use ($challengeId) {
    //         $query->where('id', $challengeId);
    //     })->first();

    //     if ($eventOwner && $eventOwner->fcm_token) {
    //         $title = "New Challenge Join!";
    //         $body = "{$user->name} joined your challenge.";
    //         $profileImage = $user->profile_image ?? 'https://your-default-image-url.com/avatar.png';
    //         $data = [
    //             'challenge_id' => $challengeId,
    //             'user_id' => $user->id,
    //             'profile_image' => $profileImage
    //         ];

    //         // Send Push Notification
    //         $fcmService = new FCMService();
    //         $fcmService->sendNotification($eventOwner->fcm_token, $title, $body, $data);

    //         // Store In-App Notification
    //         Notification::create([
    //             'user_id' => $eventOwner->id,
    //             'title' => $title,
    //             'body' => $body,
    //             'is_read' => false,
    //             'profile_image' => $profileImage
    //         ]);
    //     }

    //     return response()->json([
    //         'message' => 'Event joined successfully',
    //         'booking_id' => $bookingId,
    //     ]);
    // }

    public function joinChallenge(Request $request)
    {
        $user = auth('api')->user();

        // Validate request inputs
        $validator = Validator::make($request->all(), [
            'challenge_id' => 'required|exists:challenges,id',
            'payment_method_id' => 'required|string', // Store this for future charges
            'punishment_amount' => 'required|integer|in:1000,500,200,100,50,20,10,5',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->first()], 400);
        }

        $challengeId = $request->input('challenge_id');
        $paymentMethodId = $request->input('payment_method_id');
        $punishmentAmount = $request->input('punishment_amount');

        // **Check if the user is already part of this challenge**
        $existingBooking = DB::table('bookings')
            ->where('user_id', $user->id)
            ->where('challenge_id', $challengeId)
            ->exists();

        if ($existingBooking) {
            return response()->json(['message' => 'You have already joined this challenge.'], 400);
        }

        // **Save booking in the database (store payment method for future charges)**
        $bookingId = DB::table('bookings')->insertGetId([
            'user_id' => $user->id,
            'challenge_id' => $challengeId,
            'payment_method_id' => $paymentMethodId, // Store this for future penalties
            'punishment_amount' => $punishmentAmount,
            'status' => 'pending',
            'joined_at' => now(),
        ]);

        // **Fetch Event Owner (Fixed Query)**
        $eventOwner = User::whereHas('challenges', function ($query) use ($challengeId) {
            $query->where('challenges.id', $challengeId); // Explicitly specify table name
        })->first();

        // if ($eventOwner && $eventOwner->fcm_token) {
        //     $title = "New Challenge Join!";
        //     $body = "{$user->name} joined your challenge.";
        //     $profileImage = $user->profile_image ?? 'https://your-default-image-url.com/avatar.png';

        //     // **Send Push Notification**
        //     $fcmService = new FCMService();
        //     $fcmService->sendNotification($eventOwner->fcm_token, $title, $body, [
        //         'challenge_id' => $challengeId,
        //         'user_id' => $user->id,
        //         'profile_image' => $profileImage,
        //     ]);

        //     // **Store In-App Notification**
        //     Notification::create([
        //         'user_id' => $eventOwner->id,
        //         'title' => $title,
        //         'body' => $body,
        //         'is_read' => false,
        //         'profile_image' => $profileImage,
        //     ]);
        // }

        if ($eventOwner && $eventOwner->fcm_token) {
            // Debugging: Ensure event owner and fcm_token are correct
            Log::info('Event Owner Found:', ['event_owner' => $eventOwner]);

            $title = "New Challenge Join!";
            $body = "{$user->name} joined your challenge.";
            $profileImage = $user->profile_image ?? 'https://your-default-image-url.com/avatar.png';

            // Send Push Notification
            try {
                $fcmService = new FCMService();
                $response = $fcmService->sendNotification($eventOwner->fcm_token, $title, $body, [
                    'challenge_id' => $challengeId,
                    'user_id' => $user->id,
                    'profile_image' => $profileImage,
                ]);

                // Debugging: Log FCM Response
                Log::info('FCM Response:', ['response' => $response]);
            } catch (\Exception $e) {
                // Log any error that occurs during the push notification
                Log::error('Error Sending Push Notification:', ['error' => $e->getMessage()]);
            }

            // Store In-App Notification
            Notification::create([
                'user_id' => $eventOwner->id,
                'title' => $title,
                'body' => $body,
                'is_read' => false,
                'profile_image' => $profileImage,
            ]);
        }

        return response()->json([
            'message' => 'Challenge joined successfully!',
            'booking_id' => $bookingId,
        ], 200);
    }

}
