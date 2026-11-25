<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public function edit()
    {
        return view('admin.settings', ['settings' => Setting::values()]);
    }

    public function update(Request $request)
    {
        $data = $request->validate(Setting::rules());

        Setting::store($data);

        return back()->with('success', 'Settings saved.');
    }
}
