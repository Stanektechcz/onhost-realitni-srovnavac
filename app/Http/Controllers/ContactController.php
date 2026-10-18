<?php

namespace App\Http\Controllers;

use App\Mail\ListingInquiry;
use App\Models\Listing;
use App\Models\Message;
use App\Models\Report;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ContactController extends Controller
{
    public function message(Request $request, Listing $listing)
    {
        abort_unless($listing->isPublic() && $listing->user_id, 404);
        // Honeypot: bots fill the hidden "website" field — pretend success, store nothing.
        if (filled($request->input('website'))) {
            return back()->with('status', 'Zpráva byla odeslána.');
        }
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:190'],
            'phone' => ['nullable', 'string', 'max:30'],
            'body' => ['required', 'string', 'min:10', 'max:3000'],
            'gdpr' => ['accepted'],
        ]);
        $msg = Message::create([
            'listing_id' => $listing->id,
            'sender_id' => $request->user()?->id,
            'recipient_id' => $listing->user_id,
            'sender_name' => $data['name'],
            'sender_email' => $data['email'],
            'sender_phone' => $data['phone'] ?? null,
            'body' => $data['body'],
            'relay_token' => Str::random(32),
            'ip' => $request->ip(),
        ]);
        \App\Models\ListingStat::bump($listing, 'inquiries');
        Mail::to($listing->user->email)->queue(new ListingInquiry($msg));

        return back()->with('status', 'Zpráva byla odeslána. Inzerent uvidí jen maskovanou adresu, odpověď přijde přes portál.');
    }

    public function report(Request $request, Listing $listing)
    {
        abort_unless($listing->isPublic(), 404);
        if (filled($request->input('website'))) {
            return back()->with('status', 'Děkujeme za nahlášení.');
        }
        $data = $request->validate([
            'reason' => ['required', Rule::in(array_keys(config('reality.report_reasons')))],
            'description' => ['required', 'string', 'min:10', 'max:3000'],
            'email' => [Rule::requiredIf(! $request->user()), 'nullable', 'email', 'max:190'],
            'good_faith' => ['accepted'],
        ]);
        Report::create([
            'listing_id' => $listing->id,
            'reporter_id' => $request->user()?->id,
            'reporter_email' => $data['email'] ?? $request->user()?->email,
            'reason' => $data['reason'],
            'description' => $data['description'],
        ]);

        return back()->with('status', 'Děkujeme za nahlášení. O výsledku posouzení vás budeme informovat.');
    }
}
