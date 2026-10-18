<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Listing;
use App\Models\Report;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AdminController extends Controller
{
    public function dashboard()
    {
        return view('admin.dashboard', [
            'pending' => Listing::where('status', 'pending')->count(),
            'active' => Listing::public()->count(),
            'openReports' => Report::where('status', 'open')->count(),
            'users' => User::count(),
            'imported' => Listing::imported()->count(),
            'audit' => AuditLog::with('user')->latest('id')->limit(15)->get(),
        ]);
    }

    public function moderation()
    {
        $listings = Listing::where('source', 'internal')->where('status', 'pending')->with(['user', 'photos'])->oldest('updated_at')->paginate(20);

        return view('admin.moderation', compact('listings'));
    }

    public function decide(Request $request, Listing $listing)
    {
        abort_unless($listing->source === 'internal', 404);
        $data = $request->validate([
            'decision' => ['required', Rule::in(['approve', 'reject'])],
            'reason' => ['required_if:decision,reject', 'nullable', 'string', 'max:2000'],
        ]);
        if ($data['decision'] === 'approve') {
            $listing->status = 'active';
            $listing->published_at = now();
            $listing->expires_at = now()->addDays(config('reality.listing_lifetime_days'));
            $listing->moderation_note = null;
        } else {
            $listing->status = 'rejected';
            $listing->moderation_note = $data['reason']; // DSA čl. 17 — odůvodnění pro inzerenta
        }
        $listing->save();
        AuditLog::record('listing.'.$data['decision'], $listing, ['reason' => $data['reason'] ?? null]);

        return back()->with('status', $data['decision'] === 'approve' ? 'Inzerát schválen.' : 'Inzerát zamítnut s odůvodněním.');
    }

    public function reports()
    {
        $reports = Report::with(['listing', 'reporter'])->orderByRaw("status = 'open' desc")->latest()->paginate(20);

        return view('admin.reports', compact('reports'));
    }

    public function resolveReport(Request $request, Report $report)
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(['upheld', 'dismissed'])],
            'decision_reason' => ['required', 'string', 'min:5', 'max:2000'],
            'take_down' => ['boolean'],
        ]);
        $report->forceFill([
            'status' => $data['status'], 'decision_reason' => $data['decision_reason'],
            'decided_by' => $request->user()->id, 'decided_at' => now(),
        ])->save();
        if ($data['status'] === 'upheld' && $request->boolean('take_down')) {
            $report->listing->forceFill(['status' => 'rejected', 'moderation_note' => 'Odstraněno na základě nahlášení: '.$data['decision_reason']])->save();
        }
        AuditLog::record('report.'.$data['status'], $report, ['take_down' => $request->boolean('take_down')]);

        return back()->with('status', 'Nahlášení bylo vyřízeno.');
    }

    /** Internal price statistics from imported (scraped) data. Never exposed publicly. */
    public function stats()
    {
        $rows = Listing::imported()->where('offer_type', 'rent')->whereNotNull('price_halere')
            ->select('city', 'district', 'disposition',
                DB::raw('count(*) as n'),
                DB::raw('avg(price_halere) as avg_price'),
                DB::raw('min(price_halere) as min_price'),
                DB::raw('max(price_halere) as max_price'),
                DB::raw('avg(case when area_m2 > 0 then price_halere / area_m2 end) as avg_per_m2'))
            ->groupBy('city', 'district', 'disposition')->orderBy('city')->orderBy('district')->orderBy('disposition')->get();
        $internal = Listing::public()->where('offer_type', 'rent')->select('disposition', DB::raw('count(*) as n'), DB::raw('avg(price_halere) as avg_price'))
            ->groupBy('disposition')->orderBy('disposition')->get();

        return view('admin.stats', compact('rows', 'internal'));
    }
}
