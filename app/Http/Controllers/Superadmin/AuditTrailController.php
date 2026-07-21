<?php

namespace App\Http\Controllers\Superadmin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;

class AuditTrailController extends Controller
{
    public function index()
    {
        return view('superadmin.audit-trail', [
            'logs' => ActivityLog::with('user')->latest('occurred_at')->paginate(30),
        ]);
    }
}
