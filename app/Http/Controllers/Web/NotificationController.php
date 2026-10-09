<?php

namespace App\Http\Controllers\Web;

use App\Domains\Notifications\Services\RecordNotificationService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;

class NotificationController extends Controller
{
    public function index(Request $r)
    {
        $f = $r->validate(['read' => 'nullable|in:unread,read']);
        $q = $r->user()->notifications()->when(($f['read'] ?? null) === 'unread', fn ($q) => $q->whereNull('read_at'))->when(($f['read'] ?? null) === 'read', fn ($q) => $q->whereNotNull('read_at'));

        return Inertia::render('Notifications/Index', ['notifications' => $q->paginate(15)->withQueryString()->through(fn ($n) => app(RecordNotificationService::class)->safe($n, $r->user())), 'filters' => $f]);
    }

    public function read(Request $r, string $notification)
    {
        $n = $r->user()->notifications()->whereKey($notification)->firstOrFail();
        $n->markAsRead();

        return back(303);
    }

    public function readAll(Request $r)
    {
        $r->user()->unreadNotifications()->update(['read_at' => now()]);

        return back(303);
    }

    public function preferences()
    {
        return Inertia::render('Account/Notifications');
    }
}
