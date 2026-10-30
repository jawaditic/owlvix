<?php

namespace App\Http\Controllers;

use App\Support\Enquiries;
use App\Support\Recaptcha;
use App\Support\RichText;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

class ContactController extends Controller
{
    public function store(Request $request)
    {
        // Back to the form they used: the homepage, /services or a service page (e.g. /ai-chatbot-development)
        $back = route('home');
        if ($request->input('source') === 'landing') {
            $path = trim((string) parse_url(url()->previous(), PHP_URL_PATH), '/');
            $back = array_key_exists($path, config('service_pages')) ? route('service.'.$path) : route('landing');
        }
        $back .= '#contact';
        // The popup works on every page: go back to that page, where it reopens with the errors
        if ($request->input('source') === 'popup') {
            $previous = url()->previous();
            $back = parse_url($previous, PHP_URL_HOST) === $request->getHost() ? strtok($previous, '#') : route('home');
        }

        // Hidden "website" field: real visitors leave it empty, simple bots fill it in.
        if (filled($request->input('website'))) {
            return redirect()->to($back)->with('sent_fake', true); // looks sent, but isn't counted as a lead
        }

        // The project box is a rich text editor: clean its HTML first, then check the length of the words.
        $message = $request->input('message');
        $request->merge(['message' => RichText::clean(is_string($message) ? $message : null)]);

        $data = $request->validate(Enquiries::rules());

        if (! Recaptcha::passes($request, 'contact')) {
            return redirect()->to($back)
                ->withErrors(['captcha' => "We couldn't confirm you're not a robot. Please try again."])
                ->withInput();
        }

        $enquiry = Enquiries::create($data, $request->ip());

        // Show the thank-you page (the owl talks there). The flash data lets analytics count the lead on it.
        return redirect()->to(URL::temporarySignedRoute('thanks', now()->addDays(30), $enquiry))
            ->with('sent', true)->with('lead_source', $enquiry->source)->with('lead_service', $enquiry->service);
    }
}
