<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AgencyClaim;
use App\Models\AgencyReview;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AgencyAdminController extends Controller
{
    public function reviews()
    {
        $reviews = AgencyReview::with('user:id,name,email', 'agency', 'broker.agency', 'reports')
            ->where(fn ($q) => $q->where('status', 'pending')->orWhere('report_count', '>', 0))
            ->orderByRaw("status = 'pending' desc")->orderByDesc('report_count')->latest()->paginate(30);

        return view('admin.agency-reviews', compact('reviews'));
    }

    public function decideReview(Request $request, AgencyReview $review)
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(['published', 'hidden'])],
            'moderation_note' => [Rule::requiredIf($request->input('status') === 'hidden'), 'nullable', 'string', 'max:500'],
        ]);
        $review->forceFill($data + ['moderated_at' => now()])->save();
        $review->reports()->where('status', 'open')->update(['status' => $data['status'] === 'hidden' ? 'upheld' : 'dismissed']);
        if ($data['status'] === 'published') {
            $review->forceFill(['report_count' => 0])->save();
        }
        $review->refreshTargets();

        return back()->with('status', 'Rozhodnutí uloženo.');
    }

    public function claims()
    {
        $claims = AgencyClaim::with('agency', 'user:id,name,email,role')->where('status', 'pending')->latest()->paginate(30);

        return view('admin.agency-claims', compact('claims'));
    }

    public function decideClaim(Request $request, AgencyClaim $claim)
    {
        abort_unless($claim->status === 'pending', 409);
        $data = $request->validate(['decision' => ['required', Rule::in(['approve', 'reject'])]]);
        if ($data['decision'] === 'approve') {
            $claim->approve('admin');
        } else {
            $claim->forceFill(['status' => 'rejected', 'method' => 'admin', 'decided_at' => now()])->save();
        }

        return back()->with('status', 'Rozhodnutí uloženo.');
    }
}
