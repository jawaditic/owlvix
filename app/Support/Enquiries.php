<?php

namespace App\Support;

use App\Mail\NewEnquiry;
use App\Mail\QuoteReceived;
use App\Models\ContactMessage;
use Illuminate\Support\Facades\Mail;

/** A new enquiry from the website forms or the mobile app: the rules it must pass, and what happens once it's in. */
class Enquiries
{
    public const SERVICES = ['Web app', 'Mobile app', 'AI chatbot / agent', 'Website', 'Fixed project', 'Monthly developer', 'Something else'];

    public const BUDGETS = Stats\DashboardStats::BUDGETS;

    public const SOURCES = ['contact', 'popup', 'app', 'landing'];

    /** Validation rules. The message must already be cleaned with RichText::clean(). */
    public static function rules(): array
    {
        return [
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
            'source' => ['nullable', 'in:'.implode(',', self::SOURCES)],
        ];
    }

    /** Save the enquiry, then email the visitor and the owner and alert the admin app. */
    public static function create(array $data, ?string $ip): ContactMessage
    {
        $enquiry = ContactMessage::create([
            ...$data,
            'source' => $data['source'] ?? 'contact',
            'ip' => $ip,
        ]);

        // The message is already saved, so a mail or push problem shouldn't stop the visitor.
        try {
            Mail::to($enquiry->email, $enquiry->name)->send(new QuoteReceived($enquiry));
            if ($owner = config('admin.email')) {
                Mail::to($owner)->send(new NewEnquiry($enquiry));
            }
        } catch (\Throwable $e) {
            report($e);
        }

        Push::toAdmins(
            'New enquiry: '.$enquiry->name,
            trim(($enquiry->service ? $enquiry->service.' · ' : '').mb_strimwidth($enquiry->messageText(), 0, 120, '…')),
            ['type' => 'enquiry', 'id' => $enquiry->id],
        );

        return $enquiry;
    }
}
