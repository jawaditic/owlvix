{{-- Guide: how to write a CV, with the resume / CV builder. Page: guides/partials/guide. --}}
@extends('layouts.page')

@include('guides.partials.guide', ['g' => [
    'route' => 'guide.cv',
    'date' => '2026-11-01',
    'time' => 'PT1H',
    'title' => 'How to Write a CV That Gets Interviews — Free Step-by-Step Guide',
    'description' => 'Write a CV that gets interviews in 8 steps: the right layout, a short profile, results-based bullet points, skills that pass ATS scans, education, and saving it as a PDF.',
    'crumb' => 'Resume / CV builder',
    'alt' => 'Owlvix owl writing a CV',
    'h1' => 'How to write a CV that gets interviews',
    'meta' => 'about 1 hour, free',
    'intro' => '<p>Recruiters often spend less than a minute on a CV before deciding whether to read on. A good CV makes that minute easy: a clear layout, a short profile, and bullet points that show what you achieved, not just what you did. This guide walks you through it section by section. You can write as you go in the free <a href="'.e(route('resume-builder')).'">Owlvix CV builder</a> — your details stay on your device.</p>',
    'cta' => [
        ['Want to write as you read?', 'Open the CV builder in another tab — nothing is uploaded.', 'Open the CV builder'],
        ['Ready to write yours?', 'Free PDF download. No sign-up, no watermark.', 'Write my CV'],
    ],
    'before' => [
        '<strong>The job advert</strong> you’re applying for — you’ll use its words.',
        '<strong>Your jobs from the last 10 years</strong>, with dates and job titles.',
        '<strong>Numbers you can share</strong>: sales, savings, customers, team size, time saved.',
        '<strong>Your education</strong> and any courses or certificates.',
    ],
    'steps' => [
        ['Pick a clean, simple layout', 'Choose a design with clear headings and plenty of white space. One column reads best for most jobs; a sidebar works well if you have many skills or languages.',
            'creative jobs can use more colour, but for most roles a simple design with one accent colour looks most professional.'],
        ['Put your contact details at the top', 'Your name, phone, email, town or city, and a LinkedIn or portfolio link. You don’t need your full address, date of birth or a photo in most countries.'],
        ['Write a short profile', 'Two or three lines on who you are, what you’re good at and what you want next. Write it for this job: “Customer service lead with 6 years in retail, looking to move into operations.”',
            'skip clichés like “hard-working team player” — show it in your bullet points instead.'],
        ['List your experience, newest first', 'For each job, add the title, company, dates and 3–5 bullet points. Start each with an action word (led, built, cut, grew) and add a result with a number where you can.',
            '“Cut stock waste by 18% by changing the weekly order” beats “Responsible for stock ordering”.'],
        ['Add skills that match the job', 'List 6–10 skills, using the exact words from the job advert where they’re true for you. Many companies use applicant tracking systems (ATS) that look for those words.'],
        ['Add education and extras', 'Add your highest qualifications, then any courses, certificates, languages or volunteering that help for this job. If you’ve just left school or university, put education before experience.'],
        ['Keep it short and check it', 'Aim for one page if you have under 10 years’ experience, two at most. Read it aloud, check every date, and ask a friend to look for typos.'],
        ['Save it as a PDF and name it well', 'Download your CV as a PDF so it looks the same on every computer. Name the file with your name, like “Sara-Khan-CV.pdf”, and change the profile and skills for each job you apply for.'],
    ],
    'mistakes' => [
        ['Listing duties instead of results.', 'Say what changed because of you, with numbers where you can.'],
        ['One CV for every job.', 'Change your profile and skills to match each advert — it takes ten minutes.'],
        ['Tables, text boxes or images for text.', 'Some ATS software can’t read them. Use real text with clear headings.'],
        ['Unprofessional email address.', 'Use a plain one with your name in it.'],
    ],
    'faqTitle' => 'CVs, explained',
    'faq' => [
        ['What’s the difference between a CV and a resume?', 'In the UK, Ireland, Europe and much of Asia, a “CV” is the short document you send for most jobs. In the US and Canada that’s called a “resume”, and a “CV” means a longer academic record. The tips here work for both.'],
        ['How long should a CV be?', 'One page if you have under 10 years’ experience, two pages for most others. Academic and medical CVs can be longer.'],
        ['Should I put a photo on my CV?', 'In the UK, US, Canada and Australia most employers prefer no photo. In much of Europe, the Middle East and Asia a professional photo is common. Follow what’s normal where you’re applying.'],
        ['What is an ATS?', 'An applicant tracking system is software many companies use to sort CVs. It reads the text and looks for skills and job titles from the advert, so use those words and avoid putting text inside images.'],
        ['Should I include every job I’ve had?', 'Focus on the last 10–15 years and the jobs that matter for this role. Older or unrelated jobs can go in one line, like “Earlier roles in hospitality, 2008–2013”.'],
        ['Do I need a cover letter?', 'If the advert asks for one, yes. Even when it doesn’t, a short note on why you want this job and what you’d bring can help you stand out.'],
    ],
]])
