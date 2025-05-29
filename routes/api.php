<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\EventController;
use App\Http\Controllers\Api\StripeController;
use App\Http\Controllers\Api\ChallengeController;
use App\Http\Controllers\Api\ChallengeReportController;
use App\Http\Controllers\Api\SocialAuthController;
use App\Http\Controllers\Api\GoalProgressController;
use App\Http\Controllers\Api\NotificationController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::post('/social-login', [SocialAuthController::class, 'socialLogin']);

Route::post('/register', [UserController::class, 'register']);
Route::post('/verify-otp', [UserController::class, 'verifyOtp']);
Route::post('/resend-otp', [UserController::class, 'resendOtp']);


Route::post('/forgot-password', [UserController::class, 'requestPasswordReset']);
Route::post('/verify-reset-otp', [UserController::class, 'verifyPasswordResetOtp']);
Route::post('/set-new-password', [UserController::class, 'setNewPassword']);
Route::post('/resend-reset-otp', [UserController::class, 'resendResetOtp']);

Route::post('/login', [UserController::class, 'login']);


Route::group(['middleware' => ['jwt.verify']], function () {

    Route::post('/user/profile', [UserController::class, 'updateProfile']);
    Route::post('/user/profile/setting', [UserController::class, 'Profileupdate']);
    Route::post('/user/password', [UserController::class, 'updatePassword']);

    Route::post('logout', [SocialAuthController::class, 'logout']);


    Route::post('user/challenge', [ChallengeController::class, 'createChallenge']);

    Route::get('/challenge-list', [ChallengeController::class, 'challengeList']);
    Route::get('/challenge-details/{id}', [ChallengeController::class, 'challengeDetails']);

    // Route::post('/charge-missed-goal/{userId}/{challengeId}', [ChallengeController::class, 'chargeUserForMissedGoal']);

    Route::post('/challenge/{challengeId}/exception', [GoalProgressController::class, 'requestException']);
    Route::post('/exception/{exceptionId}/review', [GoalProgressController::class, 'reviewException']);
    Route::get('/exceptions', [GoalProgressController::class, 'getExceptions']);

    Route::post('/exception/{exceptionId}/{action}', [GoalProgressController::class, 'handleExceptionRequest']);


    Route::post('/stripe/create-connected-account', [StripeController::class, 'createConnectedAccount'])->name('stripe.create.account');
    Route::get('/user/check-payout-status', [StripeController::class, 'checkIfUserIsReadyForPayouts']);
    Route::get('/user/is-connected', [StripeController::class, 'checkIfUserIsConected']);

    // Join an event
    Route::post('/challenge/join', [EventController::class, 'joinChallenge']);

    Route::get('/challenge/joined/list', [EventController::class, 'joinedChallengeList']);
    Route::get('/my-challenge/list', [EventController::class, 'myCreatedChallenges']);

    Route::get('/users/stripe/status', [UserController::class, 'isStripeConnected']);

    Route::post('/challenges/{challengeId}/log-progress', [GoalProgressController::class, 'logProgress']);

    Route::post('/update-fcm-token', [UserController::class, 'updateFcmToken']);

    Route::get('/notifications', [NotificationController::class, 'getUserNotifications']);


    // challenge report

    Route::get('/challenges/{challengeId}/progress', [ChallengeReportController::class, 'getDailyChallengeProgress']);

    Route::get('/challenge/{challengeId}/weekly-progress', [ChallengeReportController::class, 'getWeeklyChallengeProgress']);

    // for user

    Route::get('/user-challenge/{challengeId}/daily-progress', [ChallengeReportController::class, 'getUserDailyChallengeProgress']);

    Route::get('/user-challenge/{challengeId}/weekly-progress', [ChallengeReportController::class, 'getUserWeeklyChallengeProgress']);

    // project completion
    Route::get('/challenge/{challengeId}/completion-progress', [ChallengeReportController::class, 'getChallengeCompletionProgress']);

    Route::get('/challenge/{challengeId}/owner-completion-progress', [ChallengeReportController::class, 'getChallengeOwnerCompletionProgress']);

    // Expired Challenges

    Route::get('/expired-challenges', [ChallengeController::class, 'getExpiredChallenges']);

    Route::get('/challenge/{challengeId}/expired', [ChallengeController::class, 'checkChallengeStatus']);



});

Route::post('/stripe/webhook', [EventController::class, 'handleWebhook']);
