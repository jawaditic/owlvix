{{-- Popups and scripts at the end of <body>, shared by the landing and service pages --}}
@include('partials.quote-modal')
@include('partials.exit-survey')
@include('partials.pricing-modal')

<script>
    window.OWLVIX = @json(\App\Support\OwlVoice::sets(['intro', 'quote']));
    window.OWLVIX.recaptchaKey = @json(config('services.recaptcha.site_key'));
</script>
<script src="{{ \App\Support\Asset::url('js/sweet.js') }}" defer></script>
<script src="{{ \App\Support\Asset::url('js/owl.js') }}" defer></script>
<script src="{{ \App\Support\Asset::url('js/site.js') }}" defer></script>
<script src="{{ \App\Support\Asset::url('js/theme.js') }}" defer></script>
