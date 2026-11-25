<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class StaffController extends Controller
{
    public function create()
    {
        $staff = User::whereIn('role', [User::ROLE_ADMIN, User::ROLE_EMPLOYEE])
            ->orderBy('role')
            ->orderBy('name')
            ->get();

        return view('staff.create', compact('staff'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|lowercase|email|max:255|unique:users',
            'password' => ['required', 'confirmed', Password::defaults()],
            'role' => 'required|in:admin,employee',
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => $request->role,
        ]);

        $user->forceFill(['email_verified_at' => now()])->save();

        return redirect()->route('staff.create')->with('success', "{$user->name} can now sign in as {$user->role}.");
    }

    public function destroy(User $user)
    {
        abort_unless($user->isStaff(), 404);

        if ($user->is(Auth::user())) {
            return back()->with('error', 'You cannot delete your own account here.');
        }

        if ($user->isAdmin() && User::where('role', User::ROLE_ADMIN)->count() <= 1) {
            return back()->with('error', 'The last admin account cannot be deleted.');
        }

        $user->delete();

        return back()->with('success', "{$user->name}'s staff account was deleted.");
    }
}
