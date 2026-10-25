{{-- Google Analytics 4 + Microsoft Clarity. Each is off when its ID is empty, and both are off while you're
     logged in to the admin (so your own visits don't count). Site scripts send events through window.track(). --}}
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

@php($clarityId = config('services.clarity.id'))
@if ($clarityId && ! session('is_admin'))
    {{-- Clarity: recordings and heatmaps. Form fields are masked (data-clarity-mask on the forms). --}}
    <script>
        (function (c, l, a, r, i, t, y) {
            c[a] = c[a] || function () { (c[a].q = c[a].q || []).push(arguments); };
            t = l.createElement(r); t.async = 1; t.src = 'https://www.clarity.ms/tag/' + i;
            y = l.getElementsByTagName(r)[0]; y.parentNode.insertBefore(t, y);
        })(window, document, 'clarity', 'script', @json($clarityId));
        // Send the same events to Clarity, so recordings can be filtered by them (e.g. "generate_lead")
        (function () {
            var ga = window.track;
            window.track = function (name, params) { ga(name, params); try { clarity('event', name); } catch (e) {} };
            @if (session('sent'))
                clarity('event', 'generate_lead');
                clarity('set', 'lead', 'yes');
            @endif
        })();
    </script>
@endif
