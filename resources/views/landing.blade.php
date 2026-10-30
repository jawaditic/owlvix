{{-- Landing page for ads and search (/services): every service on one page, with the quote form at the top.
     Leads from this page are saved with source "landing" (Admin → Messages → Landing page). --}}
@php
    $t = \App\Support\Testimonials::data();

    // The services. "service" is the value the form's "What do you need?" box is set to (Enquiries::SERVICES).
    $services = [
        [
            'id' => 'web-apps', 'page' => ['web-app-development', 'erp-crm-development'], 'name' => 'Custom Web App Development', 'service' => 'Custom web app', 'bar' => '',
            'icon' => '<rect class="d" x="3" y="4" width="18" height="13" rx="2"/><path d="M8 21h8M12 17v4"/>',
            'intro' => 'Dashboards, client portals, SaaS products, booking systems and internal tools — built around how your business actually works, not squeezed into an off-the-shelf template.',
            'for' => 'Startups launching a product, and businesses replacing spreadsheets or old software.',
            'gets' => ['Design, frontend, backend and database', 'User accounts, roles, payments and admin panel', 'Fast, secure and ready to grow', 'Deployed to your server with full source code'],
            'tech' => ['PHP', 'Laravel', 'JavaScript', 'TypeScript', 'React', 'Next.js', 'Vue', 'Nuxt', 'Angular', 'Node.js', 'Express', 'NestJS', 'Python', 'Django', 'FastAPI', 'MySQL', 'PostgreSQL', 'MongoDB', 'Redis', 'Tailwind CSS'],
        ],
        [
            'id' => 'mobile-apps', 'page' => 'mobile-app-development', 'name' => 'Mobile App Development', 'service' => 'Mobile app (iOS & Android)', 'bar' => 'bar--blue',
            'icon' => '<rect class="d" x="7" y="2.5" width="10" height="19" rx="2.5"/><path d="M11 18.5h2"/>',
            'intro' => 'iOS and Android apps from one shared codebase, so you launch on both stores for close to the price of one — connected to your website, database or existing systems.',
            'for' => 'Businesses that want an app for customers, staff or bookings, and founders with an app idea.',
            'gets' => ['iPhone and Android from one codebase', 'Push notifications, logins and payments', 'Publishing to the App Store and Google Play', 'Updates and fixes after launch'],
            'tech' => ['React Native', 'Expo', 'Flutter', 'Dart', 'TypeScript', 'Swift', 'SwiftUI', 'Kotlin', 'Jetpack Compose', 'Firebase', 'Supabase', 'REST APIs', 'GraphQL'],
        ],
        [
            'id' => 'ai-chatbots', 'page' => 'ai-chatbot-development', 'name' => 'AI Chatbot Development', 'service' => 'AI chatbot', 'bar' => '',
            'icon' => '<path class="d" d="M4 5h16v11H9l-5 4z"/><path d="M8 10h8M8 13h5"/>',
            'intro' => 'Chatbots for your website, WhatsApp or support desk that answer from your own documents, FAQs and products — and hand over to a person when they should.',
            'for' => 'Teams answering the same customer questions every day, and shops that want to sell around the clock.',
            'gets' => ['Trained on your own content', 'Website widget, WhatsApp or Slack', 'Captures leads and books meetings', 'Chat history and reports'],
            'tech' => ['OpenAI GPT', 'Claude', 'Gemini', 'Llama', 'Mistral', 'Python', 'LangChain', 'LlamaIndex', 'RAG', 'Pinecone', 'pgvector', 'Hugging Face', 'WhatsApp API'],
        ],
        [
            'id' => 'ai-agents', 'page' => 'ai-agent-development', 'name' => 'AI Agents & Automation', 'service' => 'AI agent / automation', 'bar' => 'bar--blue',
            'icon' => '<path class="d" d="M12 3l1.8 4.7L18.5 9.5l-4.7 1.8L12 16l-1.8-4.7L5.5 9.5l4.7-1.8z"/><path d="M19 15l.8 2.2L22 18l-2.2.8L19 21l-.8-2.2L16 18l2.2-.8z"/>',
            'intro' => 'Hand repetitive work to AI: sorting emails, entering data, writing reports, updating your CRM. It follows your rules and reports back, so your team spends time on work that needs them.',
            'for' => 'Businesses losing hours each week to copy-paste and manual admin.',
            'gets' => ['A map of what can be automated first', 'Agents that work with your existing tools', 'Human approval where it matters', 'Clear logs of everything they did'],
            'tech' => ['OpenAI', 'Claude', 'Gemini', 'Python', 'LangChain', 'LangGraph', 'CrewAI', 'MCP', 'n8n', 'Make', 'Zapier', 'Node.js'],
        ],
        [
            'id' => 'websites', 'page' => ['shopify-development', 'wordpress-development', 'landing-page-design'], 'name' => 'Websites & Shopify Stores', 'service' => 'Website / WordPress', 'bar' => '',
            'icon' => '<circle class="d" cx="12" cy="12" r="9"/><path d="M3 12h18M12 3c2.5 2.6 3.8 5.6 3.8 9s-1.3 6.4-3.8 9c-2.5-2.6-3.8-5.6-3.8-9S9.5 5.6 12 3z"/>',
            'intro' => 'Fast business websites and online stores that load quickly, rank on Google and turn visitors into enquiries and sales — easy for you to update yourself.',
            'for' => 'Small businesses, shops and agencies that need a site that brings in customers.',
            'gets' => ['Mobile-friendly design', 'SEO basics and fast loading', 'Contact forms, payments and analytics', 'Shopify, WordPress or custom'],
            'tech' => ['Shopify', 'Liquid', 'Shopify apps', 'WordPress', 'WooCommerce', 'Elementor', 'PHP', 'HTML', 'CSS', 'JavaScript', 'Next.js', 'Webflow', 'Tailwind CSS'],
        ],
        [
            'id' => 'integrations', 'page' => ['api-integration-services', 'hosting-and-support'], 'name' => 'API Integrations, Hosting & Support', 'service' => 'API integration', 'bar' => 'bar--blue',
            'icon' => '<path d="M9 7H6a4 4 0 0 0 0 8h3M15 7h3a4 4 0 0 1 0 8h-3M8 11h8"/>',
            'intro' => 'Connect payments, CRMs, ERPs and AI models to the tools you already use — then keep it all running with hosting, monitoring and fixes. We also take over apps other developers started.',
            'for' => 'Businesses with an existing app or website that needs fixing, extending or looking after.',
            'gets' => ['Stripe, PayPal, HubSpot, Salesforce CRM and more', 'Hosting, domains, email and SSL', 'Monitoring, backups and security updates', 'Bug fixes and new features on request'],
            'tech' => ['REST', 'GraphQL', 'Webhooks', 'OAuth', 'Stripe', 'PayPal', 'HubSpot', 'Salesforce CRM', 'AWS', 'Google Cloud', 'Azure', 'DigitalOcean', 'Vercel', 'Docker', 'Cloudflare', 'GitHub Actions'],
        ],
    ];

    $faq = [
        ['Do I have to pay upfront?', 'No. Every project starts free: we plan it and design your key screens at no cost. The rest is split into as many milestones as we agree, and you pay for each one only after you have seen and approved it.'],
        ['How much does a project cost?', 'It depends on what you need. Small websites start at a few hundred dollars and most web apps are a few thousand. Send us your idea and you get a fixed price, free, within one business day.'],
        ['How fast can you build my app or website?', 'A business website is usually live in 1 to 3 weeks. A web app or mobile app usually takes 3 to 8 weeks, and an AI chatbot 1 to 3 weeks. Your quote includes a timeline with dates for each stage.'],
        ['Will I own the source code and designs?', 'Yes, all of it. Once the project is paid, we hand over the source code, design files and every account (hosting, app stores, domains) in your name. There is no lock-in, so any developer can carry on the work.'],
        ['Can you fix or finish an app another developer started?', 'Yes. We review the existing code first, tell you honestly what state it is in, then fix, finish or extend it. Many of our projects start this way.'],
        ['Which AI does your chatbot or agent use?', 'We build with OpenAI (ChatGPT), Anthropic Claude and Google Gemini. We pick the model that fits your task and budget, and can switch later without rebuilding.'],
        ['Do you work with clients outside my country?', 'Yes. We work online with clients worldwide, mostly in the US, UK, Europe, Canada, Australia and the Middle East, and fit calls around your time zone.'],
        ['What do I need to get started?', 'Just a short description of what you want. Notes, sketches or examples of apps you like help, but they are not required — we will ask the right questions.'],
    ];

    // Structured data for search engines: the services on this page and its FAQ.
    // Same @id as the Organization on the homepage (partials/structured-data), with its key details repeated here.
    $org = \App\Support\Owlvix::organization();
    $orgRef = ['@id' => $org['@id']];
    $graph = [
        '@context' => 'https://schema.org',
        '@graph' => [
            $org,
            [
                '@type' => 'WebPage',
                '@id' => route('landing').'#page',
                'url' => route('landing'),
                'name' => 'Web, Mobile & AI Development Services',
                'isPartOf' => ['@type' => 'WebSite', '@id' => url('/').'#website', 'name' => 'Owlvix', 'url' => url('/')],
                'description' => 'Web app, mobile app, AI chatbot, AI agent, website and integration development services from Owlvix, with a free fixed-price quote.',
                'inLanguage' => 'en',
                'about' => $orgRef,
                'mainEntity' => ['@id' => route('landing').'#services-list'],
                'breadcrumb' => ['@type' => 'BreadcrumbList', 'itemListElement' => [
                    ['@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => url('/')],
                    ['@type' => 'ListItem', 'position' => 2, 'name' => 'Services', 'item' => route('landing')],
                ]],
            ],
            [
                '@type' => 'ItemList',
                '@id' => route('landing').'#services-list',
                'name' => 'Owlvix services',
                'itemListElement' => array_map(fn ($s, $i) => [
                    '@type' => 'ListItem', 'position' => $i + 1,
                    'item' => ['@type' => 'Service', '@id' => route('landing').'#'.$s['id'], 'name' => $s['name'], 'serviceType' => $s['name'], 'description' => $s['intro'], 'url' => route('landing').'#'.$s['id'], 'areaServed' => 'Worldwide', 'provider' => $orgRef],
                ], $services, array_keys($services)),
            ],
            [
                '@type' => 'FAQPage',
                'mainEntity' => array_map(fn ($q) => ['@type' => 'Question', 'name' => $q[0], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $q[1]]], $faq),
            ],
        ],
    ];
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Web App, Mobile App & AI Development Services | Owlvix</title>
    <meta name="description" content="Hire a team to build your web app, mobile app, AI chatbot or automation. Fixed price, free quote within 24 hours, and you own 100% of the code.">
    <link rel="canonical" href="{{ route('landing') }}">
    <meta name="robots" content="index, follow, max-image-preview:large">
    <meta name="theme-color" content="#070b14" media="(prefers-color-scheme: dark)">
    <meta name="theme-color" content="#f5f7fb" media="(prefers-color-scheme: light)">

    <meta property="og:type" content="website">
    <meta property="og:site_name" content="Owlvix">
    <meta property="og:locale" content="en_US">
    <meta property="og:title" content="Web apps, mobile apps & AI — built for your business | Owlvix">
    <meta property="og:description" content="Custom web apps, mobile apps, AI chatbots and automation. Fixed price, free quote within 24h, and you own the code.">
    <meta property="og:url" content="{{ route('landing') }}">
    <meta property="og:image" content="{{ asset('images/og.png') }}">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="628">
    <meta property="og:image:alt" content="Owlvix owl mascot next to the text: Need a web app? I build it start to finish.">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="Web apps, mobile apps & AI — built for your business | Owlvix">
    <meta name="twitter:description" content="Custom web apps, mobile apps, AI chatbots and automation. Free quote within 24h.">
    <meta name="twitter:image" content="{{ asset('images/og.png') }}">
    <meta name="twitter:image:alt" content="Owlvix owl mascot next to the text: Need a web app? I build it start to finish.">

    <script type="application/ld+json">{!! json_encode($graph, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) !!}</script>

    @include('partials.head-assets')
</head>
{{-- data-auto-quote="off": the form is already at the top, so the quote popup doesn't open by itself here --}}
<body class="lp" data-no-popup="{{ session('sent') || $errors->any() ? '1' : '0' }}" data-auto-quote="off">
    <div class="bg-grid" aria-hidden="true"></div>

    @include('partials.site-nav', ['local' => ['services', 'process', 'reviews', 'pricing', 'faq', 'contact'], 'form' => true])

    <main>
        {{-- Hero: the offer on the left, the quote form on the right (first thing on phones after the headline) --}}
        <section class="hero lp-hero" id="contact">
            <div class="container lp-hero__inner">
                <div class="lp-hero__copy">
                    <span class="pill"><span class="pill__dot" aria-hidden="true"></span>Web • Mobile • AI development</span>
                    <h1>Web apps, mobile apps &amp; AI tools, <span class="accent">built for your business</span></h1>
                    <p class="lead">One team for the whole job: design, development, AI and launch. Tell us what you need and get a fixed-price quote within one business day — free, with no obligation.</p>
                    <ul class="checks lp-hero__checks">
                        <li>Free, fixed-price quote within 24 hours</li>
                        <li>You see progress every week on a live preview</li>
                        <li>100% of the source code is yours</li>
                        <li>Free bug fixes after launch</li>
                    </ul>
                    <a href="#quote-form" class="btn btn--glow lp-hero__jump">Get my free quote
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14M6 13l6 6 6-6"/></svg>
                    </a>
                    @if (! empty($t['profile_url']))
                        <a href="{{ $t['profile_url'] }}" class="rating-badge" target="_blank" rel="noopener">
                            <span class="rating-badge__stars" aria-hidden="true">★★★★★</span>
                            <span>@include('partials.rating-summary', ['t' => $t])</span>
                        </a>
                    @endif
                </div>

                @include('partials.quote-form')
            </div>
        </section>

        {{-- Trust strip --}}
        <section class="strip">
            <div class="container strip__inner">
                <div><strong>200+</strong><span>projects shipped</span></div>
                <div><strong>7+</strong><span>years building</span></div>
                <div><strong>{{ $t['five_star'] ?: $t['count'] }}</strong><span>{{ $t['five_star'] ? 'five-star reviews' : 'client reviews' }}</span></div>
                <div><strong>24h</strong><span>reply to new enquiries</span></div>
            </div>
        </section>

        {{-- Services: one block each, with who it's for, what's included and the tools --}}
        <section class="section" id="services">
            <div class="container">
                <p class="eyebrow">Our services</p>
                <h2>Everything you need to build and grow online</h2>
                <nav class="lp-jump" aria-label="Jump to a service">
                    @foreach ($services as $s)<a href="#{{ $s['id'] }}">{{ $s['name'] }}</a>@endforeach
                </nav>
                <div class="lp-services">
                    @foreach ($services as $s)
                        <article class="card lp-service" id="{{ $s['id'] }}">
                            <div class="lp-service__main">
                                <span class="lp-service__icon {{ $s['bar'] ? 'is-blue' : '' }}" aria-hidden="true"><svg viewBox="0 0 24 24">{!! $s['icon'] !!}</svg></span>
                                <h3>{{ $s['name'] }}</h3>
                                <p>{{ $s['intro'] }}</p>
                                <p class="lp-service__for"><strong>Best for:</strong> {{ $s['for'] }}</p>
                                <ul class="chips" aria-label="Tools we use">
                                    @foreach ($s['tech'] as $tech)<li>{{ $tech }}</li>@endforeach
                                </ul>
                            </div>
                            <div class="lp-service__side">
                                <p class="lp-service__label">What you get</p>
                                <ul class="checks">
                                    @foreach ($s['gets'] as $g)<li>{{ $g }}</li>@endforeach
                                </ul>
                                <a href="#contact" class="btn btn--ghost btn--block" data-service="{{ $s['service'] }}">Get a quote for this</a>
                                @foreach ((array) ($s['page'] ?? []) as $slug)
                                    @if (config('service_pages.'.$slug))
                                        <a href="{{ route('service.'.$slug) }}" class="lp-service__more">{{ config('service_pages.'.$slug.'.name') }} in detail →</a>
                                    @endif
                                @endforeach
                            </div>
                        </article>
                    @endforeach
                </div>
                @include('partials.more-services')
            </div>
        </section>

        {{-- Why Owlvix --}}
        <section class="section section--alt" id="why">
            <div class="container">
                <p class="eyebrow">Why Owlvix</p>
                <h2>Why businesses choose us</h2>
                <div class="grid grid--4 lp-why">
                    <article class="card">
                        <span class="svc-icon" aria-hidden="true">{!! \App\Support\Icons::svg('team') !!}</span>
                        <h3>One team, start to finish</h3>
                        <p>Design, code, AI and hosting under one roof — no juggling freelancers.</p>
                    </article>
                    <article class="card">
                        <span class="svc-icon is-blue" aria-hidden="true">{!! \App\Support\Icons::svg('tag') !!}</span>
                        <h3>Fixed price, no surprises</h3>
                        <p>Clear scope, price and deadline before we start. You only pay for what was agreed.</p>
                    </article>
                    <article class="card">
                        <span class="svc-icon" aria-hidden="true">{!! \App\Support\Icons::svg('lock') !!}</span>
                        <h3>You own everything</h3>
                        <p>Source code, designs and accounts are handed over to you. No lock-in.</p>
                    </article>
                    <article class="card">
                        <span class="svc-icon is-blue" aria-hidden="true">{!! \App\Support\Icons::svg('heart') !!}</span>
                        <h3>Support after launch</h3>
                        <p>Free bug fixes after launch, and ongoing help whenever you need it.</p>
                    </article>
                </div>
            </div>
        </section>

        {{-- Process --}}
        <section class="section" id="process">
            <div class="container">
                <p class="eyebrow">How it works</p>
                <h2>How a project with us works</h2>
                <ol class="grid grid--3 steps">
                    <li class="card">
                        <span class="num">1</span>
                        <h3>Tell us what you need</h3>
                        <p>A few lines in the form above is enough. Within one business day you get questions, a plan and a fixed-price quote.</p>
                    </li>
                    <li class="card">
                        <span class="num">2</span>
                        <h3>Watch it take shape</h3>
                        <p>We design first, then build. Every week you can try the latest version on a private link and ask for changes.</p>
                    </li>
                    <li class="card">
                        <span class="num">3</span>
                        <h3>Go live with support</h3>
                        <p>We test it, launch it on your server or the app stores, hand everything over, and fix any bugs for free afterwards.</p>
                    </li>
                </ol>
            </div>
        </section>

        @include('partials.reviews')

        @include('partials.pricing')

        {{-- FAQ --}}
        <section class="section section--alt" id="faq">
            <div class="container container--narrow">
                <p class="eyebrow">FAQ</p>
                <h2>Questions people ask first</h2>
                <div class="faq">
                    @foreach ($faq as [$q, $a])
                        <details>
                            <summary>{{ $q }}</summary>
                            <p>{{ $a }}</p>
                        </details>
                    @endforeach
                </div>
            </div>
        </section>

        {{-- Final call to action --}}
        <section class="section lp-cta">
            <div class="container">
                <div class="card lp-cta__box">
                    @include('partials.live-owl', ['width' => 120, 'alt' => 'Owlvix owl mascot'])
                    <div>
                        <h2>Have a project in mind?</h2>
                        <p>Tell us about it in a few lines. You get a fixed-price quote within one business day — free.</p>
                    </div>
                    <a href="#contact" class="btn btn--glow">Get a free quote
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
                    </a>
                </div>
            </div>
        </section>
    </main>

    @include('partials.footer', ['top' => route('home')])

    @include('partials.page-scripts')
</body>
</html>
