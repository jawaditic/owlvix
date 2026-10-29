<?php

// One page per service (resources/views/service.blade.php), at https://owlvix.com/<slug>.
// "service" is the value preselected in the quote form (App\Support\Enquiries::SERVICES).
// "category" shows matching portfolio projects (App\Models\Project::CATEGORIES) when there are any.
// Keep claims factual: no invented numbers, clients or results.
return [
    'ai-chatbot-development' => [
        'name' => 'AI Chatbot Development',
        'service' => 'AI chatbot / agent',
        'category' => 'ai',
        'title' => 'Custom AI Chatbot Development Services | Owlvix',
        'description' => 'Custom AI chatbots trained on your own documents, FAQs and products, for your website, WhatsApp or support desk. Fixed-price quote within 24 hours.',
        'form_title' => 'Get a free AI chatbot quote',
        'h1' => 'AI chatbot development for your website, WhatsApp and support desk',
        'lead' => 'We build custom AI chatbots that answer your customers from your own documents, FAQs and product data — and hand over to a person when they should. You get a fixed-price quote within one business day, and you own everything we build.',
        'checks' => [
            'Answers from your own content, not the open internet',
            'Works on your website, WhatsApp, Slack or help desk',
            'Captures leads and books meetings',
            'Built on OpenAI, Claude or Gemini — your choice',
        ],

        // Short definition near the top: easy for search engines and AI assistants to quote
        'definition' => [
            'heading' => 'What is a custom AI chatbot?',
            'text' => 'A custom AI chatbot is an assistant built on a large language model, such as OpenAI GPT, Anthropic Claude or Google Gemini, that is connected to your own business information. Instead of following a fixed script, it reads your documents, help articles and product data to answer questions in plain language, and it can take actions such as collecting a lead, booking a call or creating a support ticket.',
        ],

        'types' => [
            'heading' => 'Chatbots we build',
            'items' => [
                ['Website support chatbot', 'Answers common customer questions on your site around the clock, using your FAQs, policies and help articles, and passes tricky conversations to your team.'],
                ['Sales and lead chatbot', 'Asks visitors what they need, recommends the right product or service, collects contact details and sends qualified leads straight to your inbox or CRM.'],
                ['WhatsApp and messaging bots', 'The same assistant on WhatsApp, Facebook Messenger, Instagram or Telegram, so customers can reach you where they already chat.'],
                ['Internal knowledge assistant', 'Lets your staff ask questions about manuals, policies and past projects in Slack or Microsoft Teams instead of searching through folders.'],
                ['Booking and order chatbot', 'Checks availability, books appointments or tracks orders by connecting to your calendar, booking system or shop.'],
                ['E-commerce shopping assistant', 'Helps shoppers find products, compare options and answer sizing or delivery questions on Shopify, WooCommerce or a custom store.'],
            ],
        ],

        'how' => [
            'heading' => 'How a custom AI chatbot works',
            'paragraphs' => [
                'Most of our chatbots use a method called retrieval-augmented generation (RAG). First, we collect the information the chatbot should know: your website pages, PDFs, FAQs, product catalogue or help desk articles. We split it into small passages and store them in a searchable index.',
                'When a customer asks a question, the chatbot finds the passages that best match it and gives them to the AI model together with the question. The model writes an answer based only on that information, so replies stay accurate and on-brand, and the chatbot can say "I don\'t know" or hand over to a person instead of guessing.',
                'Because your knowledge lives in the index, not inside the model, updating the chatbot is as simple as updating your documents. You can also switch AI providers later without rebuilding everything.',
            ],
        ],

        'gets' => [
            'heading' => 'What\'s included',
            'items' => [
                'Planning: what the chatbot should and shouldn\'t do, and its tone of voice',
                'Your content prepared and indexed, with a way to keep it up to date',
                'Chat widget designed to match your brand, or WhatsApp/Slack setup',
                'Hand-over to a person by email, live chat or ticket',
                'Lead capture, booking or CRM connection where needed',
                'Conversation history and a simple report of common questions',
                'Testing against real customer questions before launch',
                'Full source code and accounts handed over to you',
            ],
        ],

        'steps' => [
            ['Tell us what it should do', 'Send a few lines about your business and the questions your customers ask. Within one business day you get questions, a plan and a fixed-price quote.'],
            ['We build and train it', 'We prepare your content, build the chatbot and share a private test link, so you can try it with real questions and tell us what to change.'],
            ['Launch and improve', 'We add it to your website or messaging app, watch the first conversations, and fine-tune answers. Bug fixes after launch are free.'],
        ],

        'tech' => ['OpenAI GPT', 'Anthropic Claude', 'Google Gemini', 'RAG', 'Vector search', 'WhatsApp Business API', 'Slack', 'Laravel', 'Python', 'Node.js'],

        'cost' => [
            'heading' => 'How much does an AI chatbot cost?',
            'text' => 'The price depends mainly on four things: how much content the chatbot needs to learn, how many channels it runs on (website only, or WhatsApp and Slack too), which systems it connects to (CRM, booking, shop or help desk), and whether you need custom design or an admin panel. A website chatbot that answers questions from your existing FAQs and pages is the simplest and most affordable option; chatbots that take actions in other systems take more work. Tell us what you need and we will send a fixed price, free, within one business day. Running costs for the AI model itself are usually small and are paid directly to the provider, so you only pay for what your customers actually use.',
        ],

        'faq' => [
            ['How long does it take to build an AI chatbot?', 'A website chatbot that answers from your existing content usually takes 1 to 3 weeks. Chatbots that connect to other systems, such as a CRM or booking tool, take longer. Your quote includes a timeline.'],
            ['Will the chatbot make things up?', 'We design it to answer only from your own content and to say when it doesn\'t know, then offer to pass the question to your team. We test it against real customer questions before launch.'],
            ['Which AI model is best for my chatbot?', 'OpenAI GPT, Anthropic Claude and Google Gemini all work well. We recommend one based on your budget, the languages you need and how you want data handled, and you can switch later.'],
            ['Can the chatbot speak other languages?', 'Yes. Modern AI models understand and reply in many languages, so customers can ask in their own language even if your content is in English.'],
            ['Is my data safe?', 'Your documents are stored in your own accounts, and we use the AI providers\' business APIs, which don\'t use your data to train their public models. We can also limit what the chatbot is allowed to see.'],
            ['Can you add a chatbot to my existing website?', 'Yes. The chat widget works on any site, including WordPress, Shopify, Wix and custom-built websites. We can also connect it to an existing help desk or CRM.'],
        ],

        'related' => ['ai-agent-development', 'web-app-development'],
    ],

    'web-app-development' => [
        'name' => 'Custom Web App Development',
        'service' => 'Web app',
        'category' => 'webapp',
        'title' => 'Custom Web App Development Services | Owlvix',
        'description' => 'Custom web apps, dashboards, client portals and SaaS products built with Laravel, React and Vue. Fixed-price quote within 24 hours, and you own the code.',
        'form_title' => 'Get a free web app quote',
        'h1' => 'Custom web app development, built around how your business works',
        'lead' => 'Dashboards, client portals, SaaS products and booking systems — designed, built and launched by one team. You get a fixed-price quote within one business day, see progress every week, and own 100% of the code.',
        'checks' => [
            'Design, frontend, backend and database in one place',
            'Weekly progress on a live preview link',
            'Fixed price agreed before we start',
            'Full source code and accounts handed over',
        ],
        'definition' => [
            'heading' => 'What is a custom web app?',
            'text' => 'A custom web app is software that runs in the browser and is built for one business\'s exact workflow, instead of an off-the-shelf tool you have to work around. It usually has user accounts, a database, an admin area and connections to other services such as payments or email. Customers, staff or partners use it from any device without installing anything.',
        ],
        'types' => [
            'heading' => 'Web apps we build',
            'items' => [
                ['Dashboards and admin panels', 'One place to see your numbers, manage orders, users and content, with charts and exports instead of spreadsheets.'],
                ['Client and partner portals', 'Secure areas where customers log in to see projects, invoices, documents or support tickets.'],
                ['SaaS products and MVPs', 'Subscription software with sign-up, plans, payments and onboarding — from a first version you can test with real users to a full product.'],
                ['Booking and scheduling systems', 'Online booking with availability, payments, reminders and a calendar for your team.'],
                ['Internal business tools', 'Replace spreadsheets and manual steps with a tool that fits how your team actually works: approvals, inventory, quotes, job tracking.'],
                ['Marketplaces and directories', 'Listings, search and filters, profiles, messaging and payments between buyers and sellers.'],
            ],
        ],
        'how' => [
            'heading' => 'How we build your web app',
            'paragraphs' => [
                'Every project starts with understanding the job the app has to do: who uses it, what they need to get done, and which steps or systems it replaces. We turn that into a short plan with screens, features and a fixed price, so you know exactly what you are getting before any code is written.',
                'We then design the main screens and build the app in small steps. Every week you can open a private preview link, click through what is finished and ask for changes early, when they are quick and cheap to make — not at the end.',
                'We build on proven open-source tools, mostly Laravel on the backend and React or Vue on the frontend, so any good developer can carry on the work later. When the app is tested and live, you receive the full source code, the database and every account in your name.',
            ],
        ],
        'gets' => [
            'heading' => 'What\'s included',
            'items' => [
                'Planning of features, screens and user roles',
                'UI design for desktop and mobile',
                'Frontend, backend, database and admin panel',
                'Logins, roles, payments and email notifications',
                'Connections to the tools you already use',
                'Testing on real devices and browsers',
                'Deployment to your server or cloud',
                'Full source code, plus free bug fixes after launch',
            ],
        ],
        'steps' => [
            ['Share your idea', 'Tell us what the app should do and who will use it. Within one business day you get questions, a plan and a fixed-price quote.'],
            ['Design and build', 'We design the key screens first, then build in small steps you can try every week on a private preview link.'],
            ['Launch and hand over', 'We test, deploy and hand over the code and accounts. Bug fixes after launch are free, and a monthly plan is there if you want ongoing work.'],
        ],
        'tech' => ['Laravel', 'PHP', 'React', 'Next.js', 'Vue', 'Node.js', 'Python', 'MySQL', 'PostgreSQL', 'Stripe', 'REST APIs'],
        'cost' => [
            'heading' => 'How much does a custom web app cost?',
            'text' => 'The cost of a web app depends on how many screens and user roles it has, how complex its rules are (for example pricing, approvals or scheduling), which systems it connects to, and whether you need payments, reports or a mobile version. A focused first version with the essential features costs far less than a full product, and is often the smartest start: launch, learn from real users, then add more. Most web apps take 3 to 8 weeks. Send us your idea and you get a fixed price, free, within one business day — or hire us monthly from $100 for ongoing development.',
        ],
        'faq' => [
            ['How long does it take to build a web app?', 'Most web apps take 3 to 8 weeks, depending on the number of features and integrations. Your quote includes a timeline with dates for each stage.'],
            ['Can you build a first version (MVP) quickly?', 'Yes. We help you choose the few features that prove your idea, launch those first, and add more once real users have tried it.'],
            ['Which technology do you use?', 'Mostly Laravel (PHP) for the backend and React, Next.js or Vue for the frontend, with MySQL or PostgreSQL. These are popular, well-supported tools, so you are never locked in to us.'],
            ['Can you take over a web app another developer started?', 'Yes. We review the existing code first, tell you honestly what state it is in, then fix, finish or extend it.'],
            ['Will the app work on phones?', 'Yes. Every web app we build is responsive, so it works in mobile browsers. If you also need an app in the App Store or Google Play, we can build that too.'],
            ['Do I own the code?', 'Yes. Once the project is paid, you get the full source code, the database and every account in your name.'],
        ],
        'related' => ['mobile-app-development', 'api-integration-services'],
    ],

    'mobile-app-development' => [
        'name' => 'Mobile App Development',
        'service' => 'Mobile app',
        'category' => 'mobile',
        'title' => 'iOS & Android Mobile App Development Services | Owlvix',
        'description' => 'iPhone and Android apps from one shared codebase, connected to your website or systems and published to both app stores. Free fixed-price quote in 24 hours.',
        'form_title' => 'Get a free mobile app quote',
        'h1' => 'Mobile app development for iPhone and Android',
        'lead' => 'We build iOS and Android apps from one shared codebase, so you launch on both stores for close to the price of one. Your app connects to your website, database or existing systems, and we handle publishing to the App Store and Google Play.',
        'checks' => [
            'iPhone and Android from one codebase',
            'Push notifications, logins and payments',
            'Publishing to the App Store and Google Play',
            'Updates and fixes after launch',
        ],
        'definition' => [
            'heading' => 'What is cross-platform app development?',
            'text' => 'Cross-platform app development means writing one app that runs natively on both iPhone and Android, instead of building and maintaining two separate apps. With frameworks such as React Native and Flutter, most of the code is shared, so new features reach both platforms at the same time and the app costs less to build and update, while still feeling like a normal app to your users.',
        ],
        'types' => [
            'heading' => 'Apps we build',
            'items' => [
                ['Customer apps', 'Let customers order, book, pay, track deliveries or manage their account from their phone, with push notifications to bring them back.'],
                ['Companion apps for your web app', 'A mobile version of your existing platform or SaaS, using the same accounts and data.'],
                ['Booking and service apps', 'Appointments, reminders and payments for salons, clinics, tutors, gyms and other service businesses.'],
                ['Staff and field apps', 'Checklists, job lists, photos, signatures and time tracking for teams working on the move — even with a weak signal.'],
                ['E-commerce apps', 'A shopping app connected to your Shopify, WooCommerce or custom store, with saved carts and order tracking.'],
                ['Startup MVPs', 'A first version of your app idea that you can put in front of real users and investors quickly.'],
            ],
        ],
        'how' => [
            'heading' => 'How we build your mobile app',
            'paragraphs' => [
                'We start by agreeing who the app is for and the main things they need to do in it. From that we sketch the key screens and write a fixed-price plan, so you know the scope, cost and timeline before development starts.',
                'We build with React Native and Expo (or Flutter where it suits better), which lets one codebase run on both iPhone and Android. During development you can install test builds on your own phone and try every new feature as it is finished.',
                'When the app is ready we prepare the store listings, screenshots and privacy details, and submit it to the App Store and Google Play. After launch we can send updates quickly, and you keep the code and the developer accounts in your name.',
            ],
        ],
        'gets' => [
            'heading' => 'What\'s included',
            'items' => [
                'App planning and screen design',
                'One codebase for iPhone and Android',
                'Logins, push notifications and in-app payments',
                'Connection to your website, database or API',
                'An admin panel to manage content and users',
                'Testing on real iPhone and Android devices',
                'App Store and Google Play submission',
                'Full source code, plus free bug fixes after launch',
            ],
        ],
        'steps' => [
            ['Tell us your app idea', 'Describe what users should be able to do. Within one business day you get questions, a plan and a fixed-price quote.'],
            ['Design, build and test', 'We design the main screens, then build the app. You install test versions on your phone and tell us what to change.'],
            ['Launch on both stores', 'We publish to the App Store and Google Play, help with the review process, and fix any bugs after launch for free.'],
        ],
        'tech' => ['React Native', 'Expo', 'Flutter', 'TypeScript', 'Firebase', 'Push notifications', 'Stripe', 'Laravel API', 'REST APIs'],
        'cost' => [
            'heading' => 'How much does a mobile app cost?',
            'text' => 'App cost depends mostly on the number of screens and features, whether users need accounts and payments, how much data it syncs with your systems, and whether you need a new backend or already have one. Building one cross-platform app instead of two separate native apps keeps both the first build and every later update cheaper. Apple and Google also charge their own developer account fees, which you pay directly. Send us your idea and you get a fixed price, free, within one business day.',
        ],
        'faq' => [
            ['How long does it take to build a mobile app?', 'It depends on the features; your quote includes a timeline with dates for each stage. Allow a few extra days at the end for Apple and Google to review the app before it goes live.'],
            ['Do I need separate apps for iPhone and Android?', 'No. We build one app with React Native or Flutter that runs on both, so you pay for one codebase and both platforms get updates at the same time.'],
            ['Can you connect the app to my existing website or system?', 'Yes. The app can use the same accounts and data as your website, store or software through an API. If you don\'t have one, we can build it.'],
            ['Will you publish the app to the stores?', 'Yes. We prepare the listings and submit to the App Store and Google Play under your own developer accounts, and help if a reviewer asks for changes.'],
            ['Can you update or fix an existing app?', 'Yes. We regularly take over apps that other developers started, fix problems and add new features.'],
            ['Do I own the app?', 'Yes. The source code, the store listings and the developer accounts are all yours.'],
        ],
        'related' => ['web-app-development', 'hosting-and-support'],
    ],

    'ai-agent-development' => [
        'name' => 'AI Agent Development',
        'service' => 'AI chatbot / agent',
        'category' => 'ai',
        'title' => 'AI Agent Development & Business Automation | Owlvix',
        'description' => 'AI agents that sort emails, enter data, write reports and update your CRM — following your rules, with human approval where it matters. Free quote in 24h.',
        'form_title' => 'Get a free AI automation quote',
        'h1' => 'AI agent development and automation for repetitive business work',
        'lead' => 'We build AI agents that take repetitive work off your team: sorting emails, entering data, writing reports and updating your CRM. They follow your rules, ask for approval where it matters and report back on everything they did.',
        'checks' => [
            'Works with the tools you already use',
            'Your rules, with human approval where needed',
            'Clear logs of every action',
            'Built on OpenAI, Claude or Gemini',
        ],
        'definition' => [
            'heading' => 'What is an AI agent?',
            'text' => 'An AI agent is software that uses a large language model to complete a task with several steps, not just answer a question. It can read an email or document, decide what to do using rules you set, and take actions in other tools — for example creating a CRM record, drafting a reply or updating a spreadsheet — then report what it did. Unlike a chatbot, an agent works in the background on your workflows.',
        ],
        'types' => [
            'heading' => 'Work we automate',
            'items' => [
                ['Email and inbox triage', 'Reads incoming emails, sorts and labels them, drafts replies for common requests and forwards the rest to the right person.'],
                ['Data entry from documents', 'Pulls details out of invoices, forms, orders or PDFs and enters them into your software, flagging anything unclear.'],
                ['Lead research and CRM updates', 'Researches new leads, fills in missing details, scores them and keeps your CRM up to date.'],
                ['Reports and summaries', 'Collects numbers and notes from different tools and writes daily or weekly summaries for your team.'],
                ['Content and listings', 'Drafts product descriptions, social posts or listings in your style for a person to approve.'],
                ['Customer follow-ups', 'Sends timely reminders and follow-ups based on what happened in your CRM, shop or booking system.'],
            ],
        ],
        'how' => [
            'heading' => 'How we build an AI agent',
            'paragraphs' => [
                'We start by mapping one workflow your team repeats every day or week: where the work comes in, the decisions people make, and where the result goes. We pick the steps that are safe and worthwhile to automate first, and agree the rules the agent must follow.',
                'Then we connect the agent to your tools through their APIs or automation platforms such as n8n or Zapier, and give it the instructions and examples it needs. For anything important — sending money, emailing customers, deleting data — the agent prepares the action and waits for a person to approve it.',
                'Every action is logged, so you can see what the agent did and why. We run it alongside your team at first, measure how well it does, and only widen what it handles once you trust the results.',
            ],
        ],
        'gets' => [
            'heading' => 'What\'s included',
            'items' => [
                'A map of the workflow and what to automate first',
                'Agent instructions, rules and examples',
                'Connections to your email, CRM, sheets or software',
                'Approval steps for sensitive actions',
                'Logs and a simple dashboard of what it did',
                'Testing on real past cases before going live',
                'A trial period alongside your team',
                'Full source code and accounts handed over',
            ],
        ],
        'steps' => [
            ['Describe the task', 'Tell us which work takes up your team\'s time. Within one business day you get questions, a plan and a fixed-price quote.'],
            ['We build and test it', 'We build the agent, test it on real past examples and show you the results before it touches live work.'],
            ['Go live safely', 'It starts with approvals switched on, then takes on more as it proves itself. Bug fixes after launch are free.'],
        ],
        'tech' => ['OpenAI', 'Anthropic Claude', 'Google Gemini', 'n8n', 'Zapier', 'Python', 'Laravel', 'Google Workspace', 'Microsoft 365', 'HubSpot'],
        'cost' => [
            'heading' => 'How much does AI automation cost?',
            'text' => 'The cost depends on how many steps the workflow has, how many tools the agent connects to, how varied the incoming work is, and how much checking and approval it needs. Starting with one well-defined task is usually the fastest way to see a return, and it keeps the first project small. AI model usage is billed by the provider based on how much the agent does, which is usually a small monthly cost. Tell us the task and we will send a fixed price, free, within one business day.',
        ],
        'faq' => [
            ['What is the difference between an AI agent and a chatbot?', 'A chatbot talks with people and answers their questions. An AI agent works in the background on a task, such as processing emails or updating your CRM, and takes actions in your tools.'],
            ['Can the agent make mistakes?', 'Like people, AI can get things wrong, so we design for it: clear rules, testing on real past cases, approval steps for important actions and logs of everything it does.'],
            ['Which tools can the agent work with?', 'Most tools with an API or an automation connector: email, Google Workspace, Microsoft 365, CRMs such as HubSpot or Salesforce, accounting software, spreadsheets and your own database.'],
            ['Is my business data safe?', 'We use the AI providers\' business APIs, which don\'t use your data to train their public models, give the agent access only to what it needs, and keep credentials in your own accounts.'],
            ['Do I need to change the software I use?', 'No. The agent works with the tools your team already uses, so nobody has to learn a new system.'],
            ['How long does it take?', 'A single, well-defined workflow is often the quickest project to deliver. Your quote includes a timeline with dates for each stage.'],
        ],
        'related' => ['ai-chatbot-development', 'api-integration-services'],
    ],

    'api-integration-services' => [
        'name' => 'API Integration Services',
        'service' => 'Something else',
        'category' => null,
        'title' => 'API Integration Services: AI, Payments & CRM | Owlvix',
        'description' => 'Connect OpenAI, Claude, Gemini, Stripe, PayPal, CRMs and ERPs to the tools you already use, so data flows automatically. Free fixed-price quote in 24 hours.',
        'form_title' => 'Get a free integration quote',
        'h1' => 'API integration services for AI, payments, CRMs and ERPs',
        'lead' => 'We connect the software your business runs on, so data moves between systems automatically instead of being copied by hand. AI models, payments, CRMs, ERPs, shops and your own apps — reliable, secure and documented.',
        'checks' => [
            'OpenAI, Claude and Gemini in your own apps',
            'Stripe, PayPal, HubSpot, Salesforce and more',
            'Error alerts and automatic retries',
            'Documented so any developer can maintain it',
        ],
        'definition' => [
            'heading' => 'What is an API integration?',
            'text' => 'An API integration is a connection that lets two pieces of software exchange data automatically through their application programming interfaces (APIs). For example, when a customer pays in your shop, an integration can create the invoice in your accounting software and update the contact in your CRM — without anyone copying the details by hand.',
        ],
        'types' => [
            'heading' => 'Integrations we build',
            'items' => [
                ['AI model integration', 'Add OpenAI, Anthropic Claude or Google Gemini features to your existing website, app or internal tools.'],
                ['Payments and subscriptions', 'Stripe and PayPal checkouts, subscriptions, invoices and webhooks that keep your records in sync.'],
                ['CRM and marketing tools', 'Send leads and customer activity to HubSpot, Salesforce, Mailchimp and similar tools automatically.'],
                ['ERP, accounting and inventory', 'Keep orders, stock and invoices in sync between your shop or app and your back-office systems.'],
                ['Custom APIs for your app', 'Build a secure API so your mobile app, partners or other systems can use your data.'],
                ['Data sync and webhooks', 'Scheduled syncs and real-time webhooks between any two systems that offer an API.'],
            ],
        ],
        'how' => [
            'heading' => 'How we build reliable integrations',
            'paragraphs' => [
                'First we map what should happen: which system is the source of truth, what triggers a sync, which fields go where, and what should happen when something fails. We check both systems\' API documentation and limits, so there are no surprises later.',
                'We then build the connection with proper authentication, validation and logging. Integrations fail in real life — a service is down, a token expires, data arrives in the wrong format — so we add automatic retries and alerts, and nothing is lost quietly.',
                'Before going live we test with real examples in a safe environment. You get written documentation of how the integration works and where its settings live, so any developer can maintain it later.',
            ],
        ],
        'gets' => [
            'heading' => 'What\'s included',
            'items' => [
                'A map of the data flow and field mapping',
                'Secure authentication and key storage',
                'Real-time webhooks or scheduled syncs',
                'Error handling, retries and alerts',
                'Logs of every sync',
                'Testing with real examples',
                'Written documentation',
                'Full source code handed over to you',
            ],
        ],
        'steps' => [
            ['Tell us what to connect', 'List the systems and what should happen between them. Within one business day you get questions, a plan and a fixed-price quote.'],
            ['We build and test', 'We build the integration and test it with real examples in a safe environment before it touches live data.'],
            ['Go live and monitor', 'We switch it on, watch the first syncs and set up alerts. Bug fixes after launch are free.'],
        ],
        'tech' => ['REST', 'GraphQL', 'Webhooks', 'OAuth', 'OpenAI', 'Claude', 'Gemini', 'Stripe', 'PayPal', 'HubSpot', 'Salesforce', 'Laravel', 'Node.js', 'Python'],
        'cost' => [
            'heading' => 'How much does an API integration cost?',
            'text' => 'The cost depends on how many systems are connected, how good their APIs and documentation are, how much data needs mapping or cleaning, and whether the sync must be real-time. A one-way connection between two well-documented services is a small job; two-way syncs between several systems take more planning and testing. Tell us what you want to connect and we will send a fixed price, free, within one business day.',
        ],
        'faq' => [
            ['Which systems can you integrate?', 'Almost any system that offers an API or webhooks: AI models, payment providers, CRMs, ERPs, accounting software, shops, marketing tools and your own database.'],
            ['What if a system doesn\'t have an API?', 'There are often other options, such as file exports, email parsing or an automation platform. We will tell you honestly what is reliable.'],
            ['What happens if an integration fails?', 'We build in automatic retries and alerts, and log every sync, so problems are noticed and fixed instead of silently losing data.'],
            ['Can you add AI to my existing app?', 'Yes. We can add OpenAI, Claude or Gemini features such as summaries, search, writing help or a chatbot to your current website or software.'],
            ['Is my data secure?', 'We use each provider\'s recommended authentication, keep keys in secure settings rather than in code, and give each connection only the access it needs.'],
            ['Do you maintain integrations after launch?', 'Yes. APIs change over time, so we offer ongoing support and updates, from $100 per month.'],
        ],
        'related' => ['ai-agent-development', 'hosting-and-support'],
    ],

    'hosting-and-support' => [
        'name' => 'Hosting & Support',
        'service' => 'Monthly developer',
        'category' => null,
        'title' => 'Website & App Hosting, Maintenance and Support | Owlvix',
        'description' => 'Hosting setup, deployment, monitoring, backups, security updates and fixes for your website or app, from $100 a month. You are never left on your own.',
        'form_title' => 'Get a free support quote',
        'h1' => 'Hosting, maintenance and support for your website or app',
        'lead' => 'We set up hosting, deploy your website or app, keep it updated and secure, and fix problems fast — so you are never left on your own after launch. Ongoing support starts from $100 a month, and you can cancel any time.',
        'checks' => [
            'Hosting, domain, email and SSL setup',
            'Monitoring, backups and security updates',
            'Bug fixes and small changes every month',
            'From $100 a month, cancel any time',
        ],
        'definition' => [
            'heading' => 'What is website and app maintenance?',
            'text' => 'Website and app maintenance is the ongoing work that keeps software online, secure and working after it launches: applying security updates, checking backups, watching for errors and downtime, renewing certificates, and fixing bugs or making small improvements as your business changes. Without it, even well-built software slowly becomes slower, less secure and harder to change.',
        ],
        'types' => [
            'heading' => 'What we look after',
            'items' => [
                ['Hosting setup and deployment', 'We choose and set up the right hosting, domain, business email and SSL certificate, or deploy to the server you already have.'],
                ['Monitoring and uptime', 'We watch for downtime and errors, so problems are spotted and fixed early — often before your customers notice.'],
                ['Backups and recovery', 'Regular automatic backups of your files and database, checked to make sure they can actually be restored.'],
                ['Security and updates', 'Framework, plugin and server updates, security patches and certificate renewals, applied carefully and tested.'],
                ['Fixes and small changes', 'Bug fixes, content changes and small new features handled by a developer who already knows your project.'],
                ['Taking over existing projects', 'Your previous developer is gone? We review the code and hosting, fix urgent problems and take it from there.'],
            ],
        ],
        'how' => [
            'heading' => 'How our support works',
            'paragraphs' => [
                'We start with a check of your website or app and its hosting: the code, the server, backups, security settings and anything that looks fragile. You get a short list of what is fine, what needs attention and what we recommend fixing first.',
                'Then we set up monitoring and backups and take care of updates on a regular schedule. When you need something — a bug fix, a change or a new small feature — you send a message and a developer who already knows your project picks it up, with priority replies on the monthly plan.',
                'You can work with us project by project, or hire us monthly from $100 for ongoing web, mobile, AI and support work. There is no long contract, and all accounts stay in your name.',
            ],
        ],
        'gets' => [
            'heading' => 'What\'s included',
            'items' => [
                'Hosting, domain, email and SSL setup',
                'Deployment of your website or app',
                'Uptime and error monitoring',
                'Automatic backups that are tested',
                'Security, framework and plugin updates',
                'Bug fixes and small changes',
                'Priority replies on the monthly plan',
                'All accounts kept in your name',
            ],
        ],
        'steps' => [
            ['Tell us what you have', 'Send a link to your website or app and what you need. Within one business day you get a reply with a plan and a quote.'],
            ['We check and secure it', 'We review the code and hosting, set up monitoring and backups, and fix anything urgent.'],
            ['Ongoing care', 'Updates, fixes and changes every month, with priority replies. Cancel any time.'],
        ],
        'tech' => ['AWS', 'DigitalOcean', 'Cloudflare', 'cPanel', 'LiteSpeed', 'Laravel', 'WordPress', 'Shopify', 'Uptime monitoring', 'Automated backups'],
        'cost' => [
            'heading' => 'How much does website and app support cost?',
            'text' => 'Our monthly developer plan starts from $100 a month and covers ongoing updates, fixes and changes across web, mobile, AI and support, with priority replies — and you can cancel any time. The right plan depends on how large your website or app is, how often it changes and how quickly you need responses. Hosting itself is paid directly to the hosting company, so you always know what you pay for. If you only need a one-off setup or fix, we will quote a fixed price instead.',
        ],
        'faq' => [
            ['What does the monthly plan cost?', 'It starts from $100 a month for ongoing updates, fixes and changes, with priority replies. You can cancel any time.'],
            ['Can you support a website or app someone else built?', 'Yes. We regularly take over projects from other developers. We review the code and hosting first and tell you honestly what we find.'],
            ['Which hosting do you recommend?', 'It depends on your project: shared hosting can suit a simple site, while busy apps need a cloud server such as DigitalOcean or AWS. We recommend what fits your needs and budget.'],
            ['Can you move my site to new hosting?', 'Yes. We can move your website, app, email and domain to new hosting with as little downtime as possible.'],
            ['How fast do you respond to problems?', 'Monthly plan clients get priority replies, and we reply to new enquiries within one business day.'],
            ['Who owns the hosting and accounts?', 'You do. Hosting, domains and all other accounts stay in your name; we only get the access we need to do the work.'],
        ],
        'related' => ['web-app-development', 'api-integration-services'],
    ],
];
