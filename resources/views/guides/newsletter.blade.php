{{-- Guide: how to make an email newsletter, with the email template builder. Page: guides/partials/guide. --}}
@extends('layouts.page')

@include('guides.partials.guide', ['g' => [
    'route' => 'guide.newsletter',
    'date' => '2026-11-01',
    'time' => 'PT1H',
    'title' => 'How to Make an Email Newsletter (Free) — Step by Step',
    'description' => 'Make an email newsletter in 7 steps: what to send, a subject line people open, a simple design, images that show, copying it into Mailchimp, Brevo or Klaviyo, and testing before you send.',
    'crumb' => 'Email template builder',
    'alt' => 'Owlvix owl writing an email newsletter',
    'h1' => 'How to make an email newsletter',
    'meta' => 'about 1 hour, free',
    'intro' => '<p>Email is still one of the best ways to bring customers back: your message lands in their inbox, not lost in a feed. A good newsletter is short, useful and easy to read on a phone. This guide walks you through writing and designing one, then sending it from your email platform, using the free <a href="'.e(route('email-builder')).'">Owlvix email template builder</a>.</p>',
    'cta' => [
        ['Want to follow along?', 'Open the email builder in another tab — no sign-up needed.', 'Open the email builder'],
        ['Ready to make your newsletter?', 'Free, no sign-up, no branding in your emails.', 'Design my newsletter'],
    ],
    'before' => [
        '<strong>An email platform</strong> such as Mailchimp, Brevo, Klaviyo or MailerLite — it holds your list and sends the email.',
        '<strong>One main thing to say</strong>: news, a new product, a tip or an offer.',
        '<strong>Your logo and one or two images</strong>, saved online (on your website or your email platform).',
        '<strong>Permission</strong>: only email people who agreed to hear from you.',
    ],
    'steps' => [
        ['Decide what to send', 'Pick one main message per email — a new product, a useful tip, behind-the-scenes news or an offer — and one action you want readers to take.',
            'a regular, simple rhythm (say, every two weeks) works better than long emails sent now and then.'],
        ['Write a subject line people open', 'Keep it short and specific: say what’s inside. “New autumn menu + 10% off this week” beats “Our latest newsletter”. Add a preview line that adds to it.'],
        ['Pick a template', 'Open the free email builder and choose a newsletter, sale, welcome, event or product launch template. Change the colours and fonts to match your brand.'],
        ['Write short and add one big button', 'A headline, two or three short paragraphs or blocks, and one clear button. Most people read email on a phone, so keep it easy to skim.'],
        ['Add images the right way', 'Emails can only show images that are online. Upload them to your email platform’s image library or your website and paste the address starting with https://. Google Drive and Dropbox links don’t work.',
            'add alt text to every image — some email apps hide images until the reader allows them.'],
        ['Copy it into your email platform', 'Choose your platform under Settings so the unsubscribe link is filled in, then press “Copy HTML”. In Mailchimp choose “Code your own” > “Paste in code”; in Brevo, “Paste your code”; in Klaviyo, “Import HTML”.'],
        ['Send yourself a test, then send', 'Send a test to yourself and open it on your phone and computer. Check every link and button, then send it — or schedule it for when your readers are most likely to open.'],
    ],
    'mistakes' => [
        ['Emailing people who didn’t sign up.', 'It’s against the rules in many countries and gets your emails marked as spam.'],
        ['One giant image.', 'Some email apps hide images, and spam filters dislike image-only emails. Use real text.'],
        ['Too many links and offers.', 'One main action gets more clicks than ten.'],
        ['No unsubscribe link.', 'It’s required by law in many places. Pick your platform in Settings so it’s added.'],
    ],
    'more' => '<p>Want help setting up email marketing — welcome series, automations and segments? <a href="'.e(route('service.api-integration-services')).'" data-quote>Talk to us about email marketing</a>.</p>',
    'faqTitle' => 'Email newsletters, explained',
    'faq' => [
        ['Is the email template builder free?', 'Yes, completely. No sign-up, no limits and no branding in your emails. Everything happens in your browser — your email is never uploaded to us.'],
        ['Can I send emails from the builder?', 'No — the builder makes the design. You send it with your email platform, which handles your list, unsubscribes and delivery.'],
        ['How often should I send a newsletter?', 'Often enough that people remember you, not so often that they unsubscribe. Every two weeks or once a month suits most small businesses.'],
        ['Will it look right in Outlook and on phones?', 'Yes. Templates are built the way email apps expect — tables and inline styles, 600 pixels wide, with columns that stack on phones. Always send yourself a test first.'],
        ['Why don’t my images show?', 'Images must be online at an address starting with https://. Upload them to your email platform or website. Google Drive and Dropbox links don’t work.'],
        ['How do I grow my email list?', 'Add a sign-up form to your website, offer something for joining (a discount or guide), and mention it on social media and at your counter.'],
    ],
]])
