<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

/** Mobile app login: the same admin email and password as the website, swapped for a token. */
class AuthController extends Controller
{
    public function login(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'device' => ['nullable', 'string', 'max:120'],
        ]);

        $email = config('admin.email');
        $hash = config('admin.password_hash');
        $ok = $email && $hash
            && hash_equals(strtolower($email), strtolower($data['email']))
            && Hash::check($data['password'], $hash);

        if (! $ok) {
            return response()->json(['message' => 'Wrong email or password.', 'errors' => ['email' => ['Wrong email or password.']]], 422);
        }

        return response()->json(['token' => AdminToken::issue($data['device'] ?? null), 'email' => $email]);
    }

    public function logout(Request $request)
    {
        $request->attributes->get('admin_token')->delete();

        return response()->json(['ok' => true]);
    }

    /** The app sends its Expo push token so this phone gets new-enquiry alerts (null turns them off). */
    public function pushToken(Request $request)
    {
        $data = $request->validate(['push_token' => ['nullable', 'string', 'max:255', 'regex:/^(Expo|Exponent)PushToken\[.+\]$/']]);
        $request->attributes->get('admin_token')->forceFill(['push_token' => $data['push_token'] ?? null])->save();

        return response()->json(['ok' => true]);
    }
}
