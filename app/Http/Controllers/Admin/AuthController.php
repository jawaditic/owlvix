<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function show(Request $request)
    {
        if ($request->session()->get('is_admin')) {
            return redirect()->route('admin.messages.index');
        }

        return view('admin.login');
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $email = config('admin.email');
        $hash = config('admin.password_hash');

        $ok = $email && $hash
            && hash_equals(strtolower($email), strtolower($data['email']))
            && Hash::check($data['password'], $hash);

        if (! $ok) {
            return back()->withInput($request->only('email'))
                ->withErrors(['email' => 'Wrong email or password.']);
        }

        $request->session()->regenerate();
        $request->session()->put('is_admin', true);

        return redirect()->intended(route('admin.messages.index'));
    }

    public function logout(Request $request)
    {
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }
}
