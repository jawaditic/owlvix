<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Owlvix — Web Apps, Mobile Apps & AI, Built Start to Finish</title>
    <meta name="description" content="Owlvix designs and builds web apps, mobile apps, AI chatbots and automation for businesses. Clean code you own, regular updates, on-time delivery.">
    <meta name="theme-color" content="#070b14">

    <meta property="og:type" content="website">
    <meta property="og:title" content="Owlvix — Need a web app? We build it start to finish.">
    <meta property="og:description" content="Design • Frontend • Backend • AI • Launch">
    <meta property="og:url" content="{{ url('/') }}">
    <meta property="og:image" content="{{ asset('images/og.png') }}">
    <meta name="twitter:card" content="summary_large_image">

    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Patrick+Hand&family=Poppins:wght@500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/intl-tel-input@29.5.7/dist/css/intlTelInput.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.snow.css">
    <link rel="stylesheet" href="{{ asset('css/site.css') }}?v={{ filemtime(public_path('css/site.css')) }}">
</head>
<body data-no-popup="{{ session('sent') || $errors->any() ? '1' : '0' }}">
    <div class="bg-grid" aria-hidden="true"></div>

    <header class="nav" id="top">
        <div class="container nav__inner">
            <a href="#top" class="brand" aria-label="Owlvix home">
                <img src="{{ asset('images/logo-mark.svg') }}" alt="" width="36" height="36">
                <span>Owl<span class="accent">vix</span></span>
            </a>
            <button class="nav__toggle" aria-label="Open menu" aria-expanded="false" aria-controls="nav-links">
                <span></span><span></span><span></span>
            </button>
            <nav class="nav__links" id="nav-links">
                <a href="#services">Services</a>
                <a href="#process">Process</a>
                <a href="#pricing">Pricing</a>
                <a href="#faq">FAQ</a>
                <a href="#contact" class="btn btn--sm" data-quote>Get a free quote</a>
            </nav>
        </div>
    </header>

    <main>
        {{-- Hero --}}
        <section class="hero">
            <div class="container hero__inner">
                <div class="hero__copy">
                    <span class="pill"><span class="pill__dot" aria-hidden="true"></span>7+ years • 200+ projects delivered</span>
                    <h1>Need a <span class="accent rotator" data-rotate='["web app?","mobile app?","AI chatbot?","AI agent?"]'>web app?</span>We build it start to finish.</h1>
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
                                <img src="{{ asset('favicon.svg') }}" alt="" width="34" height="34" class="chat__avatar">
                                <div>
                                    <strong>Owlvix</strong>
                                    <small><span class="live-dot" aria-hidden="true"></span>Online · replies within 24h</small>
                                </div>
                            </div>
                            <p class="chat__text" aria-live="polite" data-owl-text>Hoo needs a developer? Tell me your idea and I'll show you how we'd build it.</p>
                            <button type="button" class="board__play chat__play" aria-label="Play the owl's intro" data-owl-play>
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
                    </article>
                    <article class="card">
                        <span class="bar bar--blue"></span>
                        <h3>Mobile Apps</h3>
                        <p>iOS and Android apps that share one codebase and talk to your existing systems.</p>
                    </article>
                    <article class="card">
                        <span class="bar"></span>
                        <h3>AI Chatbots</h3>
                        <p>Website and customer support bots trained on your own documents, FAQs and products.</p>
                    </article>
                    <article class="card">
                        <span class="bar bar--blue"></span>
                        <h3>AI Agents & Automation</h3>
                        <p>Hand repetitive tasks and workflows to AI that follows your rules and reports back.</p>
                    </article>
                    <article class="card">
                        <span class="bar"></span>
                        <h3>API Integrations</h3>
                        <p>Connect OpenAI, Claude, Gemini, payments, CRMs and ERPs to the tools you already use.</p>
                    </article>
                    <article class="card">
                        <span class="bar bar--blue"></span>
                        <h3>Hosting & Support</h3>
                        <p>Setup, deployment, monitoring and fixes after launch — you're never left on your own.</p>
                    </article>
                </div>
                <p class="promise">Clean code you own <i>•</i> Regular updates <i>•</i> On-time delivery</p>
            </div>
        </section>

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

        {{-- Pricing --}}
        <section class="section" id="pricing">
            <div class="container">
                <p class="eyebrow">Pricing</p>
                <h2>Two simple ways to work with us</h2>
                <div class="grid grid--2 pricing">
                    <article class="card plan">
                        <h3>Fixed project</h3>
                        <p class="price"><span>Custom quote</span></p>
                        <p>Best when you know what you want built — a new app, a website or an AI feature.</p>
                        <ul class="checks">
                            <li>Clear scope, price and deadline up front</li>
                            <li>Weekly progress updates</li>
                            <li>Full source code handed over</li>
                            <li>Free bug fixes after launch</li>
                        </ul>
                        <a href="#contact" class="btn btn--ghost btn--block" data-service="Fixed project">Get a quote</a>
                    </article>
                    <article class="card plan plan--featured">
                        <span class="badge">Most flexible</span>
                        <h3>Monthly developer</h3>
                        <p class="price">from <strong>$100</strong><span>/month</span></p>
                        <p>Your own developer on call for ongoing work across web, mobile, AI and support.</p>
                        <ul class="checks">
                            <li>New features, fixes and updates every month</li>
                            <li>Web • Mobile • AI • Support</li>
                            <li>Priority replies</li>
                            <li>Cancel any time</li>
                        </ul>
                        <a href="#contact" class="btn btn--block" data-service="Monthly developer">Start monthly plan</a>
                    </article>
                </div>
            </div>
        </section>

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
                    <img src="{{ asset('images/owl.png') }}" alt="" width="200" height="210" class="contact__owl" loading="lazy">
                </div>

                <form class="card form" method="POST" action="{{ route('contact.store') }}#contact" novalidate data-validate>
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
                </form>
            </div>
        </section>
    </main>

    <footer class="footer">
        <div class="container footer__inner">
            <a href="#top" class="brand brand--sm">
                <img src="{{ asset('images/logo-mark.svg') }}" alt="" width="28" height="28">
                <span>Owl<span class="accent">vix</span></span>
            </a>
            <p>© {{ date('Y') }} Owlvix. Web, mobile & AI development.</p>
            <a href="#contact">Start a project →</a>
        </div>
    </footer>

    @include('partials.quote-modal')

    <script>
        window.OWLVIX = @json(\App\Support\OwlVoice::sets(['intro', 'quote']));
        window.OWLVIX.recaptchaKey = @json(config('services.recaptcha.site_key'));
    </script>
    @if (config('services.recaptcha.site_key'))
        <script src="https://www.google.com/recaptcha/api.js?render={{ urlencode(config('services.recaptcha.site_key')) }}" async defer></script>
    @endif
    <script src="{{ asset('js/owl.js') }}?v={{ filemtime(public_path('js/owl.js')) }}"></script>
    <script src="https://cdn.jsdelivr.net/npm/intl-tel-input@29.5.7/dist/js/intlTelInput.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.js"></script>
    <script src="{{ asset('js/site.js') }}?v={{ filemtime(public_path('js/site.js')) }}"></script>
</body>
</html>
