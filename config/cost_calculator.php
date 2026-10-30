<?php

// Free project cost calculator (/project-cost-calculator). All prices in USD — ADJUST THESE TO YOUR OWN RATES.
// The estimate = (project base + chosen features) × design level × size; shown as a min–max range.
// "service" is the quote form option to preselect when the visitor asks for an exact quote.
return [
    'types' => [
        'website' => ['label' => 'Business website', 'icon' => 'globe', 'service' => 'Website / WordPress', 'min' => 300, 'max' => 900, 'weeks' => [1, 3], 'sizes' => true],
        'landing' => ['label' => 'Landing page', 'icon' => 'rocket', 'service' => 'Landing page', 'min' => 150, 'max' => 450, 'weeks' => [1, 1], 'sizes' => false],
        'shopify' => ['label' => 'Online store', 'icon' => 'cart', 'service' => 'Shopify store', 'min' => 400, 'max' => 1200, 'weeks' => [1, 3], 'sizes' => true],
        'webapp' => ['label' => 'Web app / SaaS', 'icon' => 'monitor', 'service' => 'Custom web app', 'min' => 1500, 'max' => 4000, 'weeks' => [3, 8], 'sizes' => true],
        'mobile' => ['label' => 'Mobile app', 'icon' => 'phone', 'service' => 'Mobile app (iOS & Android)', 'min' => 2000, 'max' => 5000, 'weeks' => [4, 10], 'sizes' => true],
        'chatbot' => ['label' => 'AI chatbot', 'icon' => 'chat', 'service' => 'AI chatbot', 'min' => 500, 'max' => 1500, 'weeks' => [1, 3], 'sizes' => false],
        'agent' => ['label' => 'AI agent / automation', 'icon' => 'sparkle', 'service' => 'AI agent / automation', 'min' => 800, 'max' => 2500, 'weeks' => [2, 5], 'sizes' => false],
        'erp' => ['label' => 'ERP / CRM system', 'icon' => 'database', 'service' => 'ERP / CRM system', 'min' => 2500, 'max' => 7000, 'weeks' => [5, 12], 'sizes' => true],
    ],

    // Size: number of pages / screens
    'sizes' => [
        'small' => ['label' => 'Small', 'hint' => 'up to 5 pages or screens', 'factor' => 1],
        'medium' => ['label' => 'Medium', 'hint' => '6–15 pages or screens', 'factor' => 1.5],
        'large' => ['label' => 'Large', 'hint' => '16+ pages or screens', 'factor' => 2.2],
    ],

    'design' => [
        'template' => ['label' => 'Template-based', 'hint' => 'Fast and affordable', 'factor' => 1],
        'custom' => ['label' => 'Custom design', 'hint' => 'Designed for your brand', 'factor' => 1.25],
        'premium' => ['label' => 'Premium design', 'hint' => 'Animations, illustrations', 'factor' => 1.5],
    ],

    // Add-on features: [label, min, max, extra weeks, which project types show it ('*' = all)]
    'features' => [
        'accounts' => ['User accounts & login', 200, 500, 1, ['website', 'shopify', 'webapp', 'mobile', 'erp']],
        'payments' => ['Online payments', 250, 600, 1, ['website', 'webapp', 'mobile', 'erp', 'landing']],
        'admin' => ['Admin panel / dashboard', 300, 800, 1, ['website', 'webapp', 'mobile', 'chatbot', 'agent']],
        'booking' => ['Bookings & scheduling', 300, 800, 1, ['website', 'webapp', 'mobile']],
        'blog' => ['Blog / editable content', 150, 400, 0, ['website', 'shopify', 'webapp', 'landing']],
        'multilang' => ['Multiple languages', 150, 450, 1, '*'],
        'ai' => ['AI features (chat, summaries, search)', 400, 1200, 1, ['website', 'shopify', 'webapp', 'mobile', 'erp']],
        'integrations' => ['Connect other tools (CRM, ERP, email…)', 200, 600, 1, '*'],
        'push' => ['Push notifications', 150, 350, 0, ['mobile', 'webapp']],
        'chat' => ['In-app chat / messaging', 400, 900, 1, ['webapp', 'mobile']],
        'maps' => ['Maps & location', 200, 500, 0, ['website', 'webapp', 'mobile']],
        'reports' => ['Reports & analytics', 300, 700, 1, ['webapp', 'mobile', 'erp', 'agent']],
        'channels' => ['WhatsApp / Slack channel', 200, 500, 0, ['chatbot', 'agent']],
        'seo' => ['SEO setup', 150, 400, 0, ['website', 'shopify', 'landing', 'webapp']],
    ],
];
