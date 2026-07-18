<?php

namespace App\Http\Controllers;

use App\Models\PortalNotification;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        return view('notifications.index', [
            'notifications' => PortalNotification::where('user_id', $request->user()->id)
                ->latest()
                ->paginate(20),
        ]);
    }

    public function read(Request $request, PortalNotification $notification)
    {
        abort_unless($notification->user_id === $request->user()->id, 403);
        $notification->update(['read_at' => now()]);

        return $notification->action_url ? redirect($notification->action_url) : back();
    }
}
