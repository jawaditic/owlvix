<?php

namespace App\Support;

/**
 * The extra free builders. Each one is the page builder editor (public/js/landing-builder.js) started in its own
 * mode, with its own example projects (KITS in public/js/builder-engine.js). This class holds each page's text.
 */
class BuilderKits
{
    public const ROUTES = [
        'website' => 'website-builder',
        'portfolio' => 'portfolio-builder',
        'links' => 'link-in-bio-builder',
        'soon' => 'coming-soon-builder',
    ];

    public static function get(string $kit): array
    {
        $kits = [
            'website' => [
                'crumb' => 'Website builder',
                'title' => 'Free Website Builder — Multi-Page Sites, No Code',
                'description' => 'Build a free multi-page website for your business: home, about, services and contact pages, menus, a working contact form. Download it or publish it free.',
                'h1' => 'Build a <span class="accent">full website</span> in an afternoon',
                'lead' => 'Start from a ready-made business website with Home, About, Services and Contact pages. Change the words and photos, add pages, then download it or publish it free.',
                'cta' => ['Want a site built for you?', 'Our designers build custom business websites with booking, payments and SEO. Start free: we design the first version at no cost.'],
                'faq' => [
                    ['Is the website builder really free?', 'Yes. Build a site with up to 10 pages, download it or publish it free on sites.owlvix.com. No payment and no watermark on your content.'],
                    ['How many pages can my website have?', 'Up to 10. Use “＋ Page” at the top and pick a ready page (About, Services, Pricing, Contact, Menu) or start from a blank one. Pages appear in your menu automatically.'],
                    ['Can I use my own domain?', 'Yes. Download the site and upload it to any hosting, then point your domain at it. Or ask us and we will set it up for you.'],
                    ['Does the contact form work?', 'Yes. Switch it on with your email (free) and every message a visitor sends is emailed to you — even on your own hosting.'],
                    ['Can I keep editing later?', 'Your work is saved in this browser automatically. Use “Save project” to download a project file and open it on any computer.'],
                ],
            ],
            'portfolio' => [
                'crumb' => 'Portfolio builder',
                'title' => 'Free Portfolio Website Builder — For Photographers, Designers & Freelancers',
                'description' => 'Make a free portfolio website: show your work in a gallery, add your story, prices and reviews, and get enquiries through a working contact form.',
                'h1' => 'Show your <span class="accent">best work</span> — free portfolio site',
                'lead' => 'Pick a portfolio for photographers, designers, developers or artists. Drop in your photos, write a few lines about you, and start getting enquiries.',
                'cta' => ['Want a portfolio that wins clients?', 'We design custom portfolio sites with fast galleries, booking and SEO so new clients find you. Start free: we design the first version at no cost.'],
                'faq' => [
                    ['Is the portfolio builder free?', 'Yes. Build your portfolio, download it or publish it free on sites.owlvix.com. No payment and no watermark on your work.'],
                    ['How do I add my photos?', 'Click any empty photo in the gallery and pick an image. Photos are resized and saved as WebP in your browser, so the page stays fast.'],
                    ['Can clients contact me?', 'Yes. Switch on the contact form with your email (free) and every enquiry is emailed to you.'],
                    ['Can I add more pages?', 'Yes. Add up to 10 pages — for example a page per project, prices or an about page.'],
                    ['Can I use my own domain?', 'Yes. Download the site and upload it to your hosting, or ask us to set it up for you.'],
                ],
            ],
            'links' => [
                'crumb' => 'Link in bio builder',
                'title' => 'Free Link in Bio Page Builder — A Linktree Alternative',
                'description' => 'Make a free link in bio page for Instagram, TikTok and YouTube: your photo, bio, link buttons and social icons. Your own design, no sign-up to start.',
                'h1' => 'One link for <span class="accent">everything you do</span>',
                'lead' => 'Make a free link in bio page for Instagram, TikTok or YouTube — your photo, a short bio, big link buttons and your socials. Design it your way and publish it in a minute.',
                'cta' => ['Ready for a full website?', 'When one page is not enough, we build fast websites and online stores for creators and small businesses. Start free: we design the first version at no cost.'],
                'faq' => [
                    ['What is a link in bio page?', 'Instagram and TikTok let you add only one link to your profile. A link in bio page puts all your links — shop, videos, booking, WhatsApp — on one simple page, so that one link can go everywhere.'],
                    ['Is it free?', 'Yes. Build it and publish it free on sites.owlvix.com, or download it and put it on your own domain. No payment and no watermark on your content.'],
                    ['How do I add my links?', 'Click a button, then in the Content tab choose where it goes — a web address, WhatsApp, phone or email. Buttons without a link are left off the published page.'],
                    ['Can I change the colours and fonts?', 'Yes. Use the Site tab for colours and fonts, or the Style tab on any button or text. Every part of the page can be styled.'],
                    ['How do I put it on Instagram or TikTok?', 'Press Publish, copy your page address and paste it into the website field of your profile.'],
                ],
            ],
            'soon' => [
                'crumb' => 'Coming soon page builder',
                'title' => 'Free Coming Soon Page Builder — Countdown & Waitlist',
                'description' => 'Make a free coming soon or under construction page with a countdown timer, waitlist form and social links. Publish it free or upload it to your domain.',
                'h1' => 'Launching soon? <span class="accent">Start collecting fans now</span>',
                'lead' => 'Make a coming soon, under construction or opening soon page with a countdown, a sign-up form and your social links — so people can find you before you launch.',
                'cta' => ['Need the full site for launch day?', 'We design and build websites and apps that are ready to sell on day one. Start free: we design the first version at no cost.'],
                'faq' => [
                    ['Is the coming soon page builder free?', 'Yes. Build it, publish it free on sites.owlvix.com or download it for your own domain. No payment and no watermark on your content.'],
                    ['How do people join my waitlist?', 'Switch on the form with your email (free). Every sign-up is emailed to you, wherever the page is hosted.'],
                    ['Can I change the countdown date?', 'Yes. Click the countdown and set the date and time in the Content tab. You can also set the text shown when it ends.'],
                    ['Can I put it on my own domain?', 'Yes. Download the page as index.html and upload it to your hosting while your full site is being built.'],
                    ['Can I turn it into a full website later?', 'Yes. Untick “Simple page” in the Site tab to show a header and menu, then add pages — it becomes a full website.'],
                ],
            ],
        ];

        return $kits[$kit];
    }
}
