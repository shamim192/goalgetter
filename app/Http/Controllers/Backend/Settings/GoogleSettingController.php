<?php

namespace App\Http\Controllers\Backend\Settings;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\File;
use Illuminate\View\View;
use Throwable;

class GoogleSettingController extends Controller
{
    /**
     * Display the Google settings page.
     *
     * @return View
     */
    public function index() {
        return view('backend.layouts.settings.google_setting');
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
                'GOOGLE_CLIENT_ID' => 'nullable|string',
                'GOOGLE_CLIENT_SECRET' => 'nullable|string',
                'GOOGLE_REDIRECT_URI' => 'nullable|string',
            ]);
            try {
                $envContent = File::get(base_path('.env'));
                $lineBreak  = "\n";
                $envContent = preg_replace([
                    '/GOOGLE_CLIENT_ID=(.*)\s/',
                    '/GOOGLE_CLIENT_SECRET=(.*)\s/',
                    '/GOOGLE_REDIRECT_URI=(.*)\s/',
                ], [
                    'GOOGLE_CLIENT_ID=' . $request->GOOGLE_CLIENT_ID . $lineBreak,
                    'GOOGLE_CLIENT_SECRET=' . $request->GOOGLE_CLIENT_SECRET . $lineBreak,
                    'GOOGLE_REDIRECT_URI=' . $request->GOOGLE_REDIRECT_URI . $lineBreak,
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
