{{-- Structured data for search engines (schema.org JSON-LD): who Owlvix is, what it offers, and the FAQ.
     Owlvix is an Organization, not a LocalBusiness/ProfessionalService: it works online with clients worldwide,
     and Google requires a street address for any LocalBusiness (Semrush flagged it as invalid without one).
     Review stars are deliberately not marked up here: Google ignores ratings a business publishes about itself. --}}
@php
    $faq = [
        ['Do I have to pay upfront?', 'No. Every project starts free: we plan it and design your key screens at no cost. The rest is split into as many milestones as we agree, and you pay for each one only after you have seen and approved it.'],
        ['How long does a project take?', 'Most websites take 1–3 weeks and most web apps 3–8 weeks. You get an exact timeline with your quote.'],
        ['Do I own the code?', 'Yes. When the project is paid for, all source code, designs and accounts are handed over to you.'],
        ['Can you work on my existing app?', 'Yes. We regularly take over, fix and extend apps that other developers started.'],
        ['Which AI models do you use?', 'We work with OpenAI, Anthropic Claude and Google Gemini, and pick the one that suits your budget and task.'],
        ['Do you help with hosting?', 'Yes. We can set up hosting, domains, email and SSL, or deploy to the server you already have.'],
    ];
    $services = ['Custom web app development', 'Mobile app development (iOS and Android)', 'AI chatbot development', 'AI agents and automation', 'API integrations', 'Website development', 'Shopify store development', 'WordPress website development', 'ERP and CRM development', 'Landing page design', 'UI/UX design', 'MVP development', 'SEO services', 'Hire a Laravel developer', 'Hire a React / Next.js developer', 'Hire a React Native developer', 'Hire an AI developer', 'Hosting and support'];
    $graph = [
        '@context' => 'https://schema.org',
        '@graph' => [
            [
                ...\App\Support\Owlvix::organization(),
                'hasOfferCatalog' => [
                    '@type' => 'OfferCatalog',
                    'name' => 'Services',
                    'itemListElement' => array_map(fn ($s) => ['@type' => 'Offer', 'itemOffered' => ['@type' => 'Service', 'name' => $s]], $services),
                ],
            ],
            [
                '@type' => 'WebSite',
                '@id' => url('/').'#website',
                'url' => url('/'),
                'name' => 'Owlvix',
                'publisher' => ['@id' => url('/').'#business'],
                'inLanguage' => 'en',
            ],
            [
                '@type' => 'FAQPage',
                'mainEntity' => array_map(fn ($q) => ['@type' => 'Question', 'name' => $q[0], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $q[1]]], $faq),
            ],
        ],
    ];
@endphp
<script type="application/ld+json">{!! json_encode($graph, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) !!}</script>
