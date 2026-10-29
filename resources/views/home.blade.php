<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Owlvix — Web Apps, Mobile Apps & AI, Built Start to Finish</title>
    <meta name="description" content="Owlvix designs and builds web apps, mobile apps, AI chatbots and automation for businesses. Clean code you own, regular updates, on-time delivery.">
    <link rel="canonical" href="{{ url('/') }}">
    <meta name="robots" content="index, follow, max-image-preview:large">
    <meta name="theme-color" content="#070b14" media="(prefers-color-scheme: dark)">
    <meta name="theme-color" content="#f5f7fb" media="(prefers-color-scheme: light)">

    {{-- Link previews (WhatsApp, Facebook, LinkedIn, X) --}}
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="Owlvix">
    <meta property="og:locale" content="en_US">
    <meta property="og:title" content="Owlvix — Need a web app? We build it start to finish.">
    <meta property="og:description" content="Web apps, mobile apps and AI tools for growing businesses. Design, frontend, backend, AI and launch — with code you own.">
    <meta property="og:url" content="{{ url('/') }}">
    <meta property="og:image" content="{{ asset('images/og.png') }}">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="628">
    <meta property="og:image:alt" content="Owlvix owl mascot next to the text: Need a web app? I build it start to finish.">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="Owlvix — Need a web app? We build it start to finish.">
    <meta name="twitter:description" content="Web apps, mobile apps and AI tools for growing businesses, with code you own.">
    <meta name="twitter:image" content="{{ asset('images/og.png') }}">
    <meta name="twitter:image:alt" content="Owlvix owl mascot next to the text: Need a web app? I build it start to finish.">

    @include('partials.structured-data')

    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
    {{-- Fonts and the phone/editor styles load without blocking the first paint --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Poppins:wght@500;600;700;800&display=swap" media="print" onload="this.media='all'"><noscript><link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Poppins:wght@500;600;700;800&display=swap"></noscript>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/intl-tel-input@29.5.7/dist/css/intlTelInput.min.css" media="print" onload="this.media='all'"><noscript><link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/intl-tel-input@29.5.7/dist/css/intlTelInput.min.css"></noscript>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.snow.css" media="print" onload="this.media='all'"><noscript><link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.snow.css"></noscript>
    {{-- Theme: saved choice, else the device setting. Runs before paint so there's no flash. --}}
    <script>(function () { var t; try { t = localStorage.getItem('owlvix-theme'); } catch (e) {} if (t !== 'light' && t !== 'dark') t = matchMedia('(prefers-color-scheme: light)').matches ? 'light' : 'dark'; document.documentElement.setAttribute('data-theme', t); })();</script>
    <link rel="stylesheet" href="{{ \App\Support\Asset::url('css/theme.css') }}">
    <link rel="stylesheet" href="{{ \App\Support\Asset::url('css/site.css') }}">
    <noscript><style>[data-reviews].is-loading > * { visibility: visible; } [data-reviews].is-loading::after { display: none; }</style></noscript>
    @include('partials.analytics')
</head>
<body data-no-popup="{{ session('sent') || $errors->any() ? '1' : '0' }}">
    <div class="bg-grid" aria-hidden="true"></div>

    @include('partials.site-nav')

    <main>
        {{-- Hero --}}
        <section class="hero">
            <div class="container hero__inner">
                <div class="hero__copy">
                    <span class="pill"><span class="pill__dot" aria-hidden="true"></span>7+ years • 200+ projects delivered</span>
                    <h1>Need <span data-article>a</span> <span class="accent rotator" data-rotate='["web app?","mobile app?","AI chatbot?","AI agent?"]'>web app?</span> We build it start to finish.</h1>
                    <p class="lead">Owlvix designs, builds and launches web apps, mobile apps and AI tools for growing businesses — from the first sketch to the server it runs on.</p>
                    <ul class="chips" aria-label="What we cover">
                        <li>Design</li><li>Frontend</li><li>Backend</li><li>AI</li><li>Launch</li>
                    </ul>
                    <div class="hero__cta">
                        <a href="#contact" class="btn btn--glow" data-quote>Get a free quote
                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
                        </a>
                        <a href="#services" class="btn btn--ghost">See what we build</a>
                    </div>
                    <ul class="hero__trust">
                        <li><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg>Free quote within 24h</li>
                        <li><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg>You own 100% of the code</li>
                    </ul>
                </div>

                {{-- Owl mascot "chatting" in a bubble; owl.js fills [data-owl-text] and moves the beak --}}
                <div class="hero__art" id="owl-intro">
                    <div class="stage">
                        <div class="orb" aria-hidden="true"></div>
                        <div class="ring" aria-hidden="true"></div>

                        <div class="chat glass">
                            <div class="chat__head">
                                <img src="{{ asset('favicon.svg') }}" alt="Owlvix" width="34" height="34" class="chat__avatar">
                                <div>
                                    <strong>Owlvix</strong>
                                    <small><span class="live-dot" aria-hidden="true"></span>Online · replies within 24h</small>
                                </div>
                            </div>
                            <p class="chat__text" aria-live="polite" data-owl-text>Hoo needs a developer? Tell me your idea and I'll show you how we'd build it.</p>
                            <button type="button" class="board__play chat__play" data-owl-play>
                                <svg class="i-play" viewBox="0 0 24 24" aria-hidden="true"><path d="M8 5v14l11-7z"/></svg>
                                <svg class="i-stop" viewBox="0 0 24 24" aria-hidden="true"><path d="M7 7h10v10H7z"/></svg>
                                <span>Hear the intro</span>
                            </button>
                        </div>

                        <div class="float float--web glass" aria-hidden="true">
                            <span class="float__icon"><svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="13" rx="2"/><path d="M8 21h8M12 17v4"/></svg></span>
                            <div><strong>Web apps</strong><small>Laravel · React · Vue</small></div>
                        </div>
                        <div class="float float--ai glass" aria-hidden="true">
                            <span class="float__icon float__icon--blue"><svg viewBox="0 0 24 24"><path d="M12 3l1.8 4.7L18.5 9.5l-4.7 1.8L12 16l-1.8-4.7L5.5 9.5l4.7-1.8z"/><path d="M19 15l.8 2.2L22 18l-2.2.8L19 21l-.8-2.2L16 18l2.2-.8z"/></svg></span>
                            <div><strong>AI agents</strong><small>OpenAI · Claude · Gemini</small></div>
                        </div>
                        <div class="float float--mobile glass" aria-hidden="true">
                            <span class="float__icon"><svg viewBox="0 0 24 24"><rect x="7" y="2.5" width="10" height="19" rx="2.5"/><path d="M11 18.5h2"/></svg></span>
                            <div><strong>Mobile apps</strong><small>iOS &amp; Android</small></div>
                        </div>

                        <div class="owl-wrap">
                            @include('partials.owl', ['alt' => 'Owlvix owl mascot'])
                        </div>
                        <div class="glow" aria-hidden="true"></div>
                    </div>
                </div>
            </div>
        </section>

        {{-- Trust strip --}}
        <section class="strip">
            <div class="container strip__inner">
                <div><strong>200+</strong><span>projects shipped</span></div>
                <div><strong>7+</strong><span>years building</span></div>
                <div><strong>100%</strong><span>source code is yours</span></div>
                <div><strong>24h</strong><span>reply to new enquiries</span></div>
            </div>
        </section>

        {{-- Services --}}
        <section class="section" id="services">
            <div class="container">
                <p class="eyebrow">What we build</p>
                <h2>Software that works for your business</h2>
                <div class="grid grid--3">
                    <article class="card">
                        <span class="bar"></span>
                        <h3>Custom Web Apps</h3>
                        <p>Dashboards, portals, SaaS products and booking systems built with Laravel, React and Vue.</p>
                        <a href="{{ route('service.web-app-development') }}" class="card__more">Learn more about web apps →</a>
                    </article>
                    <article class="card">
                        <span class="bar bar--blue"></span>
                        <h3>Mobile Apps</h3>
                        <p>iOS and Android apps that share one codebase and talk to your existing systems.</p>
                        <a href="{{ route('service.mobile-app-development') }}" class="card__more">Learn more about mobile apps →</a>
                    </article>
                    <article class="card">
                        <span class="bar"></span>
                        <h3>AI Chatbots</h3>
                        <p>Website and customer support bots trained on your own documents, FAQs and products.</p>
                        <a href="{{ route('service.ai-chatbot-development') }}" class="card__more">Learn more about AI chatbots →</a>
                    </article>
                    <article class="card">
                        <span class="bar bar--blue"></span>
                        <h3>AI Agents & Automation</h3>
                        <p>Hand repetitive tasks and workflows to AI that follows your rules and reports back.</p>
                        <a href="{{ route('service.ai-agent-development') }}" class="card__more">Learn more about AI agents →</a>
                    </article>
                    <article class="card">
                        <span class="bar"></span>
                        <h3>API Integrations</h3>
                        <p>Connect OpenAI, Claude, Gemini, payments, CRMs and ERPs to the tools you already use.</p>
                        <a href="{{ route('service.api-integration-services') }}" class="card__more">Learn more about integrations →</a>
                    </article>
                    <article class="card">
                        <span class="bar bar--blue"></span>
                        <h3>Hosting & Support</h3>
                        <p>Setup, deployment, monitoring and fixes after launch — you're never left on your own.</p>
                        <a href="{{ route('service.hosting-and-support') }}" class="card__more">Learn more about support →</a>
                    </article>
                </div>
                <p class="promise">Clean code you own <i>•</i> Regular updates <i>•</i> On-time delivery</p>
                <p class="services__more"><a href="{{ route('landing') }}" class="btn btn--ghost">Explore our development services in detail</a></p>
            </div>
        </section>

        {{-- Portfolio (Admin → Portfolio) --}}
        @php($projects = \Illuminate\Support\Facades\Schema::hasTable('projects') ? \App\Models\Project::published()->ordered()->get() : collect())
        @if ($projects->isNotEmpty())
        <section class="section section--alt" id="work">
            <div class="container">
                <p class="eyebrow">Our work</p>
                <h2>Projects we've built</h2>
                @php($cats = $projects->pluck('category')->unique())
                @if ($cats->count() > 1)
                    <div class="work-filter" role="group" aria-label="Filter projects">
                        <button type="button" class="is-active" data-filter="all" aria-pressed="true">All <span>{{ $projects->count() }}</span></button>
                        @foreach (\App\Models\Project::CATEGORIES as $key => $label)
                            @if ($cats->contains($key))
                                <button type="button" data-filter="{{ $key }}" aria-pressed="false">{{ $label }}{{ in_array($key, ['website', 'mobile']) ? 's' : '' }} <span>{{ $projects->where('category', $key)->count() }}</span></button>
                            @endif
                        @endforeach
                    </div>
                @endif
                <div class="work" data-work data-limit="8">
                    @foreach ($projects as $p)
                        <article class="work-card" data-category="{{ $p->category }}">
                            <div class="work-card__img">
                                @php([$iw, $ih] = ($p->image && is_file(public_path($p->image))) ? (getimagesize(public_path($p->image)) ?: [720, 480]) : [720, 480])
                                @include('partials.picture', ['src' => $p->image, 'alt' => $p->title.' — '.$p->categoryLabel().' project by Owlvix', 'width' => $iw, 'height' => $ih, 'loading' => 'lazy'])
                            </div>
                            <div class="work-card__body">
                                <span class="work-card__cat">{{ $p->categoryLabel() }}</span>
                                <h3>{{ $p->title }}</h3>
                                @if ($p->summary)<p>{{ $p->summary }}</p>@endif
                                @if ($p->url || $p->ios_url || $p->android_url)
                                    <div class="work-card__links">
                                        @if ($p->url)<a href="{{ $p->url }}" target="_blank" rel="noopener nofollow">View site ↗</a>@endif
                                        @if ($p->ios_url)<a href="{{ $p->ios_url }}" target="_blank" rel="noopener nofollow">App Store ↗</a>@endif
                                        @if ($p->android_url)<a href="{{ $p->android_url }}" target="_blank" rel="noopener nofollow">Google Play ↗</a>@endif
                                    </div>
                                @endif
                            </div>
                        </article>
                    @endforeach
                </div>
                <p class="work__more" hidden>
                    <button type="button" class="btn btn--ghost" data-work-more>Show all projects</button>
                </p>
            </div>
        </section>
        @endif

        {{-- Process --}}
        <section class="section section--alt" id="process">
            <div class="container">
                <p class="eyebrow">How it works</p>
                <h2>From idea to launch in 3 steps</h2>
                <ol class="grid grid--3 steps">
                    <li class="card">
                        <span class="num">1</span>
                        <h3>Share your idea</h3>
                        <p>Tell us what you need and send any notes, designs or documents. We reply with a plan and a clear quote.</p>
                    </li>
                    <li class="card">
                        <span class="num">2</span>
                        <h3>We design & build it</h3>
                        <p>You see progress every week on a live preview, so there are no surprises at the end.</p>
                    </li>
                    <li class="card">
                        <span class="num">3</span>
                        <h3>Launch & support</h3>
                        <p>Tested, deployed and handed over with full source code — plus support when you need it.</p>
                    </li>
                </ol>
            </div>
        </section>

        {{-- Who's behind Owlvix (full story on /about) --}}
        <section class="section" id="about">
            <div class="container">
                <div class="card about-strip">
                    @include('partials.picture', ['src' => 'images/owl.png', 'alt' => 'Owlvix owl mascot', 'width' => 120, 'height' => 126, 'loading' => 'lazy'])
                    <div>
                        <p class="eyebrow">Who's behind Owlvix</p>
                        <h2>A small team led by Jawad Ahmad</h2>
                        <p>Owlvix is a web, mobile and AI development studio founded by Jawad Ahmad, based in Pakistan and building software for clients worldwide since 2018. We were previously known as <strong>Anjtec</strong> on Fiverr — that's why the reviews below mention Anjtec and Jawad.</p>
                    </div>
                    <a href="{{ route('about') }}" class="btn btn--ghost">About us</a>
                </div>
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
                    <details>
                        <summary>How long does a project take?</summary>
                        <p>Most websites take 1–3 weeks and most web apps 3–8 weeks. You get an exact timeline with your quote.</p>
                    </details>
                    <details>
                        <summary>Do I own the code?</summary>
                        <p>Yes. When the project is paid for, all source code, designs and accounts are handed over to you.</p>
                    </details>
                    <details>
                        <summary>Can you work on my existing app?</summary>
                        <p>Yes. We regularly take over, fix and extend apps that other developers started.</p>
                    </details>
                    <details>
                        <summary>Which AI models do you use?</summary>
                        <p>We work with OpenAI, Anthropic Claude and Google Gemini, and pick the one that suits your budget and task.</p>
                    </details>
                    <details>
                        <summary>Do you help with hosting?</summary>
                        <p>Yes. We can set up hosting, domains, email and SSL, or deploy to the server you already have.</p>
                    </details>
                </div>
            </div>
        </section>

        {{-- Contact --}}
        <section class="section" id="contact">
            <div class="container contact">
                <div class="contact__copy">
                    <p class="eyebrow">Get in touch</p>
                    <h2>Tell us about your project</h2>
                    <p class="lead">Send a short message — no forms to sign, no obligation. We reply within one business day with questions or a quote.</p>
                    @include('partials.picture', ['src' => 'images/owl.png', 'alt' => 'Owlvix owl mascot', 'width' => 200, 'height' => 210, 'class' => 'contact__owl', 'loading' => 'lazy'])
                </div>

                <form class="card form" data-clarity-mask="true" method="POST" action="{{ route('contact.store') }}#contact" novalidate data-validate>
                    @csrf
                    <input type="hidden" name="source" value="contact">
                    @if (session('sent'))
                        <div class="alert alert--ok" role="status">Thanks! Your message is in. We'll get back to you within one business day.</div>
                    @endif
                    @if ($errors->has('captcha'))
                        <div class="alert alert--err" role="alert">{{ $errors->first('captcha') }}</div>
                    @elseif ($errors->any())
                        <div class="alert alert--err" role="alert">Please check the highlighted fields.</div>
                    @endif

                    <div class="hp" aria-hidden="true">
                        <label>Website <input type="text" name="website" tabindex="-1" autocomplete="off"></label>
                    </div>

                    <div class="row">
                        <label>Name
                            <input type="text" name="name" value="{{ old('name') }}" required maxlength="100" autocomplete="name" @error('name') aria-invalid="true" @enderror>
                            @error('name')<small>{{ $message }}</small>@enderror
                        </label>
                        <label>Email
                            <input type="email" name="email" value="{{ old('email') }}" required maxlength="150" autocomplete="email" @error('email') aria-invalid="true" @enderror>
                            @error('email')<small>{{ $message }}</small>@enderror
                        </label>
                    </div>
                    <div class="row">
                        <label>What do you need?
                            <select name="service" id="service">
                                @foreach (['Web app', 'Mobile app', 'AI chatbot / agent', 'Website', 'Fixed project', 'Monthly developer', 'Something else'] as $opt)
                                    <option value="{{ $opt }}" @selected(old('service') === $opt)>{{ $opt }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label>Budget
                            <select name="budget">
                                @foreach (['Not sure yet', 'Under $500', '$500 – $2,000', '$2,000 – $5,000', '$5,000+'] as $opt)
                                    <option value="{{ $opt }}" @selected(old('budget') === $opt)>{{ $opt }}</option>
                                @endforeach
                            </select>
                        </label>
                    </div>
                    <div class="field">
                        <label for="contact-message">Your project</label>
                        <textarea id="contact-message" name="message" rows="5" required placeholder="What should it do? Who is it for? Any deadline?" data-rich @error('message') aria-invalid="true" @enderror>{{ old('message') }}</textarea>
                        @error('message')<small>{{ $message }}</small>@enderror
                    </div>
                    <button type="submit" class="btn btn--block">Send message</button>
                    <p class="form__privacy">We only use your details to reply to you. <a href="{{ route('privacy') }}">Privacy policy</a></p>
                </form>
            </div>
        </section>
    </main>

    @include('partials.footer')

    @include('partials.quote-modal')
    @include('partials.exit-survey')

    <script>
        window.OWLVIX = @json(\App\Support\OwlVoice::sets(['intro', 'quote']));
        window.OWLVIX.recaptchaKey = @json(config('services.recaptcha.site_key'));
    </script>
    {{-- "defer": scripts download in parallel and run in order after the page is parsed, without blocking it.
         Google reCAPTCHA is loaded by site.js only when someone starts filling in a form. --}}
    <script src="{{ \App\Support\Asset::url('js/owl.js') }}" defer></script>
    <script src="{{ \App\Support\Asset::url('js/site.js') }}" defer></script>
    <script src="{{ \App\Support\Asset::url('js/theme.js') }}" defer></script>
</body>
</html>
