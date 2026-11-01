{{-- Guide: how to build a sales funnel, with the funnel builder. Page: guides/partials/guide. --}}
@extends('layouts.page')

@include('guides.partials.guide', ['g' => [
    'route' => 'guide.sales-funnel',
    'date' => '2026-11-01',
    'time' => 'PT3H',
    'title' => 'How to Build a Sales Funnel (Free) — A Step-by-Step Guide',
    'description' => 'Build a sales funnel in 8 steps: a free offer, an opt-in page, a thank-you page, a sales page, payments with Stripe or PayPal, sending leads to your email tool and A/B testing.',
    'crumb' => 'Funnel builder',
    'alt' => 'Owlvix owl building a sales funnel',
    'h1' => 'How to build a sales funnel',
    'meta' => 'an afternoon, free',
    'intro' => '<p>A sales funnel is a short series of pages that walks visitors to one goal: first they give you their email for something free, then they see an offer, then they buy. Each page has one job and one next step, so more visitors take action than on a busy website. This guide shows you how to build one for free with the <a href="'.e(route('funnel-builder')).'">Owlvix funnel builder</a>.</p>',
    'cta' => [
        ['Want to follow along?', 'Open the funnel builder in another tab — no sign-up to start.', 'Open the funnel builder'],
        ['Ready to build your funnel?', 'Free leads, stats and A/B tests. Payments go straight to you.', 'Build my funnel'],
    ],
    'before' => [
        '<strong>One product or service</strong> you want to sell.',
        '<strong>A free offer</strong> your buyers would want: a guide, checklist, discount or free call.',
        '<strong>A payment link</strong> from Stripe, PayPal or Gumroad, if you sell online.',
        '<strong>Optional:</strong> a Mailchimp or Brevo account for your email list.',
    ],
    'steps' => [
        ['Pick one goal and one buyer', 'Decide what you want people to buy at the end and who it’s for. One funnel, one product, one type of buyer — that focus is what makes funnels work.'],
        ['Create your free offer', 'Make something small and useful that solves part of your buyer’s problem: a checklist, short guide, discount code or free consultation. It should lead naturally to what you sell.',
            'specific beats general — “7-day meal plan for busy parents” gets more sign-ups than “Healthy eating tips”.'],
        ['Build the opt-in page', 'Open the free funnel builder and start with the opt-in step. Write a headline that names the result, three bullet points on what they get, and a short form for their name and email. Nothing else — no menu, no extra links.'],
        ['Add the thank-you page with an offer', 'After sign-up, thank people, tell them where to find their freebie, and show a short offer — often a discount for the next 24 hours. This is where many first sales happen.'],
        ['Write your sales page', 'Explain the problem, show how your product solves it, add reviews, answer common worries in an FAQ, and repeat one clear “Buy” button down the page.'],
        ['Connect payments', 'Paste your Stripe Payment Link, PayPal or Gumroad link into the sales step. Buy buttons go there, and your payment provider sends buyers back to your thank-you step. Money goes straight to you.'],
        ['Send leads to your email list', 'Every sign-up is emailed to you and saved in the Results tab, where you can download a CSV. Connect Mailchimp, Brevo or a webhook (Zapier, Make, your CRM) to add them automatically, then send a short welcome series.',
            'a few emails over the first week — the freebie, a helpful tip, a customer story, then your offer — usually sells more than one hard sell.'],
        ['Test and improve', 'Turn on A/B testing for a step to try a second headline or offer. Visitors see A or B at random, and the Results tab shows which one wins — keep the winner with one click and test the next thing.'],
    ],
    'mistakes' => [
        ['Too many choices.', 'Each page should have one button. Menus and extra links leak visitors.'],
        ['A freebie nobody wants.', 'If it doesn’t solve a real problem, people won’t sign up.'],
        ['Testing everything at once.', 'Change one thing at a time so you know what made the difference.'],
        ['No follow-up.', 'Most people don’t buy on day one. Your emails do the selling.'],
    ],
    'more' => '<p>Want a custom funnel with your own checkout, membership area or CRM? <a href="'.e(route('service.landing-page-design')).'" data-quote>Talk to us about a funnel built for you</a>.</p>',
    'faqTitle' => 'Sales funnels, explained',
    'faq' => [
        ['What is a sales funnel?', 'A series of pages that walks visitors to one goal — for example a free guide sign-up, then a thank-you page with an offer, then a checkout. Each step has one job and one clear next step.'],
        ['Is the funnel builder free?', 'Yes. Build funnels, collect leads, see your stats and run A/B tests for free. A small “made with” link sits in the footer.'],
        ['Do I need a website to have a funnel?', 'No. A funnel works on its own — you send people to it from ads, social media or email. If you have a website, link to your funnel from it.'],
        ['How do I get people into my funnel?', 'Share your opt-in page on social media, in your bio, in ads, in your email signature and anywhere your buyers spend time.'],
        ['What’s a good opt-in rate?', 'It varies a lot by audience and offer. Many opt-in pages turn 20–40% of visitors into sign-ups; use A/B tests to improve yours.'],
        ['Does Owlvix take a cut of my sales?', 'No. Payments go through your own Stripe, PayPal or Gumroad account, straight to you — never through Owlvix.'],
    ],
]])
