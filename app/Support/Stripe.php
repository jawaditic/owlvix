<?php

namespace App\Support;

use App\Models\BuilderApp;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

/**
 * Online card payments for apps made with the mobile app builder, through Stripe Connect ("Standard" accounts).
 * Each owner connects their own Stripe account on Stripe's page (OAuth); owlvix only keeps the account id
 * (acct_…), never their keys. Payments are made on the owner's account, so they are the merchant: refunds,
 * disputes and payouts are theirs.
 *
 * Owlvix's own platform keys, one pair for test mode (sandbox, test cards) and one for live mode, each with an
 * on/off switch, are set on the admin Payments page and kept encrypted with the app key (storage/app/private/
 * stripe.enc); .env values (config builder.payments) are only a fallback. APP_BUILDER_PAYMENTS=false (or the
 * admin's main switch) turns online payments off for every app.
 */
class Stripe
{
    public const MODES = ['test', 'live'];

    /** Currencies Stripe counts in whole units (no cents). */
    private const ZERO_DECIMAL = ['bif', 'clp', 'djf', 'gnf', 'jpy', 'kmf', 'krw', 'mga', 'pyg', 'rwf', 'ugx', 'vnd', 'vuv', 'xaf', 'xof', 'xpf'];

    /** Currencies offered in the builder (Stripe supports them for cards). */
    public const CURRENCIES = ['usd', 'eur', 'gbp', 'cad', 'aud', 'nzd', 'chf', 'sek', 'nok', 'dkk', 'pln', 'czk', 'huf', 'ron', 'try', 'aed', 'sar', 'qar', 'inr', 'pkr', 'bdt', 'lkr', 'myr', 'sgd', 'hkd', 'idr', 'php', 'thb', 'jpy', 'krw', 'cny', 'brl', 'mxn', 'zar', 'ngn', 'kes', 'egp'];

    private static ?array $settings = null;

    public static function file(): string
    {
        return (string) (config('builder.payments.file') ?: storage_path('app/private/stripe.enc'));
    }

    /** Forget the settings read in this request (tests, after saving). */
    public static function flush(): void
    {
        self::$settings = null;
    }

    /**
     * The platform settings: {enabled, fee, test: {on, secret, client_id}, live: {…}}, from the encrypted file,
     * else from .env.
     */
    public static function settings(): array
    {
        if (self::$settings !== null) {
            return self::$settings;
        }
        $env = fn (string $mode) => ['on' => true, 'secret' => (string) config("builder.payments.stripe.$mode.secret"), 'client_id' => (string) config("builder.payments.stripe.$mode.client_id")];
        $saved = null;
        if (is_file(self::file())) {
            try {
                $saved = json_decode(Crypt::decryptString((string) file_get_contents(self::file())), true);
            } catch (\Throwable $e) {
                report($e); // a different APP_KEY: the keys must be entered again
            }
        }
        $s = is_array($saved) ? $saved : ['enabled' => true, 'fee' => (float) config('builder.payments.stripe.fee_percent'), 'test' => $env('test'), 'live' => $env('live')];
        $s['enabled'] = ($s['enabled'] ?? true) && (bool) config('builder.payments.enabled');

        return self::$settings = $s;
    }

    /** Saves the platform settings, encrypted. Empty keys keep the saved ones. */
    public static function save(array $in): void
    {
        $old = self::settings();
        $s = ['enabled' => (bool) ($in['enabled'] ?? false), 'fee' => max(0, min(20, (float) ($in['fee'] ?? 0)))];
        foreach (self::MODES as $mode) {
            $s[$mode] = ['on' => (bool) ($in[$mode]['on'] ?? false),
                'secret' => trim((string) ($in[$mode]['secret'] ?? '')) ?: (string) ($old[$mode]['secret'] ?? ''),
                'client_id' => trim((string) ($in[$mode]['client_id'] ?? '')) ?: (string) ($old[$mode]['client_id'] ?? '')];
            if (! empty($in[$mode]['forget'])) {
                $s[$mode]['secret'] = $s[$mode]['client_id'] = '';
            }
        }
        File::ensureDirectoryExists(dirname(self::file()));
        File::put(self::file(), Crypt::encryptString(json_encode($s)));
        @chmod(self::file(), 0600);
        self::$settings = null;
    }

    /** "…a1b2" for showing a saved key without showing it. */
    public static function hint(string $key): string
    {
        return $key === '' ? '' : '…'.substr($key, -4);
    }

    /** Online payments can be offered in this mode: switched on and the platform's keys are set. */
    public static function available(string $mode): bool
    {
        $s = self::settings();

        return in_array($mode, self::MODES, true) && $s['enabled'] && ! empty($s[$mode]['on']) && ! empty($s[$mode]['secret']) && ! empty($s[$mode]['client_id']);
    }

    /** Some mode is available (the builder shows the online payments settings). */
    public static function anyAvailable(): bool
    {
        return self::available('test') || self::available('live');
    }

    private static function clientId(string $mode): string
    {
        return (string) (self::settings()[$mode]['client_id'] ?? '');
    }

    /** Stripe's page where the owner signs in and lets owlvix make payments on their account. */
    public static function connectUrl(string $mode, string $state): string
    {
        return 'https://connect.stripe.com/oauth/authorize?'.http_build_query([
            'response_type' => 'code',
            'client_id' => self::clientId($mode),
            'scope' => 'read_write',
            'state' => $state,
            'redirect_uri' => route('builder.stripe.callback'),
        ]);
    }

    /** The connected account's id (acct_…) for the code Stripe sends back. */
    public static function exchange(string $mode, string $code): string
    {
        $res = Http::asForm()->withToken(self::secret($mode))->timeout(20)
            ->post('https://connect.stripe.com/oauth/token', ['grant_type' => 'authorization_code', 'code' => $code])->throw();
        $id = (string) $res->json('stripe_user_id');
        if (! preg_match('/^acct_[A-Za-z0-9]{6,60}$/', $id)) {
            throw new \RuntimeException('Stripe sent no account id.');
        }

        return $id;
    }

    /** Ends owlvix's access to the account (the owner can also do it in their Stripe dashboard). */
    public static function disconnect(string $mode, string $account): void
    {
        Http::asForm()->withToken(self::secret($mode))->timeout(20)->post('https://connect.stripe.com/oauth/deauthorize', [
            'client_id' => self::clientId($mode), 'stripe_user_id' => $account,
        ]);
    }

    /**
     * A Stripe Checkout page for an order, on the owner's account. Prices are owlvix's (from the app's content),
     * never the phone's.
     *
     * @param  list<array{name: string, price: float, qty: int}>  $lines
     * @return array{id: string, url: string}
     */
    public static function checkout(BuilderApp $app, string $mode, string $currency, array $lines, string $success, string $cancel): array
    {
        $form = ['mode' => 'payment', 'success_url' => $success, 'cancel_url' => $cancel, 'metadata[owlvix_app]' => $app->id, 'payment_intent_data[metadata][owlvix_app]' => $app->id];
        $total = 0;
        foreach (array_values($lines) as $i => $l) {
            $unit = self::minor($l['price'], $currency);
            $total += $unit * $l['qty'];
            $form["line_items[$i][quantity]"] = $l['qty'];
            $form["line_items[$i][price_data][currency]"] = $currency;
            $form["line_items[$i][price_data][unit_amount]"] = $unit;
            $form["line_items[$i][price_data][product_data][name]"] = mb_substr($l['name'], 0, 200);
        }
        $fee = (int) floor($total * max(0, min(20, (float) (self::settings()['fee'] ?? 0))) / 100);
        if ($fee > 0) {
            $form['payment_intent_data[application_fee_amount]'] = $fee;
        }
        $res = self::api($mode, self::account($app, $mode))->post('https://api.stripe.com/v1/checkout/sessions', $form)->throw();

        return ['id' => (string) $res->json('id'), 'url' => (string) $res->json('url')];
    }

    /** @return array{paid: bool, amount: int, currency: string} */
    public static function session(BuilderApp $app, string $mode, string $id): array
    {
        $res = self::api($mode, self::account($app, $mode))->get('https://api.stripe.com/v1/checkout/sessions/'.rawurlencode($id))->throw();

        return ['paid' => $res->json('payment_status') === 'paid' && ($res->json('metadata.owlvix_app') === $app->id),
            'amount' => (int) $res->json('amount_total'), 'currency' => (string) $res->json('currency')];
    }

    /** A price in the currency's smallest unit (cents), as Stripe wants it. */
    public static function minor(float $price, string $currency): int
    {
        return (int) round($price * (in_array($currency, self::ZERO_DECIMAL, true) ? 1 : 100));
    }

    public static function account(BuilderApp $app, string $mode): ?string
    {
        return $mode === 'live' ? $app->stripe_live_account : ($mode === 'test' ? $app->stripe_test_account : null);
    }

    private static function secret(string $mode): string
    {
        return (string) (self::settings()[$mode]['secret'] ?? '') ?: throw new \RuntimeException("Stripe $mode keys are not set.");
    }

    private static function api(string $mode, ?string $account)
    {
        return Http::asForm()->withToken(self::secret($mode))->timeout(20)
            ->withHeaders(['Stripe-Account' => $account ?? throw new \RuntimeException('No Stripe account connected.'), 'Stripe-Version' => '2024-06-20']);
    }
}
