{{-- The owl's banner for the page builders' quick setup (public/js/builder-wizard.js via landing-builder.js). $mode: '' (landing), funnel or a kit. --}}
@php
    $what = ['' => 'page', 'funnel' => 'funnel', 'website' => 'website', 'portfolio' => 'portfolio', 'links' => 'link-in-bio page', 'soon' => 'coming soon page', 'event' => 'event website', 'menu' => 'menu', 'store' => 'online store'][$mode ?? ''] ?? 'page';
@endphp
<div class="container lpb-wzb">
    @include('partials.wizard-banner', [
        'say' => "Hoo! Want a {$what}? I’ll set it up with you — it takes about 2 minutes.",
        'title' => 'Let’s build <span class="accent">your '.e($what).'</span> together',
        'sub' => 'Answer a few easy questions and watch your '.$what.' come to life on a phone as you go. No code, no cost.',
        'steps' => [['Pick what', 'it’s for'], ['Name, words', '& colours'], ['Preview, then', 'save it']],
        'go' => '🚀 Start building my '.$what,
    ])
</div>
