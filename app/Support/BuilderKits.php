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
        'event' => 'event-website-builder',
        'menu' => 'menu-builder',
        'store' => 'store-builder',
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
                    ['Can I keep editing later?', 'Your work is kept in this browser automatically. Press “☁️ Save” to save it to your free account, then open it from “My sites” on any device.'],
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
            'event' => [
                'crumb' => 'Wedding & event website builder',
                'title' => 'Free Wedding & Event Website Builder — RSVP, Countdown & Invitation',
                'description' => 'Make a free wedding website or event page: invitation, countdown, schedule, venue map and an RSVP form. Guest replies are emailed to you and saved in a list.',
                'h1' => 'Your <span class="accent">wedding or event website</span>, with RSVPs',
                'lead' => 'Build a beautiful invitation page for a wedding, birthday, conference or baby shower — with a countdown, the plan for the day, directions and an RSVP form that collects your guest list for you.',
                'cta' => ['Planning something bigger?', 'We build event sites with ticket sales, seating plans, galleries and custom domains. Start free: we design the first version at no cost.'],
                'faq' => [
                    ['Is the wedding website builder free?', 'Yes. Build your page, publish it free on sites.owlvix.com or download it for your own domain. No payment and no watermark on your content.'],
                    ['How do RSVPs work?', 'Select the RSVP form and press “Switch on RSVPs” (free, sign in with your email). Every reply is emailed to you and saved in a list in the form builder, where you can download your guest list as a CSV file.'],
                    ['Can guests choose a meal or bring a plus one?', 'Yes. In the RSVP form’s Content tab you can ask how many guests are coming, offer meal choices and ask for a message.'],
                    ['How do I share it with guests?', 'Press Publish and send the link by WhatsApp, email or text — or print it on your invitations.'],
                    ['Can I change the date or add photos later?', 'Yes. Click any text or photo to change it, then publish again — the link stays the same.'],
                ],
            ],
            'menu' => [
                'crumb' => 'Restaurant menu & QR code builder',
                'title' => 'Free Restaurant Menu Maker with QR Code — Digital Menu Builder',
                'description' => 'Make a free digital menu for your restaurant, café or takeaway: prices, specials, opening hours and map — plus a QR code and printable table cards. Order buttons for WhatsApp.',
                'h1' => 'Your <span class="accent">menu online</span>, with a QR code for every table',
                'lead' => 'Pick a restaurant, café, pizza or food truck menu, change the dishes and prices, publish — then print QR table cards so guests can scan and see your menu on their phone.',
                'cta' => ['Want online ordering too?', 'We build restaurant websites and ordering apps with payments, delivery zones and a kitchen screen. Start free: we design the first version at no cost.'],
                'faq' => [
                    ['Is the menu maker really free?', 'Yes. Build your menu, publish it free on sites.owlvix.com and print as many QR codes as you like. No payment and no watermark on your menu.'],
                    ['How do I get the QR code?', 'Press Publish. The QR code appears straight away — download it as PNG or SVG, or print ready-made table cards (four to an A4 page). It also stays in the Site tab under “QR code”.'],
                    ['Do I need to reprint the QR code when prices change?', 'No. The QR code points to your menu’s web address, which stays the same. Change your prices, publish again, and every printed code shows the new menu.'],
                    ['Can customers order?', 'Yes — the buttons can open WhatsApp, a phone call or your own ordering link. Add your WhatsApp number in the button’s Content tab.'],
                    ['Can I add photos of dishes?', 'Yes. Add an Image, Gallery or Image box widget anywhere on the page and click it to upload a photo.'],
                ],
            ],
            'store' => [
                'crumb' => 'Online store builder',
                'title' => 'Free Online Store Builder — Sell Online with Cart & Checkout',
                'description' => 'Build a free online store: product pages, a shopping cart and checkout with card payments (Stripe), cash on delivery or bank transfer. Orders are emailed to you.',
                'h1' => 'Start selling with a <span class="accent">free online store</span>',
                'lead' => 'Pick a clothing, bakery, beauty or gift shop, add your products and prices, and switch on checkout. Customers add to cart and pay by card, cash on delivery or bank transfer — every order is emailed to you.',
                'cta' => ['Ready for a bigger store?', 'We build online stores with stock tracking, discount codes, shipping rules and your own domain. Start free: we design the first version at no cost.'],
                'faq' => [
                    ['Is the online store builder really free?', 'Yes. Build your shop, publish it free on sites.owlvix.com and take orders. Cash on delivery and bank transfer cost nothing. Card payments go through your own Stripe account, so Stripe’s normal card fee applies.'],
                    ['How do customers pay?', 'You choose: card payments with Stripe (money goes straight to your Stripe account), cash on delivery, bank transfer (we show your bank details after the order), or any mix of them.'],
                    ['Where do I see my orders?', 'Every order is emailed to you, and the Orders tab in the builder lists them all. Mark them as sent or completed, and download them as a CSV file. Customers get an email receipt too.'],
                    ['Can I charge for delivery?', 'Yes. Set a delivery fee and, if you like, free delivery over a certain amount. You can also offer pick-up from your shop.'],
                    ['Can I sell sizes or colours?', 'Yes. Give a product options such as “Size: S, M, L” and customers pick one before adding it to their cart.'],
                    ['Can my customers change the prices?', 'No. The prices are saved on our side when you publish, and checkout always charges those prices — whatever someone changes on the page.'],
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
