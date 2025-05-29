<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\File;
use Carbon\Carbon;
use App\Models\User;
use App\Mail\OtpMail;
use App\Traits\ApiResponse;
use App\Models\Notification;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;

class UserController extends Controller
{
    use ApiResponse;

    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return $this->error([], $validator->errors()->first(), 422);
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'is_verified' => false, // Indicates user is not verified
        ]);

        // Generate a 6-digit OTP
        $otp = rand(100000, 999999);

        // Save OTP and expiration time
        $user->otp = $otp;
        $user->otp_expires_at = Carbon::now()->addMinutes(10); // OTP valid for 10 minutes
        $user->save();

        // Send OTP to user's email
        Mail::to($user->email)->send(new OtpMail($otp));

        // Generate the token
        $token = JWTAuth::fromUser($user);

        //  $userArray['token'] = $token;

        // Prepare response data
        $responseData = [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'otp' => $user->otp,
            'otp_expires_at' => $user->otp_expires_at,
            'is_verified' => $user->is_verified, // Include is_verified status
            'token' => $token
        ];

        return $this->success($responseData, 'User registered successfully. Please check your email for the OTP.', 201);
    }

    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return $this->error([], $validator->errors()->first(), 422);
        }

        // // Authenticate the user
        if (!auth()->attempt($request->only('email', 'password'))) {
            return $this->error(null, 'Invalid credentials.', 401);
        }

        $user = auth()->user();

        // Check if the user is verified
        if (!$user->is_verified) {
            return $this->error(null, 'Account not verified.', 401);
        }

        // Generate the token
        $token = JWTAuth::fromUser($user);

        // Convert user to array and add the token
        $userArray = $user->toArray();
        $userArray['token'] = $token;

        return $this->success($userArray, 'Login successful', 200);
    }

    public function verifyOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|exists:users,email',
            'otp' => 'required|digits:6',
        ]);

        if ($validator->fails()) {
            return $this->error([], $validator->errors()->first(), 422);
        }

        $user = User::where('email', $request->email)->first();

        // Check if OTP matches and hasn't expired
        if ($user->otp === $request->otp && Carbon::now()->lt($user->otp_expires_at)) {
            // Mark user as verified
            $user->is_verified = true;
            $user->otp = null; // Clear the OTP
            $user->otp_expires_at = null; // Clear expiration time
            $user->save();

            return $this->success($user, 'OTP verified successfully.');
        }

        return $this->error(null, 'Invalid or expired OTP.', 422);
    }

    public function resendOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|exists:users,email',
        ]);

        if ($validator->fails()) {
            return $this->error([], $validator->errors()->first(), 422);
        }

        $user = User::where('email', $request->email)->first();

        // Generate a new 6-digit OTP
        $otp = rand(100000, 999999);

        // Update OTP and expiration time
        $user->otp = $otp;
        $user->otp_expires_at = Carbon::now()->addMinutes(10);
        $user->save();

        // Resend OTP to user's email
        Mail::to($user->email)->send(new OtpMail($otp));

        return $this->success(null, 'OTP has been resent successfully.');
    }

    public function requestPasswordReset(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|exists:users,email',
        ]);

        if ($validator->fails()) {
            return $this->error([], $validator->errors()->first(), 422);
        }

        $user = User::where('email', $request->email)->first();

        // Generate a 6-digit OTP
        $otp = rand(100000, 999999);

        // Save OTP and expiration time
        $user->otp = $otp;
        $user->otp_expires_at = Carbon::now()->addMinutes(10);
        $user->save();

        // Send OTP to user's email
        Mail::to($user->email)->send(new OtpMail($otp));

        return $this->success(null, 'Password reset OTP sent to your email.');
    }

    public function verifyPasswordResetOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|exists:users,email',
            'otp' => 'required|digits:6',
        ]);

        if ($validator->fails()) {
            return $this->error([], $validator->errors()->first(), 422);
        }

        $user = User::where('email', $request->email)->first();

        if ($user->otp === $request->otp && Carbon::now()->lt($user->otp_expires_at)) {
            // OTP is valid, allow the user to proceed
            return $this->success(null, 'OTP verified successfully.');
        }

        return $this->error(null, 'Invalid or expired OTP.', 422);
    }

    public function setNewPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|exists:users,email',
            'password' => 'required|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return $this->error([], $validator->errors()->first(), 422);
        }

        $user = User::where('email', $request->email)->first();

        // Update the password and clear the OTP
        $user->password = Hash::make($request->password);
        $user->otp = null;
        $user->otp_expires_at = null;
        $user->save();

        return $this->success(null, 'Password has been reset successfully.');
    }

    public function resendResetOtp(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
        ]);

        $user = User::where('email', $request->email)->first();

        // Generate a new 6-digit OTP for password reset
        $otp = rand(100000, 999999);

        // Update OTP and expiration time
        $user->otp = $otp;
        $user->otp_expires_at = Carbon::now()->addMinutes(10);
        $user->save();

        // Resend OTP to user's email for password reset
        Mail::to($user->email)->send(new OtpMail($otp));

        return $this->success(null, 'Password reset OTP has been resent successfully.');
    }

    /**
     * Delete the authenticated user's account
     *
     * @return \Illuminate\Http\JsonResponse JSON response with success or error.
     */
    public function deleteUser()
    {
        try {
            // Get the authenticated user
            $user = auth()->user();

            // Delete the user's avatar if it exists
            if ($user->avatar) {
                $previousImagePath = public_path($user->avatar);
                if (file_exists($previousImagePath)) {
                    unlink($previousImagePath);
                }
            }

            // Delete the user
            $user->delete();

            return $this->success([], 'User deleted successfully', '200');
        } catch (\Exception $e) {
            return $this->error([], $e->getMessage(), 500);
        }
    }

    public function updateProfile(Request $request)
    {
        // Validate input data
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email,' . auth()->user()->id, // Ensure the email is unique, except for the current user's email
        ]);

        // If validation fails
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 400);
        }

        // Get the authenticated user
        $user = auth()->user();

        // Update the user details
        $user->name = $request->name;
        $user->email = $request->email;
        $user->save();

        // Return success response
        return response()->json(['message' => 'Profile updated successfully!'], 200);
    }

    public function updatePassword(Request $request)
    {
        // Validate the request
        $validator = Validator::make($request->all(), [
            'previous_password' => 'required|string|min:8',
            'new_password' => 'required|string|min:8|confirmed',
        ]);

        // If validation fails, return errors
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 400);
        }

        // Get the authenticated user
        $user = auth()->user();

        // Check if the previous password is correct
        if (!Hash::check($request->previous_password, $user->password)) {
            return response()->json(['error' => 'Previous password is incorrect.'], 400);
        }

        // Update the password
        $user->password = Hash::make($request->new_password);
        $user->save();

        // Return success response
        return response()->json(['message' => 'Password updated successfully!'], 200);
    }

    public function updateFcmToken(Request $request)
    {
        $user = auth('api')->user();
        $request->validate(['fcm_token' => 'required|string']);

        $user->update(['fcm_token' => $request->fcm_token]);

        return response()->json(['message' => 'FCM token updated successfully']);
    }

    public function Profileupdate(Request $request)
    {
        $user = Auth::user();

        $request->validate([
            'email' => 'sometimes|string|email|max:255|unique:users,email,' . $user->id,
            'about_us' => 'sometimes|string|max:1000',
            'profile_photo' => 'sometimes|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        if ($request->has('email')) {
            $user->email = $request->email;
        }

        if ($request->has('about_us')) {
            $user->about_us = $request->about_us;
        }

        if ($request->hasFile('avatar')) {
            // Delete old profile photo if it exists
            if ($user->avatar) {
                $oldImagePath = public_path('uploads/' . $user->avatar);
                if (file_exists($oldImagePath)) {
                    unlink($oldImagePath); // Delete the file
                }
            }

            // Store the new profile photo
            $image = $request->file('avatar');
            $imageName = time() . '.' . $image->getClientOriginalExtension();
            $image->move(public_path('uploads'), $imageName);
            $user->avatar = $imageName;
        }

        $user->save();

        return response()->json(['message' => 'Profile updated successfully', 'user' => $user]);
    }

}
