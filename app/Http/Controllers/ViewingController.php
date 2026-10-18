<?php

namespace App\Http\Controllers;

use App\Models\ListingStat;
use App\Models\ViewingSlot;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ViewingController extends Controller
{
    public function index(Request $request)
    {
        $u = $request->user();
        $own = ViewingSlot::whereIn('listing_id', $u->listings()->select('id'))->where('starts_at', '>', now()->subDay())
            ->with('listing')->orderBy('starts_at')->get();
        $mine = ViewingSlot::where('booked_by', $u->id)->with('listing')->orderBy('starts_at')->get();

        return view('account.viewings', compact('own', 'mine'));
    }

    public function book(Request $request, ViewingSlot $slot)
    {
        $listing = $slot->listing;
        abort_unless($listing->isPublic(), 404);
        abort_if($listing->user_id === $request->user()->id, 403);
        $data = $request->validate(['name' => ['required', 'string', 'max:100'], 'phone' => ['nullable', 'string', 'max:30'], 'note' => ['nullable', 'string', 'max:500']]);
        abort_if(ViewingSlot::where('listing_id', $listing->id)->where('booked_by', $request->user()->id)->where('starts_at', '>', now())->exists(), 422, 'Už máte rezervovanou prohlídku tohoto inzerátu.');

        // Atomic claim: only succeeds if the slot is still free.
        $ok = DB::table('viewing_slots')->where('id', $slot->id)->whereNull('booked_by')->where('starts_at', '>', now())->update([
            'booked_by' => $request->user()->id, 'booked_name' => $data['name'], 'booked_phone' => $data['phone'] ?? null,
            'booked_note' => $data['note'] ?? null, 'booked_at' => now(), 'updated_at' => now(),
        ]);
        if (! $ok) {
            return back()->withErrors(['slot' => 'Termín už je obsazený, vyberte prosím jiný.']);
        }
        ListingStat::bump($listing, 'bookings');

        return back()->with('status', 'Prohlídka je rezervována na '.$slot->starts_at->format('j. n. Y H:i').'.');
    }

    public function cancel(Request $request, ViewingSlot $slot)
    {
        $u = $request->user();
        $isOwner = $slot->listing->user_id === $u->id;
        abort_unless($slot->booked_by === $u->id || $isOwner, 403);
        $slot->forceFill(['booked_by' => null, 'booked_name' => null, 'booked_phone' => null, 'booked_note' => null, 'booked_at' => null])->save();

        return back()->with('status', 'Rezervace byla zrušena.');
    }
}
