<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Api\StripeController;
use App\Http\Controllers\Backend\ChallengeController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|v 
*/

Route::get('/', function () {
    return redirect('/login');
});

Route::get('/stripe/onboarding/refresh', [StripeController::class, 'refreshOnboarding'])->name('stripe.onboarding.refresh');

Route::get('/stripe/onboarding/complete', [StripeController::class, 'completeOnboarding'])->name('stripe.onboarding.complete');

Route::get('challenge/{id}/download', [ChallengeController::class, 'show'])->name('challenge.show');


require __DIR__.'/auth.php';

