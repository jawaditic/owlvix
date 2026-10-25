{{-- Google Analytics 4. Off when GA_MEASUREMENT_ID is empty, and while you're logged in to the admin
     (so your own visits don't count). Site scripts send events through window.track(name, params). --}}
@php($gaId = config('services.google_analytics.id'))
@if ($gaId && ! session('is_admin'))
    <script async src="https://www.googletagmanager.com/gtag/js?id={{ urlencode($gaId) }}"></script>
    <script>
        window.dataLayer = window.dataLayer || [];
        function gtag() { dataLayer.push(arguments); }
        gtag('js', new Date());
        gtag('config', @json($gaId));
        window.track = function (name, params) { gtag('event', name, params || {}); };
        @if (session('sent'))
            // The server has just saved an enquiry: this is a real lead
            track('generate_lead', { form: @json(session('lead_source') === 'popup' ? 'quote_popup' : 'contact_form'), service: @json(session('lead_service')) });
        @endif
    </script>
@else
    <script>window.track = function () {};</script>
@endif
