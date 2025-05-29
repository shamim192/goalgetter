<?php

namespace App\Http\Controllers\Api;

use App\Models\Notification;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class NotificationController extends Controller
{
    public function getUserNotifications()
    {
        $user = auth('api')->user();

        $notifications = Notification::where('user_id', $user->id)->orderBy('created_at', 'desc')->get();

        return response()->json([
            'message' => 'Notifications retrieved successfully!',
            'data' => $notifications,
        ]);
    }

}
