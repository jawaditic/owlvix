/* Starting templates for the mobile app builder (/mobile-app-builder, public/js/app-builder.js): a colour scheme and
   tabs full of blocks for common kinds of business. Loaded with the builder; the owner changes the words, adds their
   own pictures and contact details, and saves. Phone numbers and addresses here are placeholders. */
(() => {
    const none = { kind: '', value: '' };
    const tab = i => ({ kind: 'tab', value: i });
    const tel = v => ({ kind: 'tel', value: v });
    const url = v => ({ kind: 'url', value: v });
    const wa = v => ({ kind: 'whatsapp', value: v });
    const hero = (title, text, label = '', action = none) => ({ type: 'hero', title, text, image: null, button: { label, action } });
    const head = (text, align = 'left') => ({ type: 'heading', text, align });
    const para = (text, align = 'left') => ({ type: 'text', text, align });
    const btn = (label, action, style = 'solid') => ({ type: 'button', label, style, action });
    const list = (title, rows) => ({ type: 'list', title, items: rows.map(([t, x = '', p = '']) => ({ title: t, text: x, price: p, image: null, action: none })) });
    const cards = (title, rows, columns = 2) => ({ type: 'cards', title, columns, items: rows.map(([icon, t, x = '', action = none]) => ({ icon, title: t, text: x, action })) });
    const reviews = (title, rows) => ({ type: 'reviews', title, items: rows.map(([text, name, stars = 5]) => ({ text, name, stars })) });
    const faq = (title, rows) => ({ type: 'faq', title, items: rows.map(([q, a]) => ({ q, a })) });
    const products = (title, rows) => ({ type: 'products', title, items: rows.map(([name, price, category = '', text = '']) => ({ name, price, category, text, image: null })) });
    const booking = (title, text, services, days, from, to, slot) => ({ type: 'booking', title, text, services: services.map(([name, info = '']) => ({ name, info })), days, from, to, slot, ahead: 30 });
    const notice = (text, tone = 'info') => ({ type: 'notice', text, tone });
    const contact = (o = {}) => ({ type: 'contact', title: 'Get in touch', phone: '+1 555 0100', whatsapp: '', email: 'hello@example.com', address: '12 High Street, Your Town', website: '', ...o });
    const hours = (rows, title = 'Opening hours') => ({ type: 'hours', title, rows: rows.map(([day, time]) => ({ day, time })) });
    const social = (links, title = 'Follow us') => ({ type: 'social', title, links });
    const space = (size = 'small') => ({ type: 'spacer', size });
    const screen = (label, icon, blocks) => ({ label, icon, type: 'screen', path: '', blocks });
    const weekHours = [['Mon – Fri', '9:00 – 18:00'], ['Saturday', '10:00 – 16:00'], ['Sunday', 'Closed']];

    window.OwlAppTemplates = [
        {
            id: 'restaurant', name: 'Restaurant', note: 'Menu, offers & bookings', sample: 'Olive & Ember',
            colors: { primary: '#e8590c', bar: '#ffffff', icon: '#e8590c', splash: '#fff7f0' },
            tabs: [
                screen('Home', 'home', [
                    hero('Wood-fired kitchen', 'Seasonal plates, fresh pasta and the best pizza in town.', 'See the menu', tab(1)),
                    notice('🔥 Tuesday: 2-for-1 pizzas, all evening.', 'warning'),
                    cards('Why guests love us', [['food', 'Fresh daily', 'Made from scratch every morning.'], ['heart', 'Family run', 'Since 2009.'], ['star', '4.8 rating', 'Over 900 reviews.'], ['cart', 'Takeaway', 'Order ahead and collect.']]),
                    btn('Book a table', tel('+1 555 0100')),
                    reviews('What guests say', [['The best margherita outside Naples. Staff are lovely too.', 'Sophie R.'], ['Cosy, quick and great value. We come every week!', 'Daniel K.']]),
                ]),
                screen('Menu', 'food', [
                    list('Starters', [['Burrata', 'Heirloom tomato, basil oil', '$12'], ['Garlic flatbread', 'Rosemary, sea salt', '$7'], ['Calamari', 'Lemon aioli', '$13']]),
                    list('Pizza', [['Margherita', 'San Marzano, fior di latte', '$15'], ['Diavola', 'Spicy salami, chilli honey', '$18'], ['Funghi', 'Wild mushrooms, truffle oil', '$17']]),
                    list('Desserts', [['Tiramisu', '', '$8'], ['Affogato', '', '$6']]),
                ]),
                screen('Visit', 'location', [contact({ title: 'Find us' }), hours([['Mon – Thu', '12:00 – 22:00'], ['Fri – Sat', '12:00 – 23:00'], ['Sunday', '12:00 – 21:00']]), social({ instagram: 'https://instagram.com/yourrestaurant' })]),
            ],
        },
        {
            id: 'cafe', name: 'Coffee shop', note: 'Order ahead, treats & loyalty', sample: 'Bean There', shop: { currency: '$', whatsapp: '+1 555 0100', email: '', note: 'Pay when you collect.' },
            colors: { primary: '#7c4a2d', bar: '#fdf8f3', icon: '#3b2416', splash: '#fdf8f3' },
            tabs: [
                screen('Home', 'home', [
                    hero('Good coffee, slow mornings', 'Small-batch roasts and fresh bakes, every day from 7am.', 'Order ahead', tab(1)),
                    cards('', [['heart', 'Loyalty card', '10th coffee free.'], ['location', 'Two spots', 'Old Town & Riverside.']]),
                    para('We roast our beans in-house every week and bake everything on site. Bring your own cup for 20% off.'),
                    reviews('', [['Perfect flat white and the cinnamon buns are unreal.', 'Maya']]),
                ]),
                screen('Order', 'cart', [
                    notice('Order ahead — tap a drink, then send your order. Ready in about 10 minutes.', 'success'),
                    products('', [['Espresso', '3', 'Coffee'], ['Flat white', '4.20', 'Coffee', 'Double shot, silky milk'], ['Oat latte', '4.60', 'Coffee'], ['Cold brew', '4.80', 'Coffee', '18-hour steep'],
                        ['Cinnamon bun', '3.80', 'Bakery'], ['Almond croissant', '4.20', 'Bakery'], ['Banana bread', '3.90', 'Bakery', 'Gluten free']]),
                ]),
                screen('Find us', 'location', [contact({ title: 'Say hello' }), hours([['Mon – Fri', '7:00 – 17:00'], ['Sat – Sun', '8:00 – 16:00']]), social({ instagram: 'https://instagram.com/yourcafe', tiktok: 'https://tiktok.com/@yourcafe' })]),
            ],
        },
        {
            id: 'salon', name: 'Salon & beauty', note: 'Services, prices & bookings', sample: 'Bloom Studio', shop: { currency: '$', whatsapp: '+1 555 0100', email: '', note: '' },
            colors: { primary: '#db2777', bar: '#fff7fb', icon: '#db2777', splash: '#fff7fb' },
            tabs: [
                screen('Home', 'home', [
                    hero('Feel your best', 'Hair, nails and skin by an award-winning team.', 'Book now', tab(3)),
                    cards('Our services', [['star', 'Hair', 'Cuts, colour & styling', tab(1)], ['heart', 'Nails', 'Gel, acrylics, nail art', tab(1)], ['user', 'Skin', 'Facials & peels', tab(1)], ['tag', 'Offers', 'New client 20% off', tab(1)]]),
                    reviews('Client love', [['Best balayage I’ve ever had. I felt so looked after.', 'Amira'], ['Quick, friendly and my nails lasted three weeks!', 'Jess']]),
                ]),
                screen('Prices', 'tag', [
                    notice('New clients: 20% off your first visit 💖', 'success'),
                    list('Hair', [['Cut & finish', '', 'from $45'], ['Full colour', '', 'from $85'], ['Balayage', '', 'from $140']]),
                    list('Nails', [['Gel manicure', '', '$35'], ['Gel pedicure', '', '$45']]),
                    list('Skin', [['Signature facial', '60 minutes', '$70']]),
                ]),
                screen('Gallery', 'image', [para('Add pictures of your work here.', 'center'), { type: 'gallery', images: [], columns: 3 }]),
                screen('Book', 'calendar', [booking('Book your visit', 'Pick a time and we’ll confirm on WhatsApp.', [['Cut & finish', '45 min · from $45'], ['Full colour', '2 h · from $85'], ['Gel manicure', '45 min · $35'], ['Signature facial', '60 min · $70']], [2, 3, 4, 5, 6], '09:00', '18:00', 30),
                    contact({ title: 'Visit us' }), hours(weekHours), social({ instagram: 'https://instagram.com/yoursalon' })]),
            ],
        },
        {
            id: 'gym', name: 'Gym & fitness', note: 'Classes, plans & coaches', sample: 'Pulse Fitness',
            colors: { primary: '#84cc16', bar: '#0b0f19', icon: '#0b0f19', splash: '#0b0f19' },
            tabs: [
                screen('Home', 'home', [
                    hero('Stronger every week', 'Classes, coaching and open gym — first week free.', 'See classes', tab(1)),
                    cards('', [['calendar', '40+ classes', 'Every week'], ['user', 'Coaches', 'Certified & friendly'], ['heart', 'Open 24/7', 'Members app access'], ['star', 'No contract', 'Cancel any time']]),
                    btn('Start your free week', tel('+1 555 0100')),
                ]),
                screen('Classes', 'calendar', [
                    list('Weekly timetable', [['HIIT', 'Mon & Wed · 7:00', '45 min'], ['Spin', 'Tue & Thu · 18:30', '45 min'], ['Yoga flow', 'Sat · 9:00', '60 min'], ['Strength', 'Daily · 12:00', '50 min']]),
                ]),
                screen('Plans', 'tag', [
                    list('Memberships', [['Off-peak', 'Weekdays 10:00 – 16:00', '$29/mo'], ['Full access', 'All classes, 24/7', '$49/mo'], ['Personal training', '4 sessions a month', '$149/mo']]),
                    faq('Questions', [['Can I freeze my membership?', 'Yes — up to 2 months a year, free.'], ['Do you have showers?', 'Yes, with towels and lockers.']]),
                ]),
                screen('Contact', 'location', [contact({ title: 'Come and train' }), social({ instagram: 'https://instagram.com/yourgym', youtube: 'https://youtube.com/@yourgym' })]),
            ],
        },
        {
            id: 'shop', name: 'Shop & boutique', note: 'Products, orders & delivery', sample: 'Maison Noir', shop: { currency: '$', whatsapp: '+1 555 0100', email: '', note: 'We’ll confirm your order and delivery by WhatsApp.' },
            colors: { primary: '#111827', bar: '#ffffff', icon: '#111827', splash: '#ffffff' },
            tabs: [
                screen('Home', 'home', [
                    hero('New season, new you', 'Timeless pieces, made to last.', 'Shop new in', tab(1)),
                    notice('Free delivery on orders over $60', 'success'),
                    { type: 'slider', images: [] },
                    cards('', [['cart', 'Free returns', '30 days'], ['tag', 'Members save', '10% off always']]),
                ]),
                screen('Shop', 'store', [
                    products('New in', [['Linen shirt', '59', 'Clothing', 'Natural · S–XL'], ['Wide-leg trousers', '79', 'Clothing', 'Black · 6–16'], ['Leather tote', '120', 'Bags', 'Tan, full-grain leather'], ['Canvas weekender', '95', 'Bags']]),
                ]),
                screen('Help', 'info', [
                    faq('Questions', [['How long is delivery?', '2–4 working days. Next-day available.'], ['Can I return an item?', 'Yes, within 30 days, unworn with tags.'], ['Do you ship abroad?', 'We ship to 30 countries.']]),
                    contact({ title: 'Our store' }), social({ instagram: 'https://instagram.com/yourshop', pinterest: 'https://pinterest.com/yourshop' }),
                ]),
            ],
        },
        {
            id: 'clinic', name: 'Clinic & dental', note: 'Treatments, fees & appointments', sample: 'Bright Smile Dental', shop: { currency: '$', whatsapp: '+1 555 0100', email: '', note: '' },
            colors: { primary: '#0284c7', bar: '#ffffff', icon: '#0284c7', splash: '#f0f9ff' },
            tabs: [
                screen('Home', 'home', [
                    hero('Gentle care for the whole family', 'Same-week appointments and evening slots.', 'Book an appointment', tab(2)),
                    cards('Treatments', [['star', 'Check-ups', 'Exam & clean'], ['heart', 'Whitening', 'In-chair or at home'], ['user', 'Children', 'Free under 12s'], ['bell', 'Emergency', 'Same-day help']]),
                    reviews('Patient reviews', [['I used to dread the dentist. Not any more — so calm and kind.', 'Priya S.']]),
                ]),
                screen('Fees', 'tag', [list('Common treatments', [['Check-up & clean', '', '$65'], ['Filling', '', 'from $90'], ['Whitening', '', '$250']]), faq('Before your visit', [['Do I need to register?', 'No — just book and bring photo ID.'], ['Do you take insurance?', 'We accept most major plans.']])]),
                screen('Book', 'calendar', [notice('Dental emergency? Call us — we keep slots free every day.', 'warning'),
                    booking('Request an appointment', 'Choose a time; our reception confirms by WhatsApp within the hour.', [['Check-up & clean', '45 min'], ['Whitening consultation', '30 min'], ['Child check-up', '30 min']], [1, 2, 3, 4, 5, 6], '08:00', '19:00', 30),
                    contact({ title: 'Contact the clinic' }), hours([['Mon – Thu', '8:00 – 19:00'], ['Friday', '8:00 – 17:00'], ['Saturday', '9:00 – 13:00']])]),
            ],
        },
        {
            id: 'realestate', name: 'Real estate', note: 'Listings, valuations & agents', sample: 'Keystone Homes',
            colors: { primary: '#0f766e', bar: '#ffffff', icon: '#0f766e', splash: '#f0fdfa' },
            tabs: [
                screen('Home', 'home', [
                    hero('Find your next home', 'Local experts selling and letting homes for 20 years.', 'View listings', tab(1)),
                    cards('', [['search', 'Buy', 'Homes for sale', tab(1)], ['store', 'Rent', 'Homes to let', tab(1)], ['tag', 'Free valuation', 'In 24 hours', tab(2)], ['user', 'Our agents', 'Meet the team', tab(2)]]),
                ]),
                screen('Listings', 'search', [
                    list('Featured', [['3-bed family home', 'Maple Road · garden & parking', '$425,000'], ['2-bed apartment', 'City centre · balcony', '$1,450/mo'], ['4-bed detached', 'Oak Lane · new build', '$610,000']]),
                ]),
                screen('Contact', 'chat', [para('Thinking of selling? Ask for a free, no-obligation valuation.'), btn('Book a valuation', tel('+1 555 0100')), contact({ title: 'Our office' }), hours(weekHours)]),
            ],
        },
        {
            id: 'events', name: 'Events & weddings', note: 'Schedule, venue & RSVP', sample: 'Emma & James',
            colors: { primary: '#a16207', bar: '#fffdf7', icon: '#a16207', splash: '#fffdf7' },
            tabs: [
                screen('Home', 'heart', [
                    hero('We’re getting married!', 'Saturday 12 June · The Old Mill', 'The day', tab(1)),
                    para('We can’t wait to celebrate with you. Everything you need for the day is in this app.', 'center'),
                    btn('RSVP', { kind: 'email', value: 'hello@example.com' }),
                ]),
                screen('The day', 'calendar', [list('Schedule', [['Ceremony', '2:00 pm · The Garden'], ['Drinks & photos', '3:00 pm'], ['Dinner', '5:30 pm · The Barn'], ['Dancing', '8:00 pm till late']]), faq('Good to know', [['Is there parking?', 'Yes, free parking at the venue.'], ['Dress code?', 'Summer formal.'], ['Can I bring a plus one?', 'If it’s on your invite, yes!']])]),
                screen('Venue', 'location', [contact({ title: 'The Old Mill', phone: '', email: '', address: 'The Old Mill, Mill Lane, Your Town' }), { type: 'gallery', images: [], columns: 2 }]),
            ],
        },
        {
            id: 'agency', name: 'Agency & consultant', note: 'Services, work & contact', sample: 'Northwind Studio',
            colors: { primary: '#6366f1', bar: '#0f172a', icon: '#0f172a', splash: '#0f172a' },
            tabs: [
                screen('Home', 'home', [
                    hero('We help brands grow', 'Strategy, design and marketing for ambitious small businesses.', 'Our services', tab(1)),
                    cards('', [['star', '120+ clients', 'Since 2015'], ['heart', '4.9 / 5', 'Client rating']]),
                    reviews('Clients say', [['They doubled our leads in three months. Brilliant team.', 'Tom, Fieldhouse Co.'], ['Clear, fast and creative. Highly recommend.', 'Lena, Ivy Dental']]),
                ]),
                screen('Services', 'grid', [cards('What we do', [['search', 'SEO', 'Get found on Google'], ['image', 'Branding', 'Logos & identity'], ['globe', 'Websites', 'Fast & beautiful'], ['bell', 'Ads', 'Google & Meta ads']]), faq('FAQ', [['How much does it cost?', 'Projects start from $1,500. We’ll quote after a free call.'], ['How long does it take?', 'Most projects take 2–6 weeks.']])]),
                screen('Contact', 'chat', [btn('Book a free call', { kind: 'email', value: 'hello@example.com' }), contact({ title: 'Talk to us', address: '' }), social({ linkedin: 'https://linkedin.com/company/youragency', instagram: 'https://instagram.com/youragency' })]),
            ],
        },
        {
            id: 'church', name: 'Church & community', note: 'Services, events & giving', sample: 'Grace Community',
            colors: { primary: '#7c3aed', bar: '#ffffff', icon: '#7c3aed', splash: '#faf5ff' },
            tabs: [
                screen('Home', 'home', [
                    hero('Welcome home', 'Join us this Sunday at 10:30am — everyone is welcome.', 'Service times', tab(1)),
                    { type: 'video', url: '', title: 'Latest message' },
                    notice('Community lunch this Sunday after the service.', 'info'),
                ]),
                screen('Events', 'calendar', [list('This month', [['Sunday service', 'Every Sunday · 10:30'], ['Youth night', 'Fridays · 19:00'], ['Food bank', 'Saturdays · 10:00'], ['Prayer group', 'Wednesdays · 19:30']])]),
                screen('Visit', 'location', [contact({ title: 'Find us' }), social({ facebook: 'https://facebook.com/yourchurch', youtube: 'https://youtube.com/@yourchurch' })]),
            ],
        },
        {
            id: 'school', name: 'School & tutoring', note: 'Courses, fees & enrolment', sample: 'BrightPath Tutors',
            colors: { primary: '#2563eb', bar: '#ffffff', icon: '#2563eb', splash: '#eff6ff' },
            tabs: [
                screen('Home', 'home', [
                    hero('Learn with confidence', 'Small groups and 1-to-1 tutoring for ages 7–18.', 'See courses', tab(1)),
                    cards('', [['book', 'All subjects', 'Maths, English, Science'], ['user', 'Expert tutors', 'Qualified teachers'], ['calendar', 'Flexible', 'After school & weekends'], ['star', '95% pass', 'Exam success']]),
                    reviews('Parents say', [['My daughter went from a C to an A in maths. Thank you!', 'Mark, parent']]),
                ]),
                screen('Courses', 'book', [list('Courses', [['Maths booster', 'Ages 11–16 · weekly', '$25/hr'], ['GCSE English', 'Small group', '$20/hr'], ['Science lab club', 'Saturdays', '$18/hr']]), faq('FAQ', [['Is there a free trial?', 'Yes, your first lesson is free.'], ['Do you teach online?', 'Yes — online and in person.']])]),
                screen('Enrol', 'mail', [btn('Book a free trial lesson', tel('+1 555 0100')), contact({ title: 'Contact us' })]),
            ],
        },
        {
            id: 'hotel', name: 'Hotel & stays', note: 'Rooms, amenities & booking', sample: 'Seabreeze Hotel',
            colors: { primary: '#0e7490', bar: '#ffffff', icon: '#0e7490', splash: '#ecfeff' },
            tabs: [
                screen('Home', 'home', [
                    hero('Wake up by the sea', 'Boutique rooms, sea views and breakfast on the terrace.', 'Our rooms', tab(1)),
                    { type: 'slider', images: [] },
                    cards('Amenities', [['heart', 'Spa', 'Pool & sauna'], ['food', 'Restaurant', 'Breakfast included'], ['location', 'Beach', '2 minutes away'], ['star', 'Free Wi-Fi', 'Everywhere']]),
                ]),
                screen('Rooms', 'store', [list('Rooms', [['Classic double', 'City view · 18 m²', 'from $120'], ['Sea view double', 'Balcony · 22 m²', 'from $160'], ['Family suite', 'Sleeps 4', 'from $220']]), btn('Book direct & save 10%', tel('+1 555 0100'))]),
                screen('Info', 'info', [faq('Good to know', [['Check-in time?', 'From 3pm. Check-out by 11am.'], ['Is there parking?', 'Yes, $10 a night.'], ['Are pets welcome?', 'Dogs are welcome in selected rooms.']]), contact({ title: 'Reception' })]),
            ],
        },
    ];
})();
