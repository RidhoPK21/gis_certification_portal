<?php

namespace App\Http\Controllers;

use App\Models\PortalNotification;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function read(Request $request, PortalNotification $notification)
    {
        abort_unless($notification->user_id === $request->user()->id, 403);
        $notification->update(['read_at' => now()]);

        return $notification->action_url ? redirect($notification->action_url) : back();
    }

    public function readAll(Request $request)
    {
        PortalNotification::where('user_id', $request->user()->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return back();
    }
}
