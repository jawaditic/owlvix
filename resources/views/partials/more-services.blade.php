{{-- Links to the extra service pages, in two groups. Used on the homepage and /services.
     $hire: show the "Hire a developer" group (false on the homepage). --}}
@php
    $groups = [
        'More services' => [
            ['shopify-development', 'Shopify stores', 'store', ''], ['wordpress-development', 'WordPress websites', 'monitor', 'is-blue'],
            ['erp-crm-development', 'ERP & CRM systems', 'database', ''], ['landing-page-design', 'Landing pages', 'rocket', 'is-blue'],
            ['ui-ux-design', 'UI/UX design', 'pen', ''], ['mvp-development', 'MVP development', 'sparkle', 'is-blue'],
            ['seo-services', 'SEO services', 'search', ''],
        ],
        'Hire a developer' => [
            ['hire-laravel-developer', 'Laravel developer', 'code', 'is-blue'], ['hire-react-developer', 'React / Next.js developer', 'monitor', ''],
            ['hire-react-native-developer', 'React Native developer', 'phone', 'is-blue'], ['hire-ai-developer', 'AI developer', 'chat', ''],
        ],
    ];
@endphp
<div class="more-services">
    @foreach ($groups as $title => $items)
        @continue($title === 'Hire a developer' && ! ($hire ?? true))
        <p class="more-services__label">{{ $title }}</p>
        <div class="more-services__grid">
            @foreach ($items as [$slug, $label, $icon, $tone])
                @if (config('service_pages.'.$slug))
                    <a href="{{ route('service.'.$slug) }}" class="more-service">
                        <span class="svc-icon {{ $tone }}" aria-hidden="true">{!! \App\Support\Icons::svg($icon) !!}</span>
                        <span>{{ $label }}</span>
                        <svg class="contact-option__arrow" viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
                    </a>
                @endif
            @endforeach
        </div>
    @endforeach
</div>
