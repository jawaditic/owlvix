/** The same words as the website's homepage (resources/views/home.blade.php). Update both together. */

export const Hero = {
  pill: '7+ years • 200+ projects delivered',
  words: ['web app?', 'mobile app?', 'AI chatbot?', 'AI agent?'],
  lead: 'Owlvix designs, builds and launches web apps, mobile apps and AI tools for growing businesses — from the first sketch to the server it runs on.',
  owl: "Hoo needs a developer? Tell me your idea and I'll show you how we'd build it.",
  trust: ['Free quote within 24h', 'You own 100% of the code'],
};

export const Strip = [
  { value: '200+', label: 'projects shipped' },
  { value: '7+', label: 'years building' },
  { value: '100%', label: 'source code is yours' },
  { value: '24h', label: 'reply to new enquiries' },
];

export const Services = [
  { title: 'Custom Web Apps', text: 'Dashboards, portals, SaaS products and booking systems built with Laravel, React and Vue.', icon: '🖥️' },
  { title: 'Mobile Apps', text: 'iOS and Android apps that share one codebase and talk to your existing systems.', icon: '📱' },
  { title: 'AI Chatbots', text: 'Website and customer support bots trained on your own documents, FAQs and products.', icon: '💬' },
  { title: 'AI Agents & Automation', text: 'Hand repetitive tasks and workflows to AI that follows your rules and reports back.', icon: '✨' },
  { title: 'API Integrations', text: 'Connect OpenAI, Claude, Gemini, payments, CRMs and ERPs to the tools you already use.', icon: '🔌' },
  { title: 'Hosting & Support', text: "Setup, deployment, monitoring and fixes after launch — you're never left on your own.", icon: '🛟' },
];

export const Steps = [
  { title: 'Share your idea', text: 'Tell us what you need and send any notes, designs or documents. We reply with a plan and a clear quote.' },
  { title: 'We design & build it', text: 'You see progress every week on a live preview, so there are no surprises at the end.' },
  { title: 'Launch & support', text: 'Tested, deployed and handed over with full source code — plus support when you need it.' },
];

export const Plans = [
  {
    title: 'Milestone project',
    price: '$0 to start',
    text: 'We plan your project and design the key screens for free, then you pay milestone by milestone — only for work you have approved.',
    points: ['Free start: project plan + key screen designs', 'No upfront payment or deposit', 'Pay per milestone, only after you approve it', 'Fixed total price agreed up front', 'Full source code handed over on final payment'],
    service: 'Milestone project',
    cta: 'Start for free',
    featured: true,
  },
]

export const Faq = [
  { q: 'How long does a project take?', a: 'Most websites take 1–3 weeks and most web apps 3–8 weeks. You get an exact timeline with your quote.' },
  { q: 'Do I own the code?', a: 'Yes. When the project is paid for, all source code, designs and accounts are handed over to you.' },
  { q: 'Can you work on my existing app?', a: 'Yes. We regularly take over, fix and extend apps that other developers started.' },
  { q: 'Which AI models do you use?', a: 'We work with OpenAI, Anthropic Claude and Google Gemini, and pick the one that suits your budget and task.' },
  { q: 'Do you help with hosting?', a: 'Yes. We can set up hosting, domains, email and SSL, or deploy to the server you already have.' },
];

/** Fallback options for the quote form, used until the API answers. */
export const FormDefaults = {
  services: ['Custom web app', 'Mobile app (iOS & Android)', 'AI chatbot', 'AI agent / automation', 'ERP / CRM system', 'MVP for a startup', 'API integration', 'Website / WordPress', 'Shopify store', 'Landing page', 'UI/UX design', 'SEO', 'Hire a developer', 'Hosting & support', 'Milestone project', 'Something else'],
  budgets: ['Not sure yet', 'Under $500', '$500 – $2,000', '$2,000 – $5,000', '$5,000+'],
};
