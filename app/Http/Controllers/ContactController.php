<?php

namespace App\Http\Controllers;

use App\Mail\NewEnquiry;
use App\Mail\QuoteReceived;
use App\Models\ContactMessage;
use App\Support\Recaptcha;
use App\Support\RichText;
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

        // The project box is a rich text editor: clean its HTML first, then check the length of the words.
        $request->merge(['message' => RichText::clean($request->input('message'))]);

        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:100'],
            'email' => ['required', 'email', 'max:150'],
            // Optional. Digits, spaces, + ( ) . - only, with 7 to 15 digits.
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^\+?[\d\s().-]+$/', function ($attr, $value, $fail) {
                $digits = strlen(preg_replace('/\D/', '', $value));
                if ($digits < 7 || $digits > 15) {
                    $fail('Please enter a valid phone number.');
                }
            }],
            'service' => ['nullable', 'string', 'max:60'],
            'budget' => ['nullable', 'string', 'max:60'],
            'message' => ['required', 'string', 'max:20000', function ($attr, $value, $fail) {
                $length = mb_strlen(RichText::plain($value));
                if ($length < 10) {
                    $fail('Please tell us a little more about your project (at least 10 characters).');
                } elseif ($length > 5000) {
                    $fail('Please keep your project description under 5000 characters.');
                }
            }],
            'source' => ['nullable', 'in:contact,popup'],
        ]);

        if (! Recaptcha::passes($request, 'contact')) {
            return redirect()->to(route('home').'#contact')
                ->withErrors(['captcha' => "We couldn't confirm you're not a robot. Please try again."])
                ->withInput();
        }

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
