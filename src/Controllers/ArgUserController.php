<?php

namespace Arg\Laravel\Controllers;

use Illuminate\Foundation\Application;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Redirector;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;

class ArgUserController extends ArgBaseController
{
    public function login(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        $userClass = 'App\Models\User';
        $user = $userClass::where('email', $data['email'])->first();
        if (!$user) {
            abort(404);
        }

        if(Hash::check($data['password'], $user->password)) {
            auth()->login($user);
            $request->session()->regenerate();
            return $user;
        }

        abort(404);
    }

    public function logout(Request $request): Application|Redirector|RedirectResponse
    {
        auth()->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect('/');
    }
}
