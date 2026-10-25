<?php

namespace App\Http\Controllers;

use App\Mail\NewEnquiry;
use App\Mail\QuoteReceived;
use App\Models\ContactMessage;
use Illuminate\Support\Facades\Mail;
use Illuminate\Http\Request;

class ContactController extends Controller
{
    public function store(Request $request)
    {
        // Hidden "website" field: real visitors leave it empty, simple bots fill it in.
        if (filled($request->input('website'))) {
            return redirect()->to(route('home').'#contact')->with('sent', true);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:150'],
            'service' => ['nullable', 'string', 'max:60'],
            'budget' => ['nullable', 'string', 'max:60'],
            'message' => ['required', 'string', 'min:10', 'max:5000'],
            'source' => ['nullable', 'in:contact,popup'],
        ]);

        $enquiry = ContactMessage::create([
            ...$data,
            'source' => $data['source'] ?? 'contact',
            'ip' => $request->ip(),
        ]);

        // Email a thank-you to the visitor and an alert to the site owner.
        // The message is already saved, so a mail problem shouldn't stop the visitor.
        try {
            Mail::to($enquiry->email, $enquiry->name)->send(new QuoteReceived($enquiry));
            if ($owner = config('admin.email')) {
                Mail::to($owner)->send(new NewEnquiry($enquiry));
            }
        } catch (\Throwable $e) {
            report($e);
        }

        return redirect()->to(route('home').'#contact')->with('sent', true);
    }
}
