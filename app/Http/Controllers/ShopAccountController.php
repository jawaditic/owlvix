<?php

namespace App\Http\Controllers;

use App\Models\BuilderStore;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;

/**
 * "My orders" for customers of a shop made with the online store builder (/shop/{id}/account): they sign in with a
 * code emailed to them and see every order they placed there, with links to each order page. The code is only sent
 * to addresses that have ordered from this shop — the page says the same either way, so it can't be used to email
 * strangers or to find out who shops where.
 */
class ShopAccountController extends Controller
{
    private const CODE_MINUTES = 15;

    public function show(Request $request, string $id)
    {
        $store = $this->store($id);
        $email = $request->session()->get($this->key($store));
        $orders = $email ? $store->orders()->where('email', $email)->latest('id')->take(50)->get() : collect();

        return $this->page($store, ['email' => $email, 'orders' => $orders, 'step' => $email ? 'orders' : ($request->session()->get($this->pendingKey($store)) ? 'code' : 'email')]);
    }

    public function code(Request $request, string $id)
    {
        $this->sameSite($request);
        $store = $this->store($id);
        $email = strtolower(trim((string) $request->input('email')));
        if (! filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 150) {
            return $this->page($store, ['step' => 'email', 'error' => 'Please enter the email you ordered with.'], 422);
        }
        foreach (['shop-acct-ip:'.$request->ip() => 10, 'shop-acct:'.$store->id.':'.sha1($email) => 3] as $k => $max) {
            if (RateLimiter::tooManyAttempts($k, $max)) {
                return $this->page($store, ['step' => 'email', 'error' => 'Too many codes asked for. Please wait a few minutes.'], 429);
            }
            RateLimiter::hit($k, 600);
        }
        if ($store->orders()->where('email', $email)->exists()) {
            $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            Cache::put('shop-acct-code:'.$store->id.':'.sha1($email), ['hash' => hash('sha256', $code), 'tries' => 0], now()->addMinutes(self::CODE_MINUTES));
            \Illuminate\Support\Facades\RateLimiter::clear('shop-acct-guess:'.$store->id.':'.sha1($email)); // a new code: 5 new guesses
            try {
                Mail::to($email)->send(new \App\Mail\BuilderShopSignIn($store, $code));
            } catch (\Throwable $e) {
                report($e);
            }
        }
        $request->session()->put($this->pendingKey($store), $email);

        return redirect()->route('shop.account', $store->id, 303);
    }

    public function verify(Request $request, string $id)
    {
        $this->sameSite($request);
        $store = $this->store($id);
        $email = $request->session()->get($this->pendingKey($store));
        if (! $email) {
            return redirect()->route('shop.account', $store->id, 303);
        }
        $cacheKey = 'shop-acct-code:'.$store->id.':'.sha1($email);
        $saved = Cache::get($cacheKey);
        $code = preg_replace('/\D/', '', (string) $request->input('code'));
        // 5 guesses per code, counted with an atomic counter (guesses sent all at once can't slip past it)
        $guesses = 'shop-acct-guess:'.$store->id.':'.sha1($email);
        $tooMany = \Illuminate\Support\Facades\RateLimiter::tooManyAttempts($guesses, 5);
        \Illuminate\Support\Facades\RateLimiter::hit($guesses, self::CODE_MINUTES * 60);
        if (! $saved || $tooMany || $saved['tries'] >= 5 || ! hash_equals($saved['hash'], hash('sha256', $code))) {
            if ($saved) {
                Cache::put($cacheKey, ['hash' => $saved['hash'], 'tries' => $saved['tries'] + 1], now()->addMinutes(self::CODE_MINUTES));
            }

            return $this->page($store, ['step' => 'code', 'pending' => $email, 'error' => $saved && $saved['tries'] < 4 ? 'That code isn’t right. Please check the email.' : 'That code has expired. Please ask for a new one.'], 422);
        }
        Cache::forget($cacheKey);
        \Illuminate\Support\Facades\RateLimiter::clear($guesses);
        $request->session()->forget($this->pendingKey($store));
        $request->session()->regenerate();
        $request->session()->put($this->key($store), $email);

        return redirect()->route('shop.account', $store->id, 303);
    }

    public function logout(Request $request, string $id)
    {
        $this->sameSite($request);
        $store = $this->store($id);
        $request->session()->forget([$this->key($store), $this->pendingKey($store)]);

        return redirect()->route('shop.account', $store->id, 303);
    }

    private function store(string $id): BuilderStore
    {
        return BuilderStore::whereKey($id)->whereNull('disabled_at')->firstOrFail();
    }

    private function key(BuilderStore $store): string
    {
        return 'shop_customer_'.$store->id;
    }

    /** The email waiting for its code (a separate key: Laravel reads dots in session keys as nesting). */
    private function pendingKey(BuilderStore $store): string
    {
        return 'shop_pending_'.$store->id;
    }

    private function page(BuilderStore $store, array $data, int $status = 200)
    {
        return response()->view('builder.shop-account', $data + ['store' => $store, 'email' => null, 'orders' => collect(), 'error' => null,
            'pending' => session()->get($this->pendingKey($store))], $status)
            ->header('X-Robots-Tag', 'noindex, nofollow')->header('Cache-Control', 'no-store');
    }

    /**
     * The shop/* routes skip Laravel's CSRF check (checkout is posted from published pages on another site), but these
     * sign-in forms are on owlvix.com and use the session, so they check the form's token themselves.
     */
    private function sameSite(Request $request): void
    {
        $token = $request->input('_token');
        abort_unless(is_string($token) && hash_equals((string) $request->session()->token(), $token), 419);
    }
}
