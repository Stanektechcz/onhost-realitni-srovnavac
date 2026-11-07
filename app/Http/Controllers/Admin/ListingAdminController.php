<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Listing;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Přehled všech inzerátů včetně importovaných (source=imported jsou jen interní, nikdy veřejné). */
class ListingAdminController extends Controller
{
    public const SOURCES = ['internal', 'gov', 'imported'];

    public function index(Request $request)
    {
        $f = $request->validate([
            'status' => ['nullable', Rule::in(Listing::STATUSES)],
            'source' => ['nullable', Rule::in(self::SOURCES)],
            'offer_type' => ['nullable', Rule::in(array_keys(config('reality.offer_types')))],
            'q' => ['nullable', 'string', 'max:100'],
            'user' => ['nullable', 'integer'],
        ]);
        $listings = Listing::query()->with('user')
            ->when($f['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->when($f['source'] ?? null, fn ($q, $v) => $q->where('source', $v))
            ->when($f['offer_type'] ?? null, fn ($q, $v) => $q->where('offer_type', $v))
            ->when($f['user'] ?? null, fn ($q, $v) => $q->where('user_id', $v))
            ->when($f['q'] ?? null, fn ($q, $v) => $q->where(fn ($w) => $w->where('title', 'like', "%$v%")->orWhere('city', 'like', "%$v%")->orWhere('district', 'like', "%$v%")))
            ->latest('id')->paginate(30)->withQueryString();

        return view('admin.listings.index', compact('listings', 'f'));
    }

    public function show(Listing $listing)
    {
        $listing->load(['user', 'photos'])->loadCount('reports');
        $audit = AuditLog::with('user')->where('subject_type', $listing->getMorphClass())->where('subject_id', $listing->id)->latest('id')->limit(20)->get();

        return view('admin.listings.show', compact('listing', 'audit'));
    }

    public function deactivate(Request $request, Listing $listing)
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:2000']]);
        $old = $listing->status;
        // DSA čl. 17 – odůvodnění je vidět inzerentovi
        $listing->forceFill(['status' => 'expired', 'expires_at' => now(), 'moderation_note' => 'Deaktivováno správcem: '.$data['reason']])->save();
        AuditLog::record('admin.listing.deactivate', $listing, ['from' => $old, 'reason' => $data['reason']]);

        return back()->with('status', 'Inzerát byl deaktivován.');
    }

    public function extend(Request $request, Listing $listing)
    {
        $data = $request->validate([
            'days' => ['required', 'integer', 'min:1', 'max:365'],
            'reason' => ['required', 'string', 'min:5', 'max:2000'],
        ]);
        $old = $listing->expires_at;
        $base = $old && $old->isFuture() ? $old->copy() : now();
        $listing->forceFill(['expires_at' => $base->addDays((int) $data['days'])])->save();
        AuditLog::record('admin.listing.extend', $listing, [
            'from' => $old?->toIso8601String(), 'to' => $listing->expires_at->toIso8601String(), 'days' => (int) $data['days'], 'reason' => $data['reason'],
        ]);

        return back()->with('status', 'Platnost inzerátu byla prodloužena.');
    }
}
