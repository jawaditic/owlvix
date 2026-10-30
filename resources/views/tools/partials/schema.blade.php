{{-- JSON-LD for a free tool page: the WebApplication + its FAQ. Built inside the PHP block because Blade reads
     the word "context" after an at-sign in the template as its own directive. Params: name, url, category, faq. --}}
@php
    $ld = ['@context' => 'https://schema.org', '@graph' => [
        ['@type' => 'WebApplication', 'name' => $name, 'url' => $url, 'applicationCategory' => $category, 'operatingSystem' => 'Any',
            'offers' => ['@type' => 'Offer', 'price' => '0', 'priceCurrency' => 'USD'], 'provider' => ['@id' => url('/').'#business']],
        ['@type' => 'FAQPage', 'mainEntity' => array_map(fn ($q) => ['@type' => 'Question', 'name' => $q[0], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $q[1]]], $faq)],
    ]];
@endphp
<script type="application/ld+json">{!! json_encode($ld, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) !!}</script>
