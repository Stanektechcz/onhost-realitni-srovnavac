<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class AuditLogController extends Controller
{
    public function index(Request $request)
    {
        $f = $request->validate([
            'action' => ['nullable', 'string', 'max:60'],
            'user' => ['nullable', 'string', 'max:100'],
            'subject_type' => ['nullable', 'string', 'max:120'],
            'subject_id' => ['nullable', 'integer'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);
        $logs = AuditLog::with('user')
            ->when($f['action'] ?? null, fn ($q, $v) => $q->where('action', 'like', "$v%"))
            ->when($f['user'] ?? null, fn ($q, $v) => $q->whereHas('user', fn ($u) => $u->where('email', 'like', "%$v%")))
            ->when($f['subject_type'] ?? null, fn ($q, $v) => $q->where('subject_type', 'like', "%$v"))
            ->when($f['subject_id'] ?? null, fn ($q, $v) => $q->where('subject_id', $v))
            ->when($f['from'] ?? null, fn ($q, $v) => $q->where('created_at', '>=', $v))
            ->when($f['to'] ?? null, fn ($q, $v) => $q->where('created_at', '<', Carbon::parse($v)->addDay()))
            ->latest('id')->paginate(50)->withQueryString();
        $actions = AuditLog::query()->distinct()->orderBy('action')->pluck('action');

        return view('admin.audit', compact('logs', 'f', 'actions'));
    }
}
