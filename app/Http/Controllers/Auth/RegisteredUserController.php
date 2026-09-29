<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class RegisteredUserController extends Controller
{
    public function create()
    {
        return view('auth.register');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'     => ['required', 'string', 'max:160'],
            'email'    => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            // Laravel's Password rule bundles length + a compromised-password
            // check against HaveIBeenPwned, which catches the credential-stuffing
            // case that a length rule alone does not.
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()->uncompromised()],
        ]);

        $user = User::create([
            'name'     => $validated['name'],
            'email'    => $validated['email'],
            'password' => Hash::make($validated['password']),
        ]);

        // Every new account starts as a Free Customer. Role assignment
        // happens here rather than in a model observer so it is visible
        // at the point of registration.
        $user->assignRole('Free Customer');

        event(new Registered($user));

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }
}
