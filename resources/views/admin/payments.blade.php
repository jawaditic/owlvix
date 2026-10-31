@extends('admin.layout')
@section('title', 'Payments')

@section('content')
    <div class="head">
        <div>
            <h1>Payments</h1>
            <p class="muted">Card payments in apps made with the mobile app builder, through Stripe Connect. Each owner connects their own Stripe account and is paid directly; owlvix only keeps their account id. These are owlvix’s own platform keys — stored encrypted, shown only by their last 4 characters.</p>
        </div>
    </div>

    @if ($envOff)
        <p class="alert">APP_BUILDER_PAYMENTS=false in .env: online payments are off for every app whatever is set here.</p>
    @endif
    @if ($errors->any())
        <p class="alert">{{ $errors->first() }}</p>
    @endif

    <form method="POST" action="{{ route('admin.payments.save') }}" class="panel detail t-form">
        @csrf
        <div class="t-form__checks">
            <input type="hidden" name="enabled" value="0">
            <label class="check"><input type="checkbox" name="enabled" value="1" @checked($s['enabled'])> <b>Online payments on</b> — the main switch for every app</label>
        </div>

        @foreach (['test' => 'Test mode (sandbox)', 'live' => 'Live mode (real money)'] as $mode => $title)
            <fieldset class="panel" style="padding:18px;display:grid;gap:12px;border-radius:12px">
                <legend style="padding:0 6px;font-weight:600">{{ $title }}</legend>
                <input type="hidden" name="{{ $mode }}[on]" value="0">
                <label class="check"><input type="checkbox" name="{{ $mode }}[on]" value="1" @checked(! empty($s[$mode]['on']))> Enabled
                    <span class="muted" style="margin:0 0 0 8px">{{ $connected[$mode] }} app{{ $connected[$mode] === 1 ? '' : 's' }} connected</span></label>
                <div class="t-form__grid" style="grid-template-columns:1fr 1fr">
                    <label>Secret key <small class="muted">{{ $hints[$mode]['secret'] ? 'saved '.$hints[$mode]['secret'].' — leave empty to keep' : 'sk_'.$mode.'_…' }}</small>
                        <input type="password" name="{{ $mode }}[secret]" autocomplete="off" placeholder="{{ $hints[$mode]['secret'] ? '••••••••'.$hints[$mode]['secret'] : 'sk_'.$mode.'_…' }}"></label>
                    <label>Connect client ID <small class="muted">{{ $hints[$mode]['client_id'] ? 'saved '.$hints[$mode]['client_id'] : 'ca_… (Stripe → Connect → Settings)' }}</small>
                        <input type="text" name="{{ $mode }}[client_id]" autocomplete="off" placeholder="{{ $hints[$mode]['client_id'] ? '…'.$hints[$mode]['client_id'] : 'ca_…' }}"></label>
                </div>
                @if ($hints[$mode]['secret'] || $hints[$mode]['client_id'])
                    <input type="hidden" name="{{ $mode }}[forget]" value="0">
                    <label class="check"><input type="checkbox" name="{{ $mode }}[forget]" value="1"> Delete the saved {{ $mode }} keys</label>
                @endif
            </fieldset>
        @endforeach

        <div class="t-form__grid" style="grid-template-columns:200px 1fr">
            <label>Owlvix fee (%) <input type="number" name="fee" min="0" max="20" step="0.1" value="{{ $s['fee'] ?? 0 }}"></label>
            <p class="muted" style="align-self:end">Taken from each payment as a Stripe application fee. 0 = none.</p>
        </div>
        <p class="muted">In Stripe: Settings → Connect → turn on OAuth for Standard accounts, and add the redirect URI <code>{{ $callback }}</code> (in both test and live mode).</p>
        <button type="submit" class="btn">Save</button>
    </form>
@endsection
