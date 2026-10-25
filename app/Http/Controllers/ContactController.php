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
        // Hidden "website" field: real visitors leave it empty, simple bots fill it in.
        if (filled($request->input('website'))) {
            return redirect()->to(route('home').'#contact')->with('sent', true);
        }

        // The project box is a rich text editor: clean its HTML first, then check the length of the words.
        $request->merge(['message' => RichText::clean($request->input('message'))]);

        $data = $request->validate(Enquiries::rules());

        if (! Recaptcha::passes($request, 'contact')) {
            return redirect()->to(route('home').'#contact')
                ->withErrors(['captcha' => "We couldn't confirm you're not a robot. Please try again."])
                ->withInput();
        }

        $enquiry = Enquiries::create($data, $request->ip());

        // Show the thank-you page (the owl talks there). The flash data lets analytics count the lead on it.
        return redirect()->to(URL::signedRoute('thanks', $enquiry))
            ->with('sent', true)->with('lead_source', $enquiry->source)->with('lead_service', $enquiry->service);
    }
}
