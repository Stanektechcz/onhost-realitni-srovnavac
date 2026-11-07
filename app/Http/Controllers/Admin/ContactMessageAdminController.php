<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\PagesController;
use App\Models\AuditLog;
use App\Models\ContactMessage;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Správa zpráv z kontaktního formuláře: přehled, detail, označení jako vyřízené. */
class ContactMessageAdminController extends Controller
{
    public function index(Request $request)
    {
        $f = $request->validate([
            'stav' => ['nullable', Rule::in(['nove', 'vyrizene'])],
            'tema' => ['nullable', Rule::in(array_keys(PagesController::TOPICS))],
        ]);
        $messages = ContactMessage::query()
            ->when(($f['stav'] ?? null) === 'nove', fn ($q) => $q->whereNull('handled_at'))
            ->when(($f['stav'] ?? null) === 'vyrizene', fn ($q) => $q->whereNotNull('handled_at'))
            ->when($f['tema'] ?? null, fn ($q, $v) => $q->where('topic', $v))
            ->latest('id')->paginate(30)->withQueryString();

        return view('admin.contact.index', ['messages' => $messages, 'f' => $f, 'topics' => PagesController::TOPICS]);
    }

    public function show(ContactMessage $message)
    {
        return view('admin.contact.show', ['m' => $message, 'topics' => PagesController::TOPICS]);
    }

    public function status(Request $request, ContactMessage $message)
    {
        $data = $request->validate(['action' => ['required', Rule::in(['handled', 'open'])]]);
        $handled = $data['action'] === 'handled';
        if ($handled !== ($message->handled_at !== null)) {
            $message->forceFill(['handled_at' => $handled ? now() : null])->save();
            AuditLog::record($handled ? 'admin.contact.handled' : 'admin.contact.reopened', $message);
        }

        return back()->with('status', $handled ? 'Zpráva označena jako vyřízená.' : 'Zpráva vrácena mezi nevyřízené.');
    }
}
