<?php

namespace App\Http\Controllers\Api;

use Carbon\Carbon;
use Stripe\Stripe;
use App\Models\Goal;
use Stripe\Customer;
use App\Models\Challenge;
use Stripe\PaymentIntent;
use Stripe\PaymentMethod;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class ChallengeController extends Controller
{
    use ApiResponse;

    public function createChallenge(Request $request)
    {

        $user = Auth::user();

        // Define validation rules
        $validationRules = [
            'challenge_name' => 'required|string|max:255',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date', // Ensure end date is after start date
            'description' => 'nullable|string|max:1000', // Description is optional, but limited to 1000 characters
            'punishment' => 'required|string|in:Charge $1000 for a miss,Charge $500 for a miss,Charge $200 for a miss,Charge $100 for a miss,Charge $50 for a miss,Charge $20 for a miss,Charge $10 for a miss,Charge $5 for a miss', // Validate punishment
            'goals' => 'required|array|min:1',
            'goals.*.goal_type' => 'required|string|in:Workout More,Eat Healthier,Lose Weight,Gain Weight,Pray',
            'goals.*.frequency' => 'required|string', // Default frequency validation
        ];

        // Custom validation for goal-specific fields based on the goal type
        $requestData = $request->all();

        foreach ($requestData['goals'] as $index => $goal) {
            $goalType = $goal['goal_type'];

            // Apply conditional validation for frequency and other fields based on goal type
            if ($goalType == 'Workout More') {
                $validationRules["goals.$index.duration"] = 'required|string|in:10 minutes,20 minutes,30 minutes,1 hour'; // Duration is required for "Workout More"
            }

            if ($goalType == 'Eat Healthier' || $goalType == 'Lose Weight' || $goalType == 'Gain Weight' || $goalType == 'Pray') {
                $validationRules["goals.$index.frequency"] = 'required|string|in:Daily (7 days of the week),5 days out of the week,Every other day,3 times a week'; // Validate frequency for these goals
            }

            // Validate the option_to_share and type for goals that need them
            if ($goalType == 'Lose Weight' || $goalType == 'Gain Weight') {
                $validationRules["goals.$index.option_to_share"] = 'required|string|in:Photo,Input Fat Percentage'; // Validate option_to_share
            }

            // Validate type for "Eat Healthier" and "Pray"
            if ($goalType == 'Eat Healthier' || $goalType == 'Pray') {
                $validationRules["goals.$index.type"] = 'required|string|in:Photo,Checkmark'; // Validate type (Photo or Checkmark)
            }
        }

        // Validate the input data with dynamic rules
        $validator = Validator::make($request->all(), $validationRules);

        // If validation fails, return errors
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 400);
        }

        // Initialize Stripe
        Stripe::setApiKey(config('stripe.secret_key'));

        // Create and save the challenge
        $challenge = new Challenge();
        $challenge->name = $request->challenge_name;
        $challenge->user_id = Auth::user()->id;
        $challenge->start_date = $request->start_date;
        $challenge->end_date = $request->end_date;
        $challenge->description = $request->description;
        $challenge->punishment = $request->punishment;
        $challenge->save();

        // Loop through each goal and save them
        foreach ($request->goals as $goalData) {
            $goal = new Goal();
            $goal->challenge_id = $challenge->id;
            $goal->goal_type = $goalData['goal_type'];
            $goal->frequency = $goalData['frequency'];
            $goal->type = $goalData['type'] ?? null; // Save the goal type (Photo or Checkmark), nullable
            $goal->option_to_share = $goalData['option_to_share'] ?? null; // Save the option to share (Photo or Input Fat Percentage), nullable
            $goal->duration = $goalData['duration'] ?? null; // Duration only for "Workout More"
            $goal->save();
        }


        // Eager load goals with challenge and return the response
        $challengeWithGoals = Challenge::with('goals')->find($challenge->id);
        // Return a success response
        // return response()->json(['message' => 'Challenge and goals created successfully!'], 200);
        return $this->success($challengeWithGoals, 'Challenge and goals created successfully!', 201);
    }

    public function challengeList()
    {
        $challenges = Challenge::with('goals:id,challenge_id,goal_type')
            ->whereNot('user_id', Auth::user()->id)
            ->orderBy('created_at', 'desc')
            ->select('id', 'name', 'start_date', 'end_date', 'punishment', 'created_at')
            ->get();

        // Check if the challenge exists
        if (!$challenges) {
            return $this->success([], 'Challenge not found.', 200);
        }

        $data = [];

        foreach ($challenges as $challenge) {
            $challenge->date = Carbon::parse($challenge->start_date)->format('d M Y') . ' - ' . Carbon::parse($challenge->end_date)->format('d M Y');
            $challenge->goal_types = $challenge->goals->pluck('goal_type')->implode(', ');

            // Extract punishment amount (e.g., "Charge $50 for a miss" → 50)
            preg_match('/\$\d+/', $challenge->punishment, $matches);
            $punishmentAmount = isset($matches[0]) ? (int) filter_var($matches[0], FILTER_SANITIZE_NUMBER_INT) : 0;

            // Fetch only the first 3 participants with their avatars
            $participants = $challenge->participants()
                ->select('users.id as user_id', 'users.avatar') // Specify table name to avoid ambiguity
                ->take(3)
                ->get();

            // Add challenge data to the list
            $data[] = [
                'id' => $challenge->id,
                'name' => $challenge->name,
                'date' => $challenge->date,
                'goal_types' => $challenge->goal_types,
                'punishment' => $punishmentAmount,
                'created_at' => $challenge->created_at,
                'participants' => $participants
            ];
        }

        return $this->success($data, 'Challenges retrieved successfully!', 200);
    }

    public function challengeDetails($id)
    {
        $user = auth('api')->user(); // Get the authenticated user

        // Fetch challenge by ID with related goals
        $challenge = Challenge::with('goals')->find($id);

        // Check if the challenge exists
        if (!$challenge) {
            return $this->success([], 'Challenge not found.', 200);
        }

        // ✅ Check if the user has joined this challenge
        $isJoined = $challenge->participants()->where('user_id', $user->id)->exists();

        // Fetch only the first 3 participants with their avatars
        $participants = $challenge->participants()
            ->select('users.id as user_id', 'users.avatar') // Specify table name to avoid ambiguity
            ->take(3)
            ->get();

        // Convert punishment text to numeric value
        $punishmentAmount = null;
        if (!empty($challenge->punishment)) {
            preg_match('/\$(\d+)/', $challenge->punishment, $matches);
            $punishmentAmount = $matches[1] ?? null; // Extract the numeric value
        }

        // Add participants count and limited avatars to the response
        $challenge->participants_count = $challenge->participants()->count();  // Count all participants
        $challenge->participants = $participants;  // Only first 3 participants
        $challenge->is_joined = $isJoined; // ✅ Add `is_joined` flag
        $challenge->punishment_amount = $punishmentAmount; // ✅ Add extracted punishment amount

        return $this->success($challenge, 'Challenge retrieved successfully!', 200);
    }


    public function getExpiredChallenges()
    {
        $user = auth('api')->user(); // Get the authenticated user

        // Fetch challenges that have ended and the user has joined
        $expiredChallenges = Challenge::with('goals')
            ->where('end_date', '<', now())
            ->whereHas('participants', function ($query) use ($user) {
                $query->where('user_id', $user->id);
            })
            ->get();

        // Check if there are any expired challenges
        if ($expiredChallenges->isEmpty()) {
            return $this->success([], 'No expired challenges found.', 200);
        }

        return $this->success($expiredChallenges, 'Expired challenges retrieved successfully!', 200);
    }

    public function checkChallengeStatus($challengeId)
    {
        $challenge = Challenge::findOrFail($challengeId);
        $isExpired = now()->gt($challenge->end_date);

        // You can then use $isExpired as needed
        // return response()->json([
        //     'challenge' => $challenge,
        //     'is_expired' => $isExpired,
        //     'message' => $isExpired ? 'This challenge has expired' : 'This challenge is still active'
        // ]);
        return $this->success(['is_expired' => $isExpired], 'Challenge status retrieved successfully!', 200);
    }
}
