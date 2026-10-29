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
];
