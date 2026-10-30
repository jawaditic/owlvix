{{-- Privacy policy. Describes what the site actually collects (ContactController, SurveyController,
     TrackController, partials/analytics). Update it when adding a new form, tracker or service. --}}
@extends('layouts.page')

@section('title', 'Privacy Policy')
@section('description', 'How Owlvix collects, uses and protects the information you share through owlvix.com, including quote requests, analytics and cookies.')
@section('canonical', route('privacy'))

@section('content')
<section class="section">
    <div class="container container--narrow prose">
        <div class="page-owl">@include('partials.live-owl', ['width' => 120, 'alt' => 'Owlvix owl'])</div>
        <p class="eyebrow">Legal</p>
        <h1>Privacy Policy</h1>
        <p class="prose__updated">Last updated: 30 October 2026</p>

        <p>This policy explains what information Owlvix ("we", "us") collects when you use owlvix.com, why we collect it, and the choices you have. If you have any questions, email us at <a href="mailto:{{ config('mail.from.address') }}">{{ config('mail.from.address') }}</a>.</p>

        <h2>Information you give us</h2>
        <p>When you ask for a quote or send us a message, we collect:</p>
        <ul>
            <li>your name and email address;</li>
            <li>your phone number, if you choose to add it;</li>
            <li>the service you need, your budget range and your project description;</li>
            <li>the IP address the request came from, to help us block spam. It is deleted after 90 days.</li>
        </ul>
        <p>If you answer our short "before you go" survey, we keep your answer, any comment you write, the page you were on and your email address if you choose to give it.</p>
        <p>We use this information only to reply to you, prepare a quote and work on your project. We send you a confirmation email when your request arrives. We don't sell your information or add you to a mailing list.</p>

        <h2>Free landing page builder</h2>
        <p>The <a href="{{ route('landing-builder') }}">landing page builder</a> (and the website, portfolio, link in bio and coming soon builders, which use the same editor) runs in your browser, and your drafts stay on your device. If you sign in to publish a page or add a contact form:</p>
        <ul>
            <li>we keep your email address, which you use to sign in with a one-time code;</li>
            <li>we store the pages you publish and show them at owlvix.com/p/…, until you unpublish them;</li>
            <li>messages sent through your page's contact form are emailed to you. We don't keep the messages, only a count of how many were sent;</li>
            <li>we may email you about your pages, for example if one is reported or taken down, or to offer help with your website.</li>
        </ul>
        <p>If you build a funnel with a sign-up (lead) form:</p>
        <ul>
            <li>the name, email address and phone number that visitors enter in the form are stored on owlvix.com for you, the funnel owner, and emailed to you. You can see and download them as a file in the builder. We keep them while the funnel is published, and delete them about 90 days after it is unpublished or removed;</li>
            <li>if you connect an email tool (Mailchimp, Brevo or your own webhook), each new sign-up is also sent there. Your API keys are stored encrypted;</li>
            <li>we count how many times each funnel page is viewed and how many sign-ups it gets, per day. These are totals only: no cookies are used and no IP addresses are stored. The counts are deleted after about 13 months.</li>
        </ul>
        <p>As a funnel owner you are responsible for telling your visitors how you use their details, and for having their permission to email them.</p>
        <p>Published pages are public and are not listed in search engines. We may remove any page that breaks the law or is used for spam, scams or phishing. Anyone can report a page with the "Report" link on it.</p>

        <h2>Information collected automatically</h2>
        <ul>
            <li><strong>Visitor statistics.</strong> Our own simple counter records which page was viewed, the site you came from, your country, device type and browser. It doesn't use cookies or store your IP address. Records are deleted after about 13 months.</li>
            <li><strong>Google Analytics</strong> measures how visitors use the site, for example which pages are viewed and when a quote form is sent.</li>
            <li><strong>Microsoft Clarity and Contentsquare</strong> record how pages are used (clicks, scrolling and mouse movement) so we can improve the design. What you type in our forms is hidden from these recordings.</li>
            <li><strong>Google reCAPTCHA</strong> checks that forms are sent by a person, not a bot. It loads only when you start filling in a form.</li>
            <li><strong>Country lookup.</strong> Your browser asks get.geojs.io for your country so the phone field can show the right dialling code.</li>
        </ul>
        <p>These services are run by other companies and have their own privacy policies.</p>

        <h2>Cookies and browser storage</h2>
        <ul>
            <li>A session cookie and a security token (XSRF-TOKEN) keep the forms working and protect them from misuse. They expire after two hours.</li>
            <li>Google Analytics, Microsoft Clarity and Contentsquare set their own cookies to tell visits apart.</li>
            <li>Your browser remembers your light/dark theme choice and whether you have already seen our popup, so we don't show it again. This stays on your device.</li>
        </ul>
        <p>You can block or delete cookies in your browser settings. The site still works without analytics cookies.</p>

        <h2>Who we share it with</h2>
        <p>We share your information only with the services that help us run the site: our web host, our email provider, and the analytics and anti-spam services listed above. We may also share it if the law requires us to.</p>

        <h2>How long we keep it</h2>
        <p>We keep enquiries and project messages for as long as we need them to reply to you, work with you and keep business records. Visitor statistics are deleted after about 13 months. You can ask us to delete your enquiry at any time.</p>

        <h2>Your rights</h2>
        <p>You can ask us to show you the information we hold about you, correct it, delete it, or stop using it. Email <a href="mailto:{{ config('mail.from.address') }}">{{ config('mail.from.address') }}</a> and we will reply within 30 days. Depending on where you live, you may also have the right to complain to your local data protection authority.</p>

        <h2>Security</h2>
        <p>The site uses HTTPS on every page, and only our team can see enquiries, through a password-protected dashboard.</p>

        <h2>Children</h2>
        <p>Our services are for businesses. We don't knowingly collect information from children under 16.</p>

        <h2>Changes to this policy</h2>
        <p>If we change this policy, we will update the date at the top of this page.</p>
    </div>
</section>
@endsection
