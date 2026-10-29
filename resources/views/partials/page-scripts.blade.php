{{-- Popups and scripts at the end of <body>, shared by the landing and service pages --}}
@include('partials.quote-modal')
@include('partials.exit-survey')

<script>
    window.OWLVIX = @json(\App\Support\OwlVoice::sets(['quote']));
    window.OWLVIX.recaptchaKey = @json(config('services.recaptcha.site_key'));
</script>
<script src="{{ \App\Support\Asset::url('js/owl.js') }}" defer></script>
<script src="https://cdn.jsdelivr.net/npm/intl-tel-input@29.5.7/dist/js/intlTelInput.min.js" defer></script>
<script src="https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.js" defer></script>
<script src="{{ \App\Support\Asset::url('js/site.js') }}" defer></script>
<script src="{{ \App\Support\Asset::url('js/theme.js') }}" defer></script>
