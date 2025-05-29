<?php

namespace App\Http\Controllers\Api;

use Carbon\Carbon;
use App\Models\Goal;
use App\Models\User;
use App\Models\Challenge;
use App\Models\GoalProgress;
use Illuminate\Http\Request;
use App\Models\ExceptionRequest;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class GoalProgressController extends Controller
{
    public function logProgress(Request $request, $challengeId)
    {
        $user = auth('api')->user();

        // Fetch the challenge by ID
        $challenge = Challenge::find($challengeId);

        // Get today's date
        $today = Carbon::today();

        // Check if the current date is within the challenge date range
        if ($today->lt(Carbon::parse($challenge->start_date)) || $today->gt(Carbon::parse($challenge->end_date))) {
            return response()->json([
                'success' => true,
                'message' => 'The challenge has expired. You cannot log progress after the challenge end date.'
            ], 200);  // Respond with 400 Bad Request or appropriate error code
        }


        // Validate the request
        $request->validate([
            'date' => 'required|date',
            'goals' => 'required|array',
            'goals.*.goal_id' => 'required|exists:goals,id',
            'goals.*.progress_data' => 'nullable|string',
            'goals.*.photo' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048', // Ensure photo is valid
            'goals.*.fat_percentage' => 'nullable|numeric|between:0,100', // Fat percentage validation
        ]);

        $progressEntries = [];
        $missedDays = [];
        $goalIds = collect($request->goals)->pluck('goal_id')->toArray();
        $today = Carbon::parse($request->date);

        // Fetch the goals for the challenge
        $goals = Goal::where('challenge_id', $challengeId)
            ->whereIn('id', $goalIds)
            ->get()
            ->keyBy('id');

        // Fetch all existing progress logs
        $existingProgressLogs = GoalProgress::whereIn('goal_id', $goalIds)
            ->where('user_id', $user->id)
            ->orderBy('date', 'asc')
            ->get()
            ->groupBy('goal_id');

        foreach ($request->goals as $index => $goalData) {
            $goalId = $goalData['goal_id'];

            // Ensure goal exists in this challenge
            if (!isset($goals[$goalId])) {
                return response()->json(['success' => "Goal ID {$goalId} does not belong to this challenge."], 200);
            }

            $goal = $goals[$goalId];
            $goalType = $goal->goal_type;  // Fetch goal type directly from the goal
            $goalFrequency = $goal->frequency;
            $progressLogs = $existingProgressLogs[$goalId] ?? collect();
            $lastLogDate = $progressLogs->last()?->date;
            $lastLog = $progressLogs->last() ? Carbon::parse($progressLogs->last()->date) : null;


            // Handle goal frequency (Daily or Weekly)
            if ($goalFrequency === 'Daily (7 days of the week)') {
                $expectedPreviousDate = $today->copy()->subDay()->toDateString();

                if ($progressLogs->isNotEmpty() && $lastLogDate !== $expectedPreviousDate) {
                    // User missed a day, apply penalty
                    $missedDays[$goalId][] = $expectedPreviousDate;
                }
            } elseif ($goalFrequency === '5 days out of the week') {
                $weekStart = $today->copy()->startOfWeek();
                $weekLogs = $progressLogs->whereBetween('date', [$weekStart, $today]);

                if ($weekLogs->count() >= 5) {
                    return response()->json([
                        'success' => "You've already logged 5 times this week. No additional logs required."
                    ], 200);
                }
            } elseif ($goalFrequency === 'Every other day') {
                if ($progressLogs->isNotEmpty()) {
                    $lastLogDate = Carbon::parse($progressLogs->last()->date);
                    $daysSinceLastLog = $lastLogDate->diffInDays($today);

                    if ($daysSinceLastLog > 2) {
                        // Find all missed dates between last log and today
                        $date = $lastLogDate->copy()->addDays(2);
                        while ($date->lt($today)) {
                            $missedDays[$goalId][] = $date->toDateString();
                            $date->addDays(2);
                        }
                    }
                }
            }

            // **Handle "3 times a week" frequency**
            elseif ($goalFrequency === '3 times a week') {
                $weekStart = $today->copy()->startOfWeek();
                $weekLogs = $progressLogs->whereBetween('date', [$weekStart, $today]);

                if ($weekLogs->count() >= 3) {
                    return response()->json([
                        'success' => "You've already logged 3 times this week. No additional logs required."
                    ], 200);
                }
            }

            // Prevent duplicate progress on the same day
            if ($progressLogs->contains('date', $request->date)) {
                return response()->json(['success' => "You have already logged progress for goal {$goalId} today."], 200);
            }

            // Handle goal type validation
            $progressData = $goalData['progress_data'] ?? null;
            $photo = $request->file("goals.{$index}.photo");
            $fatPercentage = $goalData['fat_percentage'] ?? null;

            // Check for 'Workout More' goal type
            if ($goalType === 'Workout More') {
                if (!$progressData || !in_array($progressData, ['10 minutes', '20 minutes', '30 minutes', '1 hour'])) {
                    return response()->json(['success' => "Valid durations are required for 'Workout More' (e.g., '10 minutes', '30 minutes')."], 200);
                }
            }

            // Check for 'Checkmark' goal type
            if ($goalType === 'Checkmark') {
                if ($progressData !== 'Checkmark') {
                    return response()->json(['success' => "For 'Checkmark' goal type, the progress data must be 'Checkmark'."], 200);
                }
            }

            // Check for 'Input Fat Percentage' goal type
            if ($goalType === 'Input Fat Percentage') {
                if ($fatPercentage !== null) {
                    if ($fatPercentage < 0 || $fatPercentage > 100) {
                        return response()->json(['success' => "Fat percentage must be between 0 and 100."], 200);
                    }
                    $progressData = $fatPercentage; // Assign fat percentage as progress data
                } else {
                    return response()->json(['success' => "Fat percentage is required for 'Input Fat Percentage' goal type."], 200);
                }
            }

            // Handle 'Photo' or 'Checkmark' types for 'Pray' or 'Eat Healthier'
            if ($goalType === 'Pray' || $goalType === 'Eat Healthier') {
                // If 'Checkmark' is selected, we directly assign it to progressData
                if ($progressData !== 'Checkmark' && $progressData !== 'Photo') {
                    return response()->json(['success' => "Valid types for '{$goalType}' are 'Photo' or 'Checkmark'."], 200);
                }

                if ($progressData === 'Checkmark') {
                    $progressData = $progressData; // This is now dynamically using the request value
                }

                // Handle photo validation for 'Pray' or 'Eat Healthier' if 'Photo' is selected
                if ($progressData === 'Photo' && !$photo) {
                    return response()->json(['success' => "A photo is required for '{$goalType}' progress."], 200);
                }
            }

            // Handle 'Photo' type validation for other goals
            // if ($goalType === 'Loose Weight' || $goalType === 'Gain Weight') {
            //     if (!isset($goalData['option_to_share']) || !in_array($goalData['option_to_share'], ['Photo', 'Input Fat Percentage'])) {
            //         return response()->json(['success' => "Valid options for '{$goalType}' are 'Photo' or 'Input Fat Percentage'."], 200);
            //     }

            //     if ($goalData['option_to_share'] === 'Photo' && !$photo) {
            //         return response()->json(['success' => "A photo is required for '{$goalType}' progress."], 200);
            //     }
            // }

            if ($goalType === 'Lose Weight' || $goalType === 'Gain Weight') {
                // Use the option_to_share from the goal configuration
                $sharingOption = $goal->option_to_share;

                if ($sharingOption === 'Photo' && !$photo) {
                    return response()->json(['success' => "A photo is required for '{$goalType}' progress."], 200);
                }

                if ($sharingOption === 'Input Fat Percentage') {
                    if (!isset($goalData['fat_percentage'])) {
                        return response()->json(['success' => "Fat percentage is required for '{$goalType}' progress."], 200);
                    }
                    if ($goalData['fat_percentage'] < 0 || $goalData['fat_percentage'] > 100) {
                        return response()->json(['success' => "Fat percentage must be between 0 and 100."], 200);
                    }
                    $progressData = $goalData['fat_percentage'];
                }
            }

            if (!$progressData && !$photo) {
                return response()->json(['success' => "Progress data or a photo is required for goal {$goalId}."], 200);
            }

            // Handle Photo Upload (Save in `public/uploads/`)
            if ($photo) {
                $filename = time() . '_' . $photo->getClientOriginalName();
                $photo->move(public_path('uploads/progress_photos/' . $user->id), $filename);
                $progressData = 'uploads/progress_photos/' . $user->id . '/' . $filename; // Store relative path in DB
            }

            // Save progress data to array
            $progressEntries[] = [
                'goal_id' => $goal->id,
                'user_id' => $user->id,
                'date' => $request->date,
                'progress_data' => $progressData,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        // Insert the progress entries into the database
        GoalProgress::insert($progressEntries);

        return response()->json([
            'message' => 'Progress logged successfully!',
            'progress' => $progressEntries,
            'missed_days' => $missedDays, // Send back missed days for penalties
        ]);
    }



    public function requestException(Request $request, $challengeId)
    {
        $user = auth('api')->user();

        // Validate request
        $request->validate([
            'date' => 'required|date',
            'photo' => 'nullable|image|mimes:jpeg,png,jpg,gif', // Photo is optional but validated
            'text' => 'nullable|string|max:1000', // Optional text
        ]);

        // Handle photo upload if exists
        $photoPath = null;
        if ($request->hasFile('photo')) {
            $photo = $request->file('photo');
            $filename = time() . '_' . $photo->getClientOriginalName();
            $photo->move(public_path('uploads/exception_photos/' . $user->id), $filename);
            $photoPath = 'uploads/exception_photos/' . $user->id . '/' . $filename;
        }

        // Check if the challenge exists
        $challenge = Challenge::find($challengeId);

        if (!$challenge) {
            return response()->json([
                'success' => 'Challenge not found',
                'data' => []
            ], 200);
        }

        // Store exception request in the database
        $exception = new ExceptionRequest();
        $exception->user_id = $user->id;
        $exception->challenge_id = $challengeId;
        $exception->date = $request->date;
        $exception->photo = $photoPath;
        $exception->text = $request->text;
        $exception->status = 'pending'; // Set status as 'pending' initially
        $exception->save();

        return response()->json(['message' => 'Exception request submitted successfully. Awaiting approval.'], 200);
    }

    public function getExceptions(Request $request)
    {
        // Get the authenticated user
        $user = auth('api')->user();

        // Check if the authenticated user is the event owner
        $eventOwner = User::whereHas('challenges', function ($query) use ($user) {
            // Specify 'bookings.user_id' to avoid ambiguity
            $query->whereHas('bookings', function ($query) use ($user) {
                $query->where('bookings.user_id', $user->id);
            });
        })->first();

        // If no event owner, return an empty response or error
        if (!$eventOwner) {
            return response()->json([
                'success' => 'User is not an event owner',
                'data' => []
            ], 403);
        }

        // Get the status filter from the query parameter (defaults to 'all')
        $statusFilter = $request->query('status', 'all');

        // Initialize the query to fetch exception requests
        $query = ExceptionRequest::query();

        // Filter by status if it's not 'all'
        if ($statusFilter !== 'all') {
            $query->where('status', $statusFilter);
        }

        // Get the exception requests
        $exceptions = $query->get();

        // If there are no exception requests, return an empty array
        if ($exceptions->isEmpty()) {
            return response()->json([], 200);
        }

        // Format the response to match the layout
        $formattedExceptions = $exceptions->map(function ($exception) {
            return [
                'user_name' => $exception->user->name, // Assuming the user's name is stored in the `name` column
                'challenge_name' => $exception->challenge->name, // Assuming the challenge's name is in the `name` column
                'status' => ucfirst($exception->status), // Capitalize the first letter of the status
                'issue_placed' => $exception->created_at->format('d M Y'), // Format the date
            ];
        });

        // Return the formatted exception requests
        return response()->json([
            'success' => 'Exception requests fetched successfully',
            'data' => $formattedExceptions
        ], 200);
    }



    public function handleExceptionRequest($exceptionId, $action)
    {
        // Fetch the exception request
        $exception = ExceptionRequest::find($exceptionId);

        // If the exception doesn't exist or has already been processed, return an error
        if (!$exception) {
            return response()->json([
                'success' => 'Exception not found',
                'data' => []
            ], 200);
        }

        // Check if the status is already processed (either accepted or canceled)
        if ($exception->status !== 'pending') {
            return response()->json([
                'success' => 'This request has already been processed.',
                'data' => []
            ], 200);
        }

        // Start handling based on the action (accept or decline)
        if ($action === 'accept') {
            // Mark the exception as accepted
            $exception->status = 'approved';
            $exception->save();

            // Now, insert/update the goal progress for the user
            $goals = Goal::where('challenge_id', $exception->challenge_id)->get(); // Fetch all goals for the challenge

            foreach ($goals as $goal) {
                GoalProgress::create([
                    'goal_id' => $goal->id,
                    'user_id' => $exception->user_id,
                    'date' => now(), // Current date or date user completes
                    'progress_data' => 'Completed', // Customize based on the goal type
                ]);
            }

            return response()->json(['success' => 'Exception request accepted, goals marked as completed.'], 200);
        } elseif ($action === 'decline') {
            // Mark the exception as declined
            $exception->status = 'canceled';
            $exception->save();

            return response()->json(['success' => 'Exception request declined, penalties applied.'], 200);
        } else {
            return response()->json(['error' => 'Invalid action. Please use "accept" or "decline".'], 400);
        }
    }
}
