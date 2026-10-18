<?php

namespace App\Http\Controllers;

use App\Models\Favorite;
use App\Models\Listing;
use App\Models\Message;
use App\Models\SavedSearch;
use Illuminate\Http\Request;

class AccountController extends Controller
{
    public function dashboard(Request $request)
    {
        $u = $request->user();

        return view('account.dashboard', [
            'listingCount' => $u->listings()->count(),
            'favoriteCount' => $u->favorites()->count(),
            'searchCount' => $u->savedSearches()->count(),
            'unread' => $u->receivedMessages()->whereNull('read_at')->count(),
            'viewingCount' => \App\Models\ViewingSlot::where('booked_by', $u->id)->where('starts_at', '>', now())->count()
                + \App\Models\ViewingSlot::whereIn('listing_id', $u->listings()->select('id'))->whereNotNull('booked_by')->where('starts_at', '>', now())->count(),
            'listings' => $u->listings()->with('photos')->latest()->limit(5)->get(),
            'views30' => \App\Models\ListingStat::whereIn('listing_id', $u->listings()->select('id'))->where('day', '>=', now()->subDays(30)->toDateString())->sum('views'),
        ]);
    }

    public function favorites(Request $request)
    {
        $listings = Listing::public()->whereIn('id', $request->user()->favorites()->select('listing_id'))->with('photos')->paginate(24);
        $notes = $request->user()->favorites()->pluck('note', 'listing_id');

        return view('account.favorites', compact('listings', 'notes'));
    }

    public function favoriteNote(Request $request, Listing $listing)
    {
        $data = $request->validate(['note' => ['nullable', 'string', 'max:1000']]);
        $fav = $request->user()->favorites()->where('listing_id', $listing->id)->firstOrFail();
        $fav->update(['note' => $data['note']]);

        return back()->with('status', 'Poznámka uložena.');
    }

    public function toggleFavorite(Request $request, Listing $listing)
    {
        abort_unless($listing->isPublic(), 404);
        $fav = Favorite::where('user_id', $request->user()->id)->where('listing_id', $listing->id)->first();
        if ($fav) {
            $fav->delete();
        } else {
            Favorite::create(['user_id' => $request->user()->id, 'listing_id' => $listing->id]);
            \App\Models\ListingStat::bump($listing, 'saves');
        }

        return back()->with('status', $fav ? 'Odebráno z oblíbených.' : 'Přidáno do oblíbených.');
    }

    public function searches(Request $request)
    {
        return view('account.searches', ['searches' => $request->user()->savedSearches()->latest()->get()]);
    }

    public function storeSearch(Request $request)
    {
        $request->validate(['name' => ['nullable', 'string', 'max:100']]);
        $filters = array_filter($request->validate(SearchController::filterRules()), fn ($v) => $v !== null && $v !== '' && $v !== []);
        unset($filters['sort']);
        abort_if($request->user()->savedSearches()->count() >= 20, 422, 'Maximálně 20 hlídacích psů.');
        $request->user()->savedSearches()->create([
            'name' => $request->input('name') ?: 'Hledání '.now()->format('j. n. Y'),
            'filters' => $filters,
            'last_notified_at' => now(),
        ]);

        return redirect()->route('account.searches')->with('status', 'Hlídací pes byl uložen. Nové inzeráty vám pošleme e-mailem.');
    }

    public function destroySearch(Request $request, SavedSearch $search)
    {
        abort_unless($search->user_id === $request->user()->id, 403);
        $search->delete();

        return back()->with('status', 'Hlídací pes byl smazán.');
    }

    public function messages(Request $request)
    {
        $box = $request->query('box') === 'archiv' ? 'archiv' : 'inbox';
        $q = $request->user()->receivedMessages()->with('listing')->latest();
        $box === 'archiv' ? $q->whereNotNull('archived_at') : $q->whereNull('archived_at');
        if ($request->filled('listing')) {
            $q->whereHas('listing', fn ($w) => $w->where('slug', $request->query('listing')));
        }
        $messages = $q->paginate(20)->withQueryString();
        $unreadIds = $messages->getCollection()->whereNull('read_at')->pluck('id');
        $request->user()->receivedMessages()->whereIn('id', $unreadIds)->update(['read_at' => now()]);
        $listings = $request->user()->listings()->whereHas('messages')->withCount('messages')->get();

        return view('account.messages', compact('messages', 'box', 'unreadIds', 'listings'));
    }

    public function archiveMessage(Request $request, \App\Models\Message $message)
    {
        abort_unless($message->recipient_id === $request->user()->id, 403);
        $message->forceFill(['archived_at' => $message->archived_at ? null : now()])->save();

        return back()->with('status', $message->archived_at ? 'Zpráva archivována.' : 'Zpráva obnovena.');
    }
}
