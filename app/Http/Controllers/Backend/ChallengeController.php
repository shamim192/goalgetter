<?php

namespace App\Http\Controllers\Backend;

use App\Models\Challenge;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class ChallengeController extends Controller
{
    public function show($id)
{
    $challenge = Challenge::findOrFail($id); // Get challenge details based on ID

    // You can customize this to send a specific link for iOS/Android based on user's device.
    $downloadLink = 'https://your-app-website.com/download'; // Default download page or App Store/Play Store link

    return view('backend.layouts.challenge.download', compact('challenge', 'downloadLink'));
}
}
