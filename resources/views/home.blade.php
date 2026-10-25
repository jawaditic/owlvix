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
                    <span class="pill">7+ years • 200+ projects delivered</span>
                    <h1>Need a <span class="accent">web app?</span><br>We build it start to finish.</h1>
                    <p class="lead">Owlvix designs, builds and launches web apps, mobile apps and AI tools for growing businesses — from the first sketch to the server it runs on.</p>
                    <p class="tags">Design <i>•</i> Frontend <i>•</i> Backend <i>•</i> AI <i>•</i> Launch</p>
                    <div class="hero__cta">
                        <a href="#contact" class="btn" data-quote>Get a free quote</a>
                        <a href="#services" class="btn btn--ghost">See what we build</a>
                    </div>
                </div>
                <div class="hero__art" id="owl-intro">
                    <div class="stage">
                        <div class="board">
                            <p class="board__title">Owlvix 101</p>
                            <p class="board__text" aria-live="polite" data-owl-text>Hoo needs a developer? Tell me your idea and I'll show you how we'd build it.</p>
                            <button type="button" class="board__play" aria-label="Play the owl's intro" data-owl-play>
                                <svg class="i-play" viewBox="0 0 24 24" aria-hidden="true"><path d="M8 5v14l11-7z"/></svg>
                                <svg class="i-stop" viewBox="0 0 24 24" aria-hidden="true"><path d="M7 7h10v10H7z"/></svg>
                                <span>Hear the intro</span>
                            </button>
                            <span class="board__chalk" aria-hidden="true"></span>
                        </div>
                        <div class="owl-wrap">
                            <img src="{{ asset('images/owl-body.png') }}" alt="Owlvix owl mascot pointing at the board" width="395" height="400" class="owl">
                            <img src="{{ asset('images/owl-mouth.png') }}" alt="" width="63" height="43" class="owl__mouth" aria-hidden="true">
                            <svg class="owl__smile" viewBox="0 0 395 400" aria-hidden="true"><path d="M255 187 Q281 196 309 182"/></svg>
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
                    <img src="{{ asset('images/owl.png') }}" alt="" width="200" height="203" class="contact__owl" loading="lazy">
                </div>

                <form class="card form" method="POST" action="{{ route('contact.store') }}#contact" novalidate>
                    @csrf
                    <input type="hidden" name="source" value="contact">
                    @if (session('sent'))
                        <div class="alert alert--ok" role="status">Thanks! Your message is in. We'll get back to you within one business day.</div>
                    @endif
                    @if ($errors->any())
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
                    <label>Your project
                        <textarea name="message" rows="5" required minlength="10" maxlength="5000" placeholder="What should it do? Who is it for? Any deadline?" @error('message') aria-invalid="true" @enderror>{{ old('message') }}</textarea>
                        @error('message')<small>{{ $message }}</small>@enderror
                    </label>
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
    </script>
    <script src="{{ asset('js/owl.js') }}?v={{ filemtime(public_path('js/owl.js')) }}"></script>
    <script src="{{ asset('js/site.js') }}?v={{ filemtime(public_path('js/site.js')) }}"></script>
</body>
</html>
