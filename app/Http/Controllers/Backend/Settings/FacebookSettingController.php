<?php

namespace App\Http\Controllers\Backend\Settings;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\File;
use Illuminate\View\View;
use Throwable;

class FacebookSettingController extends Controller
{
    /**
     * Display the Google settings page.
     *
     * @return View
     */
    public function index() {
        return view('backend.layouts.settings.facebook_setting');
    }

    /**
     * Update the Google settings.
     *
     * @param Request $request
     * @return RedirectResponse
     */
    public function update(Request $request) {
        if (User::find(auth()->user()->id)) {
            $request->validate([
                'FACEBOOK_CLIENT_ID' => 'nullable|string',
                'FACEBOOK_CLIENT_SECRET' => 'nullable|string',
                'FACEBOOK_REDIRECT_URI' => 'nullable|string',
            ]);
            try {
                $envContent = File::get(base_path('.env'));
                $lineBreak  = "\n";
                $envContent = preg_replace([
                    '/FACEBOOK_CLIENT_ID=(.*)\s/',
                    '/FACEBOOK_CLIENT_SECRET=(.*)\s/',
                    '/FACEBOOK_REDIRECT_URI=(.*)\s/',
                ], [
                    'FACEBOOK_CLIENT_ID=' . $request->FACEBOOK_CLIENT_ID . $lineBreak,
                    'FACEBOOK_CLIENT_SECRET=' . $request->FACEBOOK_CLIENT_SECRET . $lineBreak,
                    'FACEBOOK_REDIRECT_URI=' . $request->FACEBOOK_REDIRECT_URI . $lineBreak,
                ], $envContent);

                if ($envContent !== null) {
                    File::put(base_path('.env'), $envContent);
                }
                return redirect()->back()->with('t-success', 'Google Setting Update successfully.');
            } catch (Throwable) {
                return redirect()->back()->with('t-error', 'Google Setting Update Failed');
            }
        }
        return redirect()->back();
    }
}
