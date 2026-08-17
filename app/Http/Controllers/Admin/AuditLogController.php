<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class AuditLogController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', AuditLog::class);

        $logs = AuditLog::query()->with(['user', 'auditable'])
            ->where('branch_id', $request->user()->branch_id)
            ->latest()->paginate(20);

        return view('admin.audit.index', compact('logs'));
    }
}
