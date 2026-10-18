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
        ]);
    }

    public function favorites(Request $request)
    {
        $listings = Listing::public()->whereIn('id', $request->user()->favorites()->select('listing_id'))->with('photos')->paginate(12);

        return view('account.favorites', compact('listings'));
    }

    public function toggleFavorite(Request $request, Listing $listing)
    {
        abort_unless($listing->isPublic(), 404);
        $fav = Favorite::where('user_id', $request->user()->id)->where('listing_id', $listing->id)->first();
        $fav ? $fav->delete() : Favorite::create(['user_id' => $request->user()->id, 'listing_id' => $listing->id]);

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
        $messages = $request->user()->receivedMessages()->with('listing')->latest()->paginate(20);
        $request->user()->receivedMessages()->whereNull('read_at')->update(['read_at' => now()]);

        return view('account.messages', compact('messages'));
    }
}
