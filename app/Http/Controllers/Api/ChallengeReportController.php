<?php

namespace App\Http\Controllers\Api;

use Carbon\Carbon;
use App\Models\Goal;
use App\Models\User;
use App\Models\Challenge;
use App\Models\GoalProgress;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;

class ChallengeReportController extends Controller
{
    // public function getDailyChallengeProgress($challengeId)
    // {
    //     // Fetch the challenge
    //     $challenge = Challenge::find($challengeId);

    //     if (!$challenge) {
    //         return response()->json(['error' => 'Challenge not found'], 404);
    //     }

    //     // Fetch all goals for the challenge
    //     $goals = Goal::where('challenge_id', $challengeId)->get();

    //     // Fetch all users who have logged progress for this challenge
    //     $users = User::whereHas('goalProgress', function ($query) use ($challengeId) {
    //         $query->whereHas('goal', function ($query) use ($challengeId) {
    //             $query->where('challenge_id', $challengeId);
    //         });
    //     })->get();

    //     // Initialize the response structure
    //     $response = [
    //         'challenge_name' => $challenge->name,
    //         'participants' => []
    //     ];

    //     // Populate participants and their progress
    //     foreach ($users as $user) {
    //         $userProgress = [];
    //         foreach ($goals as $goal) {
    //             // Fetch all progress logs for this user and goal
    //             $progressLogs = GoalProgress::where('goal_id', $goal->id)
    //                 ->where('user_id', $user->id)
    //                 ->orderBy('date', 'asc')
    //                 ->get();

    //             // Calculate progress based on goal frequency
    //             $progressPercentage = $this->calculateProgressPercentage($goal->frequency, $progressLogs);

    //             // Check if the user logged progress for this goal today
    //             $loggedToday = GoalProgress::where('goal_id', $goal->id)
    //                 ->where('user_id', $user->id)
    //                 ->whereDate('date', Carbon::today())
    //                 ->exists();

    //             $userProgress[] = [
    //                 'goal_name' => $goal->goal_type, // Use goal_type as the goal name
    //                 'progress_percentage' => $progressPercentage . '%', // Progress percentage
    //                 'logged_today' => $loggedToday ? 'Yes' : 'No' // Indicate if progress was logged today
    //             ];
    //         }

    //         $response['participants'][] = [
    //             'name' => $user->name,
    //             'goals' => $userProgress
    //         ];
    //     }

    //     return response()->json([
    //         'success' => 'Daily Report fetched successfully',
    //         'data' => $response
    //     ], 200);
    // }
public function getDailyChallengeProgress($challengeId)
{
    // Fetch the challenge along with participants
    $challenge = Challenge::with('participants')->find($challengeId);

    if (!$challenge) {
        return response()->json([
            'success' => false,
            'message' => 'Challenge not found'
        ], 404);
    }

    // Fetch all goals for the challenge
    $goals = Goal::where('challenge_id', $challengeId)->get();

    if ($goals->isEmpty()) {
        return response()->json([
            'success' => false,
            'message' => 'No goals found for this challenge'
        ], 404);
    }

    $users = $challenge->participants; // ✅ use participants relation!

    // Prepare the response structure
    $response = [
        'challenge_name' => $challenge->name,
        'participants' => []
    ];

    $totalChallengeProgress = 0; // Track overall challenge progress

    foreach ($users as $user) {
        $userProgress = [];
        $totalProgress = 0;

        foreach ($goals as $goal) {
            // Fetch all progress logs for this user and goal
            $progressLogs = GoalProgress::where('goal_id', $goal->id)
                ->where('user_id', $user->id)
                ->orderBy('date', 'asc')
                ->get();

            // Calculate the progress percentage
            $progressPercentage = $progressLogs->isNotEmpty()
                ? $this->calculateProgressPercentage($goal->frequency, $progressLogs)
                : 0;

            // Add to total progress
            $totalProgress += $progressPercentage;

            // Check if user logged today
            $loggedToday = $progressLogs->where('date', Carbon::today()->toDateString())->isNotEmpty();

            $userProgress[] = [
                'goal_name' => $goal->goal_type,
                'progress_percentage' => round($progressPercentage, 2),
                'logged_today' => $loggedToday ? 'Yes' : 'No',
            ];
        }

        $averageProgress = $totalProgress / max(count($goals), 1); // prevent division by zero
        $totalChallengeProgress += $averageProgress; // add user's average to total

        $response['participants'][] = [
            'name' => $user->name,
            'goals' => $userProgress,
            'average_progress_percentage' => round($averageProgress, 2),
        ];
    }

    // Calculate overall challenge progress
    $overallProgress = count($response['participants']) > 0
        ? ($totalChallengeProgress / count($response['participants']))
        : 0;

    $response['overall_progress_percentage'] = round($overallProgress, 2);

    return response()->json([
        'success' => true,
        'message' => 'Daily Report fetched successfully',
        'data' => $response
    ], 200);
}


    /**
     * Calculate progress percentage based on goal frequency and progress logs.
     */
    private function calculateProgressPercentage($frequency, $progressLogs)
    {
        $today = Carbon::today();
        $totalRequired = 0;
        $totalCompleted = 0;

        switch ($frequency) {
            case 'Daily (7 days of the week)':
                // Calculate progress for daily goals
                $totalRequired = 7; // 7 days in a week
                $totalCompleted = $progressLogs->count();
                break;

            case '5 days out of the week':
                // Calculate progress for 5 days a week
                $totalRequired = 5;
                $totalCompleted = $progressLogs->count();
                break;

            case 'Every other day':
                // Calculate progress for every other day
                if ($progressLogs->isNotEmpty()) {
                    $firstLogDate = Carbon::parse($progressLogs->first()->date);
                    $totalRequired = floor($today->diffInDays($firstLogDate) / 2);
                    $totalCompleted = $progressLogs->count();
                }
                break;

            case '3 times a week':
                // Calculate progress for 3 times a week
                $totalRequired = 3;
                $totalCompleted = $progressLogs->count();
                break;

            default:
                // Default to 0% if frequency is not recognized
                return 0;
        }

        // Calculate percentage
        if ($totalRequired > 0) {
            return min(100, ($totalCompleted / $totalRequired) * 100);
        }

        return 0;
    }

    public function getWeeklyChallengeProgress($challengeId)
    {
        // Fetch the challenge and its associated goals
        $challenge = Challenge::find($challengeId);
        if (!$challenge) {
            return response()->json(['success' => 'Challenge not found'], 200);
        }
        $goals = Goal::where('challenge_id', $challengeId)->get();

        // Get all users participating in the challenge
        $users = User::whereHas('goalProgress', function ($query) use ($challengeId) {
            $query->whereHas('goal', function ($query) use ($challengeId) {
                $query->where('challenge_id', $challengeId);
            });
        })->get();

        // Initialize the response structure
        $response = [
            'challenge_name' => $challenge->name,
            'participants' => []
        ];

        // For each user, generate their weekly summary
        foreach ($users as $user) {
            $userProgress = [];

            // Check progress for each day (Sunday to Saturday)
            for ($i = 0; $i < 7; $i++) {
                // Adjust this to start from Sunday
                $dayOfWeek = Carbon::now()->startOfWeek(Carbon::SUNDAY)->addDays($i); // Sunday to Saturday
                $dayProgress = [];
                $goalsLogged = 0;
                $totalGoals = 0;

                // Get the first letter of the day (e.g., "S" for Sunday, "M" for Monday)
                $dayName = $dayOfWeek->format('D');  // Grab the first letter (e.g., 'S' for Sunday)

                foreach ($goals as $goal) {
                    // Check if the user logged progress for this goal on this day
                    $loggedToday = GoalProgress::where('goal_id', $goal->id)
                        ->where('user_id', $user->id)
                        ->whereDate('date', $dayOfWeek)
                        ->exists();

                    // Increment counters
                    if ($loggedToday) {
                        $goalsLogged++;
                    }
                    $totalGoals++;

                    // Add the logged or missed status to the day progress
                    $dayProgress[] = $loggedToday ? 'green' : 'red';
                }

                // Calculate the percentage for the day
                $percentage = ($totalGoals > 0) ? round(($goalsLogged / $totalGoals) * 100, 2) : 0;

                // Add the daily progress summary and percentage for this day to the user
                $userProgress[] = [
                    'day_name' => $dayName,  // Add the first letter of the day
                    'day_progress' => $dayProgress,
                    'percentage' => $percentage // Adding the percentage
                ];
            }

            // Add the user progress to the response
            $response['participants'][] = [
                'name' => $user->name,
                'weekly_summary' => $userProgress
            ];
        }

        return response()->json([
            'success' => 'Weekly report fetched successfully',
            'data' => $response
        ], 200);
    }



    public function getUserDailyChallengeProgress($challengeId)
    {
        // Fetch the challenge
        $challenge = Challenge::find($challengeId);

        if (!$challenge) {
            return response()->json(['success' => 'Challenge not found'], 200);
        }

        // Fetch all participants for the challenge
        $participants = $challenge->participants()->get(); // Assuming 'participants()' is a relationship method to fetch participants

        // Fetch the authenticated user
        $user = auth()->user();

        // Check if the user is a participant in the challenge
        if (!$participants->contains('id', $user->id)) {
            return response()->json(['success' => 'User is not a participant in this challenge'], 200);
        }

        // Fetch all goals for the challenge
        $goals = Goal::where('challenge_id', $challengeId)->get();

        // Initialize the response structure
        $response = [
            'challenge_name' => $challenge->name,
            'user_name' => $user->name,
            'user_progress' => []
        ];

        // Initialize total progress tracking
        $totalProgress = 0;
        $totalGoals = count($goals);

        // Loop through each goal to fetch the progress for this user
        foreach ($goals as $goal) {
            // Fetch all progress logs for this user and goal
            $progressLogs = GoalProgress::where('goal_id', $goal->id)
                ->where('user_id', $user->id)
                ->orderBy('date', 'asc')
                ->get();

            // Calculate progress based on goal frequency
            $progressPercentage = $this->calculateProgressPercentage($goal->frequency, $progressLogs);

            // Add to total progress
            $totalProgress += $progressPercentage;

            // Check if the user logged progress for this goal today
            $loggedToday = GoalProgress::where('goal_id', $goal->id)
                ->where('user_id', $user->id)
                ->whereDate('date', Carbon::today())
                ->exists();

            $response['user_progress'][] = [
                'goal_name' => $goal->goal_type, // Goal name
                'progress_percentage' => $progressPercentage, // Progress percentage for the goal
                'logged_today' => $loggedToday ? 'Yes' : 'No' // Whether progress was logged today
            ];
        }

        // Calculate the average progress percentage for all goals
        $averageProgress = $totalProgress / $totalGoals;

        // Add the total progress to the response
        $response['average_progress_percentage'] = round($averageProgress, 2);

        return response()->json([
            'success' => 'User Daily Challenge Progress Report fetched successfully',
            'data' => $response
        ], 200);
    }

    public function getUserWeeklyChallengeProgress($challengeId)
    {
        // Fetch the challenge and its associated goals
        $challenge = Challenge::find($challengeId);
        if (!$challenge) {
            return response()->json(['success' => 'Challenge not found'], 200);
        }
        $goals = Goal::where('challenge_id', $challengeId)->get();

        // Get the authenticated user
        $user = auth()->user();

        // Check if the user is a participant in the challenge
        $isParticipant = $challenge->participants()->where('user_id', $user->id)->exists();

        if (!$isParticipant) {
            return response()->json(['success' => 'User is not a participant in this challenge'], 200);
        }

        // Initialize the response structure
        $response = [
            'challenge_name' => $challenge->name,
            'user_name' => $user->name,
            'weekly_summary' => []
        ];

        // Check progress for each day (Sunday to Saturday)
        for ($i = 0; $i < 7; $i++) {
            // Adjust this to start from Sunday
            $dayOfWeek = Carbon::now()->startOfWeek(Carbon::SUNDAY)->addDays($i); // Sunday to Saturday
            $dayProgress = [];
            $goalsLogged = 0;
            $totalGoals = 0;

            // Get the first letter of the day (e.g., "S" for Sunday, "M" for Monday)
            $dayName = $dayOfWeek->format('D');  // Grab the first letter (e.g., 'S' for Sunday)

            foreach ($goals as $goal) {
                // Check if the user logged progress for this goal on this day
                $loggedToday = GoalProgress::where('goal_id', $goal->id)
                    ->where('user_id', $user->id)
                    ->whereDate('date', $dayOfWeek)
                    ->exists();

                // Increment counters
                if ($loggedToday) {
                    $goalsLogged++;
                }
                $totalGoals++;

                // Add the logged or missed status to the day progress
                $dayProgress[] = $loggedToday ? 'green' : 'red';
            }

            // Calculate the percentage for the day
            $percentage = ($totalGoals > 0) ? round(($goalsLogged / $totalGoals) * 100, 2) : 0;

            // Add the daily progress summary and percentage for this day to the user
            $response['weekly_summary'][] = [
                'day_name' => $dayName,  // Add the first letter of the day
                'day_progress' => $dayProgress,
                'percentage' => $percentage // Adding the percentage
            ];
        }

        return response()->json([
            'success' => 'User Weekly Challenge Progress Report fetched successfully',
            'data' => $response
        ], 200);
    }

    public function getChallengeCompletionProgress($challengeId)
    {
        // Fetch the challenge and its associated goals
        $challenge = Challenge::find($challengeId);
        if (!$challenge) {
            return response()->json(['success' => 'Challenge not found'], 200);
        }

        // Get the authenticated user
        $user = auth()->user();

        // Check if the user is a participant in the challenge
        $isParticipant = $challenge->participants()->where('user_id', $user->id)->exists();

        if (!$isParticipant) {
            return response()->json(['success' => 'User is not a participant in this challenge'], 200);
        }

        // Get all the goals associated with the challenge
        $goals = Goal::where('challenge_id', $challengeId)->get();

        // Calculate the total number of goals for the challenge
        $totalGoals = $goals->count();

        // Count the number of completed goals for the authenticated user
        $completedGoals = 0;

        // Loop through the goals and check if the user has logged progress
        foreach ($goals as $goal) {
            // Check if there is any progress logged for the goal by the user
            $loggedProgress = GoalProgress::where('goal_id', $goal->id)
                ->where('user_id', $user->id)
                ->exists();

            if ($loggedProgress) {
                $completedGoals++;
            }
        }

        // Calculate the completion percentage
        $completionPercentage = ($totalGoals > 0) ? round(($completedGoals / $totalGoals) * 100, 2) : 0;

        // Prepare the response with challenge name and completion percentage
        $response = [
            'challenge_name' => $challenge->name,
            'completion_percentage' => $completionPercentage, // Add the percentage
        ];

        return response()->json([
            'success' => 'Challenge progress fetched successfully',
            'data' => $response
        ], 200);
    }

    public function getChallengeOwnerCompletionProgress($challengeId)
    {
        // Fetch the challenge and check if it exists
        $challenge = Challenge::find($challengeId);
        if (!$challenge) {
            return response()->json(['success' => 'Challenge not found'], 200);
        }

        // Get the authenticated user
        $user = auth()->user();

        // Check if the user is the challenge owner
        if ($challenge->user_id != $user->id) {
            return response()->json(['success' => 'User is not the owner of this challenge'], 200);
        }

        // Get all the goals associated with the challenge
        $goals = Goal::where('challenge_id', $challengeId)->get();

        // Initialize variables for completion calculation
        $totalGoals = $goals->count();
        $totalCompleted = 0;
        $totalParticipants = $challenge->participants()->count();  // Get the number of participants

        // If there are no goals or participants, return 0% completion
        if ($totalGoals === 0 || $totalParticipants === 0) {
            return response()->json([
                'success' => 'Challenge owner progress fetched successfully',
                'data' => [
                    'challenge_name' => $challenge->name,
                    'completion_percentage' => '0'
                ]
            ], 200);
        }

        // Loop through the goals and calculate the total progress from participants
        foreach ($goals as $goal) {
            // Get the number of participants who have logged progress for this goal
            $participantsWithProgress = GoalProgress::where('goal_id', $goal->id)
                ->distinct('user_id')  // Ensure unique participants are counted
                ->count();

            // Add the number of participants who completed this goal to totalCompleted
            $totalCompleted += $participantsWithProgress;
        }

        // Calculate the overall completion percentage
        $completionPercentage = round(($totalCompleted / ($totalGoals * $totalParticipants)) * 100, 2);

        // Prepare the response with challenge name and completion percentage
        $response = [
            'challenge_name' => $challenge->name,
            'completion_percentage' => $completionPercentage, // Overall challenge completion
        ];

        return response()->json([
            'success' => 'Challenge owner progress fetched successfully',
            'data' => $response
        ], 200);
    }
}
