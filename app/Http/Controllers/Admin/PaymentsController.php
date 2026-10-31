<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BuilderApp;
use App\Support\Stripe;
use Illuminate\Http\Request;

/** Owlvix's Stripe platform keys for card payments in apps: test and live, each switchable, saved encrypted. */
class PaymentsController extends Controller
{
    public function index()
    {
        $s = Stripe::settings();

        return view('admin.payments', [
            's' => $s,
            'hints' => collect(Stripe::MODES)->mapWithKeys(fn ($m) => [$m => ['secret' => Stripe::hint((string) ($s[$m]['secret'] ?? '')), 'client_id' => Stripe::hint((string) ($s[$m]['client_id'] ?? ''))]]),
            'connected' => ['test' => BuilderApp::whereNotNull('stripe_test_account')->count(), 'live' => BuilderApp::whereNotNull('stripe_live_account')->count()],
            'envOff' => ! config('builder.payments.enabled'),
            'callback' => route('builder.stripe.callback'),
        ]);
    }

    public function save(Request $request)
    {
        $data = $request->validate([
            'enabled' => 'nullable|boolean', 'fee' => 'nullable|numeric|min:0|max:20',
            'test.on' => 'nullable|boolean', 'test.secret' => ['nullable', 'string', 'max:200', 'regex:/^(sk|rk)_test_[A-Za-z0-9]+$/'], 'test.client_id' => ['nullable', 'string', 'max:100', 'regex:/^ca_[A-Za-z0-9]+$/'], 'test.forget' => 'nullable|boolean',
            'live.on' => 'nullable|boolean', 'live.secret' => ['nullable', 'string', 'max:200', 'regex:/^(sk|rk)_live_[A-Za-z0-9]+$/'], 'live.client_id' => ['nullable', 'string', 'max:100', 'regex:/^ca_[A-Za-z0-9]+$/'], 'live.forget' => 'nullable|boolean',
        ], [
            'test.secret.regex' => 'The test secret key starts with sk_test_ (or rk_test_ for a restricted key).',
            'live.secret.regex' => 'The live secret key starts with sk_live_ (or rk_live_ for a restricted key).',
            'test.client_id.regex' => 'The Connect client ID starts with ca_.', 'live.client_id.regex' => 'The Connect client ID starts with ca_.',
        ]);
        Stripe::save($data);
        // phones get the "Pay now" button (or lose it) right away
        BuilderApp::where('status', 'live')->where(fn ($q) => $q->whereNotNull('stripe_test_account')->orWhereNotNull('stripe_live_account'))
            ->each(fn ($app) => \App\Support\AppContent::republish($app));

        return back()->with('status', 'Payment settings saved (keys are stored encrypted).');
    }
}
