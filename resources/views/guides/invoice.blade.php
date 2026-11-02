{{-- Guide: how to make an invoice, with the invoice generator. Page: guides/partials/guide. --}}
@extends('layouts.page')

@include('guides.partials.guide', ['g' => [
    'route' => 'guide.invoice',
    'date' => '2026-11-02',
    'time' => 'PT15M',
    'title' => 'How to Make an Invoice and Get Paid on Time — Free Step-by-Step Guide',
    'description' => 'Make a professional invoice in 8 steps: what to put on it, invoice numbers, dates and payment terms, VAT or sales tax, how to get paid, and what to do when a client pays late.',
    'crumb' => 'Invoice generator',
    'alt' => 'Owlvix owl writing an invoice',
    'h1' => 'How to make an invoice and get paid on time',
    'meta' => 'about 15 minutes, free',
    'intro' => '<p>An invoice is a simple request for payment, but small details decide how fast you get paid: a clear number, a due date, the right tax and easy ways to pay. This guide walks you through every part of a good invoice. You can fill yours in as you go with the free <a href="'.e(route('invoice-generator')).'">Owlvix invoice generator</a> — nothing is uploaded, and the PDF is free.</p>',
    'cta' => [
        ['Want to make it as you read?', 'Open the invoice generator in another tab — your details stay on your device.', 'Open the invoice generator'],
        ['Ready to send yours?', 'Free PDF download. No sign-up, no watermark.', 'Make my invoice'],
    ],
    'before' => [
        '<strong>Your business details</strong>: name, address, email and, if you have one, your VAT or tax number.',
        '<strong>Your client’s details</strong>: their company name, address and who should receive it.',
        '<strong>What you did or sold</strong>, with quantities, hours and prices.',
        '<strong>How you want to be paid</strong>: bank details, PayPal or a payment link.',
    ],
    'steps' => [
        ['Put your details and logo at the top', 'Your business name, address, email and phone, plus your logo if you have one. If you’re registered for VAT, GST or sales tax, add your tax number — many countries require it on a tax invoice.'],
        ['Add your client’s details', 'The client’s company or name, their address and, for larger companies, the person or department who pays bills. If they gave you a purchase order (PO) number, add it as a reference — their accounts team often can’t pay without it.'],
        ['Give it a unique invoice number', 'Number invoices in order and never reuse a number, for example INV-0001, INV-0002. It keeps your records clean, makes payments easy to match, and tax offices expect it.',
            'in the Owlvix generator, “New invoice” fills in the next number for you and keeps your business details.'],
        ['Add the date and a due date', 'The invoice date is the day you send it. The due date tells the client exactly when to pay — “14 days” or “30 days” from the invoice date are common. A clear date gets paid faster than “payment due on receipt”.'],
        ['List what you’re charging for', 'One line per product or service, with a clear description, the quantity (or hours) and the price. Clients pay faster when they can see what each amount is for.',
            '“Website design — 5 pages” is clearer than “Design work”.'],
        ['Add discounts, tax and shipping', 'Take off any agreed discount, then add tax at the right rate — VAT, GST or sales tax — or say your prices already include it. Add shipping if you charge for delivery, and show any deposit already paid so the balance is clear.'],
        ['Say how to pay', 'Give your bank details, a PayPal address or a payment link, and ask the client to use the invoice number as the payment reference. The easier you make it, the sooner you’re paid.'],
        ['Save it as a PDF and send it', 'Download your invoice as a PDF so it looks the same on every device, name the file clearly (like “INV-0042-Northwind.pdf”) and email it to the person who pays bills. Keep a copy for your records and your tax return.'],
    ],
    'mistakes' => [
        ['No due date.', 'Without one, your invoice goes to the bottom of the pile. Always give a date.'],
        ['Missing or repeated invoice numbers.', 'Use one numbering series, in order, with no gaps or repeats.'],
        ['Vague descriptions.', 'Say exactly what you did or sold, so nobody has to ask before paying.'],
        ['Forgetting the client’s PO number.', 'Many companies can’t pay an invoice that doesn’t quote it.'],
    ],
    'faqTitle' => 'Invoices, explained',
    'faq' => [
        ['What must an invoice include?', 'Your business name and contact details, the client’s name and address, a unique invoice number, the invoice date, what you’re charging for with prices, the total and any tax. Add a due date and how to pay so you get paid on time. Rules vary by country, so check what your tax office asks for.'],
        ['What’s the difference between an invoice, a quote and a receipt?', 'A quote (or estimate) is a price you offer before the work starts. An invoice asks for payment after you’ve agreed or done the work. A receipt confirms that a payment was made.'],
        ['What is a proforma invoice?', 'A proforma invoice is a preliminary bill sent before the sale is final — often to ask for payment up front or for customs. It isn’t a tax invoice; you send a normal invoice once the sale is complete.'],
        ['Do I need to charge VAT or sales tax?', 'Only if you’re registered for it, or your country’s rules say you must. If you are, show the rate, the tax amount and your tax number on the invoice. If not, don’t add tax.'],
        ['What payment terms should I use?', '14 or 30 days are the most common. For new clients or large jobs, ask for a deposit up front and show it on the final invoice as already paid.'],
        ['What do I do if a client pays late?', 'Send a friendly reminder on the due date, then another a week later with the invoice attached. If it’s still unpaid, call them. In many countries you can also charge interest on late business payments.'],
    ],
]])
