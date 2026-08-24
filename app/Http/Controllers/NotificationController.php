<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        return $request->user()->notifications()->latest()->paginate(20);
    }

    public function markAsRead(Request $request, string $notificationId)
    {
        $request->user()->notifications()->findOrFail($notificationId)->markAsRead();

        return response()->noContent();
    }

    public function markAllAsRead(Request $request)
    {
        $request->user()->unreadNotifications->markAsRead();

        return response()->noContent();
    }
}